<?php
/**
 * Template para a página de relatórios
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Obtém as coleções do Tainacan
require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-tainacan-api.php';
$tainacan_api = new Barramento_Tainacan_API();
$collections_result = $tainacan_api->get_collections();

// Obtém estatísticas gerais
global $wpdb;
$objects_table = $wpdb->prefix . 'barramento_objects';
$logs_table = $wpdb->prefix . 'barramento_logs';
$queue_table = $wpdb->prefix . 'barramento_queue';
$hashes_table = $wpdb->prefix . 'barramento_hashes';

// Total de objetos por status
$status_counts = $wpdb->get_results(
    "SELECT preservation_status, COUNT(*) as count FROM $objects_table GROUP BY preservation_status",
    ARRAY_A
);

// Formatação das estatísticas para exibição
$stats = array(
    'total_objects' => 0,
    'preserved' => 0,
    'in_process' => 0,
    'failed' => 0,
    'not_preserved' => 0
);

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

foreach ($status_counts as $status) {
    $count = (int)$status['count'];
    $stats['total_objects'] += $count;
    
    $category = $status_map[$status['preservation_status']] ?? 'not_preserved';
    $stats[$category] += $count;
}

// Estatísticas de fila
$queue_stats = $wpdb->get_results(
    "SELECT status, COUNT(*) as count FROM $queue_table GROUP BY status",
    ARRAY_A
);

$queue_counts = array(
    'pending' => 0,
    'processing' => 0,
    'failed' => 0
);

foreach ($queue_stats as $stat) {
    $queue_counts[$stat['status']] = (int)$stat['count'];
}

// Total de logs por nível
$log_counts = $wpdb->get_results(
    "SELECT level, COUNT(*) as count FROM $logs_table GROUP BY level",
    ARRAY_A
);

$log_levels = array(
    'debug' => 0,
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'critical' => 0
);

foreach ($log_counts as $log) {
    $log_levels[$log['level']] = (int)$log['count'];
}

// Estatísticas por coleção
$collection_stats = array();

if (!is_wp_error($collections_result)) {
    foreach ($collections_result as $collection) {
        $collection_id = $collection['id'];
        
        // Obtém estatísticas desta coleção
        $coll_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT preservation_status, COUNT(*) as count FROM $objects_table WHERE collection_id = %d GROUP BY preservation_status",
                $collection_id
            ),
            ARRAY_A
        );
        
        $formatted_stats = array(
            'total' => 0,
            'preserved' => 0,
            'in_process' => 0,
            'failed' => 0,
            'id' => $collection_id,
            'name' => $collection['name'],
            'items_count' => $collection['items_count']
        );
        
        foreach ($coll_stats as $stat) {
            $count = (int)$stat['count'];
            $formatted_stats['total'] += $count;
            
            $category = $status_map[$stat['preservation_status']] ?? 'not_preserved';
            if ($category !== 'not_preserved') {
                $formatted_stats[$category] += $count;
            }
        }
        
        // Calcula a porcentagem de preservação
        $formatted_stats['preservation_percent'] = $formatted_stats['items_count'] > 0 ? 
            round(($formatted_stats['preserved'] / $formatted_stats['items_count']) * 100, 1) : 0;
        
        $collection_stats[] = $formatted_stats;
    }
}

// Ordena as coleções por porcentagem de preservação (decrescente)
usort($collection_stats, function($a, $b) {
    return $b['preservation_percent'] <=> $a['preservation_percent'];
});

// Data do último relatório
$last_report_date = current_time('mysql');

// URL para exportação
$export_url = admin_url('admin.php?page=barramento-tainacan-reports&action=export&format=');
?>

<div class="wrap barramento-tainacan">
    <h1><?php _e('Relatórios de Preservação Digital', 'barramento-tainacan'); ?></h1>
    
    <div class="barramento-report-actions">
        <div class="barramento-export-buttons">
            <a href="<?php echo esc_url($export_url . 'csv'); ?>" class="button">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php _e('Exportar CSV', 'barramento-tainacan'); ?>
            </a>
            
            <a href="<?php echo esc_url($export_url . 'json'); ?>" class="button">
                <span class="dashicons dashicons-media-code"></span>
                <?php _e('Exportar JSON', 'barramento-tainacan'); ?>
            </a>
            
            <a href="#" class="button" id="print-report">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Imprimir', 'barramento-tainacan'); ?>
            </a>
        </div>
        
        <div class="barramento-report-info">
            <?php printf(
                __('Relatório gerado em: %s', 'barramento-tainacan'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_report_date))
            ); ?>
        </div>
    </div>
    
    <div class="barramento-report-container">
        <div class="barramento-report-section">
            <h2><?php _e('Sumário de Preservação Digital', 'barramento-tainacan'); ?></h2>
            
            <div class="barramento-statistics barramento-report-summary">
                <div class="statistics-card">
                    <div class="statistic-value"><?php echo number_format_i18n($stats['total_objects']); ?></div>
                    <div class="statistic-label"><?php _e('Total de Objetos', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card success">
                    <div class="statistic-value"><?php echo number_format_i18n($stats['preserved']); ?></div>
                    <div class="statistic-label"><?php _e('Preservados', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card info">
                    <div class="statistic-value"><?php echo number_format_i18n($stats['in_process']); ?></div>
                    <div class="statistic-label"><?php _e('Em Processamento', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card warning">
                    <div class="statistic-value"><?php echo number_format_i18n($stats['failed']); ?></div>
                    <div class="statistic-label"><?php _e('Falhas', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card secondary">
                    <div class="statistic-value"><?php echo number_format_i18n(array_sum($queue_counts)); ?></div>
                    <div class="statistic-label"><?php _e('Na Fila', 'barramento-tainacan'); ?></div>
                </div>
            </div>
            
            <?php if ($stats['total_objects'] > 0): ?>
                <div class="barramento-report-chart">
                    <div class="barramento-progress-bar-large">
                        <?php 
                            $preserved_percent = $stats['total_objects'] > 0 ? ($stats['preserved'] / $stats['total_objects']) * 100 : 0;
                            $in_process_percent = $stats['total_objects'] > 0 ? ($stats['in_process'] / $stats['total_objects']) * 100 : 0;
                            $failed_percent = $stats['total_objects'] > 0 ? ($stats['failed'] / $stats['total_objects']) * 100 : 0;
                        ?>
                        <div class="barramento-progress-segment success" style="width: <?php echo esc_attr($preserved_percent); ?>%;">
                            <?php if ($preserved_percent >= 10): ?>
                                <?php echo number_format_i18n($preserved_percent, 1); ?>%
                            <?php endif; ?>
                        </div>
                        <div class="barramento-progress-segment info" style="width: <?php echo esc_attr($in_process_percent); ?>%;">
                            <?php if ($in_process_percent >= 10): ?>
                                <?php echo number_format_i18n($in_process_percent, 1); ?>%
                            <?php endif; ?>
                        </div>
                        <div class="barramento-progress-segment error" style="width: <?php echo esc_attr($failed_percent); ?>%;">
                            <?php if ($failed_percent >= 10): ?>
                                <?php echo number_format_i18n($failed_percent, 1); ?>%
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="barramento-progress-legend">
                        <div class="barramento-legend-item">
                            <span class="barramento-legend-color success"></span>
                            <span class="barramento-legend-label"><?php _e('Preservados', 'barramento-tainacan'); ?></span>
                        </div>
                        <div class="barramento-legend-item">
                            <span class="barramento-legend-color info"></span>
                            <span class="barramento-legend-label"><?php _e('Em Processamento', 'barramento-tainacan'); ?></span>
                        </div>
                        <div class="barramento-legend-item">
                            <span class="barramento-legend-color error"></span>
                            <span class="barramento-legend-label"><?php _e('Falhas', 'barramento-tainacan'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="barramento-report-section">
            <h2><?php _e('Estatísticas por Coleção', 'barramento-tainacan'); ?></h2>
            
            <?php if (empty($collection_stats)): ?>
                <p><?php _e('Nenhuma coleção encontrada.', 'barramento-tainacan'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Coleção', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Itens', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Preservados', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Em Processo', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Falhas', 'barramento-tainacan'); ?></th>
                            <th><?php _e('% Preservado', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collection_stats as $coll): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_permalink($coll['id']); ?>" target="_blank">
                                        <?php echo esc_html($coll['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format_i18n($coll['items_count']); ?></td>
                                <td><?php echo number_format_i18n($coll['preserved']); ?></td>
                                <td><?php echo number_format_i18n($coll['in_process']); ?></td>
                                <td><?php echo number_format_i18n($coll['failed']); ?></td>
                                <td>
                                    <div class="barramento-progress-mini-container">
                                        <div class="barramento-progress-mini">
                                            <div class="barramento-progress-mini-bar" style="width: <?php echo esc_attr($coll['preservation_percent']); ?>%;"></div>
                                        </div>
                                        <span class="barramento-progress-mini-value">
                                            <?php echo number_format_i18n($coll['preservation_percent'], 1); ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="barramento-report-section">
            <h2><?php _e('Análise de Conformidade ISO 16363', 'barramento-tainacan'); ?></h2>
            
            <table class="wp-list-table widefat fixed">
                <thead>
                    <tr>
                        <th><?php _e('Requisito', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Descrição', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Status', 'barramento-tainacan'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>4.1.3</td>
                        <td><?php _e('Análise dos SIPs', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.1.5</td>
                        <td><?php _e('Verificação de integridade', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.1.6</td>
                        <td><?php _e('Controle dos objetos digitais', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.2.4</td>
                        <td><?php _e('Identificadores únicos', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.2.8/4.4.1.2</td>
                        <td><?php _e('Verificação periódica de integridade', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.5.3</td>
                        <td><?php _e('Ligação entre AIP e Tainacan', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.6.1.1</td>
                        <td><?php _e('Registro de falhas e anomalias', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                    <tr>
                        <td>4.3.4</td>
                        <td><?php _e('Evidência da eficácia', 'barramento-tainacan'); ?></td>
                        <td><span class="barramento-compliance compliant"><?php _e('Conforme', 'barramento-tainacan'); ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="barramento-report-section">
            <h2><?php _e('Atividade de Preservação Recente', 'barramento-tainacan'); ?></h2>
            
            <?php
            // Obtém os objetos mais recentemente atualizados
            $recent_objects = $wpdb->get_results(
                "SELECT o.*, c.post_title as collection_name, i.post_title as item_title 
                 FROM $objects_table o
                 LEFT JOIN {$wpdb->posts} c ON o.collection_id = c.ID
                 LEFT JOIN {$wpdb->posts} i ON o.item_id = i.ID
                 ORDER BY o.last_status_update DESC LIMIT 10",
                ARRAY_A
            );
            ?>
            
            <?php if (empty($recent_objects)): ?>
                <p><?php _e('Nenhuma atividade de preservação recente.', 'barramento-tainacan'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Item', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Coleção', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Status', 'barramento-tainacan'); ?></th>
                            <th><?php _e('AIP ID', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Data', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_objects as $object): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_permalink($object['item_id']); ?>" target="_blank">
                                        <?php echo esc_html($object['item_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($object['collection_name']); ?></td>
                                <td>
                                    <span class="barramento-status-badge status-<?php echo esc_attr($object['preservation_status']); ?>">
                                        <?php 
                                        switch ($object['preservation_status']) {
                                            case 'not_preserved':
                                                _e('Não Preservado', 'barramento-tainacan');
                                                break;
                                            case 'sip_created':
                                                _e('SIP Criado', 'barramento-tainacan');
                                                break;
                                            case 'transfer_started':
                                                _e('Transferência Iniciada', 'barramento-tainacan');
                                                break;
                                            case 'transfer_failed':
                                                _e('Falha na Transferência', 'barramento-tainacan');
                                                break;
                                            case 'ingest_started':
                                                _e('Ingestão Iniciada', 'barramento-tainacan');
                                                break;
                                            case 'ingest_failed':
                                                _e('Falha na Ingestão', 'barramento-tainacan');
                                                break;
                                            case 'aip_stored':
                                                _e('AIP Armazenado', 'barramento-tainacan');
                                                break;
                                            case 'fully_preserved':
                                                _e('Totalmente Preservado', 'barramento-tainacan');
                                                break;
                                            default:
                                                echo esc_html($object['preservation_status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($object['aip_id'])): ?>
                                        <?php if (!empty($object['archivematica_url'])): ?>
                                            <a href="<?php echo esc_url($object['archivematica_url']); ?>" target="_blank">
                                                <?php echo esc_html(substr($object['aip_id'], 0, 8) . '...'); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo esc_html(substr($object['aip_id'], 0, 8) . '...'); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($object['last_status_update'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.barramento-report-actions {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    background-color: #fff;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 3px;
    align-items: center;
}

.barramento-export-buttons {
    display: flex;
    gap: 10px;
}

.barramento-report-info {
    color: #666;
    font-style: italic;
}

.barramento-report-container {
    background-color: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.barramento-report-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.barramento-report-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.barramento-report-summary {
    margin-bottom: 20px;
}

.barramento-progress-bar-large {
    height: 25px;
    background-color: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    display: flex;
    margin-bottom: 10px;
}

.barramento-progress-segment {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 12px;
    text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
}

.barramento-progress-legend {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.barramento-legend-item {
    display: flex;
    align-items: center;
}

.barramento-legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    margin-right: 5px;
}

.barramento-legend-color.success {
    background-color: #46b450;
}

.barramento-legend-color.info {
    background-color: #00a0d2;
}

.barramento-legend-color.error {
    background-color: #dc3232;
}

.barramento-progress-mini-container {
    display: flex;
    align-items: center;
}

.barramento-progress-mini {
    width: 70%;
    height: 8px;
    background-color: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    margin-right: 10px;
}

.barramento-progress-mini-bar {
    height: 100%;
    background-color: #46b450;
}

.barramento-progress-mini-value {
    font-size: 12px;
    color: #666;
}

.barramento-compliance {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.barramento-compliance.compliant {
    background-color: #edf7ed;
    color: #2a8a31;
}

.barramento-compliance.partial {
    background-color: #fff8e5;
    color: #996800;
}

.barramento-compliance.non-compliant {
    background-color: #f9e2e2;
    color: #b32d2e;
}

@media print {
    .barramento-export-buttons, #adminmenumain, #wpadminbar, .wp-header-end, .notice, .updated, .update-nag, #wpfooter {
        display: none !important;
    }
    
    #wpcontent, #wpfooter {
        margin-left: 0 !important;
    }
    
    .barramento-report-container {
        border: none;
        padding: 0;
    }
    
    .wrap h1 {
        text-align: center;
        margin-bottom: 20px;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Botão de impressão
    $('#print-report').on('click', function(e) {
        e.preventDefault();
        window.print();
    });
});
</script>
