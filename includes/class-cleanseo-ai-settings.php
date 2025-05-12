<?php

/**
 * Klasa odpowiedzialna za zarządzanie ustawieniami AI (obsługa wielu kluczy API, szablonów promptów i innych konfiguracji)
 */
class CleanSEO_AI_Settings {
    /**
     * Nazwa opcji w bazie danych
     * @var string
     */
    private $settings_option = 'cleanseo_ai_settings';
    
    /**
     * Nazwa opcji providerów w bazie danych
     * @var string
     */
    private $providers_option = 'cleanseo_ai_provider_settings';
    
    /**
     * Nazwa opcji szablonów promptów w bazie danych
     * @var string
     */
    private $templates_option = 'cleanseo_prompt_templates';
    
    /**
     * Domyślne ustawienia
     * @var array
     */
    private $default_settings;
    
    /**
     * Obiekt loggera
     * @var CleanSEO_Logger
     */
    private $logger;
    
    /**
     * Dostępni dostawcy AI
     * @var array
     */
    private $available_providers = array(
        'openai' => 'OpenAI',
        'google' => 'Google Gemini',
        'anthropic' => 'Anthropic Claude',
        'mistral' => 'Mistral AI',
        'cohere' => 'Cohere'
    );
    
    /**
     * Dostępne modele dla każdego dostawcy
     * @var array
     */
    private $available_models = array(
        'openai' => array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo'
        ),
        'google' => array(
            'gemini-pro' => 'Gemini Pro'
        ),
        'anthropic' => array(
            'claude-3-opus' => 'Claude 3 Opus',
            'claude-3-sonnet' => 'Claude 3 Sonnet',
            'claude-3-haiku' => 'Claude 3 Haiku'
        ),
        'mistral' => array(
            'mistral-small' => 'Mistral Small',
            'mistral-medium' => 'Mistral Medium'
        ),
        'cohere' => array(
            'command' => 'Command',
            'command-light' => 'Command Light'
        )
    );
    
    /**
     * Dostępne typy promptów
     * @var array
     */
    private $prompt_types = array(
        'meta_title' => 'Meta tytuł',
        'meta_description' => 'Meta opis',
        'content' => 'Treść artykułu',
        'excerpt' => 'Wyciąg',
        'schema' => 'Schema.org',
        'faq' => 'FAQ',
        'tags' => 'Tagi',
        'heading_ideas' => 'Pomysły na nagłówki',
        'content_audit' => 'Audyt treści',
        'competition' => 'Analiza konkurencji',
        'keyword_research' => 'Badanie słów kluczowych'
    );
    
    /**
     * Konstruktor
     * 
     * @param CleanSEO_Logger $logger Opcjonalny obiekt loggera
     */
    public function __construct($logger = null) {
        // Inicjalizacja domyślnych ustawień
        $this->default_settings = array(
            // Ustawienia ogólne
            'enabled' => true,
            'default_provider' => 'openai',
            'default_model' => 'gpt-3.5-turbo',
            'fallback_provider' => '',
            'fallback_model' => '',
            
            // Ustawienia generacji
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'default_language' => 'pl',
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            
            // Ustawienia cache
            'cache_enabled' => true,
            'cache_time' => 86400, // 24 godziny
            'cache_max_size' => 50000000, // 50MB
            
            // Ustawienia logowania
            'logging_enabled' => true,
            'log_level' => 'info',
            'error_notifications' => false,
            'notification_email' => get_option('admin_email'),
            
            // Ustawienia limitów
            'budget_limit' => 0, // 0 = brak limitu
            'daily_request_limit' => 50,
            'user_roles_limits' => array(
                'administrator' => 100,
                'editor' => 50,
                'author' => 20,
                'contributor' => 10
            ),
            
            // Ustawienia automatycznej generacji
            'auto_generate' => false,
            'auto_generate_post_types' => array('post', 'page'),
            'auto_generate_fields' => array('meta_title', 'meta_description'),
            'auto_generate_on_publish' => true,
            'auto_generate_on_update' => false,
            
            // Ustawienia integracji z innymi pluginami SEO
            'yoast_integration' => true,
            'rankmath_integration' => true,
            'aioseo_integration' => true,
            
            // Ustawienia zaawansowane
            'request_timeout' => 60,
            'max_retries' => 3,
            'backoff_strategy' => 'exponential',
            'parallel_requests' => false,
            'max_parallel_requests' => 3,
            'load_balancing' => false,
            'verify_ssl' => true
        );
        
        // Inicjalizacja loggera
        $this->logger = $logger ?: new CleanSEO_Logger('ai_settings');
        
        // Dodaj hooki
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Rejestruje ustawienia
     */
    public function register_settings() {
        register_setting(
            'cleanseo_ai_settings',
            $this->settings_option,
            array('sanitize_callback' => array($this, 'sanitize_settings'))
        );
        
        register_setting(
            'cleanseo_ai_providers',
            $this->providers_option,
            array('sanitize_callback' => array($this, 'sanitize_providers'))
        );
        
        register_setting(
            'cleanseo_prompt_templates',
            $this->templates_option,
            array('sanitize_callback' => array($this, 'sanitize_templates'))
        );
    }

    /**
     * Pobiera wszystkie ustawienia
     * 
     * @return array Ustawienia
     */
    public function get_settings() {
        $settings = get_option($this->settings_option, array());
        return wp_parse_args($settings, $this->default_settings);
    }

    /**
     * Pobiera konkretne ustawienie
     * 
     * @param string $key Klucz ustawienia
     * @param mixed $default Domyślna wartość
     * @return mixed Wartość ustawienia
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        
        // Jeśli podano hierarhiczny klucz (np. 'group.setting')
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $value = $settings;
            
            foreach ($parts as $part) {
                if (!isset($value[$part])) {
                    return $default;
                }
                $value = $value[$part];
            }
            
            return $value;
        }
        
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Pobiera ustawienia dla określonego modelu
     * 
     * @param string $model_id Identyfikator modelu
     * @return array Ustawienia modelu
     */
    public function get_model_settings($model_id) {
        // Pobierz ustawienia specyficzne dla modelu
        $model_settings = $this->get_setting('model_settings', array());
        
        // Jeśli istnieją ustawienia dla tego modelu, użyj ich
        if (isset($model_settings[$model_id])) {
            return $model_settings[$model_id];
        }
        
        // W przeciwnym razie zwróć domyślne ustawienia generacji
        return array(
            'max_tokens' => $this->get_setting('max_tokens'),
            'temperature' => $this->get_setting('temperature'),
            'top_p' => $this->get_setting('top_p'),
            'frequency_penalty' => $this->get_setting('frequency_penalty'),
            'presence_penalty' => $this->get_setting('presence_penalty')
        );
    }
    
    /**
     * Pobiera zaawansowane ustawienia
     * 
     * @return array Zaawansowane ustawienia
     */
    public function get_advanced_settings() {
        return array(
            'request_timeout' => $this->get_setting('request_timeout'),
            'max_retries' => $this->get_setting('max_retries'),
            'backoff_strategy' => $this->get_setting('backoff_strategy'),
            'parallel_requests' => $this->get_setting('parallel_requests'),
            'max_parallel_requests' => $this->get_setting('max_parallel_requests'),
            'load_balancing' => $this->get_setting('load_balancing'),
            'verify_ssl' => $this->get_setting('verify_ssl')
        );
    }
    
    /**
     * Pobiera konkretne zaawansowane ustawienie
     * 
     * @param string $key Klucz ustawienia
     * @param mixed $default Domyślna wartość
     * @return mixed Wartość ustawienia
     */
    public function get_advanced_setting($key, $default = null) {
        $advanced_settings = $this->get_advanced_settings();
        return isset($advanced_settings[$key]) ? $advanced_settings[$key] : $default;
    }

    /**
     * Pobiera wszystkie szablony promptów
     * 
     * @return array Tablica szablonów promptów
     */
    public function get_prompt_templates() {
        // Pobierz zapisane szablony z bazy danych
        $templates = get_option($this->templates_option, array());
        
        // Jeśli nie ma zapisanych szablonów, zwróć puste szablony dla wszystkich typów promptów
        if (empty($templates)) {
            $templates = array();
            
            // Dla każdego typu promptu, utwórz pusty szablon
            foreach ($this->prompt_types as $type_id => $type_name) {
                $templates[$type_id] = $this->get_default_prompt_template($type_id);
            }
        }
        
        return $templates;
    }

    /**
     * Pobiera szablon promptu dla określonego pola
     * 
     * @param string $field Typ pola
     * @param string $provider Dostawca (opcjonalnie)
     * @return string Szablon promptu
     */
    public function get_prompt_template($field, $provider = null) {
        // Pobierz wszystkie szablony
        $templates = get_option($this->templates_option, array());
        
        // Klucz określający zadanie i dostawcę
        $specific_key = ($provider !== null) ? "{$field}_{$provider}" : $field;
        
        // Spróbuj znaleźć specyficzny szablon dla dostawcy
        if ($provider !== null && isset($templates[$specific_key])) {
            return $templates[$specific_key];
        }
        
        // Spróbuj znaleźć ogólny szablon
        if (isset($templates[$field])) {
            return $templates[$field];
        }
        
        // Zwróć domyślny szablon
        return $this->get_default_prompt_template($field);
    }
    
    /**
     * Pobiera domyślny szablon promptu
     * 
     * @param string $field Typ pola
     * @return string Domyślny szablon promptu
     */
    private function get_default_prompt_template($field) {
        $default_templates = array(
            'meta_title' => 'Napisz w języku polskim skuteczny meta tytuł SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tytuł powinien mieć maksymalnie 60 znaków, być atrakcyjny dla czytelników i zawierać główne słowo kluczowe blisko początku.',
            
            'meta_description' => 'Napisz w języku polskim perswazyjny meta opis SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Opis powinien mieć maksymalnie 155 znaków, być atrakcyjny dla czytelników, zawierać wezwanie do działania i naturalne użycie słów kluczowych.',
            
            'content' => 'Napisz w języku polskim wysokiej jakości treść na temat "{title}". Słowa kluczowe: {keywords}. Długość treści: {length} (short = 300 słów, medium = 800 słów, long = 1500 słów). Treść powinna być podzielona nagłówkami, zawierać wstęp, rozwinięcie i podsumowanie. Użyj naturalnego stylu, angażującego tonu i pisz w drugiej osobie. Słowa kluczowe powinny być użyte naturalnie i rozproszone w całej treści.',
            
            'excerpt' => 'Napisz w języku polskim krótki, przyciągający uwagę wyciąg dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Wyciąg powinien mieć maksymalnie 160 znaków i zachęcać do przeczytania całego artykułu.',
            
            'schema' => 'Wygeneruj w języku polskim schemat JSON-LD dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Użyj struktury Article. Zwróć tylko czysty kod JSON bez dodatkowych wyjaśnień.',
            
            'faq' => 'Stwórz w języku polskim sekcję FAQ (5 pytań i odpowiedzi) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Każde pytanie powinno być naturalne i odzwierciedlać rzeczywiste zapytania użytkowników. Każda odpowiedź powinna mieć 2-3 zdania. Zwróć FAQ w formacie HTML z użyciem struktury schema.org.',
            
            'tags' => 'Zaproponuj 5-8 tagów dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tagi powinny być krótkimi frazami związanymi z tematem artykułu i użytecznymi dla SEO. Podaj tylko listę tagów oddzielonych przecinkami, bez dodatkowych wyjaśnień.',
            
            'heading_ideas' => 'Zaproponuj 5-7 nagłówków (H2) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Nagłówki powinny być atrakcyjne, zawierać słowa kluczowe tam gdzie to naturalne i pokrywać różne aspekty tematu. Zwróć listę nagłówków, po jednym w linii.',
            
            'content_audit' => 'Przeprowadź audyt SEO poniższej treści pod kątem słów kluczowych: {keywords}. Treść: {content}. Oceń gęstość słów kluczowych, strukturę treści, czytelność, długość i ogólną jakość z perspektywy SEO. Podaj ocenę w skali 1-10 oraz szczegółowe rekomendacje dotyczące poprawy.',
            
            'competition' => 'Przeanalizuj poniższą treść konkurencji i podaj rekomendacje SEO dla tematu związanego z następującymi słowami kluczowymi: {keywords}. Treść konkurencji: {content}. Podaj: 1) Główne tematy poruszane przez konkurencję, 2) Brakujące aspekty, które moglibyśmy pokryć, 3) Słowa kluczowe, które używają i które również powinniśmy uwzględnić, 4) Propozycję struktury treści, która byłaby lepsza.',
            
            'keyword_research' => 'Przeprowadź analizę słów kluczowych dla tematu "{topic}" w języku polskim. Podaj: 1) 10 głównych słów kluczowych z długim ogonem (long tail), 2) 5 pytań, które ludzie często zadają na ten temat, 3) 3 tematy pokrewne, które warto uwzględnić, 4) Sugestie dotyczące intencji wyszukiwania dla każdego słowa kluczowego (informacyjna, transakcyjna, nawigacyjna).'
        );
        
        return isset($default_templates[$field]) ? $default_templates[$field] : '';
    }

    /**
     * Pobiera klucz API dla określonego dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @return string|null Klucz API lub null
     */
    public function get_api_key($provider) {
        // Pobierz ustawienia dostawców
        $provider_settings = get_option($this->providers_option, array());
        
        // Jeśli provider to 'openai', sprawdź również stare ustawienie dla kompatybilności wstecznej
        if ($provider === 'openai') {
            $legacy_key = get_option('cleanseo_openai_api_key', '');
            if (!empty($legacy_key)) {
                return $legacy_key;
            }
        }
        
        return isset($provider_settings[$provider]['api_key']) ? $provider_settings[$provider]['api_key'] : null;
    }
    
    /**
     * Pobiera ustawienia wszystkich dostawców
     * 
     * @return array Ustawienia dostawców
     */
    public function get_provider_settings() {
        return get_option($this->providers_option, array());
    }
    
    /**
     * Pobiera ustawienia określonego dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @return array Ustawienia dostawcy
     */
    public function get_provider_setting($provider) {
        $provider_settings = $this->get_provider_settings();
        return isset($provider_settings[$provider]) ? $provider_settings[$provider] : array();
    }
    
    /**
     * Zapisuje klucz API dla określonego dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @param string $api_key Klucz API
     * @return bool Czy operacja się powiodła
     */
    public function save_api_key($provider, $api_key) {
        // Walidacja dostawcy
        if (!array_key_exists($provider, $this->available_providers)) {
            return false;
        }
        
        // Pobierz obecne ustawienia dostawców
        $provider_settings = get_option($this->providers_option, array());
        
        // Jeśli nie ma ustawień dla tego dostawcy, utwórz je
        if (!isset($provider_settings[$provider])) {
            $provider_settings[$provider] = array();
        }
        
        // Zapisz klucz API
        $provider_settings[$provider]['api_key'] = sanitize_text_field($api_key);
        
        // Jeśli to OpenAI, zapisz również w starym ustawieniu dla kompatybilności wstecznej
        if ($provider === 'openai') {
            update_option('cleanseo_openai_api_key', sanitize_text_field($api_key));
        }
        
        // Zapisz ustawienia
        $result = update_option($this->providers_option, $provider_settings);
        
        // Loguj operację
        if ($result) {
            $this->logger->info('Zapisano klucz API', array('provider' => $provider));
        } else {
            $this->logger->error('Nie udało się zapisać klucza API', array('provider' => $provider));
        }
        
        return $result;
    }
    
    /**
     * Zapisuje szablon promptu
     * 
     * @param string $field Typ pola
     * @param string $template Szablon promptu
     * @param string $provider Dostawca (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function save_prompt_template($field, $template, $provider = null) {
        // Walidacja pola
        if (!array_key_exists($field, $this->prompt_types)) {
            return false;
        }
        
        // Pobierz obecne szablony
        $templates = get_option($this->templates_option, array());
        
        // Klucz określający zadanie i dostawcę
        $key = ($provider !== null) ? "{$field}_{$provider}" : $field;
        
        // Zapisz szablon
        $templates[$key] = wp_kses_post($template);
        
        // Zapisz szablony
        $result = update_option($this->templates_option, $templates);
        
        // Loguj operację
        if ($result) {
            $this->logger->info('Zapisano szablon promptu', array(
                'field' => $field,
                'provider' => $provider
            ));
        } else {
            $this->logger->error('Nie udało się zapisać szablonu promptu', array(
                'field' => $field,
                'provider' => $provider
            ));
        }
        
        return $result;
    }
    
    /**
     * Resetuje szablon promptu do domyślnej wartości
     * 
     * @param string $field Typ pola
     * @param string $provider Dostawca (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function reset_prompt_template($field, $provider = null) {
        // Walidacja pola
        if (!array_key_exists($field, $this->prompt_types)) {
            return false;
        }
        
        // Pobierz obecne szablony
        $templates = get_option($this->templates_option, array());
        
        // Klucz określający zadanie i dostawcę
        $key = ($provider !== null) ? "{$field}_{$provider}" : $field;
        
        // Usuń szablon
        if (isset($templates[$key])) {
            unset($templates[$key]);
            
            // Zapisz szablony
            $result = update_option($this->templates_option, $templates);
            
            // Loguj operację
            if ($result) {
                $this->logger->info('Zresetowano szablon promptu', array(
                    'field' => $field,
                    'provider' => $provider
                ));
            } else {
                $this->logger->error('Nie udało się zresetować szablonu promptu', array(
                    'field' => $field,
                    'provider' => $provider
                ));
            }
            
            return $result;
        }
        
        return false;
    }

    /**
     * Aktualizuje ustawienia
     * 
     * @param array $data Nowe ustawienia
     * @return bool Czy operacja się powiodła
     */
    public function update_settings($data) {
        // Sanityzacja danych
        $sanitized = $this->sanitize_settings($data);
        
        // Zapisz ustawienia
        $result = update_option($this->settings_option, $sanitized);
        
        // Loguj operację
        if ($result) {
            $this->logger->info('Zaktualizowano ustawienia AI');
        } else {
            $this->logger->error('Nie udało się zaktualizować ustawień AI');
        }
        
        return $result;
    }
    
    /**
     * Aktualizuje ustawienia dostawcy
     * 
     * @param string $provider Identyfikator dostawcy
     * @param array $settings Nowe ustawienia
     * @return bool Czy operacja się powiodła
     */
    public function update_provider_settings($provider, $settings) {
        // Walidacja dostawcy
        if (!array_key_exists($provider, $this->available_providers)) {
            return false;
        }
        
        // Pobierz obecne ustawienia dostawców
        $provider_settings = get_option($this->providers_option, array());
        
        // Jeśli nie ma ustawień dla tego dostawcy, utwórz je
        if (!isset($provider_settings[$provider])) {
            $provider_settings[$provider] = array();
        }
        
        // Sanityzacja ustawień
        $sanitized = $this->sanitize_deep($settings);
        
        // Zaktualizuj ustawienia
        $provider_settings[$provider] = array_merge($provider_settings[$provider], $sanitized);
        
        // Zapisz ustawienia
        $result = update_option($this->providers_option, $provider_settings);
        
        // Loguj operację
        if ($result) {
            $this->logger->info('Zaktualizowano ustawienia dostawcy', array('provider' => $provider));
        } else {
            $this->logger->error('Nie udało się zaktualizować ustawień dostawcy', array('provider' => $provider));
        }
        
        return $result;
    }
    
    /**
     * Sprawdza, czy dostawca jest włączony
     * 
     * @param string $provider Identyfikator dostawcy
     * @return bool Czy dostawca jest włączony
     */
    public function is_provider_enabled($provider) {
        $provider_settings = $this->get_provider_setting($provider);
        return isset($provider_settings['enabled']) ? (bool)$provider_settings['enabled'] : false;
    }
    
    /**
     * Włącza lub wyłącza dostawcę
     * 
     * @param string $provider Identyfikator dostawcy
     * @param bool $enabled Czy dostawca ma być włączony
     * @return bool Czy operacja się powiodła
     */
    public function set_provider_enabled($provider, $enabled) {
        return $this->update_provider_settings($provider, array('enabled' => (bool)$enabled));
    }
    
    /**
     * Sanityzuje ustawienia
     * 
     * @param array $input Ustawienia do sanityzacji
     * @return array Sanityzowane ustawienia
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return $this->default_settings;
        }
        
        $output = array();
        
        // Przejdź przez wszystkie domyślne ustawienia
        foreach ($this->default_settings as $key => $default) {
            // Jeśli ustawienie istnieje w danych wejściowych
            if (isset($input[$key])) {
                // Sanityzuj w zależności od typu
                if (is_array($default)) {
                    $output[$key] = $this->sanitize_deep($input[$key]);
                } elseif (is_bool($default)) {
                    $output[$key] = (bool) $input[$key];
                } elseif (is_int($default)) {
                    $output[$key] = absint($input[$key]);
                } elseif (is_float($default)) {
                    $output[$key] = (float) $input[$key];
                } else {
                    $output[$key] = sanitize_text_field($input[$key]);
                }
            } else {
                // Użyj wartości domyślnej
                $output[$key] = $default;
            }
        }
        
        return $output;
    }
    
    /**
     * Sanityzuje ustawienia dostawców
     * 
     * @param array $input Ustawienia do sanityzacji
     * @return array Sanityzowane ustawienia
     */
    public function sanitize_providers($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $output = array();
        
        // Przejdź przez wszystkich dostawców
        foreach ($this->available_providers as $provider => $name) {
            if (isset($input[$provider]) && is_array($input[$provider])) {
                $output[$provider] = $this->sanitize_deep($input[$provider]);
            }
        }
        
        return $output;
    }
    
    /**
     * Sanityzuje szablony promptów
     * 
     * @param array $input Szablony do sanityzacji
     * @return array Sanityzowane szablony
     */
    public function sanitize_templates($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $output = array();
        
        // Przejdź przez wszystkie szablony
        foreach ($input as $key => $template) {
            $output[$key] = wp_kses_post($template);
        }
        
        return $output;
    }

    /**
     * Sanityzuje dane rekurencyjnie
     * 
     * @param mixed $data Dane do sanityzacji
     * @return mixed Sanityzowane dane
     */
    private function sanitize_deep($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize_deep($value);
            }
            return $data;
        } elseif (is_object($data)) {
            // Konwertuj obiekt na tablicę
            $array = (array) $data;
            foreach ($array as $key => $value) {
                $array[$key] = $this->sanitize_deep($value);
            }
            return $array;
        } elseif (is_string($data)) {
            return sanitize_text_field($data);
       } elseif (is_bool($data)) {
           return (bool) $data;
       } elseif (is_int($data)) {
           return absint($data);
       } elseif (is_float($data)) {
           return (float) $data;
       } else {
           return $data;
       }
   }
   
   /**
    * Pobiera dostępnych dostawców
    * 
    * @return array Dostępni dostawcy
    */
   public function get_available_providers() {
       return $this->available_providers;
   }
   
  /**
    * Pobiera dostępne modele dla określonego dostawcy
    * 
    * @param string $provider Identyfikator dostawcy
    * @return array Dostępne modele
    */
   public function get_available_models($provider = null) {
       if ($provider !== null) {
           return isset($this->available_models[$provider]) ? $this->available_models[$provider] : array();
       }
       
       // Jeśli nie podano dostawcy, zwróć wszystkie modele
       $all_models = array();
       foreach ($this->available_models as $provider_models) {
           $all_models = array_merge($all_models, $provider_models);
       }
       
       return $all_models;
   }
   
   /**
    * Pobiera typy promptów
    * 
    * @return array Typy promptów
    */
   public function get_prompt_types() {
       return $this->prompt_types;
   }
   
   /**
    * Eksportuje ustawienia
    * 
    * @return array Ustawienia do eksportu
    */
   public function export() {
       $export = array(
           'settings' => $this->get_settings(),
           'providers' => $this->get_provider_settings(),
           'templates' => get_option($this->templates_option, array()),
           'export_date' => current_time('mysql'),
           'version' => defined('CLEANSEO_VERSION') ? CLEANSEO_VERSION : '1.0',
           'site_url' => get_site_url()
       );
       
       return $export;
   }
   
   /**
    * Importuje ustawienia
    * 
    * @param array $import Ustawienia do importu
    * @return bool Czy operacja się powiodła
    */
   public function import($import) {
       // Walidacja danych importu
       if (!is_array($import) || !isset($import['settings']) || !isset($import['providers']) || !isset($import['templates'])) {
           $this->logger->error('Nieprawidłowy format danych importu');
           return false;
       }
       
       $success = true;
       
       // Importuj ustawienia
       if (isset($import['settings']) && is_array($import['settings'])) {
           $sanitized_settings = $this->sanitize_settings($import['settings']);
           $result = update_option($this->settings_option, $sanitized_settings);
           if (!$result) {
               $success = false;
               $this->logger->error('Nie udało się zaimportować ustawień');
           }
       }
       
       // Importuj ustawienia dostawców
       if (isset($import['providers']) && is_array($import['providers'])) {
           $sanitized_providers = $this->sanitize_providers($import['providers']);
           $result = update_option($this->providers_option, $sanitized_providers);
           if (!$result) {
               $success = false;
               $this->logger->error('Nie udało się zaimportować ustawień dostawców');
           }
       }
       
       // Importuj szablony promptów
       if (isset($import['templates']) && is_array($import['templates'])) {
           $sanitized_templates = $this->sanitize_templates($import['templates']);
           $result = update_option($this->templates_option, $sanitized_templates);
           if (!$result) {
               $success = false;
               $this->logger->error('Nie udało się zaimportować szablonów promptów');
           }
       }
       
       if ($success) {
           $this->logger->info('Pomyślnie zaimportowano ustawienia', array(
               'source' => isset($import['site_url']) ? $import['site_url'] : 'nieznane',
               'date' => isset($import['export_date']) ? $import['export_date'] : 'nieznana',
               'version' => isset($import['version']) ? $import['version'] : 'nieznana'
           ));
       }
       
       return $success;
   }
   
   /**
    * Resetuje ustawienia do wartości domyślnych
    * 
    * @param bool $reset_api_keys Czy resetować klucze API
    * @return bool Czy operacja się powiodła
    */
   public function reset($reset_api_keys = false) {
       $success = true;
       
       // Resetuj ustawienia
       $result = update_option($this->settings_option, $this->default_settings);
       if (!$result) {
           $success = false;
           $this->logger->error('Nie udało się zresetować ustawień');
       }
       
       // Resetuj szablony promptów
       $result = delete_option($this->templates_option);
       if (!$result) {
           $success = false;
           $this->logger->error('Nie udało się zresetować szablonów promptów');
       }
       
       // Resetuj klucze API, jeśli wymagane
       if ($reset_api_keys) {
           $result = delete_option($this->providers_option);
           $legacy_result = delete_option('cleanseo_openai_api_key');
           if (!$result || !$legacy_result) {
               $success = false;
               $this->logger->error('Nie udało się zresetować kluczy API');
           }
       } else {
           // Zachowaj klucze API, ale zresetuj inne ustawienia dostawców
           $provider_settings = $this->get_provider_settings();
           $new_provider_settings = array();
           
           foreach ($provider_settings as $provider => $settings) {
               $new_provider_settings[$provider] = array();
               
               // Zachowaj tylko klucz API
               if (isset($settings['api_key'])) {
                   $new_provider_settings[$provider]['api_key'] = $settings['api_key'];
               }
           }
           
           $result = update_option($this->providers_option, $new_provider_settings);
           if (!$result) {
               $success = false;
               $this->logger->error('Nie udało się zaktualizować ustawień dostawców');
           }
       }
       
       if ($success) {
           $this->logger->info('Pomyślnie zresetowano ustawienia', array(
               'reset_api_keys' => $reset_api_keys
           ));
       }
       
       return $success;
   }
   
   /**
    * Pobiera dzienne limity dla różnych ról użytkowników
    * 
    * @return array Limity dzienne
    */
   public function get_daily_limits() {
       $default_limit = $this->get_setting('daily_request_limit', 50);
       $role_limits = $this->get_setting('user_roles_limits', array());
       
       // Upewnij się, że wszystkie standardowe role mają limity
       $default_role_limits = array(
           'administrator' => 100,
           'editor' => 50,
           'author' => 20,
           'contributor' => 10,
           'subscriber' => 5
       );
       
       foreach ($default_role_limits as $role => $limit) {
           if (!isset($role_limits[$role])) {
               $role_limits[$role] = $limit;
           }
       }
       
       return array(
           'default' => $default_limit,
           'roles' => $role_limits
       );
   }
   
   /**
    * Pobiera dzienny limit dla konkretnego użytkownika
    * 
    * @param int $user_id ID użytkownika
    * @return int Limit dzienny
    */
   public function get_user_daily_limit($user_id) {
       // Domyślny limit
       $default_limit = $this->get_setting('daily_request_limit', 50);
       
       // Jeśli użytkownik nie jest zalogowany, zwróć domyślny limit
       if ($user_id <= 0) {
           return $default_limit;
       }
       
       // Sprawdź, czy użytkownik ma własny limit
       $user_limit = get_user_meta($user_id, 'cleanseo_ai_daily_limit', true);
       if ($user_limit !== '') {
           return (int) $user_limit;
       }
       
       // Pobierz limity dla ról
       $limits = $this->get_daily_limits();
       $role_limits = $limits['roles'];
       
       // Pobierz role użytkownika
       $user = get_userdata($user_id);
       if (!$user || empty($user->roles)) {
           return $default_limit;
       }
       
       // Znajdź najwyższy limit dla ról użytkownika
       $highest_limit = 0;
       foreach ($user->roles as $role) {
           if (isset($role_limits[$role]) && $role_limits[$role] > $highest_limit) {
               $highest_limit = $role_limits[$role];
           }
       }
       
       // Jeśli znaleziono limit dla roli, użyj go
       if ($highest_limit > 0) {
           return $highest_limit;
       }
       
       return $default_limit;
   }
   
   /**
    * Ustawia dzienny limit dla konkretnego użytkownika
    * 
    * @param int $user_id ID użytkownika
    * @param int $limit Limit dzienny
    * @return bool Czy operacja się powiodła
    */
   public function set_user_daily_limit($user_id, $limit) {
       $limit = absint($limit);
       
       // Jeśli limit jest równy domyślnemu lub 0, usuń meta dane
       if ($limit === 0) {
           return delete_user_meta($user_id, 'cleanseo_ai_daily_limit');
       }
       
       // Ustaw nowy limit
       return update_user_meta($user_id, 'cleanseo_ai_daily_limit', $limit);
   }
   
   /**
    * Sprawdza czy AI jest włączone
    * 
    * @return bool Czy AI jest włączone
    */
   public function is_ai_enabled() {
       return $this->get_setting('enabled', true);
   }
   
   /**
    * Włącza lub wyłącza AI
    * 
    * @param bool $enabled Czy AI ma być włączone
    * @return bool Czy operacja się powiodła
    */
   public function set_ai_enabled($enabled) {
       $settings = $this->get_settings();
       $settings['enabled'] = (bool) $enabled;
       
       return $this->update_settings($settings);
   }
   
   /**
    * Sprawdza czy automatyczne generowanie jest włączone
    * 
    * @return bool Czy automatyczne generowanie jest włączone
    */
   public function is_auto_generate_enabled() {
       return $this->get_setting('auto_generate', false);
   }
   
   /**
    * Sprawdza czy automatyczne generowanie jest włączone dla określonego typu postu
    * 
    * @param string $post_type Typ postu
    * @return bool Czy automatyczne generowanie jest włączone
    */
   public function is_auto_generate_enabled_for_post_type($post_type) {
       if (!$this->is_auto_generate_enabled()) {
           return false;
       }
       
       $post_types = $this->get_setting('auto_generate_post_types', array('post', 'page'));
       return in_array($post_type, $post_types);
   }
   
   /**
    * Pobiera pola, które mają być automatycznie generowane
    * 
    * @return array Pola do automatycznego generowania
    */
   public function get_auto_generate_fields() {
       return $this->get_setting('auto_generate_fields', array('meta_title', 'meta_description'));
   }
   
   /**
    * Sprawdza czy cachowanie jest włączone
    * 
    * @return bool Czy cachowanie jest włączone
    */
   public function is_cache_enabled() {
       return $this->get_setting('cache_enabled', true);
   }
   
   /**
    * Pobiera czas życia cache
    * 
    * @return int Czas życia cache w sekundach
    */
   public function get_cache_time() {
       return $this->get_setting('cache_time', 86400);
   }
   
   /**
    * Pobiera maksymalny rozmiar cache
    * 
    * @return int Maksymalny rozmiar cache w bajtach
    */
   public function get_cache_max_size() {
       return $this->get_setting('cache_max_size', 50000000);
   }
   
   /**
    * Sprawdza czy logowanie jest włączone
    * 
    * @return bool Czy logowanie jest włączone
    */
   public function is_logging_enabled() {
       return $this->get_setting('logging_enabled', true);
   }
   
   /**
    * Pobiera poziom logowania
    * 
    * @return string Poziom logowania
    */
   public function get_log_level() {
       return $this->get_setting('log_level', 'info');
   }
   
   /**
    * Sprawdza czy powiadomienia o błędach są włączone
    * 
    * @return bool Czy powiadomienia o błędach są włączone
    */
   public function are_error_notifications_enabled() {
       return $this->get_setting('error_notifications', false);
   }
   
   /**
    * Pobiera adres e-mail do powiadomień
    * 
    * @return string Adres e-mail
    */
   public function get_notification_email() {
       return $this->get_setting('notification_email', get_option('admin_email'));
   }
   
   /**
    * Pobiera limit budżetu
    * 
    * @return float Limit budżetu
    */
   public function get_budget_limit() {
       return (float) $this->get_setting('budget_limit', 0);
   }
   
   /**
    * Sprawdza czy integracja z Yoast SEO jest włączona
    * 
    * @return bool Czy integracja z Yoast SEO jest włączona
    */
   public function is_yoast_integration_enabled() {
       return $this->get_setting('yoast_integration', true);
   }
   
   /**
    * Sprawdza czy integracja z Rank Math jest włączona
    * 
    * @return bool Czy integracja z Rank Math jest włączona
    */
   public function is_rankmath_integration_enabled() {
       return $this->get_setting('rankmath_integration', true);
   }
   
   /**
    * Sprawdza czy integracja z All in One SEO jest włączona
    * 
    * @return bool Czy integracja z All in One SEO jest włączona
    */
   public function is_aioseo_integration_enabled() {
       return $this->get_setting('aioseo_integration', true);
   }
   
   /**
    * Pobiera domyślnego dostawcę
    * 
    * @return string Identyfikator dostawcy
    */
   public function get_default_provider() {
       return $this->get_setting('default_provider', 'openai');
   }
   
   /**
    * Pobiera domyślny model
    * 
    * @return string Identyfikator modelu
    */
   public function get_default_model() {
       return $this->get_setting('default_model', 'gpt-3.5-turbo');
   }
   
   /**
    * Pobiera rezerwowego dostawcę
    * 
    * @return string Identyfikator dostawcy
    */
   public function get_fallback_provider() {
       return $this->get_setting('fallback_provider', '');
   }
   
   /**
    * Pobiera rezerwowy model
    * 
    * @return string Identyfikator modelu
    */
   public function get_fallback_model() {
       return $this->get_setting('fallback_model', '');
   }
   
  /**
 * Pobiera informacje o użyciu AI
 * 
 * @param string $period Okres (day, week, month, year, all)
 * @return array Informacje o użyciu
 */
