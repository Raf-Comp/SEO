<?php
/**
 * Klasa odpowiedzialna za obsługę statystyk
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Stats {
    private $db;
    private $logger;
    private $stats_table;
    private $cache_group = 'cleanseo_stats';
    private $cache_time = 3600; // 1 godzina

    public function __construct() {
        global $wpdb;
        $this->db = new CleanSEO_Database();
        $this->logger = new CleanSEO_Logger();
        $this->stats_table = $wpdb->prefix . 'seo_stats';
        
        // Inicjalizuj hooki
        $this->init_hooks();
    }
    
    /**
     * Inicjalizuj hooki
     */
    private function init_hooks() {
        // Dodaj hook dla codziennego czyszczenia starych statystyk
        if (!wp_next_scheduled('cleanseo_daily_stats_cleanup')) {
            wp_schedule_event(time(), 'daily', 'cleanseo_daily_stats_cleanup');
        }
        
        add_action('cleanseo_daily_stats_cleanup', array($this, 'cleanup_old_stats'));
        
        // Hook dla czyszczenia cache
        add_action('cleanseo_stats_updated', array($this, 'clear_stats_cache'));
    }

    /**
     * Dodaj statystyki
     *
     * @param array $data Dane statystyk
     * @return bool
     */
    public function add_stats($data) {
        global $wpdb;

        // Sprawdź poprawność danych wejściowych
        if (!is_array($data)) {
            $this->logger->log('error', 'Nieprawidłowy format danych', array('data' => $data));
            return false;
        }

        try {
            // Przygotuj dane do wstawienia
            $insert_data = array(
                'date' => current_time('Y-m-d'),
                'page_views' => isset($data['page_views']) ? absint($data['page_views']) : 0,
                'unique_visitors' => isset($data['unique_visitors']) ? absint($data['unique_visitors']) : 0,
                'bounce_rate' => isset($data['bounce_rate']) ? floatval($data['bounce_rate']) : 0,
                'avg_time_on_site' => isset($data['avg_time_on_site']) ? absint($data['avg_time_on_site']) : 0,
                'created_at' => current_time('mysql')
            );
            
            // Upewnij się, że wartości są w dopuszczalnym zakresie
            if ($insert_data['bounce_rate'] < 0 || $insert_data['bounce_rate'] > 100) {
                $insert_data['bounce_rate'] = min(100, max(0, $insert_data['bounce_rate']));
            }

            // Sprawdź, czy statystyki dla tej daty już istnieją
            $existing = $this->get_stats_by_date($insert_data['date']);
            
            if ($existing) {
                // Aktualizuj istniejące statystyki
                return $this->update_stats($insert_data['date'], $insert_data);
            }

            // Wstaw nowe statystyki
            $result = $wpdb->insert($this->stats_table, $insert_data);

            if ($result) {
                $this->logger->log('info', 'Dodano statystyki', array(
                    'date' => $insert_data['date'],
                    'data' => $insert_data
                ));
                
                do_action('cleanseo_stats_updated');
                
                return true;
            }

            throw new Exception($wpdb->last_error);

        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd dodawania statystyk', array(
                'error' => $e->getMessage(),
                'data' => $data
            ));
            return false;
        }
    }

    /**
     * Pobierz statystyki
     *
     * @param string $start_date Data początkowa (Y-m-d)
     * @param string $end_date Data końcowa (Y-m-d)
     * @param bool $use_cache Czy używać cache
     * @return array
     */
    public function get_stats($start_date = null, $end_date = null, $use_cache = true) {
        global $wpdb;

        // Generuj klucz cache na podstawie parametrów
        $cache_key = 'cleanseo_stats_' . md5($start_date . $end_date);
        
        // Próbuj pobrać dane z cache
        if ($use_cache) {
            $stats = wp_cache_get($cache_key, $this->cache_group);
            if ($stats !== false) {
                return $stats;
            }
        }

        $where = array();
        $params = array();

        if ($start_date) {
            $where[] = 'date >= %s';
            $params[] = $start_date;
        }

        if ($end_date) {
            $where[] = 'date <= %s';
            $params[] = $end_date;
        }

        $sql = "SELECT * FROM {$this->stats_table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY date DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $stats = $wpdb->get_results($sql);
        
        // Jeśli wystąpił błąd, zaloguj go
        if ($stats === null) {
            $this->logger->log('error', 'Błąd podczas pobierania statystyk', array(
                'error' => $wpdb->last_error,
                'sql' => $sql
            ));
            return array();
        }
        
        // Zapisz wynik w cache
        if ($use_cache) {
            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        }

        return $stats;
    }

    /**
     * Pobierz statystyki za ostatnie X dni
     *
     * @param int $days Liczba dni
     * @param bool $use_cache Czy używać cache
     * @return array
     */
    public function get_recent_stats($days = 30, $use_cache = true) {
        global $wpdb;
        
        // Generuj klucz cache na podstawie parametrów
        $cache_key = 'cleanseo_recent_stats_' . $days;
        
        // Próbuj pobrać dane z cache
        if ($use_cache) {
            $stats = wp_cache_get($cache_key, $this->cache_group);
            if ($stats !== false) {
                return $stats;
            }
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->stats_table} 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
            ORDER BY date DESC",
            $days
        );

        $stats = $wpdb->get_results($sql);
        
        // Jeśli wystąpił błąd, zaloguj go
        if ($stats === null) {
            $this->logger->log('error', 'Błąd podczas pobierania ostatnich statystyk', array(
                'error' => $wpdb->last_error,
                'days' => $days
            ));
            return array();
        }
        
        // Zapisz wynik w cache
        if ($use_cache) {
            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        }

        return $stats;
    }

    /**
     * Pobierz podsumowanie statystyk
     *
     * @param string $start_date Data początkowa (Y-m-d)
     * @param string $end_date Data końcowa (Y-m-d)
     * @param bool $use_cache Czy używać cache
     * @return object
     */
    public function get_stats_summary($start_date = null, $end_date = null, $use_cache = true) {
        global $wpdb;
        
        // Generuj klucz cache na podstawie parametrów
        $cache_key = 'cleanseo_stats_summary_' . md5($start_date . $end_date);
        
        // Próbuj pobrać dane z cache
        if ($use_cache) {
            $summary = wp_cache_get($cache_key, $this->cache_group);
            if ($summary !== false) {
                return $summary;
            }
        }

        $where = array();
        $params = array();

        if ($start_date) {
            $where[] = 'date >= %s';
            $params[] = $start_date;
        }

        if ($end_date) {
            $where[] = 'date <= %s';
            $params[] = $end_date;
        }

        $sql = "SELECT 
            SUM(page_views) as total_page_views,
            SUM(unique_visitors) as total_unique_visitors,
            AVG(bounce_rate) as avg_bounce_rate,
            AVG(avg_time_on_site) as avg_time_on_site,
            COUNT(*) as days_count
            FROM {$this->stats_table}";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $summary = $wpdb->get_row($sql);
        
        // Jeśli wystąpił błąd, zaloguj go
        if ($summary === null) {
            $this->logger->log('error', 'Błąd podczas pobierania podsumowania statystyk', array(
                'error' => $wpdb->last_error,
                'sql' => $sql
            ));
            return (object) array(
                'total_page_views' => 0,
                'total_unique_visitors' => 0,
                'avg_bounce_rate' => 0,
                'avg_time_on_site' => 0,
                'days_count' => 0
            );
        }
        
        // Zapisz wynik w cache
        if ($use_cache) {
            wp_cache_set($cache_key, $summary, $this->cache_group, $this->cache_time);
        }

        return $summary;
    }

    /**
     * Wyczyść stare statystyki
     *
     * @param int $days Starsze niż X dni
     * @return bool
     */
    public function cleanup_old_stats($days = 90) {
        global $wpdb;

        try {
            // Pobierz liczbę statystyk do usunięcia (dla logów)
            $count_to_delete = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->stats_table} 
                WHERE date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                $days
            ));
            
            // Usuń stare statystyki
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->stats_table} 
                WHERE date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                $days
            ));

            if ($result !== false) {
                $this->logger->log('info', 'Wyczyszczono stare statystyki', array(
                    'days' => $days,
                    'deleted' => $result,
                    'to_delete' => $count_to_delete
                ));
                
                do_action('cleanseo_stats_updated');
                
                return true;
            }

            throw new Exception($wpdb->last_error);

        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd czyszczenia starych statystyk', array(
                'error' => $e->getMessage(),
                'days' => $days
            ));
            return false;
        }
    }

    /**
     * Pobierz statystyki dla konkretnej daty
     *
     * @param string $date Data (Y-m-d)
     * @param bool $use_cache Czy używać cache
     * @return object|null
     */
    public function get_stats_by_date($date, $use_cache = true) {
        global $wpdb;

        // Generuj klucz cache na podstawie parametrów
        $cache_key = 'cleanseo_stats_date_' . $date;
        
        // Próbuj pobrać dane z cache
        if ($use_cache) {
            $stats = wp_cache_get($cache_key, $this->cache_group);
            if ($stats !== false) {
                return $stats;
            }
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->stats_table} WHERE date = %s",
            $date
        ));
        
        // Jeśli wystąpił błąd, zaloguj go
        if ($stats === null && $wpdb->last_error) {
            $this->logger->log('error', 'Błąd podczas pobierania statystyk dla daty', array(
                'error' => $wpdb->last_error,
                'date' => $date
            ));
        }
        
        // Zapisz wynik w cache
        if ($use_cache && $stats) {
            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_time);
        }

        return $stats;
    }

    /**
     * Aktualizuj statystyki dla konkretnej daty
     *
     * @param string $date Data (Y-m-d)
     * @param array $data Dane do aktualizacji
     * @return bool
     */
    public function update_stats($date, $data) {
        global $wpdb;

        try {
            // Upewnij się, że bounce_rate jest w zakresie 0-100
            if (isset($data['bounce_rate'])) {
                $data['bounce_rate'] = min(100, max(0, floatval($data['bounce_rate'])));
            }
            
            // Dodaj timestamp aktualizacji
            $data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $this->stats_table,
                array(
                    'page_views' => isset($data['page_views']) ? absint($data['page_views']) : 0,
                    'unique_visitors' => isset($data['unique_visitors']) ? absint($data['unique_visitors']) : 0,
                    'bounce_rate' => isset($data['bounce_rate']) ? floatval($data['bounce_rate']) : 0,
                    'avg_time_on_site' => isset($data['avg_time_on_site']) ? absint($data['avg_time_on_site']) : 0,
                    'updated_at' => $data['updated_at']
                ),
                array('date' => $date)
            );

            if ($result !== false) {
                $this->logger->log('info', 'Zaktualizowano statystyki', array(
                    'date' => $date,
                    'data' => $data
                ));
                
                do_action('cleanseo_stats_updated');
                
                return true;
            }

            throw new Exception($wpdb->last_error);

        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd aktualizacji statystyk', array(
                'error' => $e->getMessage(),
                'date' => $date,
                'data' => $data
            ));
            return false;
        }
    }
    
    /**
     * Wyczyść cache statystyk
     */
    public function clear_stats_cache() {
        wp_cache_flush_group($this->cache_group);
        $this->logger->log('info', 'Wyczyszczono cache statystyk');
    }
    
    /**
     * Pobierz statystyki używalności funkcji
     * 
     * @param int $limit Limit wyników
     * @return array
     */
    public function get_feature_usage_stats($limit = 10) {
        global $wpdb;
        
        // Pobierz dane z tabeli logów
        $sql = $wpdb->prepare(
            "SELECT action, COUNT(*) as count 
            FROM {$wpdb->prefix}seo_logs 
            WHERE level = 'info' 
            GROUP BY action 
            ORDER BY count DESC 
            LIMIT %d",
            $limit
        );
        
        $stats = $wpdb->get_results($sql);
        
        // Jeśli wystąpił błąd, zaloguj go
        if ($stats === null) {
            $this->logger->log('error', 'Błąd podczas pobierania statystyk używalności funkcji', array(
                'error' => $wpdb->last_error
            ));
            return array();
        }
        
        return $stats;
    }
    
    /**
     * Eksportuj statystyki do CSV
     * 
     * @param string $start_date Data początkowa (Y-m-d)
     * @param string $end_date Data końcowa (Y-m-d)
     * @return bool
     */
    public function export_stats_csv($start_date = null, $end_date = null) {
        // Pobierz statystyki
        $stats = $this->get_stats($start_date, $end_date, false);
        
        if (empty($stats)) {
            return false;
        }
        
        // Przygotuj nazwę pliku
        $filename = 'cleanseo-stats-' . date('Ymd-His') . '.csv';
        
        // Ustawienia nagłówków
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // Otwórz strumień wyjściowy
        $output = fopen('php://output', 'w');
        
        // Nagłówki CSV
        fputcsv($output, array(
            'Data',
            'Odsłony',
            'Unikalni odwiedzający',
            'Współczynnik odrzuceń',
            'Średni czas na stronie (s)',
            'Data utworzenia',
            'Data aktualizacji'
        ));
        
        // Dane
        foreach ($stats as $row) {
            fputcsv($output, array(
                $row->date,
                $row->page_views,
                $row->unique_visitors,
                $row->bounce_rate,
                $row->avg_time_on_site,
                $row->created_at,
                $row->updated_at
            ));
        }
        
        fclose($output);
        
        $this->logger->log('info', 'Wyeksportowano statystyki do CSV', array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'count' => count($stats)
        ));
        
        return true;
    }
}