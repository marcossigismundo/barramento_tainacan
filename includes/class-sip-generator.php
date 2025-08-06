<?php
/**
 * Classe para geração de pacotes SIP (Submission Information Package)
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Responsável por gerar pacotes SIP para envio ao Archivematica.
 */
class Barramento_Tainacan_SIP_Generator {

    /**
     * Diretório temporário para criação de pacotes
     *
     * @var string
     */
    private $temp_dir;

    /**
     * Diretório base para armazenamento de SIPs
     *
     * @var string
     */
    private $sip_dir;

    /**
     * Instância da classe de logger
     *
     * @var Barramento_Tainacan_Logger
     */
    private $logger;

    /**
     * Instância da classe do gerenciador de hash
     *
     * @var Barramento_Tainacan_Hash_Manager
     */
    private $hash_manager;

    /**
     * Inicializa a classe
     *
     * @param Barramento_Tainacan_Logger $logger Instância do logger
     * @param Barramento_Tainacan_Hash_Manager $hash_manager Instância do gerenciador de hash
     */
    public function __construct($logger = null, $hash_manager = null) {
        // Define o diretório temporário
        $upload_dir = wp_upload_dir();
        $this->temp_dir = trailingslashit($upload_dir['basedir']) . 'barramento-temp';
        
        // Define o diretório para armazenamento de SIPs
        $this->sip_dir = trailingslashit($upload_dir['basedir']) . 'barramento-sips';
        
        // Cria os diretórios se não existirem
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
        
        if (!file_exists($this->sip_dir)) {
            wp_mkdir_p($this->sip_dir);
        }
        
        // Inicializa o logger
        if ($logger === null) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php';
            $this->logger = new Barramento_Tainacan_Logger();
        } else {
            $this->logger = $logger;
        }
        
