<?php
/**
 * Klasa obsługująca bazę danych
 */
class CleanSEO_Database {
    /**
     * Wersja bazy danych
     */
    private $db_version = '1.0.0';
    
    /**
     * Prefiksy tabel
     */
    private $tables = array(
        'settings' => 'seo_settings',
        'redirects' => 'seo_redirects',
        'competitors' => 'seo_competitors',
        'locations' => 'seo_locations',
        'audits' => 'seo_audits',
        'logs' => 'seo_logs',
        'ai_cache' => 'seo_ai_cache',
        'openai_logs' => 'seo_openai_logs'
    );
    
    /**
     * Konstruktor
     */
    public function __construct() {
        register_activation_hook(CLEANSEO_PLUGIN_DIR . 'cleanseo-optimizer.php', array($this, 'install'));
    }
    
    /**
     * Instaluje bazę danych
     */
    public function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela ustawień
        $table_settings = $wpdb->prefix . $this->tables['settings'];
        $sql_settings = "CREATE TABLE $table_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            meta_title varchar(255) NOT NULL,
            meta_description text NOT NULL,
            og_image_url varchar(255) NOT NULL,
            sitemap_enabled tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabela przekierowań
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        $sql_redirects = "CREATE TABLE $table_redirects (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source_url varchar(255) NOT NULL,
            target_url varchar(255) NOT NULL,
            status_code smallint(3) NOT NULL,
            hits int(11) NOT NULL DEFAULT 0,
            last_accessed datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY source_url (source_url)
        ) $charset_collate;";
        
        // Tabela konkurentów
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        $sql_competitors = "CREATE TABLE $table_competitors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            keywords text NOT NULL,
            last_check datetime DEFAULT NULL,
            rankings text,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabela lokalizacji
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        $sql_locations = "CREATE TABLE $table_locations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            street varchar(255) NOT NULL,
            city varchar(255) NOT NULL,
            postal_code varchar(20) NOT NULL,
            country varchar(100) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            google_place_id varchar(255) DEFAULT NULL,
            google_place_url varchar(255) DEFAULT NULL,
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            opening_hours text,
            services text,
            payment_methods text,
            price_range varchar(10) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabela audytów
        $table_audits = $wpdb->prefix . $this->tables['audits'];
        $sql_audits = "CREATE TABLE $table_audits (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            score tinyint(3) NOT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            results longtext NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabela logów
        $table_logs = $wpdb->prefix . $this->tables['logs'];
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level varchar(10) NOT NULL,
            message text NOT NULL,
            context text,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Tabela cache AI
        $table_ai_cache = $wpdb->prefix . $this->tables['ai_cache'];
        $sql_ai_cache = "CREATE TABLE $table_ai_cache (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            model varchar(50) NOT NULL,
            cache_type varchar(50) NOT NULL,
            expires int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key (cache_key)
        ) $charset_collate;";
        
        // Tabela logów OpenAI
        $table_openai_logs = $wpdb->prefix . $this->tables['openai_logs'];
        $sql_openai_logs = "CREATE TABLE $table_openai_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL,
            model varchar(50) NOT NULL,
            type varchar(50) NOT NULL,
            tokens_used int(11) NOT NULL DEFAULT 0,
            processing_time float NOT NULL DEFAULT 0,
            prompt text,
            response text,
            cost decimal(10,6) NOT NULL DEFAULT 0,
            error_message text,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Tworzenie tabel
        dbDelta($sql_settings);
        dbDelta($sql_redirects);
        dbDelta($sql_competitors);
        dbDelta($sql_locations);
        dbDelta($sql_audits);
        dbDelta($sql_logs);
        dbDelta($sql_ai_cache);
        dbDelta($sql_openai_logs);
        
        // Dodaj domyślne ustawienia, jeśli jeszcze nie istnieją
        $this->add_default_settings();
        
        // Zapisz wersję bazy danych
        update_option('cleanseo_db_version', $this->db_version);
    }
    
    /**
     * Dodaje domyślne ustawienia
     */
    private function add_default_settings() {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . $this->tables['settings'];
        
        // Sprawdź, czy istnieją już ustawienia
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_settings");
        
        if ($count == 0) {
            // Domyślne ustawienia
            $wpdb->insert(
                $table_settings,
                array(
                    'meta_title' => get_bloginfo('name'),
                    'meta_description' => get_bloginfo('description'),
                    'og_image_url' => '',
                    'sitemap_enabled' => 1
                ),
                array('%s', '%s', '%s', '%d')
            );
        }
    }
    
    /**
     * Pobiera ustawienia
     */
    public function get_settings() {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . $this->tables['settings'];
        
        $settings = $wpdb->get_row("SELECT * FROM $table_settings WHERE id = 1");
        
        if (!$settings) {
            $this->add_default_settings();
            $settings = $wpdb->get_row("SELECT * FROM $table_settings WHERE id = 1");
        }
        
        return $settings;
    }
    
    /**
     * Aktualizuje ustawienia
     */
    public function update_settings($data) {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . $this->tables['settings'];
        
        $result = $wpdb->update(
            $table_settings,
            $data,
            array('id' => 1)
        );
        
        return $result !== false;
    }
    
    /**
     * Pobiera przekierowania
     */
    public function get_redirects() {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->get_results("SELECT * FROM $table_redirects ORDER BY id DESC");
    }
    
    /**
     * Dodaje przekierowanie
     */
    public function add_redirect($source_url, $target_url, $status_code) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        $result = $wpdb->insert(
            $table_redirects,
            array(
                'source_url' => $source_url,
                'target_url' => $target_url,
                'status_code' => $status_code,
                'hits' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Usuwa przekierowanie
     */
    public function delete_redirect($id) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->delete(
            $table_redirects,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Zwiększa licznik odwiedzin przekierowania
     */
    public function increment_redirect_hit($id) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table_redirects SET hits = hits + 1, last_accessed = %s WHERE id = %d",
            current_time('mysql'),
            $id
        ));
    }
    
    /**
     * Pobiera przekierowanie po URL źródłowym
     */
    public function get_redirect_by_source($source_url) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_redirects WHERE source_url = %s LIMIT 1",
            $source_url
        ));
    }
    
    /**
     * Pobiera przekierowanie po ID
     */
    public function get_redirect($id) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_redirects WHERE id = %d LIMIT 1",
            $id
        ));
    }
    
    /**
     * Aktualizuje przekierowanie
     */
    public function update_redirect($id, $data) {
        global $wpdb;
        
        $table_redirects = $wpdb->prefix . $this->tables['redirects'];
        
        return $wpdb->update(
            $table_redirects,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Pobiera konkurentów
     */
    public function get_competitors() {
        global $wpdb;
        
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        
        return $wpdb->get_results("SELECT * FROM $table_competitors ORDER BY id DESC");
    }
    
    /**
     * Dodaje konkurenta
     */
    public function add_competitor($domain, $keywords) {
        global $wpdb;
        
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        
        // Usuń protokół i www z domeny
        $domain = preg_replace('/(https?:\/\/)?(www\.)?/', '', $domain);
        
        // Sprawdź, czy konkurent już istnieje
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_competitors WHERE domain = %s",
            $domain
        ));
        
        if ($exists) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_competitors,
            array(
                'domain' => $domain,
                'keywords' => is_array($keywords) ? implode(',', $keywords) : $keywords,
                'last_check' => null,
                'rankings' => null
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Usuwa konkurenta
     */
    public function delete_competitor($id) {
        global $wpdb;
        
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        
        return $wpdb->delete(
            $table_competitors,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Pobiera konkurenta po ID
     */
    public function get_competitor($id) {
        global $wpdb;
        
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_competitors WHERE id = %d LIMIT 1",
            $id
        ));
    }
    
    /**
     * Aktualizuje ranking konkurenta
     */
    public function update_competitor_rankings($id, $rankings) {
        global $wpdb;
        
        $table_competitors = $wpdb->prefix . $this->tables['competitors'];
        
        return $wpdb->update(
            $table_competitors,
            array(
                'rankings' => json_encode($rankings),
                'last_check' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Pobiera lokalizacje
     */
    public function get_locations() {
        global $wpdb;
        
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        
        return $wpdb->get_results("SELECT * FROM $table_locations ORDER BY id DESC");
    }
    
    /**
     * Dodaje lokalizację
     */
    public function add_location($data) {
        global $wpdb;
        
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        
        // Przetwórz tablicowe dane do JSON
        if (isset($data['opening_hours']) && is_array($data['opening_hours'])) {
            $data['opening_hours'] = json_encode($data['opening_hours']);
        }
        
        if (isset($data['services']) && is_array($data['services'])) {
            $data['services'] = json_encode($data['services']);
        }
        
        if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
            $data['payment_methods'] = json_encode($data['payment_methods']);
        }
        
        $result = $wpdb->insert($table_locations, $data);
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Usuwa lokalizację
     */
    public function delete_location($id) {
        global $wpdb;
        
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        
        return $wpdb->delete(
            $table_locations,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Pobiera lokalizację po ID
     */
    public function get_location($id) {
        global $wpdb;
        
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        
        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_locations WHERE id = %d LIMIT 1",
            $id
        ));
        
        if ($location) {
            // Dekoduj JSON
            if (!empty($location->opening_hours)) {
                $location->opening_hours = json_decode($location->opening_hours, true);
            }
            
            if (!empty($location->services)) {
                $location->services = json_decode($location->services, true);
            }
            
            if (!empty($location->payment_methods)) {
                $location->payment_methods = json_decode($location->payment_methods, true);
            }
        }
        
        return $location;
    }
    
    /**
     * Aktualizuje lokalizację
     */
    public function update_location($id, $data) {
        global $wpdb;
        
        $table_locations = $wpdb->prefix . $this->tables['locations'];
        
        // Przetwórz tablicowe dane do JSON
        if (isset($data['opening_hours']) && is_array($data['opening_hours'])) {
            $data['opening_hours'] = json_encode($data['opening_hours']);
        }
        
        if (isset($data['services']) && is_array($data['services'])) {
            $data['services'] = json_encode($data['services']);
        }
        
        if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
            $data['payment_methods'] = json_encode($data['payment_methods']);
        }
        
        return $wpdb->update(
            $table_locations,
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Dodaje audyt
     */
    public function add_audit($score, $results) {
        global $wpdb;
        
        $table_audits = $wpdb->prefix . $this->tables['audits'];
        
        $result = $wpdb->insert(
            $table_audits,
            array(
                'score' => $score,
                'timestamp' => current_time('mysql'),
                'results' => json_encode($results)
            ),
            array('%d', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Pobiera audyt po ID
     */
    public function get_audit($id) {
        global $wpdb;
        
        $table_audits = $wpdb->prefix . $this->tables['audits'];
        
        $audit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_audits WHERE id = %d LIMIT 1",
            $id
        ));
        
        if ($audit && !empty($audit->results)) {
            $audit->results = json_decode($audit->results, true);
        }
        
        return $audit;
    }
    
    /**
     * Pobiera ostatnie audyty
     */
    public function get_last_audits($limit = 5) {
        global $wpdb;
        
        $table_audits = $wpdb->prefix . $this->tables['audits'];
        
        $audits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_audits ORDER BY timestamp DESC LIMIT %d",
            $limit
        ));
        
        return $audits;
    }
    
    /**
     * Dodaje log
     */
    public function add_log($level, $message, $context = array()) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . $this->tables['logs'];
        
        $result = $wpdb->insert(
            $table_logs,
            array(
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? json_encode($context) : null
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Pobiera logi
     */
    public function get_logs($limit = 100, $offset = 0, $level = null) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . $this->tables['logs'];
        
        $sql = "SELECT * FROM $table_logs";
        $sql_params = array();
        
        if ($level) {
            $sql .= " WHERE level = %s";
            $sql_params[] = $level;
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $sql_params[] = $limit;
        $sql_params[] = $offset;
        
        $logs = $wpdb->get_results($wpdb->prepare($sql, $sql_params));
        
        // Dekoduj JSON w kontekście
        foreach ($logs as &$log) {
            if (!empty($log->context)) {
                $log->context = json_decode($log->context, true);
            }
        }
        
        return $logs;
    }
    
    /**
     * Czyści stare logi
     */
    public function clear_old_logs($days = 30) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . $this->tables['logs'];
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Wykonuje czyszczenie bazy danych
     */
    public function cleanup() {
        // Wyczyść stare logi
        $this->clear_old_logs();
        
        // Wyczyść wygasłe wpisy cache
        global $cleanseo_ai_cache;
        if ($cleanseo_ai_cache) {
            $cleanseo_ai_cache->clear_expired();
        }
    }
}