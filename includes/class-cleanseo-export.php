<?php
/**
 * Klasa do eksportu danych CleanSEO
 * 
 * @package CleanSEO
 * @subpackage Export
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Export {
    private $export_dir;
    private $plugin_name;
    private $version;
    private $wpdb;

    /**
     * Konstruktor
     * 
     * @param string $plugin_name Nazwa pluginu
     * @param string $version Wersja pluginu
     */
    public function __construct($plugin_name = 'cleanseo-optimizer', $version = '1.0.0') {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $upload_dir = wp_upload_dir();
        $this->export_dir = $upload_dir['basedir'] . '/cleanseo-exports';
        
        if (!file_exists($this->export_dir)) {
            wp_mkdir_p($this->export_dir);
        }

        global $wpdb;
        $this->wpdb = $wpdb;

        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     */
    private function init_hooks() {
        add_action('wp_ajax_cleanseo_export_data', array($this, 'handle_export_request'));
        add_action('admin_post_cleanseo_download_export', array($this, 'handle_download_request'));
    }

    /**
     * Obsługa żądania eksportu przez AJAX
     */
    public function handle_export_request() {
        check_ajax_referer('cleanseo_export_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień do eksportu danych.', 'cleanseo-optimizer'));
        }

        $type = sanitize_text_field($_POST['export_type']);
        $format = sanitize_text_field($_POST['format']);
        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : 'all';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        try {
            $data = $this->get_export_data($type, $section, $id);
            
            if (empty($data)) {
                wp_send_json_error(__('Brak danych do eksportu.', 'cleanseo-optimizer'));
            }

            $filename = 'cleanseo-export-' . $section . '-' . date('Y-m-d');
            
            if ($format === 'csv') {
                $filepath = $this->export_csv($data, $filename);
            } else {
                $filepath = $this->export_pdf($data, $filename, ucfirst($section));
            }

            $download_url = add_query_arg(array(
                'action' => 'cleanseo_download_export',
                'file' => basename($filepath),
                'nonce' => wp_create_nonce('cleanseo_download_' . basename($filepath))
            ), admin_url('admin-post.php'));

            wp_send_json_success(array(
                'message' => __('Eksport zakończony sukcesem.', 'cleanseo-optimizer'),
                'download_url' => $download_url
            ));

        } catch (Exception $e) {
            wp_send_json_error(sprintf(
                __('Błąd podczas eksportu: %s', 'cleanseo-optimizer'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Pobierz dane do eksportu
     */
    private function get_export_data($type, $section, $id = 0) {
        $data = array();

        switch ($section) {
            case 'settings':
                $row = $this->wpdb->get_row("SELECT * FROM {$this->wpdb->prefix}seo_settings LIMIT 1", ARRAY_A);
                $data[] = $row;
                break;

            case 'competitors':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_competitors", ARRAY_A);
                foreach ($rows as &$r) {
                    if (isset($r['keywords'])) {
                        $r['keywords'] = implode(', ', json_decode($r['keywords'], true));
                    }
                }
                $data = $rows;
                break;

            case 'audits':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_audits ORDER BY timestamp DESC", ARRAY_A);
                $data = $rows;
                break;

            case 'locations':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_locations ORDER BY created_at DESC", ARRAY_A);
                $data = $rows;
                break;

            case 'analytics':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_analytics ORDER BY date DESC", ARRAY_A);
                $data = $rows;
                break;

            case 'logs':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_logs ORDER BY timestamp DESC", ARRAY_A);
                $data = $rows;
                break;

            case '404':
                $rows = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_logs WHERE action = '404_error' ORDER BY timestamp DESC", ARRAY_A);
                $data = $rows;
                break;

            case 'seo_report':
                if ($id) {
                    $post = get_post($id);
                    $content_analysis = new CleanSEO_ContentAnalysis($id);
                    $data[] = array(
                        'title' => $post->post_title,
                        'url' => get_permalink($id),
                        'content_length' => $content_analysis->get_content_length(),
                        'keyword_density' => $content_analysis->get_keyword_density(),
                        'readability_score' => $content_analysis->get_flesch_score(),
                        'seo_score' => $content_analysis->get_seo_score(),
                        'headers' => json_encode($content_analysis->get_headers()),
                        'internal_links' => $content_analysis->get_internal_links_count()
                    );
                }
                break;

            case 'all':
            default:
                $data[] = array('Sekcja' => 'Ustawienia', 'Dane' => json_encode($this->wpdb->get_row("SELECT * FROM {$this->wpdb->prefix}seo_settings LIMIT 1", ARRAY_A)));
                $data[] = array('Sekcja' => 'Konkurenci', 'Dane' => json_encode($this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_competitors", ARRAY_A)));
                $data[] = array('Sekcja' => 'Audyty', 'Dane' => json_encode($this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_audits", ARRAY_A)));
                $data[] = array('Sekcja' => 'Lokalizacje', 'Dane' => json_encode($this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_locations", ARRAY_A)));
                $data[] = array('Sekcja' => 'Analityka', 'Dane' => json_encode($this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_analytics", ARRAY_A)));
                $data[] = array('Sekcja' => 'Logi', 'Dane' => json_encode($this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}seo_logs", ARRAY_A)));
                break;
        }

        return $data;
    }

    /**
     * Obsługa żądania pobrania pliku
     */
    public function handle_download_request() {
        $file = sanitize_text_field($_GET['file']);
        $nonce = sanitize_text_field($_GET['nonce']);

        if (!wp_verify_nonce($nonce, 'cleanseo_download_' . $file)) {
            wp_die(__('Nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień do pobrania pliku.', 'cleanseo-optimizer'));
        }

        $filepath = $this->export_dir . '/' . $file;
        
        if (!file_exists($filepath)) {
            wp_die(__('Plik nie istnieje.', 'cleanseo-optimizer'));
        }

        // Wymuś pobranie pliku
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Eksportuj dane do CSV
     */
    public function export_csv($data, $filename) {
        $filepath = $this->export_dir . '/' . sanitize_file_name($filename) . '.csv';
        
        $fp = fopen($filepath, 'w');
        if (!$fp) {
            throw new Exception(__('Nie można utworzyć pliku CSV.', 'cleanseo-optimizer'));
        }

        // Nagłówki
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
        }

        // Dane
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        return $filepath;
    }

    /**
     * Eksportuj dane do PDF
     */
    public function export_pdf($data, $filename, $title = 'Raport') {
        require_once(ABSPATH . 'wp-content/plugins/cleanseo-optimizer/includes/vendor/mpdf/mpdf.php');
        
        $mpdf = new \Mpdf\Mpdf(['utf-8', 'A4']);
        $html = '<h1>' . esc_html($title) . '</h1>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<thead><tr>';
        
        foreach (array_keys($data[0]) as $col) {
            $html .= '<th>' . esc_html($col) . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        $filepath = $this->export_dir . '/' . sanitize_file_name($filename) . '.pdf';
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        
        return $filepath;
    }
} 