<?php
/**
 * Klasa odpowiedzialna za komunikację z API OpenAI
 */
class CleanSEO_AI_API {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $models = array(
        'gpt-4' => array(
            'max_tokens' => 8192,
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.03,
            'cost_per_1k_output' => 0.06
        ),
        'gpt-4-turbo' => array(
            'max_tokens' => 128000,
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03
        ),
        'gpt-3.5-turbo' => array(
            'max_tokens' => 16385,
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.0015,
            'cost_per_1k_output' => 0.002
        ),
        'claude-3' => array(
            'max_tokens' => 200000,
            'temperature' => 0.7,
            'cost_per_1k_input' => 0.008,
            'cost_per_1k_output' => 0.024
        )
    );
    private $max_retries = 3;
    private $retry_delay = 1; // sekundy
    private $timeout = 60;
    private $settings;
    private $cost_tracking = true;
    private $total_cost = 0;
    private $logger;
    private $cache;
    
    /**
     * Szablony promptów dla różnych zadań
     */
    private $prompt_templates = array(
        'meta_title' => 'Napisz w języku polskim skuteczny meta tytuł SEO dla artykułu o tytule "%s". 
            Słowa kluczowe to: %s. 
            Tytuł powinien mieć maksymalnie 60 znaków, być atrakcyjny dla czytelników i zawierać główne słowo kluczowe blisko początku.',
            
        'meta_description' => 'Napisz w języku polskim perswazyjny meta opis SEO dla artykułu o tytule "%s". 
            Słowa kluczowe to: %s. 
            Opis powinien mieć maksymalnie 155 znaków, być atrakcyjny dla czytelników, zawierać wezwanie do działania i naturalne użycie słów kluczowych.',
            
        'content' => 'Napisz w języku polskim wysokiej jakości treść na temat "%s". 
            Słowa kluczowe do uwzględnienia: %s. 
            Długość treści: %s (short = 300 słów, medium = 800 słów, long = 1500 słów). 
            Treść powinna być podzielona nagłówkami, zawierać wstęp, rozwinięcie i podsumowanie. 
            Użyj naturalnego stylu, angażującego tonu i pisz w drugiej osobie. 
            Słowa kluczowe powinny być użyte naturalnie i rozproszone w całej treści.
            Tekst powinien być zoptymalizowany pod SEO, ale pisany przede wszystkim dla czytelnika.',
            
        'competition' => 'Przeanalizuj poniższą treść konkurencji i podaj rekomendacje SEO dla tematu związanego z następującymi słowami kluczowymi: %s.
            Treść konkurencji: 
            %s
            
            Przeprowadź dogłębną analizę i podaj:
            1. Główne tematy poruszane przez konkurencję
            2. Brakujące aspekty, które moglibyśmy pokryć
            3. Słowa kluczowe, które używają i które również powinniśmy uwzględnić
            4. Strukturę treści, którą moglibyśmy zastosować, aby była lepsza
            5. Rekomendowane długości sekcji i całej treści
            6. Sugestie dotyczące nagłówków H1, H2, H3
            7. Propozycje unikalnych punktów sprzedaży (USP).',
            
        'keyword_research' => 'Przeprowadź analizę słów kluczowych dla tematu "%s" w języku polskim.
            Podaj:
            1. 10 głównych słów kluczowych z długim ogonem (long tail)
            2. 5 pytań, które ludzie często zadają na ten temat
            3. 3 tematy pokrewne, które warto uwzględnić
            4. Sugestie dotyczące intencji wyszukiwania dla każdego słowa kluczowego (informacyjna, transakcyjna, nawigacyjna)',
            
        'content_audit' => 'Przeprowadź audyt SEO poniższej treści pod kątem słów kluczowych: %s.
            Treść do analizy:
            %s
            
            Oceń:
            1. Gęstość słów kluczowych (czy jest odpowiednia, za niska, czy za wysoka)
            2. Struktura treści (nagłówki, akapity, listy)
            3. Czytelność tekstu
            4. Długość treści (czy jest wystarczająca)
            5. Jakość wprowadzenia i podsumowania
            6. Użycie wewnętrznych i zewnętrznych linków
            7. Okazje do poprawy
            8. Ogólna ocena w skali 1-10'
    );

