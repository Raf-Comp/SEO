<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://raf-comp.net
 * @since      1.0.0
 *
 * @package    CleanSEO_Optimizer
 * @subpackage CleanSEO_Optimizer/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    CleanSEO_Optimizer
 * @subpackage CleanSEO_Optimizer/includes
 * @author     Rafał Danielewski
 */
class CleanSEO_Deactivator {
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
            'analytics' => $wpdb->prefix . 'seo_analytics'
        );
    }

    /**
     * Run the deactivation process
     */
    public function deactivate() {
        $this->clear_scheduled_events();
        $this->clear_capabilities();
        $this->clear_cache();
        $this->flush_rewrite_rules();
    }

    /**
     * Clear all scheduled events
     */
    private function clear_scheduled_events() {
        wp_clear_scheduled_hook('cleanseo_daily_cleanup');
        wp_clear_scheduled_hook('cleanseo_weekly_report');
        wp_clear_scheduled_hook('cleanseo_monthly_report');
    }

    /**
     * Remove all plugin capabilities from administrator role
     */
    private function clear_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_cleanseo');
            $role->remove_cap('view_cleanseo_reports');
            $role->remove_cap('manage_cleanseo_redirects');
            $role->remove_cap('manage_cleanseo_competitors');
            $role->remove_cap('manage_cleanseo_audits');
            $role->remove_cap('manage_cleanseo_locations');
        }
    }

    /**
     * Clear all plugin caches
     */
    private function clear_cache() {
        // Clear WordPress cache
        wp_cache_flush();
        
        // Clear plugin cache
        delete_transient('cleanseo_cache');
        
        // Clear sitemap cache
        delete_transient('cleanseo_sitemap');
        delete_transient('cleanseo_sitemap_index');
        
        // Clear analytics cache
        delete_transient('cleanseo_analytics');
        delete_transient('cleanseo_stats');
        
        // Clear audits cache
        delete_transient('cleanseo_audits');
        
        // Clear competitors cache
        delete_transient('cleanseo_competitors');
        
        // Clear locations cache
        delete_transient('cleanseo_locations');
    }

    /**
     * Flush rewrite rules and clear sitemap rules
     */
    private function flush_rewrite_rules() {
        // Refresh rewrite rules for sitemap
        $sitemap = new CleanSEO_Sitemap();
        $sitemap->flush_rules();
    }
} 