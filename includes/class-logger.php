<?php
/**
 * Classe para gerenciamento de logs
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Responsável pelo registro de logs do plugin.
 */
class Barramento_Tainacan_Logger {

    /**
     * Nome da tabela de logs
     *
     * @var string
     */
    private $table_name;

    /**
     * Inicializa a classe
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'barramento_logs';
    }

    /**
     * Registra uma entrada no log
     *
     * @param string $level Nível do log (debug, info, warning, error, critical)
     * @param string $message Mensagem do log
     * @param array $context Dados adicionais de contexto
     * @return int|false ID do log inserido ou false em caso de falha
     */
    public function log($level, $message, $context = array()) {
        global $wpdb;
        
        // Valida o nível do log
        $allowed_levels = array('debug', 'info', 'warning', 'error', 'critical');
        if (!in_array($level, $allowed_levels)) {
            $level = 'info';
        }
        
        // Filtro para evitar logs de depuração em produção
        if ($level === 'debug' && !get_option('barramento_debug_mode', false)) {
            return false;
        }
        
        // Prepara os dados de contexto específicos
        $item_id = isset($context['item_id']) ? intval($context['item_id']) : null;
        $collection_id = isset($context['collection_id']) ? intval($context['collection_id']) : null;
        $batch_id = isset($context['batch_id']) ? sanitize_text_field($context['batch_id']) : null;
        $aip_id = isset($context['aip_id']) ? sanitize_text_field($context['aip_id']) : null;
        
        // Serializa o resto do contexto
        $context_serialized = !empty($context) ? wp_json_encode($context) : null;
        
        // Insere o log no banco de dados
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'level' => $level,
                'message' => $message,
                'context' => $context_serialized,
                'created_at' => current_time('mysql'),
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'batch_id' => $batch_id,
                'aip_id' => $aip_id
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s'
            )
        );
        
        if ($result === false) {
            // Em caso de falha na inserção, tenta fazer log diretamente no error_log
            if (function_exists('error_log')) {
                error_log('Barramento Tainacan - ' . $level . ': ' . $message);
                if (!empty($context)) {
                    error_log('Barramento Tainacan - Contexto: ' . print_r($context, true));
                }
            }
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Obtém logs do sistema com filtros
     *
     * @param array $args Argumentos de filtro
     * @return array Logs e informações de paginação
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        // Parâmetros padrão
        $defaults = array(
            'level' => '',          // Filtro por nível
            'search' => '',         // Busca por texto
            'item_id' => '',        // Filtro por ID do item
            'collection_id' => '',  // Filtro por ID da coleção
            'batch_id' => '',       // Filtro por ID do lote
            'aip_id' => '',         // Filtro por ID do AIP
            'date_from' => '',      // Filtro por data inicial
            'date_to' => '',        // Filtro por data final
            'orderby' => 'created_at', // Ordenação
            'order' => 'DESC',      // Direção da ordenação
            'page' => 1,            // Página atual
            'per_page' => 20        // Itens por página
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Prepara a condição WHERE
        $where = '1=1';
        $values = array();
        
        // Filtro por nível
        if (!empty($args['level'])) {
            $where .= ' AND level = %s';
            $values[] = $args['level'];
        }
        
        // Filtro por ID do item
        if (!empty($args['item_id'])) {
            $where .= ' AND item_id = %d';
            $values[] = $args['item_id'];
        }
        
        // Filtro por ID da coleção
        if (!empty($args['collection_id'])) {
            $where .= ' AND collection_id = %d';
            $values[] = $args['collection_id'];
        }
        
        // Filtro por ID do lote
        if (!empty($args['batch_id'])) {
            $where .= ' AND batch_id = %s';
            $values[] = $args['batch_id'];
        }
        
        // Filtro por ID do AIP
        if (!empty($args['aip_id'])) {
            $where .= ' AND aip_id = %s';
            $values[] = $args['aip_id'];
        }
        
        // Filtro por busca de texto
        if (!empty($args['search'])) {
            $where .= ' AND message LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        // Filtro por data inicial
        if (!empty($args['date_from'])) {
            $where .= ' AND created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        
        // Filtro por data final
        if (!empty($args['date_to'])) {
            $where .= ' AND created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }
        
        // Prepara a consulta de contagem
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where";
        
        // Executa a consulta de contagem com valores preparados
        $count_query = $values ? $wpdb->prepare($count_query, $values) : $count_query;
        $total = $wpdb->get_var($count_query);
        
        // Calcula o total de páginas
        $total_pages = ceil($total / $args['per_page']);
        
        // Garante que a página atual está dentro dos limites
        $page = max(1, min($args['page'], $total_pages));
        
        // Calcula o offset para a consulta
        $offset = ($page - 1) * $args['per_page'];
        
        // Valida os parâmetros de ordenação
        $allowed_orderby = array('id', 'level', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Prepara a consulta principal
        $query = "SELECT * FROM {$this->table_name} WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        
        // Adiciona os novos valores para os parâmetros da consulta principal
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        // Executa a consulta principal com valores preparados
        $query = $wpdb->prepare($query, $values);
        $logs = $wpdb->get_results($query, ARRAY_A);
        
        // Processa os logs para adicionar o contexto desserializado
        foreach ($logs as &$log) {
            if (!empty($log['context'])) {
                $context = json_decode($log['context'], true);
                $log['context'] = $context;
            } else {
                $log['context'] = array();
            }
        }
        
        return array(
            'logs' => $logs,
            'total' => $total,
            'pages' => $total_pages,
            'page' => $page,
            'per_page' => $args['per_page']
        );
    }

    /**
     * Limpa logs antigos do sistema
     *
     * @param int $days Número de dias para manter (logs mais antigos serão removidos)
     * @param bool $keep_critical Se deve manter logs críticos mesmo antigos
     * @return int Número de logs removidos
     */
    public function cleanup_logs($days = 90, $keep_critical = true) {
        global $wpdb;
        
        $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $where = "created_at < %s";
        $values = array($date_limit);
        
        if ($keep_critical) {
            $where .= " AND level != 'critical'";
        }
        
        $query = "DELETE FROM {$this->table_name} WHERE $where";
        $prepared_query = $wpdb->prepare($query, $values);
        
        $result = $wpdb->query($prepared_query);
        
        // Registra a limpeza
        $this->log('info', "Limpeza de logs concluída: $result logs removidos", array(
            'days_kept' => $days,
            'keep_critical' => $keep_critical,
            'date_limit' => $date_limit
        ));
        
        return $result;
    }
}
