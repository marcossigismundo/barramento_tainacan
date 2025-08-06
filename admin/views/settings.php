<?php
/**
 * Template para a página de configurações
 *
 * @package Barramento_Tainacan
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Obtém valores atuais das configurações
$archivematica_url = get_option('barramento_archivematica_url', '');
$archivematica_user = get_option('barramento_archivematica_user', '');
$archivematica_api_key = get_option('barramento_archivematica_api_key', '');

$storage_service_url = get_option('barramento_storage_service_url', '');
$storage_service_user = get_option('barramento_storage_service_user', '');
$storage_service_api_key = get_option('barramento_storage_service_api_key', '');

$max_sip_size = get_option('barramento_max_sip_size', 1024);
$schedule_frequency = get_option('barramento_schedule_frequency', 'daily');
$hash_algorithm = get_option('barramento_hash_algorithm', 'sha256');
$retry_attempts = get_option('barramento_retry_attempts', 3);

$debug_mode = get_option('barramento_debug_mode', false);

// Opções para algoritmos de hash
$hash_algorithms = array(
    'md5' => 'MD5',
    'sha1' => 'SHA-1',
    'sha256' => 'SHA-256 (recomendado)',
    'sha512' => 'SHA-512'
);

// Opções para frequência de agendamento
$schedule_frequencies = array(
    'hourly' => __('A cada hora', 'barramento-tainacan'),
    'twicedaily' => __('Duas vezes ao dia', 'barramento-tainacan'),
    'daily' => __('Diariamente', 'barramento-tainacan'),
    'weekly' => __('Semanalmente', 'barramento-tainacan')
);

// Verifica se o Tainacan está ativo
$tainacan_active = class_exists('Tainacan\Tainacan');
?>

<div class="wrap barramento-tainacan">
    <h1><?php _e('Configurações do Barramento Tainacan', 'barramento-tainacan'); ?></h1>
    
    <?php if (!$tainacan_active): ?>
    <div class="barramento-notice barramento-error">
        <p>
            <span class="dashicons dashicons-warning"></span>
            <?php _e('O plugin Tainacan não está ativo. O Barramento Tainacan requer o Tainacan para funcionar corretamente.', 'barramento-tainacan'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="barramento-tabs">
        <div class="barramento-tab active">
            <a href="#tab-general"><?php _e('Geral', 'barramento-tainacan'); ?></a>
        </div>
        <div class="barramento-tab">
            <a href="#tab-preservation"><?php _e('Preservação', 'barramento-tainacan'); ?></a>
        </div>
        <div class="barramento-tab">
            <a href="#tab-advanced"><?php _e('Avançado', 'barramento-tainacan'); ?></a>
        </div>
    </div>
    
    <form method="post" action="options.php">
        <div id="tab-general" class="barramento-tab-content active">
            <?php settings_fields('barramento_general_settings'); ?>
            
            <div class="barramento-form-section">
                <h2><?php _e('Configurações do Archivematica Dashboard', 'barramento-tainacan'); ?></h2>
                <p><?php _e('Configure as credenciais de acesso ao Archivematica Dashboard.', 'barramento-tainacan'); ?></p>
                
                <div class="barramento-form-row">
                    <label for="barramento_archivematica_url"><?php _e('URL do Archivematica Dashboard', 'barramento-tainacan'); ?></label>
                    <input type="text" id="barramento_archivematica_url" name="barramento_archivematica_url" value="<?php echo esc_attr($archivematica_url); ?>" placeholder="https://archivematica.example.com" />
                    <p class="barramento-form-description"><?php _e('URL completa do Archivematica Dashboard. Exemplo: https://archivematica.example.com', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_archivematica_user"><?php _e('Usuário', 'barramento-tainacan'); ?></label>
                    <input type="text" id="barramento_archivematica_user" name="barramento_archivematica_user" value="<?php echo esc_attr($archivematica_user); ?>" />
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_archivematica_api_key"><?php _e('Chave de API', 'barramento-tainacan'); ?></label>
                    <input type="password" id="barramento_archivematica_api_key" name="barramento_archivematica_api_key" value="<?php echo esc_attr($archivematica_api_key); ?>" />
                    <p class="barramento-form-description"><?php _e('Chave de API gerada na interface do Archivematica Dashboard.', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <button type="button" id="test_dashboard_connection" class="button"><?php _e('Testar Conexão', 'barramento-tainacan'); ?></button>
                    <span id="dashboard-connection-result"></span>
                    <span class="spinner" id="dashboard-spinner"></span>
                </div>
            </div>
            
            <div class="barramento-form-section">
                <h2><?php _e('Configurações do Archivematica Storage Service', 'barramento-tainacan'); ?></h2>
                <p><?php _e('Configure as credenciais de acesso ao Archivematica Storage Service.', 'barramento-tainacan'); ?></p>
                
                <div class="barramento-form-row">
                    <label for="barramento_storage_service_url"><?php _e('URL do Storage Service', 'barramento-tainacan'); ?></label>
                    <input type="text" id="barramento_storage_service_url" name="barramento_storage_service_url" value="<?php echo esc_attr($storage_service_url); ?>" placeholder="https://storage.archivematica.example.com" />
                    <p class="barramento-form-description"><?php _e('URL completa do Archivematica Storage Service. Exemplo: https://storage.archivematica.example.com', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_storage_service_user"><?php _e('Usuário', 'barramento-tainacan'); ?></label>
                    <input type="text" id="barramento_storage_service_user" name="barramento_storage_service_user" value="<?php echo esc_attr($storage_service_user); ?>" />
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_storage_service_api_key"><?php _e('Chave de API', 'barramento-tainacan'); ?></label>
                    <input type="password" id="barramento_storage_service_api_key" name="barramento_storage_service_api_key" value="<?php echo esc_attr($storage_service_api_key); ?>" />
                    <p class="barramento-form-description"><?php _e('Chave de API gerada na interface do Archivematica Storage Service.', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <button type="button" id="test_storage_connection" class="button"><?php _e('Testar Conexão', 'barramento-tainacan'); ?></button>
                    <span id="storage-connection-result"></span>
                    <span class="spinner" id="storage-spinner"></span>
                </div>
            </div>
        </div>
        
        <div id="tab-preservation" class="barramento-tab-content">
            <?php settings_fields('barramento_preservation_settings'); ?>
            
            <div class="barramento-form-section">
                <h2><?php _e('Configurações de Preservação', 'barramento-tainacan'); ?></h2>
                
                <div class="barramento-form-row">
                    <label for="barramento_max_sip_size"><?php _e('Tamanho Máximo do SIP (MB)', 'barramento-tainacan'); ?></label>
                    <input type="number" id="barramento_max_sip_size" name="barramento_max_sip_size" value="<?php echo esc_attr($max_sip_size); ?>" min="1" max="10240" />
                    <p class="barramento-form-description"><?php _e('Tamanho máximo do pacote SIP em megabytes. Valor recomendado: 1024 (1GB).', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_schedule_frequency"><?php _e('Frequência de Verificação Automática', 'barramento-tainacan'); ?></label>
                    <select id="barramento_schedule_frequency" name="barramento_schedule_frequency">
                        <?php foreach ($schedule_frequencies as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule_frequency, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="barramento-form-description"><?php _e('Com que frequência o sistema deve verificar automaticamente o status das transferências e ingestões.', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_hash_algorithm"><?php _e('Algoritmo de Hash', 'barramento-tainacan'); ?></label>
                    <select id="barramento_hash_algorithm" name="barramento_hash_algorithm">
                        <?php foreach ($hash_algorithms as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($hash_algorithm, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="barramento-form-description"><?php _e('Algoritmo utilizado para gerar hashes de verificação de integridade.', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <label for="barramento_retry_attempts"><?php _e('Tentativas de Reprocessamento', 'barramento-tainacan'); ?></label>
                    <input type="number" id="barramento_retry_attempts" name="barramento_retry_attempts" value="<?php echo esc_attr($retry_attempts); ?>" min="0" max="10" />
                    <p class="barramento-form-description"><?php _e('Número de tentativas de reprocessamento em caso de falha. Zero para não tentar novamente.', 'barramento-tainacan'); ?></p>
                </div>
            </div>
        </div>
        
        <div id="tab-advanced" class="barramento-tab-content">
            <?php settings_fields('barramento_advanced_settings'); ?>
            
            <div class="barramento-form-section">
                <h2><?php _e('Configurações Avançadas', 'barramento-tainacan'); ?></h2>
                
                <div class="barramento-form-row">
                    <label for="barramento_debug_mode">
                        <input type="checkbox" id="barramento_debug_mode" name="barramento_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
                        <?php _e('Ativar Modo de Depuração', 'barramento-tainacan'); ?>
                    </label>
                    <p class="barramento-form-description"><?php _e('Ativa o registro detalhado de todas as operações no log. Useful para diagnosticar problemas.', 'barramento-tainacan'); ?></p>
                </div>
                
                <div class="barramento-form-row">
                    <button type="button" id="cleanup_logs_button" class="button"><?php _e('Limpar Logs Antigos', 'barramento-tainacan'); ?></button>
                    <span id="cleanup-logs-result"></span>
                    <span class="spinner" id="cleanup-logs-spinner"></span>
                    <p class="barramento-form-description"><?php _e('Remove logs com mais de 90 dias, exceto logs críticos.', 'barramento-tainacan'); ?></p>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Salvar Configurações', 'barramento-tainacan')); ?>
    </form>
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
    
    // Teste de conexão com Archivematica Dashboard
    $('#test_dashboard_connection').on('click', function() {
        var spinner = $('#dashboard-spinner');
        var resultContainer = $('#dashboard-connection-result');
        
        spinner.addClass('is-active');
        resultContainer.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_test_connection',
                nonce: barramento_admin.nonce,
                url: $('#barramento_archivematica_url').val(),
                user: $('#barramento_archivematica_user').val(),
                api_key: $('#barramento_archivematica_api_key').val(),
                service_type: 'dashboard'
            },
            success: function(response) {
                if (response.success) {
                    resultContainer.html('<span class="dashicons dashicons-yes" style="color: green;"></span> ' + response.data.message);
                } else {
                    resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + response.data.message);
                }
            },
            error: function() {
                resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + barramento_admin.i18n.connection_error);
            },
            complete: function() {
                spinner.removeClass('is-active');
            }
        });
    });
    
    // Teste de conexão com Archivematica Storage Service
    $('#test_storage_connection').on('click', function() {
        var spinner = $('#storage-spinner');
        var resultContainer = $('#storage-connection-result');
        
        spinner.addClass('is-active');
        resultContainer.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_test_connection',
                nonce: barramento_admin.nonce,
                url: $('#barramento_storage_service_url').val(),
                user: $('#barramento_storage_service_user').val(),
                api_key: $('#barramento_storage_service_api_key').val(),
                service_type: 'storage'
            },
            success: function(response) {
                if (response.success) {
                    resultContainer.html('<span class="dashicons dashicons-yes" style="color: green;"></span> ' + response.data.message);
                } else {
                    resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + response.data.message);
                }
            },
            error: function() {
                resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + barramento_admin.i18n.connection_error);
            },
            complete: function() {
                spinner.removeClass('is-active');
            }
        });
    });
    
    // Limpeza de logs antigos
    $('#cleanup_logs_button').on('click', function() {
        var spinner = $('#cleanup-logs-spinner');
        var resultContainer = $('#cleanup-logs-result');
        
        spinner.addClass('is-active');
        resultContainer.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'barramento_cleanup_logs',
                nonce: barramento_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultContainer.html('<span class="dashicons dashicons-yes" style="color: green;"></span> ' + response.data.message);
                } else {
                    resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + response.data.message);
                }
            },
            error: function() {
                resultContainer.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + barramento_admin.i18n.error);
            },
            complete: function() {
                spinner.removeClass('is-active');
            }
        });
    });
});
</script>
