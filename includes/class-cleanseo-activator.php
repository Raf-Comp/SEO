<?php
/**
 * Klasa odpowiedzialna za aktywację i deaktywację pluginu
 */
class CleanSEO_Activator {
    private $wpdb;
    private $tables;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = array(
            'settings' => $wpdb->prefix . 'seo_settings',
            'redirects' => $wpdb->prefix . 'seo_redirects',
            'competitors' => $wpdb->prefix . 'seo_competitors',
            'logs' => $wpdb->prefix . 'seo_logs',
            'audits' => $wpdb->prefix . 'seo_audits',
            'locations' => $wpdb->prefix . 'seo_locations',
            'stats' => $wpdb->prefix . 'seo_stats',
            'analytics' => $wpdb->prefix . 'seo_analytics',
            'openai_logs' => $wpdb->prefix . 'seo_openai_logs',
            'ai_cache' => $wpdb->prefix . 'seo_ai_cache'
        );
    }

    /**
     * Instalacja pluginu (statyczna wersja)
     */
    public static function install() {
        $instance = new self();
        return $instance->activate();
    }

    /**
     * Instalacja pluginu
     */
    public function activate() {
        $this->create_tables();
        $this->create_options();
        $this->create_capabilities();
        $this->create_directories();
        $this->schedule_events();
        
        // Zapisz log aktywacji
        $this->log_activation();
        
        // Dodaj flush rewrite rules dla właściwego działania permalinków
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabela settings
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['settings']} (
            id INT NOT NULL AUTO_INCREMENT,
            sitemap_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sitemap_include_images TINYINT(1) NOT NULL DEFAULT 1,
            sitemap_include_video TINYINT(1) NOT NULL DEFAULT 1,
            sitemap_include_news TINYINT(1) NOT NULL DEFAULT 1,
            robots_txt TEXT NULL,
            openai_api_key VARCHAR(255) NULL,
            google_api_key VARCHAR(255) NULL,
            semrush_api_key VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela redirects
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['redirects']} (
            id INT NOT NULL AUTO_INCREMENT,
            source_url VARCHAR(255) NOT NULL,
            target_url VARCHAR(255) NOT NULL,
            redirect_type INT NOT NULL DEFAULT 301,
            is_regex TINYINT(1) NOT NULL DEFAULT 0,
            preserve_query TINYINT(1) NOT NULL DEFAULT 0,
            hits INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela competitors
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['competitors']} (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            url VARCHAR(255) NOT NULL,
            keywords TEXT NULL,
            serp_position INT NULL,
            domain_authority INT NULL,
            backlinks INT NULL,
            last_check DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela logs
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['logs']} (
            id INT NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NULL,
            action VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            data LONGTEXT NULL,
            ip VARCHAR(45) NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY timestamp (timestamp)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela audits
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['audits']} (
            id INT NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) NOT NULL,
            score INT NOT NULL DEFAULT 0,
            issues LONGTEXT NULL,
            recommendations LONGTEXT NULL,
            meta_score INT NULL,
            content_score INT NULL,
            seo_score INT NULL,
            performance_score INT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY score (score)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela locations
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['locations']} (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            street VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            zip VARCHAR(20) NOT NULL,
            country VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(100) NULL,
            website VARCHAR(255) NULL,
            business_hours TEXT NULL,
            lat DECIMAL(10,8) NULL,
            lng DECIMAL(11,8) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela stats
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['stats']} (
            id INT NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            pageviews INT NOT NULL DEFAULT 0,
            unique_visitors INT NOT NULL DEFAULT 0,
            bounce_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            avg_time_on_site INT NOT NULL DEFAULT 0,
            top_pages LONGTEXT NULL,
            top_referrers LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela analytics
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['analytics']} (
            id INT NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            source VARCHAR(50) NULL,
            medium VARCHAR(50) NULL,
            campaign VARCHAR(100) NULL,
            sessions INT NOT NULL DEFAULT 0,
            users INT NOT NULL DEFAULT 0,
            pageviews INT NOT NULL DEFAULT 0,
            bounce_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            avg_session_duration INT NOT NULL DEFAULT 0,
            conversion_rate DECIMAL(5,2) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date (date),
            KEY source (source),
            KEY medium (medium)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela openai_logs
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['openai_logs']} (
            id INT NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            model VARCHAR(50) NOT NULL,
            prompt TEXT NOT NULL,
            response LONGTEXT NOT NULL,
            tokens INT NULL,
            cost DECIMAL(10,5) NULL,
            duration DECIMAL(10,3) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY model (model),
            KEY created_at (created_at)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);

        // Tabela ai_cache
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['ai_cache']} (
            id INT NOT NULL AUTO_INCREMENT,
            hash_key VARCHAR(64) NOT NULL,
            model VARCHAR(50) NOT NULL,
            prompt TEXT NOT NULL,
            response LONGTEXT NOT NULL,
            tokens INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY hash_key (hash_key),
            KEY model (model),
            KEY expires_at (expires_at)
        ) " . $this->wpdb->get_charset_collate();
        dbDelta($sql);
    }
    
    private function create_options() {
        if (!get_option('cleanseo_version')) {
            add_option('cleanseo_version', CLEANSEO_VERSION);
        }
        
        if (!get_option('cleanseo_db_version')) {
            add_option('cleanseo_db_version', CLEANSEO_DB_VERSION);
        }
        
        if (!get_option('cleanseo_install_date')) {
            add_option('cleanseo_install_date', current_time('mysql'));
        }
        
        // Dodaj domyślne opcje jeśli nie istnieją
        if (!get_option('cleanseo_options')) {
            add_option('cleanseo_options', array(
                'sitemap_enabled' => 1,
                'sitemap_include_images' => 1,
                'sitemap_include_video' => 1,
                'sitemap_include_news' => 1,
                'sitemap_exclude_post_types' => array('revision', 'nav_menu_item', 'attachment'),
                'robots_txt' => "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /wp-includes/\nDisallow: /wp-content/plugins/\nDisallow: /wp-login.php",
                'openai_settings' => array(
                    'model' => 'gpt-4',
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                    'cache_enabled' => 1,
                    'cache_time' => 86400, // 24 godziny
                ),
                'auto_analyze_posts' => 1,
                'email_reports' => 0,
                'email_recipients' => get_option('admin_email'),
                'report_frequency' => 'weekly'
            ));
        }
    }
    
    private function create_capabilities() {
        // Dodaj uprawnienia dla administratora
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_cleanseo');
            $role->add_cap('view_cleanseo_reports');
            $role->add_cap('manage_cleanseo_redirects');
            $role->add_cap('manage_cleanseo_competitors');
            $role->add_cap('manage_cleanseo_audits');
            $role->add_cap('manage_cleanseo_locations');
            $role->add_cap('manage_cleanseo_settings');
            $role->add_cap('use_cleanseo_ai_tools');
        }
        
        // Dodaj uprawnienia dla edytora
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('view_cleanseo_reports');
            $editor_role->add_cap('manage_cleanseo_audits');
            $editor_role->add_cap('use_cleanseo_ai_tools');
        }
    }
    
    private function create_directories() {
        // Utwórz katalog cache
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/cleanseo-cache';
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
            // Zabezpiecz katalog przed bezpośrednim dostępem
            $this->create_protection_file($cache_dir);
        }

        // Utwórz katalog logów
        $log_dir = WP_CONTENT_DIR . '/cleanseo-logs';
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
            // Zabezpiecz katalog przed bezpośrednim dostępem
            $this->create_protection_file($log_dir);
        }
        
        // Utwórz katalog eksportów
        $export_dir = $upload_dir['basedir'] . '/cleanseo-exports';
        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
            $this->create_protection_file($export_dir);
        }
    }
    
    /**
     * Tworzy plik .htaccess dla ochrony katalogu
     */
    private function create_protection_file($dir) {
        $htaccess_file = $dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Zabezpieczenie katalogu\n";
            $htaccess_content .= "<Files ~ \".*\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            @file_put_contents($htaccess_file, $htaccess_content);
        }
        
        $index_file = $dir . '/index.php';
        if (!file_exists($index_file)) {
            $index_content = "<?php\n// Silence is golden.";
            @file_put_contents($index_file, $index_content);
        }
    }
    
    private function schedule_events() {
        // Zaplanuj codzienne czyszczenie cache
        if (!wp_next_scheduled('cleanseo_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'cleanseo_daily_cleanup');
        }
        
        // Zaplanuj tygodniowy raport
        if (!wp_next_scheduled('cleanseo_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'cleanseo_weekly_report');
        }
        
        // Zaplanuj miesięczny raport
        if (!wp_next_scheduled('cleanseo_monthly_report')) {
            wp_schedule_event(time(), 'monthly', 'cleanseo_monthly_report');
        }
        
        // Zaplanuj codzienne sprawdzanie pozycji
        if (!wp_next_scheduled('cleanseo_daily_rank_check')) {
            wp_schedule_event(time(), 'daily', 'cleanseo_daily_rank_check');
        }
        
        // Zaplanuj cotygodniowe sprawdzanie błędów 404
        if (!wp_next_scheduled('cleanseo_weekly_404_check')) {
            wp_schedule_event(time(), 'weekly', 'cleanseo_weekly_404_check');
        }
    }
    
    /**
     * Zapisuje log aktywacji pluginu
     */
    private function log_activation() {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID > 0 ? $current_user->ID : 0;
        $user_login = $current_user->user_login ? $current_user->user_login : 'System';
        
        $data = array(
            'version' => CLEANSEO_VERSION,
            'db_version' => CLEANSEO_DB_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'server' => $_SERVER['SERVER_SOFTWARE'],
            'multisite' => is_multisite() ? 'Tak' : 'Nie',
            'active_plugins' => get_option('active_plugins')
        );
        
        // Zapisz log w bazie danych
        $this->wpdb->insert(
            $this->tables['logs'],
            array(
                'user_id' => $user_id,
                'action' => 'plugin_activation',
                'message' => "Plugin CleanSEO został aktywowany przez {$user_login}",
                'data' => maybe_serialize($data),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => current_time('mysql')
            )
        );
        
        // Zapisz również log do pliku
        $log_dir = WP_CONTENT_DIR . '/cleanseo-logs';
        if (is_dir($log_dir)) {
            $log_file = $log_dir . '/activation.log';
            $log_message = sprintf(
                "[%s] Plugin CleanSEO v%s aktywowany przez %s (ID: %d) z IP %s\n",
                current_time('mysql'),
                CLEANSEO_VERSION,
                $user_login,
                $user_id,
                $_SERVER['REMOTE_ADDR']
            );
            
            @file_put_contents($log_file, $log_message, FILE_APPEND);
        }
    }

    /**
     * Czyści cache podczas deaktywacji
     */
    public static function clear_cache() {
        // Wyczyść cache WordPress
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Usuń transients związane z wtyczką
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cleanseo_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cleanseo_%'");
        
        // Wyczyść pliki cache jeśli istnieją
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/cleanseo-cache';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && pathinfo($file, PATHINFO_BASENAME) !== '.htaccess' && pathinfo($file, PATHINFO_BASENAME) !== 'index.php') {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Zapisuje log deaktywacji pluginu
     */
    private static function log_deactivation() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_logs';
        
        // Sprawdź czy tabela logów istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID > 0 ? $current_user->ID : 0;
            $user_login = $current_user->user_login ? $current_user->user_login : 'System';
            
            // Zapisz log deaktywacji
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'action' => 'plugin_deactivation',
                    'message' => "Plugin CleanSEO został dezaktywowany przez {$user_login}",
                    'data' => '',
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'timestamp' => current_time('mysql')
                )
            );
            
            // Zapisz również log do pliku
            $log_dir = WP_CONTENT_DIR . '/cleanseo-logs';
            if (is_dir($log_dir)) {
                $log_file = $log_dir . '/activation.log';
                $log_message = sprintf(
                    "[%s] Plugin CleanSEO dezaktywowany przez %s (ID: %d) z IP %s\n",
                    current_time('mysql'),
                    $user_login,
                    $user_id,
                    $_SERVER['REMOTE_ADDR']
                );
                
                @file_put_contents($log_file, $log_message, FILE_APPEND);
            }
        }
    }

    /**
     * Deaktywacja pluginu
     */
    public static function deactivate() {
        // Usuń zaplanowane zadania
        wp_clear_scheduled_hook('cleanseo_daily_cleanup');
        wp_clear_scheduled_hook('cleanseo_weekly_report');
        wp_clear_scheduled_hook('cleanseo_monthly_report');
        wp_clear_scheduled_hook('cleanseo_daily_rank_check');
        wp_clear_scheduled_hook('cleanseo_weekly_404_check');
        
        // Wyczyść cache podczas deaktywacji
        self::clear_cache();
        
        // Zapisz log deaktywacji
        self::log_deactivation();
        
        // Odśwież reguły rewrite
        flush_rewrite_rules();
    }

    /**
     * Deinstalacja pluginu
     */
    public static function uninstall() {
        // Pobierz ustawienia pluginu
        $options = get_option('cleanseo_options', array());
        $keep_data = isset($options['keep_data_on_uninstall']) ? (bool)$options['keep_data_on_uninstall'] : false;
        
        // Jeśli ustawiono zachowanie danych, nie usuwaj ich
        if ($keep_data) {
            return;
        }
        
        global $wpdb;
        
        // Usuń tabele
        $tables = array(
            'seo_settings',
            'seo_redirects',
            'seo_competitors',
            'seo_logs',
            'seo_audits',
            'seo_locations',
            'seo_stats',
            'seo_analytics',
            'seo_openai_logs',
            'seo_ai_cache'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        // Usuń metadane
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'cleanseo_%'");
        $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE 'cleanseo_%'");
        
        // Usuń opcje
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'cleanseo_%'");
        
        // Usuń uprawnienia z ról
        $roles = array('administrator', 'editor');
        $capabilities = array(
            'manage_cleanseo',
            'view_cleanseo_reports',
            'manage_cleanseo_redirects',
            'manage_cleanseo_competitors',
            'manage_cleanseo_audits',
            'manage_cleanseo_locations',
            'manage_cleanseo_settings',
            'use_cleanseo_ai_tools'
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        // Usuń katalogi
        $upload_dir = wp_upload_dir();
        $dirs_to_remove = array(
            $upload_dir['basedir'] . '/cleanseo-cache',
            $upload_dir['basedir'] . '/cleanseo-exports',
            WP_CONTENT_DIR . '/cleanseo-logs'
        );
        
        foreach ($dirs_to_remove as $dir) {
            if (is_dir($dir)) {
                self::remove_directory($dir);
            }
        }
        
        // Wyczyść cache
        self::clear_cache();
    }
    
    /**
     * Rekursywnie usuwa katalog i wszystkie pliki w nim
     */
    private static function remove_directory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        self::remove_directory($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        @unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            @rmdir($dir);
        }
    }
}