    /**
     * Konstruktor
     */
    public function __construct($api_key = null) {
        global $wpdb;
        
        $this->api_key = $api_key ?: get_option('cleanseo_options')['openai_settings']['api_key'] ?? '';
        $this->load_settings();
        $this->init_logger();
        $this->init_cache();
        $this->calculate_total_cost();
    }
    
    /**
     * Załaduj ustawienia
     */
    private function load_settings() {
        $options = get_option('cleanseo_options', array());
        
        // Pobierz ustawienia OpenAI
        $openai_settings = $options['openai_settings'] ?? array();
        
        // Ustawienia z bazy danych
        $this->max_retries = $openai_settings['retry_attempts'] ?? 3;
        $this->retry_delay = $openai_settings['retry_delay'] ?? 1;
        $this->timeout = $openai_settings['timeout'] ?? 60;
        $this->cost_tracking = $openai_settings['cost_tracking'] ?? true;
    }
    
    /**
     * Inicjalizacja loggera
     */
    private function init_logger() {
        $this->logger = new CleanSEO_Logger('ai_api');
    }
    
    /**
     * Inicjalizacja cache
     */
    private function init_cache() {
        $this->cache = new CleanSEO_Cache('ai');
    }
    
    /**
     * Oblicz całkowity koszt z historii
     */
    private function calculate_total_cost() {
        $costs = get_option('cleanseo_api_costs', array());
        $this->total_cost = array_sum($costs);
    }

    /**
     * Wyślij zapytanie do API z mechanizmem retry
     *
     * @param string $prompt Treść zapytania
     * @param string $model Model AI (np. gpt-4, gpt-3.5-turbo)
     * @param int $max_tokens Maksymalna liczba tokenów w odpowiedzi
     * @param float $temperature Parametr losowości (0-1)
     * @param array $options Dodatkowe opcje
     * @return string|WP_Error Odpowiedź lub obiekt błędu
     */
    public function send_request($prompt, $model = null, $max_tokens = null, $temperature = null, $options = array()) {
        if (empty($prompt)) {
            return new WP_Error('empty_prompt', __('Prompt nie może być pusty.', 'cleanseo-optimizer'));
        }

        // Pobierz model z ustawień jeśli nie podano
        if (!$model) {
            $options = get_option('cleanseo_options', array());
            $model = $options['openai_settings']['model'] ?? 'gpt-3.5-turbo';
        }

        // Sprawdź cache jeśli włączony
        $options_cache = get_option('cleanseo_options', array());
        $cache_enabled = $options_cache['openai_settings']['cache_enabled'] ?? true;
        $cache_time = $options_cache['openai_settings']['cache_time'] ?? 86400;
        
        if ($cache_enabled) {
            $cache_key = md5($model . $prompt . ($max_tokens ?? '') . ($temperature ?? ''));
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                return $cached_response;
            }
        }

        // Pobierz ustawienia modelu
        if (!isset($this->models[$model])) {
            return new WP_Error('invalid_model', __('Nieprawidłowy model AI.', 'cleanseo-optimizer'));
        }
        $model_settings = $this->models[$model];

        // Walidacja max_tokens
        if ($max_tokens !== null) {
            $max_tokens = absint($max_tokens);
            if ($max_tokens <= 0 || $max_tokens > $model_settings['max_tokens']) {
                $max_tokens = min($model_settings['max_tokens'], 2000);
            }
        } else {
            $max_tokens = min($model_settings['max_tokens'], 2000);
        }

        // Walidacja temperature
        if ($temperature !== null) {
            $temperature = floatval($temperature);
            if ($temperature < 0 || $temperature > 1) {
                $temperature = $model_settings['temperature'];
            }
        } else {
            $temperature = $model_settings['temperature'];
        }

        // Przygotuj dane zapytania w zależności od modelu
        $data = $this->prepare_request_data($model, $prompt, $max_tokens, $temperature, $options);

        // Próby wysłania żądania
        $attempts = 0;
        $last_error = null;
        $start_time = microtime(true);

