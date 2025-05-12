<?php
/**
 * Klasa do integracji z Google Trends i propozycji tematów
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Trends {
    private $api_key;
    private $cache_time = 3600; // 1 godzina
    private $max_retries = 3;
    private $retry_delay = 1; // sekundy
    private $timeout = 30;
    private $logger;
    private $cache;
    private $base_url = 'https://trends.google.com/trends/api/';

    public function __construct() {
        $this->api_key = get_option('cleanseo_google_api_key');
        $this->logger = new CleanSEO_Logger();
        $this->cache = new CleanSEO_Cache();
    }

    /**
     * Wykonaj zapytanie do API Google Trends
     *
     * @param string $endpoint Endpoint API
     * @param array $params Parametry zapytania
     * @param string $cache_key Klucz cache
     * @param int $cache_time Czas cache w sekundach
     * @return array|WP_Error Dane lub obiekt błędu
     */
    private function make_api_request($endpoint, $params, $cache_key, $cache_time = null) {
        if ($cache_time === null) {
            $cache_time = $this->cache_time;
        }

        // Sprawdź cache
        $cached_data = $this->cache->get($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $attempt = 0;
        $last_error = null;
        $url = $this->base_url . $endpoint;

        // Dodaj podstawowe parametry
        $params = array_merge([
            'hl' => 'pl',
            'tz' => 'Europe/Warsaw',
            'geo' => 'PL',
        ], $params);

        while ($attempt < $this->max_retries) {
            try {
                $request_args = [
                    'timeout' => $this->timeout,
                    'body' => $params
                ];
                
                // Dodaj nagłówek authorization tylko jeśli klucz API jest ustawiony
                if (!empty($this->api_key)) {
                    $request_args['headers'] = [
                        'Authorization' => 'Bearer ' . $this->api_key
                    ];
                }

                $response = wp_remote_get($url, $request_args);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    throw new Exception(sprintf(
                        __('Błąd API Google Trends: %d - %s', 'cleanseo-optimizer'),
                        $response_code,
                        wp_remote_retrieve_response_message($response)
                    ));
                }

                $body = wp_remote_retrieve_body($response);
                
                // Usuń prefiks )]}' jeśli istnieje
                if (substr($body, 0, 4) === ")]}'") {
                    $body = substr($body, 4);
                }
                
                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception(sprintf(
                        __('Błąd parsowania odpowiedzi JSON: %s', 'cleanseo-optimizer'),
                        json_last_error_msg()
                    ));
                }

                $this->cache->set($cache_key, $data, $cache_time);
                $this->logger->log('api_request_success', sprintf(
                    __('Pomyślnie wykonano zapytanie do %s', 'cleanseo-optimizer'),
                    $endpoint
                ));

                return $data;

            } catch (Exception $e) {
                $last_error = $e;
                $attempt++;
                
                $this->logger->log('api_request_error', sprintf(
                    __('Próba %d/%d nie powiodła się: %s', 'cleanseo-optimizer'),
                    $attempt,
                    $this->max_retries,
                    $e->getMessage()
                ));

                if ($attempt < $this->max_retries) {
                    sleep($this->retry_delay);
                }
            }
        }

        return new WP_Error(
            'api_request_error',
            sprintf(
                __('Nie udało się wykonać zapytania do %s po %d próbach. Ostatni błąd: %s', 'cleanseo-optimizer'),
                $endpoint,
                $this->max_retries,
                $last_error->getMessage()
            )
        );
    }

    /**
     * Pobierz trendy dla słowa kluczowego
     * 
     * @param string $keyword Słowo kluczowe
     * @param string $time_range Zakres czasu (np. '30d')
     * @return array|WP_Error Dane lub błąd
     */
    public function get_trends($keyword, $time_range = '30d') {
        if (empty($keyword)) {
            return new WP_Error('empty_keyword', __('Słowo kluczowe nie może być puste.', 'cleanseo-optimizer'));
        }

        $cache_key = 'cleanseo_trends_' . md5($keyword . $time_range);
        $params = [
            'ns' => 15,
        ];

        $data = $this->make_api_request('dailytrends', $params, $cache_key, 7200); // 2 godziny cache
        
        if (is_wp_error($data)) {
            return $data;
        }

        if (!isset($data['default']['trendingSearchesDays'])) {
            return new WP_Error(
                'invalid_response',
                __('Nieprawidłowa struktura odpowiedzi API.', 'cleanseo-optimizer')
            );
        }

        $this->logger->log('trends_success', sprintf(
            __('Pobrano trendy dla słowa kluczowego: %s', 'cleanseo-optimizer'),
            $keyword
        ));

        return $data;
    }

    /**
     * Pobierz powiązane zapytania
     * 
     * @param string $keyword Słowo kluczowe
     * @return array|WP_Error Dane lub błąd
     */
    public function get_related_queries($keyword) {
        if (empty($keyword)) {
            return new WP_Error('empty_keyword', __('Słowo kluczowe nie może być puste.', 'cleanseo-optimizer'));
        }

        $cache_key = 'cleanseo_related_' . md5($keyword);
        $params = [
            'q' => $keyword
        ];

        $data = $this->make_api_request('relatedqueries', $params, $cache_key, 86400); // 24 godziny cache
        
        if (is_wp_error($data)) {
            return $data;
        }

        if (!isset($data['default']['rankedList'])) {
            return new WP_Error(
                'invalid_response',
                __('Nieprawidłowa struktura odpowiedzi API.', 'cleanseo-optimizer')
            );
        }

        $this->logger->log('related_queries_success', sprintf(
            __('Pobrano powiązane zapytania dla słowa kluczowego: %s', 'cleanseo-optimizer'),
            $keyword
        ));

        return $data;
    }

    /**
     * Generuj propozycje tematów
     * 
     * @param string $keyword Słowo kluczowe
     * @return array|WP_Error Sugestie tematów lub błąd
     */
    public function generate_topic_suggestions($keyword) {
        if (empty($keyword)) {
            return new WP_Error('empty_keyword', __('Słowo kluczowe nie może być puste.', 'cleanseo-optimizer'));
        }

        // Sprawdź cache dla sugestii
        $cache_key = 'cleanseo_suggestions_' . md5($keyword);
        $cached_suggestions = $this->cache->get($cache_key);
        
        if ($cached_suggestions !== false) {
            return $cached_suggestions;
        }

        $trends = $this->get_trends($keyword);
        $related = $this->get_related_queries($keyword);
        
        $suggestions = [];
        
        // Dodaj dane z trendów
        if (!is_wp_error($trends) && isset($trends['default']['trendingSearchesDays'][0]['trendingSearches'])) {
            foreach ($trends['default']['trendingSearchesDays'][0]['trendingSearches'] as $trend) {
                if (isset($trend['title']['query'])) {
                    $suggestion = [
                        'title' => sanitize_text_field($trend['title']['query']),
                        'traffic' => isset($trend['formattedTraffic']) ? sanitize_text_field($trend['formattedTraffic']) : 0,
                        'articles' => []
                    ];
                    
                    // Dodaj artykuły jeśli istnieją
                    if (isset($trend['articles']) && is_array($trend['articles'])) {
                        foreach ($trend['articles'] as $article) {
                            if (isset($article['title'], $article['url'])) {
                                $suggestion['articles'][] = [
                                    'title' => sanitize_text_field($article['title']),
                                    'url' => esc_url_raw($article['url']),
                                    'source' => isset($article['source']) ? sanitize_text_field($article['source']) : ''
                                ];
                            }
                        }
                    }
                    
                    $suggestions[] = $suggestion;
                }
            }
        }
        
        // Dodaj dane z powiązanych zapytań
        if (!is_wp_error($related) && isset($related['default']['rankedList'][0]['rankedKeyword'])) {
            foreach ($related['default']['rankedList'][0]['rankedKeyword'] as $query) {
                if (isset($query['query'])) {
                    $suggestions[] = [
                        'title' => sanitize_text_field($query['query']),
                        'traffic' => isset($query['value']) ? intval($query['value']) : 0,
                        'articles' => []
                    ];
                }
            }
        }
        
        // Jeśli nie ma sugestii, zwróć błąd
        if (empty($suggestions)) {
            return new WP_Error(
                'no_suggestions',
                __('Nie znaleziono żadnych sugestii tematów.', 'cleanseo-optimizer')
            );
        }

        // Zapisz sugestie w cache na 6 godzin
        $this->cache->set($cache_key, $suggestions, 21600);
        
        return $suggestions;
    }

    /**
     * Pobierz trendy dla kategorii
     * 
     * @param string $category ID kategorii
     * @return array|WP_Error Dane lub obiekt błędu
     */
    public function get_category_trends($category) {
        if (empty($category)) {
            return new WP_Error('empty_category', __('Kategoria nie może być pusta.', 'cleanseo-optimizer'));
        }

        $cache_key = 'cleanseo_category_' . md5($category);
        $params = [
            'cat' => $category
        ];

        $data = $this->make_api_request('explore', $params, $cache_key, 43200); // 12 godzin cache
        
        if (is_wp_error($data)) {
            return $data;
        }

        if (!isset($data['widgets'])) {
            return new WP_Error(
                'invalid_response',
                __('Nieprawidłowa struktura odpowiedzi API.', 'cleanseo-optimizer')
            );
        }

        $this->logger->log('category_trends_success', sprintf(
            __('Pobrano trendy dla kategorii: %s', 'cleanseo-optimizer'),
            $category
        ));

        return $data;
    }
    
    /**
     * Sprawdź czy API jest skonfigurowane poprawnie
     * 
     * @return bool Czy API jest gotowe do użycia
     */
    public function is_api_ready() {
        return !empty($this->api_key);
    }
}