<?php

/**
 * Klasa odpowiedzialna za kolejkowanie zadań AI
 */
class CleanSEO_AI_Queue {
    /**
     * Obiekt bazy danych WordPress
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Nazwa tabeli kolejki
     * @var string
     */
    private $queue_table;
    
    /**
     * Rozmiar paczki zadań
     * @var int
     */
    private $batch_size = 10;
    
    /**
     * Maksymalna liczba prób
     * @var int
     */
    private $max_attempts = 3;
    
    /**
     * Obiekt loggerа
     * @var CleanSEO_Logger
     */
    private $logger;
    
    /**
     * Nazwa blokady procesora kolejki
     * @var string
     */
    private $lock_name = 'cleanseo_queue_processor_lock';
    
    /**
     * Czas ważności blokady w sekundach
     * @var int
     */
    private $lock_timeout = 300; // 5 minut
    
    /**
     * Statusy zadań
     * @var array
     */
    private $statuses = array(
        'pending' => 'Oczekujące',
        'processing' => 'Przetwarzane',
        'done' => 'Zakończone',
        'failed' => 'Nieudane',
        'cancelled' => 'Anulowane',
        'delayed' => 'Opóźnione',
        'scheduled' => 'Zaplanowane'
    );

    /**
     * Konstruktor
     * 
     * @param CleanSEO_Logger $logger Opcjonalny obiekt loggera
     */
    public function __construct($logger = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->queue_table = $wpdb->prefix . 'seo_ai_jobs';
        
        // Inicjalizacja loggera
        $this->logger = $logger ?: new CleanSEO_Logger('ai_queue');
        
        // Pobierz ustawienia z opcji
        $options = get_option('cleanseo_queue_settings', array());
        
        // Ustaw parametry z opcji
        if (isset($options['batch_size']) && $options['batch_size'] > 0) {
            $this->batch_size = intval($options['batch_size']);
        }
        
        if (isset($options['max_attempts']) && $options['max_attempts'] > 0) {
            $this->max_attempts = intval($options['max_attempts']);
        }
        
        if (isset($options['lock_timeout']) && $options['lock_timeout'] > 0) {
            $this->lock_timeout = intval($options['lock_timeout']);
        }
    }

