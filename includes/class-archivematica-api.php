<?php
/**
 * Classe responsável por interagir com a API do Archivematica
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Classe para interação com as APIs do Archivematica.
 */
class Barramento_Tainacan_Archivematica_API {

    /**
     * URL base da API
     *
     * @var string
     */
    private $api_url;

    /**
     * Nome de usuário para autenticação
     *
     * @var string
     */
    private $username;

    /**
     * Chave de API para autenticação
     *
     * @var string
     */
    private $api_key;

    /**
     * Tipo de serviço (dashboard ou storage)
     *
     * @var string
     */
    private $service_type;

    /**
     * Inicializa a classe com os dados de autenticação
     *
     * @param string $api_url URL da API
     * @param string $username Nome de usuário
     * @param string $api_key Chave de API
     * @param string $service_type Tipo de serviço (dashboard ou storage)
     */
    public function __construct($api_url = '', $username = '', $api_key = '', $service_type = 'dashboard') {
        $this->api_url = $api_url;
        $this->username = $username;
        $this->api_key = $api_key;
        $this->service_type = $service_type;

        // Se não foram fornecidos dados, tenta obter das opções
        if (empty($api_url) || empty($username) || empty($api_key)) {
            if ($service_type === 'dashboard') {
                $this->api_url = get_option('barramento_archivematica_url', '');
                $this->username = get_option('barramento_archivematica_user', '');
                $this->api_key = get_option('barramento_archivematica_api_key', '');
            } else {
                $this->api_url = get_option('barramento_storage_service_url', '');
                $this->username = get_option('barramento_storage_service_user', '');
                $this->api_key = get_option('barramento_storage_service_api_key', '');
            }
        }

        // Certifica-se que a URL termina com "/"
        if (!empty($this->api_url) && substr($this->api_url, -1) !== '/') {
            $this->api_url .= '/';
        }
    }

    /**
     * Testa a conexão com o Archivematica
     *
     * @return array|WP_Error Resultado do teste
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->username) || empty($this->api_key)) {
            return new WP_Error(
                'missing_credentials',
                __('Credenciais incompletas', 'barramento-tainacan')
            );
        }

        $endpoint = ($this->service_type === 'dashboard') ? 'api/transfer/start_transfer/' : 'api/v2/location/';
        
        $response = $this->make_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return array(
            'status' => 'success',
            'service_type' => $this->service_type,
            'response' => $response
        );
    }

    /**
     * Inicia uma transferência no Archivematica
     *
     * @param string $directory Diretório do SIP
     * @param string $transfer_type Tipo de transferência
     * @param string $accession_id ID de acesso (opcional)
     * @return array|WP_Error Resultado da requisição
     */
    public function start_transfer($directory, $transfer_type = 'standard', $accession_id = '') {
        $data = array(
            'name' => basename($directory),
            'type' => $transfer_type,
            'path' => $directory,
        );
        
        if (!empty($accession_id)) {
            $data['accession'] = $accession_id;
        }
        
        return $this->make_request('POST', 'api/transfer/start_transfer/', $data);
    }

    /**
     * Obtém o status de uma transferência
     *
     * @param string $uuid UUID da transferência
     * @return array|WP_Error Resultado da requisição
     */
    public function get_transfer_status($uuid) {
        return $this->make_request('GET', "api/transfer/status/{$uuid}/");
    }

    /**
     * Aprova uma transferência
     *
     * @param string $directory Diretório do SIP
     * @param string $transfer_type Tipo de transferência
     * @return array|WP_Error Resultado da requisição
     */
    public function approve_transfer($directory, $transfer_type = 'standard') {
        $data = array(
            'type' => $transfer_type,
            'directory' => $directory,
        );
        
        return $this->make_request('POST', 'api/transfer/approve/', $data);
    }

    /**
     * Obtém o status de ingestão
     *
     * @param string $uuid UUID da ingestão
     * @return array|WP_Error Resultado da requisição
     */
    public function get_ingest_status($uuid) {
        return $this->make_request('GET', "api/ingest/status/{$uuid}/");
    }

    /**
     * Obtém informações sobre um AIP específico
     *
     * @param string $uuid UUID do AIP
     * @return array|WP_Error Resultado da requisição
     */
    public function get_aip_info($uuid) {
        if ($this->service_type !== 'dashboard') {
            return new WP_Error(
                'wrong_service',
                __('Esta operação deve ser executada no serviço Dashboard', 'barramento-tainacan')
            );
        }
        
        return $this->make_request('GET', "api/ingest/completed/{$uuid}/");
    }

    /**
     * Pesquisa AIPs no Archivematica
     *
     * @param array $params Parâmetros de pesquisa
     * @return array|WP_Error Resultado da requisição
     */
    public function search_aip($params = array()) {
        return $this->make_request('GET', 'api/v2/file/', $params);
    }

    /**
     * Solicita uma reingestão de AIP
     *
     * @param string $uuid UUID do AIP
     * @param string $pipeline_uuid UUID do pipeline
     * @param string $reingest_type Tipo de reingestão
     * @return array|WP_Error Resultado da requisição
     */
    public function request_aip_reingest($uuid, $pipeline_uuid, $reingest_type = 'metadata_only') {
        if ($this->service_type !== 'storage') {
            return new WP_Error(
                'wrong_service',
                __('Esta operação deve ser executada no serviço Storage', 'barramento-tainacan')
            );
        }
        
        $data = array(
            'pipeline' => $pipeline_uuid,
            'reingest_type' => $reingest_type
        );
        
        return $this->make_request('POST', "api/v2/file/{$uuid}/reingest/", $data);
    }

    /**
     * Faz uma requisição para a API do Archivematica
     *
     * @param string $method Método HTTP (GET, POST, etc)
     * @param string $endpoint Endpoint da API
     * @param array $data Dados para a requisição
     * @return array|WP_Error Resultado da requisição
     */
    private function make_request($method, $endpoint, $data = array()) {
        $url = $this->api_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'ApiKey ' . $this->username . ':' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'sslverify' => true,
        );
        
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = json_encode($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Log da resposta se modo debug estiver ativado
        if (get_option('barramento_debug_mode', false)) {
            error_log('Barramento Tainacan - API Request: ' . $url);
            error_log('Barramento Tainacan - API Response Code: ' . $status_code);
            if ($body) {
                error_log('Barramento Tainacan - API Response: ' . print_r($body, true));
            } else {
                error_log('Barramento Tainacan - API Response Body: ' . wp_remote_retrieve_body($response));
            }
        }
        
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($body['message']) ? $body['message'] : __('Erro na requisição', 'barramento-tainacan');
            return new WP_Error(
                'request_failed',
                $error_message . ' (' . $status_code . ')'
            );
        }
        
        return $body;
    }
}
