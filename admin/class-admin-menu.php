<?php
/**
 * Classe para gerenciamento do menu administrativo
 *
 * @package Barramento_Tainacan
 */

// Impede o acesso direto
if (!defined('WPINC')) {
    die;
}

/**
 * Classe para gerenciamento do menu administrativo
 */
class Barramento_Tainacan_Admin_Menu {
    /**
     * Adiciona os itens de menu ao painel administrativo
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Barramento Tainacan', 'barramento-tainacan'),
            __('Barramento Tainacan', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan',
            array($this, 'render_dashboard_page'),
            'dashicons-shield-alt',
            26
        );

        add_submenu_page(
            'barramento-tainacan',
            __('Dashboard', 'barramento-tainacan'),
            __('Dashboard', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'barramento-tainacan',
            __('Configurações', 'barramento-tainacan'),
            __('Configurações', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'barramento-tainacan',
            __('Coleções', 'barramento-tainacan'),
            __('Coleções', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan-collections',
            array($this, 'render_collections_page')
        );

        add_submenu_page(
            'barramento-tainacan',
            __('Logs', 'barramento-tainacan'),
            __('Logs', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'barramento-tainacan',
            __('Relatórios', 'barramento-tainacan'),
            __('Relatórios', 'barramento-tainacan'),
            'manage_options',
            'barramento-tainacan-reports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Registra os estilos para o painel administrativo
     */
    public function enqueue_styles($hook) {
        // Carrega apenas nas páginas do plugin
        if (strpos($hook, 'barramento-tainacan') === false) {
            return;
        }

        wp_enqueue_style(
            'barramento-tainacan-admin-style',
            BARRAMENTO_TAINACAN_PLUGIN_URL . 'admin/css/barramento-admin.css',
            array(),
            BARRAMENTO_TAINACAN_VERSION
        );
    }

    /**
     * Registra os scripts para o painel administrativo
     */
    public function enqueue_scripts($hook) {
        // Carrega apenas nas páginas do plugin
        if (strpos($hook, 'barramento-tainacan') === false) {
            return;
        }

        wp_enqueue_script(
            'barramento-tainacan-admin-script',
            BARRAMENTO_TAINACAN_PLUGIN_URL . 'admin/js/barramento-admin.js',
            array('jquery'),
            BARRAMENTO_TAINACAN_VERSION,
            true
        );

        wp_localize_script('barramento-tainacan-admin-script', 'barramento_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('barramento_admin_nonce'),
            'i18n' => array(
                'success' => __('Sucesso!', 'barramento-tainacan'),
                'error' => __('Erro!', 'barramento-tainacan'),
                'confirm' => __('Tem certeza?', 'barramento-tainacan'),
                'loading' => __('Processando...', 'barramento-tainacan'),
                'connection_success' => __('Conexão estabelecida com sucesso!', 'barramento-tainacan'),
                'connection_error' => __('Erro ao estabelecer conexão:', 'barramento-tainacan')
            )
        ));
    }

    /**
     * Renderiza a página de dashboard
     */
    public function render_dashboard_page() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Renderiza a página de configurações
     */
    public function render_settings_page() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Renderiza a página de coleções
     */
    public function render_collections_page() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/views/collections.php';
    }

    /**
     * Renderiza a página de logs
     */
    public function render_logs_page() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Renderiza a página de relatórios
     */
    public function render_reports_page() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'admin/views/reports.php';
    }
}