        while ($attempts < $this->max_retries) {
            try {
                $response = $this->make_api_request($model, $data);
                
                if (is_wp_error($response)) {
                    $last_error = $response;
                    $this->logger->log('error', 'API request failed', array(
                        'model' => $model,
                        'error' => $response->get_error_message(),
                        'attempt' => $attempts + 1
                    ));
                    
                    // Sprawdź czy to błąd tymczasowy
                    if ($this->is_temporary_error($response)) {
                        $attempts++;
                        if ($attempts < $this->max_retries) {
                            $delay = $this->retry_delay * pow(2, $attempts - 1); // Exponential backoff
                            sleep($delay);
                            continue;
                        }
                    }
                    return $response;
                }

                $duration = microtime(true) - $start_time;
                
                // Zapisz do cache jeśli włączony
                if ($cache_enabled) {
                    $this->cache_response($cache_key, $response, $cache_time);
                }
                
                // Zapisz log zapytania
                $this->log_request($model, $prompt, $response, $duration);
                
                // Śledź koszty
                if ($this->cost_tracking) {
                    $this->track_cost($model, $prompt, $response);
                }

                return $response;

            } catch (Exception $e) {
                $this->logger->log('error', 'Exception in API request', array(
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'attempt' => $attempts + 1
                ));
                
                $last_error = new WP_Error('api_error', $e->getMessage());
                $attempts++;
                
                if ($attempts < $this->max_retries) {
                    $delay = $this->retry_delay * pow(2, $attempts - 1); // Exponential backoff
                    sleep($delay);
                    continue;
                }
            }
        }

