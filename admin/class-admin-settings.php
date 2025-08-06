<?php
/**
 * Classe para gerenciamento das configurações administrativas
 *
 * @package Barramento_Tainacan
 */

// Impede o acesso direto
if (!defined('WPINC')) {
    die;
}

/**
 * Classe para gerenciamento das configurações administrativas
 */
class Barramento_Tainacan_Admin_Settings {
    /**
     * Registra as configurações do plugin
     */
    public function register_settings() {
        // Grupo de configurações gerais
        register_setting(
            'barramento_general_settings',
            'barramento_archivematica_url',
            array('sanitize_callback' => array($this, 'sanitize_url'))
        );
        register_setting(
            'barramento_general_settings',
            'barramento_archivematica_user',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_general_settings',
            'barramento_archivematica_api_key',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_general_settings',
            'barramento_storage_service_url',
            array('sanitize_callback' => array($this, 'sanitize_url'))
        );
        register_setting(
            'barramento_general_settings',
            'barramento_storage_service_user',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_general_settings',
            'barramento_storage_service_api_key',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_general_settings',
            'barramento_debug_mode',
            array('sanitize_callback' => array($this, 'sanitize_boolean'))
        );

        // Grupo de configurações de preservação
        register_setting(
            'barramento_preservation_settings',
            'barramento_max_sip_size',
            array('sanitize_callback' => array($this, 'sanitize_int'))
        );
        register_setting(
            'barramento_preservation_settings',
            'barramento_schedule_frequency',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_preservation_settings',
            'barramento_schedule_time',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_preservation_settings',
            'barramento_hash_algorithm',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        register_setting(
            'barramento_preservation_settings',
            'barramento_retry_attempts',
            array('sanitize_callback' => array($this, 'sanitize_int'))
        );

        // Grupo de configurações de coleções
        register_setting(
            'barramento_collections_settings',
            'barramento_enabled_collections',
            array('sanitize_callback' => array($this, 'sanitize_ids'))
        );
        register_setting(
            'barramento_collections_settings',
            'barramento_metadata_mapping',
            array('sanitize_callback' => array($this, 'sanitize_textarea'))
        );
        register_setting(
            'barramento_collections_settings',
            'barramento_required_metadata',
            array('sanitize_callback' => array($this, 'sanitize_textarea'))
        );
        register_setting(
            'barramento_collections_settings',
            'barramento_fixed_metadata',
            array('sanitize_callback' => array($this, 'sanitize_textarea'))
        );

        // Grupo de configurações de relatórios
        register_setting(
            'barramento_reports_settings',
            'barramento_public_page_enabled',
            array('sanitize_callback' => array($this, 'sanitize_boolean'))
        );
        register_setting(
            'barramento_reports_settings',
            'barramento_public_page_slug',
            array('sanitize_callback' => array($this, 'sanitize_slug'))
        );
        register_setting(
            'barramento_reports_settings',
            'barramento_public_display_options',
            array('sanitize_callback' => array($this, 'sanitize_textarea'))
        );
    }

    /**
     * Testa a conexão com o Archivematica via AJAX
     */
    public function test_connection() {
        check_ajax_referer('barramento_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'barramento-tainacan')));
            return;
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $user = isset($_POST['user']) ? sanitize_text_field($_POST['user']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $service_type = isset($_POST['service_type']) ? sanitize_text_field($_POST['service_type']) : 'dashboard';

        if (empty($url) || empty($user) || empty($api_key)) {
            wp_send_json_error(array('message' => __('Parâmetros incompletos.', 'barramento-tainacan')));
            return;
        }

        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-archivematica-api.php';
        $api = new Barramento_Tainacan_Archivematica_API($url, $user, $api_key, $service_type);
        
        $result = $api->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Conexão estabelecida com sucesso!', 'barramento-tainacan'),
            'details' => $result
        ));
    }

    /**
     * Prepara uma coleção para indexação via AJAX
     */
    public function index_collection() {
        check_ajax_referer('barramento_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'barramento-tainacan')));
            return;
        }

        $collection_id = isset($_POST['collection_id']) ? intval($_POST['collection_id']) : 0;
        $force_update = isset($_POST['force_update']) ? (bool) $_POST['force_update'] : false;

        if (empty($collection_id)) {
            wp_send_json_error(array('message' => __('ID de coleção inválido.', 'barramento-tainacan')));
            return;
        }

        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-processor.php';
        $processor = new Barramento_Tainacan_Processor();
        
        $result = $processor->queue_collection_items($collection_id, $force_update);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Coleção adicionada à fila de processamento. %d itens identificados.', 'barramento-tainacan'),
                $result['total_items']
            ),
            'details' => $result
        ));
    }

    /**
     * Processa um SIP para um item específico via AJAX
     */
    public function process_sip() {
        check_ajax_referer('barramento_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'barramento-tainacan')));
            return;
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $collection_id = isset($_POST['collection_id']) ? intval($_POST['collection_id']) : 0;

        if (empty($item_id) || empty($collection_id)) {
            wp_send_json_error(array('message' => __('Parâmetros inválidos.', 'barramento-tainacan')));
            return;
        }

        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-processor.php';
        $processor = new Barramento_Tainacan_Processor();
        
        $result = $processor->process_item($item_id, $collection_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Item processado com sucesso.', 'barramento-tainacan'),
            'details' => $result
        ));
    }

    /**
     * Obtém o status atualizado do Archivematica para um item via AJAX
     */
    public function get_archivematica_status() {
        check_ajax_referer('barramento_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'barramento-tainacan')));
            return;
        }

        $object_id = isset($_POST['object_id']) ? intval($_POST['object_id']) : 0;

        if (empty($object_id)) {
            wp_send_json_error(array('message' => __('ID de objeto inválido.', 'barramento-tainacan')));
            return;
        }

        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-processor.php';
        $processor = new Barramento_Tainacan_Processor();
        
        $result = $processor->check_preservation_status($object_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Status atualizado com sucesso.', 'barramento-tainacan'),
            'details' => $result
        ));
    }

    /**
     * Sanitiza valores booleanos.
     *
     * @param mixed $value Valor a ser sanitizado.
     * @return bool
     */
    public function sanitize_boolean( $value ) {
        return (bool) $value;
    }

    /**
     * Sanitiza inteiros.
     *
     * @param mixed $value Valor a ser sanitizado.
     * @return int
     */
    public function sanitize_int( $value ) {
        return absint( $value );
    }

    /**
     * Sanitiza URLs.
     *
     * @param string $value Valor a ser sanitizado.
     * @return string
     */
    public function sanitize_url( $value ) {
        return esc_url_raw( $value );
    }

    /**
     * Sanitiza arrays de IDs.
     *
     * @param mixed $value Valor a ser sanitizado.
     * @return array
     */
    public function sanitize_ids( $value ) {
        if ( ! is_array( $value ) ) {
            $value = array();
        }
        return array_map( 'absint', $value );
    }

    /**
     * Sanitiza textos longos/áreas de texto.
     *
     * @param string $value Valor a ser sanitizado.
     * @return string
     */
    public function sanitize_textarea( $value ) {
        return sanitize_textarea_field( $value );
    }

    /**
     * Sanitiza slugs.
     *
     * @param string $value Valor a ser sanitizado.
     * @return string
     */
    public function sanitize_slug( $value ) {
        return sanitize_title( $value );
    }
}
