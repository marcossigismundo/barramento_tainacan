<?php
/**
 * Plugin Name: Barramento Tainacan
 * Plugin URI: https://github.com/seu-usuario/barramento-tainacan
 * Description: Integração do repositório digital Tainacan ao sistema de preservação digital Archivematica
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seu-site.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: barramento-tainacan
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.1
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Definindo constantes
define('BARRAMENTO_TAINACAN_VERSION', '1.0.0');
define('BARRAMENTO_TAINACAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BARRAMENTO_TAINACAN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BARRAMENTO_TAINACAN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Verifica a dependência com o Tainacan
 * 
 * @return bool True se o Tainacan estiver ativo, False caso contrário
 */
function barramento_check_dependency() {
    if (class_exists('Tainacan\Repositories\Repository')) {
        return true;
    }
    
    // Para multisite
    if (function_exists('is_plugin_active')) {
        if (is_plugin_active('tainacan/tainacan.php')) {
            return true;
        }
    } else {
        $active_plugins = get_option('active_plugins', array());
        if (in_array('tainacan/tainacan.php', $active_plugins)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Código executado durante a ativação do plugin.
 */
function activate_barramento_tainacan() {
    // Inicialização básica
    add_option('barramento_version', BARRAMENTO_TAINACAN_VERSION);
    add_option('barramento_activation_time', time());
    
    // Verifica dependência
    if (!barramento_check_dependency()) {
        // Interrompe a ativação
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('O plugin Barramento Tainacan requer o plugin Tainacan instalado e ativado.', 'barramento-tainacan'),
            'Plugin Dependency Error',
            array('back_link' => true)
        );
    }
    
    // Criação de tabelas no banco
    global $wpdb;
    
    // Tabela de fila
    $queue_table = $wpdb->prefix . 'barramento_queue';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $queue_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        collection_id bigint(20) NOT NULL,
        item_id bigint(20) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        priority int(11) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        scheduled_date datetime DEFAULT NULL,
        batch_id varchar(36) DEFAULT NULL,
        retries int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY collection_id (collection_id),
        KEY item_id (item_id),
        KEY status (status),
        KEY batch_id (batch_id)
    ) $charset_collate;";
    
    // Tabela de logs
    $logs_table = $wpdb->prefix . 'barramento_logs';
    $sql .= "CREATE TABLE IF NOT EXISTS $logs_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        level varchar(20) NOT NULL DEFAULT 'info',
        message text NOT NULL,
        context longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        item_id bigint(20) DEFAULT NULL,
        collection_id bigint(20) DEFAULT NULL,
        batch_id varchar(36) DEFAULT NULL,
        aip_id varchar(255) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY level (level),
        KEY created_at (created_at),
        KEY item_id (item_id),
        KEY collection_id (collection_id),
        KEY batch_id (batch_id),
        KEY aip_id (aip_id)
    ) $charset_collate;";
    
    // Tabela de objetos
    $objects_table = $wpdb->prefix . 'barramento_objects';
    $sql .= "CREATE TABLE IF NOT EXISTS $objects_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        collection_id bigint(20) NOT NULL,
        item_id bigint(20) NOT NULL,
        tainacan_status varchar(20) NOT NULL DEFAULT 'published',
        sip_id varchar(255) DEFAULT NULL,
        aip_id varchar(255) DEFAULT NULL,
        dip_id varchar(255) DEFAULT NULL,
        preservation_status varchar(50) DEFAULT 'not_preserved',
        transfer_status varchar(50) DEFAULT NULL,
        ingest_status varchar(50) DEFAULT NULL,
        sip_creation_date datetime DEFAULT NULL,
        aip_creation_date datetime DEFAULT NULL,
        last_status_update datetime DEFAULT NULL,
        archivematica_url text DEFAULT NULL,
        batch_id varchar(36) DEFAULT NULL,
        notes text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY collection_id (collection_id),
        KEY item_id (item_id),
        KEY preservation_status (preservation_status),
        KEY sip_id (sip_id(191)),
        KEY aip_id (aip_id(191)),
        KEY batch_id (batch_id),
        UNIQUE KEY item_unique (item_id)
    ) $charset_collate;";
    
    // Tabela de hashes
    $hashes_table = $wpdb->prefix . 'barramento_hashes';
    $sql .= "CREATE TABLE IF NOT EXISTS $hashes_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        object_id mediumint(9) NOT NULL,
        item_id bigint(20) NOT NULL,
        hash_type varchar(20) NOT NULL DEFAULT 'sha256',
        hash_value varchar(255) NOT NULL,
        file_path text DEFAULT NULL,
        file_size bigint(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        verification_date datetime DEFAULT NULL,
        verification_status varchar(20) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY object_id (object_id),
        KEY item_id (item_id),
        KEY hash_type (hash_type),
        KEY hash_value (hash_value(191))
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Configurações padrão
    if (!get_option('barramento_max_sip_size')) {
        add_option('barramento_max_sip_size', 1024); // 1GB
    }
    
    if (!get_option('barramento_schedule_frequency')) {
        add_option('barramento_schedule_frequency', 'daily');
    }
    
    if (!get_option('barramento_hash_algorithm')) {
        add_option('barramento_hash_algorithm', 'sha256');
    }
    
    if (!get_option('barramento_retry_attempts')) {
        add_option('barramento_retry_attempts', 3);
    }
    
    if (!get_option('barramento_debug_mode')) {
        add_option('barramento_debug_mode', false);
    }
    
    // Cria diretórios
    $upload_dir = wp_upload_dir();
    $dirs = array(
        trailingslashit($upload_dir['basedir']) . 'barramento-temp',
        trailingslashit($upload_dir['basedir']) . 'barramento-sips'
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            // Adiciona proteção
            file_put_contents($dir . '/index.php', '<?php // Silence is golden.');
        }
    }
    
    // Configura cron
    if (!wp_next_scheduled('barramento_tainacan_cron_event')) {
        wp_schedule_event(time(), 'daily', 'barramento_tainacan_cron_event');
    }
}

