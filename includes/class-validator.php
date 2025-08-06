<?php
/**
 * Classe de validação para itens antes da preservação
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Responsável por validar itens antes da preservação.
 */
class Barramento_Tainacan_Validator {

    /**
     * Logger para registro de eventos
     *
     * @var Barramento_Tainacan_Logger
     */
    private $logger;

    /**
     * Inicializa a classe
     *
     * @param Barramento_Tainacan_Logger $logger Instância do logger
     */
    public function __construct($logger = null) {
        if ($logger === null) {
            require_once BARRAMENTO_TAINACAN_PLUGIN_DIR . 'includes/class-logger.php';
            $this->logger = new Barramento_Tainacan_Logger();
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * Valida um item antes de enviá-lo para preservação
     *
     * @param array $item Dados do item
     * @param array $required_metadata Lista de metadados obrigatórios
     * @param array $mapping_metadata Lista de mapeamento de metadados
     * @return bool|WP_Error True se válido, WP_Error com erros encontrados caso contrário
     */
    public function validate_item($item, $required_metadata = array(), $mapping_metadata = array()) {
        if (empty($item) || empty($item['id'])) {
            return new WP_Error(
                'invalid_item',
                __('Item inválido ou incompleto', 'barramento-tainacan')
            );
        }
        
        $errors = array();
        
        // Verifica se há documento principal
        if ($this->is_document_required() && empty($item['document'])) {
            $errors[] = __('O item não possui documento principal', 'barramento-tainacan');
        } elseif (!empty($item['document']) && empty($item['document']['file_path'])) {
            $errors[] = __('O documento principal não possui arquivo associado', 'barramento-tainacan');
        } elseif (!empty($item['document']['file_path']) && !file_exists($item['document']['file_path'])) {
            $errors[] = __('O arquivo do documento principal não existe no sistema', 'barramento-tainacan');
        }
        
        // Verifica metadados obrigatórios
        if (!empty($required_metadata) && !empty($item['metadata'])) {
            foreach ($required_metadata as $meta_id) {
                if (!isset($item['metadata'][$meta_id]) || empty($item['metadata'][$meta_id]['value'])) {
                    $errors[] = sprintf(
                        __('Metadado obrigatório "%s" não preenchido', 'barramento-tainacan'),
                        $this->get_metadata_name($meta_id, $item)
                    );
                }
            }
        }
        
        // Verifica se o item já foi preservado anteriormente
        if ($this->item_already_preserved($item['id'])) {
            // Não é um erro, apenas um aviso que será tratado pelo processador
            $this->logger->log('info', 'Item já possui registro de preservação', array(
                'item_id' => $item['id'],
                'collection_id' => $item['collection_id']
            ));
        }
        
        // Se houver erros, retorna como WP_Error
        if (!empty($errors)) {
            $error_message = sprintf(
                __('Item %d inválido para preservação: %s', 'barramento-tainacan'),
                $item['id'],
                implode('; ', $errors)
            );
            
            $this->logger->log('warning', $error_message, array(
                'item_id' => $item['id'],
                'collection_id' => $item['collection_id'],
                'errors' => $errors
            ));
            
            return new WP_Error('validation_failed', $error_message, $errors);
        }
        
        return true;
    }

    /**
     * Verifica se documento é obrigatório conforme configurações
     *
     * @return bool True se documento for obrigatório
     */
    private function is_document_required() {
        return (bool) apply_filters('barramento_document_required', true);
    }

    /**
     * Verifica se um item já foi preservado anteriormente
     *
     * @param int $item_id ID do item
     * @return bool True se o item já foi preservado
     */
    private function item_already_preserved($item_id) {
        global $wpdb;
        
        $objects_table = $wpdb->prefix . 'barramento_objects';
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $objects_table WHERE item_id = %d AND preservation_status != 'not_preserved'",
                $item_id
            )
        );
        
        return (int) $result > 0;
    }

    /**
     * Obtém o nome legível de um metadado a partir do ID
     *
     * @param int $meta_id ID do metadado
     * @param array $item Dados do item
     * @return string Nome do metadado
     */
    private function get_metadata_name($meta_id, $item) {
        if (!empty($item['metadata'][$meta_id]['name'])) {
            return $item['metadata'][$meta_id]['name'];
        }
        
        // Tenta buscar no Tainacan
        if (class_exists('Tainacan\Repositories\Metadata')) {
            try {
                $metadata_repository = \Tainacan\Repositories\Metadata::get_instance();
                $meta = $metadata_repository->fetch($meta_id);
                
                if ($meta) {
                    return $meta->get_name();
                }
            } catch (Exception $e) {
                // Ignora erros
            }
        }
        
        return "ID: {$meta_id}";
    }

