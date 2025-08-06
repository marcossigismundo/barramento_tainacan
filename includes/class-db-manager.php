<?php
/**
 * Classe para gerenciamento do banco de dados do plugin.
 *
 * @package Barramento_Tainacan
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Responsável por criar e gerenciar as tabelas do banco de dados do plugin.
 */
class Barramento_Tainacan_DB_Manager {

    /**
     * Prefixo para as tabelas do plugin.
     *
     * @var string
     */
    private $table_prefix;

    /**
     * Inicializa a classe e define o prefixo das tabelas.
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'barramento_';
    }

    /**
     * Inicializa o banco de dados criando as tabelas necessárias.
     */
    public function init_db() {
        $this->create_preservation_queue_table();
        $this->create_preservation_logs_table();
        $this->create_preservation_objects_table();
        $this->create_hash_registry_table();
    }

    /**
     * Cria a tabela de fila de preservação.
     */
    private function create_preservation_queue_table() {
        global $wpdb;
        $table_name = $this->table_prefix . 'queue';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            collection_id bigint(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            scheduled_date datetime DEFAULT NULL,
            batch_id varchar(36) DEFAULT NULL,
            retries int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY collection_id (collection_id),
            KEY item_id (item_id),
            KEY status (status),
            KEY batch_id (batch_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Cria a tabela de logs de preservação.
     */
    private function create_preservation_logs_table() {
        global $wpdb;
        $table_name = $this->table_prefix . 'logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            item_id bigint(20) DEFAULT NULL,
            collection_id bigint(20) DEFAULT NULL,
            batch_id varchar(36) DEFAULT NULL,
            aip_id varchar(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at),
            KEY item_id (item_id),
            KEY collection_id (collection_id),
            KEY batch_id (batch_id),
            KEY aip_id (aip_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Cria a tabela de objetos de preservação.
     */
    private function create_preservation_objects_table() {
        global $wpdb;
        $table_name = $this->table_prefix . 'objects';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            collection_id bigint(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            tainacan_status varchar(20) NOT NULL DEFAULT 'published',
            sip_id varchar(255) DEFAULT NULL,
            aip_id varchar(255) DEFAULT NULL,
            dip_id varchar(255) DEFAULT NULL,
            preservation_status varchar(50) DEFAULT 'not_preserved',
            transfer_status varchar(50) DEFAULT NULL,
            ingest_status varchar(50) DEFAULT NULL,
            sip_creation_date datetime DEFAULT NULL,
            aip_creation_date datetime DEFAULT NULL,
            last_status_update datetime DEFAULT NULL,
            archivematica_url text DEFAULT NULL,
            batch_id varchar(36) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY collection_id (collection_id),
            KEY item_id (item_id),
            KEY preservation_status (preservation_status),
            KEY sip_id (sip_id(191)),
            KEY aip_id (aip_id(191)),
            KEY batch_id (batch_id),
            UNIQUE KEY item_unique (item_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Cria a tabela de registro de hashes.
     */
    private function create_hash_registry_table() {
        global $wpdb;
        $table_name = $this->table_prefix . 'hashes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            object_id mediumint(9) NOT NULL,
            item_id bigint(20) NOT NULL,
            hash_type varchar(20) NOT NULL DEFAULT 'sha256',
            hash_value varchar(255) NOT NULL,
            file_path text DEFAULT NULL,
            file_size bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            verification_date datetime DEFAULT NULL,
            verification_status varchar(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY object_id (object_id),
            KEY item_id (item_id),
            KEY hash_type (hash_type),
            KEY hash_value (hash_value(191)),
            CONSTRAINT fk_object_id FOREIGN KEY (object_id) REFERENCES " . $this->table_prefix . "objects(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Obtém o nome completo de uma tabela do plugin.
     *
     * @param string $table Nome base da tabela.
     * @return string Nome completo da tabela.
     */
    public function get_table_name($table) {
        global $wpdb;
        return $this->table_prefix . $table;
    }
}
