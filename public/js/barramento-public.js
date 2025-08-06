/**
 * Script para a parte pública do Barramento Tainacan
 */
(function($) {
    'use strict';

    /**
     * Inicializa quando o documento estiver pronto
     */
    $(document).ready(function() {
        initPreservationStatus();
        initTooltips();
        initCollapsibleSections();
    });

    /**
     * Inicializa o monitoramento do status de preservação
     */
    function initPreservationStatus() {
        // Verifica se existem elementos de status de preservação na página
        if ($('.barramento-preservation-status').length === 0) {
            return;
        }

        // Busca os elementos com status em processamento
        const processingElements = $('.barramento-status-badge.status-transfer_started, .barramento-status-badge.status-ingest_started').closest('.barramento-preservation-status');
        
        if (processingElements.length > 0) {
            // Inicializa o efeito de progresso
            processingElements.each(function() {
                const progressBar = $(this).find('.barramento-progress-value');
                animateProgressBar(progressBar);
            });
            
            // Configura a atualização automática se houver itens em processamento
            setTimeout(checkPreservationStatus, 30000); // Verifica a cada 30 segundos
        }
    }

    /**
     * Verifica o status atual de preservação via AJAX
     */
    function checkPreservationStatus() {
        // Obtém os IDs dos itens em processamento
        const itemIDs = [];
        $('.barramento-status-badge.status-transfer_started, .barramento-status-badge.status-ingest_started').each(function() {
            const container = $(this).closest('[data-item-id]');
            if (container.length) {
                const itemID = container.data('item-id');
                if (itemID) {
                    itemIDs.push(itemID);
                }
            }
        });
        
        if (itemIDs.length === 0) {
            return;
        }
        
        // Requisição AJAX para verificar o status atual
        $.ajax({
            url: barramento_public.ajax_url,
            type: 'POST',
            data: {
                action: 'barramento_check_status',
                nonce: barramento_public.nonce,
                item_ids: itemIDs
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Atualiza o status dos itens
                    $.each(response.data, function(itemID, statusData) {
                        const container = $(`[data-item-id="${itemID}"]`);
                        if (container.length && statusData.status) {
                            updatePreservationStatus(container, statusData);
                        }
                    });
                }
                
                // Agenda a próxima verificação se ainda houver itens em processamento
                if ($('.barramento-status-badge.status-transfer_started, .barramento-status-badge.status-ingest_started').length > 0) {
                    setTimeout(checkPreservationStatus, 30000);
                }
            }
        });
    }

    /**
     * Atualiza o status de preservação de um item
     * 
     * @param {jQuery} container Elemento contendo o status
     * @param {Object} statusData Dados do status atualizado
     */
    function updatePreservationStatus(container, statusData) {
        const statusBadge = container.find('.barramento-status-badge');
        
        // Atualiza a classe e o texto do badge
        if (statusBadge.length) {
            statusBadge.removeClass (function (index, className) {
                return (className.match (/(^|\s)status-\S+/g) || []).join(' ');
            });
            statusBadge.addClass('status-' + statusData.status);
            statusBadge.text(statusData.label);
        }
        
        // Atualiza ou remove elementos de progresso
        if (statusData.status !== 'transfer_started' && statusData.status !== 'ingest_started') {
            container.find('.barramento-progress-details').fadeOut(300, function() {
                $(this).remove();
            });
        }
        
        // Adiciona detalhes de AIP quando disponíveis
        if (statusData.aip_id && !container.find('.barramento-aip-info').length) {
            const detailsHtml = `
                <div class="barramento-preservation-details">
                    <p class="barramento-aip-info">
                        <strong>${barramento_public.i18n.aip_id}</strong> 
                        ${statusData.aip_url ? 
                            `<a href="${statusData.aip_url}" target="_blank" class="barramento-external-link">
                                ${statusData.aip_id}
                                <span class="dashicons dashicons-external"></span>
                            </a>` : 
                            statusData.aip_id
                        }
                    </p>
                    ${statusData.aip_date ? 
                        `<p class="barramento-preservation-date">
                            <strong>${barramento_public.i18n.preserved_on}</strong> 
                            ${statusData.aip_date}
                        </p>` : 
                        ''
                    }
                </div>
            `;
            
            container.append(detailsHtml);
        }
        
        // Adiciona detalhes de erro quando aplicável
        if ((statusData.status === 'transfer_failed' || statusData.status === 'ingest_failed') && 
            statusData.error_message && !container.find('.barramento-error-details').length) {
            
            const errorHtml = `
                <div class="barramento-error-details">
                    <p class="barramento-error-message">
                        <strong>${barramento_public.i18n.reason}</strong>
                        ${statusData.error_message}
                    </p>
                    <p class="barramento-retry-note">
                        ${barramento_public.i18n.retry_note}
                    </p>
                </div>
            `;
            
            container.append(errorHtml);
        }
    }

    /**
     * Anima a barra de progresso
     * 
     * @param {jQuery} progressBar Elemento da barra de progresso
     */
    function animateProgressBar(progressBar) {
        let width = 0;
        
        // Ciclo de animação para indicar progresso
        const interval = setInterval(function() {
            if (width >= 90) {
                width = 10;
            } else {
                width += 2;
            }
            progressBar.css('width', width + '%');
        }, 1000);
        
        // Armazena o interval na barra para poder cancelar depois
        progressBar.data('animation-interval', interval);
    }

    /**
     * Inicializa tooltips
     */
    function initTooltips() {
        $('.barramento-tooltip').hover(
            function() {
                const tooltip = $(this);
                const text = tooltip.data('tooltip');
                
                if (!text) return;
                
                $('<div class="barramento-tooltip-popup">' + text + '</div>')
                    .appendTo('body')
                    .css({
                        top: tooltip.offset().top - 30,
                        left: tooltip.offset().left + (tooltip.outerWidth() / 2) - 100
                    })
                    .fadeIn('fast');
            },
            function() {
                $('.barramento-tooltip-popup').remove();
            }
        );
    }

    /**
     * Inicializa seções colapsáveis
     */
    function initCollapsibleSections() {
        $('.barramento-collapsible-header').on('click', function() {
            const content = $(this).next('.barramento-collapsible-content');
            content.slideToggle(300);
            $(this).toggleClass('collapsed');
            
            const icon = $(this).find('.barramento-toggle-icon');
            if (icon.length) {
                icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            }
        });
        
        // Inicia com todas as seções fechadas exceto a primeira
        $('.barramento-collapsible-content').not(':first').hide();
        $('.barramento-collapsible-header').not(':first').addClass('collapsed');
        $('.barramento-collapsible-header').not(':first').find('.barramento-toggle-icon')
            .removeClass('dashicons-arrow-up-alt2')
            .addClass('dashicons-arrow-down-alt2');
    }

})(jQuery);
