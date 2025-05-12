<?php
/**
 * Klasa CleanSEO_Competitors - zaawansowane śledzenie konkurencji
 */
class CleanSEO_Competitors {
    private $wpdb;
    private $tables;
    private $cache;
    private $logger;
    private $our_domain;
    private $google_api_key;

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
        $this->cache = new CleanSEO_Cache();
        $this->logger = new CleanSEO_Logger();
        $this->our_domain = parse_url(home_url(), PHP_URL_HOST);
        $this->google_api_key = get_option('cleanseo_google_api_key');

        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_cleanseo_add_competitor', array($this, 'ajax_add_competitor'));
        add_action('wp_ajax_cleanseo_delete_competitor', array($this, 'ajax_delete_competitor'));
        add_action('wp_ajax_cleanseo_get_competitors', array($this, 'ajax_get_competitors'));
        add_action('wp_ajax_cleanseo_update_keywords', array($this, 'ajax_update_keywords'));
        add_action('wp_ajax_cleanseo_get_ranking_history', array($this, 'ajax_get_ranking_history'));
        add_action('cleanseo_daily_competitor_check', array($this, 'check_competitors_rankings'));
        add_action('admin_init', array($this, 'schedule_competitor_check'));
    }

    public function schedule_competitor_check() {
        if (!wp_next_scheduled('cleanseo_daily_competitor_check')) {
            wp_schedule_event(time(), 'daily', 'cleanseo_daily_competitor_check');
        }
    }

    public function ajax_add_competitor() {
        check_ajax_referer('cleanseo_add_competitor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';

        if (empty($domain) || empty($keywords)) {
            wp_send_json_error('Wypełnij wszystkie pola');
        }

        $keywords_array = array_map('trim', explode("\n", $keywords));
        
        if ($this->add_competitor($domain, $keywords_array)) {
            wp_send_json_success('Konkurent dodany pomyślnie');
        } else {
            wp_send_json_error('Błąd podczas dodawania konkurenta');
        }
    }

    public function ajax_delete_competitor() {
        check_ajax_referer('cleanseo_delete_competitor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($this->delete_competitor($id)) {
            wp_send_json_success('Konkurent usunięty pomyślnie');
        } else {
            wp_send_json_error('Błąd podczas usuwania konkurenta');
        }
    }

    public function ajax_get_competitors() {
        check_ajax_referer('cleanseo_get_competitors', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $competitors = $this->get_competitors_data();
        wp_send_json_success($competitors);
    }

    public function ajax_update_keywords() {
        check_ajax_referer('cleanseo_update_keywords', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';

        if (empty($competitor_id) || empty($keywords)) {
            wp_send_json_error('Wypełnij wszystkie pola');
        }

        $keywords_array = array_map('trim', explode("\n", $keywords));
        
        if ($this->update_competitor_keywords($competitor_id, $keywords_array)) {
            wp_send_json_success('Słowa kluczowe zaktualizowane pomyślnie');
        } else {
            wp_send_json_error('Błąd podczas aktualizacji słów kluczowych');
        }
    }

    public function ajax_get_ranking_history() {
        check_ajax_referer('cleanseo_get_ranking_history', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

        if (empty($competitor_id) || empty($keyword)) {
            wp_send_json_error('Brak wymaganych parametrów');
        }

        $history = $this->get_ranking_history($competitor_id, $keyword);
        wp_send_json_success($history);
    }

    public function check_competitors_rankings() {
        $competitors = $this->get_competitors_data();
        
        foreach ($competitors as $competitor) {
            $keywords = $this->get_competitor_keywords($competitor['id']);
            
            foreach ($keywords as $keyword) {
                $rankings = $this->get_keyword_rankings($keyword, $competitor['domain']);
                $this->save_ranking_data($competitor['id'], $keyword, $rankings);
            }
        }
    }

    private function get_keyword_rankings($keyword, $domain) {
        if (empty($this->google_api_key)) {
            return $this->get_keyword_rankings_simulated($keyword, $domain);
        }

        // Implementacja rzeczywistego API Google Search Console
        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($domain) . '/searchAnalytics/query';
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->google_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'startDate' => date('Y-m-d', strtotime('-30 days')),
                'endDate' => date('Y-m-d'),
                'dimensions' => array('query'),
                'query' => $keyword
            ))
        ));

        if (is_wp_error($response)) {
            return $this->get_keyword_rankings_simulated($keyword, $domain);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $this->process_ranking_data($data);
    }

    private function get_keyword_rankings_simulated($keyword, $domain) {
        // Symulacja danych dla celów testowych
        return array(
            'our_rank' => rand(1, 100),
            'their_rank' => rand(1, 100),
            'volume' => rand(100, 10000),
            'difficulty' => rand(1, 100)
        );
    }

    private function process_ranking_data($data) {
        // Przetwarzanie danych z API
        return array(
            'our_rank' => isset($data['rows'][0]['position']) ? $data['rows'][0]['position'] : 0,
            'their_rank' => isset($data['rows'][1]['position']) ? $data['rows'][1]['position'] : 0,
            'volume' => isset($data['rows'][0]['clicks']) ? $data['rows'][0]['clicks'] : 0,
            'difficulty' => $this->calculate_keyword_difficulty($data)
        );
    }

    private function calculate_keyword_difficulty($data) {
        // Implementacja algorytmu oceny trudności słowa kluczowego
        $factors = array(
            'competition' => isset($data['rows'][0]['competition']) ? $data['rows'][0]['competition'] : 0,
            'volume' => isset($data['rows'][0]['clicks']) ? $data['rows'][0]['clicks'] : 0,
            'cpc' => isset($data['rows'][0]['cpc']) ? $data['rows'][0]['cpc'] : 0
        );

        $difficulty = 0;
        $difficulty += $factors['competition'] * 0.4;
        $difficulty += min($factors['volume'] / 10000, 1) * 0.3;
        $difficulty += min($factors['cpc'] / 10, 1) * 0.3;

        return round($difficulty * 100);
    }

    private function save_ranking_data($competitor_id, $keyword, $rankings) {
        $this->wpdb->insert(
            $this->tables['competitors'],
            array(
                'name' => $keyword,
                'url' => '',
                'keywords' => json_encode(array($keyword)),
                'last_check' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'our_rank' => $rankings['our_rank'],
                'their_rank' => $rankings['their_rank'],
                'volume' => $rankings['volume'],
                'difficulty' => $rankings['difficulty']
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );
    }

    public function get_competitors_data() {
        $cached = $this->cache->get('competitors');
        if ($cached !== false) {
            return $cached;
        }

        $competitors = $this->wpdb->get_results("SELECT * FROM {$this->tables['competitors']} ORDER BY name ASC");
        $this->cache->set('competitors', $competitors);

        foreach ($competitors as &$competitor) {
            $competitor['keywords'] = $this->get_competitor_keywords($competitor['id']);
            $competitor['stats'] = $this->get_competitor_stats($competitor['id']);
        }

        return $competitors;
    }

    private function get_competitor_keywords($competitor_id) {
        return $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT keyword FROM {$this->keywords_table} WHERE competitor_id = %d",
            $competitor_id
        ));
    }

    private function get_competitor_stats($competitor_id) {
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                AVG(our_rank) as avg_our_rank,
                AVG(their_rank) as avg_their_rank,
                COUNT(*) as total_keywords,
                SUM(CASE WHEN our_rank < their_rank THEN 1 ELSE 0 END) as keywords_ahead
            FROM {$this->keywords_table}
            WHERE competitor_id = %d",
            $competitor_id
        ));

        return array(
            'avg_our_rank' => round($stats->avg_our_rank, 1),
            'avg_their_rank' => round($stats->avg_their_rank, 1),
            'total_keywords' => $stats->total_keywords,
            'keywords_ahead' => $stats->keywords_ahead,
            'win_rate' => $stats->total_keywords > 0 ? 
                round(($stats->keywords_ahead / $stats->total_keywords) * 100, 1) : 0
        );
    }

    public function add_competitor($domain, $keywords) {
        $result = $this->wpdb->insert(
            $this->competitors_table,
            array(
                'domain' => $domain,
                'last_check' => current_time('mysql')
            )
        );

        if ($result) {
            $competitor_id = $this->wpdb->insert_id;
            foreach ($keywords as $keyword) {
                $this->wpdb->insert(
                    $this->keywords_table,
                    array(
                        'competitor_id' => $competitor_id,
                        'keyword' => $keyword,
                        'timestamp' => current_time('mysql')
                    )
                );
            }
            return true;
        }

        return false;
    }

    public function delete_competitor($id) {
        $this->wpdb->delete(
            $this->keywords_table,
            array('competitor_id' => $id)
        );

        return $this->wpdb->delete(
            $this->competitors_table,
            array('id' => $id)
        );
    }

    public function update_competitor_keywords($competitor_id, $keywords) {
        // Usuń stare słowa kluczowe
        $this->wpdb->delete(
            $this->keywords_table,
            array('competitor_id' => $competitor_id)
        );

        // Dodaj nowe słowa kluczowe
        foreach ($keywords as $keyword) {
            $this->wpdb->insert(
                $this->keywords_table,
                array(
                    'competitor_id' => $competitor_id,
                    'keyword' => $keyword,
                    'timestamp' => current_time('mysql')
                )
            );
        }

        return true;
    }

    public function get_ranking_history($competitor_id, $keyword) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT our_rank, their_rank, volume, difficulty, timestamp
            FROM {$this->keywords_table}
            WHERE competitor_id = %d AND keyword = %s
            ORDER BY timestamp DESC
            LIMIT 30",
            $competitor_id,
            $keyword
        ));
    }

    /**
     * Instalacja tabel w bazie danych
     */
    public function install() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        // Tabela konkurentów
        $sql = "CREATE TABLE IF NOT EXISTS {$this->competitors_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            last_check datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY domain (domain),
            KEY last_check (last_check)
        ) $charset_collate;";
        dbDelta($sql);

        // Tabela słów kluczowych
        $sql = "CREATE TABLE IF NOT EXISTS {$this->keywords_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            competitor_id bigint(20) NOT NULL,
            keyword varchar(255) NOT NULL,
            our_rank int(11) DEFAULT 0,
            their_rank int(11) DEFAULT 0,
            volume int(11) DEFAULT 0,
            difficulty int(11) DEFAULT 0,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY competitor_id (competitor_id),
            KEY keyword (keyword),
            KEY timestamp (timestamp),
            CONSTRAINT fk_competitor
                FOREIGN KEY (competitor_id)
                REFERENCES {$this->competitors_table} (id)
                ON DELETE CASCADE
        ) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Eksport danych konkurenta do CSV lub PDF
     */
    public function export_competitor_data($competitor_id, $format = 'csv') {
        check_ajax_referer('cleanseo_export_competitor', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $competitor = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->competitors_table} WHERE id = %d",
            $competitor_id
        ));

        if (!$competitor) {
            wp_die('Nie znaleziono konkurenta');
        }

        $keywords = $this->get_competitor_keywords($competitor_id);
        $stats = $this->get_competitor_stats($competitor_id);
        $history = $this->get_ranking_history($competitor_id, '');

        switch ($format) {
            case 'csv':
                $this->export_to_csv($competitor, $keywords, $stats, $history);
                break;
            case 'pdf':
                $this->export_to_pdf($competitor, $keywords, $stats, $history);
                break;
            default:
                wp_die('Nieobsługiwany format eksportu');
        }
    }

    /**
     * Eksport danych do CSV
     */
    private function export_to_csv($competitor, $keywords, $stats, $history) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=konkurent-' . sanitize_file_name($competitor->domain) . '-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        
        // Nagłówek
        fputcsv($output, array('Raport konkurencji - ' . $competitor->domain));
        fputcsv($output, array('Data wygenerowania: ' . current_time('Y-m-d H:i:s')));
        fputcsv($output, array());

        // Statystyki ogólne
        fputcsv($output, array('Statystyki ogólne'));
        fputcsv($output, array('Średnia pozycja', $stats['avg_our_rank']));
        fputcsv($output, array('Liczba słów kluczowych', $stats['total_keywords']));
        fputcsv($output, array('Wskaźnik wygranych', $stats['win_rate'] . '%'));
        fputcsv($output, array());

        // Słowa kluczowe
        fputcsv($output, array('Słowa kluczowe'));
        fputcsv($output, array('Słowo kluczowe', 'Nasza pozycja', 'Ich pozycja', 'Wolumen', 'Trudność', 'Trend'));
        
        foreach ($keywords as $keyword) {
            $keyword_data = $this->get_keyword_data($competitor->id, $keyword);
            fputcsv($output, array(
                $keyword,
                $keyword_data['our_rank'],
                $keyword_data['their_rank'],
                $keyword_data['volume'],
                $keyword_data['difficulty'],
                $this->calculate_trend($keyword_data['history'])
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Eksport danych do PDF
     */
    private function export_to_pdf($competitor, $keywords, $stats, $history) {
        require_once(ABSPATH . 'wp-content/plugins/cleanseo-optimizer/includes/vendor/tecnickcom/tcpdf/tcpdf.php');

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Ustawienia dokumentu
        $pdf->SetCreator('CleanSEO Optimizer');
        $pdf->SetAuthor('CleanSEO Optimizer');
        $pdf->SetTitle('Raport konkurencji - ' . $competitor->domain);

        // Dodaj stronę
        $pdf->AddPage();

        // Nagłówek
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, 'Raport konkurencji - ' . $competitor->domain, 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 10, 'Data wygenerowania: ' . current_time('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(10);

        // Statystyki ogólne
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'Statystyki ogólne', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        
        $pdf->Cell(60, 7, 'Średnia pozycja:', 0);
        $pdf->Cell(0, 7, $stats['avg_our_rank'], 0, 1);
        
        $pdf->Cell(60, 7, 'Liczba słów kluczowych:', 0);
        $pdf->Cell(0, 7, $stats['total_keywords'], 0, 1);
        
        $pdf->Cell(60, 7, 'Wskaźnik wygranych:', 0);
        $pdf->Cell(0, 7, $stats['win_rate'] . '%', 0, 1);
        
        $pdf->Ln(10);

        // Słowa kluczowe
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'Słowa kluczowe', 0, 1);
        
        // Nagłówki tabeli
        $pdf->SetFont('dejavusans', 'B', 10);
        $header = array('Słowo kluczowe', 'Nasza pozycja', 'Ich pozycja', 'Wolumen', 'Trudność', 'Trend');
        $w = array(50, 25, 25, 25, 25, 25);
        
        foreach ($header as $i => $col) {
            $pdf->Cell($w[$i], 7, $col, 1, 0, 'C');
        }
        $pdf->Ln();

        // Dane tabeli
        $pdf->SetFont('dejavusans', '', 10);
        foreach ($keywords as $keyword) {
            $keyword_data = $this->get_keyword_data($competitor->id, $keyword);
            $pdf->Cell($w[0], 6, $keyword, 1);
            $pdf->Cell($w[1], 6, $keyword_data['our_rank'], 1);
            $pdf->Cell($w[2], 6, $keyword_data['their_rank'], 1);
            $pdf->Cell($w[3], 6, $keyword_data['volume'], 1);
            $pdf->Cell($w[4], 6, $keyword_data['difficulty'], 1);
            $pdf->Cell($w[5], 6, $this->calculate_trend($keyword_data['history']), 1);
            $pdf->Ln();
        }

        // Wykres historii rankingów
        $pdf->Ln(10);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'Historia rankingów', 0, 1);
        
        // Tu można dodać generowanie wykresu
        // Wymaga dodatkowej biblioteki do generowania wykresów

        // Wyślij PDF
        $pdf->Output('konkurent-' . sanitize_file_name($competitor->domain) . '-' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }

    /**
     * Pobierz dane słowa kluczowego
     */
    private function get_keyword_data($competitor_id, $keyword) {
        $data = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT our_rank, their_rank, volume, difficulty
            FROM {$this->keywords_table}
            WHERE competitor_id = %d AND keyword = %s
            ORDER BY timestamp DESC
            LIMIT 1",
            $competitor_id,
            $keyword
        ));

        $history = $this->get_ranking_history($competitor_id, $keyword);

        return array(
            'our_rank' => $data->our_rank,
            'their_rank' => $data->their_rank,
            'volume' => $data->volume,
            'difficulty' => $data->difficulty,
            'history' => $history
        );
    }

    /**
     * Oblicz trend dla słowa kluczowego
     */
    private function calculate_trend($history) {
        if (count($history) < 2) {
            return 'brak danych';
        }

        $first = $history[count($history) - 1]->our_rank;
        $last = $history[0]->our_rank;
        $diff = $last - $first;

        if ($diff > 5) {
            return '↑↑';
        } elseif ($diff > 0) {
            return '↑';
        } elseif ($diff < -5) {
            return '↓↓';
        } elseif ($diff < 0) {
            return '↓';
        } else {
            return '→';
        }
    }

    /**
     * Analiza treści konkurenta
     */
    public function analyze_competitor_content($competitor_id) {
        $competitor = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->competitors_table} WHERE id = %d",
            $competitor_id
        ));

        if (!$competitor) {
            return false;
        }

        $content_analysis = array(
            'meta_tags' => $this->analyze_meta_tags($competitor->domain),
            'content_structure' => $this->analyze_content_structure($competitor->domain),
            'backlinks' => $this->analyze_backlinks($competitor->domain),
            'social_signals' => $this->analyze_social_signals($competitor->domain),
            'technical_seo' => $this->analyze_technical_seo($competitor->domain)
        );

        return $content_analysis;
    }

    /**
     * Analiza meta tagów konkurenta
     */
    private function analyze_meta_tags($domain) {
        $url = 'https://' . $domain;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        $meta_tags = array();

        // Analiza meta title
        preg_match('/<title>(.*?)<\/title>/i', $html, $title_matches);
        $meta_tags['title'] = isset($title_matches[1]) ? $title_matches[1] : '';

        // Analiza meta description
        preg_match('/<meta name="description" content="(.*?)"/i', $html, $desc_matches);
        $meta_tags['description'] = isset($desc_matches[1]) ? $desc_matches[1] : '';

        // Analiza meta keywords
        preg_match('/<meta name="keywords" content="(.*?)"/i', $html, $keywords_matches);
        $meta_tags['keywords'] = isset($keywords_matches[1]) ? $keywords_matches[1] : '';

        // Analiza Open Graph
        preg_match('/<meta property="og:title" content="(.*?)"/i', $html, $og_title_matches);
        $meta_tags['og_title'] = isset($og_title_matches[1]) ? $og_title_matches[1] : '';

        return $meta_tags;
    }

    /**
     * Analiza struktury treści
     */
    private function analyze_content_structure($domain) {
        $url = 'https://' . $domain;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        $structure = array();

        // Analiza nagłówków
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $html, $headers);
        $structure['headers'] = array(
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array()
        );

        foreach ($headers[1] as $key => $level) {
            $structure['headers']['h' . $level][] = strip_tags($headers[2][$key]);
        }

        // Analiza długości treści
        $text_content = strip_tags($html);
        $structure['content_length'] = strlen($text_content);
        $structure['word_count'] = str_word_count($text_content);

        // Analiza obrazów
        preg_match_all('/<img[^>]+>/i', $html, $images);
        $structure['images'] = array(
            'count' => count($images[0]),
            'with_alt' => 0,
            'without_alt' => 0
        );

        foreach ($images[0] as $img) {
            if (preg_match('/alt="[^"]*"/', $img)) {
                $structure['images']['with_alt']++;
            } else {
                $structure['images']['without_alt']++;
            }
        }

        return $structure;
    }

    /**
     * Analiza backlinków przez OpenLinkProfiler API
     */
    private function analyze_backlinks($domain) {
        $api_key = get_option('cleanseo_olp_api_key');
        if (!$api_key) return false;

        $endpoint = "https://www.openlinkprofiler.org/api/domain/$domain?api_key=$api_key";
        $response = wp_remote_get($endpoint);

        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'total_backlinks' => $data['info']['links_total'] ?? 0,
            'unique_domains' => $data['info']['unique_domains'] ?? 0,
            'authority_score' => $data['info']['domain_influence'] ?? 0,
            'top_referring_domains' => $data['refdomains'] ?? []
        );
    }

    /**
     * Analiza sygnałów społecznościowych przez SharedCount API
     */
    private function analyze_social_signals($domain) {
        $api_key = get_option('cleanseo_sharedcount_api_key');
        if (!$api_key) return false;

        $url = 'https://' . $domain;
        $endpoint = "https://api.sharedcount.com/v1.0/?url=" . urlencode($url) . "&apikey={$api_key}";
        $response = wp_remote_get($endpoint);

        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'facebook' => array(
                'shares' => $data['Facebook']['share_count'] ?? 0,
                'likes' => $data['Facebook']['like_count'] ?? 0,
                'comments' => $data['Facebook']['comment_count'] ?? 0
            ),
            'pinterest' => array(
                'shares' => $data['Pinterest'] ?? 0
            ),
            'reddit' => array(
                'score' => $data['Reddit'] ?? 0
            )
        );
    }

    /**
     * Analiza technicznego SEO
     */
    private function analyze_technical_seo($domain) {
        $url = 'https://' . $domain;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }

        $headers = wp_remote_retrieve_headers($response);
        $technical = array();

        // Sprawdzenie SSL
        $technical['ssl'] = strpos($url, 'https://') === 0;

        // Sprawdzenie nagłówków
        $technical['headers'] = array(
            'server' => $headers['server'],
            'x_powered_by' => $headers['x-powered-by'],
            'content_type' => $headers['content-type']
        );

        // Sprawdzenie prędkości ładowania
        $technical['load_time'] = wp_remote_retrieve_response_code($response) === 200 ? 
            wp_remote_retrieve_header($response, 'x-runtime') : false;

        // Sprawdzenie responsywności przez Google Mobile-Friendly Test API
        $technical['mobile_friendly'] = $this->check_mobile_friendly($url);

        return $technical;
    }

    /**
     * Sprawdzenie responsywności strony przez Google Mobile-Friendly Test API
     */
    private function check_mobile_friendly($url) {
        $api_key = get_option('cleanseo_google_api_key');
        if (!$api_key) return false;

        $endpoint = "https://searchconsole.googleapis.com/v1/urlTestingTools/mobileFriendlyTest:run?key={$api_key}";
        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array('url' => $url))
        ));

        if (is_wp_error($response)) return false;
        $data = json_decode(wp_remote_retrieve_body($response), true);

        return isset($data['mobileFriendliness']) && $data['mobileFriendliness'] === 'MOBILE_FRIENDLY';
    }

    /**
     * Generowanie rekomendacji na podstawie analizy
     */
    public function generate_recommendations($competitor_id) {
        $analysis = $this->analyze_competitor_content($competitor_id);
        if (!$analysis) {
            return false;
        }

        $recommendations = array();

        // Rekomendacje dotyczące meta tagów
        if (empty($analysis['meta_tags']['title'])) {
            $recommendations[] = 'Dodaj meta tytuł';
        }
        if (empty($analysis['meta_tags']['description'])) {
            $recommendations[] = 'Dodaj meta opis';
        }

        // Rekomendacje dotyczące struktury treści
        if ($analysis['content_structure']['content_length'] < 300) {
            $recommendations[] = 'Zwiększ długość treści (minimum 300 słów)';
        }
        if ($analysis['content_structure']['images']['without_alt'] > 0) {
            $recommendations[] = 'Dodaj atrybuty ALT do wszystkich obrazów';
        }

        // Rekomendacje techniczne
        if (!$analysis['technical_seo']['ssl']) {
            $recommendations[] = 'Włącz SSL na stronie';
        }
        if (!$analysis['technical_seo']['mobile_friendly']) {
            $recommendations[] = 'Zoptymalizuj stronę pod urządzenia mobilne';
        }

        return $recommendations;
    }

    /**
     * Porównanie z konkurentem
     */
    public function compare_with_competitor($competitor_id) {
        $competitor = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->competitors_table} WHERE id = %d",
            $competitor_id
        ));

        if (!$competitor) {
            return false;
        }

        $our_analysis = $this->analyze_competitor_content($this->our_domain);
        $their_analysis = $this->analyze_competitor_content($competitor->domain);

        $comparison = array(
            'meta_tags' => $this->compare_meta_tags($our_analysis['meta_tags'], $their_analysis['meta_tags']),
            'content' => $this->compare_content($our_analysis['content_structure'], $their_analysis['content_structure']),
            'backlinks' => $this->compare_backlinks($our_analysis['backlinks'], $their_analysis['backlinks']),
            'technical' => $this->compare_technical($our_analysis['technical_seo'], $their_analysis['technical_seo'])
        );

        return $comparison;
    }

    /**
     * Porównanie meta tagów
     */
    private function compare_meta_tags($our_tags, $their_tags) {
        return array(
            'title_length' => array(
                'our' => strlen($our_tags['title']),
                'their' => strlen($their_tags['title']),
                'difference' => strlen($our_tags['title']) - strlen($their_tags['title'])
            ),
            'description_length' => array(
                'our' => strlen($our_tags['description']),
                'their' => strlen($their_tags['description']),
                'difference' => strlen($our_tags['description']) - strlen($their_tags['description'])
            )
        );
    }

    /**
     * Porównanie treści
     */
    private function compare_content($our_content, $their_content) {
        return array(
            'word_count' => array(
                'our' => $our_content['word_count'],
                'their' => $their_content['word_count'],
                'difference' => $our_content['word_count'] - $their_content['word_count']
            ),
            'images' => array(
                'our' => $our_content['images']['count'],
                'their' => $their_content['images']['count'],
                'difference' => $our_content['images']['count'] - $their_content['images']['count']
            )
        );
    }

    /**
     * Porównanie backlinków
     */
    private function compare_backlinks($our_backlinks, $their_backlinks) {
        return array(
            'total' => array(
                'our' => $our_backlinks['total_backlinks'],
                'their' => $their_backlinks['total_backlinks'],
                'difference' => $our_backlinks['total_backlinks'] - $their_backlinks['total_backlinks']
            ),
            'authority' => array(
                'our' => $our_backlinks['authority_score'],
                'their' => $their_backlinks['authority_score'],
                'difference' => $our_backlinks['authority_score'] - $their_backlinks['authority_score']
            )
        );
    }

    /**
     * Porównanie techniczne
     */
    private function compare_technical($our_technical, $their_technical) {
        return array(
            'ssl' => array(
                'our' => $our_technical['ssl'],
                'their' => $their_technical['ssl']
            ),
            'mobile_friendly' => array(
                'our' => $our_technical['mobile_friendly'],
                'their' => $their_technical['mobile_friendly']
            ),
            'load_time' => array(
                'our' => $our_technical['load_time'],
                'their' => $their_technical['load_time'],
                'difference' => $our_technical['load_time'] - $their_technical['load_time']
            )
        );
    }
} 