<?php
/**
 * Klasa odpowiedzialna za obsługę modeli AI
 */
class CleanSEO_AI_Models {
    /**
     * Dostępne modele
     * @var array
     */
    private $models = array(
        'gpt-3.5-turbo' => array(
            'name' => 'GPT-3.5 Turbo',
            'provider' => 'openai',
            'description' => 'Szybki i ekonomiczny model do podstawowych zadań SEO i generowania treści.',
            'pricing' => array(
                'input' => 0.0015,
                'output' => 0.002
            ),
            'max_tokens' => 16385,
            'max_prompt_tokens' => 12288,
            'recommended_tasks' => array('meta_title', 'meta_description', 'excerpt', 'tags'),
            'default_temperature' => 0.7,
            'supported' => true,
            'version' => '0125'
        ),
        'gpt-4' => array(
            'name' => 'GPT-4',
            'provider' => 'openai',
            'description' => 'Zaawansowany model do tworzenia wysokiej jakości treści i złożonych analiz SEO.',
            'pricing' => array(
                'input' => 0.03,
                'output' => 0.06
            ),
            'max_tokens' => 8192,
            'max_prompt_tokens' => 6144,
            'recommended_tasks' => array('content', 'content_audit', 'competition', 'schema'),
            'default_temperature' => 0.7,
            'supported' => true,
            'version' => '0613'
        ),
        'gpt-4-turbo' => array(
            'name' => 'GPT-4 Turbo',
            'provider' => 'openai',
            'description' => 'Najnowsza wersja GPT-4 z większym kontekstem i zaktualizowaną wiedzą.',
            'pricing' => array(
                'input' => 0.01,
                'output' => 0.03
            ),
            'max_tokens' => 128000,
            'max_prompt_tokens' => 110000,
            'recommended_tasks' => array('content', 'content_audit', 'competition', 'schema', 'keyword_research'),
            'default_temperature' => 0.7,
            'supported' => true,
            'version' => '0125'
        ),
        'gemini-pro' => array(
            'name' => 'Gemini Pro (Google)',
            'provider' => 'google',
            'description' => 'Model AI od Google z szeroką wiedzą i dobrą wydajnością przy optymalizacji stron.',
            'pricing' => array(
                'input' => 0.00125,
                'output' => 0.00375
            ),
            'max_tokens' => 30720,
            'max_prompt_tokens' => 25000,
            'recommended_tasks' => array('content', 'meta_title', 'meta_description'),
            'default_temperature' => 0.5,
            'supported' => true,
            'version' => '1.0'
        ),
        'claude-3-opus' => array(
            'name' => 'Claude 3 Opus (Anthropic)',
            'provider' => 'anthropic',
            'description' => 'Najwyższej klasy model Claude, idealny do zaawansowanych analiz i złożonych treści SEO.',
            'pricing' => array(
                'input' => 0.015,
                'output' => 0.075
            ),
            'max_tokens' => 200000,
            'max_prompt_tokens' => 180000,
            'recommended_tasks' => array('content', 'content_audit', 'competition', 'keyword_research'),
            'default_temperature' => 0.5,
            'supported' => true,
            'version' => '1.0'
        ),
        'claude-3-sonnet' => array(
            'name' => 'Claude 3 Sonnet (Anthropic)',
            'provider' => 'anthropic',
            'description' => 'Zrównoważony model pod względem jakości i kosztów, dobry do większości zadań SEO.',
            'pricing' => array(
                'input' => 0.003,
                'output' => 0.015
            ),
            'max_tokens' => 200000,
            'max_prompt_tokens' => 180000,
            'recommended_tasks' => array('content', 'meta_title', 'meta_description', 'excerpt', 'faq'),
            'default_temperature' => 0.5,
            'supported' => true,
            'version' => '1.0'
        ),
        'claude-3-haiku' => array(
            'name' => 'Claude 3 Haiku (Anthropic)',
            'provider' => 'anthropic',
            'description' => 'Szybki i ekonomiczny model Claude, dobry do prostych zadań SEO.',
            'pricing' => array(
                'input' => 0.00025,
                'output' => 0.00125
            ),
            'max_tokens' => 200000,
            'max_prompt_tokens' => 180000,
            'recommended_tasks' => array('meta_title', 'meta_description', 'excerpt', 'tags'),
            'default_temperature' => 0.5,
            'supported' => true,
            'version' => '1.0'
        )
    );
    
    /**
     * Informacje o dostawcach API
     * @var array
     */
    private $providers = array(
        'openai' => array(
            'name' => 'OpenAI',
            'api_url' => 'https://api.openai.com',
            'verification_endpoint' => '/v1/models',
            'key_format' => '^sk-[A-Za-z0-9]{32,}$',
            'key_prefix' => 'sk-',
            'documentation_url' => 'https://platform.openai.com/docs/',
            'website' => 'https://openai.com'
        ),
        'google' => array(
            'name' => 'Google AI',
            'api_url' => 'https://generativelanguage.googleapis.com',
            'verification_endpoint' => '/v1beta/models',
            'key_format' => '^AIza[A-Za-z0-9_-]{35}$',
            'key_prefix' => 'AIza',
            'documentation_url' => 'https://ai.google.dev/docs',
            'website' => 'https://ai.google.dev'
        ),
        'anthropic' => array(
            'name' => 'Anthropic',
            'api_url' => 'https://api.anthropic.com',
            'verification_endpoint' => '/v1/models',
            'key_format' => '^sk-ant-[A-Za-z0-9]{32,}$',
            'key_prefix' => 'sk-ant-',
            'documentation_url' => 'https://docs.anthropic.com',
            'website' => 'https://anthropic.com'
        )
    );
    
