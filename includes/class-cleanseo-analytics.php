<?php
/**
 * Klasa CleanSEO_Analytics
 * 
 * Odpowiada za przetwarzanie i przechowywanie danych analitycznych dla CleanSEO
 * 
 * @package CleanSEO
 * @subpackage Analytics
 */

class CleanSEO_Analytics {
    /**
     * Instancja WPDB
     * @var wpdb
     */
    protected $wpdb;
    
    /**
     * Nazwy tabel używane przez klasę
     * @var array
     */
    protected $tables;
    
    /**
     * Instancja loggera
     * @var object
     */
    protected $logger;
    
    /**
     * Instancja cache'a
     * @var object
     */
    protected $cache;
    
    /**
     * Konstruktor klasy
     *
     * @param object|null $logger Instancja loggera
     * @param object|null $cache  Instancja cache'a
     */
    public function __construct($logger = null, $cache = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = array(
            'analytics' => $wpdb->prefix . 'cleanseo_analytics',
            'stats'     => $wpdb->prefix . 'cleanseo_stats',
            'settings'  => $wpdb->prefix . 'cleanseo_settings'
        );
        
        // Inicjalizuj logger
        if (is_object($logger) && method_exists($logger, 'log')) {
            $this->logger = $logger;
        } else {
            // Utwórz domyślny obiekt logger
            $this->logger = new stdClass();
            $this->logger->log = function($type, $message, $data = array()) {
                error_log("CleanSEO {$type}: {$message}");
            };
        }
        
        // Inicjalizuj cache
        if (is_object($cache) && method_exists($cache, 'get') && method_exists($cache, 'set') && method_exists($cache, 'delete')) {
            $this->cache = $cache;
        } else {
            // Utwórz domyślny obiekt cache
            $this->cache = new stdClass();
            $this->cache->get = function($key) { return false; };
            $this->cache->set = function($key, $value) { return false; };
            $this->cache->delete = function($key) { return false; };
        }
    }
    
