<?php
/**
 * Executado durante a desativação do plugin.
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe responsável pela desativação do plugin.
 */
class Barramento_Tainacan_Deactivator {

    /**
     * Executa as ações de desativação do plugin.
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        self::register_deactivation();
    }

    /**
     * Remove os eventos agendados (cron).
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook('barramento_tainacan_cron_event');
    }

    /**
     * Registra a desativação do plugin.
     */
    private static function register_deactivation() {
        update_option('barramento_deactivation_time', time());
        
        // Registra a desativação no log
        if (class_exists('Barramento_Tainacan_Logger')) {
            $logger = new Barramento_Tainacan_Logger();
            $logger->log('info', 'Plugin Barramento Tainacan desativado', array(
                'version' => BARRAMENTO_TAINACAN_VERSION,
                'timestamp' => time()
            ));
        }
    }
}
