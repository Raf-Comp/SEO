<?php

/**
 * Klasa instalacyjna dla funkcji AI w CleanSEO
 */
class CleanSEO_AI_Installer {
    /**
     * Aktualna wersja schematu bazy danych
     */
    const DB_VERSION = '1.2';
    
    /**
     * Prefiks opcji
     */
    const OPTION_PREFIX = 'cleanseo_ai_';
    
    /**
     * Zainstaluj lub zaktualizuj tabele bazy danych
     * 
     * @return bool Czy instalacja się powiodła
     */
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $success = true;
        
        // Rozpocznij mierzenie czasu
        $start_time = microtime(true);
        
        // Tabela cache AI
        $cache_table = $wpdb->prefix . 'seo_ai_cache';
        $sql_cache = "CREATE TABLE IF NOT EXISTS $cache_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            hash_key varchar(64) NOT NULL,
            prompt text NOT NULL,
            model varchar(50) NOT NULL,
            response longtext NOT NULL,
            cache_type varchar(50) NOT NULL DEFAULT '',
            post_id bigint(20) NOT NULL DEFAULT 0,
            is_serialized tinyint(1) NOT NULL DEFAULT 0,
            data_size int(11) NOT NULL DEFAULT 0,
            hits int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY hash_key (hash_key),
            KEY model (model),
            KEY cache_type (cache_type),
            KEY post_id (post_id),
            KEY expires_at (expires_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Tabela logów AI
        $logs_table = $wpdb->prefix . 'seo_openai_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL DEFAULT 0,
            model varchar(50) NOT NULL,
            prompt text NOT NULL,
            response longtext NOT NULL,
            cache_hit tinyint(1) NOT NULL DEFAULT 0,
            tokens int(11) NOT NULL DEFAULT 0,
            cost decimal(10,5) NOT NULL DEFAULT 0.00000,
            duration decimal(10,3) NOT NULL DEFAULT 0.000,
            status varchar(20) NOT NULL DEFAULT 'success',
            error_message text,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY model (model),
            KEY status (status),
            KEY created_at (created_at),
            KEY cache_hit (cache_hit)
        ) $charset_collate;";
        