    /**
     * Lista zadań AI i ich wymagania
     * @var array
     */
    private $tasks = array(
        'meta_title' => array(
            'name' => 'Generowanie meta tytułów',
            'description' => 'Tworzenie zoptymalizowanych tytułów SEO',
            'min_level' => 'basic',
            'tokens_required' => 100,
            'temperature' => 0.7
        ),
        'meta_description' => array(
            'name' => 'Generowanie meta opisów',
            'description' => 'Tworzenie perswazyjnych meta opisów',
            'min_level' => 'basic',
            'tokens_required' => 200,
            'temperature' => 0.7
        ),
        'content' => array(
            'name' => 'Generowanie treści',
            'description' => 'Tworzenie pełnych artykułów i treści',
            'min_level' => 'advanced',
            'tokens_required' => 3000,
            'temperature' => 0.7
        ),
        'content_audit' => array(
            'name' => 'Audyt treści',
            'description' => 'Analiza istniejących treści pod kątem SEO',
            'min_level' => 'advanced',
            'tokens_required' => 1000,
            'temperature' => 0.3
        ),
        'competition' => array(
            'name' => 'Analiza konkurencji',
            'description' => 'Analiza treści konkurencyjnych',
            'min_level' => 'advanced',
            'tokens_required' => 2000,
            'temperature' => 0.2
        ),
        'keyword_research' => array(
            'name' => 'Badanie słów kluczowych',
            'description' => 'Generowanie sugestii słów kluczowych',
            'min_level' => 'medium',
            'tokens_required' => 1000,
            'temperature' => 0.5
        ),
        'schema' => array(
            'name' => 'Generowanie schema.org',
            'description' => 'Tworzenie kodu JSON-LD',
            'min_level' => 'medium',
            'tokens_required' => 500,
            'temperature' => 0.1
        ),
        'excerpt' => array(
            'name' => 'Generowanie wyciągów',
            'description' => 'Tworzenie krótkich opisów',
            'min_level' => 'basic',
            'tokens_required' => 200,
            'temperature' => 0.6
        ),
        'tags' => array(
            'name' => 'Generowanie tagów',
            'description' => 'Sugerowanie odpowiednich tagów',
            'min_level' => 'basic',
            'tokens_required' => 100,
            'temperature' => 0.5
        ),
        'faq' => array(
            'name' => 'Generowanie FAQ',
            'description' => 'Tworzenie sekcji pytań i odpowiedzi',
            'min_level' => 'medium',
            'tokens_required' => 1000,
            'temperature' => 0.6
        )
    );
    
    /**
     * Obiekt loggera
     * @var CleanSEO_Logger
     */
    private $logger;
    
    /**
     * Cache kluczy API
     * @var array
     */
    private $api_keys_cache = array();
    
    /**
     * Domyślne ustawienia
     * @var array
     */
    private $defaults = array(
        'default_model' => 'gpt-3.5-turbo',
        'default_temperature' => 0.7,
        'default_max_tokens' => 2000,
        'auto_fallback' => true,
        'cache_enabled' => true,
        'cache_lifetime' => 86400, // 24 godziny
        'verification_timeout' => 15
    );

    /**
     * Konstruktor
     * 
     * @param CleanSEO_Logger $logger Obiekt loggera (opcjonalny)
     */
    public function __construct($logger = null) {
        // Inicjalizacja loggera
        $this->logger = $logger ?: new CleanSEO_Logger('ai_models');
        
        // Załaduj niestandardowe modele z filtru WordPress
        $this->load_custom_models();
        
        // Inicjalizuj cache kluczy API
        $this->init_api_keys_cache();
    }
    
    /**
     * Inicjalizuje cache kluczy API
     */
    private function init_api_keys_cache() {
        // Pobierz cache z opcji
        $this->api_keys_cache = get_option('cleanseo_api_keys_cache', array());
        
        // Usuń przestarzałe dane z cache (starsze niż 24 godziny)
        foreach ($this->api_keys_cache as $key => $data) {
            if (isset($data['timestamp']) && (time() - $data['timestamp']) > 86400) {
                unset($this->api_keys_cache[$key]);
            }
        }
        
        // Zaktualizuj opcję
        update_option('cleanseo_api_keys_cache', $this->api_keys_cache);
    }
    
    /**
     * Ładuje niestandardowe modele przez filtr WordPress
     */
    private function load_custom_models() {
        // Pozwól na dodanie/modyfikację modeli przez filtr
        $this->models = apply_filters('cleanseo_ai_models', $this->models);
        
        // Pozwól na dodanie/modyfikację dostawców przez filtr
        $this->providers = apply_filters('cleanseo_ai_providers', $this->providers);
        
        // Pozwól na dodanie/modyfikację zadań przez filtr
        $this->tasks = apply_filters('cleanseo_ai_tasks', $this->tasks);
    }

