<?php

/**
 * Klasa instalacyjna odpowiedzialna za tworzenie tabel w bazie danych.
 */
class CleanSEO_Installer {

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Tabela cache AI
        $table_cache = $wpdb->prefix . 'seo_ai_cache';
        $sql_cache = "CREATE TABLE {$table_cache} (
            id BIGINT NOT NULL AUTO_INCREMENT,
            cache_key VARCHAR(255) NOT NULL UNIQUE,
            cache_value LONGTEXT,
            expires INT,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Tabela logów AI
        $table_logs = $wpdb->prefix . 'seo_openai_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id BIGINT NOT NULL AUTO_INCREMENT,
            user_id BIGINT,
            created_at DATETIME,
            status VARCHAR(20),
            model VARCHAR(100),
            type VARCHAR(50),
            tokens_used INT,
            processing_time FLOAT,
            prompt LONGTEXT,
            response LONGTEXT,
            cost FLOAT,
            error_message TEXT,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Tabela kolejki AI
        $table_queue = $wpdb->prefix . 'seo_ai_queue';
        $sql_queue = "CREATE TABLE {$table_queue} (
            id BIGINT NOT NULL AUTO_INCREMENT,
            type VARCHAR(100) NOT NULL,
            data LONGTEXT NOT NULL,
            priority INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pending',
            attempts INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql_cache);
        dbDelta($sql_logs);
        dbDelta($sql_queue);

        add_option('cleanseo_db_version', CLEANSEO_DB_VERSION);
    }
}
