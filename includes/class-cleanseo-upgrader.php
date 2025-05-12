class CleanSEO_Upgrader {
    private $wpdb;
    private $tables;
    private $current_version;
    private $current_db_version;

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
            'analytics' => $wpdb->prefix . 'seo_analytics'
        );
        $this->current_version = get_option('cleanseo_version', '1.0.0');
        $this->current_db_version = get_option('cleanseo_db_version', '1.0.0');
    }

    public function upgrade() {
        if (version_compare($this->current_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
        }
        if (version_compare($this->current_version, '1.2.0', '<')) {
            $this->upgrade_to_1_2_0();
        }
        if (version_compare($this->current_version, '1.3.0', '<')) {
            $this->upgrade_to_1_3_0();
        }
        if (version_compare($this->current_version, '1.4.0', '<')) {
            $this->upgrade_to_1_4_0();
        }
        if (version_compare($this->current_version, '1.5.0', '<')) {
            $this->upgrade_to_1_5_0();
        }

        update_option('cleanseo_version', CLEANSEO_VERSION);
        update_option('cleanseo_db_version', CLEANSEO_DB_VERSION);
    }

    private function upgrade_to_1_1_0() {
        // Dodaj kolumnę is_regex do tabeli redirects
        $this->wpdb->query("ALTER TABLE {$this->tables['redirects']} ADD COLUMN is_regex TINYINT(1) NOT NULL DEFAULT 0");
        
        // Dodaj kolumnę preserve_query do tabeli redirects
        $this->wpdb->query("ALTER TABLE {$this->tables['redirects']} ADD COLUMN preserve_query TINYINT(1) NOT NULL DEFAULT 0");
        
        // Dodaj kolumnę last_accessed do tabeli redirects
        $this->wpdb->query("ALTER TABLE {$this->tables['redirects']} ADD COLUMN last_accessed DATETIME NULL");
    }

    private function upgrade_to_1_2_0() {
        // Dodaj kolumnę status do tabeli locations
        $this->wpdb->query("ALTER TABLE {$this->tables['locations']} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        
        // Dodaj kolumnę created_at do tabeli locations
        $this->wpdb->query("ALTER TABLE {$this->tables['locations']} ADD COLUMN created_at DATETIME NULL");
        
        // Dodaj kolumnę updated_at do tabeli locations
        $this->wpdb->query("ALTER TABLE {$this->tables['locations']} ADD COLUMN updated_at DATETIME NULL");
    }

    private function upgrade_to_1_3_0() {
        // Dodaj kolumnę score do tabeli audits
        $this->wpdb->query("ALTER TABLE {$this->tables['audits']} ADD COLUMN score INT NOT NULL DEFAULT 0");
        
        // Dodaj kolumnę issues do tabeli audits
        $this->wpdb->query("ALTER TABLE {$this->tables['audits']} ADD COLUMN issues TEXT NULL");
        
        // Dodaj kolumnę recommendations do tabeli audits
        $this->wpdb->query("ALTER TABLE {$this->tables['audits']} ADD COLUMN recommendations TEXT NULL");
    }

    private function upgrade_to_1_4_0() {
        // Dodaj kolumnę source do tabeli analytics
        $this->wpdb->query("ALTER TABLE {$this->tables['analytics']} ADD COLUMN source VARCHAR(50) NULL");
        
        // Dodaj kolumnę medium do tabeli analytics
        $this->wpdb->query("ALTER TABLE {$this->tables['analytics']} ADD COLUMN medium VARCHAR(50) NULL");
        
        // Dodaj kolumnę campaign do tabeli analytics
        $this->wpdb->query("ALTER TABLE {$this->tables['analytics']} ADD COLUMN campaign VARCHAR(100) NULL");
    }

    private function upgrade_to_1_5_0() {
        // Dodaj kolumnę sitemap_include_images do tabeli settings
        $this->wpdb->query("ALTER TABLE {$this->tables['settings']} ADD COLUMN sitemap_include_images TINYINT(1) NOT NULL DEFAULT 1");
        
        // Dodaj kolumnę sitemap_include_video do tabeli settings
        $this->wpdb->query("ALTER TABLE {$this->tables['settings']} ADD COLUMN sitemap_include_video TINYINT(1) NOT NULL DEFAULT 1");
        
        // Dodaj kolumnę sitemap_include_news do tabeli settings
        $this->wpdb->query("ALTER TABLE {$this->tables['settings']} ADD COLUMN sitemap_include_news TINYINT(1) NOT NULL DEFAULT 1");
    }
} 