    /**
     * Weryfikuje klucz API
     * 
     * @param string $api_key Klucz API
     * @param string $provider Dostawca (opcjonalnie)
     * @return array|WP_Error Informacje o pomyślnej weryfikacji lub błąd
     */
    public function verify_api_key($api_key, $provider = null) {
        // Jeśli nie podano dostawcy, określ go na podstawie klucza
        if ($provider === null) {
            $provider = $this->get_provider_from_key($api_key);
        }
        
        // Sprawdź czy dostawca jest obsługiwany
        if ($provider === 'unknown' || !isset($this->providers[$provider])) {
            return new WP_Error('invalid_provider', __('Nieznany dostawca API. Upewnij się, że używasz prawidłowego klucza API.', 'cleanseo-optimizer'));
        }
        
        // Sprawdź, czy klucz jest w poprawnym formacie
        $key_format = $this->providers[$provider]['key_format'];
        if (!preg_match("/{$key_format}/", $api_key)) {
            return new WP_Error('invalid_key_format', __('Nieprawidłowy format klucza API.', 'cleanseo-optimizer'));
        }
        
        // Sprawdź czy weryfikacja jest już w cache
        $cache_key = md5($api_key);
        if (isset($this->api_keys_cache[$cache_key]) && !empty($this->api_keys_cache[$cache_key]['valid'])) {
            // Sprawdź czy cache nie jest przestarzały
            if ((time() - $this->api_keys_cache[$cache_key]['timestamp']) < 3600) { // 1 godzina
                return $this->api_keys_cache[$cache_key];
            }
        }
        
        // Przeprowadź weryfikację w zależności od dostawcy
        try {
            $verification_result = array();
            
            switch ($provider) {
                case 'openai':
                    $verification_result = $this->verify_openai_key($api_key);
                    break;
                case 'google':
                    $verification_result = $this->verify_gemini_key($api_key);
                    break;
                case 'anthropic':
                    $verification_result = $this->verify_claude_key($api_key);
                    break;
                default:
                    throw new Exception(__('Nieznany dostawca API.', 'cleanseo-optimizer'));
            }
            
            // Dodaj weryfikację do cache
            $verification_result['timestamp'] = time();
            $this->api_keys_cache[$cache_key] = $verification_result;
            update_option('cleanseo_api_keys_cache', $this->api_keys_cache);
            
            return $verification_result;
            
        } catch (Exception $e) {
            $error = new WP_Error('verification_error', $e->getMessage());
            
            // Loguj błąd
            $this->logger->error('Błąd weryfikacji klucza API', array(
                'provider' => $provider,
                'error' => $e->getMessage()
            ));
            
            return $error;
        }
    }
    
    /**
     * Określa dostawcę na podstawie klucza API
     * 
     * @param string $api_key Klucz API
     * @return string Identyfikator dostawcy
     */
    private function get_provider_from_key($api_key) {
        foreach ($this->providers as $provider_id => $provider) {
            if (isset($provider['key_prefix']) && strpos($api_key, $provider['key_prefix']) === 0) {
                return $provider_id;
            }
        }
        return 'unknown';
    }

    /**
     * Weryfikuje klucz API OpenAI
     * 
     * @param string $api_key Klucz API
     * @return array Informacje o weryfikacji
     * @throws Exception W przypadku błędu
     */
    private function verify_openai_key($api_key) {
        $provider = $this->providers['openai'];
        $timeout = $this->get_setting('verification_timeout', 15);
        
        $response = wp_remote_get($provider['api_url'] . $provider['verification_endpoint'], array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => $timeout
        ));

