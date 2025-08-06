<?php
/**
 * Classe responsável por interagir com a API do Tainacan
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Classe para interação com as APIs do Tainacan.
 */
class Barramento_Tainacan_API {

    /**
     * Obtém a lista de coleções do Tainacan
     *
     * @param array $args Argumentos para a consulta
     * @return array|WP_Error Lista de coleções ou erro
     */
    public function get_collections($args = array()) {
        // Verifica se o Tainacan está disponível
        if (!class_exists('Tainacan\Repositories\Collections')) {
            return new WP_Error(
                'tainacan_not_available',
                __('Repositório de coleções do Tainacan não disponível', 'barramento-tainacan')
            );
        }

        try {
            // Usa a API interna do Tainacan diretamente
            $collections_repository = \Tainacan\Repositories\Collections::get_instance();
            $collections = $collections_repository->fetch($args, 'OBJECT');
            
            $formatted_collections = array();
            
            // Verifica se é um objeto WP_Query ou um array de coleções
            if (is_object($collections) && method_exists($collections, 'have_posts')) {
                if ($collections->have_posts()) {
                    while ($collections->have_posts()) {
                        $collections->the_post();
                        $collection = $collections_repository->fetch_one(get_the_ID());
                        
                        // Obtém a contagem de itens - usando método compatível
                        $items_count = 0;
                        if (method_exists($collections_repository, 'fetch_items_count')) {
                            $items_count = $collections_repository->fetch_items_count($collection->get_id());
                        } else {
                            // Alternativa: obter a contagem de itens diretamente da coleção
                            $items_count = $collection->get_items_count();
                        }
                        
                        $formatted_collections[] = array(
                            'id' => $collection->get_id(),
                            'name' => $collection->get_name(),
                            'description' => $collection->get_description(),
                            'slug' => $collection->get_slug(),
                            'items_count' => $items_count,
                            'metadata' => $this->get_collection_metadata_schema($collection->get_id())
                        );
                    }
                    wp_reset_postdata();
                }
            } else if (is_array($collections)) {
                // Trata como array direto de objetos de coleção
                foreach ($collections as $collection) {
                    // Obtém a contagem de itens - usando método compatível
                    $items_count = 0;
                    if (method_exists($collection, 'get_items_count')) {
                        $items_count = $collection->get_items_count();
                    }
                    
                    $formatted_collections[] = array(
                        'id' => $collection->get_id(),
                        'name' => $collection->get_name(),
                        'description' => $collection->get_description(),
                        'slug' => $collection->get_slug(),
                        'items_count' => $items_count,
                        'metadata' => $this->get_collection_metadata_schema($collection->get_id())
                    );
                }
            }
            
            return $formatted_collections;
            
        } catch (Exception $e) {
            return new WP_Error(
                'tainacan_fetch_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Obtém o esquema de metadados de uma coleção
     *
     * @param int $collection_id ID da coleção
     * @return array Lista de metadados
     */
    public function get_collection_metadata_schema($collection_id) {
        if (!class_exists('Tainacan\Repositories\Metadata')) {
            return array();
        }

        try {
            $metadata_repository = \Tainacan\Repositories\Metadata::get_instance();
            
            $args = array(
                'collection_id' => $collection_id,
                'include_control_metadata_types' => true
            );
            
            $metadata = $metadata_repository->fetch($args, 'OBJECT');
            
            $formatted_metadata = array();
            
            // Verifica se é um objeto WP_Query ou um array de metadados
            if (is_object($metadata) && method_exists($metadata, 'have_posts')) {
                if ($metadata->have_posts()) {
                    while ($metadata->have_posts()) {
                        $metadata->the_post();
                        $meta = $metadata_repository->fetch(get_the_ID());
                        
                        $formatted_metadata[] = array(
                            'id' => $meta->get_id(),
                            'name' => $meta->get_name(),
                            'type' => $meta->get_metadata_type(),
                            'required' => $meta->get_required(),
                            'multiple' => $meta->get_multiple(),
                            'cardinality' => $meta->get_cardinality(),
                            'semantic_uri' => $meta->get_semantic_uri()
                        );
                    }
                    wp_reset_postdata();
                }
            } else if (is_array($metadata)) {
                // Trata como array direto de objetos de metadados
                foreach ($metadata as $meta) {
                    $formatted_metadata[] = array(
                        'id' => $meta->get_id(),
                        'name' => $meta->get_name(),
                        'type' => $meta->get_metadata_type(),
                        'required' => $meta->get_required(),
                        'multiple' => $meta->get_multiple(),
                        'cardinality' => $meta->get_cardinality(),
                        'semantic_uri' => $meta->get_semantic_uri()
                    );
                }
            }
            
            return $formatted_metadata;
            
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Obtém itens de uma coleção
     *
     * @param int $collection_id ID da coleção
     * @param array $args Argumentos para a consulta
     * @return array|WP_Error Itens da coleção ou erro
     */
    public function get_collection_items($collection_id, $args = array()) {
        if (!class_exists('Tainacan\Repositories\Items')) {
            return new WP_Error(
                'tainacan_not_available',
                __('Repositório de itens do Tainacan não disponível', 'barramento-tainacan')
            );
        }

        try {
            // Configura os argumentos da consulta
            $default_args = array(
                'collection_id' => $collection_id,
                'posts_per_page' => 20,
                'paged' => 1,
                'status' => 'publish'
            );
            
            $args = wp_parse_args($args, $default_args);
            
            $items_repository = \Tainacan\Repositories\Items::get_instance();
            $items = $items_repository->fetch($args, 'OBJECT');
            
            $formatted_items = array();
            $total_items = 0;
            
            // Verifica se é um objeto WP_Query ou um array de itens
            if (is_object($items) && method_exists($items, 'have_posts')) {
                $total_items = $items->found_posts;
                
                if ($items->have_posts()) {
                    while ($items->have_posts()) {
                        $items->the_post();
                        $item = $items_repository->fetch(get_the_ID());
                        
                        // Obtém os metadados do item
                        $metadata = $item->get_metadata();
                        $item_metadata = array();
                        
                        foreach ($metadata as $meta) {
                            $item_metadata[$meta->get_metadatum()->get_id()] = array(
                                'name' => $meta->get_metadatum()->get_name(),
                                'value' => $meta->get_value(),
                                'semantic_uri' => $meta->get_metadatum()->get_semantic_uri()
                            );
                        }
                        
                        // Obtém os documentos do item
                        $document = $item->get_document();
                        $document_info = array();
                        
                        if ($document) {
                            $document_info = array(
                                'id' => $document->get_id(),
                                'type' => $document->get_document_type(),
                                'url' => $document->get_url(),
                                'file_path' => $document->get_file_path(),
                                'mime_type' => $document->get_mime_type()
                            );
                        }
                        
                        // Obtém as miniaturas
                        $thumbnails = $item->get_thumbnail();
                        
                        $formatted_items[] = array(
                            'id' => $item->get_id(),
                            'title' => $item->get_title(),
                            'description' => $item->get_description(),
                            'document' => $document_info,
                            'thumbnail' => $thumbnails,
                            'metadata' => $item_metadata,
                            'url' => get_permalink($item->get_id()),
                            'creation_date' => $item->get_creation_date(),
                            'modification_date' => $item->get_modification_date()
                        );
                    }
                    wp_reset_postdata();
                }
            } else if (is_array($items)) {
                // Trata como array direto de objetos de item
                $total_items = count($items);
                
                foreach ($items as $item) {
                    // Obtém os metadados do item
                    $metadata = $item->get_metadata();
                    $item_metadata = array();
                    
                    foreach ($metadata as $meta) {
                        $item_metadata[$meta->get_metadatum()->get_id()] = array(
                            'name' => $meta->get_metadatum()->get_name(),
                            'value' => $meta->get_value(),
                            'semantic_uri' => $meta->get_metadatum()->get_semantic_uri()
                        );
                    }
                    
                    // Obtém os documentos do item
                    $document = $item->get_document();
                    $document_info = array();
                    
                    if ($document) {
                        $document_info = array(
                            'id' => $document->get_id(),
                            'type' => $document->get_document_type(),
                            'url' => $document->get_url(),
                            'file_path' => $document->get_file_path(),
                            'mime_type' => $document->get_mime_type()
                        );
                    }
                    
                    // Obtém as miniaturas
                    $thumbnails = $item->get_thumbnail();
                    
                    $formatted_items[] = array(
                        'id' => $item->get_id(),
                        'title' => $item->get_title(),
                        'description' => $item->get_description(),
                        'document' => $document_info,
                        'thumbnail' => $thumbnails,
                        'metadata' => $item_metadata,
                        'url' => get_permalink($item->get_id()),
                        'creation_date' => $item->get_creation_date(),
                        'modification_date' => $item->get_modification_date()
                    );
                }
            }
            
            return array(
                'items' => $formatted_items,
                'total' => $total_items,
                'pages' => ceil($total_items / $args['posts_per_page'])
            );
            
        } catch (Exception $e) {
            return new WP_Error(
                'tainacan_fetch_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Obtém informações detalhadas de um item específico
     *
     * @param int $item_id ID do item
     * @return array|WP_Error Dados do item ou erro
     */
    public function get_item($item_id) {
        if (!class_exists('Tainacan\Repositories\Items')) {
            return new WP_Error(
                'tainacan_not_available',
                __('Repositório de itens do Tainacan não disponível', 'barramento-tainacan')
            );
        }

        try {
            $items_repository = \Tainacan\Repositories\Items::get_instance();
            $item = $items_repository->fetch($item_id);
            
            if (!$item) {
                return new WP_Error(
                    'item_not_found',
                    __('Item não encontrado', 'barramento-tainacan')
                );
            }
            
            // Obtém os metadados do item
            $metadata = $item->get_metadata();
            $item_metadata = array();
            
            foreach ($metadata as $meta) {
                $item_metadata[$meta->get_metadatum()->get_id()] = array(
                    'name' => $meta->get_metadatum()->get_name(),
                    'value' => $meta->get_value(),
                    'semantic_uri' => $meta->get_metadatum()->get_semantic_uri()
                );
            }
            
            // Obtém os documentos do item
            $document = $item->get_document();
            $document_info = array();
            
            if ($document) {
                $document_info = array(
                    'id' => $document->get_id(),
                    'type' => $document->get_document_type(),
                    'url' => $document->get_url(),
                    'file_path' => $document->get_file_path(),
                    'mime_type' => $document->get_mime_type(),
                    'size' => $document->get_file_size()
                );
            }
            
            // Obtém os anexos do item
            $attachments = $item->get_attachments();
            $formatted_attachments = array();
            
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $file_path = get_attached_file($attachment->ID);
                    $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                    
                    $formatted_attachments[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'description' => $attachment->post_content,
                        'url' => wp_get_attachment_url($attachment->ID),
                        'mime_type' => $attachment->post_mime_type,
                        'file_path' => $file_path,
                        'size' => $file_size
                    );
                }
            }
            
            return array(
                'id' => $item->get_id(),
                'title' => $item->get_title(),
                'description' => $item->get_description(),
                'collection_id' => $item->get_collection_id(),
                'document' => $document_info,
                'attachments' => $formatted_attachments,
                'metadata' => $item_metadata,
                'url' => get_permalink($item->get_id()),
                'creation_date' => $item->get_creation_date(),
                'modification_date' => $item->get_modification_date()
            );
            
        } catch (Exception $e) {
            return new WP_Error(
                'tainacan_fetch_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Baixa o arquivo de um documento do Tainacan para o diretório temporário
     *
     * @param array $document Informações do documento
     * @param string $temp_dir Diretório temporário para salvar o arquivo
     * @return string|WP_Error Caminho do arquivo baixado ou erro
     */
    public function download_document($document, $temp_dir) {
        if (empty($document) || empty($document['file_path'])) {
            return new WP_Error(
                'missing_document',
                __('Documento não disponível', 'barramento-tainacan')
            );
        }

        // Verifica se o arquivo existe
        $file_path = $document['file_path'];
        if (!file_exists($file_path)) {
            return new WP_Error(
                'file_not_found',
                __('Arquivo não encontrado no sistema', 'barramento-tainacan')
            );
        }

        // Cria o diretório temporário se não existir
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Obtém o nome do arquivo
        $file_name = basename($file_path);
        $dest_path = trailingslashit($temp_dir) . $file_name;

        // Copia o arquivo
        if (!copy($file_path, $dest_path)) {
            return new WP_Error(
                'copy_failed',
                __('Falha ao copiar o arquivo', 'barramento-tainacan')
            );
        }

        return $dest_path;
    }

    /**
     * Baixa anexos do item para o diretório temporário
     *
     * @param array $attachments Lista de anexos
     * @param string $temp_dir Diretório temporário para salvar os arquivos
     * @return array Lista de caminhos dos arquivos baixados
     */
    public function download_attachments($attachments, $temp_dir) {
        if (empty($attachments)) {
            return array();
        }

        // Cria o diretório temporário para anexos se não existir
        $attachments_dir = trailingslashit($temp_dir) . 'attachments';
        if (!file_exists($attachments_dir)) {
            wp_mkdir_p($attachments_dir);
        }

        $downloaded_files = array();

        foreach ($attachments as $attachment) {
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
                $downloaded_files[] = array(
                    'id' => $attachment['id'],
                    'title' => $attachment['title'],
                    'path' => $dest_path,
                    'original_path' => $file_path,
                    'mime_type' => $attachment['mime_type'],
                    'size' => $attachment['size'] ?? filesize($dest_path)
                );
            }
        }

        return $downloaded_files;
    }
}