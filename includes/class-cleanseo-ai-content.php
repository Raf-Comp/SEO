<?php

/**
 * Klasa odpowiedzialna za generowanie i optymalizację treści AI
 */
class CleanSEO_AI_Content {
    /**
     * Instancja API AI
     * @var CleanSEO_AI_API
     */
    private $api;
    
    /**
     * Instancja ustawień
     * @var object
     */
    private $settings;
    
    /**
     * Instancja cache
     * @var CleanSEO_AI_Cache
     */
    private $cache;
    
    /**
     * Instancja loggera
     * @var CleanSEO_Logger
     */
    private $logger;
    
    /**
     * Typy treści obsługiwane przez generator
     * @var array
     */
    private $content_types = array(
        'meta_title',
        'meta_description',
        'content',
        'schema',
        'excerpt',
        'tags',
        'faq',
        'heading_ideas',
        'product_description',
        'image_alt',
        'social_media_post',
        'email_subject',
        'email_content'
    );
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->api = new CleanSEO_AI_API();
        
        // Załaduj ustawienia z opcji
        $options = get_option('cleanseo_options', array());
        $this->settings = isset($options['ai_content']) ? $options['ai_content'] : array();
        
        $this->cache = new CleanSEO_AI_Cache('content');
        $this->logger = new CleanSEO_Logger('ai_content');
        
