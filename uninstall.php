<?php
/**
 * Executado quando o plugin é desinstalado.
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Acesso à classe global do banco de dados
global $wpdb;

// Verifica se a opção de manter dados na desinstalação está ativada
$preserve_data = get_option('barramento_preserve_data_on_uninstall', false);

if (!$preserve_data) {
    // Remove tabelas do banco de dados
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}barramento_queue");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}barramento_logs");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}barramento_hashes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}barramento_objects");
    
    // Remove todas as opções do plugin
    delete_option('barramento_version');
    delete_option('barramento_installed');
    delete_option('barramento_archivematica_url');
    delete_option('barramento_archivematica_user');
    delete_option('barramento_archivematica_api_key');
    delete_option('barramento_storage_service_url');
    delete_option('barramento_storage_service_user');
    delete_option('barramento_storage_service_api_key');
    delete_option('barramento_max_sip_size');
    delete_option('barramento_schedule_frequency');
    delete_option('barramento_hash_algorithm');
    delete_option('barramento_retry_attempts');
    delete_option('barramento_debug_mode');
    delete_option('barramento_enabled_collections');
    delete_option('barramento_metadata_mapping');
    delete_option('barramento_required_metadata');
    delete_option('barramento_fixed_metadata');
    delete_option('barramento_public_page_enabled');
    delete_option('barramento_public_page_slug');
    delete_option('barramento_public_display_options');
    delete_option('barramento_preserve_data_on_uninstall');
    
    // Remove eventos cron
    wp_clear_scheduled_hook('barramento_tainacan_cron_event');
    
    // Remove diretórios de arquivos
    $upload_dir = wp_upload_dir();
    $dirs_to_remove = array(
        trailingslashit($upload_dir['basedir']) . 'barramento-temp',
        trailingslashit($upload_dir['basedir']) . 'barramento-sips'
    );
    
    foreach ($dirs_to_remove as $dir) {
        if (is_dir($dir)) {
            barramento_remove_directory($dir);
        }
    }
    
    // Limpa o cache
    wp_cache_flush();
    
    // Registra o log de desinstalação
    if (function_exists('error_log')) {
        error_log('Plugin Barramento Tainacan desinstalado e dados removidos em ' . date('Y-m-d H:i:s'));
    }
}

/**
 * Remove recursivamente um diretório e seu conteúdo
 *
 * @param string $dir Caminho do diretório
 * @return bool Resultado da operação
 */
function barramento_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = trailingslashit($dir) . $file;
        
        if (is_dir($path)) {
            barramento_remove_directory($path);
        } else {
            @unlink($path);
        }
    }
    
    return @rmdir($dir);
}