        // Inicializa o gerenciador de hash
        if ($hash_manager === null) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-hash-manager.php';
            $this->hash_manager = new Barramento_Tainacan_Hash_Manager();
        } else {
            $this->hash_manager = $hash_manager;
        }
    }

    /**
     * Gera um pacote SIP para um item do Tainacan
     *
     * @param array $item Dados do item
     * @param array $metadata_mapping Mapeamento dos metadados
     * @param array $fixed_metadata Metadados fixos a serem incluídos
     * @return array|WP_Error Informações do SIP gerado ou erro
     */
    public function generate_sip($item, $metadata_mapping = array(), $fixed_metadata = array()) {
        if (empty($item) || empty($item['id'])) {
            return new WP_Error(
                'invalid_item',
                __('Item inválido', 'barramento-tainacan')
            );
        }

        try {
            // Gera um ID único para o SIP
            $sip_id = uniqid('sip-');
            $batch_id = uniqid('batch-');
            
            // Cria diretório para o SIP
            $sip_path = trailingslashit($this->sip_dir) . $sip_id;
            if (!file_exists($sip_path)) {
                wp_mkdir_p($sip_path);
            }
            
            // Cria a estrutura básica do SIP (seguindo o padrão OAIS/Archivematica)
            $objects_dir = trailingslashit($sip_path) . 'objects';
            $metadata_dir = trailingslashit($sip_path) . 'metadata';
            $submissions_dir = trailingslashit($metadata_dir) . 'submissionDocumentation';
            
            wp_mkdir_p($objects_dir);
            wp_mkdir_p($metadata_dir);
            wp_mkdir_p($submissions_dir);
            
            // Obtém os arquivos (documento principal e anexos)
            $files = $this->prepare_item_files($item, $objects_dir);
            
            if (is_wp_error($files)) {
                $this->logger->log('error', 'Falha ao preparar arquivos do item', array(
                    'item_id' => $item['id'],
                    'collection_id' => $item['collection_id'],
                    'error' => $files->get_error_message()
                ));
                return $files;
            }
            
            // Gera o arquivo de metadados Dublin Core
            $dc_metadata = $this->generate_dc_metadata($item, $metadata_mapping, $fixed_metadata);
            $dc_file_path = trailingslashit($metadata_dir) . 'dc.xml';
            file_put_contents($dc_file_path, $dc_metadata);
            
            // Gera um arquivo de metadados METS
            $mets_metadata = $this->generate_mets_metadata($item, $files, $metadata_mapping, $fixed_metadata);
            $mets_file_path = trailingslashit($metadata_dir) . 'mets.xml';
            file_put_contents($mets_file_path, $mets_metadata);
            
            // Gera um arquivo JSON com todos os metadados do Tainacan
            $tainacan_json = json_encode($item, JSON_PRETTY_PRINT);
            $tainacan_json_path = trailingslashit($submissions_dir) . 'tainacan_metadata.json';
            file_put_contents($tainacan_json_path, $tainacan_json);
            
            // Calcula o hash do pacote SIP completo
            $hash_algorithm = get_option('barramento_hash_algorithm', 'sha256');
            $sip_hash = $this->hash_manager->generate_directory_hash($sip_path, $hash_algorithm);
            
            // Armazena informações do SIP
            $sip_info = array(
                'sip_id' => $sip_id,
                'batch_id' => $batch_id,
                'item_id' => $item['id'],
                'collection_id' => $item['collection_id'],
                'path' => $sip_path,
                'files' => $files,
                'hash' => $sip_hash,
                'hash_algorithm' => $hash_algorithm,
                'size' => $this->get_directory_size($sip_path),
                'creation_date' => current_time('mysql')
            );
            
            // Registra o SIP no banco de dados
            $this->register_sip($sip_info);
            
            // Registra o evento no log
            $this->logger->log('info', 'SIP gerado com sucesso', array(
                'item_id' => $item['id'],
                'collection_id' => $item['collection_id'],
                'sip_id' => $sip_id,
                'batch_id' => $batch_id,
                'hash' => $sip_hash
            ));
            
            return $sip_info;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Exceção ao gerar SIP', array(
                'item_id' => $item['id'],
                'collection_id' => $item['collection_id'],
                'exception' => $e->getMessage()
            ));
            
            return new WP_Error(
                'sip_generation_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Prepara os arquivos do item para inclusão no SIP
     *
     * @param array $item Dados do item
     * @param string $objects_dir Diretório de objetos do SIP
     * @return array|WP_Error Lista de arquivos preparados ou erro
     */
    private function prepare_item_files($item, $objects_dir) {
        $prepared_files = array();
        
        // Verifica se há um documento principal
        if (!empty($item['document']) && !empty($item['document']['file_path'])) {
            $document = $item['document'];
            $file_path = $document['file_path'];
            
            if (file_exists($file_path)) {
                $file_name = basename($file_path);
                $dest_path = trailingslashit($objects_dir) . $file_name;
                
                // Copia o arquivo para o diretório de objetos
                if (copy($file_path, $dest_path)) {
                    $hash = $this->hash_manager->generate_file_hash($dest_path);
                    $prepared_files['main_document'] = array(
                        'original_path' => $file_path,
                        'sip_path' => $dest_path,
                        'file_name' => $file_name,
                        'mime_type' => $document['mime_type'],
                        'size' => filesize($dest_path),
                        'hash' => $hash,
                        'hash_algorithm' => get_option('barramento_hash_algorithm', 'sha256')
                    );
                } else {
                    return new WP_Error(
                        'file_copy_failed',
                        __('Falha ao copiar o documento principal', 'barramento-tainacan')
                    );
                }
            } else {
                return new WP_Error(
                    'main_document_not_found',
                    __('Documento principal não encontrado no sistema', 'barramento-tainacan')
                );
            }
        }
        
        // Processa os anexos
        if (!empty($item['attachments'])) {
            $attachments_dir = trailingslashit($objects_dir) . 'attachments';
            if (!file_exists($attachments_dir)) {
                wp_mkdir_p($attachments_dir);
            }
            
            foreach ($item['attachments'] as $attachment) {
                if (empty($attachment['file_path'])) {
                    continue;
                }
                
                $file_path = $attachment['file_path'];
                if (!file_exists($file_path)) {
                    continue;
                }
                
                $file_name = basename($file_path);
                $dest_path = trailingslashit($attachments_dir) . $file_name;
                
                if (copy($file_path, $dest_path)) {
                    $hash = $this->hash_manager->generate_file_hash($dest_path);
                    $prepared_files['attachments'][] = array(
                        'id' => $attachment['id'],
                        'original_path' => $file_path,
                        'sip_path' => $dest_path,
                        'file_name' => $file_name,
                        'mime_type' => $attachment['mime_type'],
                        'size' => filesize($dest_path),
                        'hash' => $hash,
                        'hash_algorithm' => get_option('barramento_hash_algorithm', 'sha256')
                    );
                }
            }
        }
        
        return $prepared_files;
    }

    /**
     * Gera o XML de metadados Dublin Core
     *
     * @param array $item Dados do item
     * @param array $metadata_mapping Mapeamento dos metadados
     * @param array $fixed_metadata Metadados fixos a serem incluídos
     * @return string XML de metadados Dublin Core
     */
    private function generate_dc_metadata($item, $metadata_mapping = array(), $fixed_metadata = array()) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        // Elemento raiz
        $root = $dom->createElement('metadata');
        $dom->appendChild($root);
        
        // Namespace Dublin Core
        $root->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $root->setAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');
        
        // Mapeia os metadados do Tainacan para Dublin Core
        $dc_mapping = array(
            'title' => 'dc:title',
            'description' => 'dc:description',
            'creator' => 'dc:creator',
            'date' => 'dc:date',
            'type' => 'dc:type',
            'format' => 'dc:format',
            'identifier' => 'dc:identifier',
            'language' => 'dc:language',
            'publisher' => 'dc:publisher',
            'relation' => 'dc:relation',
            'rights' => 'dc:rights',
            'source' => 'dc:source',
            'subject' => 'dc:subject',
            'coverage' => 'dc:coverage',
            'contributor' => 'dc:contributor'
        );
        
        // Adiciona título e descrição
        $this->add_dc_element($dom, $root, 'dc:title', $item['title']);
        if (!empty($item['description'])) {
            $this->add_dc_element($dom, $root, 'dc:description', $item['description']);
        }
        
        // Adiciona identificador do sistema
        $this->add_dc_element($dom, $root, 'dc:identifier', 'tainacan:' . $item['id']);
        
        // Adiciona URL no repositório
        if (!empty($item['url'])) {
            $this->add_dc_element($dom, $root, 'dc:relation', $item['url']);
        }
        
        // Adiciona data de criação
        if (!empty($item['creation_date'])) {
            $this->add_dc_element($dom, $root, 'dcterms:created', $item['creation_date']);
        }
        
        // Adiciona data de modificação
        if (!empty($item['modification_date'])) {
            $this->add_dc_element($dom, $root, 'dcterms:modified', $item['modification_date']);
        }
        
        // Mapeia outros metadados conforme configuração
        if (!empty($metadata_mapping) && !empty($item['metadata'])) {
            foreach ($metadata_mapping as $meta_id => $dc_element) {
                if (isset($item['metadata'][$meta_id]) && !empty($item['metadata'][$meta_id]['value'])) {
                    $this->add_dc_element($dom, $root, $dc_element, $item['metadata'][$meta_id]['value']);
                }
            }
        }
        
        // Adiciona metadados fixos
        if (!empty($fixed_metadata)) {
            foreach ($fixed_metadata as $dc_element => $value) {
                $this->add_dc_element($dom, $root, $dc_element, $value);
            }
        }
        
        return $dom->saveXML();
    }

    /**
     * Adiciona um elemento Dublin Core ao XML
     *
     * @param DOMDocument $dom Documento DOM
     * @param DOMElement $parent Elemento pai
     * @param string $name Nome do elemento
     * @param string|array $value Valor do elemento
     */
    private function add_dc_element($dom, $parent, $name, $value) {
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->add_dc_element($dom, $parent, $name, $val);
            }
            return;
        }
        
        $element = $dom->createElement($name);
        $parent->appendChild($element);
        
        $text = $dom->createTextNode($value);
        $element->appendChild($text);
    }

    /**
     * Gera o XML de metadados METS
     *
     * @param array $item Dados do item
     * @param array $files Arquivos do item
     * @param array $metadata_mapping Mapeamento dos metadados
     * @param array $fixed_metadata Metadados fixos a serem incluídos
     * @return string XML de metadados METS
     */
    private function generate_mets_metadata($item, $files, $metadata_mapping = array(), $fixed_metadata = array()) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        // Elemento raiz METS
        $mets = $dom->createElement('mets:mets');
        $dom->appendChild($mets);
        
        // Namespaces
        $mets->setAttribute('xmlns:mets', 'http://www.loc.gov/METS/');
        $mets->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $mets->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $mets->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $mets->setAttribute('xmlns:premis', 'http://www.loc.gov/premis/v3');
        
        // Cabeçalho METS
        $metsHdr = $dom->createElement('mets:metsHdr');
        $mets->appendChild($metsHdr);
        $metsHdr->setAttribute('CREATEDATE', date('c'));
        
        // Agente (Tainacan)
        $agent = $dom->createElement('mets:agent');
        $metsHdr->appendChild($agent);
        $agent->setAttribute('ROLE', 'CREATOR');
        $agent->setAttribute('TYPE', 'OTHER');
        $agent->setAttribute('OTHERTYPE', 'SOFTWARE');
        
        $name = $dom->createElement('mets:name', 'Tainacan Repository');
        $agent->appendChild($name);
        
        // Seção de metadados descritivos (Dublin Core)
        $dmdSec = $dom->createElement('mets:dmdSec');
        $mets->appendChild($dmdSec);
        $dmdSec->setAttribute('ID', 'dmdSec_1');
        
        $mdWrap = $dom->createElement('mets:mdWrap');
        $dmdSec->appendChild($mdWrap);
        $mdWrap->setAttribute('MDTYPE', 'DC');
        
        $xmlData = $dom->createElement('mets:xmlData');
        $mdWrap->appendChild($xmlData);
        
        // Elementos Dublin Core
        $dc = $dom->createElement('dc:title', $item['title']);
        $xmlData->appendChild($dc);
        
        if (!empty($item['description'])) {
            $dc = $dom->createElement('dc:description', $item['description']);
            $xmlData->appendChild($dc);
        }
        
        // Identificador do item
        $dc = $dom->createElement('dc:identifier', 'tainacan:' . $item['id']);
        $xmlData->appendChild($dc);
        
        // Mapeia outros metadados conforme configuração
        if (!empty($metadata_mapping) && !empty($item['metadata'])) {
            foreach ($metadata_mapping as $meta_id => $dc_element) {
                if (isset($item['metadata'][$meta_id]) && !empty($item['metadata'][$meta_id]['value'])) {
                    $value = $item['metadata'][$meta_id]['value'];
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $dc = $dom->createElement($dc_element, $val);
                            $xmlData->appendChild($dc);
                        }
                    } else {
                        $dc = $dom->createElement($dc_element, $value);
                        $xmlData->appendChild($dc);
                    }
                }
            }
        }
        
        // Adiciona metadados fixos
        if (!empty($fixed_metadata)) {
            foreach ($fixed_metadata as $dc_element => $value) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $dc = $dom->createElement($dc_element, $val);
                        $xmlData->appendChild($dc);
                    }
                } else {
                    $dc = $dom->createElement($dc_element, $value);
                    $xmlData->appendChild($dc);
                }
            }
        }
        
        // Seção de arquivo
        $fileSec = $dom->createElement('mets:fileSec');
        $mets->appendChild($fileSec);
        
        // Grupo de arquivos
        $fileGrp = $dom->createElement('mets:fileGrp');
        $fileSec->appendChild($fileGrp);
        $fileGrp->setAttribute('USE', 'original');
        
        // Adiciona o documento principal
        if (!empty($files['main_document'])) {
            $file = $dom->createElement('mets:file');
            $fileGrp->appendChild($file);
            $file->setAttribute('ID', 'file_1');
            $file->setAttribute('MIMETYPE', $files['main_document']['mime_type']);
            $file->setAttribute('SIZE', $files['main_document']['size']);
            $file->setAttribute('CHECKSUMTYPE', strtoupper($files['main_document']['hash_algorithm']));
            $file->setAttribute('CHECKSUM', $files['main_document']['hash']);
            
            $flocat = $dom->createElement('mets:FLocat');
            $file->appendChild($flocat);
            $flocat->setAttribute('LOCTYPE', 'OTHER');
            $flocat->setAttribute('OTHERLOCTYPE', 'SYSTEM');
            $flocat->setAttribute('xlink:href', 'objects/' . $files['main_document']['file_name']);
        }
        
        // Adiciona os anexos
        if (!empty($files['attachments'])) {
            foreach ($files['attachments'] as $i => $attachment) {
                $file = $dom->createElement('mets:file');
                $fileGrp->appendChild($file);
                $file->setAttribute('ID', 'file_' . ($i + 2));
                $file->setAttribute('MIMETYPE', $attachment['mime_type']);
                $file->setAttribute('SIZE', $attachment['size']);
                $file->setAttribute('CHECKSUMTYPE', strtoupper($attachment['hash_algorithm']));
                $file->setAttribute('CHECKSUM', $attachment['hash']);
                
                $flocat = $dom->createElement('mets:FLocat');
                $file->appendChild($flocat);
                $flocat->setAttribute('LOCTYPE', 'OTHER');
                $flocat->setAttribute('OTHERLOCTYPE', 'SYSTEM');
                $flocat->setAttribute('xlink:href', 'objects/attachments/' . $attachment['file_name']);
            }
        }
        
        // Mapa estrutural
        $structMap = $dom->createElement('mets:structMap');
        $mets->appendChild($structMap);
        $structMap->setAttribute('TYPE', 'physical');
        
        $div = $dom->createElement('mets:div');
        $structMap->appendChild($div);
        $div->setAttribute('TYPE', 'Item');
        $div->setAttribute('LABEL', $item['title']);
        
        // Referência para o documento principal
        if (!empty($files['main_document'])) {
            $fptr = $dom->createElement('mets:fptr');
            $div->appendChild($fptr);
            $fptr->setAttribute('FILEID', 'file_1');
        }
        
        // Referências para os anexos
        if (!empty($files['attachments'])) {
            foreach ($files['attachments'] as $i => $attachment) {
                $fptr = $dom->createElement('mets:fptr');
                $div->appendChild($fptr);
                $fptr->setAttribute('FILEID', 'file_' . ($i + 2));
            }
        }
        
        return $dom->saveXML();
    }

    /**
     * Registra o SIP no banco de dados
     *
     * @param array $sip_info Informações do SIP
     * @return int|bool ID do registro ou false em caso de falha
     */
    private function register_sip($sip_info) {
        global $wpdb;
        
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        // Verifica se já existe um registro para este item
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $objects_table WHERE item_id = %d",
                $sip_info['item_id']
            )
        );
        
        $data = array(
            'collection_id' => $sip_info['collection_id'],
            'item_id' => $sip_info['item_id'],
            'sip_id' => $sip_info['sip_id'],
            'preservation_status' => 'sip_created',
            'sip_creation_date' => current_time('mysql'),
            'last_status_update' => current_time('mysql'),
            'batch_id' => $sip_info['batch_id'],
            'notes' => json_encode($sip_info)
        );
        
        $format = array(
            '%d', // collection_id
            '%d', // item_id
            '%s', // sip_id
            '%s', // preservation_status
            '%s', // sip_creation_date
            '%s', // last_status_update
            '%s', // batch_id
            '%s'  // notes
        );
        
        if ($existing) {
            // Atualiza o registro existente
            $wpdb->update(
                $objects_table,
                $data,
                array('id' => $existing->id),
                $format,
                array('%d')
            );
            
            $object_id = $existing->id;
        } else {
            // Insere um novo registro
            $wpdb->insert(
                $objects_table,
                $data,
                $format
            );
            
            $object_id = $wpdb->insert_id;
        }
        
        // Registra os hashes no banco de dados
        if ($object_id) {
            $this->register_hashes($object_id, $sip_info);
        }
        
        return $object_id;
    }

    /**
     * Registra os hashes dos arquivos no banco de dados
     *
     * @param int $object_id ID do objeto de preservação
     * @param array $sip_info Informações do SIP
     * @return bool True se bem-sucedido, False caso contrário
     */
    private function register_hashes($object_id, $sip_info) {
        global $wpdb;
        
        $hashes_table = $wpdb->prefix . 'barramento_hashes';
        
        // Registra o hash do documento principal
        if (!empty($sip_info['files']['main_document'])) {
            $file = $sip_info['files']['main_document'];
            
            $wpdb->insert(
                $hashes_table,
                array(
                    'object_id' => $object_id,
                    'item_id' => $sip_info['item_id'],
                    'hash_type' => $file['hash_algorithm'],
                    'hash_value' => $file['hash'],
                    'file_path' => $file['sip_path'],
                    'file_size' => $file['size'],
                    'created_at' => current_time('mysql')
                ),
                array(
                    '%d', // object_id
                    '%d', // item_id
                    '%s', // hash_type
                    '%s', // hash_value
                    '%s', // file_path
                    '%d', // file_size
                    '%s'  // created_at
                )
            );
        }
        
        // Registra os hashes dos anexos
        if (!empty($sip_info['files']['attachments'])) {
            foreach ($sip_info['files']['attachments'] as $file) {
                $wpdb->insert(
                    $hashes_table,
                    array(
                        'object_id' => $object_id,
                        'item_id' => $sip_info['item_id'],
                        'hash_type' => $file['hash_algorithm'],
                        'hash_value' => $file['hash'],
                        'file_path' => $file['sip_path'],
                        'file_size' => $file['size'],
                        'created_at' => current_time('mysql')
                    ),
                    array(
                        '%d', // object_id
                        '%d', // item_id
                        '%s', // hash_type
                        '%s', // hash_value
                        '%s', // file_path
                        '%d', // file_size
                        '%s'  // created_at
                    )
                );
            }
        }
        
        // Registra o hash do pacote SIP completo
        $wpdb->insert(
            $hashes_table,
            array(
                'object_id' => $object_id,
                'item_id' => $sip_info['item_id'],
                'hash_type' => $sip_info['hash_algorithm'],
                'hash_value' => $sip_info['hash'],
                'file_path' => $sip_info['path'],
                'file_size' => $sip_info['size'],
                'created_at' => current_time('mysql')
            ),
            array(
                '%d', // object_id
                '%d', // item_id
                '%s', // hash_type
                '%s', // hash_value
                '%s', // file_path
                '%d', // file_size
                '%s'  // created_at
            )
        );
        
        return true;
    }

    /**
     * Obtém o tamanho de um diretório
     *
     * @param string $dir Caminho do diretório
     * @return int Tamanho em bytes
     */
    private function get_directory_size($dir) {
        $size = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($objects as $object) {
            if ($object->isFile()) {
                $size += $object->getSize();
            }
        }
        
        return $size;
    }
}
