<?php
/**
 * Template para a página de gerenciamento de coleções
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

// Obtém as coleções habilitadas para preservação
$enabled_collections = get_option('barramento_enabled_collections', array());

// Obtém o mapeamento de metadados
$metadata_mapping = get_option('barramento_metadata_mapping', array());

// Obtém os metadados obrigatórios
$required_metadata = get_option('barramento_required_metadata', array());

// Obtém os metadados fixos
$fixed_metadata = get_option('barramento_fixed_metadata', array());

// Verifica se houve erro ao obter as coleções
$has_error = is_wp_error($collections_result);

// Obtém as estatísticas de preservação por coleção
global $wpdb;
$objects_table = $wpdb->prefix . 'barramento_objects';

$collection_stats = array();
if (!$has_error) {
    foreach ($collections_result as $collection) {
        $collection_id = $collection['id'];
        
        // Obtém o total de objetos preservados para esta coleção
        $stats = $wpdb->get_results(
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
            'collection_id' => $collection_id
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
        
        foreach ($stats as $stat) {
            $count = (int)$stat['count'];
            $formatted_stats['total'] += $count;
            
            $category = $status_map[$stat['preservation_status']] ?? 'not_preserved';
            if ($category !== 'not_preserved') {
                $formatted_stats[$category] += $count;
            }
        }
        
        $collection_stats[$collection_id] = $formatted_stats;
    }
}
?>

<div class="wrap barramento-tainacan">
    <h1><?php _e('Gerenciamento de Coleções', 'barramento-tainacan'); ?></h1>
    
    <?php if ($has_error): ?>
    <div class="barramento-notice barramento-error">
        <p>
            <span class="dashicons dashicons-warning"></span>
            <?php _e('Erro ao carregar coleções do Tainacan', 'barramento-tainacan'); ?>: <?php echo esc_html($collections_result->get_error_message()); ?>
        </p>
    </div>
    <?php elseif (empty($collections_result)): ?>
    <div class="barramento-notice barramento-warning">
        <p>
            <span class="dashicons dashicons-info"></span>
            <?php _e('Nenhuma coleção encontrada no Tainacan. Crie coleções no Tainacan antes de configurar a preservação.', 'barramento-tainacan'); ?>
        </p>
    </div>
    <?php else: ?>
    
    <div class="barramento-tabs">
        <div class="barramento-tab active">
            <a href="#tab-collections"><?php _e('Coleções', 'barramento-tainacan'); ?></a>
        </div>
        <div class="barramento-tab">
            <a href="#tab-metadata"><?php _e('Metadados', 'barramento-tainacan'); ?></a>
        </div>
    </div>
    
    <div id="tab-collections" class="barramento-tab-content active">
        <p><?php _e('Selecione quais coleções do Tainacan serão incluídas no processo de preservação digital.', 'barramento-tainacan'); ?></p>
        
        <form method="post" action="options.php" id="collections-form">
            <?php settings_fields('barramento_collections_settings'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="select-all-collections">
                        </th>
                        <th><?php _e('Coleção', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Total de Itens', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Status de Preservação', 'barramento-tainacan'); ?></th>
                        <th><?php _e('Ações', 'barramento-tainacan'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections_result as $collection): ?>
                        <?php 
                            $collection_id = $collection['id'];
                            $is_enabled = in_array($collection_id, $enabled_collections);
                            $stats = $collection_stats[$collection_id] ?? array('total' => 0, 'preserved' => 0, 'in_process' => 0, 'failed' => 0);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="barramento_enabled_collections[]" value="<?php echo esc_attr($collection_id); ?>" <?php checked($is_enabled); ?>>
                            </td>
                            <td>
                                <strong><?php echo esc_html($collection['name']); ?></strong>
                                <?php if (!empty($collection['description'])): ?>
                                    <p class="description"><?php echo wp_trim_words(esc_html($collection['description']), 15); ?></p>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($collection['items_count']); ?></td>
                            <td>
                                <?php if ($stats['total'] > 0): ?>
                                    <div class="barramento-progress-container">
                                        <div class="barramento-progress-bar">
                                            <?php 
                                                $preserved_percent = $stats['total'] > 0 ? ($stats['preserved'] / $stats['total']) * 100 : 0;
                                                $in_process_percent = $stats['total'] > 0 ? ($stats['in_process'] / $stats['total']) * 100 : 0;
                                                $failed_percent = $stats['total'] > 0 ? ($stats['failed'] / $stats['total']) * 100 : 0;
                                            ?>
                                            <div class="barramento-progress-segment success" style="width: <?php echo esc_attr($preserved_percent); ?>%;" title="<?php echo esc_attr(sprintf(__('%d preservados', 'barramento-tainacan'), $stats['preserved'])); ?>"></div>
                                            <div class="barramento-progress-segment info" style="width: <?php echo esc_attr($in_process_percent); ?>%;" title="<?php echo esc_attr(sprintf(__('%d em processamento', 'barramento-tainacan'), $stats['in_process'])); ?>"></div>
                                            <div class="barramento-progress-segment error" style="width: <?php echo esc_attr($failed_percent); ?>%;" title="<?php echo esc_attr(sprintf(__('%d falhas', 'barramento-tainacan'), $stats['failed'])); ?>"></div>
                                        </div>
                                        <div class="barramento-progress-stats">
                                            <?php printf(__('%d de %d itens preservados', 'barramento-tainacan'), $stats['preserved'], $stats['total']); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="barramento-text-muted"><?php _e('Nenhum item processado', 'barramento-tainacan'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_enabled): ?>
                                    <button type="button" class="button queue-collection" data-collection-id="<?php echo esc_attr($collection_id); ?>">
                                        <?php _e('Adicionar à Fila', 'barramento-tainacan'); ?>
                                    </button>
                                    <span class="spinner collection-spinner"></span>
                                <?php else: ?>
                                    <span class="barramento-text-muted"><?php _e('Habilite a coleção primeiro', 'barramento-tainacan'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php submit_button(__('Salvar Configurações de Coleções', 'barramento-tainacan')); ?>
        </form>
    </div>
    
    <div id="tab-metadata" class="barramento-tab-content">
        <p><?php _e('Configure o mapeamento de metadados para Dublin Core e defina metadados obrigatórios para a preservação.', 'barramento-tainacan'); ?></p>
        
        <form method="post" action="options.php" id="metadata-form">
            <?php settings_fields('barramento_collections_settings'); ?>
            
            <div class="barramento-form-section">
                <h2><?php _e('Metadados Obrigatórios', 'barramento-tainacan'); ?></h2>
                <p><?php _e('Selecione quais metadados são obrigatórios para a preservação. Itens que não possuírem estes metadados preenchidos não serão preservados.', 'barramento-tainacan'); ?></p>
                
                <table class="wp-list-table widefat fixed">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all-required">
                            </th>
                            <th><?php _e('Metadado', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Tipo', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Lista todos os metadados de todas as coleções
                        $all_metadata = array();
                        foreach ($collections_result as $collection) {
                            if (!empty($collection['metadata'])) {
                                foreach ($collection['metadata'] as $metadata) {
                                    $meta_key = $metadata['id'];
                                    $all_metadata[$meta_key] = $metadata;
                                }
                            }
                        }
                        
                        if (empty($all_metadata)):
                        ?>
                            <tr>
                                <td colspan="3"><?php _e('Nenhum metadado encontrado nas coleções.', 'barramento-tainacan'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_metadata as $meta_id => $metadata): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="barramento_required_metadata[]" value="<?php echo esc_attr($meta_id); ?>" <?php checked(in_array($meta_id, $required_metadata)); ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($metadata['name']); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($metadata['type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="barramento-form-section">
                <h2><?php _e('Mapeamento de Metadados para Dublin Core', 'barramento-tainacan'); ?></h2>
                <p><?php _e('Mapeie os metadados do Tainacan para os elementos Dublin Core equivalentes. Este mapeamento será utilizado para gerar os metadados descritivos no pacote SIP.', 'barramento-tainacan'); ?></p>
                
                <table class="wp-list-table widefat fixed">
                    <thead>
                        <tr>
                            <th><?php _e('Metadado Tainacan', 'barramento-tainacan'); ?></th>
                            <th><?php _e('Elemento Dublin Core', 'barramento-tainacan'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Define elementos Dublin Core
                        $dc_elements = array(
                            '' => __('Não mapear', 'barramento-tainacan'),
                            'dc:title' => 'Title',
                            'dc:creator' => 'Creator',
                            'dc:subject' => 'Subject',
                            'dc:description' => 'Description',
                            'dc:publisher' => 'Publisher',
                            'dc:contributor' => 'Contributor',
                            'dc:date' => 'Date',
                            'dc:type' => 'Type',
                            'dc:format' => 'Format',
                            'dc:identifier' => 'Identifier',
                            'dc:source' => 'Source',
                            'dc:language' => 'Language',
                            'dc:relation' => 'Relation',
                            'dc:coverage' => 'Coverage',
                            'dc:rights' => 'Rights'
                        );
                        
                        if (empty($all_metadata)):
                        ?>
                            <tr>
                                <td colspan="2"><?php _e('Nenhum metadado encontrado nas coleções.', 'barramento-tainacan'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_metadata as $meta_id => $metadata): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($metadata['name']); ?></strong>
                                    </td>
                                    <td>
                                        <select name="barramento_metadata_mapping[<?php echo esc_attr($meta_id); ?>]">
                                            <?php foreach ($dc_elements as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected(isset($metadata_mapping[$meta_id]) ? $metadata_mapping[$meta_id] : '', $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="barramento-form-section">
                <h2><?php _e('Metadados Fixos', 'barramento-tainacan'); ?></h2>
                <p><?php _e('Defina metadados fixos que serão incluídos em todos os pacotes SIP, independentemente dos metadados dos itens.', 'barramento-tainacan'); ?></p>
                
                <div id="fixed-metadata-container">
                    <?php 
                    if (!empty($fixed_metadata)):
                        foreach ($fixed_metadata as $dc_element => $value):
                    ?>
                        <div class="barramento-form-row fixed-metadata-row">
                            <select name="barramento_fixed_metadata_elements[]" class="fixed-metadata-element">
                                <?php foreach ($dc_elements as $element_value => $element_label): ?>
                                    <?php if (!empty($element_value)): ?>
                                        <option value="<?php echo esc_attr($element_value); ?>" <?php selected($dc_element, $element_value); ?>>
                                            <?php echo esc_html($element_label); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="barramento_fixed_metadata_values[]" value="<?php echo esc_attr($value); ?>" class="fixed-metadata-value" placeholder="<?php esc_attr_e('Valor', 'barramento-tainacan'); ?>">
                            <button type="button" class="button remove-fixed-metadata"><?php _e('Remover', 'barramento-tainacan'); ?></button>
                        </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <div class="barramento-form-row fixed-metadata-row">
                            <select name="barramento_fixed_metadata_elements[]" class="fixed-metadata-element">
                                <?php foreach ($dc_elements as $element_value => $element_label): ?>
                                    <?php if (!empty($element_value)): ?>
                                        <option value="<?php echo esc_attr($element_value); ?>">
                                            <?php echo esc_html($element_label); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="barramento_fixed_metadata_values[]" value="" class="fixed-metadata-value" placeholder="<?php esc_attr_e('Valor', 'barramento-tainacan'); ?>">
                            <button type="button" class="button remove-fixed-metadata"><?php _e('Remover', 'barramento-tainacan'); ?></button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="button" id="add-fixed-metadata"><?php _e('Adicionar Metadado Fixo', 'barramento-tainacan'); ?></button>
            </div>
            
            <?php submit_button(__('Salvar Configurações de Metadados', 'barramento-tainacan')); ?>
        </form>
    </div>
    
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Navegação por abas
    $('.barramento-tab a').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Ativa a aba
        $('.barramento-tab').removeClass('active');
        $(this).parent('.barramento-tab').addClass('active');
        
        // Mostra o conteúdo da aba
        $('.barramento-tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Seleção de todas as coleções
    $('#select-all-collections').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="barramento_enabled_collections[]"]').prop('checked', isChecked);
    });
    
    // Seleção de todos os metadados obrigatórios
    $('#select-all-required').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="barramento_required_metadata[]"]').prop('checked', isChecked);
    });
    
    // Adicionar metadado fixo
    $('#add-fixed-metadata').on('click', function() {
        var template = $('.fixed-metadata-row').first().clone();
        template.find('input').val('');
        template.find('select').val(template.find('select option:first').val());
        $('#fixed-metadata-container').append(template);
    });
    
    // Remover metadado fixo
    $(document).on('click', '.remove-fixed-metadata', function() {
        if ($('.fixed-metadata-row').length > 1) {
            $(this).closest('.fixed-metadata-row').remove();
        } else {
            // Se é o último, apenas limpa os valores
            var row = $(this).closest('.fixed-metadata-row');
            row.find('input').val('');
            row.find('select').val(row.find('select option:first').val());
        }
    });
    
    // Adicionar coleção à fila
    $('.queue-collection').on('click', function() {
        var button = $(this);
        var spinner = button.next('.collection-spinner');
        var collection_id = button.data('collection-id');
        
        button.attr('disabled', true);
        spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_index_collection',
                nonce: barramento_admin.nonce,
                collection_id: collection_id,
                force_update: false
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
            }
        });
    });
    
    // Processa o formulário de metadados
    $('#metadata-form').on('submit', function() {
        // Prepara os metadados fixos
        var fixed_metadata = {};
        $('.fixed-metadata-row').each(function() {
            var element = $(this).find('.fixed-metadata-element').val();
            var value = $(this).find('.fixed-metadata-value').val();
            
            if (element && value) {
                fixed_metadata[element] = value;
            }
        });
        
        // Adiciona campo oculto com os metadados fixos formatados
        if (Object.keys(fixed_metadata).length > 0) {
            $(this).append('<input type="hidden" name="barramento_fixed_metadata" value="' + JSON.stringify(fixed_metadata) + '">');
        }
    });
});
</script>

<style>
.barramento-progress-container {
    margin-top: 5px;
}

.barramento-progress-bar {
    height: 10px;
    width: 100%;
    background-color: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    display: flex;
}

.barramento-progress-segment {
    height: 100%;
}

.barramento-progress-segment.success {
    background-color: #46b450;
}

.barramento-progress-segment.info {
    background-color: #00a0d2;
}

.barramento-progress-segment.error {
    background-color: #dc3232;
}

.barramento-progress-stats {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.barramento-text-muted {
    color: #999;
    font-style: italic;
}

.fixed-metadata-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.fixed-metadata-element {
    width: 180px;
}

.fixed-metadata-value {
    flex: 1;
    max-width: 400px;
}
</style>
