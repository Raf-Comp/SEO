<?php
/**
 * Klasa obsługująca cache zapytań AI
 */
class CleanSEO_AI_Cache {
    /**
     * Nazwa tabeli cache
     */
    private $table_name;
    
    /**
     * Prefiks kluczy cache
     */
    private $prefix;
    
    /**
     * Domyślny czas życia cache (w sekundach)
     */
    private $default_ttl = 86400; // 24 godziny
    
    /**
     * Czy cache jest aktywny
     */
    private $enabled = true;
    
    /**
     * Maksymalny rozmiar cache w bajtach
     */
    private $max_size = 50000000; // 50MB
    
    /**
     * Typy zapytań do cachowania
     */
    private $cache_types = array(
        'meta_title',
        'meta_description',
        'content',
        'keyword_research',
        'content_audit',
        'competition',
        'faq'
    );
    
    /**
     * Konstruktor
     * 
     * @param string $prefix Prefiks używany dla kluczy cache
     */
    public function __construct($prefix = 'ai') {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'seo_ai_cache';
        $this->prefix = sanitize_key($prefix);
        
        // Pobierz ustawienia cache z opcji
        $options = get_option('cleanseo_options', array());
        $cache_settings = isset($options['ai_cache']) ? $options['ai_cache'] : array();
        
        // Ustawienia z opcji
        $this->enabled = isset($cache_settings['enabled']) ? (bool)$cache_settings['enabled'] : true;
        $this->default_ttl = isset($cache_settings['ttl']) ? (int)$cache_settings['ttl'] : 86400;
        $this->max_size = isset($cache_settings['max_size']) ? (int)$cache_settings['max_size'] : 50000000;
        
        // Automatyczne czyszczenie wygasłych wpisów raz dziennie
        add_action('cleanseo_daily_cleanup', array($this, 'clear_expired'));
        
        // Hook do czyszczenia cache po aktualizacji posta
        add_action('save_post', array($this, 'clear_post_cache'), 10, 2);
    }
    
    /**
     * Pobiera dane z cache
     * 
     * @param string $key Klucz cache
     * @return mixed|null Dane z cache lub null jeśli nie znaleziono
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        global $wpdb;
        
        $key = $this->prepare_key($key);
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE hash_key = %s AND expires_at > %s LIMIT 1",
                $key,
                current_time('mysql')
            )
        );
        
        if (!$result) {
            return null;
        }
        
        // Aktualizuj licznik trafień
        $this->increment_hits($key);
        
        // Jeśli dane są serializowane, zdekoduj
        $value = $result->response;
        if ($result->is_serialized) {
            $value = maybe_unserialize($value);
        }
        
        return $value;
    }
    
    /**
     * Zapisuje dane w cache
     * 
     * @param string $key Klucz cache
     * @param mixed $value Wartość do zapisania
     * @param string $model Model AI używany do generowania odpowiedzi
     * @param string $cache_type Typ zapytania (np. meta_title, content)
     * @param int $ttl Czas życia cache w sekundach
     * @param int $post_id Powiązany ID posta (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function set($key, $value, $model = '', $cache_type = '', $ttl = 0, $post_id = 0) {
        if (!$this->enabled) {
            return false;
        }
        
        // Sprawdź, czy typ zapytania jest wspierany
        if (!empty($cache_type) && !in_array($cache_type, $this->cache_types)) {
            return false;
        }
        
        global $wpdb;
        
        // Ustaw domyślny TTL jeśli nie podano
        if ($ttl <= 0) {
            $ttl = $this->default_ttl;
        }
        
        $key = $this->prepare_key($key);
        
        // Sprawdź czy dane wymagają serializacji
        $is_serialized = !is_string($value);
        $serialized_value = $is_serialized ? maybe_serialize($value) : $value;
        
        // Oblicz rozmiar danych
        $data_size = strlen($serialized_value);
        
        // Sprawdź czy cache nie przekracza maksymalnego rozmiaru
        $current_size = $this->get_total_size();
        if ($current_size + $data_size > $this->max_size) {
            // Spróbuj zwolnić miejsce
            $this->cleanup_cache($data_size);
            
            // Sprawdź ponownie rozmiar
            $current_size = $this->get_total_size();
            if ($current_size + $data_size > $this->max_size) {
                return false;
            }
        }
        
        // Usuń stary wpis, jeśli istnieje
        $this->delete($key);
        
        // Przygotuj datę wygaśnięcia
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);
        
        // Zapisz nowy wpis
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'hash_key' => $key,
                'model' => $model,
                'prompt' => substr($key, 0, 100), // Skrócona wersja dla debugowania
                'response' => $serialized_value,
                'cache_type' => $cache_type,
                'post_id' => $post_id,
                'is_serialized' => $is_serialized ? 1 : 0,
                'data_size' => $data_size,
                'hits' => 0,
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );
        
        return ($result !== false);
    }
    
    /**
     * Usuwa dane z cache
     * 
     * @param string $key Klucz cache
     * @return bool Czy operacja się powiodła
     */
    public function delete($key) {
        global $wpdb;
        
        $key = $this->prepare_key($key);
        
        $result = $wpdb->delete(
            $this->table_name,
            array('hash_key' => $key),
            array('%s')
        );
        
        return ($result !== false);
    }
    