        return $last_error;
    }
    
    /**
     * Przygotuj dane zapytania w zależności od modelu
     */
    private function prepare_request_data($model, $prompt, $max_tokens, $temperature, $options = array()) {
        // Domyślne opcje
        $options = wp_parse_args($options, array(
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'top_p' => 1,
            'stop' => null,
            'stream' => false
        ));
        
        // OpenAI GPT modele używają formatu messages
        if (strpos($model, 'gpt') === 0) {
            $data = array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'Jesteś ekspertem SEO i copywriterem.'),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'frequency_penalty' => $options['frequency_penalty'],
                'presence_penalty' => $options['presence_penalty'],
                'top_p' => $options['top_p']
            );
            
            if ($options['stop'] !== null) {
                $data['stop'] = $options['stop'];
            }
            
            if ($options['stream'] === true) {
                $data['stream'] = true;
            }
        }
        // Anthropic Claude modele
        else if (strpos($model, 'claude') === 0) {
            $data = array(
                'model' => $model,
                'prompt' => "\n\nHuman: " . $prompt . "\n\nAssistant:",
                'max_tokens_to_sample' => $max_tokens,
                'temperature' => $temperature,
                'top_p' => $options['top_p']
            );
            
            if ($options['stop'] !== null) {
                $data['stop_sequences'] = $options['stop'];
            }
        }
        
        return $data;
    }

    /**
     * Wykonaj żądanie do API
     */
    private function make_api_request($model, $data) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Brak klucza API.', 'cleanseo-optimizer'));
        }

        $endpoint = $this->get_api_endpoint($model);
        if (empty($endpoint)) {
            return new WP_Error('invalid_endpoint', __('Nieprawidłowy endpoint API.', 'cleanseo-optimizer'));
        }
        
        $headers = $this->get_api_headers($model, $this->api_key);

        $args = array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => json_encode($data),
            'sslverify' => true
        );

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : sprintf(__('Błąd API (Status: %d)', 'cleanseo-optimizer'), $status);
                
            return new WP_Error('api_error', $error_message, array(
                'status' => $status,
                'response' => $body
            ));
        }

        $result = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Błąd parsowania odpowiedzi JSON.', 'cleanseo-optimizer'));
        }

        return $this->format_response($model, $result);
    }

    /**
     * Pobierz endpoint API dla modelu
     */
    private function get_api_endpoint($model) {
        $endpoints = array(
            'gpt-3.5-turbo' => 'https://api.openai.com/v1/chat/completions',
            'gpt-4' => 'https://api.openai.com/v1/chat/completions',
            'gpt-4-turbo' => 'https://api.openai.com/v1/chat/completions',
            'claude-3' => 'https://api.anthropic.com/v1/messages'
        );

        return isset($endpoints[$model]) ? $endpoints[$model] : '';
    }

    /**
     * Pobierz nagłówki API dla modelu
     */
    private function get_api_headers($model, $api_key) {
        $headers = array(
            'Content-Type' => 'application/json'
        );

        if (strpos($model, 'gpt') === 0) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        } else if (strpos($model, 'claude') === 0) {
            $headers['x-api-key'] = $api_key;
            $headers['anthropic-version'] = '2023-06-01';
        }

        return $headers;
    }

    /**
     * Formatuj odpowiedź API
     */
    private function format_response($model, $result) {
        if (strpos($model, 'gpt') === 0) {
            if (isset($result['choices'][0]['message']['content'])) {
                return $result['choices'][0]['message']['content'];
            }
        } else if (strpos($model, 'claude') === 0) {
            if (isset($result['content'][0]['text'])) {
                return $result['content'][0]['text'];
            }
        }

        return new WP_Error('invalid_response', __('Nieprawidłowa struktura odpowiedzi API.', 'cleanseo-optimizer'), $result);
    }
    
    /**
     * Zapisz log zapytania do bazy danych
     */
    private function log_request($model, $prompt, $response, $duration) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'seo_openai_logs';
        
        // Oszacuj liczbę tokenów
        $prompt_tokens = $this->estimate_token_count($prompt);
        $response_tokens = $this->estimate_token_count($response);
        $total_tokens = $prompt_tokens + $response_tokens;
        
        // Oblicz koszt
        $cost = $this->calculate_request_cost($model, $prompt_tokens, $response_tokens);
        
        // Zapisz do bazy danych
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'model' => $model,
                'prompt' => $prompt,
                'response' => $response,
                'tokens' => $total_tokens,
                'cost' => $cost,
                'duration' => $duration,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%f', '%f', '%s')
        );
    }

    /**
     * Oblicz szacunkowy koszt zapytania
     */
    private function calculate_request_cost($model, $prompt_tokens, $response_tokens) {
        if (!isset($this->models[$model])) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        $input_cost = ($prompt_tokens / 1000) * $model_info['cost_per_1k_input'];
        $output_cost = ($response_tokens / 1000) * $model_info['cost_per_1k_output'];
        
        return $input_cost + $output_cost;
    }
    
    /**
     * Pobierz odpowiedź z cache
     */
    private function get_cached_response($cache_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_ai_cache';
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT response FROM $table_name 
                WHERE hash_key = %s AND expires_at > %s",
                $cache_key,
                current_time('mysql')
            )
        );
        
        if ($result) {
            return $result->response;
        }
        
        return false;
    }
    
    /**
     * Zapisz odpowiedź do cache
     */
    private function cache_response($cache_key, $response, $cache_time) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_ai_cache';
        
        // Usuń stare odpowiedzi z tym samym kluczem
        $wpdb->delete($table_name, array('hash_key' => $cache_key));
        
        // Oblicz czas wygaśnięcia
        $expires_at = date('Y-m-d H:i:s', time() + $cache_time);
        
        // Zapisz nową odpowiedź
        $wpdb->insert(
            $table_name,
            array(
                'hash_key' => $cache_key,
                'model' => 'cache',
                'prompt' => 'cached_prompt',
                'response' => $response,
                'tokens' => $this->estimate_token_count($response),
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Śledź koszty API
     */
    private function track_cost($model, $prompt, $response) {
        if (!isset($this->models[$model])) {
            return;
        }
        
        // Oszacuj liczbę tokenów
        $prompt_tokens = $this->estimate_token_count($prompt);
        $response_tokens = $this->estimate_token_count($response);
        
        // Oblicz koszt
        $cost = $this->calculate_request_cost($model, $prompt_tokens, $response_tokens);
        $this->total_cost += $cost;

        // Zapisz koszt w bazie danych
        $costs = get_option('cleanseo_api_costs', array());
        $today = date('Y-m-d');
        
        if (!isset($costs[$today])) {
            $costs[$today] = 0;
        }
        
        $costs[$today] += $cost;
        update_option('cleanseo_api_costs', $costs);

        // Sprawdź limit budżetu
        $options = get_option('cleanseo_options', array());
        $budget_limit = $options['openai_settings']['budget_limit'] ?? 0;
        
        if ($budget_limit > 0 && $this->total_cost >= $budget_limit) {
            $this->notify_budget_exceeded();
        }
    }

    /**
     * Oszacuj liczbę tokenów w tekście
     * Dokładniejsza implementacja niż oryginalna
     */
    private function estimate_token_count($text) {
        if (empty($text)) {
            return 0;
        }
        
        // Uproszczona heurystyka: około 4 znaki na token dla języka angielskiego
        // Dla języka polskiego może być potrzebne dostosowanie
        $token_count = (int) (mb_strlen($text) / 4);
        
        // Uwzględnienie liczby słów (tokeny to czasem słowa lub części słów)
        $word_count = str_word_count(strip_tags($text));
        
        // Średnia z obu metod dla lepszej dokładności
        return max(1, (int) (($token_count + $word_count) / 2));
    }

    /**
     * Powiadom o przekroczeniu budżetu
     */
    private function notify_budget_exceeded() {
        $options = get_option('cleanseo_options', array());
        $email = $options['openai_settings']['notification_email'] ?? get_option('admin_email');
        
        if (empty($email)) {
            return;
        }

        $subject = __('CleanSEO: Przekroczono limit budżetu API', 'cleanseo-optimizer');
        $message = sprintf(
            __('Przekroczono limit budżetu API (%s USD). Całkowity koszt: %s USD. Sprawdź ustawienia pluginu CleanSEO, aby zwiększyć limit lub wyłączyć funkcje AI.', 'cleanseo-optimizer'),
            number_format($options['openai_settings']['budget_limit'] ?? 0, 2),
            number_format($this->total_cost, 2)
        );

        wp_mail($email, $subject, $message);
        
        // Zapisz log o przekroczeniu budżetu
        $this->logger->log('warning', 'Budget limit exceeded', array(
            'budget_limit' => $options['openai_settings']['budget_limit'] ?? 0,
            'total_cost' => $this->total_cost
        ));
    }

    /**
     * Sprawdź czy błąd jest tymczasowy
     */
    private function is_temporary_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }
        
        $temporary_errors = array(
            'timeout',
            'connection_failed',
            'http_request_failed',
            'rate_limit_exceeded',
            'server_error',
            'overloaded',
            'busy'
        );

        $error_code = $error->get_error_code();
        
        // Sprawdź bezpośrednio po kodzie
        if (in_array($error_code, $temporary_errors)) {
            return true;
        }
        
        // Sprawdź w treści błędu
        $error_message = $error->get_error_message();
        foreach ($temporary_errors as $temp_error) {
            if (stripos($error_message, $temp_error) !== false) {
                return true;
            }
        }
        
        // Sprawdź kody HTTP
        $error_data = $error->get_error_data();
        if (is_array($error_data) && isset($error_data['status'])) {
            $status = $error_data['status'];
            // 429 = too many requests, 500 & 503 = server errors
            if ($status == 429 || $status == 500 || $status == 503) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Pobierz całkowity koszt
     */
    public function get_total_cost() {
        return $this->total_cost;
    }

    /**
     * Pobierz koszty z ostatnich dni
     */
    public function get_recent_costs($days = 30) {
        $costs = get_option('cleanseo_api_costs', array());
        $recent_costs = array();
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $recent_costs[$date] = isset($costs[$date]) ? $costs[$date] : 0;
        }

        // Posortuj od najstarszej do najnowszej daty
        ksort($recent_costs);
        
        return $recent_costs;
    }

    /**
     * Wyczyść historię kosztów
     */
    public function clear_cost_history() {
        delete_option('cleanseo_api_costs');
        $this->total_cost = 0;
        return true;
    }

    /**
     * Generuj meta tytuł
     */
    public function generate_meta_title($post_title, $keywords = '') {
        // Walidacja tytułu
        if (empty($post_title) || !is_string($post_title)) {
            return new WP_Error('invalid_title', __('Nieprawidłowy tytuł.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tytułu
        $post_title = sanitize_text_field($post_title);

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        $prompt = sprintf(
            $this->prompt_templates['meta_title'],
            $post_title,
            $keywords
        );

        return $this->send_request($prompt);
    }

    /**
     * Generuj meta opis
     */
    public function generate_meta_description($post_title, $keywords = '') {
        // Walidacja tytułu
        if (empty($post_title) || !is_string($post_title)) {
            return new WP_Error('invalid_title', __('Nieprawidłowy tytuł.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tytułu
        $post_title = sanitize_text_field($post_title);

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        $prompt = sprintf(
            $this->prompt_templates['meta_description'],
            $post_title,
            $keywords
        );

        return $this->send_request($prompt);
    }

    /**
     * Generuj treść
     */
    public function generate_content($topic, $keywords = '', $length = 'medium') {
        // Walidacja tematu
        if (empty($topic) || !is_string($topic)) {
            return new WP_Error('invalid_topic', __('Nieprawidłowy temat.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tematu
        $topic = sanitize_text_field($topic);

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        // Walidacja długości
        if (!in_array($length, array('short', 'medium', 'long'))) {
            $length = 'medium';
        }

        $prompt = sprintf(
            $this->prompt_templates['content'],
            $topic,
            $keywords,
            $length
        );

        // Użyj większej liczby tokenów dla dłuższych treści
        $max_tokens = array(
            'short' => 500,
            'medium' => 1200,
            'long' => 2500
        );

        return $this->send_request($prompt, null, $max_tokens[$length]);
    }

    /**
     * Przeprowadź badanie słów kluczowych
     */
    public function research_keywords($topic) {
        // Walidacja tematu
        if (empty($topic) || !is_string($topic)) {
            return new WP_Error('invalid_topic', __('Nieprawidłowy temat.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tematu
        $topic = sanitize_text_field($topic);

        $prompt = sprintf(
            $this->prompt_templates['keyword_research'],
            $topic
        );

        return $this->send_request($prompt);
    }

    /**
     * Analizuj konkurencję
     */
    public function analyze_competition($url, $keywords) {
        // Walidacja URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Nieprawidłowy URL.', 'cleanseo-optimizer'));
        }

        // Sanityzacja URL
        $url = esc_url_raw($url);

        // Walidacja słów kluczowych
        if (empty($keywords) || !is_string($keywords)) {
            return new WP_Error('invalid_keywords', __('Nieprawidłowe słowa kluczowe.', 'cleanseo-optimizer'));
        }

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        // Pobierz treść
        $content = $this->fetch_url_content($url);
        if (is_wp_error($content)) {
            return $content;
        }

        // Skróć treść konkurencji, jeśli jest zbyt długa
        if (mb_strlen($content) > 10000) {
            $content = mb_substr($content, 0, 10000) . '...';
        }

        $prompt = sprintf(
            $this->prompt_templates['competition'],
            $keywords,
            $content
        );

        return $this->send_request($prompt, null, 2000);
    }

    /**
     * Przeprowadź audyt treści pod kątem SEO
     */
    public function audit_content($content, $keywords) {
        // Walidacja treści
        if (empty($content) || !is_string($content)) {
            return new WP_Error('invalid_content', __('Nieprawidłowa treść.', 'cleanseo-optimizer'));
        }

        // Walidacja słów kluczowych
        if (empty($keywords) || !is_string($keywords)) {
            return new WP_Error('invalid_keywords', __('Nieprawidłowe słowa kluczowe.', 'cleanseo-optimizer'));
        }

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        // Skróć treść, jeśli jest zbyt długa
        if (mb_strlen($content) > 15000) {
            $content = mb_substr($content, 0, 15000) . '...';
        }

        $prompt = sprintf(
            $this->prompt_templates['content_audit'],
            $keywords,
            $content
        );

        return $this->send_request($prompt, null, 2000);
    }

    /**
     * Pobierz treść z URL
     */
    public function fetch_url_content($url) {
        // Walidacja URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Nieprawidłowy URL.', 'cleanseo-optimizer'));
        }

        // Sanityzacja URL
        $url = esc_url_raw($url);

        // Sprawdź cache
        $cache_key = 'url_content_' . md5($url);
        $cached_content = get_transient($cache_key);
        if ($cached_content !== false) {
            return $cached_content;
        }

        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status !== 200) {
            return new WP_Error(
                'http_error',
                sprintf(__('Nie można pobrać treści. Kod HTTP: %d', 'cleanseo-optimizer'), $status)
            );
        }

        if (empty($body)) {
            return new WP_Error('empty_response', __('Pusta odpowiedź z serwera.', 'cleanseo-optimizer'));
        }

        // Usuń skrypty, style i komentarze
        $body = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $body);
        $body = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $body);
        $body = preg_replace('/<!--(.*)-->/Uis', '', $body);
        
        // Usuń tagi inne niż p, h1-h6, ul, ol, li aby zachować strukturę tekstu
        $body = strip_tags($body, '<p><h1><h2><h3><h4><h5><h6><ul><ol><li>');
        
        // Konwertuj HTML na tekst
        $content = wp_strip_all_tags($body);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (empty($content)) {
            return new WP_Error('no_content', __('Nie znaleziono treści.', 'cleanseo-optimizer'));
        }

        // Zapisz do cache na 24 godziny
        set_transient($cache_key, $content, 24 * HOUR_IN_SECONDS);

        return $content;
    }

    /**
     * Optymalizuj istniejący tekst dla SEO
     */
    public function optimize_content($content, $keywords, $options = array()) {
        // Walidacja treści
        if (empty($content) || !is_string($content)) {
            return new WP_Error('invalid_content', __('Nieprawidłowa treść.', 'cleanseo-optimizer'));
        }

        // Walidacja słów kluczowych
        if (empty($keywords) || !is_string($keywords)) {
            return new WP_Error('invalid_keywords', __('Nieprawidłowe słowa kluczowe.', 'cleanseo-optimizer'));
        }

        // Sanityzacja słów kluczowych
        $keywords = sanitize_text_field($keywords);

        // Domyślne opcje
        $options = wp_parse_args($options, array(
            'preserve_structure' => true,
            'improve_readability' => true,
            'add_headings' => true,
            'optimize_density' => true
        ));

        // Skróć treść, jeśli jest zbyt długa
        if (mb_strlen($content) > 20000) {
            $content = mb_substr($content, 0, 20000) . '...';
        }

        $prompt = "Zoptymalizuj poniższy tekst pod kątem SEO dla słów kluczowych: {$keywords}.\n\n";
        $prompt .= "Tekst do optymalizacji:\n{$content}\n\n";
        $prompt .= "Instrukcje:\n";
        
        if ($options['preserve_structure']) {
            $prompt .= "- Zachowaj ogólną strukturę tekstu\n";
        }
        
        if ($options['improve_readability']) {
            $prompt .= "- Popraw czytelność tekstu\n";
        }
        
        if ($options['add_headings']) {
            $prompt .= "- Dodaj lub zoptymalizuj nagłówki H2 i H3\n";
        }
        
        if ($options['optimize_density']) {
            $prompt .= "- Zoptymalizuj gęstość słów kluczowych (naturalnie, bez spamu)\n";
        }
        
        $prompt .= "- Zachowaj styl i ton tekstu\n";
        $prompt .= "- Nie skracaj tekstu, zachowaj jego długość\n";
        $prompt .= "- Zwróć zoptymalizowaną wersję tekstu bez komentarzy i wyjaśnień\n";

        return $this->send_request($prompt, null, 3000);
    }

    /**
     * Generuj pomysły na treści dla określonego tematu
     */
    public function generate_content_ideas($topic, $content_type = 'blog') {
        // Walidacja tematu
        if (empty($topic) || !is_string($topic)) {
            return new WP_Error('invalid_topic', __('Nieprawidłowy temat.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tematu
        $topic = sanitize_text_field($topic);

        // Walidacja typu treści
        $valid_types = array('blog', 'social', 'landing', 'newsletter', 'ebook');
        if (!in_array($content_type, $valid_types)) {
            $content_type = 'blog';
        }

        $prompt = "Wygeneruj 10 pomysłów na treści na temat \"{$topic}\" dla formatu: {$content_type}.\n\n";
        $prompt .= "Dla każdego pomysłu podaj:\n";
        $prompt .= "1. Pełny tytuł\n";
        $prompt .= "2. Krótki opis (2-3 zdania)\n";
        $prompt .= "3. Propozycje 3-5 nagłówków sekcji\n";
        $prompt .= "4. Kluczowe słowa i frazy do uwzględnienia\n\n";
        $prompt .= "Pomysły powinny być oryginalne, wartościowe dla czytelnika i zoptymalizowane pod SEO.";

        return $this->send_request($prompt);
    }

    /**
     * Generuj FAQ dla określonego tematu
     */
    public function generate_faq($topic, $count = 5) {
        // Walidacja tematu
        if (empty($topic) || !is_string($topic)) {
            return new WP_Error('invalid_topic', __('Nieprawidłowy temat.', 'cleanseo-optimizer'));
        }

        // Sanityzacja tematu
        $topic = sanitize_text_field($topic);

        // Walidacja liczby pytań
        $count = absint($count);
        if ($count < 1 || $count > 20) {
            $count = 5;
        }

        $prompt = "Stwórz sekcję FAQ składającą się z {$count} najczęściej zadawanych pytań na temat \"{$topic}\".\n\n";
        $prompt .= "Dla każdego pytania:\n";
        $prompt .= "1. Sformułuj pytanie w sposób naturalny, jak zadaliby je użytkownicy w wyszukiwarce\n";
        $prompt .= "2. Napisz zwięzłą, ale kompletną odpowiedź (3-5 zdań)\n";
        $prompt .= "3. Odpowiedź powinna być wartościowa, faktyczna i zoptymalizowana pod kątem SEO\n\n";
        $prompt .= "Zwróć FAQ w formacie HTML z użyciem struktury schema.org dla FAQ, aby był gotowy do umieszczenia na stronie.";

        return $this->send_request($prompt);
    }

    /**
     * Pobierz dostępne modele
     */
    public function get_available_models() {
        return array_keys($this->models);
    }

    /**
     * Sprawdź czy model jest dostępny
     */
    public function is_model_available($model) {
        return isset($this->models[$model]);
    }

    /**
     * Pobierz maksymalną liczbę tokenów dla modelu
     */
    public function get_model_max_tokens($model) {
        return isset($this->models[$model]) ? $this->models[$model]['max_tokens'] : null;
    }

    /**
     * Pobierz domyślną temperaturę dla modelu
     */
    public function get_model_temperature($model) {
        return isset($this->models[$model]) ? $this->models[$model]['temperature'] : null;
    }
    
    /**
     * Pobierz statystyki użycia API
     */
    public function get_usage_stats($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            return array(
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'models_usage' => array(),
                'daily_usage' => array()
            );
        }
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Całkowita liczba zapytań
        $total_requests = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE created_at >= %s",
                $start_date
            )
        );
        
        // Całkowita liczba tokenów
        $total_tokens = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(tokens) FROM $table_name 
                WHERE created_at >= %s",
                $start_date
            )
        );
        
        // Całkowity koszt
        $total_cost = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(cost) FROM $table_name 
                WHERE created_at >= %s",
                $start_date
            )
        );
        
        // Użycie według modeli
        $models_usage = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT model, COUNT(*) as count, SUM(tokens) as tokens, SUM(cost) as cost 
                FROM $table_name 
                WHERE created_at >= %s 
                GROUP BY model",
                $start_date
            ),
            ARRAY_A
        );
        
        // Dzienne użycie
        $daily_usage = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count, SUM(tokens) as tokens, SUM(cost) as cost 
                FROM $table_name 
                WHERE created_at >= %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $start_date
            ),
            ARRAY_A
        );
        
        return array(
            'total_requests' => (int) $total_requests,
            'total_tokens' => (int) $total_tokens,
            'total_cost' => (float) $total_cost,
            'models_usage' => $models_usage,
            'daily_usage' => $daily_usage
        );
    }
    
    /**
     * Wyczyść cache AI
     */
    public function clear_cache() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_ai_cache';
        
        // Usuń wszystkie wpisy z tabeli cache
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Wyczyść również transients URL
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_url_content_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_url_content_%'");
        
        return true;
    }
    
    /**
     * Testuj połączenie z API
     */
    public function test_connection() {
        $test_prompt = "Odpowiedz jednym słowem 'OK' aby potwierdzić, że API działa poprawnie.";
        
        $response = $this->send_request($test_prompt, null, 10);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Sprawdź czy odpowiedź zawiera "OK"
        if (stripos($response, 'OK') !== false) {
            return true;
        }
        
        return new WP_Error('invalid_response', __('Otrzymano nieprawidłową odpowiedź z API.', 'cleanseo-optimizer'), $response);
    }
}