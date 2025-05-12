<?php
/**
 * Klasa odpowiedzialna za logowanie zdarzeń w pluginie
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Logger {
    private $log_table;
    private $max_log_size = 1000; // Maksymalna liczba wpisów w logu

    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'seo_logs';
    }

    /**
     * Dodaj wpis do logu
     *
     * @param string $type Typ wpisu (error, warning, info, success)
     * @param string $message Wiadomość
     * @param array $context Dodatkowe dane kontekstowe
     * @return bool|WP_Error
     */
    public function log($type, $message, $context = array()) {
        global $wpdb;

        try {
            // Sprawdź czy typ jest prawidłowy
            $valid_types = array('error', 'warning', 'info', 'success');
            if (!in_array($type, $valid_types)) {
                throw new Exception('Nieprawidłowy typ logu');
            }

            // Przygotuj dane do zapisu
            $data = array(
                'type' => $type,
                'message' => $message,
                'context' => maybe_serialize($context),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            );

            // Zapisz do bazy danych
            $result = $wpdb->insert($this->log_table, $data);
            if ($result === false) {
                throw new Exception('Błąd zapisu do bazy danych: ' . $wpdb->last_error);
            }

            // Wyczyść stare logi jeśli przekroczono limit
            $this->cleanup_old_logs();

            return true;

        } catch (Exception $e) {
            return new WP_Error('log_error', $e->getMessage());
        }
    }

    /**
     * Pobierz logi
     *
     * @param array $args Argumenty filtrowania
     * @return array|WP_Error
     */
    public function get_logs($args = array()) {
        global $wpdb;

        try {
            $defaults = array(
                'type' => null,
                'user_id' => null,
                'limit' => 100,
                'offset' => 0,
                'orderby' => 'created_at',
                'order' => 'DESC'
            );

            $args = wp_parse_args($args, $defaults);
            $where = array('1=1');
            $values = array();

            if ($args['type']) {
                $where[] = 'type = %s';
                $values[] = $args['type'];
            }

            if ($args['user_id']) {
                $where[] = 'user_id = %d';
                $values[] = $args['user_id'];
            }

            $sql = "SELECT * FROM {$this->log_table} WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];

            $logs = $wpdb->get_results($wpdb->prepare($sql, $values));
            if ($logs === null) {
                throw new Exception('Błąd pobierania logów: ' . $wpdb->last_error);
            }

            // Deserializuj kontekst
            foreach ($logs as &$log) {
                $log->context = maybe_unserialize($log->context);
            }

            return $logs;

        } catch (Exception $e) {
            return new WP_Error('get_logs_error', $e->getMessage());
        }
    }

    /**
     * Wyczyść stare logi
     */
    private function cleanup_old_logs() {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
        if ($count > $this->max_log_size) {
            $delete_count = $count - $this->max_log_size;
            $wpdb->query("DELETE FROM {$this->log_table} ORDER BY created_at ASC LIMIT {$delete_count}");
        }
    }

    /**
     * Pobierz adres IP klienta
     */
    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Wyczyść wszystkie logi
     */
    public function clear_logs() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->log_table}");
    }
} 