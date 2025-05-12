<?php
/**
 * Klasa CleanSEO_Audit - zaawansowana analiza SEO całej strony
 *
 * @package CleanSEO
 * @subpackage Audit
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Audit {
    private $wpdb;
    private $tables;
    private $cache;
    private $logger;
    private $results = array();
    private $cache_group = 'cleanseo_audit';
    private $cache_time = 3600; // 1 godzina

    /**
     * Konstruktor
     */
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

        // Inicjalizacja hooków
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_cleanseo_run_audit', array($this, 'ajax_run_audit'));
        add_action('wp_ajax_cleanseo_get_audit_report', array($this, 'ajax_get_audit_report'));
        add_action('wp_ajax_cleanseo_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_cleanseo_delete_audit', array($this, 'ajax_delete_audit'));
        
        // Scheduled audit
        add_action('cleanseo_scheduled_audit', array($this, 'run_scheduled_audit'));
    }

    /**
     * AJAX: Uruchom audyt
     */
    public function ajax_run_audit() {
        check_ajax_referer('cleanseo_run_audit', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'manual';
        $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'full';
        
        try {
            $results = $this->run_audit($scope);
            $audit_id = $this->save_audit($results['results'], $results['score'], $type);
            
            if ($audit_id) {
                $this->logger->log('info', 'Wykonano audyt SEO', array(
                    'audit_id' => $audit_id,
                    'score' => $results['score'],
                    'type' => $type,
                    'scope' => $scope
                ));
                
                wp_send_json_success(array(
                    'audit_id' => $audit_id,
                    'score' => $results['score'],
                    'message' => __('Audyt został wykonany pomyślnie.', 'cleanseo-optimizer')
                ));
            } else {
                throw new Exception(__('Nie udało się zapisać wyników audytu.', 'cleanseo-optimizer'));
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd podczas wykonywania audytu', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Pobierz raport audytu
     */
    public function ajax_get_audit_report() {
        check_ajax_referer('cleanseo_get_audit_report', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        
        $audit_id = isset($_REQUEST['audit_id']) ? intval($_REQUEST['audit_id']) : 0;
        $format = isset($_REQUEST['format']) ? sanitize_text_field($_REQUEST['format']) : 'html';
        
        if (!$audit_id) {
            wp_send_json_error(__('Nieprawidłowy identyfikator audytu.', 'cleanseo-optimizer'));
        }
        
        try {
            $data = $this->get_audit_report_data($audit_id, $format);
            
            if ($data) {
                if ($format === 'html') {
                    wp_send_json_success($data);
                } else {
                    // Format PDF lub CSV będzie obsługiwany bezpośrednio przez metody generujące
                    $this->generate_report($format, $data['audit'], $data['results']);
                }
            } else {
                throw new Exception(__('Nie znaleziono audytu.', 'cleanseo-optimizer'));
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd podczas pobierania raportu audytu', array(
                'error' => $e->getMessage(),
                'audit_id' => $audit_id,
                'format' => $format
            ));
            
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Zapisz harmonogram
     */
    public function ajax_save_schedule() {
        check_ajax_referer('cleanseo_save_schedule', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'weekly';
        
        // Usuń istniejące zaplanowane zdarzenie
        wp_clear_scheduled_hook('cleanseo_scheduled_audit');
        
        // Zaplanuj nowe zdarzenie w zależności od częstotliwości
        switch ($frequency) {
            case 'daily':
                $interval = 'daily';
                break;
            case 'monthly':
                $interval = 'monthly';
                break;
            case 'weekly':
            default:
                $interval = 'weekly';
                break;
        }
        
        $next_run = time() + 60; // Uruchom za minutę za pierwszym razem
        wp_schedule_event($next_run, $interval, 'cleanseo_scheduled_audit');
        
        update_option('cleanseo_audit_frequency', $frequency);
        update_option('cleanseo_next_scheduled_audit', date('Y-m-d H:i:s', $next_run));
        
        $this->logger->log('info', 'Zaktualizowano harmonogram audytów', array(
            'frequency' => $frequency,
            'next_run' => date('Y-m-d H:i:s', $next_run)
        ));
        
        wp_send_json_success(array(
            'message' => __('Harmonogram został zapisany.', 'cleanseo-optimizer'),
            'next_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)
        ));
    }

    /**
     * AJAX: Usuń audyt
     */
    public function ajax_delete_audit() {
        check_ajax_referer('cleanseo_delete_audit', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        
        $audit_id = isset($_POST['audit_id']) ? intval($_POST['audit_id']) : 0;
        
        if (!$audit_id) {
            wp_send_json_error(__('Nieprawidłowy identyfikator audytu.', 'cleanseo-optimizer'));
        }
        
        try {
            $result = $this->wpdb->delete(
                $this->tables['audits'],
                array('id' => $audit_id),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }
            
            $this->logger->log('info', 'Usunięto audyt', array(
                'audit_id' => $audit_id
            ));
            
            wp_send_json_success(array(
                'message' => __('Audyt został usunięty.', 'cleanseo-optimizer')
            ));
        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd podczas usuwania audytu', array(
                'error' => $e->getMessage(),
                'audit_id' => $audit_id
            ));
            
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Wykonaj audyt SEO dla wszystkich postów i stron
     * 
     * @param string $scope Zakres audytu (full, posts, pages)
     * @return array Tablica wyników i ogólny score
     */
    public function run_audit($scope = 'full') {
        $this->logger->log('info', 'Rozpoczęto audyt SEO', array(
            'scope' => $scope
        ));
        
        $results = array();
        $score = 0;
        $total = 0;
        $ok = 0;
        
        try {
            // Określ warunek dla zakresu audytu
            $post_type_condition = "post_type = 'post' OR post_type = 'page'";
            if ($scope === 'posts') {
                $post_type_condition = "post_type = 'post'";
            } elseif ($scope === 'pages') {
                $post_type_condition = "post_type = 'page'";
            }
            
            // Pobierz wszystkie opublikowane posty i strony
            $posts = $this->wpdb->get_results(
                "SELECT ID, post_title, post_content, post_type 
                FROM {$this->wpdb->posts} 
                WHERE post_status = 'publish' AND ($post_type_condition)"
            );
            
            if (!$posts) {
                return array('score' => 0, 'results' => array());
            }
            
            // Kategorie do analizy
            $categories = array(
                'meta_tags' => array(),
                'content_structure' => array(),
                'images' => array(),
                'links' => array(),
                'schema' => array()
            );
            
            // Wykonaj analizę dla każdego posta/strony
            foreach ($posts as $post) {
                $post_score = $this->analyze_post($post, $categories);
                $ok += $post_score['score'];
                $total += 100;
            }
            
            // Oblicz ogólny wynik
            $score = $total > 0 ? round($ok / count($posts)) : 0;
            
            // Dodaj analizę ogólną witryny
            $site_analysis = $this->analyze_site();
            $categories['site_analysis'] = $site_analysis['results'];
            $score = ($score + $site_analysis['score']) / 2;
            
            // Zwróć wyniki
            return array('score' => $score, 'results' => $categories);
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd podczas wykonywania audytu', array(
                'error' => $e->getMessage()
            ));
            
            return array('score' => 0, 'results' => array());
        }
    }
    
    /**
     * Przeanalizuj pojedynczy post lub stronę
     * 
     * @param object $post Obiekt posta
     * @param array &$categories Referencja do kategorii analizy
     * @return array Wyniki analizy
     */
    private function analyze_post($post, &$categories) {
        $item = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'score' => 0,
            'issues' => array()
        );
        
        // Sprawdź Meta Title
        $meta_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
        if (!$meta_title) {
            $meta_title = get_post_meta($post->ID, '_cleanseo_meta_title', true);
        }
        if (!$meta_title) {
            $meta_title = get_the_title($post->ID);
        }
        
        $meta_title_status = true;
        $meta_title_recommendation = '';
        
        if (!$meta_title) {
            $meta_title_status = false;
            $meta_title_recommendation = __('Brak meta title.', 'cleanseo-optimizer');
            $item['issues'][] = $meta_title_recommendation;
        } elseif (mb_strlen($meta_title) < 30) {
            $meta_title_status = false;
            $meta_title_recommendation = __('Meta title jest za krótki (mniej niż 30 znaków).', 'cleanseo-optimizer');
            $item['issues'][] = $meta_title_recommendation;
        } elseif (mb_strlen($meta_title) > 60) {
            $meta_title_status = false;
            $meta_title_recommendation = __('Meta title jest za długi (więcej niż 60 znaków).', 'cleanseo-optimizer');
            $item['issues'][] = $meta_title_recommendation;
        }
        
        // Sprawdź Meta Description
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (!$meta_desc) {
            $meta_desc = get_post_meta($post->ID, '_cleanseo_meta_description', true);
        }
        
        $meta_desc_status = true;
        $meta_desc_recommendation = '';
        
        if (!$meta_desc) {
            $meta_desc_status = false;
            $meta_desc_recommendation = __('Brak meta description.', 'cleanseo-optimizer');
            $item['issues'][] = $meta_desc_recommendation;
        } elseif (mb_strlen($meta_desc) < 120) {
            $meta_desc_status = false;
            $meta_desc_recommendation = __('Meta description jest za krótki (mniej niż 120 znaków).', 'cleanseo-optimizer');
            $item['issues'][] = $meta_desc_recommendation;
        } elseif (mb_strlen($meta_desc) > 155) {
            $meta_desc_status = false;
            $meta_desc_recommendation = __('Meta description jest za długi (więcej niż 155 znaków).', 'cleanseo-optimizer');
            $item['issues'][] = $meta_desc_recommendation;
        }
        
        // Zapisz wyniki analizy meta tagów
        $categories['meta_tags'][] = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'meta_title' => array(
                'status' => $meta_title_status,
                'value' => $meta_title,
                'recommendation' => $meta_title_recommendation
            ),
            'meta_description' => array(
                'status' => $meta_desc_status,
                'value' => $meta_desc,
                'recommendation' => $meta_desc_recommendation
            )
        );
        
        // Analizuj nagłówki (H1-H6)
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $post->post_content, $headers);
        
        $h1_count = 0;
        $headers_count = array(0, 0, 0, 0, 0, 0);
        
        foreach ($headers[1] as $h) {
            $h_index = intval($h) - 1;
            $headers_count[$h_index]++;
            if ($h == '1') {
                $h1_count++;
            }
        }
        
        $headers_status = true;
        $headers_recommendation = '';
        
        if ($h1_count === 0) {
            $headers_status = false;
            $headers_recommendation = __('Brak nagłówka H1.', 'cleanseo-optimizer');
            $item['issues'][] = $headers_recommendation;
        } elseif ($h1_count > 1) {
            $headers_status = false;
            $headers_recommendation = __('Więcej niż jeden nagłówek H1.', 'cleanseo-optimizer');
            $item['issues'][] = $headers_recommendation;
        }
        
        if ($headers_count[1] === 0 && $headers_count[0] > 0) {
            $headers_status = false;
            $headers_recommendation .= ' ' . __('Brak nagłówków H2 po H1.', 'cleanseo-optimizer');
            $item['issues'][] = __('Brak nagłówków H2 po H1.', 'cleanseo-optimizer');
        }
        
        // Zapisz wyniki analizy struktury treści
        $categories['content_structure'][] = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'headers' => array(
                'status' => $headers_status,
                'h1_count' => $h1_count,
                'h2_count' => $headers_count[1],
                'h3_count' => $headers_count[2],
                'recommendation' => $headers_recommendation
            )
        );
        
        // Analizuj obrazki
        preg_match_all('/<img[^>]+>/i', $post->post_content, $imgs);
        
        $all_alt = true;
        $missing_alt_count = 0;
        
        foreach ($imgs[0] as $img) {
            if (!preg_match('/alt="[^"]*"/i', $img)) {
                $all_alt = false;
                $missing_alt_count++;
            }
        }
        
        $img_alt_status = $all_alt;
        $img_alt_recommendation = '';
        
        if (!$all_alt) {
            $img_alt_recommendation = sprintf(
                __('%d obrazków nie ma atrybutu ALT.', 'cleanseo-optimizer'),
                $missing_alt_count
            );
            $item['issues'][] = $img_alt_recommendation;
        }
        
        // Zapisz wyniki analizy obrazków
        $categories['images'][] = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'alt_tags' => array(
                'status' => $img_alt_status,
                'total_images' => count($imgs[0]),
                'missing_alt' => $missing_alt_count,
                'recommendation' => $img_alt_recommendation
            )
        );
        
        // Analizuj linki
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>/i', $post->post_content, $links);
        
        $internal_links = 0;
        $external_links = 0;
        $broken_links = 0;
        
        foreach ($links[1] as $url) {
            // Sprawdź czy link jest wewnętrzny
            if (strpos($url, home_url()) === 0 || (!preg_match('/^https?:\/\//', $url) && strpos($url, '#') !== 0)) {
                $internal_links++;
                
                // Sprawdź czy link nie jest uszkodzony (tylko dla wewnętrznych)
                $response = wp_remote_head($url);
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) == 404) {
                    $broken_links++;
                }
            } else {
                $external_links++;
            }
        }
        
        $links_status = true;
        $links_recommendation = '';
        
        if ($internal_links === 0) {
            $links_status = false;
            $links_recommendation = __('Brak linków wewnętrznych.', 'cleanseo-optimizer');
            $item['issues'][] = $links_recommendation;
        }
        
        if ($broken_links > 0) {
            $links_status = false;
            $links_recommendation .= ' ' . sprintf(
                __('Znaleziono %d uszkodzonych linków.', 'cleanseo-optimizer'),
                $broken_links
            );
            $item['issues'][] = sprintf(
                __('Znaleziono %d uszkodzonych linków.', 'cleanseo-optimizer'),
                $broken_links
            );
        }
        
        // Zapisz wyniki analizy linków
        $categories['links'][] = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'links' => array(
                'status' => $links_status,
                'internal_links' => $internal_links,
                'external_links' => $external_links,
                'broken_links' => $broken_links,
                'recommendation' => $links_recommendation
            )
        );
        
        // Analizuj dane strukturalne (schema.org)
        $has_schema = false;
        
        if (preg_match('/<script[^>]+type="application\/ld\+json"[^>]*>/', $post->post_content)) {
            $has_schema = true;
        }
        
        $schema_status = $has_schema;
        $schema_recommendation = '';
        
        if (!$has_schema) {
            $schema_recommendation = __('Brak danych strukturalnych (schema.org).', 'cleanseo-optimizer');
            $item['issues'][] = $schema_recommendation;
        }
        
        // Zapisz wyniki analizy danych strukturalnych
        $categories['schema'][] = array(
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'schema' => array(
                'status' => $schema_status,
                'recommendation' => $schema_recommendation
            )
        );
        
        // Oblicz wynik dla tego posta
        $item['score'] = $this->calculate_post_score($item);
        
        return $item;
    }
    
    /**
     * Przeanalizuj całą witrynę
     * 
     * @return array Wyniki analizy
     */
    private function analyze_site() {
        $results = array();
        $score = 0;
        $issues = 0;
        
        // Sprawdź sitemap
        $sitemap_status = false;
        $sitemap_recommendation = '';
        
        if (function_exists('get_home_path')) {
            $sitemap_path = get_home_path() . 'sitemap.xml';
            if (file_exists($sitemap_path)) {
                $sitemap_status = true;
            } else {
                $sitemap_recommendation = __('Brak pliku sitemap.xml.', 'cleanseo-optimizer');
                $issues++;
            }
        }
        
        $results[] = array(
            'title' => __('Mapa witryny (Sitemap)', 'cleanseo-optimizer'),
            'status' => $sitemap_status,
            'recommendation' => $sitemap_recommendation
        );
        
        // Sprawdź robots.txt
        $robots_status = false;
        $robots_recommendation = '';
        
        if (function_exists('get_home_path')) {
            $robots_path = get_home_path() . 'robots.txt';
            if (file_exists($robots_path)) {
                $robots_status = true;
            } else {
                $robots_recommendation = __('Brak pliku robots.txt.', 'cleanseo-optimizer');
                $issues++;
            }
        }
        
        $results[] = array(
            'title' => __('Plik robots.txt', 'cleanseo-optimizer'),
            'status' => $robots_status,
            'recommendation' => $robots_recommendation
        );
        
        // Sprawdź favicon
        $favicon_status = false;
        $favicon_recommendation = '';
        
        if (function_exists('get_home_path')) {
            $favicon_path = get_home_path() . 'favicon.ico';
            if (file_exists($favicon_path)) {
                $favicon_status = true;
            } else {
                $favicon_recommendation = __('Brak pliku favicon.ico.', 'cleanseo-optimizer');
                $issues++;
            }
        }
        
        $results[] = array(
            'title' => __('Favicon', 'cleanseo-optimizer'),
            'status' => $favicon_status,
            'recommendation' => $favicon_recommendation
        );
        
        // Sprawdź SSL
        $ssl_status = is_ssl();
        $ssl_recommendation = '';
        
        if (!$ssl_status) {
            $ssl_recommendation = __('Witryna nie używa SSL (HTTPS).', 'cleanseo-optimizer');
            $issues++;
        }
        
        $results[] = array(
            'title' => __('SSL (HTTPS)', 'cleanseo-optimizer'),
            'status' => $ssl_status,
            'recommendation' => $ssl_recommendation
        );
        
        // Sprawdź Canonical URL
        $canonical_status = true;
        $canonical_recommendation = '';
        
        // Pobierz stronę główną
        $response = wp_remote_get(home_url());
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            
            if (!preg_match('/<link[^>]+rel="canonical"[^>]+>/i', $body)) {
                $canonical_status = false;
                $canonical_recommendation = __('Brak canonical URL na stronie głównej.', 'cleanseo-optimizer');
                $issues++;
            }
        }
        
        $results[] = array(
            'title' => __('Canonical URL', 'cleanseo-optimizer'),
            'status' => $canonical_status,
            'recommendation' => $canonical_recommendation
        );
        
        // Oblicz wynik
        $total_checks = count($results);
        $passed_checks = $total_checks - $issues;
        $score = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;
        
        return array(
            'score' => $score,
            'results' => $results
        );
    }
    
    /**
     * Oblicz wynik dla posta
     * 
     * @param array $item Dane posta
     * @return int Wynik (0-100)
     */
    private function calculate_post_score($item) {
        $total_issues = count($item['issues']);
        $max_issues = 7; // Maksymalna liczba możliwych problemów
        
        // Im mniej problemów, tym wyższy wynik
        $score = 100 - (($total_issues / $max_issues) * 100);
        
        return round($score);
    }
    
    /**
     * Zapisz wyniki audytu
     * 
     * @param array $results Wyniki audytu
     * @param int $score Ogólny wynik
     * @param string $type Typ audytu (manual, scheduled)
     * @return int|false ID zapisanego audytu lub false w przypadku błędu
     */
    private function save_audit($results, $score, $type = 'manual') {
        $data = array(
            'score' => $score,
            'results' => json_encode($results),
            'timestamp' => current_time('mysql'),
            'type' => $type
        );
        
        $result = $this->wpdb->insert($this->tables['audits'], $data);
        
        if ($result === false) {
            $this->logger->log('error', 'Błąd podczas zapisywania audytu', array(
                'error' => $this->wpdb->last_error
            ));
            
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Pobierz dane raportu audytu
     * 
     * @param int $audit_id ID audytu
     * @param string $format Format raportu (html, pdf, csv)
     * @return array|false Dane raportu lub false w przypadku błędu
     */
    public function get_audit_report_data($audit_id, $format = 'html') {
        $cache_key = 'audit_report_' . $audit_id . '_' . $format;
        $data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($data !== false) {
            return $data;
        }
        
        $audit = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['audits']} WHERE id = %d",
            $audit_id
        ));
        
        if (!$audit) {
            return false;
        }
        
        $results = json_decode($audit->results, true);
        
        $data = array(
            'audit' => $audit,
            'results' => $results,
            'score' => $audit->score,
            'timestamp' => $audit->timestamp
        );
        
        wp_cache_set($cache_key, $data, $this->cache_group, $this->cache_time);
        
        return $data;
    }
    
    /**
     * Generuj raport
     * 
     * @param string $format Format raportu (pdf, csv)
     * @param object $audit Dane audytu
     * @param array $results Wyniki audytu
     */
    private function generate_report($format, $audit, $results) {
        switch ($format) {
            case 'pdf':
                $this->generate_pdf_report($audit, $results);
                break;
            case 'csv':
                $this->generate_csv_report($audit, $results);
                break;
            default:
                wp_die(__('Nieobsługiwany format raportu.', 'cleanseo-optimizer'));
        }
    }
    
    /**
     * Eksport raportu audytu do PDF
     * 
     * @param object $audit Dane audytu
     * @param array $results Wyniki audytu
     */
    private function generate_pdf_report($audit, $results) {
        // Sprawdź czy klasa mPDF jest dostępna
        if (!class_exists('\Mpdf\Mpdf')) {
            try {
                require_once(CLEANSEO_PLUGIN_DIR . 'includes/vendor/autoload.php');
            } catch (Exception $e) {
                wp_die(__('Nie można załadować klasy mPDF. Proszę sprawdzić instalację biblioteki.', 'cleanseo-optimizer'));
            }
        }
        
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15
            ]);
            
            // Ustaw metadane
            $mpdf->SetTitle(__('Raport Audytu SEO', 'cleanseo-optimizer') . ' - ' . get_bloginfo('name'));
            $mpdf->SetAuthor('CleanSEO Optimizer');
            $mpdf->SetCreator('CleanSEO Optimizer');
            
            // Dodaj style CSS
            $stylesheet = '
                body { font-family: DejaVuSansCondensed, sans-serif; font-size: 11pt; }
                h1 { color: #0073aa; font-size: 18pt; margin-bottom: 10pt; }
                h2 { color: #23282d; font-size: 14pt; margin-top: 15pt; margin-bottom: 5pt; }
                table { width: 100%; border-collapse: collapse; margin: 10pt 0; }
                th { background-color: #f1f1f1; border: 1px solid #ddd; padding: 5pt; text-align: left; }
                td { border: 1px solid #ddd; padding: 5pt; }
                .success { color: #46b450; }
                .error { color: #dc3232; }
                .summary { background-color: #f9f9f9; padding: 10pt; margin-bottom: 15pt; border-left: 4pt solid #0073aa; }
                .score { font-size: 16pt; font-weight: bold; }
                .category { margin-top: 15pt; margin-bottom: 10pt; }
                .footer { text-align: center; font-size: 9pt; color: #666; }
            ';
            $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
            
            // Przygotuj zawartość HTML
            $html = '<h1>' . __('Raport Audytu SEO', 'cleanseo-optimizer') . '</h1>';
            
            // Podsumowanie
            $html .= '<div class="summary">';
            $html .= '<p><strong>' . __('Witryna:', 'cleanseo-optimizer') . '</strong> ' . get_bloginfo('name') . ' (' . get_site_url() . ')</p>';
            $html .= '<p><strong>' . __('Data audytu:', 'cleanseo-optimizer') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($audit->timestamp)) . '</p>';
            $html .= '<p><strong>' . __('Ogólny wynik:', 'cleanseo-optimizer') . '</strong> <span class="score">' . esc_html($audit->score) . '%</span></p>';
            $html .= '</div>';
            
            // Analiza witryny
            if (isset($results['site_analysis'])) {
                $html .= '<h2>' . __('Analiza ogólna witryny', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Element', 'cleanseo-optimizer') . '</th><th>' . __('Status', 'cleanseo-optimizer') . '</th><th>' . __('Rekomendacja', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['site_analysis'] as $item) {
                    $status_class = $item['status'] ? 'success' : 'error';
                    $status_icon = $item['status'] ? '&#10004;' : '&#10008;';
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['title']) . '</td>';
                    $html .= '<td class="' . $status_class . '">' . $status_icon . '</td>';
                    $html .= '<td>' . ($item['recommendation'] ? esc_html($item['recommendation']) : __('Brak rekomendacji.', 'cleanseo-optimizer')) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Meta tagi
            if (isset($results['meta_tags']) && !empty($results['meta_tags'])) {
                $html .= '<h2>' . __('Meta tagi', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Strona', 'cleanseo-optimizer') . '</th><th>' . __('Meta Title', 'cleanseo-optimizer') . '</th><th>' . __('Meta Description', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['meta_tags'] as $item) {
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['post_title']) . '</td>';
                    
                    $title_status = $item['meta_title']['status'] ? 'success' : 'error';
                    $title_icon = $item['meta_title']['status'] ? '&#10004;' : '&#10008;';
                    $html .= '<td class="' . $title_status . '">' . $title_icon . ' ' . ($item['meta_title']['recommendation'] ? esc_html($item['meta_title']['recommendation']) : '') . '</td>';
                    
                    $desc_status = $item['meta_description']['status'] ? 'success' : 'error';
                    $desc_icon = $item['meta_description']['status'] ? '&#10004;' : '&#10008;';
                    $html .= '<td class="' . $desc_status . '">' . $desc_icon . ' ' . ($item['meta_description']['recommendation'] ? esc_html($item['meta_description']['recommendation']) : '') . '</td>';
                    
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Struktura treści
            if (isset($results['content_structure']) && !empty($results['content_structure'])) {
                $html .= '<h2>' . __('Struktura treści', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Strona', 'cleanseo-optimizer') . '</th><th>' . __('Nagłówki', 'cleanseo-optimizer') . '</th><th>' . __('Rekomendacja', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['content_structure'] as $item) {
                    $headers_status = $item['headers']['status'] ? 'success' : 'error';
                    $headers_icon = $item['headers']['status'] ? '&#10004;' : '&#10008;';
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['post_title']) . '</td>';
                    $html .= '<td class="' . $headers_status . '">' . $headers_icon . ' H1: ' . $item['headers']['h1_count'] . ', H2: ' . $item['headers']['h2_count'] . ', H3: ' . $item['headers']['h3_count'] . '</td>';
                    $html .= '<td>' . ($item['headers']['recommendation'] ? esc_html($item['headers']['recommendation']) : __('Brak rekomendacji.', 'cleanseo-optimizer')) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Obrazki
            if (isset($results['images']) && !empty($results['images'])) {
                $html .= '<h2>' . __('Obrazki', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Strona', 'cleanseo-optimizer') . '</th><th>' . __('Atrybuty ALT', 'cleanseo-optimizer') . '</th><th>' . __('Rekomendacja', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['images'] as $item) {
                    $alt_status = $item['alt_tags']['status'] ? 'success' : 'error';
                    $alt_icon = $item['alt_tags']['status'] ? '&#10004;' : '&#10008;';
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['post_title']) . '</td>';
                    $html .= '<td class="' . $alt_status . '">' . $alt_icon . ' ' . sprintf(__('Obrazki: %d, Brak ALT: %d', 'cleanseo-optimizer'), $item['alt_tags']['total_images'], $item['alt_tags']['missing_alt']) . '</td>';
                    $html .= '<td>' . ($item['alt_tags']['recommendation'] ? esc_html($item['alt_tags']['recommendation']) : __('Brak rekomendacji.', 'cleanseo-optimizer')) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Linki
            if (isset($results['links']) && !empty($results['links'])) {
                $html .= '<h2>' . __('Linki', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Strona', 'cleanseo-optimizer') . '</th><th>' . __('Linki', 'cleanseo-optimizer') . '</th><th>' . __('Rekomendacja', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['links'] as $item) {
                    $links_status = $item['links']['status'] ? 'success' : 'error';
                    $links_icon = $item['links']['status'] ? '&#10004;' : '&#10008;';
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['post_title']) . '</td>';
                    $html .= '<td class="' . $links_status . '">' . $links_icon . ' ' . sprintf(__('Wewnętrzne: %d, Zewnętrzne: %d, Uszkodzone: %d', 'cleanseo-optimizer'), $item['links']['internal_links'], $item['links']['external_links'], $item['links']['broken_links']) . '</td>';
                    $html .= '<td>' . ($item['links']['recommendation'] ? esc_html($item['links']['recommendation']) : __('Brak rekomendacji.', 'cleanseo-optimizer')) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Schema
            if (isset($results['schema']) && !empty($results['schema'])) {
                $html .= '<h2>' . __('Dane Strukturalne (Schema.org)', 'cleanseo-optimizer') . '</h2>';
                $html .= '<table>';
                $html .= '<tr><th>' . __('Strona', 'cleanseo-optimizer') . '</th><th>' . __('Status', 'cleanseo-optimizer') . '</th><th>' . __('Rekomendacja', 'cleanseo-optimizer') . '</th></tr>';
                
                foreach ($results['schema'] as $item) {
                    $schema_status = $item['schema']['status'] ? 'success' : 'error';
                    $schema_icon = $item['schema']['status'] ? '&#10004;' : '&#10008;';
                    
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($item['post_title']) . '</td>';
                    $html .= '<td class="' . $schema_status . '">' . $schema_icon . '</td>';
                    $html .= '<td>' . ($item['schema']['recommendation'] ? esc_html($item['schema']['recommendation']) : __('Brak rekomendacji.', 'cleanseo-optimizer')) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            // Stopka
            $html .= '<div class="footer">';
            $html .= '<p>' . __('Raport wygenerowany przez CleanSEO Optimizer', 'cleanseo-optimizer') . ' - ' . date_i18n(get_option('date_format'), current_time('timestamp')) . '</p>';
            $html .= '</div>';
            
            // Zapisz zawartość HTML do PDF
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
            
            // Ustaw nagłówek odpowiedzi HTTP
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="cleanseo-audit-report-' . date('Y-m-d') . '.pdf"');
            header('Cache-Control: max-age=0');
            
            // Wyślij PDF do przeglądarki
            $mpdf->Output('cleanseo-audit-report-' . date('Y-m-d') . '.pdf', \Mpdf\Output\Destination::INLINE);
            exit;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd podczas generowania raportu PDF', array(
                'error' => $e->getMessage()
            ));
            
            wp_die(__('Błąd podczas generowania raportu PDF: ', 'cleanseo-optimizer') . $e->getMessage());
        }
    }

    /**
     * Eksport raportu audytu do CSV
     * 
     * @param object $audit Dane audytu
     * @param array $results Wyniki audytu
     */
    private function generate_csv_report($audit, $results) {
        // Ustaw nagłówki HTTP
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cleanseo-audit-report-' . date('Y-m-d') . '.csv');
        
        // Utwórz uchwyt do strumienia wyjściowego PHP
        $output = fopen('php://output', 'w');
        
        // Dodaj nagłówek pliku CSV z oznaczeniem BOM dla poprawnego kodowania UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Nagłówki kolumn dla ogólnej analizy witryny
        fputcsv($output, array(
            __('Raport Audytu SEO', 'cleanseo-optimizer'),
            get_bloginfo('name'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($audit->timestamp))
        ));
        
        fputcsv($output, array(
            __('Ogólny wynik', 'cleanseo-optimizer'),
            $audit->score . '%'
        ));
        
        fputcsv($output, array(''));
        
        // Analiza witryny
        if (isset($results['site_analysis'])) {
            fputcsv($output, array(__('Analiza ogólna witryny', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Element', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['site_analysis'] as $item) {
                fputcsv($output, array(
                    $item['title'],
                    $item['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['recommendation'] ? $item['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
            
            fputcsv($output, array(''));
        }
        
        // Meta tagi
        if (isset($results['meta_tags']) && !empty($results['meta_tags'])) {
            fputcsv($output, array(__('Meta tagi', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Strona', 'cleanseo-optimizer'),
                __('Meta Title', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer'),
                __('Meta Description', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['meta_tags'] as $item) {
                fputcsv($output, array(
                    $item['post_title'],
                    isset($item['meta_title']['value']) ? $item['meta_title']['value'] : '',
                    $item['meta_title']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['meta_title']['recommendation'] ? $item['meta_title']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer'),
                    isset($item['meta_description']['value']) ? $item['meta_description']['value'] : '',
                    $item['meta_description']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['meta_description']['recommendation'] ? $item['meta_description']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
            
            fputcsv($output, array(''));
        }
        
        // Struktura treści
        if (isset($results['content_structure']) && !empty($results['content_structure'])) {
            fputcsv($output, array(__('Struktura treści', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Strona', 'cleanseo-optimizer'),
                __('H1', 'cleanseo-optimizer'),
                __('H2', 'cleanseo-optimizer'),
                __('H3', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['content_structure'] as $item) {
                fputcsv($output, array(
                    $item['post_title'],
                    $item['headers']['h1_count'],
                    $item['headers']['h2_count'],
                    $item['headers']['h3_count'],
                    $item['headers']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['headers']['recommendation'] ? $item['headers']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
            
            fputcsv($output, array(''));
        }
        
        // Obrazki
        if (isset($results['images']) && !empty($results['images'])) {
            fputcsv($output, array(__('Obrazki', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Strona', 'cleanseo-optimizer'),
                __('Liczba obrazków', 'cleanseo-optimizer'),
                __('Brak ALT', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['images'] as $item) {
                fputcsv($output, array(
                    $item['post_title'],
                    $item['alt_tags']['total_images'],
                    $item['alt_tags']['missing_alt'],
                    $item['alt_tags']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['alt_tags']['recommendation'] ? $item['alt_tags']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
            
            fputcsv($output, array(''));
        }
        
        // Linki
        if (isset($results['links']) && !empty($results['links'])) {
            fputcsv($output, array(__('Linki', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Strona', 'cleanseo-optimizer'),
                __('Linki wewnętrzne', 'cleanseo-optimizer'),
                __('Linki zewnętrzne', 'cleanseo-optimizer'),
                __('Uszkodzone linki', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['links'] as $item) {
                fputcsv($output, array(
                    $item['post_title'],
                    $item['links']['internal_links'],
                    $item['links']['external_links'],
                    $item['links']['broken_links'],
                    $item['links']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['links']['recommendation'] ? $item['links']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
            
            fputcsv($output, array(''));
        }
        
        // Schema
        if (isset($results['schema']) && !empty($results['schema'])) {
            fputcsv($output, array(__('Dane Strukturalne (Schema.org)', 'cleanseo-optimizer')));
            fputcsv($output, array(
                __('Strona', 'cleanseo-optimizer'),
                __('Status', 'cleanseo-optimizer'),
                __('Rekomendacja', 'cleanseo-optimizer')
            ));
            
            foreach ($results['schema'] as $item) {
                fputcsv($output, array(
                    $item['post_title'],
                    $item['schema']['status'] ? __('OK', 'cleanseo-optimizer') : __('Błąd', 'cleanseo-optimizer'),
                    $item['schema']['recommendation'] ? $item['schema']['recommendation'] : __('Brak rekomendacji.', 'cleanseo-optimizer')
                ));
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Uruchom zaplanowany audyt
     */
    public function run_scheduled_audit() {
        $this->logger->log('info', 'Rozpoczęto zaplanowany audyt SEO');
        
        $results = $this->run_audit('full');
        $audit_id = $this->save_audit($results['results'], $results['score'], 'scheduled');
        
        if ($audit_id) {
            $this->logger->log('info', 'Zaplanowany audyt SEO zakończony pomyślnie', array(
                'audit_id' => $audit_id,
                'score' => $results['score']
            ));
            
            update_option('cleanseo_last_scheduled_audit', current_time('mysql'));
            
            // Wyślij powiadomienie e-mail
            $this->send_audit_notification($audit_id, $results['score']);
            
            return true;
        } else {
            $this->logger->log('error', 'Nie udało się zapisać wyników zaplanowanego audytu SEO');
            return false;
        }
    }
    
    /**
     * Wyślij powiadomienie o audycie
     * 
     * @param int $audit_id ID audytu
     * @param int $score Wynik audytu
     * @return bool Status wysłania powiadomienia
     */
    private function send_audit_notification($audit_id, $score) {
        $notification_email = get_option('cleanseo_notification_email', get_option('admin_email'));
        
        if (!is_email($notification_email)) {
            return false;
        }
        
        $subject = sprintf(
            __('[%s] Raport audytu SEO - Wynik: %d%%', 'cleanseo-optimizer'),
            get_bloginfo('name'),
            $score
        );
        
        $message = sprintf(
            __('Automatyczny audyt SEO został zakończony dla witryny %s.', 'cleanseo-optimizer'),
            get_bloginfo('name')
        );
        
        $message .= "\n\n";
        $message .= sprintf(__('Wynik audytu: %d%%', 'cleanseo-optimizer'), $score) . "\n\n";
        $message .= __('Aby zobaczyć pełny raport, zaloguj się do panelu administracyjnego WordPress i przejdź do sekcji CleanSEO > Audyt SEO.', 'cleanseo-optimizer') . "\n\n";
        
        $admin_url = admin_url('admin.php?page=cleanseo-audit');
        $message .= __('Link do raportu:', 'cleanseo-optimizer') . ' ' . $admin_url . "\n\n";
        
        $message .= __('Pozdrawiamy,', 'cleanseo-optimizer') . "\n";
        $message .= __('Zespół CleanSEO Optimizer', 'cleanseo-optimizer');
        
        $headers = 'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>' . "\r\n";
        
        $result = wp_mail($notification_email, $subject, $message, $headers);
        
        if ($result) {
            $this->logger->log('info', 'Wysłano powiadomienie o audycie', array(
                'email' => $notification_email,
                'audit_id' => $audit_id,
                'score' => $score
            ));
            
            return true;
        } else {
            $this->logger->log('error', 'Nie udało się wysłać powiadomienia o audycie', array(
                'email' => $notification_email,
                'audit_id' => $audit_id,
                'score' => $score
            ));
            
            return false;
        }
    }
}