    /**
     * Czyści cały cache
     * 
     * @return bool Czy operacja się powiodła
     */
    public function clear() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        return ($result !== false);
    }
    
    /**
     * Usuwa wygasłe wpisy z cache
     * 
     * @return int Liczba usuniętych wpisów
     */
    public function clear_expired() {
        global $wpdb;
        
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                current_time('mysql')
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Czyści cache dla określonego typu
     * 
     * @param string $cache_type Typ cache do wyczyszczenia
     * @return int Liczba usuniętych wpisów
     */
    public function clear_by_type($cache_type) {
        global $wpdb;
        
        $count = $wpdb->delete(
            $this->table_name,
            array('cache_type' => $cache_type),
            array('%s')
        );
        
        return (int) $count;
    }
    
    /**
     * Czyści cache dla określonego modelu
     * 
     * @param string $model Model AI
     * @return int Liczba usuniętych wpisów
     */
    public function clear_by_model($model) {
        global $wpdb;
        
        $count = $wpdb->delete(
            $this->table_name,
            array('model' => $model),
            array('%s')
        );
        
        return (int) $count;
    }
    
    /**
     * Czyści cache powiązany z postem
     * 
     * @param int $post_id ID posta
     * @param WP_Post $post Obiekt posta (opcjonalnie)
     * @return int Liczba usuniętych wpisów
     */
    public function clear_post_cache($post_id, $post = null) {
        if (!$post_id) {
            return 0;
        }
        
        // Jeśli to wersja robocza lub autosave, nie czyść cache
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return 0;
        }
        
        global $wpdb;
        
        $count = $wpdb->delete(
            $this->table_name,
            array('post_id' => $post_id),
            array('%d')
        );
        
        return (int) $count;
    }
    
    /**
     * Czyści stare lub mało używane wpisy aby zwolnić miejsce
     * 
     * @param int $needed_space Ilość potrzebnego miejsca w bajtach
     * @return int Zwolnione miejsce w bajtach
     */
    private function cleanup_cache($needed_space) {
        global $wpdb;
        
        // Najpierw usuń wygasłe wpisy
        $this->clear_expired();
        
        // Następnie usuń najmniej używane wpisy, aż do zwolnienia wystarczającej ilości miejsca
        $freed_space = 0;
        $items_to_delete = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, data_size FROM {$this->table_name} ORDER BY hits ASC, created_at ASC LIMIT 100"
            )
        );
        
        foreach ($items_to_delete as $item) {
            $wpdb->delete($this->table_name, array('id' => $item->id), array('%d'));
            $freed_space += $item->data_size;
            
            if ($freed_space >= $needed_space) {
                break;
            }
        }
        
        return $freed_space;
    }
    
    /**
     * Zwiększa licznik trafień dla klucza cache
     * 
     * @param string $key Klucz cache
     * @return bool Czy operacja się powiodła
     */
    private function increment_hits($key) {
        global $wpdb;
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET hits = hits + 1 WHERE hash_key = %s",
                $key
            )
        );
        
        return ($result !== false);
    }
    
    /**
     * Przygotowuje klucz cache (dodaje prefiks i hashuje)
     * 
     * @param string $key Oryginalny klucz
     * @return string Przygotowany klucz
     */
    private function prepare_key($key) {
        if (is_array($key) || is_object($key)) {
            $key = json_encode($key);
        }
        
        return md5($this->prefix . '_' . $key);
    }
    
    /**
     * Generuje klucz cache na podstawie zapytania
     * 
     * @param string $model Model AI
     * @param string $prompt Treść zapytania
     * @param array $params Dodatkowe parametry
     * @return string Wygenerowany klucz
     */
    public function generate_key($model, $prompt, $params = array()) {
        $data = array(
            'model' => $model,
            'prompt' => $prompt,
            'params' => $params
        );
        
        return $this->prepare_key(json_encode($data));
    }
    
    /**
     * Pobiera łączny rozmiar cache w bajtach
     * 
     * @return int Rozmiar cache w bajtach
     */
    private function get_total_size() {
        global $wpdb;
        
        $result = $wpdb->get_var("SELECT SUM(data_size) FROM {$this->table_name}");
        
        return (int) $result;
    }
    
    /**
     * Pobiera statystyki cache
     * 
     * @return array Statystyki cache
     */
    public function get_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $expired = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE expires_at < %s",
                current_time('mysql')
            )
        );
        
        // Całkowity rozmiar cache
        $size = $this->get_total_size();
        
        // Średnia liczba trafień
        $avg_hits = $wpdb->get_var("SELECT AVG(hits) FROM {$this->table_name}");
        
        // Liczba wpisów według typu
        $types = $wpdb->get_results(
            "SELECT cache_type, COUNT(*) as count FROM {$this->table_name} GROUP BY cache_type"
        );
        
        $types_stats = array();
        foreach ($types as $type) {
            $types_stats[$type->cache_type] = (int) $type->count;
        }
        
        // Najstarszy i najnowszy wpis
        $oldest = $wpdb->get_var("SELECT MIN(created_at) FROM {$this->table_name}");
        $newest = $wpdb->get_var("SELECT MAX(created_at) FROM {$this->table_name}");
        
        // Trafienia i chybienia w cache w ciągu ostatnich 24h
        // To wymagałoby dodatkowej tabeli logów, więc zostawiamy puste
        
        return array(
            'total' => (int) $total,
            'expired' => (int) $expired,
            'size' => (int) $size,
            'max_size' => $this->max_size,
            'usage_percent' => $this->max_size > 0 ? round(($size / $this->max_size) * 100, 2) : 0,
            'avg_hits' => round($avg_hits, 2),
            'types' => $types_stats,
            'oldest' => $oldest,
            'newest' => $newest,
            'enabled' => $this->enabled,
            'ttl' => $this->default_ttl
        );
    }
    
    /**
     * Pobiera najpopularniejsze typy zapytań
     * 
     * @param int $limit Limit wyników
     * @return array Popularne typy zapytań
     */
    public function get_popular_types($limit = 5) {
        global $wpdb;
        
        $popular_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    cache_type AS type, 
                    COUNT(*) AS count,
                    SUM(hits) AS total_hits,
                    AVG(hits) AS avg_hits
                FROM {$this->table_name} 
                WHERE cache_type != ''
                GROUP BY cache_type 
                ORDER BY total_hits DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $popular_types ?: array();
    }
    
    /**
     * Pobiera najpopularniejsze modele
     *
     * @param int $limit Limit wyników
     * @return array Popularne modele
     */
    public function get_popular_models($limit = 5) {
        global $wpdb;
        
        $popular_models = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    model, 
                    COUNT(*) AS count,
                    SUM(hits) AS total_hits,
                    AVG(hits) AS avg_hits,
                    SUM(data_size) AS total_size
                FROM {$this->table_name} 
                WHERE model != ''
                GROUP BY model 
                ORDER BY total_hits DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $popular_models ?: array();
    }
    
    /**
     * Pobiera najpopularniejsze wpisy w cache
     * 
     * @param int $limit Limit wyników
     * @return array Popularne wpisy
     */
    public function get_most_used_entries($limit = 10) {
        global $wpdb;
        
        $popular_entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    id,
                    hash_key,
                    model,
                    cache_type,
                    prompt,
                    data_size,
                    hits,
                    created_at,
                    expires_at
                FROM {$this->table_name} 
                ORDER BY hits DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $popular_entries ?: array();
    }
    
    /**
     * Włącza lub wyłącza cache
     * 
     * @param bool $enabled Czy cache ma być włączony
     * @return bool Poprzedni stan
     */
    public function set_enabled($enabled) {
        $previous = $this->enabled;
        $this->enabled = (bool) $enabled;
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['ai_cache'])) {
            $options['ai_cache'] = array();
        }
        $options['ai_cache']['enabled'] = $this->enabled;
        update_option('cleanseo_options', $options);
        
        return $previous;
    }
    
    /**
     * Ustawia domyślny czas życia cache
     * 
     * @param int $ttl Czas życia w sekundach
     * @return int Poprzedni czas życia
     */
    public function set_default_ttl($ttl) {
        $previous = $this->default_ttl;
        $this->default_ttl = max(60, (int) $ttl); // Minimum 1 minuta
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['ai_cache'])) {
            $options['ai_cache'] = array();
        }
        $options['ai_cache']['ttl'] = $this->default_ttl;
        update_option('cleanseo_options', $options);
        
        return $previous;
    }
    
    /**
     * Ustawia maksymalny rozmiar cache
     * 
     * @param int $size Rozmiar w bajtach
     * @return int Poprzedni rozmiar
     */
    public function set_max_size($size) {
        $previous = $this->max_size;
        $this->max_size = max(1000000, (int) $size); // Minimum 1MB
        
        // Zapisz ustawienie w opcjach
        $options = get_option('cleanseo_options', array());
        if (!isset($options['ai_cache'])) {
            $options['ai_cache'] = array();
        }
        $options['ai_cache']['max_size'] = $this->max_size;
        update_option('cleanseo_options', $options);
        
        // Jeśli nowy rozmiar jest mniejszy niż obecny, wyczyść nadmiar
        $current_size = $this->get_total_size();
        if ($current_size > $this->max_size) {
            $needed_space = $current_size - $this->max_size;
            $this->cleanup_cache($needed_space);
        }
        
        return $previous;
    }
    
    /**
     * Sprawdza czy klucz istnieje w cache
     * 
     * @param string $key Klucz cache
     * @return bool Czy klucz istnieje
     */
    public function has($key) {
        if (!$this->enabled) {
            return false;
        }
        
        global $wpdb;
        
        $key = $this->prepare_key($key);
        
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE hash_key = %s AND expires_at > %s",
                $key,
                current_time('mysql')
            )
        );
        
        return ($exists > 0);
    }
    
    /**
     * Zapamiętuje lub pobiera dane z cache
     * 
     * @param string $key Klucz cache
     * @param callable $callback Funkcja do wykonania, jeśli dane nie są w cache
     * @param string $model Model AI
     * @param string $cache_type Typ cache
     * @param int $ttl Czas życia cache
     * @param int $post_id ID posta
     * @return mixed Dane z cache lub z callbacka
     */
    public function remember($key, $callback, $model = '', $cache_type = '', $ttl = 0, $post_id = 0) {
        // Pobierz z cache jeśli istnieje
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Wykonaj callback
        $value = call_user_func($callback);
        
        // Zapisz wynik do cache
        if ($value !== null && !is_wp_error($value)) {
            $this->set($key, $value, $model, $cache_type, $ttl, $post_id);
        }
        
        return $value;
    }
    
    /**
     * Zwraca listę dostępnych typów cache
     * 
     * @return array Typy cache
     */
    public function get_cache_types() {
        return $this->cache_types;
    }
    
    /**
     * Dodaje nowy typ cache
     * 
     * @param string $type Typ cache
     * @return bool Czy dodano pomyślnie
     */
    public function add_cache_type($type) {
        $type = sanitize_key($type);
        
        if (empty($type) || in_array($type, $this->cache_types)) {
            return false;
        }
        
        $this->cache_types[] = $type;
        return true;
    }
}