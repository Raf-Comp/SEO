<?php

/**
 * Klasa odpowiedzialna za przetwarzanie zadań AI w tle
 */
class CleanSEO_AI_Background_Process {
    private $queue;
    private $api;
    private $content;
    private $logger;
    private $settings;
    private $is_processing = false;
    private $lock_key = 'cleanseo_queue_lock';
    private $lock_timeout = 300; // 5 minut
    private $max_attempts = 3;
    private $batch_size = 5; // Liczba zadań przetwarzanych w jednym cyklu
    private $processing_time_limit = 60; // Limit czasu przetwarzania w sekundach

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->queue = new CleanSEO_AI_Queue();
        $this->api = new CleanSEO_AI_API();
        $this->content = new CleanSEO_AI_Content();
        $this->logger = new CleanSEO_Logger('background_process');
        
        // Pobierz ustawienia z opcji
        $options = get_option('cleanseo_options', array());
        $this->settings = $options['background_process'] ?? array();
        
        // Ustawienia z opcji
        $this->max_attempts = isset($this->settings['max_attempts']) ? (int)$this->settings['max_attempts'] : 3;
        $this->batch_size = isset($this->settings['batch_size']) ? (int)$this->settings['batch_size'] : 5;
        $this->processing_time_limit = isset($this->settings['time_limit']) ? (int)$this->settings['time_limit'] : 60;
        $this->lock_timeout = isset($this->settings['lock_timeout']) ? (int)$this->settings['lock_timeout'] : 300;
        