        // Dodaj hooki do edytora WordPress
        $this->register_hooks();
    }
    
    /**
     * Rejestruje hooki WordPress
     */
    private function register_hooks() {
        // Rejestruj metaboksy dla edytora klasycznego
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        
        // Rejestruj akcje AJAX
        add_action('wp_ajax_cleanseo_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_cleanseo_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_cleanseo_optimize_content', array($this, 'ajax_optimize_content'));
        
        // Zapisz meta dane podczas zapisu posta
        add_action('save_post', array($this, 'save_post_meta'), 10, 2);
        
        // Dodaj przyciski do edytora Gutenberg
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_gutenberg_assets'));
    }
    
    /**
     * Rejestruje metaboksy dla edytora klasycznego
     */
    public function register_meta_boxes() {
        // Pobierz typy postów, dla których włączono funkcje AI
        $post_types = $this->get_enabled_post_types();
        
        if (empty($post_types)) {
            return;
        }
        
        // Dodaj metabox dla generowania treści AI
        add_meta_box(
            'cleanseo_ai_content',
            __('CleanSEO - Generator Treści AI', 'cleanseo-optimizer'),
            array($this, 'render_ai_content_metabox'),
            $post_types,
            'normal',
            'high'
        );
        
        // Dodaj metabox dla analizy SEO
        add_meta_box(
            'cleanseo_seo_analysis',
            __('CleanSEO - Analiza SEO', 'cleanseo-optimizer'),
            array($this, 'render_seo_analysis_metabox'),
            $post_types,
            'side',
            'high'
        );
    }
    
    /**
     * Renderuje metabox generatora treści AI
     * 
     * @param WP_Post $post Obiekt posta
     */
    public function render_ai_content_metabox($post) {
        // Tutaj wstawilibyśmy kod HTML dla metaboxa
        // W rzeczywistej implementacji ta funkcja byłaby znacznie dłuższa
        
        wp_nonce_field('cleanseo_ai_content_nonce', 'cleanseo_ai_content_nonce');
        
        // Pobierz zapisane meta dane
        $keywords = get_post_meta($post->ID, '_cleanseo_keywords', true);
        
        echo '<div class="cleanseo-ai-content-wrapper">';
        echo '<p><label for="cleanseo_keywords">' . __('Słowa kluczowe:', 'cleanseo-optimizer') . '</label>';
        echo '<input type="text" id="cleanseo_keywords" name="cleanseo_keywords" value="' . esc_attr($keywords) . '" class="large-text" /></p>';
        
        echo '<div class="cleanseo-ai-buttons">';
        echo '<button type="button" class="button button-primary" id="cleanseo-generate-title">' . __('Generuj Meta Tytuł', 'cleanseo-optimizer') . '</button> ';
        echo '<button type="button" class="button button-primary" id="cleanseo-generate-description">' . __('Generuj Meta Opis', 'cleanseo-optimizer') . '</button> ';
        echo '<button type="button" class="button button-primary" id="cleanseo-generate-content">' . __('Generuj Treść', 'cleanseo-optimizer') . '</button> ';
        echo '<button type="button" class="button" id="cleanseo-analyze-content">' . __('Analizuj Treść', 'cleanseo-optimizer') . '</button>';
        echo '</div>';
        
        echo '<div id="cleanseo-ai-results" class="cleanseo-ai-results" style="display:none;"></div>';
        echo '</div>';
        
        // Dodaj skrypt JS
        $this->enqueue_metabox_scripts();
    }
    
    /**
     * Renderuje metabox analizy SEO
     * 
     * @param WP_Post $post Obiekt posta
     */
    public function render_seo_analysis_metabox($post) {
        // Tutaj wstawilibyśmy kod HTML dla metaboxa analizy SEO
        
        $score = get_post_meta($post->ID, '_cleanseo_seo_score', true);
        $analysis = get_post_meta($post->ID, '_cleanseo_seo_analysis', true);
        
        echo '<div class="cleanseo-seo-analysis">';
        
        if (!empty($score)) {
            echo '<div class="cleanseo-seo-score score-' . esc_attr($this->get_score_class($score)) . '">';
            echo '<span class="score-number">' . esc_html($score) . '/100</span>';
            echo '<span class="score-label">' . esc_html($this->get_score_label($score)) . '</span>';
            echo '</div>';
        }
        
        echo '<button type="button" class="button" id="cleanseo-analyze-seo">' . __('Analizuj SEO', 'cleanseo-optimizer') . '</button>';
        
        if (!empty($analysis) && is_array($analysis)) {
            echo '<div class="cleanseo-seo-details">';
            foreach ($analysis as $category => $items) {
                echo '<h4>' . esc_html(ucfirst($category)) . '</h4>';
                echo '<ul class="cleanseo-' . esc_attr($category) . '-items">';
                foreach ($items as $item) {
                    echo '<li class="' . esc_attr($item['type']) . '">' . esc_html($item['message']) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Zapisuje meta dane podczas zapisu posta
     * 
     * @param int $post_id ID posta
     * @param WP_Post $post Obiekt posta
     */
    public function save_post_meta($post_id, $post) {
        // Sprawdź nonce
        if (!isset($_POST['cleanseo_ai_content_nonce']) || !wp_verify_nonce($_POST['cleanseo_ai_content_nonce'], 'cleanseo_ai_content_nonce')) {
            return;
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Zapisz słowa kluczowe
        if (isset($_POST['cleanseo_keywords'])) {
            update_post_meta($post_id, '_cleanseo_keywords', sanitize_text_field($_POST['cleanseo_keywords']));
        }
    }
    
    /**
     * Dołącza skrypty JavaScript dla metaboksów
     */
    private function enqueue_metabox_scripts() {
        wp_enqueue_script(
            'cleanseo-ai-content',
            plugins_url('assets/js/ai-content.js', dirname(__FILE__)),
            array('jquery'),
            CLEANSEO_VERSION,
            true
        );
        
        wp_localize_script('cleanseo-ai-content', 'cleanseoAiContent', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_ai_content_nonce'),
            'generating' => __('Generowanie...', 'cleanseo-optimizer'),
            'analyzing' => __('Analizowanie...', 'cleanseo-optimizer'),
            'error' => __('Wystąpił błąd:', 'cleanseo-optimizer')
        ));
    }
    
    /**
     * Dołącza zasoby dla edytora Gutenberg
     */
    public function enqueue_gutenberg_assets() {
        // Sprawdź czy aktualny ekran to edytor postów
        $screen = get_current_screen();
        $post_types = $this->get_enabled_post_types();
        
        if (!$screen || !in_array($screen->post_type, $post_types)) {
            return;
        }
        
        wp_enqueue_script(
            'cleanseo-gutenberg',
            plugins_url('assets/js/gutenberg.js', dirname(__FILE__)),
            array('wp-blocks', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-element'),
            CLEANSEO_VERSION,
            true
        );
        
        wp_localize_script('cleanseo-gutenberg', 'cleanseoGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_ai_content_nonce'),
            'generating' => __('Generowanie...', 'cleanseo-optimizer'),
            'analyzing' => __('Analizowanie...', 'cleanseo-optimizer'),
            'error' => __('Wystąpił błąd:', 'cleanseo-optimizer')
        ));
    }
    
    /**
     * Obsługuje żądanie AJAX do generowania treści
     */
    public function ajax_generate_content() {
        // Sprawdź nonce
        if (!check_ajax_referer('cleanseo_ai_content_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Brak uprawnień.', 'cleanseo-optimizer')));
        }
        
        // Pobierz parametry
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $length = isset($_POST['length']) ? sanitize_key($_POST['length']) : 'medium';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        
        // Sprawdź wymagane parametry
        if (empty($type) || empty($post_id)) {
            wp_send_json_error(array('message' => __('Brak wymaganych parametrów.', 'cleanseo-optimizer')));
        }
        
        // Generuj treść w zależności od typu
        switch ($type) {
            case 'meta_title':
                $result = $this->generate_meta_title($post_id, $keywords);
                break;
                
            case 'meta_description':
                $result = $this->generate_meta_description($post_id, $keywords);
                break;
                
            case 'content':
                $result = $this->generate_content($title, $keywords, $length, $post_id);
                break;
                
            case 'schema':
                $result = $this->generate_schema_markup($post_id, $keywords);
                break;
                
            case 'excerpt':
                $result = $this->generate_excerpt($post_id, $keywords);
                break;
                
            case 'tags':
                $result = $this->generate_tags($post_id, $keywords);
                break;
                
            case 'faq':
                $result = $this->generate_faq($post_id, $keywords);
                break;
                
            case 'heading_ideas':
                $result = $this->generate_heading_ideas($post_id, $keywords);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Nieobsługiwany typ treści.', 'cleanseo-optimizer')));
                break;
        }
        
        // Obsłuż wynik
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'content' => $result,
                'type' => $type
            ));
        }
    }
    
    /**
     * Obsługuje żądanie AJAX do analizy treści
     */
    public function ajax_analyze_content() {
        // Sprawdź nonce
        if (!check_ajax_referer('cleanseo_ai_content_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Brak uprawnień.', 'cleanseo-optimizer')));
        }
        
        // Pobierz parametry
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        
        // Sprawdź wymagane parametry
        if (empty($post_id) && empty($content)) {
            wp_send_json_error(array('message' => __('Brak wymaganych parametrów.', 'cleanseo-optimizer')));
        }
        
        // Jeśli nie podano treści, pobierz ją z posta
        if (empty($content) && !empty($post_id)) {
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('Nie znaleziono posta.', 'cleanseo-optimizer')));
            }
            $content = $post->post_content;
        }
        
        // Analizuj treść
        $result = $this->analyze_content($content, $keywords, $post_id);
        
        // Obsłuż wynik
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            // Zapisz wynik analizy w metadanych posta
            if (!empty($post_id)) {
                update_post_meta($post_id, '_cleanseo_seo_analysis', $result['details']);
                update_post_meta($post_id, '_cleanseo_seo_score', $result['score']);
            }
            
            wp_send_json_success($result);
        }
    }
    
    /**
     * Obsługuje żądanie AJAX do optymalizacji treści
     */
    public function ajax_optimize_content() {
        // Sprawdź nonce
        if (!check_ajax_referer('cleanseo_ai_content_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Nieprawidłowy token bezpieczeństwa.', 'cleanseo-optimizer')));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Brak uprawnień.', 'cleanseo-optimizer')));
        }
        
        // Pobierz parametry
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $options = isset($_POST['options']) ? (array)$_POST['options'] : array();
        
        // Sprawdź wymagane parametry
        if (empty($content) || empty($keywords)) {
            wp_send_json_error(array('message' => __('Brak wymaganych parametrów.', 'cleanseo-optimizer')));
        }
        
        // Optymalizuj treść
        $result = $this->optimize_content($content, $keywords, $options, $post_id);
        
        // Obsłuż wynik
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'content' => $result,
                'message' => __('Treść została zoptymalizowana.', 'cleanseo-optimizer')
            ));
        }
    }
    
    /**
     * Generuje meta tytuł dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return string|WP_Error Wygenerowany tytuł lub błąd
     */
    public function generate_meta_title($post_id, $keywords = '') {
        return $this->generate_from_prompt($post_id, 'meta_title', $keywords);
    }
    
    /**
     * Generuje meta opis dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return string|WP_Error Wygenerowany opis lub błąd
     */
    public function generate_meta_description($post_id, $keywords = '') {
        return $this->generate_from_prompt($post_id, 'meta_description', $keywords);
    }
    
    /**
     * Generuje schemat JSON-LD dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return string|WP_Error Wygenerowany schemat lub błąd
     */
    public function generate_schema_markup($post_id, $keywords = '') {
        return $this->generate_from_prompt($post_id, 'schema', $keywords);
    }
    
    /**
     * Generuje fragment treści dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return string|WP_Error Wygenerowany fragment lub błąd
     */
    public function generate_excerpt($post_id, $keywords = '') {
        return $this->generate_from_prompt($post_id, 'excerpt', $keywords);
    }
    
    /**
     * Generuje tagi dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return array|WP_Error Wygenerowane tagi lub błąd
     */
    public function generate_tags($post_id, $keywords = '') {
        $result = $this->generate_from_prompt($post_id, 'tags', $keywords);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Przetwórz wynik na tablicę tagów
        $tags = array_map('trim', explode(',', $result));
        
        return $tags;
    }
    
    /**
     * Generuje FAQ dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return string|WP_Error Wygenerowany FAQ lub błąd
     */
    public function generate_faq($post_id, $keywords = '') {
        return $this->generate_from_prompt($post_id, 'faq', $keywords);
    }
    
    /**
     * Generuje pomysły na nagłówki dla posta
     * 
     * @param int $post_id ID posta
     * @param string $keywords Słowa kluczowe
     * @return array|WP_Error Wygenerowane nagłówki lub błąd
     */
    public function generate_heading_ideas($post_id, $keywords = '') {
        $result = $this->generate_from_prompt($post_id, 'heading_ideas', $keywords);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Przetwórz wynik na tablicę nagłówków
        $headings = array();
        $lines = explode("\n", $result);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $headings[] = $line;
            }
        }
        
        return $headings;
    }
    
    /**
     * Generuje treść dla posta
     * 
     * @param string $title Tytuł lub temat
     * @param string $keywords Słowa kluczowe
     * @param string $length Długość treści (short, medium, long)
     * @param int $post_id ID posta (opcjonalnie)
     * @return string|WP_Error Wygenerowana treść lub błąd
     */
    public function generate_content($title, $keywords = '', $length = 'medium', $post_id = null) {
        if (empty($title)) {
            return new WP_Error('empty_title', __('Tytuł nie może być pusty.', 'cleanseo-optimizer'));
        }
        
        // Ustaw domyślną długość
        if (!in_array($length, array('short', 'medium', 'long'))) {
            $length = 'medium';
        }
        
        // Przygotuj prompt
        $template = $this->get_prompt_template('content');
        if (empty($template)) {
            return new WP_Error('missing_template', __('Brak szablonu promptu.', 'cleanseo-optimizer'));
        }
        
        $prompt = str_replace(
            array('{title}', '{topic}', '{keywords}', '{length}'),
            array($title, $title, $keywords, $length),
            $template
        );
        
        // Generuj unikalny klucz cache
        $cache_key = "content_{$length}_" . md5($title . $keywords);
        
        // Sprawdź cache
        $cached = $this->cache->get($cache_key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Ustaw parametry w zależności od długości
        $max_tokens = array(
            'short' => 500,
            'medium' => 1500,
            'long' => 3000
        );
        
        // Wyślij zapytanie do API
        $model = $this->get_setting('default_model', 'gpt-3.5-turbo');
        $response = $this->api->send_request(
            $prompt,
            $model,
            $max_tokens[$length]
        );
        
        if (is_wp_error($response)) {
            $this->logger->log('error', $response->get_error_message(), array(
                'title' => $title,
                'keywords' => $keywords,
                'length' => $length,
                'post_id' => $post_id
            ));
            return $response;
        }
        
        // Zapisz do cache
        $this->cache->set($cache_key, $response, $model, 'content', 3600, $post_id);
        
        return $response;
    }
    
    /**
     * Analizuje treść pod kątem SEO
     * 
     * @param string $content Treść do analizy
     * @param string $keywords Słowa kluczowe
     * @param int $post_id ID posta (opcjonalnie)
     * @return array|WP_Error Wynik analizy lub błąd
     */
    public function analyze_content($content, $keywords = '', $post_id = null) {
        if (empty($content)) {
            return new WP_Error('empty_content', __('Treść nie może być pusta.', 'cleanseo-optimizer'));
        }
        
        // Przygotuj prompt
        $template = $this->get_prompt_template('content_audit');
        if (empty($template)) {
            return new WP_Error('missing_template', __('Brak szablonu promptu.', 'cleanseo-optimizer'));
        }
        
        // Skróć treść jeśli jest za długa
        $content_excerpt = (strlen($content) > 10000) ? substr($content, 0, 10000) . "..." : $content;
        
        $prompt = str_replace(
            array('{content}', '{keywords}'),
            array($content_excerpt, $keywords),
            $template
        );
        
        // Generuj unikalny klucz cache
        $cache_key = "content_audit_" . md5($content_excerpt . $keywords);
        
        // Sprawdź cache
        $cached = $this->cache->get($cache_key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Wyślij zapytanie do API
        $model = $this->get_setting('default_model', 'gpt-3.5-turbo');
        $response = $this->api->send_request(
            $prompt,
            $model,
            1000
        );
        
        if (is_wp_error($response)) {
            $this->logger->log('error', $response->get_error_message(), array(
                'keywords' => $keywords,
                'post_id' => $post_id
            ));
            return $response;
        }
        
        // Przetwórz odpowiedź na strukturę analizy
        $analysis = $this->parse_content_analysis($response);
        
        // Zapisz do cache
        $this->cache->set($cache_key, $analysis, $model, 'content_audit', 3600, $post_id);
        
        return $analysis;
    }
    
    /**
     * Optymalizuje treść pod kątem SEO
     * 
     * @param string $content Treść do optymalizacji
     * @param string $keywords Słowa kluczowe
     * @param array $options Opcje optymalizacji
     * @param int $post_id ID posta (opcjonalnie)
     * @return string|WP_Error Zoptymalizowana treść lub błąd
     */
    public function optimize_content($content, $keywords = '', $options = array(), $post_id = null) {
        if (empty($content)) {
            return new WP_Error('empty_content', __('Treść nie może być pusta.', 'cleanseo-optimizer'));
        }
        
        // Domyślne opcje
        $default_options = array(
            'improve_readability' => true,
            'optimize_headings' => true,
            'optimize_keywords' => true,
            'add_internal_links' => false,
            'add_faq' => false
        );
        
        $options = wp_parse_args($options, $default_options);
        
        // Przygotuj prompt
        $template = $this->get_prompt_template('content_optimization');
        if (empty($template)) {
            return new WP_Error('missing_template', __('Brak szablonu promptu.', 'cleanseo-optimizer'));
        }
        
        // Skróć treść jeśli jest za długa
        $content_excerpt = (strlen($content) > 15000) ? substr($content, 0, 15000) . "..." : $content;
        
        // Przygotuj instrukcje optymalizacji
        $optimization_instructions = array();
        
        if ($options['improve_readability']) {
            $optimization_instructions[] = "Popraw czytelność tekstu, używając krótszych zdań i paragrafów.";
        }
        
        if ($options['optimize_headings']) {
            $optimization_instructions[] = "Zoptymalizuj nagłówki H2 i H3, dodając słowa kluczowe tam, gdzie to naturalne.";
        }
        
        if ($options['optimize_keywords']) {
            $optimization_instructions[] = "Zwiększ gęstość słów kluczowych w tekście, ale zachowaj naturalny styl.";
        }
        
        if ($options['add_internal_links']) {
            $optimization_instructions[] = "Zasugeruj miejsca, gdzie można dodać linki wewnętrzne (oznacz jako [LINK: temat]).";
        }
        
        if ($options['add_faq']) {
            $optimization_instructions[] = "Dodaj sekcję FAQ na końcu treści z 3-5 najczęściej zadawanymi pytaniami.";
        }
        
        $prompt = str_replace(
            array('{content}', '{keywords}', '{instructions}'),
            array($content_excerpt, $keywords, implode("\n", $optimization_instructions)),
            $template
        );
        
        // Generuj unikalny klucz cache
        $cache_key = "content_optimization_" . md5($content_excerpt . $keywords . serialize($options));
        
        // Sprawdź cache
        $cached = $this->cache->get($cache_key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Wyślij zapytanie do API
        $model = $this->get_setting('default_model', 'gpt-4');  // Do optymalizacji lepiej użyć mocniejszego modelu
        $response = $this->api->send_request(
            $prompt,
            $model,
            4000
        );
        
        if (is_wp_error($response)) {
            $this->logger->log('error', $response->get_error_message(), array(
                'keywords' => $keywords,
                'options' => $options,
                'post_id' => $post_id
            ));
            return $response;
        }
        
        // Zapisz do cache
        $this->cache->set($cache_key, $response, $model, 'content_optimization', 3600, $post_id);
        
        return $response;
    }
    
    /**
     * Generuje treść na podstawie szablonu promptu
     * 
     * @param int $post_id ID posta
     * @param string $field Typ pola (meta_title, meta_description, itp.)
     * @param string $keywords Słowa kluczowe (opcjonalnie)
     * @return string|WP_Error Wygenerowana treść lub błąd
     */
    private function generate_from_prompt($post_id, $field, $keywords = '') {
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('invalid_post', __('Nieprawidłowy post.', 'cleanseo-optimizer'));
        }

        $post = get_post($post_id);
        $post_title = $post->post_title;
        $post_content = $post->post_content;
        $post_excerpt = $post->post_excerpt;
        
        // Jeśli nie podano słów kluczowych, spróbuj pobrać je z meta danych posta
        if (empty($keywords)) {
            $keywords = get_post_meta($post_id, '_cleanseo_keywords', true);
        }
        
        // Pobierz szablon promptu
        $template = $this->get_prompt_template($field);
        if (empty($template)) {
            return new WP_Error('missing_template', __('Brak szablonu promptu dla pola: ' . $field, 'cleanseo-optimizer'));
        }
        
        // Podstaw wartości do szablonu
        $prompt = str_replace(
            array('{title}', '{content}', '{excerpt}', '{keywords}', '{post_type}'),
            array($post_title, $post_content, $post_excerpt, $keywords, $post->post_type),
            $template
        );
        
        // Generuj unikalny klucz cache
        $cache_key = "{$field}_" . md5($prompt);
        
        // Sprawdź cache
        $cached = $this->cache->get($cache_key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Pobierz ustawienia dla tego typu pola
        $field_settings = $this->get_field_settings($field);
        $model = $field_settings['model'] ?? $this->get_setting('default_model', 'gpt-3.5-turbo');
        $max_tokens = $field_settings['max_tokens'] ?? 500;
        $temperature = $field_settings['temperature'] ?? 0.7;
        
        // Wyślij zapytanie do API
        $response = $this->api->send_request(
            $prompt,
            $model,
            $max_tokens,
            $temperature
        );
        
        if (is_wp_error($response)) {
            $this->logger->log('error', $response->get_error_message(), array(
                'post_id' => $post_id,
                'field' => $field,
                'keywords' => $keywords
            ));
            return $response;
        }
        
        // Przetwórz odpowiedź w zależności od typu pola
        $processed_response = $this->process_response($response, $field);
        
        // Zapisz do cache
        $this->cache->set($cache_key, $processed_response, $model, $field, 3600, $post_id);
        
        // Zapisz wygenerowaną treść w metadanych posta
        $this->save_generated_content($post_id, $field, $processed_response);
        
        return $processed_response;
    }
    
    /**
     * Przetwarza odpowiedź API w zależności od typu pola
     * 
     * @param string $response Odpowiedź z API
     * @param string $field Typ pola
     * @return mixed Przetworzona odpowiedź
     */
    private function process_response($response, $field) {
        switch ($field) {
            case 'meta_title':
                // Usuń cudzysłowy i ograniczenia długości
                $response = trim($response, "\"'\n\r\t ");
                $response = substr($response, 0, 60);
                break;
                
            case 'meta_description':
                // Usuń cudzysłowy i ograniczenia długości
                $response = trim($response, "\"'\n\r\t ");
                $response = substr($response, 0, 160);
                break;
                
            case 'tags':
                // Zamień odpowiedź na tablicę tagów
                $response = array_map('trim', explode(',', $response));
                break;
                
            case 'heading_ideas':
                // Zamień odpowiedź na tablicę nagłówków
                $headings = array();
                $lines = explode("\n", $response);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $headings[] = $line;
                    }
                }
                $response = $headings;
                break;
                
            case 'schema':
                // Upewnij się, że odpowiedź to poprawny JSON
                $json = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Spróbuj wyodrębnić JSON z tekstu
                    preg_match('/\{.*\}/s', $response, $matches);
                    if (!empty($matches[0])) {
                        $response = $matches[0];
                    }
                }
                break;
        }
        
        return $response;
    }
    
    /**
     * Zapisuje wygenerowaną treść w metadanych posta
     * 
     * @param int $post_id ID posta
     * @param string $field Typ pola
     * @param mixed $content Wygenerowana treść
     */
    private function save_generated_content($post_id, $field, $content) {
        if (empty($post_id) || empty($field) || empty($content)) {
            return;
        }
        
        switch ($field) {
            case 'meta_title':
                update_post_meta($post_id, '_cleanseo_meta_title', $content);
                // Jeśli używany jest Yoast SEO
                update_post_meta($post_id, '_yoast_wpseo_title', $content);
                break;
                
            case 'meta_description':
                update_post_meta($post_id, '_cleanseo_meta_description', $content);
                // Jeśli używany jest Yoast SEO
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $content);
                break;
                
            case 'schema':
                update_post_meta($post_id, '_cleanseo_schema_markup', $content);
                break;
                
            case 'excerpt':
                // Aktualizuj wyciąg posta
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $content
                ));
                break;
                
            case 'tags':
                // Przypisz tagi do posta
                if (is_array($content)) {
                    wp_set_post_tags($post_id, $content, false);
                }
                break;
                
            case 'faq':
                update_post_meta($post_id, '_cleanseo_faq', $content);
                break;
                
            case 'heading_ideas':
                update_post_meta($post_id, '_cleanseo_heading_ideas', $content);
                break;
        }
    }
    
    /**
     * Parsuje odpowiedź analizy treści na strukturę danych
     * 
     * @param string $response Odpowiedź z API
     * @return array Struktura analizy
     */
    private function parse_content_analysis($response) {
        // Przykładowa struktura wyniku analizy
        $analysis = array(
            'score' => 0,
            'details' => array(
                'good' => array(),
                'improvement' => array(),
                'bad' => array()
            ),
            'summary' => ''
        );
        
        // Próba wyodrębnienia oceny
        if (preg_match('/ocena(?:\:|\s+w\s+skali\s+\d+\-\d+)\s*(\d+)/i', $response, $matches)) {
            $analysis['score'] = intval($matches[1]);
            
            // Jeśli skala jest inna niż 0-100, przekonwertuj
            if ($analysis['score'] > 0 && $analysis['score'] <= 10) {
                $analysis['score'] = $analysis['score'] * 10;
            }
        }
        
        // Próba wyodrębnienia dobrych aspektów
        if (preg_match('/dobre\s+aspekty:?(.*?)(?:aspekty\s+do\s+poprawy|słabe\s+aspekty|$)/is', $response, $matches)) {
            $good_aspects = $this->extract_list_items($matches[1]);
            foreach ($good_aspects as $aspect) {
                $analysis['details']['good'][] = array(
                    'message' => $aspect,
                    'type' => 'good'
                );
            }
        }
        
        // Próba wyodrębnienia aspektów do poprawy
        if (preg_match('/(?:aspekty\s+do\s+poprawy|do\s+poprawy):?(.*?)(?:słabe\s+aspekty|$)/is', $response, $matches)) {
            $improvement_aspects = $this->extract_list_items($matches[1]);
            foreach ($improvement_aspects as $aspect) {
                $analysis['details']['improvement'][] = array(
                    'message' => $aspect,
                    'type' => 'improvement'
                );
            }
        }
        
        // Próba wyodrębnienia słabych aspektów
        if (preg_match('/(?:słabe\s+aspekty|problemy):?(.*?)(?:podsumowanie|$)/is', $response, $matches)) {
            $bad_aspects = $this->extract_list_items($matches[1]);
            foreach ($bad_aspects as $aspect) {
                $analysis['details']['bad'][] = array(
                    'message' => $aspect,
                    'type' => 'bad'
                );
            }
        }
        
        // Próba wyodrębnienia podsumowania
        if (preg_match('/podsumowanie:?(.*?)$/is', $response, $matches)) {
            $analysis['summary'] = trim($matches[1]);
        } else {
            // Jeśli nie znaleziono podsumowania, użyj ostatniego akapitu
            $paragraphs = explode("\n\n", $response);
            $analysis['summary'] = trim(end($paragraphs));
        }
        
        return $analysis;
    }
    
    /**
     * Wyodrębnia elementy listy z tekstu
     * 
     * @param string $text Tekst zawierający listę
     * @return array Tablica elementów listy
     */
    private function extract_list_items($text) {
        $items = array();
        
        // Usuń zbędne białe znaki
        $text = trim($text);
        
        // Sprawdź czy są punktory lub numeracja
        if (preg_match_all('/(?:^|\n)[\s]*(?:\d+\.|\*|\-|\–|\•)[\s]+(.+?)(?=(?:\n[\s]*(?:\d+\.|\*|\-|\–|\•)[\s]+)|$)/s', $text, $matches)) {
            $items = $matches[1];
        } else {
            // Jeśli nie znaleziono punktorów, podziel po nowej linii
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $items[] = $line;
                }
            }
        }
        
        // Usuń puste elementy i przytnij
        $items = array_map('trim', $items);
        $items = array_filter($items);
        
        return $items;
    }
    
    /**
     * Pobiera typy postów, dla których włączono funkcje AI
     * 
     * @return array Typy postów
     */
    private function get_enabled_post_types() {
        $default_post_types = array('post', 'page');
        
        return $this->get_setting('post_types', $default_post_types);
    }
    
    /**
     * Pobiera szablon promptu dla określonego pola
     * 
     * @param string $field Typ pola
     * @return string Szablon promptu
     */
    private function get_prompt_template($field) {
        // Domyślne szablony
        $default_templates = array(
            'meta_title' => 'Napisz w języku polskim atrakcyjny meta tytuł SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tytuł powinien mieć maksymalnie 60 znaków, zawierać główne słowo kluczowe na początku i zachęcać do kliknięcia.',
            
            'meta_description' => 'Napisz w języku polskim przekonujący meta opis SEO dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Opis powinien mieć maksymalnie 155 znaków, zawierać główne słowo kluczowe i zawierać wezwanie do działania.',
            
            'schema' => 'Wygeneruj w języku polskim schemat JSON-LD dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Użyj struktury Article. Zwróć tylko czysty kod JSON bez dodatkowych wyjaśnień.',
            
            'excerpt' => 'Napisz w języku polskim krótki, przyciągający uwagę wyciąg dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Wyciąg powinien mieć około 150-200 znaków i zachęcać do przeczytania całego artykułu.',
            
            'tags' => 'Zaproponuj 5-8 tagów dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Tagi powinny być krótkimi frazami związanymi z tematem artykułu. Zwróć listę tagów oddzielonych przecinkami, bez dodatkowych wyjaśnień.',
            
            'faq' => 'Stwórz w języku polskim sekcję FAQ (5 pytań i odpowiedzi) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Każde pytanie powinno być naturalne i odzwierciedlać rzeczywiste zapytania użytkowników. Każda odpowiedź powinna mieć 2-3 zdania. Zwróć FAQ w formacie HTML z użyciem struktury schema.org.',
            
            'heading_ideas' => 'Zaproponuj 5-7 nagłówków (H2) dla artykułu o tytule "{title}". Słowa kluczowe: {keywords}. Nagłówki powinny być atrakcyjne, zawierać słowa kluczowe tam gdzie to naturalne i pokrywać różne aspekty tematu. Zwróć listę nagłówków, po jednym w linii.',
            
            'content' => 'Napisz w języku polskim wysokiej jakości treść na temat "{title}". Słowa kluczowe: {keywords}. Długość: {length} (short = 300 słów, medium = 800 słów, long = 1500 słów). Treść powinna być podzielona nagłówkami, zawierać wstęp, rozwinięcie i podsumowanie. Użyj naturalnego stylu, zaangażowanego tonu i pisz w drugiej osobie. Słowa kluczowe powinny być użyte naturalnie i rozproszone w całej treści.',
            
            'content_audit' => 'Przeprowadź szczegółową analizę SEO poniższej treści pod kątem słów kluczowych: {keywords}. Treść: {content}. Oceń następujące aspekty: 1) Gęstość słów kluczowych, 2) Struktura treści (nagłówki, akapity), 3) Czytelność tekstu, 4) Długość treści, 5) Meta tagi. Podaj ocenę w skali 1-10 oraz konkretne sugestie ulepszeń. Podziel wyniki na: dobre aspekty, aspekty do poprawy i słabe aspekty.',
            
            'content_optimization' => 'Zoptymalizuj poniższą treść pod kątem SEO dla słów kluczowych: {keywords}. Treść: {content}. Instrukcje optymalizacji: {instructions}. Zachowaj oryginalny styl i ton tekstu. Zwróć zoptymalizowaną wersję treści.'
        );
        
        // Pobierz szablon z ustawień lub użyj domyślnego
        $templates = $this->get_setting('prompt_templates', array());
        
        return isset($templates[$field]) ? $templates[$field] : (isset($default_templates[$field]) ? $default_templates[$field] : '');
    }
    
    /**
     * Pobiera ustawienia dla określonego pola
     * 
     * @param string $field Typ pola
     * @return array Ustawienia pola
     */
    private function get_field_settings($field) {
        // Domyślne ustawienia dla poszczególnych pól
        $default_settings = array(
            'meta_title' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 100,
                'temperature' => 0.7
            ),
            'meta_description' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 200,
                'temperature' => 0.7
            ),
            'schema' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 500,
                'temperature' => 0.2
            ),
            'excerpt' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 200,
                'temperature' => 0.7
            ),
            'tags' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 100,
                'temperature' => 0.5
            ),
            'faq' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 1000,
                'temperature' => 0.7
            ),
            'heading_ideas' => array(
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 300,
                'temperature' => 0.8
            ),
            'content' => array(
                'model' => 'gpt-4',
                'max_tokens' => 4000,
                'temperature' => 0.7
            ),
            'content_audit' => array(
                'model' => 'gpt-4',
                'max_tokens' => 1000,
                'temperature' => 0.3
            ),
            'content_optimization' => array(
                'model' => 'gpt-4',
                'max_tokens' => 4000,
                'temperature' => 0.5
            )
        );
        
        // Pobierz ustawienia z konfiguracji
        $field_settings = $this->get_setting('field_settings', array());
        
        if (isset($field_settings[$field])) {
            return wp_parse_args($field_settings[$field], $default_settings[$field] ?? array());
        }
        
        return $default_settings[$field] ?? array();
    }
    
    /**
     * Pobiera ustawienie z konfiguracji
     * 
     * @param string $key Klucz ustawienia
     * @param mixed $default Wartość domyślna
     * @return mixed Wartość ustawienia
     */
    private function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Oblicza klasę dla wyniku SEO
     * 
     * @param int $score Wynik SEO
     * @return string Nazwa klasy
     */
    private function get_score_class($score) {
        if ($score >= 80) {
            return 'good';
        } elseif ($score >= 50) {
            return 'ok';
        } else {
            return 'bad';
        }
    }
    
    /**
     * Pobiera etykietę dla wyniku SEO
     * 
     * @param int $score Wynik SEO
     * @return string Etykieta
     */
    private function get_score_label($score) {
        if ($score >= 80) {
            return __('Dobrze', 'cleanseo-optimizer');
        } elseif ($score >= 50) {
            return __('Wymaga poprawy', 'cleanseo-optimizer');
        } else {
            return __('Słabo', 'cleanseo-optimizer');
        }
    }
    
    /**
     * Sprawdza czy funkcja AI jest włączona
     * 
     * @param string $feature Nazwa funkcji
     * @return bool Czy funkcja jest włączona
     */
    public function is_feature_enabled($feature) {
        $features = $this->get_setting('enabled_features', array(
            'meta_title' => true,
            'meta_description' => true,
            'content' => true,
            'content_audit' => true,
            'content_optimization' => true,
            'schema' => true,
            'excerpt' => true,
            'faq' => false
        ));
        
        return isset($features[$feature]) ? (bool)$features[$feature] : false;
    }
    
    /**
     * Generuje treść dla wielu postów jednocześnie
     * 
     * @param array $post_ids Tablica ID postów
     * @param string $field Typ pola
     * @param array $options Dodatkowe opcje
     * @return array Wyniki dla każdego posta
     */
    public function bulk_generate($post_ids, $field, $options = array()) {
        if (!is_array($post_ids) || empty($post_ids) || empty($field)) {
            return new WP_Error('invalid_params', __('Nieprawidłowe parametry.', 'cleanseo-optimizer'));
        }
        
        $results = array();
        
        foreach ($post_ids as $post_id) {
            $keywords = isset($options['keywords']) ? $options['keywords'] : '';
            
            // Jeśli nie podano słów kluczowych, spróbuj pobrać je z meta danych posta
            if (empty($keywords)) {
                $keywords = get_post_meta($post_id, '_cleanseo_keywords', true);
            }
            
            $result = $this->generate_from_prompt($post_id, $field, $keywords);
            
            $results[$post_id] = array(
                'success' => !is_wp_error($result),
                'data' => is_wp_error($result) ? $result->get_error_message() : $result
            );
        }
        
        return $results;
    }
}