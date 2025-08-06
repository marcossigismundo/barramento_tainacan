<?php
/**
 * Classe principal do plugin Barramento Tainacan
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Classe principal que inicializa todos os componentes do plugin.
 */
class Barramento_Tainacan {

    /**
     * Nome único do plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string    $plugin_name    Nome único do plugin.
     */
    protected $plugin_name = 'barramento-tainacan';

    /**
     * Inicializa a classe e define as propriedades.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Carrega as dependências necessárias para o plugin.
     *
     * @since  1.0.0
     * @access private
     */
    private function load_dependencies() {
        // Classes principais
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-db-manager.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-db-manager.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-tainacan-api.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-tainacan-api.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-archivematica-api.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-archivematica-api.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-sip-generator.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-sip-generator.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-hash-manager.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-hash-manager.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-validator.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-validator.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-processor.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-processor.php';
        }

        // Classes admin
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/class-admin-menu.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/class-admin-menu.php';
        }
        
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/class-admin-settings.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/class-admin-settings.php';
        }

        // Classes public
        if (file_exists(BARRAMENTO_TAINACAN_PLUGIN_DIR . 'public/class-public-display.php')) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'public/class-public-display.php';
        }
    }

    /**
     * Define os hooks relacionados à área administrativa.
     *
     * @since  1.0.0
     * @access private
     */
    private function define_admin_hooks() {
        if (class_exists('Barramento_Tainacan_Admin_Menu')) {
            $admin_menu = new Barramento_Tainacan_Admin_Menu();
            add_action('admin_menu', array($admin_menu, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($admin_menu, 'enqueue_styles'));
            add_action('admin_enqueue_scripts', array($admin_menu, 'enqueue_scripts'));
        }

        if (class_exists('Barramento_Tainacan_Admin_Settings')) {
            $admin_settings = new Barramento_Tainacan_Admin_Settings();
            add_action('admin_init', array($admin_settings, 'register_settings'));

            // Hooks para AJAX
            add_action('wp_ajax_barramento_test_connection', array($admin_settings, 'test_connection'));
            add_action('wp_ajax_barramento_index_collection', array($admin_settings, 'index_collection'));
            add_action('wp_ajax_barramento_process_sip', array($admin_settings, 'process_sip'));
            add_action('wp_ajax_barramento_get_archivematica_status', array($admin_settings, 'get_archivematica_status'));
            
            // Novos hooks AJAX
            if (method_exists($admin_settings, 'process_queue')) {
                add_action('wp_ajax_barramento_process_queue', array($admin_settings, 'process_queue'));
            }
            
            if (method_exists($admin_settings, 'check_transfers')) {
                add_action('wp_ajax_barramento_check_transfers', array($admin_settings, 'check_transfers'));
            }
            
            if (method_exists($admin_settings, 'check_ingests')) {
                add_action('wp_ajax_barramento_check_ingests', array($admin_settings, 'check_ingests'));
            }
            
            if (method_exists($admin_settings, 'cleanup_logs')) {
                add_action('wp_ajax_barramento_cleanup_logs', array($admin_settings, 'cleanup_logs'));
            }
        }
    }

    /**
     * Define os hooks relacionados à parte pública do site.
     *
     * @since  1.0.0
     * @access private
     */
    private function define_public_hooks() {
        if (class_exists('Barramento_Tainacan_Public_Display')) {
            $public_display = new Barramento_Tainacan_Public_Display();
            add_action('wp_enqueue_scripts', array($public_display, 'enqueue_styles'));
            add_action('wp_enqueue_scripts', array($public_display, 'enqueue_scripts'));
            
            // Shortcodes
            if (method_exists($public_display, 'preservation_status_shortcode')) {
                add_shortcode('barramento_status', array($public_display, 'preservation_status_shortcode'));
            }
            
            if (method_exists($public_display, 'collection_status_shortcode')) {
                add_shortcode('barramento_collection', array($public_display, 'collection_status_shortcode'));
            }
        }
    }

    /**
     * Define os hooks relacionados aos agendamentos (cron).
     *
     * @since  1.0.0
     * @access private
     */
    private function define_cron_hooks() {
        // Registra o evento cron se não existir
        if (!wp_next_scheduled('barramento_tainacan_cron_event')) {
            wp_schedule_event(time(), 'daily', 'barramento_tainacan_cron_event');
        }
        
        // Adiciona o hook para o evento cron
        add_action('barramento_tainacan_cron_event', array($this, 'execute_scheduled_tasks'));
    }

    /**
     * Executa as tarefas agendadas.
     *
     * @since  1.0.0
     */
    public function execute_scheduled_tasks() {
        if (class_exists('Barramento_Tainacan_Processor')) {
            $processor = new Barramento_Tainacan_Processor();
            if (method_exists($processor, 'process_scheduled_collections')) {
                $processor->process_scheduled_collections();
            }
        }
    }

    /**
     * Executa o plugin.
     *
     * @since 1.0.0
     */
    public function run() {
        // Inicializa o banco de dados se a classe estiver disponível
        if (class_exists('Barramento_Tainacan_DB_Manager')) {
            $db_manager = new Barramento_Tainacan_DB_Manager();
            if (method_exists($db_manager, 'init_db')) {
                $db_manager->init_db();
            }
        }
        
        // Carrega as traduções
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Carrega o arquivo de tradução.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'barramento-tainacan',
            false,
            dirname(plugin_basename(__FILE__)) . '/../languages/'
        );
    }
}