<?php
/**
 * Classe para processamento de itens do Tainacan
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Responsável pelo processamento dos itens do Tainacan para preservação no Archivematica.
 */
class Barramento_Tainacan_Processor {

    /**
     * Instância da classe de API do Tainacan
     *
     * @var Barramento_Tainacan_API
     */
    private $tainacan_api;

    /**
     * Instância da classe de API do Archivematica
     *
     * @var Barramento_Tainacan_Archivematica_API
     */
    private $archivematica_api;

    /**
     * Instância da classe de geração de SIP
     *
     * @var Barramento_Tainacan_SIP_Generator
     */
    private $sip_generator;

    /**
     * Instância da classe de validação
     *
     * @var Barramento_Tainacan_Validator
     */
    private $validator;

    /**
     * Instância da classe de logger
     *
     * @var Barramento_Tainacan_Logger
     */
    private $logger;
	
	/**
     * Inicializa a classe
     */
    public function __construct() {
        // Inicializa as dependências
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-tainacan-api.php';
        $this->tainacan_api = new Barramento_Tainacan_API();
        
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-archivematica-api.php';
        $this->archivematica_api = new Barramento_Tainacan_Archivematica_API();
        
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php';
        $this->logger = new Barramento_Tainacan_Logger();
        
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-validator.php';
        $this->validator = new Barramento_Tainacan_Validator($this->logger);
        
        require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-sip-generator.php';
        $this->sip_generator = new Barramento_Tainacan_SIP_Generator($this->logger);
    }
	
	/**
     * Adiciona os itens de uma coleção à fila de processamento
     *
     * @param int $collection_id ID da coleção
     * @param bool $force_update Forçar reprocessamento de itens já processados
     * @return array|WP_Error Resultado da operação
     */
    public function queue_collection_items($collection_id, $force_update = false) {
        // Verifica se a coleção está habilitada para preservação
        if (!$this->validator->is_collection_enabled($collection_id)) {
            return new WP_Error(
                'collection_not_enabled',
                __('Esta coleção não está habilitada para preservação', 'barramento-tainacan')
            );
        }
        
        // Obtém os itens da coleção
        $result = $this->tainacan_api->get_collection_items($collection_id, array(
            'posts_per_page' => -1 // Todos os itens
        ));
        
        if (is_wp_error($result)) {
            $this->logger->log('error', 'Erro ao obter itens da coleção', array(
                'collection_id' => $collection_id,
                'error' => $result->get_error_message()
            ));
            return $result;
        }
		
		$total_items = count($result['items']);
        $added_to_queue = 0;
        $skipped = 0;
        $batch_id = uniqid('batch-');
        
        // Adiciona cada item à fila
        foreach ($result['items'] as $item) {
            $queue_result = $this->add_item_to_queue($item['id'], $collection_id, $batch_id, $force_update);
            
            if ($queue_result) {
                $added_to_queue++;
            } else {
                $skipped++;
            }
        }
        
        // Registra no log
        $this->logger->log('info', 'Itens da coleção adicionados à fila', array(
            'collection_id' => $collection_id,
            'total_items' => $total_items,
            'added_to_queue' => $added_to_queue,
            'skipped' => $skipped,
            'batch_id' => $batch_id,
            'force_update' => $force_update
        ));
        
        return array(
            'collection_id' => $collection_id,
            'total_items' => $total_items,
            'added_to_queue' => $added_to_queue,
            'skipped' => $skipped,
            'batch_id' => $batch_id
        );
    }
	
	/**
     * Adiciona um item à fila de processamento
     *
     * @param int $item_id ID do item
     * @param int $collection_id ID da coleção
     * @param string $batch_id ID do lote
     * @param bool $force_update Forçar reprocessamento
     * @return bool Sucesso da operação
     */
    public function add_item_to_queue($item_id, $collection_id, $batch_id = '', $force_update = false) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'barramento_queue';
        