        if (is_wp_error($response)) {
            throw new Exception(__('Błąd połączenia: ', 'cleanseo-optimizer') . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Nieznany błąd', 'cleanseo-optimizer');
            throw new Exception(__('Błąd weryfikacji: ', 'cleanseo-optimizer') . $error_message);
        }
        
        // Zbierz informacje o dostępnych modelach
        $available_models = array();
        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $model) {
                if (isset($model['id'])) {
                    $available_models[] = $model['id'];
                }
            }
        }
        
        // Sprawdź, które z naszych modeli są dostępne dla tego klucza API
        $supported_models = array();
        foreach ($this->models as $model_id => $model) {
            if ($model['provider'] === 'openai' && in_array($model_id, $available_models)) {
                $supported_models[] = $model_id;
            }
        }

        return array(
            'valid' => true,
            'provider' => 'openai',
            'available_models' => $available_models,
            'supported_models' => $supported_models
        );
    }

    /**
     * Weryfikuje klucz API Gemini
     * 
     * @param string $api_key Klucz API
     * @return array Informacje o weryfikacji
     * @throws Exception W przypadku błędu
     */
    private function verify_gemini_key($api_key) {
        $provider = $this->providers['google'];
        $timeout = $this->get_setting('verification_timeout', 15);
        
        // URL do API Gemini z kluczem API
        $url = $provider['api_url'] . $provider['verification_endpoint'] . "?key={$api_key}";
        
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            throw new Exception(__('Błąd połączenia: ', 'cleanseo-optimizer') . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Nieznany błąd', 'cleanseo-optimizer');
            throw new Exception(__('Błąd weryfikacji: ', 'cleanseo-optimizer') . $error_message);
        }
        
        // Zbierz informacje o dostępnych modelach
        $available_models = array();
        if (isset($body['models']) && is_array($body['models'])) {
            foreach ($body['models'] as $model) {
                if (isset($model['name'])) {
                    // Pobierz tylko nazwę modelu z pełnej ścieżki (np. "models/gemini-pro")
                    $model_name = basename($model['name']);
                    $available_models[] = $model_name;
                }
            }
        }
        
        // Sprawdź, które z naszych modeli są dostępne dla tego klucza API
        $supported_models = array();
        foreach ($this->models as $model_id => $model) {
            if ($model['provider'] === 'google' && in_array($model_id, $available_models)) {
                $supported_models[] = $model_id;
            }
        }

        return array(
            'valid' => true,
            'provider' => 'google',
            'available_models' => $available_models,
            'supported_models' => $supported_models
        );
    }

    /**
     * Weryfikuje klucz API Claude
     * 
     * @param string $api_key Klucz API
     * @return array Informacje o weryfikacji
     * @throws Exception W przypadku błędu
     */
    private function verify_claude_key($api_key) {
        $provider = $this->providers['anthropic'];
        $timeout = $this->get_setting('verification_timeout', 15);
        
        $response = wp_remote_get($provider['api_url'] . $provider['verification_endpoint'], array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'timeout' => $timeout
        ));

        if (is_wp_error($response)) {
            throw new Exception(__('Błąd połączenia: ', 'cleanseo-optimizer') . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Nieznany błąd', 'cleanseo-optimizer');
            throw new Exception(__('Błąd weryfikacji: ', 'cleanseo-optimizer') . $error_message);
        }
        
        // Zbierz informacje o dostępnych modelach
        $available_models = array();
        if (isset($body['models']) && is_array($body['models'])) {
            foreach ($body['models'] as $model) {
                if (isset($model['id'])) {
                    $available_models[] = $model['id'];
                }
            }
        }
        
        // Sprawdź, które z naszych modeli są dostępne dla tego klucza API
        $supported_models = array();
        foreach ($this->models as $model_id => $model) {
            if ($model['provider'] === 'anthropic' && in_array($model_id, $available_models)) {
                $supported_models[] = $model_id;
            }
        }

        return array(
            'valid' => true,
            'provider' => 'anthropic',
            'available_models' => $available_models,
            'supported_models' => $supported_models
        );
    }

    /**
     * Pobiera dostępne modele
     * 
     * @param string $provider Filtruj według dostawcy (opcjonalnie)
     * @param bool $only_supported Tylko obsługiwane modele
     * @return array Dostępne modele
     */
    public function get_available_models($provider = null, $only_supported = true) {
        $models = $this->models;
        
        // Filtruj modele według dostawcy
        if ($provider !== null) {
            $models = array_filter($models, function($model) use ($provider) {
                return $model['provider'] === $provider;
            });
        }
        
        // Filtruj tylko obsługiwane modele
        if ($only_supported) {
            $models = array_filter($models, function($model) {
                return isset($model['supported']) && $model['supported'] === true;
            });
        }
        
        return $models;
    }

    /**
     * Pobiera konkretny model
     * 
     * @param string $model_id Identyfikator modelu
     * @return array|null Informacje o modelu lub null
     */
    public function get_model($model_id) {
        return isset($this->models[$model_id]) ? $this->models[$model_id] : null;
    }

    /**
     * Pobiera domyślny model
     * 
     * @return array Informacje o domyślnym modelu
     */
    public function get_default_model() {
        $default_model_id = $this->get_setting('default_model', $this->defaults['default_model']);
        $model = $this->get_model($default_model_id);
        
        // Jeśli domyślny model nie istnieje, wróć do gpt-3.5-turbo
        if ($model === null) {
            $model = $this->get_model('gpt-3.5-turbo');
        }
        
        return $model;
    }
    
    /**
     * Pobiera informacje o dostawcy
     * 
     * @param string $provider_id Identyfikator dostawcy
     * @return array|null Informacje o dostawcy lub null
     */
    public function get_provider($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id] : null;
    }
    
    /**
     * Pobiera wszystkich dostawców
     * 
     * @return array Informacje o dostawcach
     */
    public function get_providers() {
        return $this->providers;
    }
    
    /**
     * Pobiera informacje o zadaniu
     * 
     * @param string $task_id Identyfikator zadania
     * @return array|null Informacje o zadaniu lub null
     */
    public function get_task($task_id) {
        return isset($this->tasks[$task_id]) ? $this->tasks[$task_id] : null;
    }
    
    /**
     * Pobiera wszystkie zadania
     * 
     * @return array Informacje o zadaniach
     */
    public function get_tasks() {
        return $this->tasks;
    }
    
    /**
     * Pobiera modele zalecane do konkretnego zadania
     * 
     * @param string $task_id Identyfikator zadania
     * @return array Zalecane modele
     */
    public function get_models_for_task($task_id) {
        $task = $this->get_task($task_id);
        if ($task === null) {
            return array();
        }
        
        $recommended_models = array();
        foreach ($this->models as $model_id => $model) {
            if (isset($model['recommended_tasks']) && in_array($task_id, $model['recommended_tasks'])) {
                $recommended_models[$model_id] = $model;
            }
        }
        
        // Jeśli nie znaleziono żadnych zalecanych modeli, zwróć wszystkie obsługiwane
        if (empty($recommended_models)) {
            return $this->get_available_models();
        }
        
        return $recommended_models;
    }

    /**
     * Oblicza koszt zapytania
     * 
     * @param string $model_id Identyfikator modelu
     * @param int $input_tokens Liczba tokenów wejściowych
     * @param int $output_tokens Liczba tokenów wyjściowych
     * @return float Koszt zapytania
     */
    public function calculate_cost($model_id, $input_tokens, $output_tokens) {
        $model = $this->get_model($model_id);
        
        if (!$model || !isset($model['pricing'])) {
            return 0;
        }
        
        $input_cost = ($input_tokens / 1000) * $model['pricing']['input'];
        $output_cost = ($output_tokens / 1000) * $model['pricing']['output'];
        
        return $input_cost + $output_cost;
    }
    
    /**
     * Szacuje liczbę tokenów w tekście
     * 
     * @param string $text Tekst do analizy
     * @param string $model_id Identyfikator modelu (opcjonalnie)
     * @return int Szacowana liczba tokenów
     */
    public function estimate_tokens($text, $model_id = null) {
        if (empty($text)) {
            return 0;
        }
        
        // Wybierz model
        if ($model_id === null) {
            $model = $this->get_default_model();
        } else {
            $model = $this->get_model($model_id);
        }
        
        // Bardzo proste oszacowanie: około 4 znaki na token
        // (bardziej zaawansowane metody wymagałyby tokenizera specyficznego dla modelu)
        return (int) ceil(mb_strlen($text) / 4);
    }
    
    /**
     * Przygotowuje prompt do zadania
     * 
     * @param string $task_id Identyfikator zadania
     * @param array $data Dane do promptu
     * @param string $model_id Identyfikator modelu (opcjonalnie)
     * @return array Przygotowany prompt i metadane
     */
    public function prepare_prompt($task_id, $data, $model_id = null) {
        $task = $this->get_task($task_id);
        if ($task === null) {
            return new WP_Error('invalid_task', __('Nieprawidłowe zadanie.', 'cleanseo-optimizer'));
        }
        
        // Jeśli nie podano modelu, wybierz optymalny dla tego zadania
        if ($model_id === null) {
            $recommended_models = $this->get_models_for_task($task_id);
            if (!empty($recommended_models)) {
                // Wybierz pierwszy z zalecanych modeli
                reset($recommended_models);
                $model_id = key($recommended_models);
            } else {
                // Użyj domyślnego modelu
                $model = $this->get_default_model();
                $model_id = array_search($model, $this->models);
            }
        }
        
        // Pobierz model
        $model = $this->get_model($model_id);
        if ($model === null) {
            return new WP_Error('invalid_model', __('Nieprawidłowy model.', 'cleanseo-optimizer'));
        }
        
        // Pobierz szablon promptu dla zadania
        $prompt_template = $this->get_prompt_template($task_id, $model['provider']);
        if (empty($prompt_template)) {
            return new WP_Error('missing_template', __('Brak szablonu promptu dla tego zadania.', 'cleanseo-optimizer'));
        }
        
        // Zastąp zmienne w szablonie
        $prompt = $prompt_template;
        foreach ($data as $key => $value) {
            $prompt = str_replace('{' . $key . '}', $value, $prompt);
        }
        
        // Ustaw odpowiednią temperaturę
        $temperature = isset($task['temperature']) ? $task['temperature'] : $model['default_temperature'];
        
        // Ustaw odpowiednią liczbę tokenów
        $max_tokens = isset($task['tokens_required']) ? $task['tokens_required'] : $this->get_setting('default_max_tokens', $this->defaults['default_max_tokens']);
        
        // Sprawdź czy prompt nie jest za długi dla modelu
        $prompt_tokens = $this->estimate_tokens($prompt, $model_id);
        $max_prompt_tokens = isset($model['max_prompt_tokens']) ? $model['max_prompt_tokens'] : ($model['max_tokens'] * 0.8); // 80% maksymalnej liczby tokenów
        
        if ($prompt_tokens > $max_prompt_tokens) {
            // Jeśli prompt jest za długi, skróć go
            if ($this->get_setting('auto_fallback', $this->defaults['auto_fallback'])) {
                // Automatyczne skracanie promptu
                $prompt = $this->truncate_prompt($prompt, $max_prompt_tokens, $model_id);
            } else {
                // Zgłoś błąd
                return new WP_Error(
                    'prompt_too_long', 
                    sprintf(
                        __('Prompt jest za długi dla tego modelu. Limit: %d tokenów, prompt: %d tokenów.', 'cleanseo-optimizer'), 
                        $max_prompt_tokens, 
                        $prompt_tokens
                    )
                );
            }
        }
        
        // Przygotuj metadane
        $metadata = array(
            'task' => $task_id,
            'model' => $model_id,
            'provider' => $model['provider'],
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'estimated_prompt_tokens' => $this->estimate_tokens($prompt, $model_id)
        );
        
        return array(
            'prompt' => $prompt,
            'metadata' => $metadata
        );
    }
    
    /**
     * Skraca prompt do określonej liczby tokenów
     * 
     * @param string $prompt Prompt do skrócenia
     * @param int $max_tokens Maksymalna liczba tokenów
     * @param string $model_id Identyfikator modelu
     * @return string Skrócony prompt
     */
    private function truncate_prompt($prompt, $max_tokens, $model_id) {
        // Jeśli prompt jest krótszy niż limit, zwróć go bez zmian
        $prompt_tokens = $this->estimate_tokens($prompt, $model_id);
        if ($prompt_tokens <= $max_tokens) {
            return $prompt;
        }
        
        // Szacuj liczbę znaków do usunięcia
        $excess_tokens = $prompt_tokens - $max_tokens;
        $excess_chars = $excess_tokens * 4; // Około 4 znaki na token
        
        // Zachowaj 20% margines bezpieczeństwa
        $excess_chars = (int) ($excess_chars * 1.2);
        
        // Skróć prompt od środka, zachowując początek i koniec
        $keep_ratio = 0.7; // Zachowaj 70% z początku, 30% z końca
        $keep_start = (int) (mb_strlen($prompt) - $excess_chars) * $keep_ratio;
        $keep_end = (int) (mb_strlen($prompt) - $excess_chars) * (1 - $keep_ratio);
        
        $truncated = mb_substr($prompt, 0, $keep_start) . 
                     "\n\n[...treść skrócona...]\n\n" . 
                     mb_substr($prompt, -$keep_end);
        
        // Loguj informację o skróceniu
        $this->logger->info('Prompt został automatycznie skrócony', array(
            'original_length' => mb_strlen($prompt),
            'truncated_length' => mb_strlen($truncated),
            'original_tokens' => $prompt_tokens,
            'max_tokens' => $max_tokens,
            'model' => $model_id
        ));
        
        return $truncated;
    }
    
    /**
     * Pobiera szablon promptu dla zadania
     * 
     * @param string $task_id Identyfikator zadania
     * @param string $provider Dostawca (opcjonalnie)
     * @return string Szablon promptu
     */
    private function get_prompt_template($task_id, $provider = null) {
        // Pobierz szablony z opcji
        $templates = get_option('cleanseo_prompt_templates', array());
        
        // Klucz określający zadanie i dostawcę
        $specific_key = ($provider !== null) ? "{$task_id}_{$provider}" : $task_id;
        
        // Spróbuj znaleźć specyficzny szablon dla dostawcy
        if ($provider !== null && isset($templates[$specific_key])) {
            return $templates[$specific_key];
        }
        
        // Spróbuj znaleźć ogólny szablon
        if (isset($templates[$task_id])) {
            return $templates[$task_id];
        }
        
        // Zwróć domyślny szablon
        return $this->get_default_prompt_template($task_id);
    }
    
    /**
     * Pobiera domyślny szablon promptu
     * 
     * @param string $task_id Identyfikator zadania
     * @return string Domyślny szablon promptu
     */
    private function get_default_prompt_template($task_id) {
        $default_templates = array(
            'meta_title' => 'Napisz w języku polskim skuteczny meta tytuł SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tytuł powinien mieć maksymalnie 60 znaków, być atrakcyjny dla czytelników i zawierać główne słowo kluczowe.',
            
            'meta_description' => 'Napisz w języku polskim perswazyjny meta opis SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Opis powinien mieć maksymalnie 155 znaków, być atrakcyjny dla czytelników, zawierać wezwanie do działania i naturalne użycie słów kluczowych.',
            
            'content' => 'Napisz w języku polskim wysokiej jakości treść na temat "{title}". Słowa kluczowe: {keywords}. Długość treści: {length} (short = 300 słów, medium = 800 słów, long = 1500 słów). Treść powinna być podzielona nagłówkami, zawierać wstęp, rozwinięcie i podsumowanie. Użyj naturalnego stylu, angażującego tonu i pisz w drugiej osobie. Słowa kluczowe powinny być użyte naturalnie i rozproszone w całej treści.',
            
            'content_audit' => 'Przeprowadź audyt SEO poniższej treści pod kątem słów kluczowych: {keywords}. Treść: {content}. Oceń gęstość słów kluczowych, strukturę treści, czytelność, długość i ogólną jakość z perspektywy SEO. Podaj ocenę w skali 1-10 oraz szczegółowe rekomendacje dotyczące poprawy.',
            
            'competition' => 'Przeanalizuj poniższą treść konkurencji i podaj rekomendacje SEO dla tematu związanego z następującymi słowami kluczowymi: {keywords}. Treść konkurencji: {content}. Podaj: 1) Główne tematy poruszane przez konkurencję, 2) Brakujące aspekty, które moglibyśmy pokryć, 3) Słowa kluczowe, które używają i które również powinniśmy uwzględnić, 4) Propozycję struktury treści, która byłaby lepsza.',
            
            'keyword_research' => 'Przeprowadź analizę słów kluczowych dla tematu "{topic}" w języku polskim. Podaj: 1) 10 głównych słów kluczowych z długim ogonem (long tail), 2) 5 pytań, które ludzie często zadają na ten temat, 3) 3 tematy pokrewne, które warto uwzględnić, 4) Sugestie dotyczące intencji wyszukiwania dla każdego słowa kluczowego.',
            
            'schema' => 'Wygeneruj kod JSON-LD dla schema.org Article na podstawie artykułu o tytule "{title}" i słowach kluczowych: {keywords}. Zwróć tylko czysty kod JSON bez dodatkowych wyjaśnień.',
            
            'excerpt' => 'Napisz krótki, przyciągający uwagę wyciąg dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Wyciąg powinien mieć maksymalnie 160 znaków i zachęcać do przeczytania całego artykułu.',
            
            'tags' => 'Zaproponuj 5-8 tagów dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tagi powinny być krótkimi frazami związanymi z tematem artykułu i użytecznymi dla SEO. Podaj tylko listę tagów, bez dodatkowych wyjaśnień.',
            
            'faq' => 'Stwórz sekcję FAQ (5 pytań i odpowiedzi) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Każde pytanie powinno być naturalne i odzwierciedlać rzeczywiste zapytania użytkowników. Każda odpowiedź powinna mieć 2-3 zdania. Zwróć FAQ w formacie HTML z użyciem struktury schema.org.'
        );
        
        return isset($default_templates[$task_id]) ? $default_templates[$task_id] : '';
    }
    
    /**
     * Pobiera ustawienie z opcji
     * 
     * @param string $key Klucz ustawienia
     * @param mixed $default Wartość domyślna
     * @return mixed Wartość ustawienia
     */
    private function get_setting($key, $default = null) {
        $options = get_option('cleanseo_ai_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Pobiera ustawienia modeli AI
     * 
     * @return array Ustawienia modeli AI
     */
    public function get_settings() {
        $settings = get_option('cleanseo_ai_settings', array());
        return wp_parse_args($settings, $this->defaults);
    }
    
    /**
     * Zapisuje ustawienia modeli AI
     * 
     * @param array $settings Nowe ustawienia
     * @return bool Czy operacja się powiodła
     */
    public function save_settings($settings) {
        // Walidacja ustawień
        $validated = array();
        
        // Default model
        if (isset($settings['default_model']) && isset($this->models[$settings['default_model']])) {
            $validated['default_model'] = $settings['default_model'];
        } else {
            $validated['default_model'] = $this->defaults['default_model'];
        }
        
        // Default temperature
        if (isset($settings['default_temperature']) && is_numeric($settings['default_temperature']) && 
            $settings['default_temperature'] >= 0 && $settings['default_temperature'] <= 1) {
            $validated['default_temperature'] = (float) $settings['default_temperature'];
        } else {
            $validated['default_temperature'] = $this->defaults['default_temperature'];
        }
        
        // Default max tokens
        if (isset($settings['default_max_tokens']) && is_numeric($settings['default_max_tokens']) && 
            $settings['default_max_tokens'] > 0) {
            $validated['default_max_tokens'] = (int) $settings['default_max_tokens'];
        } else {
            $validated['default_max_tokens'] = $this->defaults['default_max_tokens'];
        }
        
        // Auto fallback
        if (isset($settings['auto_fallback'])) {
            $validated['auto_fallback'] = (bool) $settings['auto_fallback'];
        } else {
            $validated['auto_fallback'] = $this->defaults['auto_fallback'];
        }
        
        // Cache enabled
        if (isset($settings['cache_enabled'])) {
            $validated['cache_enabled'] = (bool) $settings['cache_enabled'];
        } else {
            $validated['cache_enabled'] = $this->defaults['cache_enabled'];
        }
        
        // Cache lifetime
        if (isset($settings['cache_lifetime']) && is_numeric($settings['cache_lifetime']) && 
            $settings['cache_lifetime'] > 0) {
            $validated['cache_lifetime'] = (int) $settings['cache_lifetime'];
        } else {
            $validated['cache_lifetime'] = $this->defaults['cache_lifetime'];
        }
        
        // Verification timeout
        if (isset($settings['verification_timeout']) && is_numeric($settings['verification_timeout']) && 
            $settings['verification_timeout'] > 0) {
            $validated['verification_timeout'] = (int) $settings['verification_timeout'];
        } else {
            $validated['verification_timeout'] = $this->defaults['verification_timeout'];
        }
        
        // Zapisz ustawienia
        return update_option('cleanseo_ai_settings', $validated);
    }
    
    /**
     * Pobiera klucz API dla dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @return string|null Klucz API lub null
     */
    public function get_api_key($provider) {
        // Jeśli to OpenAI, pobierz z głównej opcji dla kompatybilności wstecznej
        if ($provider === 'openai') {
            $legacy_key = get_option('cleanseo_openai_api_key', '');
            if (!empty($legacy_key)) {
                return $legacy_key;
            }
        }
        
        // Pobierz z ustawień dostawców
        $provider_settings = get_option('cleanseo_ai_provider_settings', array());
        return isset($provider_settings[$provider]['api_key']) ? $provider_settings[$provider]['api_key'] : null;
    }
    
    /**
     * Zapisuje klucz API dla dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @param string $api_key Klucz API
     * @return bool Czy operacja się powiodła
     */
    public function save_api_key($provider, $api_key) {
        // Walidacja dostawcy
        if (!isset($this->providers[$provider])) {
            return false;
        }
        
        // Pobierz obecne ustawienia
        $provider_settings = get_option('cleanseo_ai_provider_settings', array());
        
        // Inicjalizuj ustawienia dostawcy, jeśli nie istnieją
        if (!isset($provider_settings[$provider])) {
            $provider_settings[$provider] = array();
        }
        
        // Zapisz klucz API
        $provider_settings[$provider]['api_key'] = $api_key;
        
        // Jeśli to OpenAI, zapisz również w starej opcji dla kompatybilności wstecznej
        if ($provider === 'openai') {
            update_option('cleanseo_openai_api_key', $api_key);
        }
        
        // Zapisz ustawienia
        return update_option('cleanseo_ai_provider_settings', $provider_settings);
    }
    
    /**
     * Pobiera szablony promptów
     * 
     * @return array Szablony promptów
     */
    public function get_prompt_templates() {
        return get_option('cleanseo_prompt_templates', array());
    }
    
    /**
     * Zapisuje szablon promptu
     * 
     * @param string $task_id Identyfikator zadania
     * @param string $template Szablon promptu
     * @param string $provider Dostawca (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function save_prompt_template($task_id, $template, $provider = null) {
        // Pobierz obecne szablony
        $templates = get_option('cleanseo_prompt_templates', array());
        
        // Klucz określający zadanie i dostawcę
        $key = ($provider !== null) ? "{$task_id}_{$provider}" : $task_id;
        
        // Zapisz szablon
        $templates[$key] = $template;
        
        // Zapisz szablony
        return update_option('cleanseo_prompt_templates', $templates);
    }
    
    /**
     * Resetuje szablon promptu do domyślnej wartości
     * 
     * @param string $task_id Identyfikator zadania
     * @param string $provider Dostawca (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function reset_prompt_template($task_id, $provider = null) {
        // Pobierz obecne szablony
        $templates = get_option('cleanseo_prompt_templates', array());
        
        // Klucz określający zadanie i dostawcę
        $key = ($provider !== null) ? "{$task_id}_{$provider}" : $task_id;
        
        // Usuń szablon, jeśli istnieje
        if (isset($templates[$key])) {
            unset($templates[$key]);
            
            // Zapisz szablony
            return update_option('cleanseo_prompt_templates', $templates);
        }
        
        return false;
    }
    
    /**
     * Znajduje optymalny model dla zadania
     * 
     * @param string $task_id Identyfikator zadania
     * @param int $min_tokens Minimalna liczba tokenów
     * @param float $max_cost Maksymalny koszt
     * @return string|null Identyfikator optymalnego modelu lub null
     */
    public function find_optimal_model($task_id, $min_tokens = null, $max_cost = null) {
        $task = $this->get_task($task_id);
        if ($task === null) {
            return null;
        }
        
        // Jeśli nie podano minimalnej liczby tokenów, użyj wymaganej liczby tokenów dla zadania
        if ($min_tokens === null && isset($task['tokens_required'])) {
            $min_tokens = $task['tokens_required'];
        }
        
        // Znajdź modele zalecane dla tego zadania
        $recommended_models = $this->get_models_for_task($task_id);
        
        // Jeśli nie ma zalecanych modeli, użyj wszystkich dostępnych
        if (empty($recommended_models)) {
            $recommended_models = $this->get_available_models();
        }
        
        // Filtruj modele według wymagań
        $suitable_models = array();
        foreach ($recommended_models as $model_id => $model) {
            // Sprawdź czy model ma wystarczająco tokenów
            if ($min_tokens !== null && isset($model['max_tokens']) && $model['max_tokens'] < $min_tokens) {
                continue;
            }
            
            // Sprawdź koszt modelu
            if ($max_cost !== null && isset($model['pricing'])) {
                // Szacuj koszt na podstawie minimalnej liczby tokenów
                $estimated_cost = $this->calculate_cost($model_id, $min_tokens / 2, $min_tokens / 2);
                if ($estimated_cost > $max_cost) {
                    continue;
                }
            }
            
            // Model spełnia wymagania
            $suitable_models[$model_id] = $model;
        }
        
        // Jeśli nie znaleziono odpowiednich modeli, zwróć null
        if (empty($suitable_models)) {
            return null;
        }
        
        // Sortuj modele według kosztu (od najniższego)
        uasort($suitable_models, function($a, $b) use ($min_tokens) {
            $a_input_cost = $a['pricing']['input'] ?? 0;
            $a_output_cost = $a['pricing']['output'] ?? 0;
            $a_cost = ($a_input_cost + $a_output_cost) / 2;
            
            $b_input_cost = $b['pricing']['input'] ?? 0;
            $b_output_cost = $b['pricing']['output'] ?? 0;
            $b_cost = ($b_input_cost + $b_output_cost) / 2;
            
            return $a_cost <=> $b_cost;
        });
        
        // Zwróć identyfikator pierwszego modelu
        reset($suitable_models);
        return key($suitable_models);
    }
    
    /**
     * Oblicza szacunkowy koszt miesięcznego użycia AI
     * 
     * @param array $usage_patterns Wzorce użycia
     * @return array Szacunkowe koszty
     */
    public function estimate_monthly_cost($usage_patterns) {
        $total_cost = 0;
        $costs_by_task = array();
        $costs_by_model = array();
        
        foreach ($usage_patterns as $task_id => $count) {
            // Pobierz zadanie
            $task = $this->get_task($task_id);
            if ($task === null || $count <= 0) {
                continue;
            }
            
            // Znajdź optymalny model dla zadania
            $model_id = $this->find_optimal_model($task_id);
            if ($model_id === null) {
                continue;
            }
            
            // Pobierz model
            $model = $this->get_model($model_id);
            
            // Oszacuj liczbę tokenów dla zadania
            $tokens = isset($task['tokens_required']) ? $task['tokens_required'] : 500;
            
            // Załóż, że prompt używa 1/3 tokenów, a odpowiedź 2/3
            $prompt_tokens = $tokens / 3;
            $completion_tokens = ($tokens * 2) / 3;
            
            // Oblicz koszt pojedynczego zapytania
            $query_cost = $this->calculate_cost($model_id, $prompt_tokens, $completion_tokens);
            
            // Oblicz miesięczny koszt dla tego zadania
            $task_cost = $query_cost * $count;
            
            // Dodaj do sum
            $total_cost += $task_cost;
            
            // Dodaj do sum według zadania
            if (!isset($costs_by_task[$task_id])) {
                $costs_by_task[$task_id] = 0;
            }
            $costs_by_task[$task_id] += $task_cost;
            
            // Dodaj do sum według modelu
            if (!isset($costs_by_model[$model_id])) {
                $costs_by_model[$model_id] = 0;
            }
            $costs_by_model[$model_id] += $task_cost;
        }
        
        return array(
            'total_cost' => $total_cost,
            'costs_by_task' => $costs_by_task,
            'costs_by_model' => $costs_by_model
        );
    }
    
    /**
     * Pobiera statystyki użycia modeli AI
     * 
     * @param string $period Okres (day, week, month, all)
     * @return array Statystyki użycia
     */
    public function get_usage_stats($period = 'month') {
        global $wpdb;
        
        // Tabela logów API
        $logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
        if (!$table_exists) {
            return array(
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'by_model' => array(),
                'by_cache' => array(
                    'hits' => 0,
                    'misses' => 0,
                    'ratio' => 0
                )
            );
        }
        
        // Przygotuj zapytanie o czas
        $time_condition = '';
        switch ($period) {
            case 'day':
                $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'all':
                // Brak warunku
                break;
            default:
                $time_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
        
        // Pobierz ogólne statystyki
        $query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(tokens) as total_tokens,
                    SUM(cost) as total_cost,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits
                FROM $logs_table
                WHERE 1=1 $time_condition";
        
        $stats = $wpdb->get_row($query, ARRAY_A);
        
        // Pobierz statystyki według modelu
        $model_query = "SELECT 
                    model,
                    COUNT(*) as requests,
                    SUM(tokens) as tokens,
                    SUM(cost) as cost,
                    AVG(duration) as avg_duration,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits
                FROM $logs_table
                WHERE 1=1 $time_condition
                GROUP BY model
                ORDER BY requests DESC";
        
        $by_model = $wpdb->get_results($model_query, ARRAY_A);
        
        // Oblicz cache hit ratio
        $cache_hit_ratio = 0;
        if ($stats['total_requests'] > 0) {
            $cache_hit_ratio = ($stats['cache_hits'] / $stats['total_requests']) * 100;
        }
        
        // Przygotuj zwracane dane
        return array(
            'total_requests' => (int) $stats['total_requests'],
            'total_tokens' => (int) $stats['total_tokens'],
            'total_cost' => (float) $stats['total_cost'],
            'by_model' => $by_model ?: array(),
            'by_cache' => array(
                'hits' => (int) $stats['cache_hits'],
                'misses' => (int) $stats['total_requests'] - (int) $stats['cache_hits'],
                'ratio' => round($cache_hit_ratio, 2)
            ),
            'period' => $period
        );
    }
}