<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Access WordPress database
global $wpdb;

// Drop plugin tables - poprawione użycie zapytań
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_settings");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_redirects");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_competitors");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_locations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_audits");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_openai_logs");

// Delete options
$options_to_delete = [
    'cleanseo_db_version',
    'cleanseo_gsc_access_token',
    'cleanseo_ga4_access_token',
    'cleanseo_excluded_post_types',
    'cleanseo_excluded_taxonomies',
    'cleanseo_openai_api_key',
    'cleanseo_openai_model',
    'cleanseo_openai_language',
    'cleanseo_gemini_api_key',
    'cleanseo_claude_api_key',
    'cleanseo_huggingface_api_key',
    'cleanseo_google_api_key',
    'cleanseo_competitor_keywords',
    'cleanseo_my_keywords'
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Delete post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_cleanseo_%'");

// Delete user meta - poprawione zapytanie
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cleanseo_%'");

// Clear scheduled events
wp_clear_scheduled_hook('cleanseo_daily_competitor_check');
wp_clear_scheduled_hook('cleanseo_daily_analytics_sync');
wp_clear_scheduled_hook('cleanseo_weekly_audit');

// Flush rewrite rules
flush_rewrite_rules();