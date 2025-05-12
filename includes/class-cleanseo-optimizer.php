<?php

/**
 * Główna klasa optymalizacyjna CleanSEO
 */
class CleanSEO_Optimizer {
    private $ai_background;
    private $ai_content;

    public function __construct($plugin_name, $version) {
        $this->ai_content = new CleanSEO_AI_Content();
        $this->ai_background = new CleanSEO_AI_Background_Process();

        $this->init_hooks();
    }

    private function init_hooks() {
        // Automatyczne generowanie meta title po zapisaniu posta
        add_action('save_post', array($this, 'auto_generate_meta_title'), 10, 2);

        // Frontend – meta tagi + schema
        add_action('wp_head', array($this, 'output_meta_tags'));
        add_action('wp_head', array($this, 'output_schema_markup'));

        // AJAX ręczne przetwarzanie kolejki
        add_action('wp_ajax_cleanseo_process_queue', array($this->ai_background, 'ajax_process_queue'));
    }

    public function auto_generate_meta_title($post_id, $post) {
        if (wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $settings = new CleanSEO_AI_Settings();
        if (!$settings->get_setting('auto_generate')) {
            return;
        }

        $allowed_post_types = $settings->get_setting('auto_generate_post_types', array('post'));
        if (!in_array($post->post_type, $allowed_post_types)) {
            return;
        }

        $queue = new CleanSEO_AI_Queue();
        $queue->add_job('generate_meta_title', array('post_id' => $post_id), 10);
    }

    public function output_meta_tags() {
        if (is_singular()) {
            $post_id = get_the_ID();
            $meta_title = get_post_meta($post_id, '_aioseo_meta_title', true);
            if ($meta_title) {
                echo '<title>' . esc_html($meta_title) . '</title>' . PHP_EOL;
            }
        }
    }

    public function output_schema_markup() {
        if (is_singular()) {
            $post_id = get_the_ID();
            $schema = get_post_meta($post_id, '_aioseo_schema_markup', true);
            if ($schema) {
                echo '<script type="application/ld+json">' . esc_html($schema) . '</script>' . PHP_EOL;
            }
        }
    }
}
