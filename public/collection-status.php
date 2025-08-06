<?php
/**
 * Template para exibição do status de preservação de uma coleção
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

/**
 * Exibe o status de preservação de uma coleção
 * 
 * @param int $collection_id ID da coleção
 * @param array $args Argumentos adicionais
 * @return string HTML do status de preservação
 */
function barramento_tainacan_display_collection_status($collection_id, $args = array()) {
    if (empty($collection_id)) {
        return '<div class="barramento-error">ID de coleção inválido</div>';
    }
    
    // Argumentos padrão
    $default_args = array(
        'show_stats' => true,
        'show_progress' => true,
        'show_items' => true,
        'items_count' => 5,
        'title' => __('Status de Preservação da Coleção', 'barramento-tainacan')
    );
    
    $args = wp_parse_args($args, $default_args);
    
    // Obtém informações da coleção
    $collection = get_post($collection_id);
    if (!$collection || $collection->post_type !== 'tainacan-collection') {
        return '<div class="barramento-error">Coleção não encontrada</div>';
    }
    
    // Obtém estatísticas de preservação
    global $wpdb;
    $objects_table = $wpdb->prefix . 'barramento_objects';
    
    $stats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT preservation_status, COUNT(*) as count FROM $objects_table WHERE collection_id = %d GROUP BY preservation_status",
            $collection_id
        ),
        ARRAY_A
    );
    
    // Prepara os dados estatísticos
    $status_map = array(
        'not_preserved' => 'not_preserved',
        'sip_created' => 'in_process',
        'transfer_started' => 'in_process',
        'transfer_failed' => 'failed',
        'ingest_started' => 'in_process',
        'ingest_failed' => 'failed',
        'aip_stored' => 'preserved',
        'fully_preserved' => 'preserved'
    );
    
    $formatted_stats = array(
        'total' => 0,
        'preserved' => 0,
        'in_process' => 0,
        'failed' => 0
    );
    
    foreach ($stats as $stat) {
        $count = (int)$stat['count'];
        $formatted_stats['total'] += $count;
        
        $category = $status_map[$stat['preservation_status']] ?? 'not_preserved';
        if ($category !== 'not_preserved') {
            $formatted_stats[$category] += $count;
        }
    }
    
    // Obtém os itens recentes
    $recent_items = array();
    if ($args['show_items']) {
        $recent_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, i.post_title as title 
                FROM $objects_table o
                JOIN {$wpdb->posts} i ON o.item_id = i.ID
                WHERE o.collection_id = %d
                ORDER BY o.last_status_update DESC
                LIMIT %d",
                $collection_id,
                $args['items_count']
            ),
            ARRAY_A
        );
    }
    
    // Calcula porcentagens
    $total_items = get_post_meta($collection_id, 'items_count', true);
    if (empty($total_items)) {
        $total_items = $formatted_stats['total'];
    }
    
    $preserved_percent = $total_items > 0 ? ($formatted_stats['preserved'] / $total_items) * 100 : 0;
    $in_process_percent = $total_items > 0 ? ($formatted_stats['in_process'] / $total_items) * 100 : 0;
    $failed_percent = $total_items > 0 ? ($formatted_stats['failed'] / $total_items) * 100 : 0;
    
    ob_start();
    ?>
    <div class="barramento-collection-status" data-collection-id="<?php echo esc_attr($collection_id); ?>">
        <div class="barramento-collection-header">
            <h3 class="barramento-collection-title"><?php echo esc_html($args['title']); ?></h3>
            <p class="barramento-collection-description"><?php echo esc_html($collection->post_title); ?></p>
        </div>
        
        <?php if ($args['show_stats']): ?>
            <div class="barramento-collection-stats">
                <div class="barramento-stat-card preserved">
                    <div class="barramento-stat-value"><?php echo number_format_i18n($formatted_stats['preserved']); ?></div>
                    <div class="barramento-stat-label"><?php _e('Itens Preservados', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="barramento-stat-card in-process">
                    <div class="barramento-stat-value"><?php echo number_format_i18n($formatted_stats['in_process']); ?></div>
                    <div class="barramento-stat-label"><?php _e('Em Processamento', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="barramento-stat-card failed">
                    <div class="barramento-stat-value"><?php echo number_format_i18n($formatted_stats['failed']); ?></div>
                    <div class="barramento-stat-label"><?php _e('Falhas de Preservação', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="barramento-stat-card">
                    <div class="barramento-stat-value"><?php echo number_format_i18n($total_items); ?></div>
                    <div class="barramento-stat-label"><?php _e('Total de Itens', 'barramento-tainacan'); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($args['show_progress'] && $total_items > 0): ?>
            <div class="barramento-progress-bar-container">
                <h4><?php _e('Progresso de Preservação', 'barramento-tainacan'); ?></h4>
                <div class="barramento-progress-bar-label">
                    <span><?php _e('Concluído', 'barramento-tainacan'); ?>: <?php echo number_format_i18n($preserved_percent, 1); ?>%</span>
                    <span><?php echo number_format_i18n($formatted_stats['preserved']); ?> / <?php echo number_format_i18n($total_items); ?></span>
                </div>
                
                <div class="barramento-progress-bar-full">
                    <?php if ($preserved_percent > 0): ?>
                        <div class="barramento-progress-segment preserved" style="width: <?php echo esc_attr($preserved_percent); ?>%;"></div>
                    <?php endif; ?>
                    
                    <?php if ($in_process_percent > 0): ?>
                        <div class="barramento-progress-segment in-process" style="width: <?php echo esc_attr($in_process_percent); ?>%;"></div>
                    <?php endif; ?>
                    
                    <?php if ($failed_percent > 0): ?>
                        <div class="barramento-progress-segment failed" style="width: <?php echo esc_attr($failed_percent); ?>%;"></div>
                    <?php endif; ?>
                </div>
                
                <div class="barramento-progress-legend">
                    <div class="barramento-legend-item">
                        <span class="barramento-legend-color preserved"></span>
                        <span><?php printf(__('Preservados (%d%%)', 'barramento-tainacan'), round($preserved_percent)); ?></span>
                    </div>
                    
                    <div class="barramento-legend-item">
                        <span class="barramento-legend-color in-process"></span>
                        <span><?php printf(__('Em Processo (%d%%)', 'barramento-tainacan'), round($in_process_percent)); ?></span>
                    </div>
                    
                    <div class="barramento-legend-item">
                        <span class="barramento-legend-color failed"></span>
                        <span><?php printf(__('Falhas (%d%%)', 'barramento-tainacan'), round($failed_percent)); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($args['show_items'] && !empty($recent_items)): ?>
            <div class="barramento-collection-recent-items">
                <div class="barramento-collapsible-section">
                    <div class="barramento-collapsible-header">
                        <span><?php _e('Itens Recentes', 'barramento-tainacan'); ?></span>
                        <span class="barramento-toggle-icon dashicons dashicons-arrow-up-alt2"></span>
                    </div>
                    <div class="barramento-collapsible-content">
                        <table class="barramento-items-table">
                            <thead>
                                <tr>
                                    <th width="50%"><?php _e('Título', 'barramento-tainacan'); ?></th>
                                    <th><?php _e('Status', 'barramento-tainacan'); ?></th>
                                    <th><?php _e('Data', 'barramento-tainacan'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_items)): ?>
                                    <tr>
                                        <td colspan="3"><?php _e('Nenhum item encontrado', 'barramento-tainacan'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_items as $item): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo get_permalink($item['item_id']); ?>">
                                                    <?php echo esc_html($item['title']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="barramento-status-badge status-<?php echo esc_attr($item['preservation_status']); ?>">
                                                    <?php echo barramento_get_status_label($item['preservation_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date_i18n(get_option('date_format'), strtotime($item['last_status_update'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <p class="barramento-collection-actions">
                            <a href="<?php echo get_permalink($collection_id); ?>" class="barramento-collection-link">
                                <?php _e('Ver Detalhes', 'barramento-tainacan'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Shortcode para exibir o status de preservação de uma coleção
 *
 * @param array $atts Atributos do shortcode
 * @return string HTML do status de preservação
 */
function barramento_collection_status_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0,
        'show_stats' => 'true',
        'show_progress' => 'true',
        'show_items' => 'true',
        'items_count' => 5,
        'title' => __('Status de Preservação da Coleção', 'barramento-tainacan')
    ), $atts, 'barramento_collection');
    
    // Converte strings para booleanos
    $show_stats = filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN);
    $show_progress = filter_var($atts['show_progress'], FILTER_VALIDATE_BOOLEAN);
    $show_items = filter_var($atts['show_items'], FILTER_VALIDATE_BOOLEAN);
    $items_count = intval($atts['items_count']);
    
    // Configura os argumentos
    $args = array(
        'show_stats' => $show_stats,
        'show_progress' => $show_progress,
        'show_items' => $show_items,
        'items_count' => $items_count,
        'title' => $atts['title']
    );
    
    return barramento_tainacan_display_collection_status($atts['id'], $args);
}
