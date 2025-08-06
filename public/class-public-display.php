<?php
/**
 * Funcionalidades públicas do Barramento Tainacan
 *
 * @package Barramento_Tainacan
 */

// Impede acesso direto
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Responsável por registrar scripts, estilos e shortcodes públicos.
 */
class Barramento_Tainacan_Public_Display {

    /**
     * Construtor.
     */
    public function __construct() {
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'public/view/preservation-status.php';
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'public/collection-status.php';
    }

    /**
     * Carrega os estilos públicos.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'barramento-public',
            BARRAMENTO_TAINACAN_PLUGIN_URL . 'public/css/barramento-public.css',
            array(),
            BARRAMENTO_TAINACAN_VERSION
        );
    }

    /**
     * Carrega os scripts públicos.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'barramento-public',
            BARRAMENTO_TAINACAN_PLUGIN_URL . 'public/js/barramento-public.js',
            array( 'jquery' ),
            BARRAMENTO_TAINACAN_VERSION,
            true
        );

        wp_localize_script(
            'barramento-public',
            'barramento_public',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'barramento_public_nonce' ),
                'i18n'     => array(
                    'aip_id'       => __( 'AIP ID:', 'barramento-tainacan' ),
                    'preserved_on' => __( 'Preservado em:', 'barramento-tainacan' ),
                    'reason'       => __( 'Motivo:', 'barramento-tainacan' ),
                    'retry_note'   => __( 'Entre em contato com o administrador para reprocessar este item.', 'barramento-tainacan' ),
                ),
            )
        );
    }

    /**
     * Shortcode para exibir o status de preservação de um item.
     *
     * @param array $atts Atributos do shortcode.
     * @return string
     */
    public function preservation_status_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'item_id' => 0,
                'details' => true,
            ),
            $atts,
            'barramento_status'
        );

        $show_details = filter_var( $atts['details'], FILTER_VALIDATE_BOOLEAN );

        return barramento_tainacan_display_preservation_status( intval( $atts['item_id'] ), $show_details );
    }

    /**
     * Shortcode para exibir o status de preservação de uma coleção.
     *
     * @param array $atts Atributos do shortcode.
     * @return string
     */
    public function collection_status_shortcode( $atts ) {
        return barramento_collection_status_shortcode( $atts );
    }
}