    /**
     * Dodaje zadanie do kolejki
     * 
     * @param string $type Typ zadania
     * @param mixed $data Dane zadania
     * @param int $priority Priorytet (wyższy = ważniejszy)
     * @param string $status Status początkowy (domyślnie: pending)
     * @param DateTime|null $scheduled_at Czas zaplanowania (opcjonalnie)
     * @return int|false ID zadania lub false w przypadku błędu
     */
    public function add_job($type, $data, $priority = 10, $status = 'pending', $scheduled_at = null) {
        // Walidacja typu zadania
        if (empty($type) || !is_string($type)) {
            $this->logger->error('Próba dodania zadania z nieprawidłowym typem', array(
                'type' => $type,
                'data' => $data
            ));
            return false;
        }
        
        // Walidacja statusu
        if (!array_key_exists($status, $this->statuses)) {
            $status = 'pending';
        }
        
        // Walidacja priorytetu
        $priority = absint($priority);
        
        // Walidacja czasu zaplanowania
        $scheduled_time = null;
        if ($scheduled_at !== null) {
            if ($scheduled_at instanceof DateTime) {
                $scheduled_time = $scheduled_at->format('Y-m-d H:i:s');
                // Jeśli czas zaplanowania jest w przyszłości, ustaw status na 'scheduled'
                if ($scheduled_at > new DateTime()) {
                    $status = 'scheduled';
                }
            } elseif (is_string($scheduled_at)) {
                $scheduled_time = $scheduled_at;
                // Sprawdź czy czas jest w przyszłości
                if (strtotime($scheduled_at) > time()) {
                    $status = 'scheduled';
                }
            }
        }
        
        // Przygotuj dane zadania
        $job_data = array(
            'type' => sanitize_text_field($type),
            'data' => maybe_serialize($data),
            'status' => $status,
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => $this->max_attempts,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj czas zaplanowania jeśli podano
        if ($scheduled_time !== null) {
            $job_data['scheduled_at'] = $scheduled_time;
        }
        
        // Dodaj zadanie do bazy danych
        $result = $this->wpdb->insert($this->queue_table, $job_data);
        
        if ($result === false) {
            $this->logger->error('Nie udało się dodać zadania do kolejki', array(
                'type' => $type,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }
        
        $job_id = $this->wpdb->insert_id;
        
        $this->logger->info('Dodano zadanie do kolejki', array(
            'job_id' => $job_id,
            'type' => $type,
            'priority' => $priority,
            'status' => $status,
            'scheduled_at' => $scheduled_time
        ));
        
        return $job_id;
    }
    
    /**
     * Dodaje wiele zadań do kolejki
     * 
     * @param array $jobs Tablica zadań
     * @return array Tablica ID zadań
     */
    public function add_jobs($jobs) {
        if (!is_array($jobs) || empty($jobs)) {
            return array();
        }
        
        $job_ids = array();
        
        foreach ($jobs as $job) {
            // Sprawdź czy zadanie zawiera wymagane pola
            if (!isset($job['type']) || empty($job['type'])) {
                continue;
            }
            
            // Przygotuj parametry
            $type = $job['type'];
            $data = isset($job['data']) ? $job['data'] : array();
            $priority = isset($job['priority']) ? absint($job['priority']) : 10;
            $status = isset($job['status']) && array_key_exists($job['status'], $this->statuses) ? $job['status'] : 'pending';
            $scheduled_at = isset($job['scheduled_at']) ? $job['scheduled_at'] : null;
            
            // Dodaj zadanie
            $job_id = $this->add_job($type, $data, $priority, $status, $scheduled_at);
            
            if ($job_id) {
                $job_ids[] = $job_id;
            }
        }
        
        return $job_ids;
    }

    /**
     * Pobiera następne zadanie z kolejki
     * 
     * @return object|null Zadanie lub null jeśli brak
     */
    public function get_next() {
        // Pobierz najpierw zaplanowane zadania, których czas już nadszedł
        $scheduled_job = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE status = %s AND scheduled_at <= %s
            ORDER BY priority DESC, scheduled_at ASC
            LIMIT 1
        ", 'scheduled', current_time('mysql')));
        
        if ($scheduled_job) {
            // Aktualizuj status zadania
            $this->wpdb->update(
                $this->queue_table,
                array('status' => 'processing', 'started_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('id' => $scheduled_job->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            $this->logger->info('Pobrano zaplanowane zadanie z kolejki', array(
                'job_id' => $scheduled_job->id,
                'type' => $scheduled_job->type
            ));
            
            return $scheduled_job;
        }
        
        // Jeśli nie ma zaplanowanych zadań, pobierz oczekujące
        $pending_job = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE status = %s AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC
            LIMIT 1
        ", 'pending'));
        
        if ($pending_job) {
            // Aktualizuj status zadania
            $this->wpdb->update(
                $this->queue_table,
                array('status' => 'processing', 'started_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('id' => $pending_job->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            $this->logger->info('Pobrano oczekujące zadanie z kolejki', array(
                'job_id' => $pending_job->id,
                'type' => $pending_job->type
            ));
            
            return $pending_job;
        }
        
        return null;
    }
    
    /**
     * Pobiera partię zadań z kolejki
     * 
     * @param int $limit Maksymalna liczba zadań
     * @return array Tablica zadań
     */
    public function get_batch($limit = null) {
        if ($limit === null) {
            $limit = $this->batch_size;
        }
        
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 1;
        }
        
        // Pobierz najpierw zaplanowane zadania, których czas już nadszedł
        $scheduled_jobs = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE status = %s AND scheduled_at <= %s
            ORDER BY priority DESC, scheduled_at ASC
            LIMIT %d
        ", 'scheduled', current_time('mysql'), $limit));
        
        $scheduled_count = count($scheduled_jobs);
        
        // Jeśli nie uzyskano pełnej partii, pobierz również oczekujące zadania
        if ($scheduled_count < $limit) {
            $pending_jobs = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT * FROM {$this->queue_table}
                WHERE status = %s AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT %d
            ", 'pending', $limit - $scheduled_count));
            
            // Połącz obie tablice
            $jobs = array_merge($scheduled_jobs, $pending_jobs);
        } else {
            $jobs = $scheduled_jobs;
        }
        
        // Aktualizuj statusy wszystkich pobranych zadań
        foreach ($jobs as $job) {
            $this->wpdb->update(
                $this->queue_table,
                array('status' => 'processing', 'started_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                array('id' => $job->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
        
        $this->logger->info('Pobrano partię zadań z kolejki', array(
            'count' => count($jobs),
            'scheduled' => $scheduled_count,
            'pending' => count($jobs) - $scheduled_count
        ));
        
        return $jobs;
    }

    /**
     * Oznacza zadanie jako zakończone
     * 
     * @param int $id ID zadania
     * @param mixed $result Wynik zadania (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function mark_done($id, $result = null) {
        $data = array(
            'status' => 'done', 
            'completed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj wynik jeśli podano
        if ($result !== null) {
            $data['result'] = maybe_serialize($result);
        }
        
        $success = $this->wpdb->update(
            $this->queue_table,
            $data,
            array('id' => intval($id)),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->info('Oznaczono zadanie jako zakończone', array('job_id' => $id));
        } else {
            $this->logger->error('Nie udało się oznaczyć zadania jako zakończone', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }

    /**
     * Oznacza zadanie jako nieudane
     * 
     * @param int $id ID zadania
     * @param string $error_message Komunikat błędu (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function mark_failed($id, $error_message = '') {
        $data = array(
            'status' => 'failed', 
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj komunikat błędu jeśli podano
        if (!empty($error_message)) {
            $data['error_message'] = sanitize_text_field($error_message);
        }
        
        $success = $this->wpdb->update(
            $this->queue_table,
            $data,
            array('id' => intval($id)),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->warning('Oznaczono zadanie jako nieudane', array(
                'job_id' => $id,
                'error_message' => $error_message
            ));
        } else {
            $this->logger->error('Nie udało się oznaczyć zadania jako nieudane', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }
    
    /**
     * Oznacza zadanie jako przetwarzane
     * 
     * @param int $id ID zadania
     * @return bool Czy operacja się powiodła
     */
    public function mark_processing($id) {
        $success = $this->wpdb->update(
            $this->queue_table,
            array(
                'status' => 'processing', 
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($id)),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->info('Oznaczono zadanie jako przetwarzane', array('job_id' => $id));
        } else {
            $this->logger->error('Nie udało się oznaczyć zadania jako przetwarzane', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }
    
    /**
     * Oznacza zadanie jako anulowane
     * 
     * @param int $id ID zadania
     * @param string $reason Powód anulowania (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function mark_cancelled($id, $reason = '') {
        $data = array(
            'status' => 'cancelled', 
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj powód anulowania jeśli podano
        if (!empty($reason)) {
            $data['error_message'] = sanitize_text_field($reason);
        }
        
        $success = $this->wpdb->update(
            $this->queue_table,
            $data,
            array('id' => intval($id)),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->info('Oznaczono zadanie jako anulowane', array(
                'job_id' => $id,
                'reason' => $reason
            ));
        } else {
            $this->logger->error('Nie udało się oznaczyć zadania jako anulowane', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }
    
    /**
     * Oznacza zadanie jako opóźnione
     * 
     * @param int $id ID zadania
     * @param DateTime|string $retry_at Czas ponowienia
     * @param string $reason Powód opóźnienia (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function mark_delayed($id, $retry_at, $reason = '') {
        // Walidacja czasu ponowienia
        $retry_time = null;
        if ($retry_at instanceof DateTime) {
            $retry_time = $retry_at->format('Y-m-d H:i:s');
        } elseif (is_string($retry_at)) {
            $retry_time = $retry_at;
        } else {
            // Domyślnie opóźnij o 5 minut
            $retry_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        }
        
        $data = array(
            'status' => 'delayed', 
            'scheduled_at' => $retry_time,
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj powód opóźnienia jeśli podano
        if (!empty($reason)) {
            $data['error_message'] = sanitize_text_field($reason);
        }
        
        $success = $this->wpdb->update(
            $this->queue_table,
            $data,
            array('id' => intval($id)),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->info('Oznaczono zadanie jako opóźnione', array(
                'job_id' => $id,
                'retry_at' => $retry_time,
                'reason' => $reason
            ));
        } else {
            $this->logger->error('Nie udało się oznaczyć zadania jako opóźnione', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }

    /**
     * Zwiększa licznik prób zadania
     * 
     * @param int $id ID zadania
     * @param string $error_message Komunikat błędu (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function increment_attempts($id, $error_message = '') {
        // Pobierz aktualne informacje o zadaniu
        $job = $this->get_job($id);
        if (!$job) {
            return false;
        }
        
        $data = array(
            'attempts' => $job->attempts + 1,
            'status' => 'pending', // Resetuj status na "oczekujący"
            'updated_at' => current_time('mysql')
        );
        
        // Dodaj komunikat błędu jeśli podano
        if (!empty($error_message)) {
            $data['error_message'] = sanitize_text_field($error_message);
        }
        
        // Jeśli osiągnięto maksymalną liczbę prób, oznacz jako nieudane
        if ($job->attempts + 1 >= $job->max_attempts) {
            return $this->mark_failed($id, $error_message ?: __('Przekroczono maksymalną liczbę prób', 'cleanseo-optimizer'));
        }
        
        $success = $this->wpdb->update(
            $this->queue_table,
            $data,
            array('id' => intval($id)),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($success) {
            $this->logger->warning('Zwiększono licznik prób zadania', array(
                'job_id' => $id,
                'attempts' => $job->attempts + 1,
                'max_attempts' => $job->max_attempts,
                'error_message' => $error_message
            ));
        } else {
            $this->logger->error('Nie udało się zwiększyć licznika prób zadania', array(
                'job_id' => $id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $success !== false;
    }
    
    /**
     * Pobiera zadanie o określonym ID
     * 
     * @param int $id ID zadania
     * @return object|null Zadanie lub null
     */
    public function get_job($id) {
        return $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE id = %d
        ", intval($id)));
    }
    
    /**
     * Pobiera zadania według określonych kryteriów
     * 
     * @param array $args Argumenty zapytania
     * @return array Zadania
     */
    public function get_jobs($args = array()) {
        $defaults = array(
            'status' => null,
            'type' => null,
            'limit' => 20,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Przygotuj warunki
        $where_clauses = array();
        $where_values = array();
        
        if ($args['status'] !== null) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where_clauses[] = 'status IN (' . implode(', ', $placeholders) . ')';
                foreach ($args['status'] as $status) {
                    $where_values[] = $status;
                }
            } else {
                $where_clauses[] = 'status = %s';
                $where_values[] = $args['status'];
            }
        }
        
        if ($args['type'] !== null) {
            if (is_array($args['type'])) {
                $placeholders = array_fill(0, count($args['type']), '%s');
                $where_clauses[] = 'type IN (' . implode(', ', $placeholders) . ')';
                foreach ($args['type'] as $type) {
                    $where_values[] = $type;
                }
            } else {
                $where_clauses[] = 'type = %s';
                $where_values[] = $args['type'];
            }
        }
        
        // Walidacja parametrów sortowania
        $allowed_order_by = array('id', 'type', 'status', 'priority', 'attempts', 'created_at', 'updated_at', 'scheduled_at');
        if (!in_array($args['order_by'], $allowed_order_by)) {
            $args['order_by'] = 'created_at';
        }
        
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Przygotuj zapytanie
        $query = "SELECT * FROM {$this->queue_table}";
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $query .= " ORDER BY {$args['order_by']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        
        $where_values[] = absint($args['limit']);
        $where_values[] = absint($args['offset']);
        
        // Wykonaj zapytanie
        $prepared_query = $this->wpdb->prepare($query, $where_values);
        return $this->wpdb->get_results($prepared_query);
    }
    
    /**
     * Pobiera liczbę zadań według określonych kryteriów
     * 
     * @param array $args Argumenty zapytania
     * @return int Liczba zadań
     */
    public function count_jobs($args = array()) {
        $defaults = array(
            'status' => null,
            'type' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Przygotuj warunki
        $where_clauses = array();
        $where_values = array();
        
        if ($args['status'] !== null) {
            if (is_array($args['status'])) {
                $placeholders = array_fill(0, count($args['status']), '%s');
                $where_clauses[] = 'status IN (' . implode(', ', $placeholders) . ')';
                foreach ($args['status'] as $status) {
                    $where_values[] = $status;
                }
            } else {
                $where_clauses[] = 'status = %s';
                $where_values[] = $args['status'];
            }
        }
        
        if ($args['type'] !== null) {
            if (is_array($args['type'])) {
                $placeholders = array_fill(0, count($args['type']), '%s');
                $where_clauses[] = 'type IN (' . implode(', ', $placeholders) . ')';
                foreach ($args['type'] as $type) {
                    $where_values[] = $type;
                }
            } else {
                $where_clauses[] = 'type = %s';
                $where_values[] = $args['type'];
            }
        }
        
        // Przygotuj zapytanie
        $query = "SELECT COUNT(*) FROM {$this->queue_table}";
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Wykonaj zapytanie
        if (!empty($where_values)) {
            $prepared_query = $this->wpdb->prepare($query, $where_values);
            return (int) $this->wpdb->get_var($prepared_query);
        } else {
            return (int) $this->wpdb->get_var($query);
        }
    }
    
    /**
     * Pobiera statystyki kolejki
     * 
     * @return array Statystyki
     */
    public function get_stats() {
        $stats = array(
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'delayed' => 0,
            'scheduled' => 0,
            'by_type' => array(),
            'by_priority' => array(),
            'processing_time' => array(
                'avg' => 0,
                'min' => 0,
                'max' => 0
            )
        );
        
        // Pobierz całkowitą liczbę zadań
        $stats['total'] = $this->count_jobs();
        
        // Pobierz liczbę zadań według statusu
        foreach (array_keys($this->statuses) as $status) {
            $stats[$status] = $this->count_jobs(array('status' => $status));
        }
        
        // Pobierz liczbę zadań według typu
        $types_query = "SELECT type, COUNT(*) as count FROM {$this->queue_table} GROUP BY type ORDER BY count DESC";
        $types_results = $this->wpdb->get_results($types_query);
        
        foreach ($types_results as $type) {
            $stats['by_type'][$type->type] = (int) $type->count;
        }
        
        // Pobierz liczbę zadań według priorytetu
        $priority_query = "SELECT priority, COUNT(*) as count FROM {$this->queue_table} GROUP BY priority ORDER BY priority DESC";
        $priority_results = $this->wpdb->get_results($priority_query);
        
        foreach ($priority_results as $priority) {
            $stats['by_priority'][$priority->priority] = (int) $priority->count;
        }
        
        // Pobierz średni, minimalny i maksymalny czas przetwarzania
        $time_query = "SELECT 
            AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_time,
            MIN(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as min_time,
            MAX(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as max_time
            FROM {$this->queue_table} 
            WHERE status = 'done' AND started_at IS NOT NULL AND completed_at IS NOT NULL";
        
        $time_results = $this->wpdb->get_row($time_query);
        
        if ($time_results) {
            $stats['processing_time']['avg'] = round($time_results->avg_time, 2);
            $stats['processing_time']['min'] = (int) $time_results->min_time;
            $stats['processing_time']['max'] = (int) $time_results->max_time;
        }
        
        return $stats;
    }
    
   /**
     * Pobiera ostatnie zadania
     * 
     * @param int $limit Limit zadań
     * @return array Ostatnie zadania
     */
    public function get_recent_jobs($limit = 10) {
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 10;
        }
        
        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit);
        
        $jobs = $this->wpdb->get_results($query);
        
        // Przetwórz dane zadań
        if ($jobs) {
            foreach ($jobs as &$job) {
                if (isset($job->data) && !empty($job->data)) {
                    $job->data = maybe_unserialize($job->data);
                }
                
                if (isset($job->result) && !empty($job->result)) {
                    $job->result = maybe_unserialize($job->result);
                }
            }
        }
        
        return $jobs ?: array();
    }
    
    /**
     * Czyści kolejkę
     * 
     * @param string $type Typ zadań do wyczyszczenia (all, pending, failed, done)
     * @return int Liczba usuniętych zadań
     */
    public function clear_queue($type = 'all') {
        $where = '';
        $where_params = array();
        
        switch ($type) {
            case 'pending':
                $where = "WHERE status IN (%s, %s, %s)";
                $where_params = array('pending', 'scheduled', 'delayed');
                break;
            case 'failed':
                $where = "WHERE status = %s";
                $where_params = array('failed');
                break;
            case 'done':
                $where = "WHERE status = %s";
                $where_params = array('done');
                break;
            case 'processing':
                $where = "WHERE status = %s";
                $where_params = array('processing');
                break;
            case 'all':
            default:
                // Brak warunków = wszystkie zadania
                break;
        }
        
        // Wykonaj zapytanie
        if (!empty($where)) {
            $query = "DELETE FROM {$this->queue_table} " . $where;
            $result = $this->wpdb->query($this->wpdb->prepare($query, $where_params));
        } else {
            $result = $this->wpdb->query("DELETE FROM {$this->queue_table}");
        }
        
        $count = is_numeric($result) ? (int) $result : 0;
        
        $this->logger->info('Wyczyszczono kolejkę zadań', array(
            'type' => $type,
            'count' => $count
        ));
        
        return $count;
    }
    
    /**
     * Ponawia nieudane zadania
     * 
     * @param array $args Argumenty do filtrowania zadań
     * @return int Liczba ponowionych zadań
     */
    public function retry_failed_jobs($args = array()) {
        $defaults = array(
            'limit' => 100,
            'older_than' => null, // timestamp lub string (np. '1 day ago')
            'type' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Przygotuj warunki
        $where_clauses = array('status = %s');
        $where_values = array('failed');
        
        if ($args['older_than'] !== null) {
            $timestamp = is_numeric($args['older_than']) ? $args['older_than'] : strtotime($args['older_than']);
            if ($timestamp !== false) {
                $date = date('Y-m-d H:i:s', $timestamp);
                $where_clauses[] = 'updated_at < %s';
                $where_values[] = $date;
            }
        }
        
        if ($args['type'] !== null) {
            if (is_array($args['type'])) {
                $placeholders = array_fill(0, count($args['type']), '%s');
                $where_clauses[] = 'type IN (' . implode(', ', $placeholders) . ')';
                foreach ($args['type'] as $type) {
                    $where_values[] = $type;
                }
            } else {
                $where_clauses[] = 'type = %s';
                $where_values[] = $args['type'];
            }
        }
        
        // Przygotuj zapytanie
        $query = "SELECT id FROM {$this->queue_table} WHERE " . implode(' AND ', $where_clauses);
        $query .= " ORDER BY priority DESC, created_at ASC LIMIT %d";
        $where_values[] = absint($args['limit']);
        
        // Wykonaj zapytanie
        $job_ids = $this->wpdb->get_col($this->wpdb->prepare($query, $where_values));
        
        if (empty($job_ids)) {
            return 0;
        }
        
        // Ponów zadania
        $count = 0;
        foreach ($job_ids as $job_id) {
            if ($this->retry_job($job_id)) {
                $count++;
            }
        }
        
        $this->logger->info('Ponowiono nieudane zadania', array(
            'count' => $count,
            'args' => $args
        ));
        
        return $count;
    }
    
    /**
     * Ponawia konkretne zadanie
     * 
     * @param int $job_id ID zadania
     * @return bool Czy operacja się powiodła
     */
    public function retry_job($job_id) {
        // Pobierz zadanie
        $job = $this->get_job($job_id);
        if (!$job || $job->status !== 'failed') {
            return false;
        }
        
        // Resetuj licznik prób
        $result = $this->wpdb->update(
            $this->queue_table,
            array(
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $job_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result) {
            $this->logger->info('Ponowiono zadanie', array('job_id' => $job_id));
        } else {
            $this->logger->error('Nie udało się ponowić zadania', array(
                'job_id' => $job_id,
                'error' => $this->wpdb->last_error
            ));
        }
        
        return $result !== false;
    }
    
    /**
     * Anuluje zadanie
     * 
     * @param int $job_id ID zadania
     * @param string $reason Powód anulowania (opcjonalnie)
     * @return bool Czy operacja się powiodła
     */
    public function cancel_job($job_id, $reason = '') {
        // Pobierz zadanie
        $job = $this->get_job($job_id);
        if (!$job || !in_array($job->status, array('pending', 'scheduled', 'delayed'))) {
            return false;
        }
        
        return $this->mark_cancelled($job_id, $reason);
    }
    
    /**
     * Usuwa stare zadania
     * 
     * @param int $days_old Liczba dni, po których zadania są uznawane za stare
     * @return int Liczba usuniętych zadań
     */
    public function cleanup_old_jobs($days_old = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Usuń wszystkie zakończone i nieudane zadania starsze niż określona liczba dni
        $query = $this->wpdb->prepare("
            DELETE FROM {$this->queue_table}
            WHERE status IN ('done', 'failed', 'cancelled')
            AND updated_at < %s
        ", $cutoff_date);
        
        $result = $this->wpdb->query($query);
        $count = is_numeric($result) ? (int) $result : 0;
        
        $this->logger->info('Wyczyszczono stare zadania', array(
            'days_old' => $days_old,
            'cutoff_date' => $cutoff_date,
            'count' => $count
        ));
        
        return $count;
    }
    
    /**
     * Resetuje zawieszone zadania
     * 
     * @param int $minutes_old Liczba minut, po których zadanie jest uznawane za zawieszone
     * @return int Liczba zresetowanych zadań
     */
    public function reset_stuck_jobs($minutes_old = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$minutes_old} minutes"));
        
        // Znajdź wszystkie przetwarzane zadania, które nie zostały zaktualizowane od dłuższego czasu
        $query = $this->wpdb->prepare("
            SELECT id FROM {$this->queue_table}
            WHERE status = 'processing'
            AND updated_at < %s
        ", $cutoff_date);
        
        $job_ids = $this->wpdb->get_col($query);
        
        if (empty($job_ids)) {
            return 0;
        }
        
        // Resetuj statusy zadań
        $count = 0;
        foreach ($job_ids as $job_id) {
            // Pobierz zadanie, aby sprawdzić liczbę prób
            $job = $this->get_job($job_id);
            if (!$job) {
                continue;
            }
            
            // Jeśli zadanie ma już maksymalną liczbę prób, oznacz jako nieudane
            if ($job->attempts >= $job->max_attempts) {
                if ($this->mark_failed($job_id, __('Zadanie zawieszone - przekroczono maksymalną liczbę prób', 'cleanseo-optimizer'))) {
                    $count++;
                }
            } else {
                // W przeciwnym razie zwiększ licznik prób
                if ($this->increment_attempts($job_id, __('Zadanie zawieszone - automatyczny reset', 'cleanseo-optimizer'))) {
                    $count++;
                }
            }
        }
        
        $this->logger->info('Zresetowano zawieszone zadania', array(
            'minutes_old' => $minutes_old,
            'cutoff_date' => $cutoff_date,
            'count' => $count
        ));
        
        return $count;
    }
    
    /**
     * Sprawdza czy kolejka jest aktualnie blokowana
     * 
     * @return bool Czy kolejka jest blokowana
     */
    public function is_queue_locked() {
        $lock = get_transient($this->lock_name);
        return $lock !== false;
    }
    
    /**
     * Blokuje kolejkę przed przetwarzaniem
     * 
     * @return bool Czy operacja się powiodła
     */
    public function lock_queue() {
        // Sprawdź czy kolejka jest już zablokowana
        if ($this->is_queue_locked()) {
            return false;
        }
        
        // Ustaw blokadę
        $result = set_transient($this->lock_name, time(), $this->lock_timeout);
        
        if ($result) {
            $this->logger->info('Zablokowano kolejkę');
        }
        
        return $result;
    }
    
    /**
     * Odblokowuje kolejkę
     * 
     * @return bool Czy operacja się powiodła
     */
    public function unlock_queue() {
        $result = delete_transient($this->lock_name);
        
        if ($result) {
            $this->logger->info('Odblokowano kolejkę');
        }
        
        return $result;
    }
    
    /**
     * Sprawdza i naprawia uszkodzone zadania
     * 
     * @return int Liczba naprawionych zadań
     */
    public function check_and_repair() {
        // Pobierz wszystkie zadania z niespójnymi danymi
        $inconsistent_jobs = $this->wpdb->get_results("
            SELECT id, status, attempts, max_attempts
            FROM {$this->queue_table}
            WHERE 
                (status = 'processing' AND started_at IS NULL) OR
                (status = 'done' AND completed_at IS NULL) OR
                (attempts > max_attempts)
        ");
        
        if (empty($inconsistent_jobs)) {
            return 0;
        }
        
        $fixed_count = 0;
        
        foreach ($inconsistent_jobs as $job) {
            // Napraw niespójne dane
            if ($job->status === 'processing' && $job->started_at === null) {
                // Jeśli status to 'processing', ale nie ma daty rozpoczęcia, zresetuj na 'pending'
                $this->wpdb->update(
                    $this->queue_table,
                    array('status' => 'pending', 'updated_at' => current_time('mysql')),
                    array('id' => $job->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $fixed_count++;
            } elseif ($job->status === 'done' && $job->completed_at === null) {
                // Jeśli status to 'done', ale nie ma daty zakończenia, dodaj ją
                $this->wpdb->update(
                    $this->queue_table,
                    array('completed_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
                    array('id' => $job->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $fixed_count++;
            } elseif ($job->attempts > $job->max_attempts) {
                // Jeśli liczba prób przekracza maksimum, oznacz jako nieudane
                $this->wpdb->update(
                    $this->queue_table,
                    array('status' => 'failed', 'updated_at' => current_time('mysql')),
                    array('id' => $job->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $fixed_count++;
            }
        }
        
        $this->logger->info('Naprawiono uszkodzone zadania', array('count' => $fixed_count));
        
        return $fixed_count;
    }
    
    /**
     * Zapisuje ustawienia kolejki
     * 
     * @param array $settings Nowe ustawienia
     * @return bool Czy operacja się powiodła
     */
    public function save_settings($settings) {
        $current_settings = get_option('cleanseo_queue_settings', array());
        
        // Aktualizuj tylko podane ustawienia
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'batch_size':
                    $value = absint($value);
                    if ($value > 0) {
                        $current_settings['batch_size'] = $value;
                        $this->batch_size = $value;
                    }
                    break;
                    
                case 'max_attempts':
                    $value = absint($value);
                    if ($value > 0) {
                        $current_settings['max_attempts'] = $value;
                        $this->max_attempts = $value;
                    }
                    break;
                    
                case 'lock_timeout':
                    $value = absint($value);
                    if ($value > 0) {
                        $current_settings['lock_timeout'] = $value;
                        $this->lock_timeout = $value;
                    }
                    break;
                    
                default:
                    $current_settings[$key] = $value;
                    break;
            }
        }
        
        return update_option('cleanseo_queue_settings', $current_settings);
    }
    
    /**
     * Tworzy lub aktualizuje tabelę kolejki
     * 
     * @return bool Czy operacja się powiodła
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_ai_jobs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            data longtext NOT NULL,
            result longtext DEFAULT NULL,
            priority int(11) NOT NULL DEFAULT 10,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            error_message text DEFAULT NULL,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tworzenie lub aktualizacja tabeli
        $result = dbDelta($sql);
        
        // Sprawdź czy tabela została utworzona
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        return $table_exists;
    }
    
    /**
     * Przenosi zadania ze starej tabeli do nowej
     * 
     * @param string $old_table Nazwa starej tabeli
     * @return int Liczba przeniesionych zadań
     */
    public function migrate_from_old_table($old_table) {
        global $wpdb;
        $old_table_name = $wpdb->prefix . $old_table;
        
        // Sprawdź czy stara tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_name'") !== $old_table_name) {
            return 0;
        }
        
        // Pobierz zadania ze starej tabeli
        $old_jobs = $wpdb->get_results("SELECT * FROM $old_table_name");
        
        if (empty($old_jobs)) {
            return 0;
        }
        
        $count = 0;
        
        foreach ($old_jobs as $old_job) {
            // Przekształć stare zadanie na nowy format
            $new_job = array(
                'type' => isset($old_job->type) ? $old_job->type : 'unknown',
                'data' => isset($old_job->data) ? $old_job->data : serialize(array()),
                'status' => isset($old_job->status) ? $old_job->status : 'pending',
                'priority' => isset($old_job->priority) ? $old_job->priority : 10,
                'attempts' => isset($old_job->attempts) ? $old_job->attempts : 0,
                'max_attempts' => $this->max_attempts,
                'created_at' => isset($old_job->created_at) ? $old_job->created_at : current_time('mysql'),
                'updated_at' => isset($old_job->updated_at) ? $old_job->updated_at : current_time('mysql')
            );
            
            // Wstaw zadanie do nowej tabeli
            $result = $this->wpdb->insert($this->queue_table, $new_job);
            
            if ($result) {
                $count++;
            }
        }
        
        return $count;
    }
}