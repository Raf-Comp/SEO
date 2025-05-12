<?php
/**
 * Klasa odpowiedzialna za deinstalację pluginu
 */
class CleanSEO_Uninstaller {
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

    public function uninstall() {
        // Usuń wszystkie tabele
        foreach ($this->tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Usuń wszystkie opcje
        $options = array(
            'cleanseo_options',
            'cleanseo_version',
            'cleanseo_db_version',
            'cleanseo_api_key',
            'cleanseo_default_model',
            'cleanseo_max_tokens',
            'cleanseo_temperature',
            'cleanseo_cache_enabled',
            'cleanseo_cache_time',
            'cleanseo_auto_generate',
            'cleanseo_post_types',
            'cleanseo_generate_fields',
            'cleanseo_frequency_penalty',
            'cleanseo_presence_penalty',
            'cleanseo_top_p',
            'cleanseo_retry_attempts',
            'cleanseo_retry_delay',
            'cleanseo_timeout',
            'cleanseo_cost_tracking',
            'cleanseo_budget_limit',
            'cleanseo_alert_threshold',
            'cleanseo_notification_email'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }

        // Usuń wszystkie meta dane
        $this->wpdb->query("DELETE FROM {$this->wpdb->postmeta} WHERE meta_key LIKE 'cleanseo_%'");
        $this->wpdb->query("DELETE FROM {$this->wpdb->usermeta} WHERE meta_key LIKE 'cleanseo_%'");
        $this->wpdb->query("DELETE FROM {$this->wpdb->termmeta} WHERE meta_key LIKE 'cleanseo_%'");

        // Usuń wszystkie transients
        $this->wpdb->query("DELETE FROM {$this->wpdb->options} WHERE option_name LIKE '_transient_cleanseo_%'");
        $this->wpdb->query("DELETE FROM {$this->wpdb->options} WHERE option_name LIKE '_transient_timeout_cleanseo_%'");

        // Usuń wszystkie cron jobs
        wp_clear_scheduled_hook('cleanseo_daily_cleanup');
        wp_clear_scheduled_hook('cleanseo_weekly_report');
        wp_clear_scheduled_hook('cleanseo_monthly_report');

        // Usuń wszystkie capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_cleanseo');
            $role->remove_cap('view_cleanseo_reports');
            $role->remove_cap('manage_cleanseo_redirects');
            $role->remove_cap('manage_cleanseo_competitors');
            $role->remove_cap('manage_cleanseo_audits');
            $role->remove_cap('manage_cleanseo_locations');
        }

        // Usuń wszystkie ustawienia sieciowe
        delete_site_option('cleanseo_network_settings');
        delete_site_option('cleanseo_network_version');
        delete_site_option('cleanseo_network_db_version');

        // Usuń wszystkie pliki cache
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/cleanseo-cache';
        if (is_dir($cache_dir)) {
            $this->remove_directory($cache_dir);
        }

        // Usuń wszystkie logi
        $log_dir = WP_CONTENT_DIR . '/cleanseo-logs';
        if (is_dir($log_dir)) {
            $this->remove_directory($log_dir);
        }

        // Wyczyść cache WordPress
        wp_cache_flush();
    }

    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
} 