public function get_usage_stats($period = 'month') {
    global $wpdb;
    
    // Tabela logów API
    $logs_table = $wpdb->prefix . 'seo_openai_logs';
    
    // Sprawdź czy tabela istnieje
    if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
        return array(
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0,
            'by_model' => array(),
            'by_user' => array(),
            'by_day' => array()
        );
    }
    
    // Określ zakres dat
    $date_condition = '';
    switch ($period) {
        case 'day':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            $group_by = "HOUR(created_at)";
            $date_format = "%H:00";
            break;
        case 'week':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            $group_by = "DATE(created_at)";
            $date_format = "%Y-%m-%d";
            break;
        case 'month':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            $group_by = "DATE(created_at)";
            $date_format = "%Y-%m-%d";
            break;
        case 'year':
            $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            $group_by = "MONTH(created_at)";
            $date_format = "%Y-%m";
            break;
        default:
            $date_condition = "";
            $group_by = "DATE(created_at)";
            $date_format = "%Y-%m-%d";
    }
    
    // Pobierz ogólne statystyki
    $stats_query = "
        SELECT 
            COUNT(*) as requests,
            SUM(tokens) as tokens,
            SUM(cost) as cost
        FROM $logs_table
        WHERE 1=1 $date_condition
    ";
    
    $stats = $wpdb->get_row($stats_query, ARRAY_A);
    
    // Jeśli $stats jest null, przypisz domyślne wartości
    if (!$stats) {
        $stats = array(
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0
        );
    }
    
    // Pobierz statystyki według modelu
    $model_query = "
        SELECT 
            model,
            COUNT(*) as requests,
            SUM(tokens) as tokens,
            SUM(cost) as cost
        FROM $logs_table
        WHERE 1=1 $date_condition
        GROUP BY model
        ORDER BY requests DESC
    ";
    
    $by_model = $wpdb->get_results($model_query, ARRAY_A);
    
    // Pobierz statystyki według użytkownika
    $user_query = "
        SELECT 
            user_id,
            COUNT(*) as requests,
            SUM(tokens) as tokens,
            SUM(cost) as cost
        FROM $logs_table
        WHERE 1=1 $date_condition
        GROUP BY user_id
        ORDER BY requests DESC
        LIMIT 20
    ";
    
    $by_user_temp = $wpdb->get_results($user_query, ARRAY_A);
    
    // Dodaj dane użytkowników
    $by_user = array();
    if (is_array($by_user_temp)) {
        foreach ($by_user_temp as $user_stats) {
            $user_id = $user_stats['user_id'];
            $user = get_userdata($user_id);
            
            $by_user[] = array(
                'user_id' => $user_id,
                'user_login' => $user ? $user->user_login : '(Gość)',
                'user_email' => $user ? $user->user_email : '',
                'requests' => (int) $user_stats['requests'],
                'tokens' => (int) $user_stats['tokens'],
                'cost' => (float) $user_stats['cost']
            );
        }
    }
    
    // Pobierz statystyki według dnia/godziny
    $day_query = "
        SELECT 
            DATE_FORMAT(created_at, '$date_format') as date,
            COUNT(*) as requests,
            SUM(tokens) as tokens,
            SUM(cost) as cost
        FROM $logs_table
        WHERE 1=1 $date_condition
        GROUP BY $group_by
        ORDER BY created_at ASC
    ";
    
    $by_day = $wpdb->get_results($day_query, ARRAY_A);
    
    return array(
        'requests' => isset($stats['requests']) ? (int) $stats['requests'] : 0,
        'tokens' => isset($stats['tokens']) ? (int) $stats['tokens'] : 0,
        'cost' => isset($stats['cost']) ? (float) $stats['cost'] : 0,
        'by_model' => $by_model ?: array(),
        'by_user' => $by_user,
        'by_day' => $by_day ?: array()
    );
}
   
   /**
    * Pobiera użycie AI dla konkretnego użytkownika
    * 
    * @param int $user_id ID użytkownika
    * @param string $period Okres (day, week, month, year, all)
    * @return array Informacje o użyciu
    */
   public function get_user_usage($user_id, $period = 'day') {
       global $wpdb;
       
       // Tabela logów API
       $logs_table = $wpdb->prefix . 'seo_openai_logs';
       
       // Sprawdź czy tabela istnieje
       if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") !== $logs_table) {
           return array(
               'requests' => 0,
               'tokens' => 0,
               'cost' => 0,
               'limit' => $this->get_user_daily_limit($user_id),
               'remaining' => $this->get_user_daily_limit($user_id)
           );
       }
       
       // Określ zakres dat
       $date_condition = '';
       switch ($period) {
           case 'day':
               $date_condition = "AND created_at >= CURRENT_DATE()";
               break;
           case 'week':
               $date_condition = "AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL WEEKDAY(CURRENT_DATE()) DAY)";
               break;
           case 'month':
               $date_condition = "AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL DAYOFMONTH(CURRENT_DATE()) - 1 DAY)";
               break;
           case 'year':
               $date_condition = "AND YEAR(created_at) = YEAR(CURRENT_DATE())";
               break;
           default:
               $date_condition = "AND created_at >= CURRENT_DATE()";
       }
       
       // Pobierz statystyki użytkownika
       $query = $wpdb->prepare("
           SELECT 
               COUNT(*) as requests,
               SUM(tokens) as tokens,
               SUM(cost) as cost
           FROM $logs_table
           WHERE user_id = %d $date_condition
       ", $user_id);
       
       $stats = $wpdb->get_row($query, ARRAY_A);
       
       // Pobierz dzienny limit
       $limit = $this->get_user_daily_limit($user_id);
       
       // Oblicz pozostałą liczbę zapytań
       $requests = (int) $stats['requests'];
       $remaining = max(0, $limit - $requests);
       
       return array(
           'requests' => $requests,
           'tokens' => (int) $stats['tokens'],
           'cost' => (float) $stats['cost'],
           'limit' => $limit,
           'remaining' => $remaining
       );
   }
   
   /**
    * Sprawdza czy użytkownik osiągnął dzienny limit
    * 
    * @param int $user_id ID użytkownika
    * @return bool Czy limit został osiągnięty
    */
   public function has_user_reached_limit($user_id) {
       $usage = $this->get_user_usage($user_id, 'day');
       return $usage['requests'] >= $usage['limit'];
   }
}