    /**
     * Inicjalizuje tabele w bazie danych
     *
     * @return void
     */
    public function initialize_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Tabela analytics
        $sql = "CREATE TABLE {$this->tables['analytics']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            source varchar(50) NOT NULL,
            medium varchar(50) DEFAULT NULL,
            campaign varchar(100) DEFAULT NULL,
            sessions int(11) DEFAULT 0,
            users int(11) DEFAULT 0,
            pageviews int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            bounce_rate float DEFAULT 0,
            avg_time_on_site float DEFAULT 0,
            avg_session_duration int(11) DEFAULT 0,
            details longtext DEFAULT NULL,
            url varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY date (date),
            KEY source (source)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela stats
        $sql = "CREATE TABLE {$this->tables['stats']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            pageviews int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            bounce_rate float DEFAULT 0,
            avg_time_on_site float DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY date (date)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tabela settings
        $sql = "CREATE TABLE {$this->tables['settings']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            gsc_connected tinyint(1) DEFAULT 0,
            gsc_access_token longtext DEFAULT NULL,
            ga4_connected tinyint(1) DEFAULT 0,
            ga4_access_token longtext DEFAULT NULL,
            ga4_property_id varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Dodaj domyślne ustawienia, jeśli tabela jest pusta
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['settings']}");
        if ($count == 0) {
            $this->wpdb->insert(
                $this->tables['settings'],
                array(
                    'gsc_connected' => 0,
                    'ga4_connected' => 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Synchronizuje dane z Google Search Console i GA4 do lokalnej bazy danych
     *
     * @return bool Czy synchronizacja się powiodła
     */
    public function sync_analytics_data() {
        if (!class_exists('Google_Client')) {
            $this->logger->log('sync_error', 'Klasa Google_Client nie istnieje');
            return false;
        }

        $settings = $this->wpdb->get_row("SELECT * FROM {$this->tables['settings']} LIMIT 1");

        if (!$settings || (!$settings->gsc_connected && !$settings->ga4_connected)) {
            $this->logger->log('sync_skipped', 'Analytics nie połączone lub brak tokenów');
            return false;
        }

        $success = true;

        // Sync GSC data
        if ($settings->gsc_connected) {
            try {
                $client = new Google_Client();
                $client->setAccessToken($settings->gsc_access_token);

                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $new_token = json_encode($client->getAccessToken());
                    
                    $updated = $this->wpdb->update(
                        $this->tables['settings'],
                        array('gsc_access_token' => $new_token),
                        array('id' => 1),
                        array('%s'),
                        array('%d')
                    );
                    
                    if ($updated === false) {
                        $this->logger->log('gsc_token_update_error', 'Nie udało się zaktualizować tokenu GSC');
                        return false;
                    }
                }

                $webmasters = new Google_Service_Webmasters($client);
                $site_url = get_site_url();
                
                // Get search analytics data
                $request = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
                $request->setStartDate(date('Y-m-d', strtotime('-30 days')));
                $request->setEndDate(date('Y-m-d'));
                $request->setDimensions(array('query', 'page'));
                $request->setRowLimit(1000);

                $response = $webmasters->searchanalytics->query($site_url, $request);
                
                // Wyczyść stare dane GSC
                $date_today = date('Y-m-d');
                $this->wpdb->delete(
                    $this->tables['analytics'],
                    array(
                        'date' => $date_today,
                        'source' => 'gsc'
                    ),
                    array('%s', '%s')
                );
                
                foreach ($response->getRows() as $row) {
                    $query = $row->getKeys()[0];
                    $page = $row->getKeys()[1];
                    $clicks = $row->getClicks();
                    $impressions = $row->getImpressions();
                    $position = $row->getPosition();
                    $ctr = $row->getCtr();
                    
                    $details = json_encode(array(
                        'query' => $query,
                        'page' => $page,
                        'position' => $position,
                        'ctr' => $ctr
                    ));
                    
                    $inserted = $this->wpdb->insert(
                        $this->tables['analytics'],
                        array(
                            'date' => $date_today,
                            'pageviews' => $clicks,
                            'unique_visitors' => $impressions,
                            'source' => 'gsc',
                            'url' => $page,
                            'details' => $details,
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%d', '%d', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($inserted === false) {
                        $this->logger->log('gsc_data_insert_error', 'Nie udało się zapisać danych GSC', array(
                            'query' => $query,
                            'page' => $page
                        ));
                        $success = false;
                    }
                }
                
                $this->logger->log('gsc_sync_success', 'Synchronizacja GSC zakończona pomyślnie');
            } catch (Exception $e) {
                $this->logger->log('gsc_sync_error', $e->getMessage());
                $success = false;
            }
        }

        // Sync GA4 data
        if ($settings->ga4_connected) {
            try {
                $client = new Google_Client();
                $client->setAccessToken($settings->ga4_access_token);

                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $new_token = json_encode($client->getAccessToken());
                    
                    $updated = $this->wpdb->update(
                        $this->tables['settings'],
                        array('ga4_access_token' => $new_token),
                        array('id' => 1),
                        array('%s'),
                        array('%d')
                    );
                    
                    if ($updated === false) {
                        $this->logger->log('ga4_token_update_error', 'Nie udało się zaktualizować tokenu GA4');
                        return false;
                    }
                }

                $analytics = new Google_Service_AnalyticsData($client);
                
                // Get GA4 data
                $request = new Google_Service_AnalyticsData_RunReportRequest();
                $request->setDateRanges(array(
                    new Google_Service_AnalyticsData_DateRange(array(
                        'start_date' => date('Y-m-d', strtotime('-30 days')),
                        'end_date' => date('Y-m-d')
                    ))
                ));
                $request->setMetrics(array(
                    new Google_Service_AnalyticsData_Metric(array('name' => 'screenPageViews')),
                    new Google_Service_AnalyticsData_Metric(array('name' => 'activeUsers')),
                    new Google_Service_AnalyticsData_Metric(array('name' => 'averageSessionDuration')),
                    new Google_Service_AnalyticsData_Metric(array('name' => 'bounceRate'))
                ));

                // Pobranie property_id z ustawień lub użycie domyślnej wartości
                $property_id = isset($settings->ga4_property_id) && !empty($settings->ga4_property_id) 
                    ? $settings->ga4_property_id 
                    : '0';
                
                $response = $analytics->properties->runReport('properties/' . $property_id, $request);
                
                // Wyczyść stare dane GA4
                $date_today = date('Y-m-d');
                $this->wpdb->delete(
                    $this->tables['analytics'],
                    array(
                        'date' => $date_today,
                        'source' => 'ga4'
                    ),
                    array('%s', '%s')
                );
                
                foreach ($response->getRows() as $row) {
                    $pageviews = $row->getMetricValues()[0]->getValue();
                    $users = $row->getMetricValues()[1]->getValue();
                    $avg_duration = $row->getMetricValues()[2]->getValue();
                    $bounce_rate = $row->getMetricValues()[3]->getValue();
                    
                    $inserted = $this->wpdb->insert(
                        $this->tables['analytics'],
                        array(
                            'date' => $date_today,
                            'pageviews' => $pageviews,
                            'unique_visitors' => $users,
                            'avg_time_on_site' => $avg_duration,
                            'bounce_rate' => $bounce_rate,
                            'source' => 'ga4',
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%d', '%d', '%f', '%f', '%s', '%s')
                    );
                    
                    if ($inserted === false) {
                        $this->logger->log('ga4_data_insert_error', 'Nie udało się zapisać danych GA4');
                        $success = false;
                    }
                }
                
                $this->logger->log('ga4_sync_success', 'Synchronizacja GA4 zakończona pomyślnie');
            } catch (Exception $e) {
                $this->logger->log('ga4_sync_error', $e->getMessage());
                $success = false;
            }
        }
        
        // Wyczyść cache po synchronizacji
        $this->cache->delete('analytics');
        $this->cache->delete('stats');
        
        return $success;
    }

    /**
     * Pobiera dane analityczne za określony okres
     *
     * @param string $start_date Data początkowa (format Y-m-d)
     * @param string $end_date Data końcowa (format Y-m-d)
     * @param string|null $source Opcjonalnie źródło danych (gsc, ga4)
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_analytics_data($start_date, $end_date, $source = null) {
        // Walidacja dat
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            $this->logger->log('analytics_data_error', 'Nieprawidłowy format daty', array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ));
            return false;
        }
        
        // Sprawdź, czy daty są w prawidłowej kolejności
        if (strtotime($start_date) > strtotime($end_date)) {
            $this->logger->log('analytics_data_error', 'Data początkowa jest późniejsza niż końcowa', array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ));
            return false;
        }
        
        // Tworzenie zapytania z parametrami
        $query = "SELECT 
                date,
                SUM(pageviews) as total_pageviews,
                SUM(unique_visitors) as total_visitors,
                AVG(avg_time_on_site) as avg_time,
                AVG(bounce_rate) as avg_bounce_rate
            FROM {$this->tables['analytics']}
            WHERE date BETWEEN %s AND %s";
        $params = array($start_date, $end_date);
        
        if ($source) {
            $query .= " AND source = %s";
            $params[] = $source;
        }
        
        $query .= " GROUP BY date ORDER BY date ASC";
        
        $prepared_query = $this->wpdb->prepare($query, $params);
        
        try {
            $results = $this->wpdb->get_results($prepared_query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('analytics_data_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $prepared_query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('analytics_data_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $prepared_query
            ));
            return false;
        }
    }

    /**
     * AJAX: Pobierz dane analityczne do panelu
     */
    public function ajax_get_analytics_data() {
        // Sprawdź nonce i uprawnienia
        check_ajax_referer('cleanseo_get_analytics', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Brak uprawnień.'));
        }
        
        // Pobierz i sanityzuj parametry
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-01');
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d');
        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : null;
        
        // Walidacja dat
        if (!$this->validate_date($start) || !$this->validate_date($end)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format daty.'));
        }
        
        // Walidacja źródła
        if ($source !== null && !in_array($source, array('gsc', 'ga4'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe źródło danych.'));
        }
        
        // Pobierz dane
        $data = $this->get_analytics_data($start, $end, $source);
        
        if ($data === false) {
            wp_send_json_error(array('message' => 'Błąd podczas pobierania danych.'));
        }
        
        wp_send_json_success($data);
    }

    /**
     * AJAX: Eksport danych do CSV
     */
    public function ajax_export_csv() {
        // Sprawdź nonce i uprawnienia
        check_ajax_referer('cleanseo_export_analytics', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }
        
        // Pobierz i sanityzuj parametry
        $start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : date('Y-m-01');
        $end = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : date('Y-m-d');
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : null;
        
        // Walidacja dat
        if (!$this->validate_date($start) || !$this->validate_date($end)) {
            wp_die('Nieprawidłowy format daty.');
        }
        
        // Walidacja źródła
        if ($source !== null && !in_array($source, array('gsc', 'ga4'))) {
            wp_die('Nieprawidłowe źródło danych.');
        }
        
        // Pobierz dane
        $data = $this->get_analytics_data($start, $end, $source);
        
        if ($data === false) {
            wp_die('Błąd podczas pobierania danych.');
        }
        
        // Ustaw nagłówki
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=analytics-' . date('Y-m-d') . '.csv');
        
        // Wygeneruj CSV
        $output = fopen('php://output', 'w');
        
        // Dodaj BOM dla UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // Nagłówki
        fputcsv($output, array('Data', 'Odsłony', 'Unikalni użytkownicy', 'Śr. czas na stronie', 'Współczynnik odrzuceń'));
        
        // Dane
        foreach ($data as $row) {
            fputcsv($output, array(
                $row->date,
                $row->total_pageviews,
                $row->total_visitors,
                $row->avg_time,
                $row->avg_bounce_rate
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * AJAX: Eksport danych do PDF (TCPDF)
     */
    public function ajax_export_pdf() {
        // Sprawdź nonce i uprawnienia
        check_ajax_referer('cleanseo_export_analytics', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }
        
        // Pobierz i sanityzuj parametry
        $start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : date('Y-m-01');
        $end = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : date('Y-m-d');
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : null;
        
        // Walidacja dat
        if (!$this->validate_date($start) || !$this->validate_date($end)) {
            wp_die('Nieprawidłowy format daty.');
        }
        
        // Walidacja źródła
        if ($source !== null && !in_array($source, array('gsc', 'ga4'))) {
            wp_die('Nieprawidłowe źródło danych.');
        }
        
        // Pobierz dane
        $data = $this->get_analytics_data($start, $end, $source);
        
        if ($data === false) {
            wp_die('Błąd podczas pobierania danych.');
        }
        
        // Sprawdź, czy TCPDF jest dostępny
        $tcpdf_path = ABSPATH . 'wp-content/plugins/cleanseo-optimizer/includes/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            wp_die('Biblioteka TCPDF nie jest dostępna.');
        }
        
        require_once($tcpdf_path);
        
        try {
            // Utwórz nowy dokument PDF
            $pdf = new TCPDF();
            
            // Ustaw metadane dokumentu
            $pdf->SetCreator('CleanSEO Optimizer');
            $pdf->SetAuthor('CleanSEO');
            $pdf->SetTitle('Raport analityczny - ' . date('Y-m-d'));
            $pdf->SetSubject('Dane analityczne');
            
            // Dodaj stronę
            $pdf->AddPage();
            
            // Ustaw czcionkę
            $pdf->SetFont('dejavusans', '', 10);
            
            // Dodaj nagłówek
            $pdf->Cell(0, 10, 'Raport analityczny - ' . date('Y-m-d'), 0, 1, 'C');
            $pdf->Ln(5);
            
            // Przygotuj tabelę
            $tbl = '<table border="1" cellpadding="4">
                <tr style="background-color: #f2f2f2;">
                    <th>Data</th>
                    <th>Odsłony</th>
                    <th>Unikalni użytkownicy</th>
                    <th>Śr. czas na stronie</th>
                    <th>Współczynnik odrzuceń</th>
                </tr>';
            
            // Dodaj dane
            foreach ($data as $row) {
                $tbl .= '<tr>
                    <td>' . htmlspecialchars($row->date) . '</td>
                    <td>' . htmlspecialchars($row->total_pageviews) . '</td>
                    <td>' . htmlspecialchars($row->total_visitors) . '</td>
                    <td>' . htmlspecialchars($row->avg_time) . '</td>
                    <td>' . htmlspecialchars($row->avg_bounce_rate) . '</td>
                </tr>';
            }
            
            $tbl .= '</table>';
            
            // Dodaj tabelę do PDF
            $pdf->writeHTML($tbl, true, false, false, false, '');
            
            // Wygeneruj PDF
            $pdf->Output('analytics-' . date('Y-m-d') . '.pdf', 'D');
        } catch (Exception $e) {
            wp_die('Błąd podczas generowania PDF: ' . $e->getMessage());
        }
        
        exit;
    }

    /**
     * AJAX: Ręczne odświeżenie danych analitycznych
     */
    public function ajax_manual_sync() {
        // Sprawdź nonce i uprawnienia
        check_ajax_referer('cleanseo_manual_sync', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Brak uprawnień.'));
        }
        
        // Wykonaj synchronizację
        $result = $this->sync_analytics_data();
        
        if ($result) {
            wp_send_json_success(array('message' => 'Dane zostały odświeżone.'));
        } else {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas odświeżania danych.'));
        }
    }

    /**
     * Zapisuje dane analityczne do bazy
     *
     * @param string $date Data w formacie Y-m-d
     * @param string $source Źródło danych
     * @param string $medium Medium
     * @param string $campaign Kampania
     * @param int $sessions Liczba sesji
     * @param int $users Liczba użytkowników
     * @param int $pageviews Liczba odsłon
     * @param float $bounce_rate Współczynnik odrzuceń
     * @param int $avg_session_duration Średni czas trwania sesji
     * @return bool Czy zapis się powiódł
     */
    public function save_analytics($date, $source, $medium, $campaign, $sessions, $users, $pageviews, $bounce_rate, $avg_session_duration) {
        // Walidacja danych
        if (!$this->validate_date($date)) {
            $this->logger->log('analytics_save_error', 'Nieprawidłowy format daty', array('date' => $date));
            return false;
        }
        
        if (!is_string($source) || empty($source)) {
            $this->logger->log('analytics_save_error', 'Nieprawidłowe źródło danych', array('source' => $source));
            return false;
        }
        
        // Przygotowanie danych do zapisu
        $data = array(
            'date' => $date,
            'source' => $source,
            'medium' => $medium,
            'campaign' => $campaign,
            'sessions' => intval($sessions),
            'users' => intval($users),
            'pageviews' => intval($pageviews),
            'bounce_rate' => floatval($bounce_rate),
            'avg_session_duration' => intval($avg_session_duration),
            'created_at' => current_time('mysql')
        );
        
        $format = array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%d', '%s');
        
        // Zapis do bazy
        $result = $this->wpdb->insert($this->tables['analytics'], $data, $format);
        
        if ($result) {
            $this->logger->log('analytics_saved', "Zapisano dane analityczne dla daty: {$date}", array(
                'date' => $date,
                'source' => $source,
                'medium' => $medium,
                'campaign' => $campaign
            ));
            
            // Wyczyść cache
            $this->cache->delete('analytics');
            
            return true;
        } else {
            $this->logger->log('analytics_save_error', "Nie udało się zapisać danych: " . $this->wpdb->last_error, array(
                'date' => $date,
                'source' => $source
            ));
            
            return false;
        }
    }

    /**
     * Pobiera dane analityczne z bazy
     *
     * @param string $start_date Data początkowa w formacie Y-m-d
     * @param string $end_date Data końcowa w formacie Y-m-d
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_analytics($start_date, $end_date) {
        // Sprawdź, czy dane są w cache
        $cache_key = 'analytics_' . md5($start_date . $end_date);
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Walidacja dat
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            $this->logger->log('analytics_get_error', 'Nieprawidłowy format daty', array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ));
            return false;
        }
        
        // Sprawdź, czy daty są w prawidłowej kolejności
        if (strtotime($start_date) > strtotime($end_date)) {
            $this->logger->log('analytics_get_error', 'Data początkowa jest późniejsza niż końcowa', array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ));
            return false;
        }
        
        // Przygotuj zapytanie
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['analytics']}
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC",
            $start_date,
            $end_date
        );
        
        try {
            // Wykonaj zapytanie
            $analytics = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('analytics_get_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            // Zapisz do cache'a
            $this->cache->set($cache_key, $analytics);
            
            return $analytics;
        } catch (Exception $e) {
            $this->logger->log('analytics_get_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Pobiera statystyki z bazy danych
     *
     * @param string $start_date Data początkowa w formacie Y-m-d
     * @param string $end_date Data końcowa w formacie Y-m-d
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_stats($start_date, $end_date) {
        // Sprawdź, czy dane są w cache
        $cache_key = 'stats_' . md5($start_date . $end_date);
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Walidacja dat
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            $this->logger->log('stats_get_error', 'Nieprawidłowy format daty', array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ));
            return false;
        }
        
        // Przygotuj zapytanie
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['stats']}
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC",
            $start_date,
            $end_date
        );
        
        try {
            // Wykonaj zapytanie
            $stats = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('stats_get_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            // Zapisz do cache'a
            $this->cache->set($cache_key, $stats);
            
            return $stats;
        } catch (Exception $e) {
            $this->logger->log('stats_get_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Zapisuje statystyki do bazy danych
     *
     * @param string $date Data w formacie Y-m-d
     * @param int $pageviews Liczba odsłon
     * @param int $unique_visitors Liczba unikalnych użytkowników
     * @param float $bounce_rate Współczynnik odrzuceń
     * @param float $avg_time_on_site Średni czas na stronie
     * @return bool Czy zapis się powiódł
     */
    public function save_stats($date, $pageviews, $unique_visitors, $bounce_rate, $avg_time_on_site) {
        // Walidacja danych
        if (!$this->validate_date($date)) {
            $this->logger->log('stats_save_error', 'Nieprawidłowy format daty', array('date' => $date));
            return false;
        }
        
        // Przygotowanie danych do zapisu
        $data = array(
            'date' => $date,
            'pageviews' => intval($pageviews),
            'unique_visitors' => intval($unique_visitors),
            'bounce_rate' => floatval($bounce_rate),
            'avg_time_on_site' => floatval($avg_time_on_site),
            'created_at' => current_time('mysql')
        );
        
        $format = array('%s', '%d', '%d', '%f', '%f', '%s');
        
        // Zapis do bazy
        $result = $this->wpdb->insert($this->tables['stats'], $data, $format);
        
        if ($result) {
            $this->logger->log('stats_saved', "Zapisano statystyki dla daty: {$date}", array(
                'date' => $date,
                'pageviews' => $pageviews,
                'unique_visitors' => $unique_visitors
            ));
            
            // Wyczyść cache
            $this->cache->delete('stats');
            
            return true;
        } else {
            $this->logger->log('stats_save_error', "Nie udało się zapisać statystyk: " . $this->wpdb->last_error, array(
                'date' => $date
            ));
            
            return false;
        }
    }

    /**
     * Zwraca listę topowych stron z największą liczbą odsłon
     *
     * @param int $limit Limit wyników
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_top_pages($limit = 10) {
        // Walidacja parametrów
        $limit = intval($limit);
        if ($limit <= 0) {
            $limit = 10;
        }
        
        // Przygotuj zapytanie
        $query = $this->wpdb->prepare(
            "SELECT url, SUM(pageviews) as total_pageviews
            FROM {$this->tables['analytics']}
            WHERE url IS NOT NULL AND url != ''
            GROUP BY url
            ORDER BY total_pageviews DESC
            LIMIT %d",
            $limit
        );
        
        try {
            // Wykonaj zapytanie
            $results = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('top_pages_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('top_pages_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Zwraca top źródła ruchu
     *
     * @param int $limit Limit wyników
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_top_sources($limit = 10) {
        // Walidacja parametrów
        $limit = intval($limit);
        if ($limit <= 0) {
            $limit = 10;
        }
        
        // Przygotuj zapytanie
        $query = $this->wpdb->prepare(
            "SELECT source, SUM(sessions) as total_sessions
            FROM {$this->tables['analytics']}
            WHERE source IS NOT NULL AND source != ''
            GROUP BY source
            ORDER BY total_sessions DESC
            LIMIT %d",
            $limit
        );
        
        try {
            // Wykonaj zapytanie
            $results = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('top_sources_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('top_sources_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Zwraca top kampanie
     *
     * @param int $limit Limit wyników
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_top_campaigns($limit = 10) {
        // Walidacja parametrów
        $limit = intval($limit);
        if ($limit <= 0) {
            $limit = 10;
        }
        
        // Przygotuj zapytanie
        $query = $this->wpdb->prepare(
            "SELECT campaign, SUM(sessions) as total_sessions
            FROM {$this->tables['analytics']}
            WHERE campaign IS NOT NULL AND campaign != ''
            GROUP BY campaign
            ORDER BY total_sessions DESC
            LIMIT %d",
            $limit
        );
        
        try {
            // Wykonaj zapytanie
            $results = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('top_campaigns_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('top_campaigns_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Zwraca trend średniego bounce rate na podstawie danych historycznych
     *
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_bounce_rate_trend() {
        // Przygotuj zapytanie
        $query = "SELECT date, AVG(bounce_rate) as avg_bounce_rate
            FROM {$this->tables['analytics']}
            WHERE bounce_rate IS NOT NULL
            GROUP BY date
            ORDER BY date ASC";
        
        try {
            // Wykonaj zapytanie
            $results = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('bounce_rate_trend_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('bounce_rate_trend_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }

    /**
     * Zwraca trend średniego czasu trwania sesji
     *
     * @return array|false Tablica wyników lub false w przypadku błędu
     */
    public function get_avg_session_duration_trend() {
        // Przygotuj zapytanie
        $query = "SELECT date, AVG(avg_session_duration) as avg_duration
            FROM {$this->tables['analytics']}
            WHERE avg_session_duration IS NOT NULL
            GROUP BY date
            ORDER BY date ASC";
        
        try {
            // Wykonaj zapytanie
            $results = $this->wpdb->get_results($query);
            
            if ($this->wpdb->last_error) {
                $this->logger->log('avg_session_duration_trend_error', 'Błąd bazy danych: ' . $this->wpdb->last_error, array(
                    'query' => $query
                ));
                return false;
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->log('avg_session_duration_trend_error', 'Wyjątek: ' . $e->getMessage(), array(
                'query' => $query
            ));
            
            return false;
        }
    }
    
    /**
     * Waliduje format daty Y-m-d
     *
     * @param string $date Data do sprawdzenia
     * @return bool Czy data jest prawidłowa
     */
    private function validate_date($date) {
        if (!is_string($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Renderuje dashboard analityczny
     */
    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        
        // Pobranie dat
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Pobranie danych
        $data = $this->get_analytics_data($start_date, $end_date);
        $top_pages = $this->get_top_pages(10);
        $top_sources = $this->get_top_sources(10);
        $bounce_rate_trend = $this->get_bounce_rate_trend();
        
        // Sprawdzenie połączenia z Google API
        $settings = $this->wpdb->get_row("SELECT * FROM {$this->tables['settings']} LIMIT 1");
        $is_connected = ($settings && ($settings->gsc_connected || $settings->ga4_connected));
        
        wp_enqueue_style('cleanseo-admin', plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-admin.css', array(), '1.0.0');
        wp_enqueue_script('chart-js', plugin_dir_url(dirname(__FILE__)) . 'admin/js/chart.min.js', array(), '3.7.0', true);
        wp_enqueue_script('cleanseo-admin', plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-admin.js', array('jquery', 'chart-js'), '1.0.0', true);
        
        wp_localize_script('cleanseo-admin', 'cleanseoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_get_analytics'),
            'export_nonce' => wp_create_nonce('cleanseo_export_analytics'),
            'sync_nonce' => wp_create_nonce('cleanseo_manual_sync'),
            'analytics' => $data,
            'top_pages' => $top_pages,
            'top_sources' => $top_sources,
            'bounce_rate_trend' => $bounce_rate_trend,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'i18n' => array(
                'pageviews' => __('Odsłony', 'cleanseo-optimizer'),
                'visitors' => __('Unikalni użytkownicy', 'cleanseo-optimizer'),
                'loading' => __('Ładowanie...', 'cleanseo-optimizer'),
                'error' => __('Błąd pobierania danych', 'cleanseo-optimizer'),
                'no_data' => __('Brak danych dla wybranego okresu', 'cleanseo-optimizer')
            )
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('CleanSEO - Analityka', 'cleanseo-optimizer'); ?></h1>
            
            <?php if (!$is_connected): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Nie skonfigurowano połączenia z Google Analytics. Przejdź do ustawień, aby podłączyć swoje konto.', 'cleanseo-optimizer'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=cleanseo-settings'); ?>" class="button button-primary"><?php _e('Przejdź do ustawień', 'cleanseo-optimizer'); ?></a></p>
                </div>
            <?php endif; ?>
            
            <div class="cleanseo-controls">
                <div class="cleanseo-date-range">
                    <label for="cleanseo-start-date"><?php _e('Od:', 'cleanseo-optimizer'); ?></label>
                    <input type="date" id="cleanseo-start-date" value="<?php echo esc_attr($start_date); ?>">
                    
                    <label for="cleanseo-end-date"><?php _e('Do:', 'cleanseo-optimizer'); ?></label>
                    <input type="date" id="cleanseo-end-date" value="<?php echo esc_attr($end_date); ?>">
                    
                    <button id="cleanseo-update-data" class="button button-primary"><?php _e('Aktualizuj', 'cleanseo-optimizer'); ?></button>
                </div>
                
                <div class="cleanseo-export">
                    <button id="cleanseo-manual-sync" class="button"><?php _e('Odśwież dane', 'cleanseo-optimizer'); ?></button>
                    <button id="cleanseo-export-csv" class="button"><?php _e('Eksportuj CSV', 'cleanseo-optimizer'); ?></button>
                    <button id="cleanseo-export-pdf" class="button"><?php _e('Eksportuj PDF', 'cleanseo-optimizer'); ?></button>
                </div>
            </div>
            
            <div class="cleanseo-dashboard">
                <div class="cleanseo-card cleanseo-summary">
                    <h2><?php _e('Podsumowanie', 'cleanseo-optimizer'); ?></h2>
                    <div class="cleanseo-stats">
                        <div class="cleanseo-stat">
                            <h3><?php _e('Odsłony', 'cleanseo-optimizer'); ?></h3>
                            <div id="total-pageviews" class="cleanseo-stat-value">0</div>
                        </div>
                        <div class="cleanseo-stat">
                            <h3><?php _e('Unikalni użytkownicy', 'cleanseo-optimizer'); ?></h3>
                            <div id="total-visitors" class="cleanseo-stat-value">0</div>
                        </div>
                        <div class="cleanseo-stat">
                            <h3><?php _e('Średni czas na stronie', 'cleanseo-optimizer'); ?></h3>
                            <div id="avg-time" class="cleanseo-stat-value">0</div>
                        </div>
                        <div class="cleanseo-stat">
                            <h3><?php _e('Współczynnik odrzuceń', 'cleanseo-optimizer'); ?></h3>
                            <div id="bounce-rate" class="cleanseo-stat-value">0%</div>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-card cleanseo-chart">
                    <h2><?php _e('Ruch na stronie', 'cleanseo-optimizer'); ?></h2>
                    <canvas id="traffic-chart"></canvas>
                </div>
                
                <div class="cleanseo-card cleanseo-pages">
                    <h2><?php _e('Najlepsze strony', 'cleanseo-optimizer'); ?></h2>
                    <div id="top-pages">
                        <?php if (empty($top_pages)): ?>
                            <p class="cleanseo-no-data"><?php _e('Brak danych', 'cleanseo-optimizer'); ?></p>
                        <?php else: ?>
                            <table class="cleanseo-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('URL', 'cleanseo-optimizer'); ?></th>
                                        <th><?php _e('Odsłony', 'cleanseo-optimizer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_pages as $page): ?>
                                        <tr>
                                            <td><a href="<?php echo esc_url($page->url); ?>" target="_blank"><?php echo esc_html($page->url); ?></a></td>
                                            <td><?php echo esc_html($page->total_pageviews); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="cleanseo-card cleanseo-sources">
                    <h2><?php _e('Źródła ruchu', 'cleanseo-optimizer'); ?></h2>
                    <div id="top-sources">
                        <?php if (empty($top_sources)): ?>
                            <p class="cleanseo-no-data"><?php _e('Brak danych', 'cleanseo-optimizer'); ?></p>
                        <?php else: ?>
                            <table class="cleanseo-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Źródło', 'cleanseo-optimizer'); ?></th>
                                        <th><?php _e('Sesje', 'cleanseo-optimizer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_sources as $source): ?>
                                        <tr>
                                            <td><?php echo esc_html($source->source); ?></td>
                                            <td><?php echo esc_html($source->total_sessions); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="cleanseo-card cleanseo-bounce-rate">
                    <h2><?php _e('Trend współczynnika odrzuceń', 'cleanseo-optimizer'); ?></h2>
                    <canvas id="bounce-rate-chart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
}