<?php

class CleanSEO_OpenAI {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $default_model = 'gpt-3.5-turbo';
    private $default_language = 'pl';
    private $log_table;
    private $cache_table;
    private $daily_limit = 20;
    private $ai_models;
    private $ai_cache;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->api_key = get_option('cleanseo_openai_api_key', '');
        $this->log_table = $wpdb->prefix . 'seo_openai_logs';
        $this->cache_table = $wpdb->prefix . 'seo_ai_cache';
        $this->ai_models = new CleanSEO_AI_Models();
        $this->ai_cache = new CleanSEO_AI_Cache();
        $this->logger = new CleanSEO_Logger();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_cleanseo_generate_meta', array($this, 'generate_meta_tags'));
        add_action('wp_ajax_cleanseo_generate_keywords', array($this, 'generate_keywords'));
        add_action('wp_ajax_cleanseo_generate_faq', array($this, 'generate_faq'));
        add_action('wp_ajax_cleanseo_generate_schema', array($this, 'generate_schema'));
        add_action('wp_ajax_cleanseo_generate_headings', array($this, 'generate_headings'));
        add_action('wp_ajax_cleanseo_generate_product_desc', array($this, 'generate_product_desc'));
        add_action('admin_menu', array($this, 'add_ai_stats_menu'));
    }

    public function add_ai_stats_menu() {
        add_submenu_page(
            'cleanseo',
            'Statystyki AI',
            'Statystyki AI',
            'manage_options',
            'cleanseo-ai-stats',
            array($this, 'render_ai_stats_page')
        );
    }

    public function render_ai_stats_page() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cleanseo-ai-stats.php';
    }

    private function get_model() {
        return $this->ai_models->get_model_config(get_option('cleanseo_openai_model', $this->default_model));
    }

    private function get_language() {
        return get_option('cleanseo_openai_language', $this->default_language);
    }

    public function generate_meta_tags() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('Post not found');
        }

        if (empty($this->api_key)) {
            wp_send_json_error('OpenAI API key not configured');
        }

        $user_id = get_current_user_id();
        if (!$this->check_limit($user_id)) {
            wp_send_json_error('Przekroczono dzienny limit zapytań AI. Spróbuj jutro.');
        }

        // Sanitize and prepare content
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000); // Limit content length
        $content = sanitize_textarea_field($content);

        $prompt = "Generate SEO meta title and description for the following content. Return in JSON format with 'title' and 'description' keys. Keep title under 60 characters and description under 160 characters:\n\n" . $content;

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 150
            )),
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('API request failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['choices'][0]['message']['content'])) {
            wp_send_json_error('Invalid API response');
        }

        $content = $body['choices'][0]['message']['content'];
        $meta = json_decode($content, true);

        if (!$meta || !isset($meta['title']) || !isset($meta['description'])) {
            wp_send_json_error('Invalid response format');
        }

        // Sanitize the response
        $meta['title'] = sanitize_text_field($meta['title']);
        $meta['description'] = sanitize_textarea_field($meta['description']);

        $this->log_openai($user_id, $post_id, 'generate_meta', $prompt, $meta['title'] . ' | ' . $meta['description'], $this->get_model(), 'success');

        wp_send_json_success(array(
            'title' => $meta['title'],
            'description' => $meta['description']
        ));
    }

    private function call_openai($prompt, $max_tokens = 200) {
        $model = $this->get_model();
        $lang = $this->get_language();
        $prompt = "Odpowiadaj w języku $lang.\n" . $prompt;
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => $max_tokens
            )),
            'timeout' => 30,
            'sslverify' => true
        ));
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['choices'][0]['message']['content'])) {
            return array('error' => 'Invalid API response');
        }
        return array('content' => $body['choices'][0]['message']['content']);
    }

    public function generate_keywords() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj listę 5 najważniejszych słów kluczowych (keywords) dla poniższej treści. Zwróć jako JSON array pod kluczem 'keywords'.\n\n" . $content;
        $result = $this->call_openai($prompt, 100);
        if (!empty($result['error'])) {
            wp_send_json_error($result['error']);
        }
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['keywords'])) {
            wp_send_json_error('Invalid response format');
        }
        $keywords = array_map('sanitize_text_field', $data['keywords']);
        wp_send_json_success(array('keywords' => $keywords));
    }

    public function generate_faq() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj 3 pytania i odpowiedzi (FAQ) do poniższej treści. Zwróć jako JSON array pod kluczem 'faq', gdzie każdy element to obiekt z 'question' i 'answer'.\n\n" . $content;
        $result = $this->call_openai($prompt, 300);
        if (!empty($result['error'])) {
            wp_send_json_error($result['error']);
        }
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['faq'])) {
            wp_send_json_error('Invalid response format');
        }
        $faq = array();
        foreach ($data['faq'] as $item) {
            $faq[] = array(
                'question' => sanitize_text_field($item['question']),
                'answer' => sanitize_textarea_field($item['answer'])
            );
        }
        wp_send_json_success(array('faq' => $faq));
    }

    public function generate_schema() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj dane strukturalne schema.org (JSON-LD) dla poniższej treści. Zwróć tylko poprawny JSON-LD.\n\n" . $content;
        $result = $this->call_openai($prompt, 400);
        if (!empty($result['error'])) {
            wp_send_json_error($result['error']);
        }
        $jsonld = trim($result['content']);
        wp_send_json_success(array('schema' => $jsonld));
    }

    public function generate_headings() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj propozycje nagłówków H1, H2 i H3 dla poniższej treści. Zwróć jako JSON z kluczami 'h1', 'h2', 'h3', gdzie 'h2' i 'h3' to tablice.\n\n" . $content;
        $result = $this->call_openai($prompt, 200);
        if (!empty($result['error'])) {
            wp_send_json_error($result['error']);
        }
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['h1'])) {
            wp_send_json_error('Invalid response format');
        }
        $headings = array(
            'h1' => sanitize_text_field($data['h1']),
            'h2' => array_map('sanitize_text_field', $data['h2']),
            'h3' => array_map('sanitize_text_field', $data['h3'])
        );
        wp_send_json_success(array('headings' => $headings));
    }

    public function generate_product_desc() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj atrakcyjny opis produktu na podstawie poniższej treści. Zwróć jako JSON z kluczem 'description'.\n\n" . $content;
        $result = $this->call_openai($prompt, 200);
        if (!empty($result['error'])) {
            wp_send_json_error($result['error']);
        }
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['description'])) {
            wp_send_json_error('Invalid response format');
        }
        $desc = sanitize_textarea_field($data['description']);
        wp_send_json_success(array('description' => $desc));
    }

    private function log_openai($user_id, $post_id, $action, $prompt, $response, $model, $status) {
        global $wpdb;
        $wpdb->insert($this->log_table, array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'action' => $action,
            'prompt' => $prompt,
            'response' => $response,
            'model' => $model,
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }

    private function check_limit($user_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->log_table} WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $user_id
        ));
        return $count < $this->daily_limit;
    }

    private function get_cache($prompt, $post_id, $model, $type) {
        global $wpdb;
        $hash = hash('sha256', $prompt . '|' . $post_id . '|' . $model . '|' . $type);
        $row = $wpdb->get_row($wpdb->prepare("SELECT response FROM {$this->cache_table} WHERE hash = %s", $hash));
        return $row ? $row->response : false;
    }

    private function set_cache($prompt, $post_id, $model, $type, $response) {
        global $wpdb;
        $hash = hash('sha256', $prompt . '|' . $post_id . '|' . $model . '|' . $type);
        $wpdb->replace($this->cache_table, array(
            'hash' => $hash,
            'post_id' => $post_id,
            'model' => $model,
            'type' => $type,
            'response' => $response,
            'created_at' => current_time('mysql')
        ));
    }

    // Metody batch do obsługi AJAX batch
    public function generate_meta_tags_batch($post_id) {
        $post = get_post($post_id);
        if (!$post) return 'Nie znaleziono posta.';
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Generate SEO meta title and description for the following content. Return in JSON format with 'title' and 'description' keys. Keep title under 60 characters and description under 160 characters:\n\n" . $content;
        $model = $this->get_model();
        $type = 'meta';
        $cached = $this->get_cache($prompt, $post_id, $model, $type);
        if ($cached) return json_decode($cached, true);
        $result = $this->call_openai($prompt, 150);
        if (!empty($result['error'])) return $result['error'];
        $meta = json_decode($result['content'], true);
        if (!$meta || !isset($meta['title']) || !isset($meta['description'])) return 'Invalid response format';
        $this->set_cache($prompt, $post_id, $model, $type, $result['content']);
        return $meta;
    }
    public function generate_keywords_batch($post_id) {
        $post = get_post($post_id);
        if (!$post) return 'Nie znaleziono posta.';
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj listę 5 najważniejszych słów kluczowych (keywords) dla poniższej treści. Zwróć jako JSON array pod kluczem 'keywords'.\n\n" . $content;
        $model = $this->get_model();
        $type = 'keywords';
        $cached = $this->get_cache($prompt, $post_id, $model, $type);
        if ($cached) return json_decode($cached, true);
        $result = $this->call_openai($prompt, 100);
        if (!empty($result['error'])) return $result['error'];
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['keywords'])) return 'Invalid response format';
        $this->set_cache($prompt, $post_id, $model, $type, $result['content']);
        return $data['keywords'];
    }
    public function generate_faq_batch($post_id) {
        $post = get_post($post_id);
        if (!$post) return 'Nie znaleziono posta.';
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj 3 pytania i odpowiedzi (FAQ) do poniższej treści. Zwróć jako JSON array pod kluczem 'faq', gdzie każdy element to obiekt z 'question' i 'answer'.\n\n" . $content;
        $model = $this->get_model();
        $type = 'faq';
        $cached = $this->get_cache($prompt, $post_id, $model, $type);
        if ($cached) return json_decode($cached, true);
        $result = $this->call_openai($prompt, 300);
        if (!empty($result['error'])) return $result['error'];
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['faq'])) return 'Invalid response format';
        $this->set_cache($prompt, $post_id, $model, $type, $result['content']);
        return $data['faq'];
    }
    public function generate_headings_batch($post_id) {
        $post = get_post($post_id);
        if (!$post) return 'Nie znaleziono posta.';
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj propozycje nagłówków H1, H2 i H3 dla poniższej treści. Zwróć jako JSON z kluczami 'h1', 'h2', 'h3', gdzie 'h2' i 'h3' to tablice.\n\n" . $content;
        $model = $this->get_model();
        $type = 'headings';
        $cached = $this->get_cache($prompt, $post_id, $model, $type);
        if ($cached) return json_decode($cached, true);
        $result = $this->call_openai($prompt, 200);
        if (!empty($result['error'])) return $result['error'];
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['h1'])) return 'Invalid response format';
        $this->set_cache($prompt, $post_id, $model, $type, $result['content']);
        return $data;
    }
    public function generate_product_desc_batch($post_id) {
        $post = get_post($post_id);
        if (!$post) return 'Nie znaleziono posta.';
        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 1000);
        $content = sanitize_textarea_field($content);
        $prompt = "Wygeneruj atrakcyjny opis produktu na podstawie poniższej treści. Zwróć jako JSON z kluczem 'description'.\n\n" . $content;
        $model = $this->get_model();
        $type = 'description';
        $cached = $this->get_cache($prompt, $post_id, $model, $type);
        if ($cached) return json_decode($cached, true);
        $result = $this->call_openai($prompt, 200);
        if (!empty($result['error'])) return $result['error'];
        $data = json_decode($result['content'], true);
        if (!$data || !isset($data['description'])) return 'Invalid response format';
        $this->set_cache($prompt, $post_id, $model, $type, $result['content']);
        return $data['description'];
    }

    /**
     * Send prompt to Gemini (Google AI)
     *
     * @param string $prompt The prompt to send.
     * @return string|WP_Error The response from Gemini or WP_Error on failure.
     */
    private function send_to_gemini($prompt) {
        $api_key = get_option('cleanseo_gemini_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('gemini_api_key_missing', 'Gemini API key is not configured.');
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . urlencode($api_key);
        $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 30
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        return new WP_Error('gemini_response_error', 'Invalid response from Gemini API.');
    }

    /**
     * Send prompt to Claude (Anthropic)
     *
     * @param string $prompt The prompt to send.
     * @return string|WP_Error The response from Claude or WP_Error on failure.
     */
    private function send_to_claude($prompt) {
        $api_key = get_option('cleanseo_claude_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('claude_api_key_missing', 'Claude API key is not configured.');
        }
        $url = 'https://api.anthropic.com/v1/messages';
        $body = json_encode([
            'model' => 'claude-3',
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ]);
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            ],
            'body' => $body,
            'timeout' => 30
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['content'][0]['text'])) {
            return $data['content'][0]['text'];
        }
        return new WP_Error('claude_response_error', 'Invalid response from Claude API.');
    }

    /**
     * Send prompt to HuggingFace
     *
     * @param string $prompt The prompt to send.
     * @return string|WP_Error The response from HuggingFace or WP_Error on failure.
     */
    private function send_to_huggingface($prompt) {
        $api_key = get_option('cleanseo_huggingface_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('huggingface_api_key_missing', 'HuggingFace API key is not configured.');
        }
        $url = 'https://api-inference.huggingface.co/models/gpt2';
        $body = json_encode(['inputs' => $prompt]);
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => $body,
            'timeout' => 30
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data[0]['generated_text'])) {
            return $data[0]['generated_text'];
        }
        return new WP_Error('huggingface_response_error', 'Invalid response from HuggingFace API.');
    }

    /**
     * Wysyła zapytanie do OpenAI API
     *
     * @param string $prompt Zapytanie do modelu
     * @param array $options Opcje zapytania (model, temperature, max_tokens)
     * @return array|WP_Error Wynik zapytania lub błąd
     */
    private function send_to_openai($prompt, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $default_options = array(
            'model' => $this->get_model(),
            'temperature' => 0.7,
            'max_tokens' => 200,
            'language' => $this->get_language()
        );

        $options = wp_parse_args($options, $default_options);
        
        // Dodaj instrukcję języka do promptu
        $prompt = "Odpowiadaj w języku {$options['language']}.\n" . $prompt;

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $options['model'],
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => $options['temperature'],
                'max_tokens' => $options['max_tokens']
            )),
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            $this->log_ai_request($prompt, $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['choices'][0]['message']['content'])) {
            $error = new WP_Error('invalid_response', 'Invalid API response');
            $this->log_ai_request($prompt, $error->get_error_message());
            return $error;
        }

        $content = $body['choices'][0]['message']['content'];
        
        // Zapisz odpowiedź w cache
        $this->cache_ai_response($prompt, $content);
        
        // Zaloguj zapytanie
        $this->log_ai_request($prompt, $content);

        return array(
            'content' => $content,
            'model' => $options['model'],
            'usage' => $body['usage'] ?? null
        );
    }

    /**
     * Log AI request and response to the database
     *
     * @param string $prompt The prompt sent to the AI.
     * @param string|WP_Error $response The response from the AI or WP_Error on failure.
     * @return void
     */
    private function log_ai_request($prompt, $response) {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_logs';
        $user_id = get_current_user_id();
        $model = get_option('cleanseo_openai_model', 'gpt-3.5-turbo');
        $response_text = is_wp_error($response) ? $response->get_error_message() : $response;
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'model' => $model,
            'prompt' => $prompt,
            'response' => $response_text,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Check if the user has exceeded their daily AI request limit
     *
     * @return bool|WP_Error True if the user is within the limit, WP_Error otherwise.
     */
    private function check_daily_limit() {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_logs';
        $user_id = get_current_user_id();
        $limit = 20; // Daily limit
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)", $user_id));
        if ($count >= $limit) {
            return new WP_Error('daily_limit_exceeded', 'You have exceeded your daily AI request limit.');
        }
        return true;
    }

    /**
     * Cache AI response in the database
     *
     * @param string $prompt The prompt sent to the AI.
     * @param string $response The response from the AI.
     * @return void
     */
    private function cache_ai_response($prompt, $response) {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_cache';
        $model = get_option('cleanseo_openai_model', 'gpt-3.5-turbo');
        $wpdb->insert($table, [
            'model' => $model,
            'prompt' => $prompt,
            'response' => $response,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get cached AI response from the database
     *
     * @param string $prompt The prompt sent to the AI.
     * @return string|false The cached response or false if not found.
     */
    private function get_cached_response($prompt) {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_cache';
        $model = get_option('cleanseo_openai_model', 'gpt-3.5-turbo');
        $response = $wpdb->get_var($wpdb->prepare("SELECT response FROM $table WHERE model = %s AND prompt = %s ORDER BY created_at DESC LIMIT 1", $model, $prompt));
        return $response ? $response : false;
    }

    /**
     * Clear AI response cache
     *
     * @return void
     */
    private function clear_cache() {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_cache';
        $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Handle AI request based on selected model
     *
     * @param string $prompt The prompt to send.
     * @return string|WP_Error The response from the AI model or WP_Error on failure.
     */
    public function handle_ai_request($prompt) {
        $limit_check = $this->check_daily_limit();
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }
        $cached_response = $this->get_cached_response($prompt);
        if ($cached_response) {
            return $cached_response;
        }
        $model = get_option('cleanseo_openai_model', 'gpt-3.5-turbo');
        if ($model === 'gemini-pro') {
            $response = $this->send_to_gemini($prompt);
        } elseif ($model === 'claude-3') {
            $response = $this->send_to_claude($prompt);
        } elseif ($model === 'huggingface') {
            $response = $this->send_to_huggingface($prompt);
        } else {
            $response = $this->send_to_openai($prompt);
        }
        $this->log_ai_request($prompt, $response);
        if (!is_wp_error($response)) {
            $this->cache_ai_response($prompt, $response);
        } else {
            $this->clear_cache();
        }
        return $response;
    }
} 