        // Verifica se o item já está na fila
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM $queue_table WHERE item_id = %d AND collection_id = %d AND status IN ('pending', 'processing')",
                $item_id,
                $collection_id
            )
        );
        
        if ($existing && !$force_update) {
            return false; // Item já está na fila
        }
		
		// Verifica se o item já foi processado e não está sendo forçado reprocessamento
        if (!$force_update) {
            $objects_table = $wpdb->prefix . 'barramento_objects';
            $preserved = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $objects_table WHERE item_id = %d AND preservation_status IN ('aip_stored', 'fully_preserved')",
                    $item_id
                )
            );
            
            if ((int) $preserved > 0) {
                return false; // Item já foi preservado
            }
        }
        
        // Gera um ID de lote se não for fornecido
        if (empty($batch_id)) {
            $batch_id = uniqid('batch-');
        }
        
        // Adiciona ou atualiza na fila
        if ($existing) {
            $result = $wpdb->update(
                $queue_table,
                array(
                    'status' => 'pending',
                    'retries' => 0,
                    'batch_id' => $batch_id,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            $result = $wpdb->insert(
                $queue_table,
                array(
                    'collection_id' => $collection_id,
                    'item_id' => $item_id,
                    'status' => 'pending',
                    'priority' => 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'batch_id' => $batch_id,
                    'retries' => 0
                ),
                array('%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d')
            );
        }
		
		if ($result === false) {
            $this->logger->log('error', 'Erro ao adicionar item à fila', array(
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'batch_id' => $batch_id
            ));
            return false;
        }
        
        return true;
    }

    /**
     * Processa os próximos itens da fila
     *
     * @param int $limit Limite de itens a processar
     * @return array Estatísticas de processamento
     */
    public function process_queue($limit = 10) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'barramento_queue';
        
        // Obtém os próximos itens da fila
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $queue_table WHERE status = 'pending' ORDER BY priority DESC, created_at ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        $stats = array(
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        if (empty($items)) {
            return $stats;
        }
		
		foreach ($items as $queue_item) {
            // Marca o item como em processamento
            $wpdb->update(
                $queue_table,
                array(
                    'status' => 'processing',
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $queue_item['id']),
                array('%s', '%s'),
                array('%d')
            );
            
            // Processa o item
            $result = $this->process_item($queue_item['item_id'], $queue_item['collection_id'], $queue_item['batch_id']);
            
            $stats['processed']++;
            
            if (is_wp_error($result)) {
                $stats['failed']++;
                
                // Incrementa as tentativas ou marca como falha permanente
                $retries = $queue_item['retries'] + 1;
                $max_retries = get_option('barramento_retry_attempts', 3);
                
                if ($retries >= $max_retries) {
                    $status = 'failed';
                } else {
                    $status = 'pending';
                }
                
                $wpdb->update(
                    $queue_table,
                    array(
                        'status' => $status,
                        'retries' => $retries,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $queue_item['id']),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
				
				$stats['details'][] = array(
                    'item_id' => $queue_item['item_id'],
                    'collection_id' => $queue_item['collection_id'],
                    'status' => 'failed',
                    'message' => $result->get_error_message()
                );
            } else {
                $stats['successful']++;
                
                // Remove da fila
                $wpdb->delete(
                    $queue_table,
                    array('id' => $queue_item['id']),
                    array('%d')
                );
                
                $stats['details'][] = array(
                    'item_id' => $queue_item['item_id'],
                    'collection_id' => $queue_item['collection_id'],
                    'status' => 'success',
                    'sip_id' => $result['sip_id'] ?? null,
                    'aip_id' => $result['aip_id'] ?? null
                );
            }
        }
        
        return $stats;
    }
	
	/**
     * Processa um item específico
     *
     * @param int $item_id ID do item
     * @param int $collection_id ID da coleção
     * @param string $batch_id ID do lote (opcional)
     * @return array|WP_Error Resultado do processamento
     */
    public function process_item($item_id, $collection_id, $batch_id = '') {
        // Obtém o item do Tainacan
        $item = $this->tainacan_api->get_item($item_id);
        
        if (is_wp_error($item)) {
            $this->logger->log('error', 'Erro ao obter item do Tainacan', array(
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'error' => $item->get_error_message(),
                'batch_id' => $batch_id
            ));
            return $item;
        }
        
        // Obtém configurações de metadados
        $required_metadata = get_option('barramento_required_metadata', array());
        $metadata_mapping = get_option('barramento_metadata_mapping', array());
        $fixed_metadata = get_option('barramento_fixed_metadata', array());
        
        // Valida o item
        $validation = $this->validator->validate_item($item, $required_metadata, $metadata_mapping);
		
		if (is_wp_error($validation)) {
            $this->logger->log('warning', 'Item falhou na validação', array(
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'error' => $validation->get_error_message(),
                'batch_id' => $batch_id
            ));
            return $validation;
        }
        
        // Gera o SIP
        $sip_result = $this->sip_generator->generate_sip($item, $metadata_mapping, $fixed_metadata);
        
        if (is_wp_error($sip_result)) {
            $this->logger->log('error', 'Erro ao gerar SIP', array(
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'error' => $sip_result->get_error_message(),
                'batch_id' => $batch_id
            ));
            return $sip_result;
        }
        
        // Inicia o processo de transferência para o Archivematica
        $transfer_result = $this->start_archivematica_transfer($sip_result['path'], $sip_result['sip_id']);
        
        if (is_wp_error($transfer_result)) {
            $this->logger->log('error', 'Erro ao iniciar transferência para o Archivematica', array(
                'item_id' => $item_id,
                'collection_id' => $collection_id,
                'sip_id' => $sip_result['sip_id'],
                'error' => $transfer_result->get_error_message(),
                'batch_id' => $batch_id
            ));
            return $transfer_result;
        }
		
		// Atualiza o status do objeto de preservação
        $this->update_preservation_status(
            $item_id,
            'transfer_started',
            array(
                'transfer_status' => 'started',
                'sip_id' => $sip_result['sip_id'],
                'batch_id' => $batch_id,
                'transfer_uuid' => $transfer_result['uuid'],
                'collection_id' => $collection_id
            )
        );
        
        // Retorna as informações do processamento
        return array(
            'item_id' => $item_id,
            'collection_id' => $collection_id,
            'sip_id' => $sip_result['sip_id'],
            'transfer_uuid' => $transfer_result['uuid'],
            'batch_id' => $batch_id,
            'status' => 'transfer_started'
        );
    }

    /**
     * Inicia uma transferência para o Archivematica
     *
     * @param string $sip_path Caminho do diretório do SIP
     * @param string $sip_id ID do SIP
     * @return array|WP_Error Resultado da transferência
     */
    private function start_archivematica_transfer($sip_path, $sip_id) {
        // Verifica se o SIP é válido
        $validation = $this->validator->validate_sip_directory($sip_path);
        if (is_wp_error($validation)) {
            return $validation;
        }
		
		// Verifica o tamanho do SIP
        $size_validation = $this->validator->validate_sip_size($sip_path);
        if (is_wp_error($size_validation)) {
            return $size_validation;
        }
        
        // Inicia a transferência no Archivematica
        $result = $this->archivematica_api->start_transfer($sip_path, 'standard', $sip_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result;
    }

    /**
     * Atualiza o status de preservação de um item
     *
     * @param int $item_id ID do item
     * @param string $status Novo status
     * @param array $additional_data Dados adicionais
     * @return bool Sucesso da operação
     */
    private function update_preservation_status($item_id, $status, $additional_data = array()) {
        global $wpdb;
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Obtém o registro atual do objeto
        $object = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $objects_table WHERE item_id = %d",
                $item_id
            ),
            ARRAY_A
        );
		
		$data = array(
            'preservation_status' => $status,
            'last_status_update' => current_time('mysql')
        );
        
        // Adiciona dados extras do Archivematica
        if (!empty($additional_data['transfer_status'])) {
            $data['transfer_status'] = $additional_data['transfer_status'];
        }
        
        if (!empty($additional_data['ingest_status'])) {
            $data['ingest_status'] = $additional_data['ingest_status'];
        }
        
        if (!empty($additional_data['sip_id']) && empty($object['sip_id'])) {
            $data['sip_id'] = $additional_data['sip_id'];
        }
        
        if (!empty($additional_data['aip_id'])) {
            $data['aip_id'] = $additional_data['aip_id'];
            $data['aip_creation_date'] = current_time('mysql');
        }
        
        if (!empty($additional_data['dip_id'])) {
            $data['dip_id'] = $additional_data['dip_id'];
        }
        
        if (!empty($additional_data['archivematica_url'])) {
            $data['archivematica_url'] = $additional_data['archivematica_url'];
        }
        
        if (!empty($additional_data['batch_id'])) {
            $data['batch_id'] = $additional_data['batch_id'];
        }
        
        if (!empty($additional_data['notes'])) {
            $data['notes'] = $additional_data['notes'];
        }
		
		// Registra o UUID da transferência
        if (!empty($additional_data['transfer_uuid'])) {
            if (!empty($object['notes'])) {
                $notes = json_decode($object['notes'], true);
                $notes['transfer_uuid'] = $additional_data['transfer_uuid'];
                $data['notes'] = wp_json_encode($notes);
            } else {
                $data['notes'] = wp_json_encode(array('transfer_uuid' => $additional_data['transfer_uuid']));
            }
        }
        
        // Atualiza ou insere o registro
        if ($object) {
            $result = $wpdb->update(
                $objects_table,
                $data,
                array('item_id' => $item_id),
                null,
                array('%d')
            );
        } else {
            // Se o objeto não existe, é necessário o ID da coleção
            if (empty($additional_data['collection_id'])) {
                return false;
            }
            
            $data['item_id'] = $item_id;
            $data['collection_id'] = $additional_data['collection_id'];
            
            $result = $wpdb->insert(
                $objects_table,
                $data,
                null
            );
        }
		
		if ($result === false) {
            $this->logger->log('error', 'Erro ao atualizar status de preservação', array(
                'item_id' => $item_id,
                'status' => $status,
                'additional_data' => $additional_data
            ));
            return false;
        }
        
        // Registra o evento no log
        $this->logger->log('info', 'Status de preservação atualizado', array(
            'item_id' => $item_id,
            'old_status' => $object ? $object['preservation_status'] : 'none',
            'new_status' => $status,
            'additional_data' => $additional_data
        ));
        
        return true;
    }
	
	/**
     * Verifica o status das transferências no Archivematica
     *
     * @param int $limit Limite de itens a verificar
     * @return array Estatísticas de verificação
     */
    public function check_transfers_status($limit = 20) {
        global $wpdb;
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Busca objetos com transferência iniciada
        $objects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $objects_table WHERE preservation_status = 'transfer_started' ORDER BY last_status_update ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        $stats = array(
            'checked' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        if (empty($objects)) {
            return $stats;
        }
		foreach ($objects as $object) {
            $stats['checked']++;
            
            // Extrai o UUID da transferência das notas
            $notes = json_decode($object['notes'], true);
            $transfer_uuid = $notes['transfer_uuid'] ?? null;
            
            if (empty($transfer_uuid)) {
                continue;
            }
            
            // Verifica o status da transferência no Archivematica
            $transfer_status = $this->archivematica_api->get_transfer_status($transfer_uuid);
            
            if (is_wp_error($transfer_status)) {
                $this->logger->log('warning', 'Erro ao verificar status da transferência', array(
                    'item_id' => $object['item_id'],
                    'transfer_uuid' => $transfer_uuid,
                    'error' => $transfer_status->get_error_message()
                ));
                continue;
            }
            
            $status = $transfer_status['status'] ?? 'unknown';
            
            // Atualiza o status no banco de dados
            $update_data = array(
                'transfer_status' => $status
            );
			
			if ($status === 'COMPLETE') {
                $stats['completed']++;
                
                // Se completou a transferência, verifica se já iniciou a ingestão
                if (!empty($transfer_status['sip_uuid'])) {
                    // Atualiza para status de ingestão
                    $this->update_preservation_status(
                        $object['item_id'],
                        'ingest_started',
                        array(
                            'transfer_status' => 'COMPLETE',
                            'ingest_status' => 'started',
                            'notes' => wp_json_encode(array(
                                'transfer_uuid' => $transfer_uuid,
                                'sip_uuid' => $transfer_status['sip_uuid']
                            ))
                        )
                    );
                    
                    $stats['details'][] = array(
                        'item_id' => $object['item_id'],
                        'status' => 'ingest_started',
                        'transfer_uuid' => $transfer_uuid,
                        'sip_uuid' => $transfer_status['sip_uuid']
                    );
                }
            } elseif ($status === 'PROCESSING') {
                $stats['in_progress']++;
                
                $this->update_preservation_status(
                    $object['item_id'],
                    'transfer_started',
                    array(
                        'transfer_status' => 'PROCESSING'
                    )
                );
				
				$stats['details'][] = array(
                    'item_id' => $object['item_id'],
                    'status' => 'transfer_processing',
                    'transfer_uuid' => $transfer_uuid
                );
            } elseif ($status === 'FAILED' || $status === 'REJECTED') {
                $stats['failed']++;
                
                $this->update_preservation_status(
                    $object['item_id'],
                    'transfer_failed',
                    array(
                        'transfer_status' => $status,
                        'notes' => wp_json_encode(array(
                            'transfer_uuid' => $transfer_uuid,
                            'message' => $transfer_status['message'] ?? 'Unknown error'
                        ))
                    )
                );
                
                $stats['details'][] = array(
                    'item_id' => $object['item_id'],
                    'status' => 'transfer_failed',
                    'transfer_uuid' => $transfer_uuid,
                    'message' => $transfer_status['message'] ?? 'Unknown error'
                );
            }
        }
        
        return $stats;
    }
	
	/**
     * Verifica o status das ingestões no Archivematica
     *
     * @param int $limit Limite de itens a verificar
     * @return array Estatísticas de verificação
     */
    public function check_ingests_status($limit = 20) {
        global $wpdb;
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Busca objetos com ingestão iniciada
        $objects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $objects_table WHERE preservation_status = 'ingest_started' ORDER BY last_status_update ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
		
		$stats = array(
            'checked' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        if (empty($objects)) {
            return $stats;
        }
        
        foreach ($objects as $object) {
            $stats['checked']++;
            
            // Extrai o UUID do SIP das notas
            $notes = json_decode($object['notes'], true);
            $sip_uuid = $notes['sip_uuid'] ?? null;
            
            if (empty($sip_uuid)) {
                continue;
            }
            
            // Verifica o status da ingestão no Archivematica
            $ingest_status = $this->archivematica_api->get_ingest_status($sip_uuid);
            
            if (is_wp_error($ingest_status)) {
                $this->logger->log('warning', 'Erro ao verificar status da ingestão', array(
                    'item_id' => $object['item_id'],
                    'sip_uuid' => $sip_uuid,
                    'error' => $ingest_status->get_error_message()
                ));
                continue;
            }
			
			$status = $ingest_status['status'] ?? 'unknown';
            
            // Atualiza o status no banco de dados
            if ($status === 'COMPLETE') {
                $stats['completed']++;
                
                // Se completou a ingestão, obtém detalhes do AIP
                $aip_info = $this->archivematica_api->get_aip_info($sip_uuid);
                
                if (!is_wp_error($aip_info) && !empty($aip_info['uuid'])) {
                    // Atualiza para status de AIP armazenado
                    $this->update_preservation_status(
                        $object['item_id'],
                        'aip_stored',
                        array(
                            'ingest_status' => 'COMPLETE',
                            'aip_id' => $aip_info['uuid'],
                            'archivematica_url' => $aip_info['url'] ?? null,
                            'notes' => wp_json_encode(array(
                                'sip_uuid' => $sip_uuid,
                                'aip_uuid' => $aip_info['uuid'],
                                'aip_details' => $aip_info
                            ))
                        )
                    );
                    
                    $stats['details'][] = array(
                        'item_id' => $object['item_id'],
                        'status' => 'aip_stored',
                        'sip_uuid' => $sip_uuid,
                        'aip_uuid' => $aip_info['uuid']
                    );
                }
				
				} elseif ($status === 'PROCESSING') {
                $stats['in_progress']++;
                
                $this->update_preservation_status(
                    $object['item_id'],
                    'ingest_started',
                    array(
                        'ingest_status' => 'PROCESSING'
                    )
                );
                
                $stats['details'][] = array(
                    'item_id' => $object['item_id'],
                    'status' => 'ingest_processing',
                    'sip_uuid' => $sip_uuid
                );
            } elseif ($status === 'FAILED' || $status === 'REJECTED') {
                $stats['failed']++;
                
                $this->update_preservation_status(
                    $object['item_id'],
                    'ingest_failed',
                    array(
                        'ingest_status' => $status,
                        'notes' => wp_json_encode(array(
                            'sip_uuid' => $sip_uuid,
                            'message' => $ingest_status['message'] ?? 'Unknown error'
                        ))
                    )
                );
                
                $stats['details'][] = array(
                    'item_id' => $object['item_id'],
                    'status' => 'ingest_failed',
                    'sip_uuid' => $sip_uuid,
                    'message' => $ingest_status['message'] ?? 'Unknown error'
                );
            }
        }
        
        return $stats;
    }
	
	/**
     * Verifica o status de preservação de um objeto específico
     *
     * @param int $object_id ID do objeto
     * @return array|WP_Error Resultado da verificação
     */
    public function check_preservation_status($object_id) {
        global $wpdb;
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Busca o objeto
        $object = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $objects_table WHERE id = %d",
                $object_id
            ),
            ARRAY_A
        );
        
        if (empty($object)) {
            return new WP_Error(
                'object_not_found',
                __('Objeto de preservação não encontrado', 'barramento-tainacan')
            );
        }
        
        // Verifica o status com base no status atual
        if ($object['preservation_status'] === 'transfer_started') {
            // Extrai o UUID da transferência das notas
            $notes = json_decode($object['notes'], true);
            $transfer_uuid = $notes['transfer_uuid'] ?? null;
            
            if (!empty($transfer_uuid)) {
                $transfer_status = $this->archivematica_api->get_transfer_status($transfer_uuid);
                
                if (!is_wp_error($transfer_status)) {
                    // Atualiza o status conforme o retorno do Archivematica
                    $this->update_transfer_status($object, $transfer_status);
                    
                    // Recarrega o objeto após a atualização
                    $object = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $objects_table WHERE id = %d",
                            $object_id
                        ),
                        ARRAY_A
                    );
                }
            }
        }
		
		elseif ($object['preservation_status'] === 'ingest_started') {
            // Extrai o UUID do SIP das notas
            $notes = json_decode($object['notes'], true);
            $sip_uuid = $notes['sip_uuid'] ?? null;
            
            if (!empty($sip_uuid)) {
                $ingest_status = $this->archivematica_api->get_ingest_status($sip_uuid);
                
                if (!is_wp_error($ingest_status)) {
                    // Atualiza o status conforme o retorno do Archivematica
                    $this->update_ingest_status($object, $ingest_status);
                    
                    // Recarrega o objeto após a atualização
                    $object = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $objects_table WHERE id = %d",
                            $object_id
                        ),
                        ARRAY_A
                    );
                }
            }
        }
        
        // Retorna o status atualizado
        return array(
            'object_id' => $object_id,
            'item_id' => $object['item_id'],
            'collection_id' => $object['collection_id'],
            'status' => $object['preservation_status'],
            'transfer_status' => $object['transfer_status'],
            'ingest_status' => $object['ingest_status'],
            'sip_id' => $object['sip_id'],
            'aip_id' => $object['aip_id'],
            'last_update' => $object['last_status_update']
        );
    }
	
	/**
     * Atualiza o status de transferência de um objeto
     *
     * @param array $object Dados do objeto
     * @param array $status_data Dados de status do Archivematica
     */
    private function update_transfer_status($object, $status_data) {
        $status = $status_data['status'] ?? 'unknown';
        
        if ($status === 'COMPLETE') {
            // Se completou a transferência, verifica se já iniciou a ingestão
            if (!empty($status_data['sip_uuid'])) {
                // Atualiza para status de ingestão
                $this->update_preservation_status(
                    $object['item_id'],
                    'ingest_started',
                    array(
                        'transfer_status' => 'COMPLETE',
                        'ingest_status' => 'started',
                        'notes' => wp_json_encode(array(
                            'transfer_uuid' => $status_data['uuid'] ?? $object['notes']['transfer_uuid'] ?? '',
                            'sip_uuid' => $status_data['sip_uuid']
                        ))
                    )
                );
            }
        } elseif ($status === 'PROCESSING') {
            $this->update_preservation_status(
                $object['item_id'],
                'transfer_started',
                array(
                    'transfer_status' => 'PROCESSING'
                )
            );
        } elseif ($status === 'FAILED' || $status === 'REJECTED') {
            $this->update_preservation_status(
                $object['item_id'],
                'transfer_failed',
                array(
                    'transfer_status' => $status,
                    'notes' => wp_json_encode(array(
                        'transfer_uuid' => $status_data['uuid'] ?? $object['notes']['transfer_uuid'] ?? '',
                        'message' => $status_data['message'] ?? 'Unknown error'
                    ))
                )
            );
        }
    }
	
	/**
     * Atualiza o status de ingestão de um objeto
     *
     * @param array $object Dados do objeto
     * @param array $status_data Dados de status do Archivematica
     */
    private function update_ingest_status($object, $status_data) {
        $status = $status_data['status'] ?? 'unknown';
        
        if ($status === 'COMPLETE') {
            // Se completou a ingestão, obtém detalhes do AIP
            $notes = json_decode($object['notes'], true);
            $sip_uuid = $notes['sip_uuid'] ?? null;
            
            if (!empty($sip_uuid)) {
                $aip_info = $this->archivematica_api->get_aip_info($sip_uuid);
                
                if (!is_wp_error($aip_info) && !empty($aip_info['uuid'])) {
                    // Atualiza para status de AIP armazenado
                    $this->update_preservation_status(
                        $object['item_id'],
                        'aip_stored',
                        array(
                            'ingest_status' => 'COMPLETE',
                            'aip_id' => $aip_info['uuid'],
                            'archivematica_url' => $aip_info['url'] ?? null,
                            'notes' => wp_json_encode(array(
                                'sip_uuid' => $sip_uuid,
                                'aip_uuid' => $aip_info['uuid'],
                                'aip_details' => $aip_info
                            ))
                        )
                    );
                }
            }
        } elseif ($status === 'PROCESSING') {
            $this->update_preservation_status(
                $object['item_id'],
                'ingest_started',
                array(
                    'ingest_status' => 'PROCESSING'
                )
            );
        }
		
		elseif ($status === 'FAILED' || $status === 'REJECTED') {
            $this->update_preservation_status(
                $object['item_id'],
                'ingest_failed',
                array(
                    'ingest_status' => $status,
                    'notes' => wp_json_encode(array(
                        'sip_uuid' => $notes['sip_uuid'] ?? '',
                        'message' => $status_data['message'] ?? 'Unknown error'
                    ))
                )
            );
        }
    }

    /**
     * Processa coleções agendadas
     *
     * @return array Estatísticas de processamento
     */
    public function process_scheduled_collections() {
        // Obtém coleções habilitadas para preservação
        $enabled_collections = get_option('barramento_enabled_collections', array());
        
        if (empty($enabled_collections)) {
            $this->logger->log('info', 'Nenhuma coleção habilitada para processamento agendado');
            return array(
                'status' => 'no_collections',
                'processed' => 0
            );
        }
        
        $stats = array(
            'collections' => 0,
            'items_added' => 0,
            'details' => array()
        );
		
		// Processa cada coleção
        foreach ($enabled_collections as $collection_id) {
            $result = $this->queue_collection_items($collection_id);
            
            $stats['collections']++;
            
            if (is_wp_error($result)) {
                $stats['details'][] = array(
                    'collection_id' => $collection_id,
                    'status' => 'error',
                    'message' => $result->get_error_message()
                );
            } else {
                $stats['items_added'] += $result['added_to_queue'];
                $stats['details'][] = array(
                    'collection_id' => $collection_id,
                    'status' => 'success',
                    'added' => $result['added_to_queue'],
                    'skipped' => $result['skipped'],
                    'total' => $result['total_items']
                );
            }
        }
        
        // Processa alguns itens da fila
        $process_limit = get_option('barramento_scheduled_process_limit', 20);
        $queue_result = $this->process_queue($process_limit);
        
        $stats['queue_processed'] = $queue_result['processed'];
        $stats['queue_successful'] = $queue_result['successful'];
        $stats['queue_failed'] = $queue_result['failed'];
        
        // Verifica status de transferências e ingestões
        $this->check_transfers_status();
        $this->check_ingests_status();
        
        return $stats;
    }
	
	/**
     * Verifica e atualiza o status de preservação de todos os itens
     *
     * @param int $limit Limite de itens a verificar
     * @return array Estatísticas de verificação
     */
    public function update_all_preservation_status($limit = 50) {
        global $wpdb;
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Busca objetos a verificar
        $objects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM $objects_table 
                 WHERE preservation_status IN ('transfer_started', 'ingest_started') 
                 ORDER BY last_status_update ASC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        $stats = array(
            'checked' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0
        );
        
        if (empty($objects)) {
            return $stats;
        }
        
        foreach ($objects as $object) {
            $stats['checked']++;
            
            $old_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT preservation_status FROM $objects_table WHERE id = %d",
                    $object['id']
                )
            );
            
            $result = $this->check_preservation_status($object['id']);
            
            if (is_wp_error($result)) {
                $stats['failed']++;
                continue;
            }
            
            $new_status = $result['status'];
            
            if ($old_status !== $new_status) {
                $stats['updated']++;
            } else {
                $stats['unchanged']++;
            }
        }
        
        return $stats;
    }
}