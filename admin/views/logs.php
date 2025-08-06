<?php
/**
 * Template para a página de logs
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Carrega a classe de logger
require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php';
$logger = new Barramento_Tainacan_Logger();

// Obtém parâmetros da URL para filtros
$level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : '';
$collection_id = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : '';
$batch_id = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;

// Parâmetros de filtro
$filter_args = array(
    'level' => $level,
    'search' => $search,
    'item_id' => $item_id,
    'collection_id' => $collection_id,
    'batch_id' => $batch_id,
    'page' => $page,
    'per_page' => 50
);

// Busca os logs
$logs_result = $logger->get_logs($filter_args);

// Opções para níveis de log
$log_levels = array(
    '' => __('Todos os níveis', 'barramento-tainacan'),
    'debug' => 'Debug',
    'info' => 'Info',
    'warning' => 'Warning',
    'error' => 'Error',
    'critical' => 'Critical'
);

// URL base para paginação
$base_url = admin_url('admin.php?page=barramento-tainacan-logs');
?>

<div class="wrap barramento-tainacan">
    <h1><?php _e('Logs do Barramento Tainacan', 'barramento-tainacan'); ?></h1>
    
    <div class="barramento-filter-container">
        <form method="get" action="">
            <input type="hidden" name="page" value="barramento-tainacan-logs">
            
            <div class="barramento-filter-row">
                <div class="barramento-filter-column">
                    <select name="level">
                        <?php foreach ($log_levels as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($level, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="barramento-filter-column">
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar nos logs...', 'barramento-tainacan'); ?>">
                </div>
                
                <div class="barramento-filter-column">
                    <input type="number" name="item_id" value="<?php echo esc_attr($item_id); ?>" placeholder="<?php esc_attr_e('ID do Item', 'barramento-tainacan'); ?>">
                </div>
                
                <div class="barramento-filter-column">
                    <input type="number" name="collection_id" value="<?php echo esc_attr($collection_id); ?>" placeholder="<?php esc_attr_e('ID da Coleção', 'barramento-tainacan'); ?>">
                </div>
                
                <div class="barramento-filter-column">
                    <input type="text" name="batch_id" value="<?php echo esc_attr($batch_id); ?>" placeholder="<?php esc_attr_e('ID do Lote', 'barramento-tainacan'); ?>">
                </div>
                
                <div class="barramento-filter-column">
                    <input type="submit" class="button" value="<?php esc_attr_e('Filtrar', 'barramento-tainacan'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=barramento-tainacan-logs')); ?>" class="button-link"><?php _e('Limpar', 'barramento-tainacan'); ?></a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="barramento-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50"><?php _e('ID', 'barramento-tainacan'); ?></th>
                    <th width="100"><?php _e('Nível', 'barramento-tainacan'); ?></th>
                    <th><?php _e('Mensagem', 'barramento-tainacan'); ?></th>
                    <th width="150"><?php _e('Data', 'barramento-tainacan'); ?></th>
                    <th width="100"><?php _e('Item', 'barramento-tainacan'); ?></th>
                    <th width="100"><?php _e('Coleção', 'barramento-tainacan'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs_result['logs'])): ?>
                    <tr>
                        <td colspan="6"><?php _e('Nenhum log encontrado.', 'barramento-tainacan'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs_result['logs'] as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['id']); ?></td>
                            <td>
                                <span class="barramento-log-level level-<?php echo esc_attr($log['level']); ?>">
                                    <?php echo strtoupper(esc_html($log['level'])); ?>
                                </span>
                            </td>
                            <td class="log-message-cell">
                                <?php echo esc_html($log['message']); ?>
                                <?php if (!empty($log['context'])): ?>
                                    <button type="button" class="toggle-context button-link" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                    <div class="log-context" id="context-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                        <pre><?php echo esc_html(wp_json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <?php if (!empty($log['item_id'])): ?>
                                    <a href="<?php echo get_permalink($log['item_id']); ?>" target="_blank">
                                        <?php echo esc_html($log['item_id']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['collection_id'])): ?>
                                    <a href="<?php echo get_permalink($log['collection_id']); ?>" target="_blank">
                                        <?php echo esc_html($log['collection_id']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($logs_result['total'] > 0): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s itens', $logs_result['total'], 'barramento-tainacan'),
                            number_format_i18n($logs_result['total'])
                        ); ?>
                    </span>
                    
                    <?php
                    $total_pages = $logs_result['pages'];
                    
                    if ($total_pages > 1):
                        $disable_first = $page <= 1;
                        $disable_last = $page >= $total_pages;
                        
                        $current_url = add_query_arg($filter_args, $base_url);
                        
                        // Gera URLs para paginação
                        $first_page_url = remove_query_arg('paged', $current_url);
                        $last_page_url = add_query_arg('paged', $total_pages, $current_url);
                        $prev_page_url = add_query_arg('paged', max(1, $page - 1), $current_url);
                        $next_page_url = add_query_arg('paged', min($total_pages, $page + 1), $current_url);
                    ?>
                    <span class="pagination-links">
                        <a class="first-page button <?php echo $disable_first ? 'disabled' : ''; ?>" href="<?php echo esc_url($first_page_url); ?>">
                            <span class="screen-reader-text"><?php _e('Primeira página', 'barramento-tainacan'); ?></span>
                            <span aria-hidden="true">«</span>
                        </a>
                        <a class="prev-page button <?php echo $disable_first ? 'disabled' : ''; ?>" href="<?php echo esc_url($prev_page_url); ?>">
                            <span class="screen-reader-text"><?php _e('Página anterior', 'barramento-tainacan'); ?></span>
                            <span aria-hidden="true">‹</span>
                        </a>
                        
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text"><?php _e('Página atual', 'barramento-tainacan'); ?></label>
                            <span class="current-page"><?php echo esc_html($page); ?></span>
                            <span class="tablenav-paging-text"> <?php _e('de', 'barramento-tainacan'); ?> <span class="total-pages"><?php echo esc_html($total_pages); ?></span></span>
                        </span>
                        
                        <a class="next-page button <?php echo $disable_last ? 'disabled' : ''; ?>" href="<?php echo esc_url($next_page_url); ?>">
                            <span class="screen-reader-text"><?php _e('Próxima página', 'barramento-tainacan'); ?></span>
                            <span aria-hidden="true">›</span>
                        </a>
                        <a class="last-page button <?php echo $disable_last ? 'disabled' : ''; ?>" href="<?php echo esc_url($last_page_url); ?>">
                            <span class="screen-reader-text"><?php _e('Última página', 'barramento-tainacan'); ?></span>
                            <span aria-hidden="true">»</span>
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.barramento-filter-container {
    margin-bottom: 20px;
    background-color: #fff;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.barramento-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}

.barramento-filter-column {
    margin-bottom: 10px;
}

.log-message-cell {
    position: relative;
}

.toggle-context {
    margin-left: 5px;
    color: #0073aa;
    cursor: pointer;
    padding: 0;
    vertical-align: middle;
}

.log-context {
    margin-top: 10px;
    padding: 10px;
    background-color: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 3px;
    max-height: 200px;
    overflow-y: auto;
    font-size: 12px;
}

.log-context pre {
    margin: 0;
    white-space: pre-wrap;
}

.button-link {
    text-decoration: none;
}

.tablenav-pages .button.disabled {
    color: #a0a5aa !important;
    border-color: #ddd !important;
    background: #f7f7f7 !important;
    box-shadow: none !important;
    text-shadow: 0 1px 0 #fff !important;
    cursor: default;
    transform: none !important;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle para exibir/ocultar contexto
    $('.toggle-context').on('click', function() {
        var logId = $(this).data('log-id');
        var contextDiv = $('#context-' + logId);
        var icon = $(this).find('.dashicons');
        
        if (contextDiv.is(':visible')) {
            contextDiv.hide();
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
            contextDiv.show();
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }
    });
});
</script>
