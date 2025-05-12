<?php
/**
 * Klasa obsługująca przekierowania w CleanSEO
 *
 * @package CleanSEO
 * @subpackage Redirects
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Redirects {
    private $plugin_name;
    private $version;
    private $redirects_table;
    private $cache_group = 'cleanseo_redirects';
    private $cache_time = 3600; // 1 hour
    private $logger;

    public function __construct($plugin_name, $version) {
        global $wpdb;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->redirects_table = $wpdb->prefix . 'seo_redirects';
        $this->logger = new CleanSEO_Logger();
        
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_cleanseo_add_redirect', array($this, 'add_redirect'));
        add_action('wp_ajax_cleanseo_update_redirect', array($this, 'update_redirect'));
        add_action('wp_ajax_cleanseo_delete_redirect', array($this, 'delete_redirect'));
        add_action('wp_ajax_cleanseo_get_redirects', array($this, 'get_redirects'));
        add_action('wp_ajax_cleanseo_get_redirect', array($this, 'get_redirect'));
        add_action('wp_ajax_cleanseo_export_redirects_csv', array($this, 'export_redirects_csv'));
        add_action('wp_ajax_cleanseo_import_redirects_csv', array($this, 'import_redirects_csv'));
        add_action('wp_ajax_cleanseo_batch_delete_redirects', array($this, 'batch_delete_redirects'));
        
        // Frontend hooks
        add_action('template_redirect', array($this, 'handle_redirects'), 1);
        
        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Cache hooks
        add_action('cleanseo_redirect_updated', array($this, 'clear_redirect_cache'));
    }

    /**
     * Załaduj zasoby administracyjne
     */
    public function enqueue_admin_assets($hook) {
        if ('cleanseo-optimizer_page_cleanseo-redirects' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'cleanseo-redirects',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-redirects.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'cleanseo-redirects',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-redirects.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('cleanseo-redirects', 'cleanseoRedirects', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_redirects_nonce'),
            'messages' => array(
                'error' => __('Wystąpił błąd podczas przetwarzania żądania.', 'cleanseo-optimizer'),
                'confirmDelete' => __('Czy na pewno chcesz usunąć to przekierowanie?', 'cleanseo-optimizer'),
                'success' => __('Operacja zakończona sukcesem.', 'cleanseo-optimizer')
            )
        ));
    }

    /**
     * Dodaj nowe przekierowanie
     */
    public function add_redirect() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        $data = $this->sanitize_redirect_data($_POST);
        
        // Validate URLs
        if (!$this->validate_urls($data['source_url'], $data['target_url'])) {
            wp_send_json_error(__('Nieprawidłowe adresy URL.', 'cleanseo-optimizer'));
        }

        // Check for duplicate source URL
        if ($this->redirect_exists($data['source_url'])) {
            wp_send_json_error(__('Przekierowanie z tego adresu źródłowego już istnieje.', 'cleanseo-optimizer'));
        }

        global $wpdb;
        $result = $wpdb->insert(
            $this->redirects_table,
            $data,
            array('%s', '%s', '%d', '%d', '%s', '%d', '%s')
        );

        if ($result === false) {
            $this->logger->log('error', 'Błąd podczas dodawania przekierowania', array(
                'error' => $wpdb->last_error,
                'data' => $data
            ));
            wp_send_json_error(__('Błąd podczas dodawania przekierowania.', 'cleanseo-optimizer'));
        }

        do_action('cleanseo_redirect_updated');
        
        $this->logger->log('info', 'Dodano nowe przekierowanie', array(
            'redirect_id' => $wpdb->insert_id,
            'source_url' => $data['source_url']
        ));
        
        wp_send_json_success(array(
            'message' => __('Przekierowanie zostało dodane pomyślnie.', 'cleanseo-optimizer'),
            'redirect_id' => $wpdb->insert_id
        ));
    }

    /**
     * Aktualizuj przekierowanie
     */
    public function update_redirect() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        $redirect_id = isset($_POST['redirect_id']) ? intval($_POST['redirect_id']) : 0;
        
        if ($redirect_id <= 0) {
            wp_send_json_error(__('Nieprawidłowy identyfikator przekierowania.', 'cleanseo-optimizer'));
        }
        
        $data = $this->sanitize_redirect_data($_POST);
        
        // Validate URLs
        if (!$this->validate_urls($data['source_url'], $data['target_url'])) {
            wp_send_json_error(__('Nieprawidłowe adresy URL.', 'cleanseo-optimizer'));
        }

        // Check for duplicate source URL (excluding current redirect)
        if ($this->redirect_exists($data['source_url'], $redirect_id)) {
            wp_send_json_error(__('Przekierowanie z tego adresu źródłowego już istnieje.', 'cleanseo-optimizer'));
        }

        global $wpdb;
        $result = $wpdb->update(
            $this->redirects_table,
            $data,
            array('id' => $redirect_id),
            array('%s', '%s', '%d', '%d', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            $this->logger->log('error', 'Błąd podczas aktualizacji przekierowania', array(
                'error' => $wpdb->last_error,
                'redirect_id' => $redirect_id,
                'data' => $data
            ));
            wp_send_json_error(__('Błąd podczas aktualizacji przekierowania.', 'cleanseo-optimizer'));
        }

        do_action('cleanseo_redirect_updated');
        
        $this->logger->log('info', 'Zaktualizowano przekierowanie', array(
            'redirect_id' => $redirect_id,
            'source_url' => $data['source_url']
        ));
        
        wp_send_json_success(array(
            'message' => __('Przekierowanie zostało zaktualizowane pomyślnie.', 'cleanseo-optimizer')
        ));
    }

    /**
     * Usuń przekierowanie
     */
    public function delete_redirect() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        $redirect_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($redirect_id <= 0) {
            wp_send_json_error(__('Nieprawidłowy identyfikator przekierowania.', 'cleanseo-optimizer'));
        }
        
        // Najpierw pobierz dane przekierowania do logów
        global $wpdb;
        $redirect = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->redirects_table} WHERE id = %d",
                $redirect_id
            )
        );
        
        if (!$redirect) {
            wp_send_json_error(__('Nie znaleziono przekierowania.', 'cleanseo-optimizer'));
        }
        
        $result = $wpdb->delete(
            $this->redirects_table,
            array('id' => $redirect_id),
            array('%d')
        );

        if ($result === false) {
            $this->logger->log('error', 'Błąd podczas usuwania przekierowania', array(
                'error' => $wpdb->last_error,
                'redirect_id' => $redirect_id
            ));
            wp_send_json_error(__('Błąd podczas usuwania przekierowania.', 'cleanseo-optimizer'));
        }

        do_action('cleanseo_redirect_updated');
        
        $this->logger->log('info', 'Usunięto przekierowanie', array(
            'redirect_id' => $redirect_id,
            'source_url' => $redirect->source_url
        ));
        
        wp_send_json_success(array(
            'message' => __('Przekierowanie zostało usunięte pomyślnie.', 'cleanseo-optimizer')
        ));
    }

    /**
     * Pobierz wszystkie przekierowania
     */
    public function get_redirects() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        global $wpdb;
        $redirects = $wpdb->get_results(
            "SELECT * FROM {$this->redirects_table} ORDER BY id DESC",
            ARRAY_A
        );

        if ($redirects === null) {
            $this->logger->log('error', 'Błąd podczas pobierania przekierowań', array(
                'error' => $wpdb->last_error
            ));
            wp_send_json_error(__('Błąd podczas pobierania przekierowań.', 'cleanseo-optimizer'));
        }

        wp_send_json_success(array('redirects' => $redirects));
    }

    /**
     * Pobierz pojedyncze przekierowanie
     */
    public function get_redirect() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        $redirect_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($redirect_id <= 0) {
            wp_send_json_error(__('Nieprawidłowy identyfikator przekierowania.', 'cleanseo-optimizer'));
        }
        
        global $wpdb;
        $redirect = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->redirects_table} WHERE id = %d",
                $redirect_id
            ),
            ARRAY_A
        );

        if (!$redirect) {
            wp_send_json_error(__('Nie znaleziono przekierowania.', 'cleanseo-optimizer'));
        }

        wp_send_json_success($redirect);
    }

    /**
     * Obsługa przekierowań na froncie
     */
    public function handle_redirects() {
        if (is_admin()) {
            return;
        }

        $current_url = $this->get_current_url();
        $redirect = $this->get_redirect_for_url($current_url);

        if ($redirect) {
            // Update hit counter
            $this->update_redirect_hits($redirect->id);

            // Handle 410 Gone
            if ($redirect->status_code === 410) {
                status_header(410);
                die('Gone');
            }

            // Handle redirect with query parameters
            $target_url = $redirect->target_url;
            if ($redirect->preserve_query && !empty($_SERVER['QUERY_STRING'])) {
                $target_url .= (strpos($target_url, '?') === false ? '?' : '&') . $_SERVER['QUERY_STRING'];
            }

            // Log the redirect
            $this->logger->log('info', 'Wykonano przekierowanie', array(
                'source_url' => $current_url,
                'target_url' => $target_url,
                'status_code' => $redirect->status_code,
                'redirect_id' => $redirect->id
            ));

            // Perform redirect
            wp_redirect($target_url, $redirect->status_code);
            exit;
        }
    }

    /**
     * Pobierz przekierowanie dla URL
     * 
     * @param string $url URL do sprawdzenia
     * @return object|false Obiekt przekierowania lub false jeśli nie znaleziono
     */
    private function get_redirect_for_url($url) {
        // Użyj jednolitego formatu klucza cache
        $cache_key = 'cleanseo_redirect_' . md5($url);
        $redirect = wp_cache_get($cache_key, $this->cache_group);
        
        if ($redirect === false) {
            global $wpdb;
            
            // Najpierw spróbuj dokładnego dopasowania
            $redirect = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->redirects_table} WHERE source_url = %s AND is_regex = 0",
                $url
            ));
            
            // Jeśli nie ma dokładnego dopasowania, spróbuj wzorców regex
            if (!$redirect) {
                $redirects = $wpdb->get_results(
                    "SELECT * FROM {$this->redirects_table} WHERE is_regex = 1"
                );
                
                foreach ($redirects as $r) {
                    // Dodaj obsługę błędów dla nieprawidłowych wyrażeń regularnych
                    try {
                        if (@preg_match($r->source_url, $url)) {
                            $redirect = $r;
                            break;
                        }
                    } catch (Exception $e) {
                        $this->logger->log('error', 'Nieprawidłowe wyrażenie regularne', array(
                            'regex' => $r->source_url,
                            'url' => $url,
                            'error' => $e->getMessage()
                        ));
                    }
                }
            }
            
            // Zapisz wynik w cache
            wp_cache_set($cache_key, $redirect, $this->cache_group, $this->cache_time);
        }
        
        return $redirect;
    }

    /**
     * Pobierz aktualny URL
     */
    private function get_current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        if (empty($host)) {
            $this->logger->log('error', 'Brak HTTP_HOST w zmiennych serwera');
            return '';
        }
        
        return $protocol . '://' . $host . $uri;
    }

    /**
     * Sanityzacja danych przekierowania
     */
    private function sanitize_redirect_data($data) {
        $source_url = isset($data['source_url']) ? $data['source_url'] : '';
        $target_url = isset($data['target_url']) ? $data['target_url'] : '';
        $status_code = isset($data['status_code']) ? absint($data['status_code']) : 301;
        
        // Sprawdź, czy status_code jest prawidłowym kodem przekierowania
        $valid_status_codes = array(301, 302, 303, 307, 308, 410);
        if (!in_array($status_code, $valid_status_codes)) {
            $status_code = 301; // Domyślnie użyj 301
        }
        
        return array(
            'source_url' => esc_url_raw($source_url),
            'target_url' => esc_url_raw($target_url),
            'status_code' => $status_code,
            'hits' => isset($data['hits']) ? absint($data['hits']) : 0,
            'last_accessed' => isset($data['last_accessed']) ? sanitize_text_field($data['last_accessed']) : null,
            'is_regex' => isset($data['is_regex']) ? 1 : 0,
            'preserve_query' => isset($data['preserve_query']) ? 1 : 0
        );
    }

    /**
     * Walidacja URL dla przekierowania
     * 
     * @param string $source_url URL źródłowy
     * @param string $target_url URL docelowy
     * @return bool Czy URL są prawidłowe
     */
    private function validate_urls($source_url, $target_url) {
        // Validate source URL
        if (empty($source_url)) {
            return false;
        }

        // If source URL is a regex pattern, validate it
        if (isset($_POST['is_regex']) && $_POST['is_regex']) {
            if (@preg_match($source_url, '') === false) {
                $this->logger->log('error', 'Nieprawidłowe wyrażenie regularne', array(
                    'regex' => $source_url
                ));
                return false;
            }
        } else {
            // Validate as regular URL
            if (!filter_var($source_url, FILTER_VALIDATE_URL)) {
                return false;
            }
        }

        // Validate target URL (skip for 410 Gone)
        if (isset($_POST['status_code']) && $_POST['status_code'] == 410) {
            return true;
        }

        // Validate target URL
        if (empty($target_url) || !filter_var($target_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    /**
     * Sprawdź czy przekierowanie istnieje
     * 
     * @param string $source_url URL źródłowy
     * @param int|null $exclude_id ID przekierowania do wykluczenia
     * @return bool Czy przekierowanie istnieje
     */
    private function redirect_exists($source_url, $exclude_id = null) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->redirects_table} WHERE source_url = %s",
            $source_url
        );
        
        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        return (bool) $wpdb->get_var($query);
    }

    /**
     * Aktualizuj licznik przekierowań
     * 
     * @param int $redirect_id ID przekierowania
     */
    private function update_redirect_hits($redirect_id) {
        global $wpdb;
        
        // Użyj transakcji, aby zapewnić poprawną aktualizację
        $wpdb->query('START TRANSACTION');
        
        // Pobierz aktualną wartość licznika
        $current_hits = $wpdb->get_var($wpdb->prepare(
            "SELECT hits FROM {$this->redirects_table} WHERE id = %d FOR UPDATE",
            $redirect_id
        ));
        
        // Zaktualizuj licznik i datę ostatniego dostępu
        $result = $wpdb->update(
            $this->redirects_table,
            array(
                'hits' => $current_hits + 1,
                'last_accessed' => current_time('mysql')
            ),
            array('id' => $redirect_id)
        );
        
        if ($result === false) {
            $wpdb->query('ROLLBACK');
            $this->logger->log('error', 'Błąd podczas aktualizacji licznika przekierowań', array(
                'error' => $wpdb->last_error,
                'redirect_id' => $redirect_id
            ));
        } else {
            $wpdb->query('COMMIT');
        }
    }

    /**
     * Wyczyść cache przekierowań
     */
    public function clear_redirect_cache() {
        wp_cache_flush_group($this->cache_group);
        $this->logger->log('info', 'Wyczyszczono cache przekierowań');
    }

    /**
     * Eksportuj przekierowania do CSV
     */
    public function export_redirects_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        global $wpdb;
        $redirects = $wpdb->get_results("SELECT * FROM {$this->redirects_table} ORDER BY id DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cleanseo-redirects-' . date('Ymd-His') . '.csv');
        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array(
            'ID',
            'Źródłowy URL',
            'Docelowy URL',
            'Kod statusu',
            'Licznik',
            'Ostatni dostęp',
            'Regex',
            'Zachowaj parametry'
        ));

        foreach ($redirects as $row) {
            fputcsv($output, array(
                $row['id'],
                $row['source_url'],
                $row['target_url'],
                $row['status_code'],
                $row['hits'],
                $row['last_accessed'],
                $row['is_regex'],
                $row['preserve_query']
            ));
        }
        fclose($output);
        
        $this->logger->log('info', 'Wyeksportowano przekierowania do CSV', array(
            'count' => count($redirects)
        ));
        
        exit;
    }

    /**
     * Importuj przekierowania z pliku CSV
     */
    public function import_redirects_csv() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Błąd przesyłania pliku.', 'cleanseo-optimizer'));
        }

        $file = $_FILES['csv']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            wp_send_json_error(__('Nie można otworzyć pliku.', 'cleanseo-optimizer'));
        }

        // Skip header
        fgetcsv($handle, 1000, ',');
        
        $imported = 0;
        $errors = array();
        
        global $wpdb;
        
        // Użyj transakcji dla lepszej wydajności i spójności
        $wpdb->query('START TRANSACTION');
        
        try {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($row) < 3) continue;
                
                $data = array(
                    'source_url' => esc_url_raw($row[1]),
                    'target_url' => esc_url_raw($row[2]),
                    'status_code' => isset($row[3]) ? intval($row[3]) : 301,
                    'hits' => isset($row[4]) ? intval($row[4]) : 0,
                    'last_accessed' => isset($row[5]) ? sanitize_text_field($row[5]) : null,
                    'is_regex' => isset($row[6]) ? intval($row[6]) : 0,
                    'preserve_query' => isset($row[7]) ? intval($row[7]) : 0
                );

                if (!$this->validate_urls($data['source_url'], $data['target_url'])) {
                    $errors[] = sprintf(
                        __('Nieprawidłowe adresy URL w wierszu %d.', 'cleanseo-optimizer'),
                        $imported + 2
                    );
                    continue;
                }

                if ($this->redirect_exists($data['source_url'])) {
                    $errors[] = sprintf(
                        __('Duplikat adresu źródłowego w wierszu %d.', 'cleanseo-optimizer'),
                        $imported + 2
                    );
                    continue;
                }

                $result = $wpdb->insert($this->redirects_table, $data);
                
                if ($result) {
                    $imported++;
                } else {
                    $errors[] = sprintf(
                        __('Błąd podczas importowania wiersza %d: %s', 'cleanseo-optimizer'),
                        $imported + 2,
                        $wpdb->last_error
                    );
                }
            }
            
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->logger->log('error', 'Błąd podczas importowania przekierowań', array(
                'error' => $e->getMessage()
            ));
            $errors[] = $e->getMessage();
        }
        
        fclose($handle);
        
        do_action('cleanseo_redirect_updated');
        
        $this->logger->log('info', 'Zaimportowano przekierowania z CSV', array(
            'imported' => $imported,
            'errors' => $errors
        ));
        
        wp_send_json_success(array(
            'imported' => $imported,
            'errors' => $errors
        ));
    }

    /**
     * Masowe usuwanie przekierowań
     */
    public function batch_delete_redirects() {
        check_ajax_referer('cleanseo_redirects_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : array();
        
        if (!is_array($ids) || empty($ids)) {
            wp_send_json_error(__('Brak przekierowań do usunięcia.', 'cleanseo-optimizer'));
        }

        // Przygotuj tablicę ID po sanityzacji
        $sanitized_ids = array_map('absint', $ids);
        
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
        $query = "DELETE FROM {$this->redirects_table} WHERE id IN ($placeholders)";
        $result = $wpdb->query($wpdb->prepare($query, $sanitized_ids));

        if ($result === false) {
            $this->logger->log('error', 'Błąd podczas masowego usuwania przekierowań', array(
                'error' => $wpdb->last_error,
                'ids' => $sanitized_ids
            ));
            wp_send_json_error(__('Błąd podczas usuwania przekierowań.', 'cleanseo-optimizer'));
        }

        do_action('cleanseo_redirect_updated');
        
        $this->logger->log('info', 'Usunięto przekierowania masowo', array(
            'count' => $result,
            'ids' => $sanitized_ids
        ));
        
        wp_send_json_success(array('deleted' => $result));
    }
}