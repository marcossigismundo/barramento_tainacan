<?php
/**
 * Template para a página de dashboard
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Obtém as estatísticas para o dashboard
global $wpdb;
$objects_table = $wpdb->prefix . 'barramento_objects';
$queue_table = $wpdb->prefix . 'barramento_queue';
$logs_table = $wpdb->prefix . 'barramento_logs';

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

// Itens na fila
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

// Logs recentes
$recent_logs = $wpdb->get_results(
    "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 10",
    ARRAY_A
);

// Atividades recentes
$recent_activities = $wpdb->get_results(
    "SELECT o.*, c.post_title as collection_name, i.post_title as item_title 
     FROM $objects_table o
     LEFT JOIN {$wpdb->posts} c ON o.collection_id = c.ID
     LEFT JOIN {$wpdb->posts} i ON o.item_id = i.ID
     ORDER BY o.last_status_update DESC LIMIT 10",
    ARRAY_A
);

// Verifica se o Archivematica está configurado
$archivematica_url = get_option('barramento_archivematica_url', '');
$archivematica_configured = !empty($archivematica_url);
?>

<div class="wrap barramento-dashboard">
    <h1><?php _e('Dashboard do Barramento Tainacan', 'barramento-tainacan'); ?></h1>
    
    <div class="barramento-welcome-panel">
        <div class="welcome-panel-content">
            <h2><?php _e('Sistema de Preservação Digital', 'barramento-tainacan'); ?></h2>
            <p class="about-description">
                <?php _e('Este dashboard apresenta uma visão geral do processo de preservação digital integrado ao Archivematica.', 'barramento-tainacan'); ?>
            </p>
            
            <?php if (!$archivematica_configured): ?>
            <div class="barramento-notice barramento-warning">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('O Archivematica não está configurado. Por favor, configure as credenciais em Configurações.', 'barramento-tainacan'); ?>
                    <a href="<?php echo admin_url('admin.php?page=barramento-tainacan-settings'); ?>" class="button"><?php _e('Configurar agora', 'barramento-tainacan'); ?></a>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="barramento-statistics">
                <div class="statistics-card">
                    <div class="statistic-value"><?php echo $stats['total_objects']; ?></div>
                    <div class="statistic-label"><?php _e('Total de Objetos', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card success">
                    <div class="statistic-value"><?php echo $stats['preserved']; ?></div>
                    <div class="statistic-label"><?php _e('Preservados', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card info">
                    <div class="statistic-value"><?php echo $stats['in_process']; ?></div>
                    <div class="statistic-label"><?php _e('Em Processamento', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card warning">
                    <div class="statistic-value"><?php echo $stats['failed']; ?></div>
                    <div class="statistic-label"><?php _e('Falhas', 'barramento-tainacan'); ?></div>
                </div>
                
                <div class="statistics-card secondary">
                    <div class="statistic-value"><?php echo array_sum($queue_counts); ?></div>
                    <div class="statistic-label"><?php _e('Na Fila', 'barramento-tainacan'); ?></div>
                </div>
            </div>
            
            <div class="welcome-panel-column-container">
                <div class="welcome-panel-column">
                    <h3><?php _e('Ações Rápidas', 'barramento-tainacan'); ?></h3>
                    <a class="button button-primary" href="<?php echo admin_url('admin.php?page=barramento-tainacan-collections'); ?>">
                        <?php _e('Gerenciar Coleções', 'barramento-tainacan'); ?>
                    </a>
                    <p></p>
                    <a class="button" href="<?php echo admin_url('admin.php?page=barramento-tainacan-logs'); ?>">
                        <?php _e('Ver Todos os Logs', 'barramento-tainacan'); ?>
                    </a>
                    <p></p>
                    <a class="button" href="<?php echo admin_url('admin.php?page=barramento-tainacan-settings'); ?>">
                        <?php _e('Configurações', 'barramento-tainacan'); ?>
                    </a>
                </div>
                
                <div class="welcome-panel-column">
                    <h3><?php _e('Status da Fila', 'barramento-tainacan'); ?></h3>
                    <ul>
                        <li>
                            <span class="dashicons dashicons-clock"></span>
                            <?php printf(__('Pendentes: %d', 'barramento-tainacan'), $queue_counts['pending']); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-update"></span>
                            <?php printf(__('Em processamento: %d', 'barramento-tainacan'), $queue_counts['processing']); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-warning"></span>
                            <?php printf(__('Falhas: %d', 'barramento-tainacan'), $queue_counts['failed']); ?>
                        </li>
                    </ul>
                    
                    <button id="process-queue-button" class="button">
                        <?php _e('Processar Fila Agora', 'barramento-tainacan'); ?>
                    </button>
                    <span id="queue-processing-spinner" class="spinner"></span>
                </div>
                
                <div class="welcome-panel-column">
                    <h3><?php _e('Processo de Preservação', 'barramento-tainacan'); ?></h3>
                    <button id="check-transfers-button" class="button">
                        <?php _e('Verificar Transferências', 'barramento-tainacan'); ?>
                    </button>
                    <span id="transfers-checking-spinner" class="spinner"></span>
                    <p></p>
                    <button id="check-ingests-button" class="button">
                        <?php _e('Verificar Ingestões', 'barramento-tainacan'); ?>
                    </button>
                    <span id="ingests-checking-spinner" class="spinner"></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="barramento-dashboard-content">
        <div class="barramento-content-column">
            <div class="barramento-card">
                <h3><?php _e('Atividades Recentes', 'barramento-tainacan'); ?></h3>
                
                <table class="widefat barramento-table">
                    <thead>
                        <tr>
                            <th><?php _e('Item', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Coleção', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Status', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Data', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_activities)): ?>
                            <tr>
                                <td colspan="4"><?php _e('Nenhuma atividade recente encontrada.', 'barramento-tainacan'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_permalink($activity['item_id']); ?>" target="_blank">
                                            <?php echo esc_html($activity['item_title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($activity['collection_name']); ?></td>
                                    <td>
                                        <span class="barramento-status-badge status-<?php echo esc_attr($activity['preservation_status']); ?>">
                                            <?php echo $this->get_status_label($activity['preservation_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo human_time_diff(strtotime($activity['last_status_update']), current_time('timestamp')); ?>
                                        <?php _e('atrás', 'barramento-tainacan'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="barramento-content-column">
            <div class="barramento-card">
                <h3><?php _e('Logs Recentes', 'barramento-tainacan'); ?></h3>
                
                <table class="widefat barramento-table">
                    <thead>
                        <tr>
                            <th><?php _e('Nível', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Mensagem', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Data', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_logs)): ?>
                            <tr>
                                <td colspan="3"><?php _e('Nenhum log recente encontrado.', 'barramento-tainacan'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="barramento-log-level level-<?php echo esc_attr($log['level']); ?>">
                                            <?php echo strtoupper(esc_html($log['level'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                    <td>
                                        <?php echo human_time_diff(strtotime($log['created_at']), current_time('timestamp')); ?>
                                        <?php _e('atrás', 'barramento-tainacan'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p class="barramento-card-footer">
                    <a href="<?php echo admin_url('admin.php?page=barramento-tainacan-logs'); ?>" class="button">
                        <?php _e('Ver Todos os Logs', 'barramento-tainacan'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Processar fila
    $('#process-queue-button').on('click', function() {
        var button = $(this);
        var spinner = $('#queue-processing-spinner');
        
        button.attr('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_process_queue',
                nonce: barramento_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || barramento_admin.i18n.error);
                }
            },
            error: function() {
                alert(barramento_admin.i18n.error);
            },
            complete: function() {
                button.attr('disabled', false);
                spinner.removeClass('is-active');
                location.reload();
            }
        });
    });
    
    // Verificar transferências
    $('#check-transfers-button').on('click', function() {
        var button = $(this);
        var spinner = $('#transfers-checking-spinner');
        
        button.attr('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_check_transfers',
                nonce: barramento_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || barramento_admin.i18n.error);
                }
            },
            error: function() {
                alert(barramento_admin.i18n.error);
            },
            complete: function() {
                button.attr('disabled', false);
                spinner.removeClass('is-active');
                location.reload();
            }
        });
    });
    
    // Verificar ingestões
    $('#check-ingests-button').on('click', function() {
        var button = $(this);
        var spinner = $('#ingests-checking-spinner');
        
        button.attr('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_check_ingests',
                nonce: barramento_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || barramento_admin.i18n.error);
                }
            },
            error: function() {
                alert(barramento_admin.i18n.error);
            },
            complete: function() {
                button.attr('disabled', false);
                spinner.removeClass('is-active');
                location.reload();
            }
        });
    });
});
</script>
