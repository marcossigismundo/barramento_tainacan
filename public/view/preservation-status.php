<?php
/**
 * Template para exibição do status de preservação de um item
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

/**
 * Exibe o status de preservação de um item
 * 
 * @param int $item_id ID do item (opcional, usa o item atual se não fornecido)
 * @param bool $show_details Se deve mostrar detalhes adicionais
 * @return string HTML do status de preservação
 */
function barramento_tainacan_display_preservation_status($item_id = 0, $show_details = true) {
    // Se não for fornecido um ID, tenta obter do post atual
    if (empty($item_id)) {
        $item_id = get_the_ID();
    }
    
    if (empty($item_id)) {
        return '<div class="barramento-status barramento-error">Item inválido</div>';
    }
    
    global $wpdb;
    $objects_table = $wpdb->prefix . 'barramento_objects';
    
    // Busca o status de preservação do item
    $object = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $objects_table WHERE item_id = %d",
            $item_id
        ),
        ARRAY_A
    );
    
    ob_start();
    ?>
    <div class="barramento-preservation-status">
        <?php if (empty($object)): ?>
            <div class="barramento-status-badge status-not_preserved">
                <?php _e('Não preservado', 'barramento-tainacan'); ?>
            </div>
        <?php else: ?>
            <div class="barramento-status-badge status-<?php echo esc_attr($object['preservation_status']); ?>">
                <?php echo barramento_get_status_label($object['preservation_status']); ?>
            </div>
            
            <?php if ($show_details && !empty($object['aip_id'])): ?>
                <div class="barramento-preservation-details">
                    <p class="barramento-aip-info">
                        <strong><?php _e('AIP ID:', 'barramento-tainacan'); ?></strong> 
                        <?php if (!empty($object['archivematica_url'])): ?>
                            <a href="<?php echo esc_url($object['archivematica_url']); ?>" target="_blank" class="barramento-external-link">
                                <?php echo esc_html($object['aip_id']); ?>
                                <span class="dashicons dashicons-external"></span>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($object['aip_id']); ?>
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($object['aip_creation_date'])): ?>
                        <p class="barramento-preservation-date">
                            <strong><?php _e('Preservado em:', 'barramento-tainacan'); ?></strong> 
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($object['aip_creation_date'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($show_details && in_array($object['preservation_status'], array('transfer_failed', 'ingest_failed'))): ?>
                <div class="barramento-error-details">
                    <p class="barramento-error-message">
                        <strong><?php _e('Motivo:', 'barramento-tainacan'); ?></strong>
                        <?php
                        $notes = json_decode($object['notes'], true);
                        echo !empty($notes['message']) ? esc_html($notes['message']) : __('Erro desconhecido', 'barramento-tainacan');
                        ?>
                    </p>
                    <p class="barramento-retry-note">
                        <?php _e('Entre em contato com o administrador para reprocessar este item.', 'barramento-tainacan'); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($show_details && in_array($object['preservation_status'], array('transfer_started', 'ingest_started'))): ?>
                <div class="barramento-progress-details">
                    <p class="barramento-progress-message">
                        <?php _e('Processamento em andamento. Este processo pode levar algum tempo dependendo do tamanho dos arquivos.', 'barramento-tainacan'); ?>
                    </p>
                    <div class="barramento-progress-indicator">
                        <div class="barramento-progress-bar">
                            <div class="barramento-progress-value"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtém o rótulo para um status de preservação
 * 
 * @param string $status Código do status
 * @return string Rótulo do status
 */
function barramento_get_status_label($status) {
    $labels = array(
        'not_preserved' => __('Não preservado', 'barramento-tainacan'),
        'sip_created' => __('SIP Criado', 'barramento-tainacan'),
        'transfer_started' => __('Transferência Iniciada', 'barramento-tainacan'),
        'transfer_failed' => __('Falha na Transferência', 'barramento-tainacan'),
        'ingest_started' => __('Ingestão Iniciada', 'barramento-tainacan'),
        'ingest_failed' => __('Falha na Ingestão', 'barramento-tainacan'),
        'aip_stored' => __('AIP Armazenado', 'barramento-tainacan'),
        'fully_preserved' => __('Totalmente Preservado', 'barramento-tainacan')
    );
    
    return isset($labels[$status]) ? $labels[$status] : $status;
}

/**
 * Exibe a preservação de detalhes técnicos
 * 
 * @param int $item_id ID do item
 * @return string HTML com detalhes técnicos
 */
function barramento_tainacan_display_technical_details($item_id) {
    if (empty($item_id)) {
        $item_id = get_the_ID();
    }
    
    if (empty($item_id)) {
        return '';
    }
    
    global $wpdb;
    $objects_table = $wpdb->prefix . 'barramento_objects';
    $hashes_table = $wpdb->prefix . 'barramento_hashes';
    
    // Busca o objeto de preservação
    $object = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, preservation_status, sip_id, aip_id FROM $objects_table WHERE item_id = %d",
            $item_id
        ),
        ARRAY_A
    );
    
    if (empty($object)) {
        return '';
    }
    
    // Busca os hashes registrados
    $hashes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $hashes_table WHERE object_id = %d ORDER BY created_at ASC",
            $object['id']
        ),
        ARRAY_A
    );
    
    ob_start();
    ?>
    <div class="barramento-technical-details">
        <h4><?php _e('Detalhes Técnicos de Preservação', 'barramento-tainacan'); ?></h4>
        
        <table class="barramento-technical-table">
            <tr>
                <th><?php _e('ID SIP', 'barramento-tainacan'); ?></th>
                <td><?php echo !empty($object['sip_id']) ? esc_html($object['sip_id']) : '-'; ?></td>
            </tr>
            <tr>
                <th><?php _e('ID AIP', 'barramento-tainacan'); ?></th>
                <td><?php echo !empty($object['aip_id']) ? esc_html($object['aip_id']) : '-'; ?></td>
            </tr>
            <tr>
                <th><?php _e('Status', 'barramento-tainacan'); ?></th>
                <td>
                    <span class="barramento-status-badge status-<?php echo esc_attr($object['preservation_status']); ?>">
                        <?php echo barramento_get_status_label($object['preservation_status']); ?>
                    </span>
                </td>
            </tr>
        </table>
        
        <?php if (!empty($hashes)): ?>
            <h5><?php _e('Verificação de Integridade', 'barramento-tainacan'); ?></h5>
            <table class="barramento-hashes-table">
                <thead>
                    <tr>
                        <th><?php _e('Arquivo', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Algoritmo', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Hash', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Tamanho', 'barramento-tainacan'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hashes as $hash): ?>
                        <tr>
                            <td>
                                <?php 
                                if (!empty($hash['file_path'])) {
                                    echo esc_html(basename($hash['file_path']));
                                } else {
                                    _e('Pacote completo', 'barramento-tainacan');
                                }
                                ?>
                            </td>
                            <td><?php echo strtoupper(esc_html($hash['hash_type'])); ?></td>
                            <td class="hash-value"><?php echo esc_html($hash['hash_value']); ?></td>
                            <td>
                                <?php 
                                if (!empty($hash['file_size'])) {
                                    echo barramento_format_file_size($hash['file_size']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="barramento-hash-info">
                <?php _e('Os hashes acima garantem a integridade dos arquivos no sistema de preservação.', 'barramento-tainacan'); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Formata um tamanho em bytes para exibição amigável
 * 
 * @param int $bytes Tamanho em bytes
 * @param int $precision Precisão decimal
 * @return string Tamanho formatado
 */
function barramento_format_file_size($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