        // Tabela zadań AI
        $jobs_table = $wpdb->prefix . 'seo_ai_jobs';
        $sql_jobs = "CREATE TABLE IF NOT EXISTS $jobs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            data longtext NOT NULL,
            result longtext DEFAULT NULL,
            priority int(11) NOT NULL DEFAULT 10,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            error_message text DEFAULT NULL,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabela statystyk użycia AI
        $stats_table = $wpdb->prefix . 'seo_ai_stats';
        $sql_stats = "CREATE TABLE IF NOT EXISTS $stats_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            model varchar(50) NOT NULL,
            cache_type varchar(50) NOT NULL DEFAULT '',
            requests int(11) NOT NULL DEFAULT 0,
            cache_hits int(11) NOT NULL DEFAULT 0,
            tokens int(11) NOT NULL DEFAULT 0,
            cost decimal(10,5) NOT NULL DEFAULT 0.00000,
            PRIMARY KEY  (id),
            UNIQUE KEY date_model_type (date, model, cache_type),
            KEY date (date),
            KEY model (model),
            KEY cache_type (cache_type)
        ) $charset_collate;";
        
        // Tabela ustawień AI
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            is_serialized tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Wykonaj zapytania dbDelta
        try {
            $results = array();
            $results[] = dbDelta($sql_cache);
            $results[] = dbDelta($sql_logs);
            $results[] = dbDelta($sql_jobs);
            $results[] = dbDelta($sql_stats);
            $results[] = dbDelta($sql_settings);
            
            // Upewnij się, że tabele zostały utworzone
            if (!self::check_tables_exist()) {
                throw new Exception('Nie udało się utworzyć wszystkich tabel.');
            }
            
            // Zapisz wersję schematu
            update_option(self::OPTION_PREFIX . 'db_version', self::DB_VERSION);
            
            // Zapisz datę instalacji, jeśli nie istnieje
            if (!get_option(self::OPTION_PREFIX . 'install_date')) {
                update_option(self::OPTION_PREFIX . 'install_date', current_time('mysql'));
            }
            
            // Inicjalizuj domyślne ustawienia
            self::init_default_settings();
            
            // Zapisz log instalacji
            self::log_installation($results);
            
        } catch (Exception $e) {
            self::log_error('installation_error', $e->getMessage());
            $success = false;
        }
        
        // Oblicz czas wykonania
        $execution_time = microtime(true) - $start_time;
        update_option(self::OPTION_PREFIX . 'install_time', $execution_time);
        
        return $success;
    }
    
    /**
     * Inicjalizuje domyślne ustawienia AI
     */
    private static function init_default_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        
        // Domyślne ustawienia
        $default_settings = array(
            'enabled_models' => array(
                'gpt-3.5-turbo' => true,
                'gpt-4' => true,
                'gpt-4-turbo' => true,
                'claude-3' => false
            ),
            'default_model' => 'gpt-3.5-turbo',
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'cache_enabled' => true,
            'cache_ttl' => 86400, // 24 godziny
            'budget_limit' => 0, // 0 = brak limitu
            'notification_email' => get_option('admin_email'),
            'post_types' => array('post' => true, 'page' => true),
            'enabled_features' => array(
                'meta_title' => true,
                'meta_description' => true,
                'content' => true,
                'schema' => false,
                'excerpt' => true,
                'faq' => false
            ),
            'prompt_templates' => self::get_default_prompts()
        );
        
        // Zapisz ustawienia w tabeli
        foreach ($default_settings as $name => $value) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $settings_table WHERE setting_name = %s",
                    $name
                )
            );
            
            // Jeśli ustawienie już istnieje, nie nadpisuj
            if ($existing) {
                continue;
            }
            
            $is_serialized = is_array($value) || is_object($value);
            $serialized_value = $is_serialized ? serialize($value) : $value;
            
            $wpdb->insert(
                $settings_table,
                array(
                    'setting_name' => $name,
                    'setting_value' => $serialized_value,
                    'is_serialized' => $is_serialized ? 1 : 0
                ),
                array('%s', '%s', '%d')
            );
        }
        
        // Zapisz również w opcjach WordPress dla kompatybilności
        $options = get_option('cleanseo_options', array());
        
        if (!isset($options['openai_settings'])) {
            $options['openai_settings'] = array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'cache_enabled' => true,
                'cache_time' => 86400
            );
            
            update_option('cleanseo_options', $options);
        }
    }
    
    /**
     * Zwraca domyślne szablony promptów
     * 
     * @return array Tablica szablonów promptów
     */
    private static function get_default_prompts() {
        return array(
            'meta_title' => 'Napisz w języku polskim atrakcyjny meta tytuł SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tytuł powinien mieć maksymalnie 60 znaków, zawierać główne słowo kluczowe na początku i zachęcać do kliknięcia.',
            
            'meta_description' => 'Napisz w języku polskim przekonujący meta opis SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Opis powinien mieć maksymalnie 155 znaków, zawierać główne słowo kluczowe i zawierać wezwanie do działania.',
            
            'content' => 'Napisz w języku polskim wysokiej jakości treść na temat "{title}". Słowa kluczowe: {keywords}. Długość: {length} (short = 300 słów, medium = 800 słów, long = 1500 słów). Treść powinna być podzielona nagłówkami, zawierać wstęp, rozwinięcie i podsumowanie. Użyj naturalnego stylu, zaangażowanego tonu i pisz w drugiej osobie. Słowa kluczowe powinny być użyte naturalnie i rozproszone w całej treści.',
            
            'schema' => 'Wygeneruj w języku polskim schemat JSON-LD dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Użyj struktury Article. Zwróć tylko czysty kod JSON bez dodatkowych wyjaśnień.',
            
            'excerpt' => 'Napisz w języku polskim krótki, przyciągający uwagę wyciąg dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Wyciąg powinien mieć około 150-200 znaków i zachęcać do przeczytania całego artykułu.',
            
            'faq' => 'Stwórz w języku polskim sekcję FAQ (5 pytań i odpowiedzi) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Każde pytanie powinno być naturalne i odzwierciedlać rzeczywiste zapytania użytkowników. Każda odpowiedź powinna mieć 2-3 zdania. Zwróć FAQ w formacie HTML z użyciem struktury schema.org.'
        );
    }
    
    /**
     * Sprawdza czy wszystkie wymagane tabele istnieją
     * 
     * @return bool Czy wszystkie tabele istnieją
     */
    private static function check_tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'seo_ai_cache',
            $wpdb->prefix . 'seo_openai_logs',
            $wpdb->prefix . 'seo_ai_jobs',
            $wpdb->prefix . 'seo_ai_stats',
            $wpdb->prefix . 'seo_ai_settings'
        );
        
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Zapisuje log instalacji
     * 
     * @param array $results Wyniki instalacji
     */
    private static function log_installation($results) {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zbierz informacje o systemie
        $system_info = array(
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('CLEANSEO_VERSION') ? CLEANSEO_VERSION : 'unknown',
            'db_version' => self::DB_VERSION,
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'multisite' => is_multisite() ? 'yes' : 'no',
            'results' => $results
        );
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_module_installation',
                'message' => 'Moduł AI został pomyślnie zainstalowany',
                'data' => serialize($system_info),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Zapisuje log błędu
     * 
     * @param string $type Typ błędu
     * @param string $message Treść błędu
     */
    private static function log_error($type, $message) {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            // Jeśli tabela nie istnieje, zapisz do pliku debug.log
            if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                error_log('[CleanSEO AI] ' . $type . ': ' . $message);
            }
            return;
        }
        
        // Zapisz log do bazy danych
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_module_error',
                'message' => $message,
                'data' => serialize(array('type' => $type)),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Usuń tabele i dane podczas deaktywacji
     * 
     * @param bool $keep_data Czy zachować dane
     */
    public static function uninstall($keep_data = false) {
        // Pobierz ustawienia
        $keep_data = get_option(self::OPTION_PREFIX . 'keep_data_on_uninstall', false);
        
        // Jeśli mamy zachować dane, wyjdź
        if ($keep_data) {
            return;
        }
        
        global $wpdb;
        
        // Usuń tabele
        $tables = array(
            'seo_ai_cache',
            'seo_openai_logs',
            'seo_ai_jobs',
            'seo_ai_stats',
            'seo_ai_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        // Usuń opcje
        $options = array(
            'db_version',
            'install_date',
            'install_time',
            'last_update_check',
            'keep_data_on_uninstall'
        );
        
        foreach ($options as $option) {
            delete_option(self::OPTION_PREFIX . $option);
        }
        
        // Usuń metadane postów związane z AI
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_cleanseo_ai_%'");
        
        // Zapisz log deinstalacji
        self::log_uninstallation();
    }
    
    /**
     * Zapisuje log deinstalacji
     */
    private static function log_uninstallation() {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_module_uninstallation',
                'message' => 'Moduł AI został odinstalowany',
                'data' => serialize(array('timestamp' => time())),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Sprawdź czy wymagana jest aktualizacja i wykonaj ją
     * 
     * @return bool Czy wykonano aktualizację
     */
    public static function check_update() {
        $current_version = get_option(self::OPTION_PREFIX . 'db_version', '0');
        
        // Jeśli aktualna wersja jest niższa niż najnowsza, wykonaj aktualizację
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            return self::update($current_version);
        }
        
        return false;
    }
    
    /**
     * Wykonaj aktualizację z określonej wersji
     * 
     * @param string $from_version Wersja, z której aktualizujemy
     * @return bool Czy aktualizacja się powiodła
     */
    private static function update($from_version) {
        global $wpdb;
        $success = true;
        
        // Zapisz początek aktualizacji
        update_option(self::OPTION_PREFIX . 'update_started', current_time('mysql'));
        
        try {
            // Wykonaj aktualizacje stopniowo dla każdej wersji
            if (version_compare($from_version, '1.0', '<')) {
                // Wykonaj aktualizację do wersji 1.0
                self::update_to_1_0();
            }
            
            if (version_compare($from_version, '1.1', '<')) {
                // Wykonaj aktualizację do wersji 1.1
                self::update_to_1_1();
            }
            
            if (version_compare($from_version, '1.2', '<')) {
                // Wykonaj aktualizację do wersji 1.2
                self::update_to_1_2();
            }
            
            // Aktualizacja zakończona sukcesem
            update_option(self::OPTION_PREFIX . 'db_version', self::DB_VERSION);
            update_option(self::OPTION_PREFIX . 'last_update', current_time('mysql'));
            
            // Zapisz log aktualizacji
            self::log_update($from_version, self::DB_VERSION);
            
        } catch (Exception $e) {
            self::log_error('update_error', $e->getMessage());
            $success = false;
        }
        
        // Usuń flagę rozpoczęcia aktualizacji
        delete_option(self::OPTION_PREFIX . 'update_started');
        
        return $success;
    }
    
    /**
     * Aktualizuje do wersji 1.0
     */
    private static function update_to_1_0() {
        // Wykonaj instalację od zera
        self::install();
    }
    
    /**
     * Aktualizuje do wersji 1.1
     */
    private static function update_to_1_1() {
        global $wpdb;
        
        // Aktualizacja struktury tabeli cache
        $cache_table = $wpdb->prefix . 'seo_ai_cache';
        
        // Dodaj nowe kolumny do tabeli cache
        $wpdb->query("ALTER TABLE $cache_table 
            ADD COLUMN cache_type varchar(50) NOT NULL DEFAULT '' AFTER response,
            ADD COLUMN post_id bigint(20) NOT NULL DEFAULT 0 AFTER cache_type,
            ADD COLUMN is_serialized tinyint(1) NOT NULL DEFAULT 0 AFTER post_id,
            ADD COLUMN data_size int(11) NOT NULL DEFAULT 0 AFTER is_serialized,
            ADD COLUMN hits int(11) NOT NULL DEFAULT 0 AFTER data_size");
        
        // Dodaj indeksy
        $wpdb->query("ALTER TABLE $cache_table 
            ADD INDEX cache_type (cache_type),
            ADD INDEX post_id (post_id)");
    }
    
    /**
     * Aktualizuje do wersji 1.2
     */
    private static function update_to_1_2() {
        global $wpdb;
        
        // Dodaj tabelę AI Stats
        $charset_collate = $wpdb->get_charset_collate();
        $stats_table = $wpdb->prefix . 'seo_ai_stats';
        
        $sql_stats = "CREATE TABLE IF NOT EXISTS $stats_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            model varchar(50) NOT NULL,
            cache_type varchar(50) NOT NULL DEFAULT '',
            requests int(11) NOT NULL DEFAULT 0,
            cache_hits int(11) NOT NULL DEFAULT 0,
            tokens int(11) NOT NULL DEFAULT 0,
            cost decimal(10,5) NOT NULL DEFAULT 0.00000,
            PRIMARY KEY  (id),
            UNIQUE KEY date_model_type (date, model, cache_type),
            KEY date (date),
            KEY model (model),
            KEY cache_type (cache_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_stats);
        
        // Dodaj tabelę ustawień AI
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        
        $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            is_serialized tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";
        
        dbDelta($sql_settings);
        
        // Zainicjalizuj domyślne ustawienia
        self::init_default_settings();
    }
    
    /**
     * Zapisuje log aktualizacji
     * 
     * @param string $from_version Wersja, z której aktualizowano
     * @param string $to_version Wersja, do której aktualizowano
     */
    private static function log_update($from_version, $to_version) {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_module_update',
                'message' => "Moduł AI został zaktualizowany z wersji $from_version do $to_version",
                'data' => serialize(array(
                    'from_version' => $from_version,
                    'to_version' => $to_version,
                    'timestamp' => time()
                )),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Sprawdza stan instalacji modułu AI
     * 
     * @return array Stan instalacji
     */
    public static function check_status() {
        global $wpdb;
        
        $status = array(
            'is_installed' => false,
            'version' => get_option(self::OPTION_PREFIX . 'db_version', '0'),
            'install_date' => get_option(self::OPTION_PREFIX . 'install_date', ''),
            'tables_exist' => self::check_tables_exist(),
            'needs_update' => false,
            'update_available' => false,
            'last_update' => get_option(self::OPTION_PREFIX . 'last_update', ''),
            'tables_info' => array()
        );
        
        // Sprawdź czy moduł jest zainstalowany
        $status['is_installed'] = !empty($status['version']) && $status['tables_exist'];
        
        // Sprawdź czy wymagana jest aktualizacja
        $status['needs_update'] = version_compare($status['version'], self::DB_VERSION, '<');
        
        // Sprawdź czy jest dostępna aktualizacja
        $status['update_available'] = version_compare($status['version'], self::DB_VERSION, '<');
        
        // Zbierz informacje o tabelach
        $tables = array(
            $wpdb->prefix . 'seo_ai_cache',
            $wpdb->prefix . 'seo_openai_logs',
            $wpdb->prefix . 'seo_ai_jobs',
            $wpdb->prefix . 'seo_ai_stats',
            $wpdb->prefix . 'seo_ai_settings'
        );
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($table_exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                $status['tables_info'][$table] = array(
                    'exists' => true,
                    'rows' => (int) $row_count
                );
            } else {
                $status['tables_info'][$table] = array(
                    'exists' => false,
                    'rows' => 0
                );
            }
        }
        
        return $status;
    }
    
    /**
     * Dodaje tablice do istniejącej instalacji bez usuwania danych
     * 
     * @return bool Czy operacja się powiodła
     */
    public static function add_missing_tables() {
        $status = self::check_status();
        
        if (!$status['is_installed']) {
            return self::install();
        }
        
        // Dodaj tylko brakujące tabele
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $success = true;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($status['tables_info'] as $table => $info) {
            if (!$info['exists']) {
                // Utwórz brakującą tabelę
                $table_name = basename($table);
                
                switch ($table_name) {
                    case 'seo_ai_cache':
                        $sql = "CREATE TABLE IF NOT EXISTS $table (
                            id bigint(20) NOT NULL AUTO_INCREMENT,
                            hash_key varchar(64) NOT NULL,
                            prompt text NOT NULL,
                            model varchar(50) NOT NULL,
                            response longtext NOT NULL,
                            cache_type varchar(50) NOT NULL DEFAULT '',
                            post_id bigint(20) NOT NULL DEFAULT 0,
                            is_serialized tinyint(1) NOT NULL DEFAULT 0,
                            data_size int(11) NOT NULL DEFAULT 0,
                            hits int(11) NOT NULL DEFAULT 0,
                            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            expires_at datetime NOT NULL,
                            PRIMARY KEY  (id),
                            UNIQUE KEY hash_key (hash_key),
                            KEY model (model),
                            KEY cache_type (cache_type),
                            KEY post_id (post_id),
                            KEY expires_at (expires_at),
                            KEY created_at (created_at)
                        ) $charset_collate;";
                        break;
                        
                    case 'seo_openai_logs':
                        $sql = "CREATE TABLE IF NOT EXISTS $table (
                            id bigint(20) NOT NULL AUTO_INCREMENT,
                            user_id bigint(20) NOT NULL,
                            post_id bigint(20) NOT NULL DEFAULT 0,
                            model varchar(50) NOT NULL,
                            prompt text NOT NULL,
                            response longtext NOT NULL,
                            cache_hit tinyint(1) NOT NULL DEFAULT 0,
                            tokens int(11) NOT NULL DEFAULT 0,
                            cost decimal(10,5) NOT NULL DEFAULT 0.00000,
                            duration decimal(10,3) NOT NULL DEFAULT 0.000,
                            status varchar(20) NOT NULL DEFAULT 'success',
                            error_message text,
                            ip_address varchar(45) DEFAULT NULL,
                            user_agent varchar(255) DEFAULT NULL,
                            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY  (id),
                            KEY user_id (user_id),
                            KEY post_id (post_id),
                            KEY model (model),
                            KEY status (status),
                            KEY created_at (created_at),
                            KEY cache_hit (cache_hit)
                        ) $charset_collate;";
                        break;
                        
                    case 'seo_ai_jobs':
                        $sql = "CREATE TABLE IF NOT EXISTS $table (
                            id bigint(20) NOT NULL AUTO_INCREMENT,
                            type varchar(50) NOT NULL,
                            status varchar(20) NOT NULL DEFAULT 'pending',
                            data longtext NOT NULL,
                            result longtext DEFAULT NULL,
                            priority int(11) NOT NULL DEFAULT 10,
                            attempts int(11) NOT NULL DEFAULT 0,
                            max_attempts int(11) NOT NULL DEFAULT 3,
                            error_message text DEFAULT NULL,
                            scheduled_at datetime DEFAULT NULL,
                            started_at datetime DEFAULT NULL,
                            completed_at datetime DEFAULT NULL,
                            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY  (id),
                            KEY type (type),
                            KEY status (status),
                            KEY priority (priority),
                            KEY scheduled_at (scheduled_at),
                            KEY created_at (created_at)
                        ) $charset_collate;";
                        break;
                        
                    case 'seo_ai_stats':
                        $sql = "CREATE TABLE IF NOT EXISTS $table (
                            id bigint(20) NOT NULL AUTO_INCREMENT,
                            date date NOT NULL,
                            model varchar(50) NOT NULL,
                            cache_type varchar(50) NOT NULL DEFAULT '',
                            requests int(11) NOT NULL DEFAULT 0,
                            cache_hits int(11) NOT NULL DEFAULT 0,
                            tokens int(11) NOT NULL DEFAULT 0,
                            cost decimal(10,5) NOT NULL DEFAULT 0.00000,
                            PRIMARY KEY  (id),
                            UNIQUE KEY date_model_type (date, model, cache_type),
                            KEY date (date),
                            KEY model (model),
                            KEY cache_type (cache_type)
                        ) $charset_collate;";
                        break;
                        
                    case 'seo_ai_settings':
                        $sql = "CREATE TABLE IF NOT EXISTS $table (
                            id bigint(20) NOT NULL AUTO_INCREMENT,
                            setting_name varchar(100) NOT NULL,
                            setting_value longtext NOT NULL,
                            is_serialized tinyint(1) NOT NULL DEFAULT 0,
                            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY  (id),
                            UNIQUE KEY setting_name (setting_name)
                        ) $charset_collate;";
                        break;
                        
                    default:
                        $sql = '';
                        break;
                }
                
                if (!empty($sql)) {
                    $result = dbDelta($sql);
                    if (empty($result)) {
                        $success = false;
                        self::log_error('add_missing_tables', "Nie udało się utworzyć tabeli: $table");
                    }
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Naprawia uszkodzone tabele
     * 
     * @return bool Czy operacja się powiodła
     */
    public static function repair_tables() {
        global $wpdb;
        $success = true;
        
        // Tabele do naprawy
        $tables = array(
            $wpdb->prefix . 'seo_ai_cache',
            $wpdb->prefix . 'seo_openai_logs',
            $wpdb->prefix . 'seo_ai_jobs',
            $wpdb->prefix . 'seo_ai_stats',
            $wpdb->prefix . 'seo_ai_settings'
        );
        
        // Przetwórz każdą tabelę
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($table_exists) {
                // Sprawdź i napraw tabelę
                $result = $wpdb->query("REPAIR TABLE $table");
                if ($result === false) {
                    $success = false;
                    self::log_error('repair_tables', "Nie udało się naprawić tabeli: $table");
                }
                
                // Optymalizuj tabelę
                $wpdb->query("OPTIMIZE TABLE $table");
            }
        }
        
        return $success;
    }
    
    /**
     * Wykonuje czyszczenie starych danych
     * 
     * @param int $days_old Liczba dni, po których dane są uznawane za stare
     * @return array Statystyki czyszczenia
     */
    public static function cleanup_old_data($days_old = 30) {
        global $wpdb;
        $stats = array(
            'cache_deleted' => 0,
            'logs_deleted' => 0,
            'jobs_deleted' => 0
        );
        
        // Data graniczna
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_old days"));
        
        // Wyczyść stary cache
        $cache_table = $wpdb->prefix . 'seo_ai_cache';
        if ($wpdb->get_var("SHOW TABLES LIKE '$cache_table'") === $cache_table) {
            $stats['cache_deleted'] = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $cache_table WHERE created_at < %s",
                    $cutoff_date
                )
            );
        }
        
        // Wyczyść stare logi
        $logs_table = $wpdb->prefix . 'seo_openai_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table) {
            $stats['logs_deleted'] = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $logs_table WHERE created_at < %s",
                    $cutoff_date
                )
            );
        }
        
        // Wyczyść stare zadania
        $jobs_table = $wpdb->prefix . 'seo_ai_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$jobs_table'") === $jobs_table) {
            $stats['jobs_deleted'] = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $jobs_table WHERE (status IN ('completed', 'failed') AND created_at < %s)",
                    $cutoff_date
                )
            );
        }
        
        // Zapisz log czyszczenia
        self::log_cleanup($stats);
        
        return $stats;
    }
    
    /**
     * Zapisuje log czyszczenia danych
     * 
     * @param array $stats Statystyki czyszczenia
     */
    private static function log_cleanup($stats) {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_module_cleanup',
                'message' => "Wyczyszczono stare dane modułu AI",
                'data' => serialize($stats),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Eksportuje ustawienia AI do pliku
     * 
     * @return string|WP_Error Ścieżka do pliku eksportu lub błąd
     */
    public static function export_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        
        // Sprawdź czy tabela ustawień istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") !== $settings_table) {
            return new WP_Error('export_failed', 'Tabela ustawień AI nie istnieje.');
        }
        
        // Pobierz wszystkie ustawienia
        $settings = $wpdb->get_results("SELECT setting_name, setting_value, is_serialized FROM $settings_table");
        
        if (empty($settings)) {
            return new WP_Error('export_failed', 'Brak ustawień do eksportu.');
        }
        
        // Konwertuj na tablicę
        $export_data = array();
        foreach ($settings as $setting) {
            $value = $setting->setting_value;
            if ($setting->is_serialized) {
                $value = maybe_unserialize($value);
            }
            $export_data[$setting->setting_name] = $value;
        }
        
        // Dodaj metadane eksportu
        $export_data['_export_meta'] = array(
            'version' => self::DB_VERSION,
            'date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('CLEANSEO_VERSION') ? CLEANSEO_VERSION : 'unknown'
        );
        
        // Utwórz katalog eksportu jeśli nie istnieje
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/cleanseo-exports';
        
        if (!file_exists($export_dir)) {
            if (!wp_mkdir_p($export_dir)) {
                return new WP_Error('export_failed', 'Nie można utworzyć katalogu eksportu.');
            }
            
            // Dodaj plik index.php dla bezpieczeństwa
            $index_file = $export_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
            
            // Dodaj plik .htaccess dla bezpieczeństwa
            $htaccess_file = $export_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "Order Deny,Allow\nDeny from all");
            }
        }
        
        // Utwórz nazwę pliku
        $file_name = 'cleanseo-ai-settings-' . date('Y-m-d-H-i-s') . '.json';
        $file_path = $export_dir . '/' . $file_name;
        
        // Zapisz dane do pliku
        $json_data = json_encode($export_data, JSON_PRETTY_PRINT);
        if (file_put_contents($file_path, $json_data) === false) {
            return new WP_Error('export_failed', 'Nie można zapisać pliku eksportu.');
        }
        
        return $file_path;
    }
    
    /**
     * Importuje ustawienia AI z pliku
     * 
     * @param string $file_path Ścieżka do pliku importu
     * @return bool|WP_Error Wynik importu
     */
    public static function import_settings($file_path) {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        
        // Sprawdź czy tabela ustawień istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") !== $settings_table) {
            return new WP_Error('import_failed', 'Tabela ustawień AI nie istnieje.');
        }
        
        // Sprawdź czy plik istnieje
        if (!file_exists($file_path)) {
            return new WP_Error('import_failed', 'Plik importu nie istnieje.');
        }
        
        // Wczytaj plik
        $json_data = file_get_contents($file_path);
        if ($json_data === false) {
            return new WP_Error('import_failed', 'Nie można odczytać pliku importu.');
        }
        
        // Zdekoduj dane JSON
        $import_data = json_decode($json_data, true);
        if ($import_data === null) {
            return new WP_Error('import_failed', 'Nieprawidłowy format pliku importu.');
        }
        
        // Sprawdź metadane eksportu
        if (!isset($import_data['_export_meta']) || !isset($import_data['_export_meta']['version'])) {
            return new WP_Error('import_failed', 'Brak metadanych w pliku importu.');
        }
        
        // Usuń metadane z danych do importu
        unset($import_data['_export_meta']);
        
        // Importuj ustawienia
        foreach ($import_data as $name => $value) {
            $is_serialized = is_array($value) || is_object($value);
            $serialized_value = $is_serialized ? serialize($value) : $value;
            
            // Sprawdź czy ustawienie już istnieje
            $existing_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $settings_table WHERE setting_name = %s",
                    $name
                )
            );
            
            if ($existing_id) {
                // Aktualizuj istniejące ustawienie
                $wpdb->update(
                    $settings_table,
                    array(
                        'setting_value' => $serialized_value,
                        'is_serialized' => $is_serialized ? 1 : 0,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing_id),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
            } else {
                // Dodaj nowe ustawienie
                $wpdb->insert(
                    $settings_table,
                    array(
                        'setting_name' => $name,
                        'setting_value' => $serialized_value,
                        'is_serialized' => $is_serialized ? 1 : 0,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%d', '%s', '%s')
                );
            }
        }
        
        // Zapisz log importu
        self::log_import($file_path, count($import_data));
        
        return true;
    }
    
    /**
     * Zapisuje log importu ustawień
     * 
     * @param string $file_path Ścieżka do pliku importu
     * @param int $count Liczba zaimportowanych ustawień
     */
    private static function log_import($file_path, $count) {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_settings_import',
                'message' => "Zaimportowano $count ustawień AI",
                'data' => serialize(array(
                    'file' => basename($file_path),
                    'count' => $count,
                    'timestamp' => time()
                )),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Pobiera informacje o wykorzystaniu bazy danych
     * 
     * @return array Informacje o wykorzystaniu bazy danych
     */
    public static function get_database_usage() {
        global $wpdb;
        $usage = array();
        
        // Tabele do analizy
        $tables = array(
            $wpdb->prefix . 'seo_ai_cache',
            $wpdb->prefix . 'seo_openai_logs',
            $wpdb->prefix . 'seo_ai_jobs',
            $wpdb->prefix . 'seo_ai_stats',
            $wpdb->prefix . 'seo_ai_settings'
        );
        
        $total_size = 0;
        $total_rows = 0;
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($table_exists) {
                // Pobierz informacje o tabeli
                $table_status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
                
                if ($table_status) {
                    $size = $table_status->Data_length + $table_status->Index_length;
                    $rows = $table_status->Rows;
                    
                    $usage[$table] = array(
                        'rows' => (int) $rows,
                        'size' => (int) $size,
                        'size_formatted' => self::format_size($size),
                        'avg_row_size' => $rows > 0 ? self::format_size($size / $rows) : '0 B',
                        'last_update' => $table_status->Update_time
                    );
                    
                    $total_size += $size;
                    $total_rows += $rows;
                }
            }
        }
        
        // Dodaj podsumowanie
        $usage['total'] = array(
            'rows' => $total_rows,
            'size' => $total_size,
            'size_formatted' => self::format_size($total_size)
        );
        
        return $usage;
    }
    
    /**
     * Formatuje rozmiar w bajtach na czytelną wartość
     * 
     * @param int $size Rozmiar w bajtach
     * @return string Sformatowany rozmiar
     */
    private static function format_size($size) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Resetuje ustawienia AI do wartości domyślnych
     * 
     * @return bool Czy reset się powiódł
     */
    public static function reset_settings() {
        global $wpdb;
        $settings_table = $wpdb->prefix . 'seo_ai_settings';
        
        // Sprawdź czy tabela ustawień istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") !== $settings_table) {
            return false;
        }
        
        // Wyczyść tabelę ustawień
        $wpdb->query("TRUNCATE TABLE $settings_table");
        
        // Zainicjalizuj domyślne ustawienia
        self::init_default_settings();
        
        // Zapisz log resetu
        self::log_reset();
        
        return true;
    }
    
    /**
     * Zapisuje log resetu ustawień
     */
    private static function log_reset() {
        // Sprawdź czy tabela logów istnieje
        global $wpdb;
        $logs_table = $wpdb->prefix . 'seo_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
            return;
        }
        
        // Zapisz log
        $wpdb->insert(
            $logs_table,
            array(
                'action' => 'ai_settings_reset',
                'message' => "Zresetowano ustawienia AI do wartości domyślnych",
                'data' => serialize(array('timestamp' => time())),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
}