        // Dodaj akcje
        add_action('init', array($this, 'init'));
        add_action('cleanseo_process_queue', array($this, 'process_queue'));
        add_action('wp_ajax_cleanseo_process_queue', array($this, 'ajax_process_queue'));
        add_action('wp_ajax_cleanseo_clear_queue', array($this, 'ajax_clear_queue'));
        add_action('wp_ajax_cleanseo_retry_failed_jobs', array($this, 'ajax_retry_failed_jobs'));
        add_action('wp_ajax_cleanseo_get_queue_status', array($this, 'ajax_get_queue_status'));
    }

    /**
     * Inicjalizacja zaplanowanych zadań
     */
    public function init() {
        // Sprawdź czy mamy zaplanowane zadanie, jeśli nie - dodaj je
        if (!wp_next_scheduled('cleanseo_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'cleanseo_process_queue');
        }
        
        // Dodaj własne interwały czasowe
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Dodaje własne interwały czasowe dla WP Cron
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Co minutę', 'cleanseo-optimizer')
        );
        
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Co 5 minut', 'cleanseo-optimizer')
        );
        
        return $schedules;
    }

    /**
     * Przetwarza kolejkę zadań
     */
    public function process_queue() {
        // Sprawdź czy proces już jest uruchomiony
        if ($this->is_processing || get_transient($this->lock_key)) {
            $this->logger->log('info', 'Kolejka jest już przetwarzana przez inny proces');
            return;
        }

        // Ustaw blokadę
        $this->is_processing = true;
        set_transient($this->lock_key, time(), $this->lock_timeout);
        
        $this->logger->log('info', 'Rozpoczęto przetwarzanie kolejki zadań AI');
        
        // Zapisz czas rozpoczęcia
        $start_time = time();
        $processed_count = 0;
        
        // Pobierz limit pakietu zadań
        $jobs = $this->queue->get_batch($this->batch_size);
        
        while (!empty($jobs) && time() - $start_time < $this->processing_time_limit && $processed_count < $this->batch_size) {
            foreach ($jobs as $job) {
                // Sprawdź czy nie przekroczyliśmy limitu czasu
                if (time() - $start_time >= $this->processing_time_limit) {
                    $this->logger->log('warning', 'Przerwano przetwarzanie z powodu limitu czasu', array(
                        'time_elapsed' => time() - $start_time,
                        'time_limit' => $this->processing_time_limit
                    ));
                    break;
                }
                
                // Przetwórz zadanie
                $this->process_job($job);
                $processed_count++;
            }
            
            // Pobierz następną partię zadań, jeśli jeszcze jest czas
            if (time() - $start_time < $this->processing_time_limit && $processed_count < $this->batch_size) {
                $jobs = $this->queue->get_batch($this->batch_size - $processed_count);
            } else {
                break;
            }
        }
        
        // Usuń blokadę
        delete_transient($this->lock_key);
        $this->is_processing = false;
        
        $this->logger->log('info', 'Zakończono przetwarzanie kolejki zadań AI', array(
            'processed_jobs' => $processed_count,
            'time_elapsed' => time() - $start_time
        ));
    }
    
    /**
     * Przetwarza pojedyncze zadanie
     */
    private function process_job($job) {
        $job_id = $job->id;
        $data = maybe_unserialize($job->data);
        
        // Aktualizuj status zadania na 'processing'
        $this->queue->mark_processing($job_id);
        
        $this->logger->log('info', 'Przetwarzanie zadania', array(
            'job_id' => $job_id,
            'type' => $job->type,
            'attempts' => $job->attempts
        ));
        
        try {
            $result = null;
            
            // Przetwarzaj różne typy zadań
            switch ($job->type) {
                case 'generate_meta_title':
                    $result = $this->content->generate_meta_title($data['post_id'], $data['keywords'] ?? '');
                    break;
                    
                case 'generate_meta_description':
                    $result = $this->content->generate_meta_description($data['post_id'], $data['keywords'] ?? '');
                    break;
                    
                case 'generate_content':
                    $result = $this->content->generate_content(
                        $data['topic'],
                        $data['keywords'] ?? '',
                        $data['length'] ?? 'medium',
                        $data['post_id'] ?? null
                    );
                    break;
                    
                case 'analyze_content':
                    $result = $this->content->analyze_content($data['post_id'], $data['keywords'] ?? '');
                    break;
                    
                case 'keyword_research':
                    $result = $this->api->research_keywords($data['topic']);
                    
                    // Zapisz wyniki do posta lub opcji
                    if (!is_wp_error($result) && isset($data['post_id'])) {
                        update_post_meta($data['post_id'], '_cleanseo_keyword_research', $result);
                    }
                    break;
                    
                case 'optimize_content':
                    $post_id = $data['post_id'] ?? 0;
                    $keywords = $data['keywords'] ?? '';
                    $options = $data['options'] ?? array();
                    
                    // Pobierz treść posta
                    $post = get_post($post_id);
                    if (!$post) {
                        throw new Exception('Post nie istnieje');
                    }
                    
                    $result = $this->api->optimize_content($post->post_content, $keywords, $options);
                    
                    // Zaktualizuj treść posta, jeśli ustawiono auto-update
                    if (!is_wp_error($result) && isset($data['auto_update']) && $data['auto_update']) {
                        wp_update_post(array(
                            'ID' => $post_id,
                            'post_content' => $result
                        ));
                    }
                    break;
                    
                case 'generate_faq':
                    $result = $this->api->generate_faq($data['topic'], $data['count'] ?? 5);
                    
                    // Zapisz wyniki do posta lub opcji
                    if (!is_wp_error($result) && isset($data['post_id'])) {
                        update_post_meta($data['post_id'], '_cleanseo_faq_content', $result);
                    }
                    break;
                    
                case 'bulk_generate_meta':
                    // Generowanie meta danych dla wielu postów
                    $post_ids = $data['post_ids'] ?? array();
                    $keywords = $data['keywords'] ?? '';
                    $result = array();
                    
                    foreach ($post_ids as $post_id) {
                        $post_keywords = $keywords;
                        if (empty($post_keywords)) {
                            // Spróbuj pobrać słowa kluczowe z posta
                            $post_keywords = get_post_meta($post_id, '_cleanseo_keywords', true);
                        }
                        
                        // Generuj tytuł i opis
                        $title_result = $this->content->generate_meta_title($post_id, $post_keywords);
                        $desc_result = $this->content->generate_meta_description($post_id, $post_keywords);
                        
                        $result[$post_id] = array(
                            'title' => is_wp_error($title_result) ? $title_result->get_error_message() : $title_result,
                            'description' => is_wp_error($desc_result) ? $desc_result->get_error_message() : $desc_result
                        );
                    }
                    break;
                    
                default:
                    // Nieznany typ zadania
                    $error_message = sprintf('Nieznany typ zadania: %s', $job->type);
                    $this->logger->log('error', $error_message, array('job_id' => $job_id));
                    $this->queue->mark_failed($job_id, $error_message);
                    return;
            }
            
            // Obsługa wyniku
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
                $this->logger->log('error', 'Błąd podczas przetwarzania zadania', array(
                    'job_id' => $job_id,
                    'error' => $error_message,
                    'attempts' => $job->attempts + 1
                ));
                
                $this->queue->increment_attempts($job_id, $error_message);
                
                // Sprawdź czy przekroczono maksymalną liczbę prób
                if ($job->attempts + 1 >= $this->max_attempts) {
                    $this->queue->mark_failed($job_id, 'Przekroczono maksymalną liczbę prób: ' . $error_message);
                }
            } else {
                // Zapisz wynik i oznacz zadanie jako zakończone
                $this->queue->mark_done($job_id, $result);
                
                $this->logger->log('info', 'Zadanie zakończone sukcesem', array(
                    'job_id' => $job_id,
                    'type' => $job->type
                ));
                
                // Wykonaj hooki po ukończeniu zadania
                do_action('cleanseo_job_completed', $job->type, $job_id, $data, $result);
                do_action("cleanseo_job_completed_{$job->type}", $job_id, $data, $result);
            }

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->logger->log('error', 'Wyjątek podczas przetwarzania zadania', array(
                'job_id' => $job_id,
                'exception' => $error_message,
                'attempts' => $job->attempts + 1
            ));
            
            $this->queue->increment_attempts($job_id, $error_message);
            
            // Sprawdź czy przekroczono maksymalną liczbę prób
            if ($job->attempts + 1 >= $this->max_attempts) {
                $this->queue->mark_failed($job_id, 'Wyjątek: ' . $error_message);
            }
        }
    }

    /**
     * Obsługuje żądanie AJAX do ręcznego przetworzenia kolejki
     */
    public function ajax_process_queue() {
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('cleanseo_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Brak uprawnień lub nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')
            ));
        }

        // Sprawdź czy proces już jest uruchomiony
        if (get_transient($this->lock_key)) {
            $locked_since = get_transient($this->lock_key);
            $time_passed = time() - $locked_since;
            
            // Jeśli blokada jest starsza niż limit, wymuś przetwarzanie
            if (isset($_POST['force']) && $_POST['force'] && $time_passed > 60) {
                delete_transient($this->lock_key);
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Kolejka jest już przetwarzana od %d sekund. Możesz wymusić przetwarzanie, jeśli uważasz, że poprzedni proces się zawiesił.', 'cleanseo-optimizer'),
                        $time_passed
                    ),
                    'locked_since' => $time_passed
                ));
            }
        }

        // Uruchom przetwarzanie
        $this->process_queue();
        
        // Pobierz statystyki kolejki
        $stats = $this->queue->get_stats();
        
        wp_send_json_success(array(
            'message' => __('Kolejka została przetworzona.', 'cleanseo-optimizer'),
            'stats' => $stats
        ));
    }
    
    /**
     * Obsługuje żądanie AJAX do czyszczenia kolejki
     */
    public function ajax_clear_queue() {
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('cleanseo_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Brak uprawnień lub nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')
            ));
        }
        
        // Określ typ zadań do wyczyszczenia
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        // Wyczyść kolejkę
        $count = $this->queue->clear_queue($type);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Wyczyszczono %d zadań z kolejki.', 'cleanseo-optimizer'), $count),
            'cleared_count' => $count
        ));
    }
    
    /**
     * Obsługuje żądanie AJAX do ponownego dodania nieudanych zadań do kolejki
     */
    public function ajax_retry_failed_jobs() {
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('cleanseo_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Brak uprawnień lub nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')
            ));
        }
        
        // Pobierz ID zadań do ponowienia
        $job_ids = isset($_POST['job_ids']) ? array_map('intval', (array)$_POST['job_ids']) : array();
        
        // Jeśli nie podano konkretnych ID, ponów wszystkie nieudane zadania
        $count = 0;
        if (empty($job_ids)) {
            $count = $this->queue->retry_failed_jobs();
        } else {
            foreach ($job_ids as $job_id) {
                if ($this->queue->retry_job($job_id)) {
                    $count++;
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Ponownie dodano %d nieudanych zadań do kolejki.', 'cleanseo-optimizer'), $count),
            'retried_count' => $count
        ));
    }
    
    /**
     * Obsługuje żądanie AJAX do pobrania statusu kolejki
     */
    public function ajax_get_queue_status() {
        // Sprawdź uprawnienia i nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('cleanseo_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Brak uprawnień lub nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')
            ));
        }
        
        // Pobierz statystyki kolejki
        $stats = $this->queue->get_stats();
        
        // Sprawdź czy kolejka jest aktualnie przetwarzana
        $is_locked = get_transient($this->lock_key);
        $locked_since = $is_locked ? time() - $is_locked : 0;
        
        // Pobierz ostatnie zadania
        $recent_jobs = $this->queue->get_recent_jobs(10);
        
        wp_send_json_success(array(
            'stats' => $stats,
            'is_processing' => $is_locked !== false,
            'locked_since' => $locked_since,
            'recent_jobs' => $recent_jobs
        ));
    }
    
    /**
     * Dodaje zadanie do kolejki
     */
    public function add_job($type, $data, $priority = 10) {
        return $this->queue->add_job($type, $data, $priority);
    }
    
    /**
     * Dodaje wiele zadań do kolejki
     */
    public function add_jobs($jobs) {
        return $this->queue->add_jobs($jobs);
    }
    
    /**
     * Usuwa zaplanowane zadania przy deaktywacji wtyczki
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('cleanseo_process_queue');
    }
}