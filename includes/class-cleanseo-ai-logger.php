<?php
/**
 * Klasa do logowania zapytań AI i zdarzeń
 */
class CleanSEO_AI_Logger {
    /**
     * Nazwa tabeli logów
     * @var string
     */
    private $table_name;
    
    /**
     * Nazwa komponentu, którego dotyczą logi
     * @var string
     */
    private $component;
    
    /**
     * Czy logowanie jest włączone
     * @var bool
     */
    private $enabled = true;
    
    /**
     * Poziom logowania
     * @var string
     */
    private $log_level = 'info';
    
    /**
     * Dostępne poziomy logowania
     * @var array
     */
    private $available_levels = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );
    
    /**
     * Konfiguracja podręcznego bufora logów
     * @var array
     */
    private $buffer_config = array(
        'enabled' => true,
        'size' => 10,
        'flush_on_shutdown' => true
    );
    
    /**
     * Bufor logów
     * @var array
     */
    private $log_buffer = array();
    
    /**
     * Konstruktor
     * 
     * @param string $component Nazwa komponentu (np. 'ai_api', 'background_process')
     */
    public function __construct($component = 'ai') {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'seo_logs';
        $this->component = sanitize_key($component);
        
        // Pobierz ustawienia logowania z opcji
        $options = get_option('cleanseo_options', array());
        $log_settings = isset($options['logging']) ? $options['logging'] : array();
        
        // Ustaw czy logowanie jest włączone
        $this->enabled = isset($log_settings['enabled']) ? (bool)$log_settings['enabled'] : true;
        
        // Ustaw poziom logowania
        if (isset($log_settings['level']) && array_key_exists($log_settings['level'], $this->available_levels)) {
            $this->log_level = $log_settings['level'];
        }
        
        // Ustaw konfigurację bufora
        if (isset($log_settings['buffer'])) {
            $this->buffer_config = wp_parse_args($log_settings['buffer'], $this->buffer_config);
        }
        
        // Jeśli buforowanie jest włączone, zarejestruj funkcję flush przy zamknięciu
        if ($this->buffer_config['enabled'] && $this->buffer_config['flush_on_shutdown']) {
            register_shutdown_function(array($this, 'flush_buffer'));
        }
    }
    
    /**
     * Loguje zdarzenie
     * 
     * @param string $level Poziom logowania (debug, info, warning, error, critical)
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function log($level, $message, $data = array()) {
        // Jeśli logowanie jest wyłączone, zakończ
        if (!$this->enabled) {
            return false;
        }
        
        // Sprawdź czy poziom logowania jest prawidłowy
        if (!array_key_exists($level, $this->available_levels)) {
            $level = 'info';
        }
        
        // Sprawdź czy poziom logowania jest wystarczający
        if ($this->available_levels[$level] < $this->available_levels[$this->log_level]) {
            return false;
        }
        
        // Przygotuj dane
        $log_entry = $this->prepare_log_entry($level, $message, $data);
        
        // Jeśli buforowanie jest włączone, dodaj do bufora
        if ($this->buffer_config['enabled']) {
            // Dodaj log do bufora
            $this->log_buffer[] = $log_entry;
            
            // Jeśli bufor osiągnął limit, wyślij go
            if (count($this->log_buffer) >= $this->buffer_config['size']) {
                $this->flush_buffer();
            }
            
            return true;
        }
        
        // Bezpośrednie zapisanie do bazy danych
        return $this->write_to_database($log_entry);
    }
    
    /**
     * Przygotowuje wpis logu
     * 
     * @param string $level Poziom logowania
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return array Gotowy wpis logu
     */
    private function prepare_log_entry($level, $message, $data) {
        // Podstawowe informacje
        $log_entry = array(
            'level' => $level,
            'component' => $this->component,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        // Dodatkowe informacje z kontekstu
        $context = array();
        
        // Informacje o użytkowniku
        if ($log_entry['user_id'] > 0) {
            $user = get_userdata($log_entry['user_id']);
            if ($user) {
                $context['user'] = array(
                    'id' => $user->ID,
                    'login' => $user->user_login,
                    'email' => $user->user_email,
                    'roles' => $user->roles
                );
            }
        }
        
        // Informacje o żądaniu
        if (!empty($_SERVER)) {
            $context['request'] = array(
                'ip' => $this->get_client_ip(),
                'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
                'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'ajax' => defined('DOING_AJAX') && DOING_AJAX
            );
        }
        
        // Informacje o wykonaniu
        $context['execution'] = array(
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'execution_time' => defined('CLEANSEO_START_TIME') ? microtime(true) - CLEANSEO_START_TIME : 0
        );
        
        // Jeśli przekazano dane, połącz je z kontekstem
        if (!empty($data)) {
            $context = array_merge($context, $data);
        }
        
        // Dodaj kontekst do wpisu
        $log_entry['data'] = $context;
        
        return $log_entry;
    }
    
    /**
     * Zapisuje wpis logu do bazy danych
     * 
     * @param array $log_entry Wpis logu
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    private function write_to_database($log_entry) {
        global $wpdb;
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            // Jeśli tabela nie istnieje, użyj pliku debug.log
            if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                $log_message = sprintf(
                    "[%s] [%s] [%s] %s %s",
                    date('Y-m-d H:i:s'),
                    $log_entry['level'],
                    $log_entry['component'],
                    $log_entry['message'],
                    json_encode($log_entry['data'])
                );
                error_log($log_message);
            }
            return false;
        }
        
        // Serializuj dane
        $serialized_data = maybe_serialize($log_entry['data']);
        
        // Wstaw wpis do bazy danych
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'level' => $log_entry['level'],
                'component' => $log_entry['component'],
                'action' => isset($log_entry['data']['action']) ? $log_entry['data']['action'] : '',
                'message' => $log_entry['message'],
                'data' => $serialized_data,
                'user_id' => $log_entry['user_id'],
                'ip_address' => isset($log_entry['data']['request']['ip']) ? $log_entry['data']['request']['ip'] : '',
                'timestamp' => $log_entry['timestamp']
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            // W przypadku błędu zapisu, spróbuj użyć debug.log
            if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                $log_message = sprintf(
                    "[%s] [%s] [%s] %s %s",
                    date('Y-m-d H:i:s'),
                    $log_entry['level'],
                    $log_entry['component'],
                    $log_entry['message'],
                    json_encode($log_entry['data'])
                );
                error_log($log_message);
            }
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Wysyła bufor logów do bazy danych
     * 
     * @return bool Czy operacja się powiodła
     */
    public function flush_buffer() {
        // Jeśli bufor jest pusty, zakończ
        if (empty($this->log_buffer)) {
            return true;
        }
        
        global $wpdb;
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            // Jeśli tabela nie istnieje, użyj pliku debug.log
            if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                foreach ($this->log_buffer as $log_entry) {
                    $log_message = sprintf(
                        "[%s] [%s] [%s] %s %s",
                        date('Y-m-d H:i:s'),
                        $log_entry['level'],
                        $log_entry['component'],
                        $log_entry['message'],
                        json_encode($log_entry['data'])
                    );
                    error_log($log_message);
                }
            }
            
            // Wyczyść bufor
            $this->log_buffer = array();
            return false;
        }
        
        // Przygotuj zapytanie do wstawienia wielu wpisów
        $values = array();
        $place_holders = array();
        $query = "INSERT INTO {$this->table_name} 
                  (level, component, action, message, data, user_id, ip_address, timestamp) 
                  VALUES ";
        
        foreach ($this->log_buffer as $log_entry) {
            $serialized_data = maybe_serialize($log_entry['data']);
            $action = isset($log_entry['data']['action']) ? $log_entry['data']['action'] : '';
            $ip = isset($log_entry['data']['request']['ip']) ? $log_entry['data']['request']['ip'] : '';
            
            $values[] = $log_entry['level'];
            $values[] = $log_entry['component'];
            $values[] = $action;
            $values[] = $log_entry['message'];
            $values[] = $serialized_data;
            $values[] = $log_entry['user_id'];
            $values[] = $ip;
            $values[] = $log_entry['timestamp'];
            
            $place_holders[] = "(%s, %s, %s, %s, %s, %d, %s, %s)";
        }
        
        $query .= implode(', ', $place_holders);
        
        // Wykonaj zapytanie
        $result = $wpdb->query($wpdb->prepare($query, $values));
        
        // Wyczyść bufor
        $this->log_buffer = array();
        
        return $result !== false;
    }
    
    /**
     * Pobiera adres IP klienta
     * 
     * @return string Adres IP
     */
    private function get_client_ip() {
        $ip = '';
        
        // CloudFlare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Normalny
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Jeśli IP zawiera wiele adresów, weź pierwszy
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        
        return $ip;
    }
    
    /**
     * Skrót do logowania informacji
     * 
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function info($message, $data = array()) {
        return $this->log('info', $message, $data);
    }
    
    /**
     * Skrót do logowania debugowania
     * 
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function debug($message, $data = array()) {
        return $this->log('debug', $message, $data);
    }
    
    /**
     * Skrót do logowania ostrzeżeń
     * 
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function warning($message, $data = array()) {
        return $this->log('warning', $message, $data);
    }
    
    /**
     * Skrót do logowania błędów
     * 
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function error($message, $data = array()) {
        return $this->log('error', $message, $data);
    }
    
    /**
     * Skrót do logowania błędów krytycznych
     * 
     * @param string $message Wiadomość
     * @param array $data Dodatkowe dane
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function critical($message, $data = array()) {
        return $this->log('critical', $message, $data);
    }
    
    /**
     * Loguje zapytanie API
     * 
     * @param string $status Status zapytania (success, error)
     * @param string $model Model AI
     * @param string $prompt Treść zapytania
     * @param string $response Odpowiedź API
     * @param array $metadata Metadane zapytania
     * @return int|bool ID wpisu w bazie danych lub false w przypadku niepowodzenia
     */
    public function log_api_request($status, $model, $prompt, $response, $metadata = array()) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return false;
        }
        
        // Przygotuj dane
        $user_id = get_current_user_id();
        $post_id = isset($metadata['post_id']) ? (int)$metadata['post_id'] : 0;
        $tokens = isset($metadata['tokens']) ? (int)$metadata['tokens'] : 0;
        $cost = isset($metadata['cost']) ? (float)$metadata['cost'] : 0.0;
        $duration = isset($metadata['duration']) ? (float)$metadata['duration'] : 0.0;
        $cache_hit = isset($metadata['cache_hit']) ? (bool)$metadata['cache_hit'] : false;
        $error_message = ($status === 'error' && isset($metadata['error_message'])) ? $metadata['error_message'] : '';
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Wstaw wpis do bazy danych
        $result = $wpdb->insert(
            $api_logs_table,
            array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'model' => $model,
                'prompt' => $prompt,
                'response' => $response,
                'cache_hit' => $cache_hit ? 1 : 0,
                'tokens' => $tokens,
                'cost' => $cost,
                'duration' => $duration,
                'status' => $status,
                'error_message' => $error_message,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        // Zapisz również podstawowe informacje w głównej tabeli logów
        $this->log($status === 'success' ? 'info' : 'error', 
            $status === 'success' ? "Successful API request to $model" : "Failed API request to $model",
            array(
                'action' => 'api_request',
                'model' => $model,
                'tokens' => $tokens,
                'cost' => $cost,
                'duration' => $duration,
                'post_id' => $post_id,
                'cache_hit' => $cache_hit,
                'error_message' => $error_message
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Pobiera logi API użytkownika
     * 
     * @param int $user_id ID użytkownika
     * @param array $params Parametry zapytania
     * @return array Logi API
     */
    public function get_user_api_logs($user_id, $params = array()) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return array();
        }
        
        $limit = isset($params['limit']) ? absint($params['limit']) : 10;
        $offset = isset($params['offset']) ? absint($params['offset']) : 0;
        $model = isset($params['model']) ? sanitize_text_field($params['model']) : '';
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
        $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
        $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
        
        // Buduj zapytanie
        $query = "SELECT * FROM {$api_logs_table} WHERE user_id = %d";
        $query_args = array($user_id);
        
        // Dodaj filtry
        if (!empty($model)) {
            $query .= " AND model = %s";
            $query_args[] = $model;
        }
        
        if (!empty($status)) {
            $query .= " AND status = %s";
            $query_args[] = $status;
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $query .= " AND created_at BETWEEN %s AND %s";
            $query_args[] = $start_date . ' 00:00:00';
            $query_args[] = $end_date . ' 23:59:59';
        }
        
        // Dodaj sortowanie i limity
        $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_args[] = $limit;
        $query_args[] = $offset;
        
        // Wykonaj zapytanie
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $query_args),
            ARRAY_A
        );
        
        // Zlicz wszystkie wyniki
        $count_query = "SELECT COUNT(*) FROM {$api_logs_table} WHERE user_id = %d";
        $count_args = array($user_id);
        
        // Dodaj filtry do zapytania liczącego
        if (!empty($model)) {
            $count_query .= " AND model = %s";
            $count_args[] = $model;
        }
        
        if (!empty($status)) {
            $count_query .= " AND status = %s";
            $count_args[] = $status;
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $count_query .= " AND created_at BETWEEN %s AND %s";
            $count_args[] = $start_date . ' 00:00:00';
            $count_args[] = $end_date . ' 23:59:59';
        }
        
        $total = $wpdb->get_var(
            $wpdb->prepare($count_query, $count_args)
        );
        
        return array(
            'items' => $results ?: array(),
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        );
    }
    
    /**
     * Pobiera statystyki użycia API dla danego okresu
     * 
     * @param string $period Okres (day, week, month, year, custom)
     * @param string $start_date Data początkowa (tylko dla custom)
     * @param string $end_date Data końcowa (tylko dla custom)
     * @return array Statystyki użycia
     */
    public function get_api_usage_stats($period = 'day', $start_date = '', $end_date = '') {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return array(
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'total_tokens' => 0,
                'avg_tokens' => 0,
                'avg_processing_time' => 0,
                'total_cost' => 0,
                'cache_hits' => 0,
                'cache_hit_ratio' => 0,
                'by_model' => array(),
                'by_day' => array()
            );
        }
        
        // Określ zakres dat
        switch ($period) {
            case 'day':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $end_date = current_time('mysql');
                $group_by = 'HOUR(created_at)';
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 week'));
                $end_date = current_time('mysql');
                $group_by = 'DATE(created_at)';
                break;
            case 'month':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 month'));
                $end_date = current_time('mysql');
                $group_by = 'DATE(created_at)';
                break;
            case 'year':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 year'));
                $end_date = current_time('mysql');
                $group_by = 'MONTH(created_at)';
                break;
            case 'custom':
                // Użyj przekazanych dat
                if (empty($start_date) || empty($end_date)) {
                    return array();
                }
                $start_date = $start_date . ' 00:00:00';
                $end_date = $end_date . ' 23:59:59';
                $group_by = 'DATE(created_at)';
                break;
            default:
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $end_date = current_time('mysql');
                $group_by = 'HOUR(created_at)';
        }
        
        // Pobierz ogólne statystyki
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests,
                SUM(tokens) as total_tokens,
                AVG(tokens) as avg_tokens,
                AVG(duration) as avg_processing_time,
                SUM(cost) as total_cost,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits
            FROM {$api_logs_table}
            WHERE created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        );
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        // Pobierz statystyki według modelu
        $model_query = $wpdb->prepare(
            "SELECT 
                model,
                COUNT(*) as requests,
                SUM(tokens) as tokens,
                SUM(cost) as cost,
                AVG(duration) as avg_duration
            FROM {$api_logs_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY model
            ORDER BY requests DESC",
            $start_date,
            $end_date
        );
        
        $by_model = $wpdb->get_results($model_query, ARRAY_A);
        
        // Pobierz statystyki według dnia/godziny
        $time_query = $wpdb->prepare(
            "SELECT 
                {$group_by} as time_unit,
                COUNT(*) as requests,
                SUM(tokens) as tokens,
                SUM(cost) as cost,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits
            FROM {$api_logs_table}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY time_unit
            ORDER BY time_unit ASC",
            $start_date,
            $end_date
        );
        
        $by_time = $wpdb->get_results($time_query, ARRAY_A);
        
        // Oblicz dodatkowe statystyki
        $cache_hit_ratio = 0;
        if ($result['total_requests'] > 0) {
            $cache_hit_ratio = ($result['cache_hits'] / $result['total_requests']) * 100;
        }
        
        return array(
            'total_requests' => (int) $result['total_requests'],
            'successful_requests' => (int) $result['successful_requests'],
            'failed_requests' => (int) $result['failed_requests'],
            'total_tokens' => (int) $result['total_tokens'],
            'avg_tokens' => round($result['avg_tokens'], 2),
            'avg_processing_time' => round($result['avg_processing_time'], 3),
            'total_cost' => round($result['total_cost'], 5),
            'cache_hits' => (int) $result['cache_hits'],
            'cache_hit_ratio' => round($cache_hit_ratio, 2),
            'by_model' => $by_model ?: array(),
            'by_time' => $by_time ?: array(),
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Pobiera najpopularniejsze błędy API
     * 
     * @param int $limit Limit wyników
     * @return array Popularne błędy
     */
    public function get_common_api_errors($limit = 5) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return array();
        }
        
        // Pobierz najczęstsze błędy
        $query = $wpdb->prepare(
            "SELECT 
                error_message, 
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
            FROM {$api_logs_table} 
            WHERE status = 'error' AND error_message != '' 
            GROUP BY error_message 
            ORDER BY count DESC 
            LIMIT %d",
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results ?: array();
    }
    
    /**
     * Eksportuje logi API do CSV
     * 
     * @param array $params Parametry eksportu
     * @return string|WP_Error Zawartość CSV lub obiekt błędu
     */
    public function export_api_logs_to_csv($params = array()) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return new WP_Error('no_table', 'Tabela logów API nie istnieje.');
        }
        
        // Parametry eksportu
        $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
        $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
        $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
        $model = isset($params['model']) ? sanitize_text_field($params['model']) : '';
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
        $include_prompt = isset($params['include_prompt']) ? (bool)$params['include_prompt'] : false;
        $include_response = isset($params['include_response']) ? (bool)$params['include_response'] : false;
        
        // Buduj zapytanie
        $query = "SELECT 
                    id, user_id, post_id, model, status, 
                    tokens, cost, duration, cache_hit, 
                    error_message, ip_address, created_at";
                    
        // Dodaj prompt i response jeśli wymagane
        if ($include_prompt) {
            $query .= ", prompt";
        }
        
        if ($include_response) {
            $query .= ", response";
        }
        
        $query .= " FROM {$api_logs_table}";
        $query_args = array();
        $where_clauses = array();
        
        // Dodaj filtry
        if ($user_id > 0) {
            $where_clauses[] = "user_id = %d";
            $query_args[] = $user_id;
        }
        
        if (!empty($model)) {
            $where_clauses[] = "model = %s";
            $query_args[] = $model;
        }
        
        if (!empty($status)) {
            $where_clauses[] = "status = %s";
            $query_args[] = $status;
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $where_clauses[] = "created_at BETWEEN %s AND %s";
            $query_args[] = $start_date . ' 00:00:00';
            $query_args[] = $end_date . ' 23:59:59';
        }
        
        // Dodaj klauzule WHERE
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Dodaj sortowanie
        $query .= " ORDER BY created_at DESC";
        
        // Wykonaj zapytanie
        if (!empty($query_args)) {
            $results = $wpdb->get_results(
                $wpdb->prepare($query, $query_args),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }
        
        if (!$results) {
            return new WP_Error('no_data', 'Brak danych dla podanych kryteriów.');
        }
        
        // Przygotuj CSV
        $csv = '';
        
        // Nagłówki
        $headers = array_keys($results[0]);
        $csv .= implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers)) . "\n";
        
        // Dane
        foreach ($results as $row) {
            // Obróbka specjalnych pól
            if (isset($row['cache_hit'])) {
                $row['cache_hit'] = $row['cache_hit'] ? 'Yes' : 'No';
            }
            
            // Przytnij długie pola
            if (isset($row['prompt']) && strlen($row['prompt']) > 1000) {
                $row['prompt'] = substr($row['prompt'], 0, 997) . '...';
            }
            
            if (isset($row['response']) && strlen($row['response']) > 1000) {
                $row['response'] = substr($row['response'], 0, 997) . '...';
            }
            
            $csv .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Pobiera dzienne statystyki użycia API dla użytkownika
     * 
     * @param int $user_id ID użytkownika
     * @return array Statystyki dzienne
     */
    public function get_daily_api_stats($user_id = null) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return array(
                'count' => 0,
                'tokens' => 0,
                'cost' => 0,
                'limit' => 0,
                'remaining' => 0
            );
        }
        
        // Jeśli nie podano ID użytkownika, użyj aktualnie zalogowanego
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Jeśli użytkownik nie jest zalogowany, zwróć puste statystyki
        if ($user_id <= 0) {
            return array(
                'count' => 0,
                'tokens' => 0,
                'cost' => 0,
                'limit' => 0,
                'remaining' => 0
            );
        }
        
        // Początek i koniec dnia
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        // Pobierz dzienne statystyki
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as count,
                SUM(tokens) as tokens,
                SUM(cost) as cost
            FROM {$api_logs_table} 
            WHERE user_id = %d AND created_at BETWEEN %s AND %s",
            $user_id,
            $today_start,
            $today_end
        );
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        // Pobierz dzienny limit z ustawień użytkownika lub systemu
        $limit = $this->get_user_daily_limit($user_id);
        
        // Oblicz pozostałą liczbę zapytań
        $count = (int) ($result['count'] ?? 0);
        $remaining = max(0, $limit - $count);
        
        return array(
            'count' => $count,
            'tokens' => (int) ($result['tokens'] ?? 0),
            'cost' => round(floatval($result['cost'] ?? 0), 5),
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $limit > 0 ? round(($count / $limit) * 100, 2) : 0
        );
    }
    
    /**
     * Pobiera dzienny limit zapytań API dla użytkownika
     * 
     * @param int $user_id ID użytkownika
     * @return int Dzienny limit
     */
    private function get_user_daily_limit($user_id) {
        // Domyślny limit
        $default_limit = 50;
        
        // Pobierz ustawienia z opcji
        $options = get_option('cleanseo_options', array());
        
        // Pobierz globalny limit
        if (isset($options['ai_limits']['daily_limit'])) {
            $default_limit = (int) $options['ai_limits']['daily_limit'];
        }
        
        // Sprawdź czy użytkownik ma własny limit
        $user_limit = get_user_meta($user_id, 'cleanseo_ai_daily_limit', true);
        if ($user_limit !== '') {
            return (int) $user_limit;
        }
        
        // Sprawdź limit dla roli użytkownika
        $user = get_userdata($user_id);
        if ($user && !empty($user->roles)) {
            foreach ($user->roles as $role) {
                if (isset($options['ai_limits']['role_limits'][$role])) {
                    return (int) $options['ai_limits']['role_limits'][$role];
                }
            }
        }
        
        return $default_limit;
    }
    
    /**
     * Sprawdza czy użytkownik osiągnął dzienny limit zapytań API
     * 
     * @param int $user_id ID użytkownika
     * @return bool Czy limit został osiągnięty
     */
    public function has_reached_daily_limit($user_id = null) {
        // Jeśli nie podano ID użytkownika, użyj aktualnie zalogowanego
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Jeśli użytkownik nie jest zalogowany, zwróć true (limit osiągnięty)
        if ($user_id <= 0) {
            return true;
        }
        
        // Pobierz dzienne statystyki
        $stats = $this->get_daily_api_stats($user_id);
        
        // Sprawdź czy limit został osiągnięty
        return $stats['remaining'] <= 0;
    }
    
    /**
     * Pobiera logi główne
     * 
     * @param array $params Parametry zapytania
     * @return array Logi
     */
    public function get_logs($params = array()) {
        global $wpdb;
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            return array(
                'items' => array(),
                'total' => 0,
                'pages' => 0
            );
        }
        
        // Parametry zapytania
        $limit = isset($params['limit']) ? absint($params['limit']) : 20;
        $offset = isset($params['offset']) ? absint($params['offset']) : 0;
        $level = isset($params['level']) ? sanitize_text_field($params['level']) : '';
        $component = isset($params['component']) ? sanitize_text_field($params['component']) : '';
        $action = isset($params['action']) ? sanitize_text_field($params['action']) : '';
        $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
        $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
        $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
        $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
        
        // Buduj zapytanie
        $query = "SELECT * FROM {$this->table_name}";
        $query_args = array();
        $where_clauses = array();
        
        // Dodaj filtry
        if (!empty($level)) {
            $where_clauses[] = "level = %s";
            $query_args[] = $level;
        }
        
        if (!empty($component)) {
            $where_clauses[] = "component = %s";
            $query_args[] = $component;
        }
        
        if (!empty($action)) {
            $where_clauses[] = "action = %s";
            $query_args[] = $action;
        }
        
        if ($user_id > 0) {
            $where_clauses[] = "user_id = %d";
            $query_args[] = $user_id;
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $where_clauses[] = "timestamp BETWEEN %s AND %s";
            $query_args[] = $start_date . ' 00:00:00';
            $query_args[] = $end_date . ' 23:59:59';
        }
        
        if (!empty($search)) {
            $where_clauses[] = "(message LIKE %s OR data LIKE %s)";
            $query_args[] = '%' . $wpdb->esc_like($search) . '%';
            $query_args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Dodaj klauzule WHERE
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Dodaj sortowanie i limity
        $query .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $query_args[] = $limit;
        $query_args[] = $offset;
        
        // Wykonaj zapytanie
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $query_args),
            ARRAY_A
        );
        
        // Zlicz wszystkie wyniki
        $count_query = "SELECT COUNT(*) FROM {$this->table_name}";
        $count_args = array();
        
        // Dodaj klauzule WHERE do zapytania liczącego
        if (!empty($where_clauses)) {
            $count_query .= " WHERE " . implode(' AND ', $where_clauses);
            $count_args = $query_args;
            // Usuń limit i offset z końca tablicy argumentów
            array_pop($count_args);
            array_pop($count_args);
        }
        
        // Wykonaj zapytanie liczące
        if (!empty($count_args)) {
            $total = $wpdb->get_var(
                $wpdb->prepare($count_query, $count_args)
            );
        } else {
            $total = $wpdb->get_var($count_query);
        }
        
        // Przetwórz wyniki - deserializuj dane
        if ($results) {
            foreach ($results as &$row) {
                if (isset($row['data'])) {
                    $row['data'] = maybe_unserialize($row['data']);
                }
                
                // Dołącz dane użytkownika
                if ($row['user_id'] > 0) {
                    $user = get_userdata($row['user_id']);
                    if ($user) {
                        $row['user_login'] = $user->user_login;
                        $row['user_email'] = $user->user_email;
                    }
                }
            }
        }
        
        return array(
            'items' => $results ?: array(),
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        );
    }
    
    /**
     * Pobiera statystyki logów
     * 
     * @param string $period Okres (day, week, month, year)
     * @return array Statystyki logów
     */
    public function get_log_stats($period = 'day') {
        global $wpdb;
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            return array(
                'total' => 0,
                'by_level' => array(),
                'by_component' => array(),
                'by_date' => array()
            );
        }
        
        // Określ zakres dat
        switch ($period) {
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 week'));
                break;
            case 'month':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 month'));
                break;
            case 'year':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 year'));
                break;
            default: // day
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
        }
        
        $end_date = current_time('mysql');
        
        // Pobierz całkowitą liczbę logów
        $total_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE timestamp BETWEEN %s AND %s",
            $start_date,
            $end_date
        );
        
        $total = $wpdb->get_var($total_query);
        
        // Pobierz statystyki według poziomu
        $level_query = $wpdb->prepare(
            "SELECT 
                level, 
                COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE timestamp BETWEEN %s AND %s
            GROUP BY level 
            ORDER BY FIELD(level, 'critical', 'error', 'warning', 'info', 'debug')",
            $start_date,
            $end_date
        );
        
        $by_level = $wpdb->get_results($level_query, ARRAY_A);
        
        // Pobierz statystyki według komponentu
        $component_query = $wpdb->prepare(
            "SELECT 
                component, 
                COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE timestamp BETWEEN %s AND %s
            GROUP BY component 
            ORDER BY count DESC
            LIMIT 10",
            $start_date,
            $end_date
        );
        
        $by_component = $wpdb->get_results($component_query, ARRAY_A);
        
        // Pobierz statystyki według daty/godziny
        $date_format = ($period === 'day') ? '%H:00' : '%Y-%m-%d';
        
        $date_query = $wpdb->prepare(
            "SELECT 
                DATE_FORMAT(timestamp, %s) as date_unit, 
                COUNT(*) as count,
                SUM(CASE WHEN level = 'error' OR level = 'critical' THEN 1 ELSE 0 END) as errors
            FROM {$this->table_name} 
            WHERE timestamp BETWEEN %s AND %s
            GROUP BY date_unit 
            ORDER BY timestamp ASC",
            $date_format,
            $start_date,
            $end_date
        );
        
        $by_date = $wpdb->get_results($date_query, ARRAY_A);
        
        return array(
            'total' => (int) $total,
            'by_level' => $by_level ?: array(),
            'by_component' => $by_component ?: array(),
            'by_date' => $by_date ?: array(),
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Czyści stare logi
     * 
     * @param int $days_old Liczba dni, po których logi są usuwane
     * @return int Liczba usuniętych wpisów
     */
    public function clear_old_logs($days_old = 30) {
        global $wpdb;
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            return 0;
        }
        
        // Data graniczna
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Usuń stare logi
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE timestamp < %s",
                $cutoff_date
            )
        );
        
        return (int) $deleted;
    }
    
    /**
     * Czyści stare logi API
     * 
     * @param int $days_old Liczba dni, po których logi są usuwane
     * @return int Liczba usuniętych wpisów
     */
    public function clear_old_api_logs($days_old = 90) {
        global $wpdb;
        
        // Tabela logów API
        $api_logs_table = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") === $api_logs_table;
        if (!$table_exists) {
            return 0;
        }
        
        // Data graniczna
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Usuń stare logi
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$api_logs_table} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        return (int) $deleted;
    }
    
    /**
     * Włącza lub wyłącza logowanie
     * 
     * @param bool $enabled Czy logowanie ma być włączone
     * @return bool Poprzedni stan
     */
    public function set_enabled($enabled) {
        $previous = $this->enabled;
        $this->enabled = (bool) $enabled;
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['logging'])) {
            $options['logging'] = array();
        }
        $options['logging']['enabled'] = $this->enabled;
        update_option('cleanseo_options', $options);
        
        return $previous;
    }
    
    /**
     * Ustawia poziom logowania
     * 
     * @param string $level Poziom logowania (debug, info, warning, error, critical)
     * @return string Poprzedni poziom
     */
    public function set_log_level($level) {
        if (!array_key_exists($level, $this->available_levels)) {
            return $this->log_level;
        }
        
        $previous = $this->log_level;
        $this->log_level = $level;
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['logging'])) {
            $options['logging'] = array();
        }
        $options['logging']['level'] = $this->log_level;
        update_option('cleanseo_options', $options);
        
        return $previous;
    }
    
    /**
     * Ustawia konfigurację bufora
     * 
     * @param array $config Konfiguracja bufora
     * @return array Poprzednia konfiguracja
     */
    public function set_buffer_config($config) {
        $previous = $this->buffer_config;
        $this->buffer_config = wp_parse_args($config, $this->buffer_config);
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['logging'])) {
            $options['logging'] = array();
        }
        $options['logging']['buffer'] = $this->buffer_config;
        update_option('cleanseo_options', $options);
        
        return $previous;
    }
}