<?php
/**
 * Executado durante a ativação do plugin.
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe responsável pela ativação do plugin.
 */
class Barramento_Tainacan_Activator {

    /**
     * Executa as ações de ativação do plugin.
     */
    public static function activate() {
        self::check_tainacan_dependency();
        self::create_database_tables();
        self::set_default_options();
        self::create_directories();
        self::setup_scheduled_events();
        self::register_activation_timestamp();
    }

    /**
     * Verifica dependência do plugin Tainacan.
     */
    private static function check_tainacan_dependency() {
        if (!is_plugin_active('tainacan/tainacan.php') && !class_exists('Tainacan\Tainacan')) {
            deactivate_plugins(plugin_basename(BARRAMENTO_TAINACAN_PLUGIN_BASENAME));
            wp_die(
                __('Erro: O plugin Barramento Tainacan requer o plugin Tainacan instalado e ativado.', 'barramento-tainacan'),
                'Plugin Dependency Error',
                array('back_link' => true)
            );
        }
    }

    /**
     * Cria as tabelas necessárias no banco de dados.
     */
    private static function create_database_tables() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-db-manager.php';
        $db_manager = new Barramento_Tainacan_DB_Manager();
        $db_manager->init_db();
    }

    /**
     * Define as opções padrão para o plugin.
     */
    private static function set_default_options() {
        // Versão atual do plugin
        add_option('barramento_version', BARRAMENTO_TAINACAN_VERSION);
        add_option('barramento_installed', true);
        
        // Configurações padrão do Archivematica
        if (!get_option('barramento_archivematica_url')) {
            add_option('barramento_archivematica_url', '');
        }
        
        if (!get_option('barramento_archivematica_user')) {
            add_option('barramento_archivematica_user', '');
        }
        
        if (!get_option('barramento_archivematica_api_key')) {
            add_option('barramento_archivematica_api_key', '');
        }
        
        if (!get_option('barramento_storage_service_url')) {
            add_option('barramento_storage_service_url', '');
        }
        
        if (!get_option('barramento_storage_service_user')) {
            add_option('barramento_storage_service_user', '');
        }
        
        if (!get_option('barramento_storage_service_api_key')) {
            add_option('barramento_storage_service_api_key', '');
        }
        
        // Configurações de preservação
        if (!get_option('barramento_max_sip_size')) {
            add_option('barramento_max_sip_size', 1024); // 1GB em MB
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
        
        if (!get_option('barramento_enabled_collections')) {
            add_option('barramento_enabled_collections', array());
        }
        
        if (!get_option('barramento_metadata_mapping')) {
            add_option('barramento_metadata_mapping', array());
        }
        
        if (!get_option('barramento_required_metadata')) {
            add_option('barramento_required_metadata', array());
        }
        
        if (!get_option('barramento_fixed_metadata')) {
            add_option('barramento_fixed_metadata', array());
        }
        
        if (!get_option('barramento_scheduled_process_limit')) {
            add_option('barramento_scheduled_process_limit', 20);
        }
        
        if (!get_option('barramento_preserve_data_on_uninstall')) {
            add_option('barramento_preserve_data_on_uninstall', false);
        }
    }

    /**
     * Cria os diretórios necessários para o plugin.
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        
        $directories = array(
            trailingslashit($upload_dir['basedir']) . 'barramento-temp',
            trailingslashit($upload_dir['basedir']) . 'barramento-sips'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Cria arquivo index.php para proteção
                $index_file = trailingslashit($dir) . 'index.php';
                if (!file_exists($index_file)) {
                    $file_handle = @fopen($index_file, 'w');
                    if ($file_handle) {
                        fwrite($file_handle, "<?php\n// Silence is golden.");
                        fclose($file_handle);
                    }
                }
                
                // Cria arquivo .htaccess para proteção adicional
                $htaccess_file = trailingslashit($dir) . '.htaccess';
                if (!file_exists($htaccess_file)) {
                    $file_handle = @fopen($htaccess_file, 'w');
                    if ($file_handle) {
                        fwrite($file_handle, "Order Allow,Deny\nDeny from all");
                        fclose($file_handle);
                    }
                }
            }
        }
    }

    /**
     * Configura os eventos agendados (cron).
     */
    private static function setup_scheduled_events() {
        // Remove agendamentos antigos se existirem
        wp_clear_scheduled_hook('barramento_tainacan_cron_event');
        
        // Configura novo agendamento
        $frequency = get_option('barramento_schedule_frequency', 'daily');
        
        if (!wp_next_scheduled('barramento_tainacan_cron_event')) {
            wp_schedule_event(time(), $frequency, 'barramento_tainacan_cron_event');
        }
    }

    /**
     * Registra o timestamp da ativação.
     */
    private static function register_activation_timestamp() {
        add_option('barramento_activation_time', time());
        
        // Registra a ativação no log
        if (class_exists('Barramento_Tainacan_Logger')) {
            $logger = new Barramento_Tainacan_Logger();
            $logger->log('info', 'Plugin Barramento Tainacan ativado', array(
                'version' => BARRAMENTO_TAINACAN_VERSION,
                'timestamp' => time()
            ));
        }
    }
}