    /**
     * Verifica se uma coleção está configurada para preservação
     *
     * @param int $collection_id ID da coleção
     * @return bool True se a coleção estiver habilitada para preservação
     */
    public function is_collection_enabled($collection_id) {
        $enabled_collections = get_option('barramento_enabled_collections', array());
        
        return in_array($collection_id, (array) $enabled_collections);
    }

    /**
     * Verifica se um diretório de SIP atende aos requisitos
     *
     * @param string $sip_path Caminho do diretório SIP
     * @return bool|WP_Error True se válido, WP_Error caso contrário
     */
    public function validate_sip_directory($sip_path) {
        if (!is_dir($sip_path)) {
            return new WP_Error(
                'invalid_sip_directory',
                __('Diretório SIP não encontrado', 'barramento-tainacan')
            );
        }
        
        // Verifica se a estrutura básica está presente
        $required_dirs = array(
            trailingslashit($sip_path) . 'objects',
            trailingslashit($sip_path) . 'metadata',
            trailingslashit($sip_path) . 'metadata/submissionDocumentation'
        );
        
        $required_files = array(
            trailingslashit($sip_path) . 'metadata/dc.xml',
            trailingslashit($sip_path) . 'metadata/mets.xml'
        );
        
        $missing_dirs = array();
        $missing_files = array();
        
        foreach ($required_dirs as $dir) {
            if (!is_dir($dir)) {
                $missing_dirs[] = basename($dir);
            }
        }
        
        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                $missing_files[] = 'metadata/' . basename($file);
            }
        }
        
        $errors = array();
        
        if (!empty($missing_dirs)) {
            $errors[] = sprintf(
                __('Diretórios obrigatórios ausentes: %s', 'barramento-tainacan'),
                implode(', ', $missing_dirs)
            );
        }
        
        if (!empty($missing_files)) {
            $errors[] = sprintf(
                __('Arquivos obrigatórios ausentes: %s', 'barramento-tainacan'),
                implode(', ', $missing_files)
            );
        }
        
        // Verifica se há objetos no diretório objects
        $objects_dir = trailingslashit($sip_path) . 'objects';
        if (is_dir($objects_dir) && $this->is_directory_empty($objects_dir)) {
            $errors[] = __('Diretório de objetos vazio', 'barramento-tainacan');
        }
        
        if (!empty($errors)) {
            return new WP_Error(
                'invalid_sip_structure',
                implode('; ', $errors),
                $errors
            );
        }
        
        return true;
    }
    
    /**
     * Verifica se um diretório está vazio
     *
     * @param string $dir Caminho do diretório
     * @return bool True se estiver vazio
     */
    private function is_directory_empty($dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verifica se o tamanho do SIP está dentro do limite configurado
     *
     * @param string $sip_path Caminho do diretório SIP
     * @return bool|WP_Error True se estiver dentro do limite, WP_Error caso contrário
     */
    public function validate_sip_size($sip_path) {
        if (!is_dir($sip_path)) {
            return new WP_Error(
                'invalid_sip_directory',
                __('Diretório SIP não encontrado', 'barramento-tainacan')
            );
        }
        
        // Obtém o limite configurado em MB (padrão: 1024 MB = 1 GB)
        $max_size_mb = get_option('barramento_max_sip_size', 1024);
        $max_size_bytes = $max_size_mb * 1024 * 1024;
        
        // Calcula o tamanho do diretório
        $size_bytes = $this->get_directory_size($sip_path);
        
        if ($size_bytes > $max_size_bytes) {
            return new WP_Error(
                'sip_too_large',
                sprintf(
                    __('Tamanho do SIP (%s) excede o limite configurado (%s MB)', 'barramento-tainacan'),
                    $this->format_file_size($size_bytes),
                    $max_size_mb
                )
            );
        }
        
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
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Formata um tamanho em bytes para uma representação legível
     *
     * @param int $bytes Tamanho em bytes
     * @param int $precision Precisão decimal
     * @return string Tamanho formatado
     */
    private function format_file_size($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
