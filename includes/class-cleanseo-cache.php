<?php
/**
 * Klasa odpowiedzialna za obsługę cache'owania
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Cache {
    private $cache_table;
    private $cache_time;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->cache_table = $wpdb->prefix . 'seo_ai_cache';
        $this->cache_time = get_option('cleanseo_cache_time', 3600); // domyślnie 1 godzina
        $this->logger = new CleanSEO_Logger();
    }

    /**
     * Pobierz dane z cache
     *
     * @param string $key Klucz cache
     * @return mixed|false Dane z cache lub false jeśli nie znaleziono
     */
    public function get($key) {
        global $wpdb;

        $cache = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->cache_table} WHERE prompt = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $key,
            $this->cache_time
        ));

        if ($cache) {
            $this->logger->log('info', 'Pobrano dane z cache', array(
                'key' => $key,
                'age' => time() - strtotime($cache->created_at)
            ));
            return maybe_unserialize($cache->response);
        }

        return false;
    }

    /**
     * Zapisz dane do cache
     *
     * @param string $key Klucz cache
     * @param mixed $data Dane do zapisania
     * @param string $model Model AI
     * @return bool
     */
    public function set($key, $data, $model = 'default') {
        global $wpdb;

        try {
            $result = $wpdb->insert(
                $this->cache_table,
                array(
                    'model' => $model,
                    'prompt' => $key,
                    'response' => maybe_serialize($data),
                    'created_at' => current_time('mysql')
                )
            );

            if ($result) {
                $this->logger->log('info', 'Zapisano dane do cache', array(
                    'key' => $key,
                    'model' => $model
                ));
                return true;
            }

            throw new Exception($wpdb->last_error);

        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd zapisu do cache', array(
                'error' => $e->getMessage(),
                'key' => $key
            ));
            return false;
        }
    }

    /**
     * Wyczyść cache
     *
     * @param string $key Opcjonalny klucz do wyczyszczenia
     * @return bool
     */
    public function clear($key = null) {
        global $wpdb;

        try {
            if ($key) {
                $result = $wpdb->delete(
                    $this->cache_table,
                    array('prompt' => $key)
                );
            } else {
                $result = $wpdb->query("TRUNCATE TABLE {$this->cache_table}");
            }

            if ($result !== false) {
                $this->logger->log('info', 'Wyczyszczono cache', array(
                    'key' => $key
                ));
                return true;
            }

            throw new Exception($wpdb->last_error);

        } catch (Exception $e) {
            $this->logger->log('error', 'Błąd czyszczenia cache', array(
                'error' => $e->getMessage(),
                'key' => $key
            ));
            return false;
        }
    }

    /**
     * Ustaw czas cache'owania
     *
     * @param int $seconds Czas w sekundach
     */
    public function set_cache_time($seconds) {
        $this->cache_time = intval($seconds);
        update_option('cleanseo_cache_time', $this->cache_time);
    }

    /**
     * Pobierz czas cache'owania
     *
     * @return int Czas w sekundach
     */
    public function get_cache_time() {
        return $this->cache_time;
    }
} 