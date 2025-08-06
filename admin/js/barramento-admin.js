/**
 * Script para o painel administrativo do Barramento Tainacan
 */

(function($) {
    'use strict';

    /**
     * Inicializa o script quando o documento estiver pronto
     */
    $(document).ready(function() {
        // Inicializa os componentes da UI
        initTabs();
        initSelectors();
        initButtons();
        initTooltips();
        initFilters();
    });

    /**
     * Inicializa a navegação por abas
     */
    function initTabs() {
        $('.barramento-tab a').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this).attr('href');
            
            // Ativa a aba
            $('.barramento-tab').removeClass('active');
            $(this).parent('.barramento-tab').addClass('active');
            
            // Mostra o conteúdo da aba
            $('.barramento-tab-content').removeClass('active');
            $(target).addClass('active');
            
            // Salva a aba ativa no localStorage
            localStorage.setItem('barramento_active_tab', target);
        });
        
        // Restaura a aba ativa ao carregar a página
        const activeTab = localStorage.getItem('barramento_active_tab');
        if (activeTab && $(activeTab).length) {
            $('.barramento-tab a[href="' + activeTab + '"]').trigger('click');
        }
    }

    /**
     * Inicializa os seletores de checkbox
     */
    function initSelectors() {
        // Seleção de todas as coleções
        $('#select-all-collections').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('input[name="barramento_enabled_collections[]"]').prop('checked', isChecked);
        });
        
        // Seleção de todos os metadados obrigatórios
        $('#select-all-required').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('input[name="barramento_required_metadata[]"]').prop('checked', isChecked);
        });
        
        // Adicionar metadado fixo
        $('#add-fixed-metadata').on('click', function() {
            const template = $('.fixed-metadata-row').first().clone();
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
                const row = $(this).closest('.fixed-metadata-row');
                row.find('input').val('');
                row.find('select').val(row.find('select option:first').val());
            }
        });
    }

    /**
     * Inicializa os botões AJAX
     */
    function initButtons() {
        // Adicionar coleção à fila
        $('.queue-collection').on('click', function() {
            const button = $(this);
            const spinner = button.next('.collection-spinner');
            const collectionId = button.data('collection-id');
            
            button.attr('disabled', true);
            spinner.addClass('is-active');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'barramento_index_collection',
                    nonce: barramento_admin.nonce,
                    collection_id: collectionId,
                    force_update: false
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', response.data.message);
                    } else {
                        showNotification('error', response.data.message || barramento_admin.i18n.error);
                    }
                },
                error: function() {
                    showNotification('error', barramento_admin.i18n.error);
                },
                complete: function() {
                    button.attr('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });
        
        // Processar fila
        $('#process-queue-button').on('click', function() {
            const button = $(this);
            const spinner = $('#queue-processing-spinner');
            
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
                        showNotification('success', response.data.message);
                    } else {
                        showNotification('error', response.data.message || barramento_admin.i18n.error);
                    }
                },
                error: function() {
                    showNotification('error', barramento_admin.i18n.error);
                },
                complete: function() {
                    button.attr('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            });
        });
        
        // Verificar transferências
        $('#check-transfers-button').on('click', function() {
            const button = $(this);
            const spinner = $('#transfers-checking-spinner');
            
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
                        showNotification('success', response.data.message);
                    } else {
                        showNotification('error', response.data.message || barramento_admin.i18n.error);
                    }
                },
                error: function() {
                    showNotification('error', barramento_admin.i18n.error);
                },
                complete: function() {
                    button.attr('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            });
        });
        
        // Verificar ingestões
        $('#check-ingests-button').on('click', function() {
            const button = $(this);
            const spinner = $('#ingests-checking-spinner');
            
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
                        showNotification('success', response.data.message);
                    } else {
                        showNotification('error', response.data.message || barramento_admin.i18n.error);
                    }
                },
                error: function() {
                    showNotification('error', barramento_admin.i18n.error);
                },
                complete: function() {
                    button.attr('disabled', false);
                    spinner.removeClass('is-active');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            });
        });
        
        // Teste de conexão com Archivematica Dashboard
        $('#test_dashboard_connection').on('click', function() {
            const spinner = $('#dashboard-spinner');
            const resultContainer = $('#dashboard-connection-result');
            
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
            const spinner = $('#storage-spinner');
            const resultContainer = $('#storage-connection-result');
            
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
            const spinner = $('#cleanup-logs-spinner');
            const resultContainer = $('#cleanup-logs-result');
            
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
        
        // Processa o formulário de metadados
        $('#metadata-form').on('submit', function() {
            // Prepara os metadados fixos
            const fixedMetadata = {};
            $('.fixed-metadata-row').each(function() {
                const element = $(this).find('.fixed-metadata-element').val();
                const value = $(this).find('.fixed-metadata-value').val();
                
                if (element && value) {
                    fixedMetadata[element] = value;
                }
            });
            
            // Adiciona campo oculto com os metadados fixos formatados
            if (Object.keys(fixedMetadata).length > 0) {
                $(this).append('<input type="hidden" name="barramento_fixed_metadata" value="' + JSON.stringify(fixedMetadata) + '">');
            }
        });
        
        // Botão de impressão para relatórios
        $('#print-report').on('click', function(e) {
            e.preventDefault();
            window.print();
        });
    }

    /**
     * Inicializa tooltips
     */
    function initTooltips() {
        // Toggle para exibir/ocultar contexto nos logs
        $('.toggle-context').on('click', function() {
            const logId = $(this).data('log-id');
            const contextDiv = $('#context-' + logId);
            const icon = $(this).find('.dashicons');
            
            if (contextDiv.is(':visible')) {
                contextDiv.hide();
                icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            } else {
                contextDiv.show();
                icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        });
    }

    /**
     * Inicializa filtros
     */
    function initFilters() {
        // Filtro dinâmico para tabelas
        $('#filter-table-input').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            
            $('.filterable-table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    }

    /**
     * Exibe uma notificação
     * 
     * @param {string} type Tipo de notificação (success, error, warning)
     * @param {string} message Mensagem a ser exibida
     */
    function showNotification(type, message) {
        const notificationArea = $('#barramento-notifications');
        
        // Cria a área de notificações se não existir
        if (notificationArea.length === 0) {
            $('body').append('<div id="barramento-notifications"></div>');
        }
        
        // Determina a classe CSS com base no tipo
        let cssClass = 'barramento-notice ';
        switch (type) {
            case 'success':
                cssClass += 'barramento-success';
                break;
            case 'error':
                cssClass += 'barramento-error';
                break;
            case 'warning':
                cssClass += 'barramento-warning';
                break;
            default:
                cssClass += 'barramento-info';
        }
        
        // Adiciona a mensagem de notificação
        const notification = $('<div class="' + cssClass + '"><p>' + message + '</p><button type="button" class="notice-dismiss"></button></div>');
        $('#barramento-notifications').append(notification);
        
        // Configura o botão para fechar a notificação
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Remove automaticamente após 5 segundos
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery);
