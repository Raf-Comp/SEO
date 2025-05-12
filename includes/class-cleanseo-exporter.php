<?php

class CleanSEO_Exporter {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->init_hooks();
    }

    public function init_hooks() {
        add_action('wp_ajax_cleanseo_export_data', array($this, 'export_data'));
    }

    public function export_data() {
        check_ajax_referer('cleanseo_export_data', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        
        // Get SEO settings
        $settings_table = $wpdb->prefix . 'seo_settings';
        $settings = $wpdb->get_row("SELECT * FROM $settings_table LIMIT 1");

        // Get competitors data
        $competitors_table = $wpdb->prefix . 'seo_competitors';
        $competitors = $wpdb->get_results("SELECT * FROM $competitors_table");

        // Get 404 errors
        $logs_table = $wpdb->prefix . 'seo_logs';
        $errors_404 = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = '404_error'");

        // Prepare CSV data
        $csv_data = array();
        
        // Add settings
        $csv_data[] = array('Type', 'Meta Title', 'Meta Description', 'OG Image URL', 'Sitemap Enabled');
        $csv_data[] = array(
            'Global Settings',
            $settings->meta_title ?? '',
            $settings->meta_description ?? '',
            $settings->og_image_url ?? '',
            $settings->sitemap_enabled ?? 0
        );

        // Add competitors
        $csv_data[] = array(''); // Empty row
        $csv_data[] = array('Competitors Analysis');
        $csv_data[] = array('Domain', 'Keywords', 'Last Check');
        
        foreach ($competitors as $competitor) {
            $keywords = json_decode($competitor->keywords, true);
            $keyword_list = is_array($keywords) ? implode(', ', array_keys($keywords)) : '';
            
            $csv_data[] = array(
                $competitor->domain,
                $keyword_list,
                $competitor->last_check
            );
        }

        // Add 404 errors
        $csv_data[] = array(''); // Empty row
        $csv_data[] = array('404 Errors');
        $csv_data[] = array('Total 404 Errors');
        $csv_data[] = array($errors_404);

        // Generate CSV
        $filename = 'cleanseo-export-' . date('Y-m-d') . '.csv';
        $csv = $this->generate_csv($csv_data);

        // Send file
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }

    private function generate_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
} 