/**
 * Código executado durante a desativação do plugin.
 */
function deactivate_barramento_tainacan() {
    // Remove o evento cron
    wp_clear_scheduled_hook('barramento_tainacan_cron_event');
    
    // Registra o timestamp de desativação
    update_option('barramento_deactivation_time', time());
}

// Registra os hooks de ativação e desativação
register_activation_hook(__FILE__, 'activate_barramento_tainacan');
register_deactivation_hook(__FILE__, 'deactivate_barramento_tainacan');

/**
 * Exibe uma notificação administrativa sobre a dependência ausente.
 */
function barramento_tainacan_missing_dependency_notice() {
    ?>
    <div class="error">
        <p><?php _e('O plugin Barramento Tainacan requer o plugin Tainacan instalado e ativado.', 'barramento-tainacan'); ?></p>
    </div>
    <?php
}

/**
 * Adiciona link para as configurações na página de plugins.
 */
function barramento_tainacan_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=barramento-tainacan-settings') . '">' . __('Configurações', 'barramento-tainacan') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'barramento_tainacan_plugin_action_links');

/**
 * Inicialização principal do plugin
 */
function barramento_tainacan_init() {
    // Verifica se o Tainacan está ativo
    if (!barramento_check_dependency()) {
        // Adiciona notificação de erro
        add_action('admin_notices', 'barramento_tainacan_missing_dependency_notice');
        return;
    }
    
    // Carrega arquivos essenciais
    $files = array(
        'includes/class-barramento-tainacan.php',
        'includes/class-db-manager.php',
        'includes/class-tainacan-api.php', 
        'includes/class-archivematica-api.php',
        'includes/class-sip-generator.php',
        'includes/class-hash-manager.php',
        'includes/class-logger.php',
        'includes/class-validator.php',
        'includes/class-processor.php',
        'admin/class-admin-menu.php',
        'admin/class-admin-settings.php',
        'public/class-public-display.php'
    );
    
    foreach ($files as $file) {
        $full_path = BARRAMENTO_TAINACAN_PLUGIN_DIR . $file;
        if (file_exists($full_path)) {
            require_once $full_path;
        }
    }
    
    // Inicializa o plugin principal se a classe existir
    if (class_exists('Barramento_Tainacan')) {
        $plugin = new Barramento_Tainacan();
        $plugin->run();
    }
    
    // Carrega traduções
    load_plugin_textdomain('barramento-tainacan', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Inicializa o plugin após o carregamento do WordPress
add_action('plugins_loaded', 'barramento_tainacan_init', 20);

/**
 * Registra o evento cron para processamento agendado
 */
function barramento_tainacan_execute_cron() {
    if (class_exists('Barramento_Tainacan_Processor')) {
        $processor = new Barramento_Tainacan_Processor();
        if (method_exists($processor, 'process_scheduled_collections')) {
            $processor->process_scheduled_collections();
        }
    }
}
add_action('barramento_tainacan_cron_event', 'barramento_tainacan_execute_cron');