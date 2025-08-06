<?php
/**
 * Classe para gerenciamento de hashes
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Classe responsável por gerar e verificar hashes de arquivos.
 */
class Barramento_Tainacan_Hash_Manager {

    /**
     * Algoritmo de hash padrão
     *
     * @var string
     */
    private $default_algorithm;

    /**
     * Inicializa a classe
     */
    public function __construct() {
        // Define o algoritmo padrão a partir das configurações ou usa sha256
        $this->default_algorithm = get_option('barramento_hash_algorithm', 'sha256');
    }

    /**
     * Gera um hash para um arquivo
     *
     * @param string $file_path Caminho do arquivo
     * @param string $algorithm Algoritmo de hash (sha256, sha512, md5)
     * @return string|WP_Error Hash gerado ou erro
     */
    public function generate_file_hash($file_path, $algorithm = null) {
        if (!file_exists($file_path)) {
            return new WP_Error(
                'file_not_found',
                __('Arquivo não encontrado', 'barramento-tainacan')
            );
        }

        if ($algorithm === null) {
            $algorithm = $this->default_algorithm;
        }

        // Verifica se o algoritmo é suportado
        if (!in_array($algorithm, hash_algos())) {
            return new WP_Error(
                'unsupported_algorithm',
                sprintf(__('Algoritmo de hash não suportado: %s', 'barramento-tainacan'), $algorithm)
            );
        }

        // Gera o hash do arquivo
        $hash = hash_file($algorithm, $file_path);

        if ($hash === false) {
            return new WP_Error(
                'hash_generation_failed',
                __('Falha ao gerar hash do arquivo', 'barramento-tainacan')
            );
        }

        return $hash;
    }

    /**
     * Gera um hash para um diretório (soma de todos os arquivos)
     *
     * @param string $dir_path Caminho do diretório
     * @param string $algorithm Algoritmo de hash (sha256, sha512, md5)
     * @return string|WP_Error Hash gerado ou erro
     */
    public function generate_directory_hash($dir_path, $algorithm = null) {
        if (!is_dir($dir_path)) {
            return new WP_Error(
                'directory_not_found',
                __('Diretório não encontrado', 'barramento-tainacan')
            );
        }

        if ($algorithm === null) {
            $algorithm = $this->default_algorithm;
        }

        // Verifica se o algoritmo é suportado
        if (!in_array($algorithm, hash_algos())) {
            return new WP_Error(
                'unsupported_algorithm',
                sprintf(__('Algoritmo de hash não suportado: %s', 'barramento-tainacan'), $algorithm)
            );
        }

        // Lista todos os arquivos no diretório e subdiretórios
        $files = $this->get_all_files($dir_path);
        
        if (empty($files)) {
            return new WP_Error(
                'no_files_found',
                __('Nenhum arquivo encontrado no diretório', 'barramento-tainacan')
            );
        }

        // Calcula o hash para cada arquivo e combina-os
        $hashes = array();
        foreach ($files as $file) {
            $file_hash = $this->generate_file_hash($file, $algorithm);
            if (is_wp_error($file_hash)) {
                continue;
            }
            $hashes[] = $file_hash;
        }

        // Ordena os hashes para garantir consistência
        sort($hashes);
        
        // Combina todos os hashes em uma string
        $combined_hash = implode('', $hashes);
        
        // Gera o hash final da combinação
        $directory_hash = hash($algorithm, $combined_hash);

        return $directory_hash;
    }

    /**
     * Verifica se um hash corresponde a um arquivo
     *
     * @param string $file_path Caminho do arquivo
     * @param string $expected_hash Hash esperado
     * @param string $algorithm Algoritmo de hash
     * @return bool|WP_Error True se o hash corresponder, False se não corresponder, WP_Error em caso de erro
     */
    public function verify_file_hash($file_path, $expected_hash, $algorithm = null) {
        if (!file_exists($file_path)) {
            return new WP_Error(
                'file_not_found',
                __('Arquivo não encontrado', 'barramento-tainacan')
            );
        }

        if ($algorithm === null) {
            $algorithm = $this->default_algorithm;
        }

        // Gera o hash atual do arquivo
        $current_hash = $this->generate_file_hash($file_path, $algorithm);
        
        if (is_wp_error($current_hash)) {
            return $current_hash;
        }

        // Compara os hashes
        return strtolower($current_hash) === strtolower($expected_hash);
    }

    /**
     * Verifica se um hash corresponde a um diretório
     *
     * @param string $dir_path Caminho do diretório
     * @param string $expected_hash Hash esperado
     * @param string $algorithm Algoritmo de hash
     * @return bool|WP_Error True se o hash corresponder, False se não corresponder, WP_Error em caso de erro
     */
    public function verify_directory_hash($dir_path, $expected_hash, $algorithm = null) {
        if (!is_dir($dir_path)) {
            return new WP_Error(
                'directory_not_found',
                __('Diretório não encontrado', 'barramento-tainacan')
            );
        }

        if ($algorithm === null) {
            $algorithm = $this->default_algorithm;
        }

        // Gera o hash atual do diretório
        $current_hash = $this->generate_directory_hash($dir_path, $algorithm);
        
        if (is_wp_error($current_hash)) {
            return $current_hash;
        }

        // Compara os hashes
        return strtolower($current_hash) === strtolower($expected_hash);
    }

    /**
     * Obtém todos os arquivos em um diretório e seus subdiretórios
     *
     * @param string $dir Caminho do diretório
     * @return array Lista de caminhos de arquivos
     */
    private function get_all_files($dir) {
        $files = array();
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        // Ordena os arquivos para garantir consistência
        sort($files);
        
        return $files;
    }

    /**
     * Registra um evento de verificação de hash no banco de dados
     *
     * @param int $hash_id ID do registro de hash
     * @param bool $is_valid Se o hash é válido
     * @return bool Sucesso da operação
     */
    public function register_hash_verification($hash_id, $is_valid) {
        global $wpdb;
        
        $hashes_table = $wpdb->prefix . 'barramento_hashes';
        
        $result = $wpdb->update(
            $hashes_table,
            array(
                'verification_date' => current_time('mysql'),
                'verification_status' => $is_valid ? 'valid' : 'invalid'
            ),
            array('id' => $hash_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }

    /**
     * Obtém os registros de hash de um objeto de preservação
     *
     * @param int $object_id ID do objeto de preservação
     * @return array|WP_Error Registros de hash ou erro
     */
    public function get_object_hashes($object_id) {
        global $wpdb;
        
        $hashes_table = $wpdb->prefix . 'barramento_hashes';
        
        $hashes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $hashes_table WHERE object_id = %d ORDER BY created_at ASC",
                $object_id
            ),
            ARRAY_A
        );
        
        if ($hashes === false) {
            return new WP_Error(
                'database_error',
                __('Erro ao buscar registros de hash no banco de dados', 'barramento-tainacan')
            );
        }
        
        return $hashes;
    }

    /**
     * Converte um tamanho em bytes para uma representação legível
     *
     * @param int $bytes Tamanho em bytes
     * @param int $precision Precisão decimal
     * @return string Tamanho formatado
     */
    public function format_file_size($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
