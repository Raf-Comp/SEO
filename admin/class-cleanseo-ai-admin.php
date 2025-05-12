<?php
/**
 * Klasa odpowiedzialna za panel administracyjny AI
 */
if (!defined('ABSPATH')) {
    exit;
}

class CleanSEO_AI_Admin {
    private $ai_models;
    private $db;
    private $logger;
    private $plugin_name;
    private $version;

    /**
     * Konstruktor
     */
    public function __construct($plugin_name = 'cleanseo-optimizer', $version = '1.0.0') {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->ai_models = new CleanSEO_AI_Models();
        $this->db = new CleanSEO_Database();
        $this->logger = new CleanSEO_Logger();

        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_cleanseo_verify_api', array($this, 'verify_api_key'));
        add_action('wp_ajax_cleanseo_get_models', array($this, 'get_available_models'));
        add_action('wp_ajax_cleanseo_test_ai_generation', array($this, 'test_ai_generation'));
        add_action('wp_ajax_cleanseo_clear_ai_cache', array($this, 'clear_ai_cache'));
    }

    /**
     * Dodaj strony menu
     */
    public function add_menu_page() {
        add_submenu_page(
            'cleanseo-optimizer',
            'Ustawienia AI',
            'Ustawienia AI',
            'manage_options',
            'cleanseo-ai-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Zarejestruj ustawienia
     */
    public function register_settings() {
        register_setting('cleanseo_ai_settings', 'cleanseo_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
            'default' => ''
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo'
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_max_tokens', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2000
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_temperature', array(
            'type' => 'float',
            'sanitize_callback' => array($this, 'sanitize_float'),
            'default' => 0.7
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_frequency_penalty', array(
            'type' => 'float',
            'sanitize_callback' => array($this, 'sanitize_float'),
            'default' => 0
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_presence_penalty', array(
            'type' => 'float',
            'sanitize_callback' => array($this, 'sanitize_float'),
            'default' => 0
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_top_p', array(
            'type' => 'float',
            'sanitize_callback' => array($this, 'sanitize_float'),
            'default' => 1
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_retry_attempts', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_retry_delay', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 1000
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_timeout', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_cost_tracking', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_budget_limit', array(
            'type' => 'float',
            'sanitize_callback' => array($this, 'sanitize_float'),
            'default' => 0
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_alert_threshold', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 80
        ));

        register_setting('cleanseo_ai_settings', 'cleanseo_notification_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ));
        
        register_setting('cleanseo_ai_settings', 'cleanseo_cache_enabled', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        
        register_setting('cleanseo_ai_settings', 'cleanseo_cache_time', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 86400 // 24 godziny
        ));
        
        register_setting('cleanseo_ai_settings', 'cleanseo_auto_generate', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
    }

    /**
     * Załaduj skrypty i style
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'cleanseo-optimizer_page_cleanseo-ai-settings') {
            return;
        }

        wp_enqueue_style(
            'cleanseo-ai-admin',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-ai-admin.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'cleanseo-ai-admin',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-ai-admin.js',
            array('jquery', 'jquery-ui-tabs', 'jquery-ui-tooltip'),
            $this->version,
            true
        );

        wp_localize_script('cleanseo-ai-admin', 'cleanseoAI', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_ai_nonce'),
            'i18n' => array(
                'verifySuccess' => __('Klucz API został zweryfikowany pomyślnie', 'cleanseo-optimizer'),
                'verifyError' => __('Nie udało się zweryfikować klucza API', 'cleanseo-optimizer'),
                'modelsError' => __('Nie udało się załadować dostępnych modeli', 'cleanseo-optimizer'),
                'saveSuccess' => __('Ustawienia zostały zapisane pomyślnie', 'cleanseo-optimizer'),
                'saveError' => __('Nie udało się zapisać ustawień', 'cleanseo-optimizer'),
                'testSuccess' => __('Test generowania udany', 'cleanseo-optimizer'),
                'testError' => __('Test generowania nieudany', 'cleanseo-optimizer'),
                'clearCacheSuccess' => __('Cache AI został wyczyszczony', 'cleanseo-optimizer'),
                'clearCacheError' => __('Nie udało się wyczyścić cache AI', 'cleanseo-optimizer'),
                'confirmClearCache' => __('Czy na pewno chcesz wyczyścić cały cache AI?', 'cleanseo-optimizer')
            )
        ));
    }

    /**
     * Renderuj stronę ustawień
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->db->get_settings();
        $models = $this->ai_models->get_available_models();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div id="cleanseo-ai-tabs" class="cleanseo-tabs">
                <ul class="cleanseo-tabs-nav">
                    <li><a href="#tab-api"><?php _e('Konfiguracja API', 'cleanseo-optimizer'); ?></a></li>
                    <li><a href="#tab-generation"><?php _e('Ustawienia generowania', 'cleanseo-optimizer'); ?></a></li>
                    <li><a href="#tab-advanced"><?php _e('Ustawienia zaawansowane', 'cleanseo-optimizer'); ?></a></li>
                    <li><a href="#tab-cost"><?php _e('Zarządzanie kosztami', 'cleanseo-optimizer'); ?></a></li>
                    <li><a href="#tab-cache"><?php _e('Cache', 'cleanseo-optimizer'); ?></a></li>
                    <li><a href="#tab-test"><?php _e('Testowanie', 'cleanseo-optimizer'); ?></a></li>
                </ul>
                
                <form method="post" action="options.php" id="cleanseo-ai-settings-form">
                    <?php settings_fields('cleanseo_ai_settings'); ?>
                    
                    <div id="tab-api" class="cleanseo-tab-content">
                        <h2><?php _e('Konfiguracja API', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_api_key"><?php _e('Klucz API', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="cleanseo_api_key" 
                                           name="cleanseo_api_key" 
                                           value="<?php echo esc_attr($settings->api_key); ?>" 
                                           class="regular-text">
                                    <button type="button" 
                                            id="verify-api-key" 
                                            class="button button-secondary">
                                        <?php _e('Weryfikuj klucz API', 'cleanseo-optimizer'); ?>
                                    </button>
                                    <button type="button" 
                                            id="toggle-api-key" 
                                            class="button button-secondary">
                                        <?php _e('Pokaż/Ukryj', 'cleanseo-optimizer'); ?>
                                    </button>
                                    <p class="description">
                                        <?php _e('Wprowadź swój klucz API OpenAI. Możesz go uzyskać na ', 'cleanseo-optimizer'); ?>
                                        <a href="https://platform.openai.com/account/api-keys" target="_blank">
                                            <?php _e('platformie OpenAI', 'cleanseo-optimizer'); ?>
                                        </a>
                                    </p>
                                    <div id="api-key-status"></div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_model"><?php _e('Model', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <select id="cleanseo_model" name="cleanseo_model" class="regular-text">
                                        <?php foreach ($models as $model): ?>
                                            <option value="<?php echo esc_attr($model['id']); ?>" 
                                                    <?php selected($settings->model, $model['id']); ?>>
                                                <?php echo esc_html($model['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" 
                                            id="refresh-models" 
                                            class="button button-secondary">
                                        <?php _e('Odśwież listę modeli', 'cleanseo-optimizer'); ?>
                                    </button>
                                    <p class="description">
                                        <?php _e('Wybierz model AI do generowania treści', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="tab-generation" class="cleanseo-tab-content">
                        <h2><?php _e('Ustawienia generowania', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_max_tokens"><?php _e('Maksymalna liczba tokenów', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_max_tokens" 
                                           name="cleanseo_max_tokens" 
                                           value="<?php echo esc_attr($settings->max_tokens); ?>" 
                                           class="small-text" 
                                           min="1" 
                                           max="32000">
                                    <p class="description">
                                        <?php _e('Maksymalna liczba tokenów do wygenerowania. Wpływa na długość tekstu i koszty.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_temperature"><?php _e('Temperatura', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="range" 
                                           id="cleanseo_temperature_range" 
                                           min="0" 
                                           max="2" 
                                           step="0.1"
                                           value="<?php echo esc_attr($settings->temperature); ?>">
                                    <input type="number" 
                                           id="cleanseo_temperature" 
                                           name="cleanseo_temperature" 
                                           value="<?php echo esc_attr($settings->temperature); ?>" 
                                           class="small-text" 
                                           min="0" 
                                           max="2" 
                                           step="0.1">
                                    <p class="description">
                                        <?php _e('Kontroluje losowość generowanych treści: 0 to skupienie i przewidywalność, 2 to maksymalna kreatywność.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_frequency_penalty"><?php _e('Kara za częstotliwość', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="range" 
                                           id="cleanseo_frequency_penalty_range" 
                                           min="-2" 
                                           max="2" 
                                           step="0.1"
                                           value="<?php echo esc_attr($settings->frequency_penalty); ?>">
                                    <input type="number" 
                                           id="cleanseo_frequency_penalty" 
                                           name="cleanseo_frequency_penalty" 
                                           value="<?php echo esc_attr($settings->frequency_penalty); ?>" 
                                           class="small-text" 
                                           min="-2" 
                                           max="2" 
                                           step="0.1">
                                    <p class="description">
                                        <?php _e('Zmniejsza powtarzanie tych samych słów i fraz. Wyższe wartości zmniejszają powtórzenia.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_presence_penalty"><?php _e('Kara za obecność', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="range" 
                                           id="cleanseo_presence_penalty_range" 
                                           min="-2" 
                                           max="2" 
                                           step="0.1"
                                           value="<?php echo esc_attr($settings->presence_penalty); ?>">
                                    <input type="number" 
                                           id="cleanseo_presence_penalty" 
                                           name="cleanseo_presence_penalty" 
                                           value="<?php echo esc_attr($settings->presence_penalty); ?>" 
                                           class="small-text" 
                                           min="-2" 
                                           max="2" 
                                           step="0.1">
                                    <p class="description">
                                        <?php _e('Zmniejsza powtarzanie tych samych tematów. Wyższe wartości zachęcają do poruszania nowych tematów.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_top_p"><?php _e('Top P', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="range" 
                                           id="cleanseo_top_p_range" 
                                           min="0" 
                                           max="1" 
                                           step="0.05"
                                           value="<?php echo esc_attr($settings->top_p); ?>">
                                    <input type="number" 
                                           id="cleanseo_top_p" 
                                           name="cleanseo_top_p" 
                                           value="<?php echo esc_attr($settings->top_p); ?>" 
                                           class="small-text" 
                                           min="0" 
                                           max="1" 
                                           step="0.05">
                                    <p class="description">
                                        <?php _e('Kontroluje różnorodność poprzez próbkowanie jądrowe. Niższe wartości = bardziej przewidywalne wyniki.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_auto_generate"><?php _e('Automatyczne generowanie', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                            id="cleanseo_auto_generate" 
                                            name="cleanseo_auto_generate" 
                                            value="1" 
                                            <?php checked($settings->auto_generate); ?>>
                                        <?php _e('Włącz automatyczne generowanie meta tagów', 'cleanseo-optimizer'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Automatycznie generuj meta tagi dla nowych wpisów i stron.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="tab-advanced" class="cleanseo-tab-content">
                        <h2><?php _e('Ustawienia zaawansowane', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_retry_attempts"><?php _e('Liczba prób', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_retry_attempts" 
                                           name="cleanseo_retry_attempts" 
                                           value="<?php echo esc_attr($settings->retry_attempts); ?>" 
                                           class="small-text" 
                                           min="1" 
                                           max="5">
                                    <p class="description">
                                        <?php _e('Liczba prób ponowienia dla nieudanych wywołań API', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_retry_delay"><?php _e('Opóźnienie między próbami (ms)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_retry_delay" 
                                           name="cleanseo_retry_delay" 
                                           value="<?php echo esc_attr($settings->retry_delay); ?>" 
                                           class="small-text" 
                                           min="100" 
                                           max="5000" 
                                           step="100">
                                    <p class="description">
                                        <?php _e('Opóźnienie między próbami w milisekundach', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_timeout"><?php _e('Limit czasu (s)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_timeout" 
                                           name="cleanseo_timeout" 
                                           value="<?php echo esc_attr($settings->timeout); ?>" 
                                           class="small-text" 
                                           min="5" 
                                           max="60">
                                    <p class="description">
                                        <?php _e('Limit czasu dla żądań API w sekundach', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="tab-cost" class="cleanseo-tab-content">
                        <h2><?php _e('Zarządzanie kosztami', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_cost_tracking"><?php _e('Śledzenie kosztów', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="cleanseo_cost_tracking" 
                                               name="cleanseo_cost_tracking" 
                                               value="1" 
                                               <?php checked($settings->cost_tracking); ?>>
                                        <?php _e('Włącz śledzenie kosztów', 'cleanseo-optimizer'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Śledź koszty użycia API. Umożliwia monitorowanie i ograniczanie wydatków.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_budget_limit"><?php _e('Limit budżetu (PLN)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_budget_limit" 
                                           name="cleanseo_budget_limit" 
                                           value="<?php echo esc_attr($settings->budget_limit); ?>" 
                                           class="small-text" 
                                           min="0" 
                                           step="0.01">
                                    <p class="description">
                                        <?php _e('Miesięczny limit budżetu na użycie API (0 = brak limitu)', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_alert_threshold"><?php _e('Próg alertu (%)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_alert_threshold" 
                                           name="cleanseo_alert_threshold" 
                                           value="<?php echo esc_attr($settings->alert_threshold); ?>" 
                                           class="small-text" 
                                           min="0" 
                                           max="100">
                                    <p class="description">
                                        <?php _e('Procent limitu budżetu do wywołania alertów', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_notification_email"><?php _e('Email powiadomień', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="email" 
                                           id="cleanseo_notification_email" 
                                           name="cleanseo_notification_email" 
                                           value="<?php echo esc_attr($settings->notification_email); ?>" 
                                           class="regular-text">
                                    <p class="description">
                                        <?php _e('Adres email do powiadomień o budżecie', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($settings->cost_tracking): ?>
                        <div class="cleanseo-cost-stats">
                            <h3><?php _e('Statystyki wykorzystania', 'cleanseo-optimizer'); ?></h3>
                            <?php $this->render_cost_stats(); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="tab-cache" class="cleanseo-tab-content">
                        <h2><?php _e('Ustawienia cache', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_cache_enabled"><?php _e('Cache AI', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="cleanseo_cache_enabled" 
                                               name="cleanseo_cache_enabled" 
                                               value="1" 
                                               <?php checked($settings->cache_enabled); ?>>
                                        <?php _e('Włącz cache dla odpowiedzi AI', 'cleanseo-optimizer'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Przechowuje odpowiedzi AI, aby zmniejszyć liczbę wywołań API i koszty.', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_cache_time"><?php _e('Czas trwania cache (sekundy)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cleanseo_cache_time" 
                                           name="cleanseo_cache_time" 
                                           value="<?php echo esc_attr($settings->cache_time); ?>" 
                                           class="regular-text"
                                           min="0">
                                    <p class="description">
                                        <?php _e('Czas w sekundach, przez który odpowiedź AI jest przechowywana w cache (0 = bez limitu)', 'cleanseo-optimizer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="cleanseo-cache-stats">
                            <h3><?php _e('Statystyki cache', 'cleanseo-optimizer'); ?></h3>
                            <?php $this->render_cache_stats(); ?>
                            
                            <div class="cleanseo-cache-actions">
                                <button type="button" id="cleanseo_clear_cache" class="button button-secondary">
                                    <?php _e('Wyczyść cache AI', 'cleanseo-optimizer'); ?>
                                </button>
                                <span id="cleanseo_clear_cache_status"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="tab-test" class="cleanseo-tab-content">
                        <h2><?php _e('Testowanie generowania AI', 'cleanseo-optimizer'); ?></h2>
                        
                        <div class="cleanseo-test-ai">
                            <p>
                                <?php _e('Możesz przetestować ustawienia generowania AI poniżej. Ten test pomoże sprawdzić poprawność konfiguracji i jakość generowanych tekstów.', 'cleanseo-optimizer'); ?>
                            </p>
                            
                            <div class="cleanseo-test-input">
                                <label for="cleanseo_test_prompt"><?php _e('Wprowadź tekst dla AI:', 'cleanseo-optimizer'); ?></label>
                                <textarea id="cleanseo_test_prompt" rows="4" class="large-text"><?php echo esc_textarea(__('Wygeneruj meta opis dla strony o tematyce: SEO dla małych firm', 'cleanseo-optimizer')); ?></textarea>
                            </div>
                            
                            <div class="cleanseo-test-actions">
                                <button type="button" id="cleanseo_test_ai" class="button button-primary">
                                    <?php _e('Testuj generowanie', 'cleanseo-optimizer'); ?>
                                </button>
                                <span class="spinner"></span>
                            </div>
                            
                            <div id="cleanseo_test_result" class="cleanseo-test-result" style="display: none;">
                                <h3><?php _e('Wynik testu:', 'cleanseo-optimizer'); ?></h3>
                                <div id="cleanseo_test_output" class="cleanseo-test-output"></div>
                                <div id="cleanseo_test_stats" class="cleanseo-test-stats"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-submit-button">
                        <?php submit_button(__('Zapisz wszystkie ustawienia', 'cleanseo-optimizer')); ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderuj statystyki kosztów
     */
    private function render_cost_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_openai_logs';
        
        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<p>' . __('Tabela logów OpenAI nie istnieje.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        // Pobierz statystyki dla bieżącego miesiąca
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        $current_month_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as requests,
                SUM(tokens) as tokens,
                SUM(cost) as cost
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s",
            $current_month_start,
            $current_month_end
        ));
        
        // Pobierz statystyki dla poprzedniego miesiąca
        $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
        
        $last_month_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as requests,
                SUM(tokens) as tokens,
                SUM(cost) as cost
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s",
            $last_month_start,
            $last_month_end
        ));
        
        // Pobierz statystyki dla wszystkich czasów
        $all_time_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as requests,
                SUM(tokens) as tokens,
                SUM(cost) as cost
            FROM $table_name"
        );
        
        // Pobierz limit budżetu
        $budget_limit = get_option('cleanseo_budget_limit', 0);
        $budget_percentage = 0;
        
        if ($budget_limit > 0 && $current_month_stats->cost > 0) {
            $budget_percentage = min(100, round(($current_month_stats->cost / $budget_limit) * 100));
        }
        
        ?>
        <div class="cleanseo-cost-stats-container">
            <div class="cleanseo-cost-period">
                <h4><?php _e('Bieżący miesiąc', 'cleanseo-optimizer'); ?></h4>
                <div class="cleanseo-cost-data">
                    <p><?php echo sprintf(__('Zapytania: %d', 'cleanseo-optimizer'), $current_month_stats->requests); ?></p>
                    <p><?php echo sprintf(__('Tokeny: %d', 'cleanseo-optimizer'), $current_month_stats->tokens); ?></p>
                    <p><?php echo sprintf(__('Koszt: %.2f PLN', 'cleanseo-optimizer'), $current_month_stats->cost); ?></p>
                    
                    <?php if ($budget_limit > 0): ?>
                    <div class="cleanseo-budget-bar">
                        <div class="cleanseo-budget-progress" style="width: <?php echo esc_attr($budget_percentage); ?>%"></div>
                    </div>
                    <p><?php echo sprintf(__('Wykorzystanie budżetu: %.2f PLN z %.2f PLN (%.1f%%)', 'cleanseo-optimizer'), $current_month_stats->cost, $budget_limit, $budget_percentage); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cleanseo-cost-period">
                <h4><?php _e('Poprzedni miesiąc', 'cleanseo-optimizer'); ?></h4>
                <div class="cleanseo-cost-data">
                    <p><?php echo sprintf(__('Zapytania: %d', 'cleanseo-optimizer'), $last_month_stats->requests); ?></p>
                    <p><?php echo sprintf(__('Tokeny: %d', 'cleanseo-optimizer'), $last_month_stats->tokens); ?></p>
                    <p><?php echo sprintf(__('Koszt: %.2f PLN', 'cleanseo-optimizer'), $last_month_stats->cost); ?></p>
                </div>
            </div>
            
            <div class="cleanseo-cost-period">
                <h4><?php _e('Wszystkie czasy', 'cleanseo-optimizer'); ?></h4>
                <div class="cleanseo-cost-data">
                <p><?php echo sprintf(__('Zapytania: %d', 'cleanseo-optimizer'), $all_time_stats->requests); ?></p>
                    <p><?php echo sprintf(__('Tokeny: %d', 'cleanseo-optimizer'), $all_time_stats->tokens); ?></p>
                    <p><?php echo sprintf(__('Koszt: %.2f PLN', 'cleanseo-optimizer'), $all_time_stats->cost); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderuj statystyki cache
     */
    private function render_cache_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_ai_cache';
        
        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<p>' . __('Tabela cache AI nie istnieje.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        // Pobierz statystyki cache
        $cache_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as entries,
                SUM(JSON_LENGTH(response)) as response_size,
                MIN(created_at) as oldest_entry,
                MAX(created_at) as newest_entry
            FROM $table_name"
        );
        
        // Pobierz liczbę trafionych cache
        $cache_hits = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE hits > 0"
        );
        
        // Oblicz procent trafionych cache
        $hit_percentage = 0;
        if ($cache_stats->entries > 0) {
            $hit_percentage = round(($cache_hits / $cache_stats->entries) * 100);
        }
        
        // Oblicz teoretyczne oszczędności
        $avg_cost_per_request = 0.05; // Załóżmy średni koszt 0.05 PLN za zapytanie
        $saved_costs = $avg_cost_per_request * $cache_hits;
        
        ?>
        <div class="cleanseo-cache-stats-container">
            <div class="cleanseo-cache-data">
                <p><?php echo sprintf(__('Liczba wpisów w cache: %d', 'cleanseo-optimizer'), $cache_stats->entries); ?></p>
                <p><?php echo sprintf(__('Całkowity rozmiar odpowiedzi: %d KB', 'cleanseo-optimizer'), round($cache_stats->response_size / 1024)); ?></p>
                <p><?php echo sprintf(__('Najstarszy wpis: %s', 'cleanseo-optimizer'), $cache_stats->oldest_entry ? date_i18n('Y-m-d H:i:s', strtotime($cache_stats->oldest_entry)) : __('Brak', 'cleanseo-optimizer')); ?></p>
                <p><?php echo sprintf(__('Najnowszy wpis: %s', 'cleanseo-optimizer'), $cache_stats->newest_entry ? date_i18n('Y-m-d H:i:s', strtotime($cache_stats->newest_entry)) : __('Brak', 'cleanseo-optimizer')); ?></p>
                <p><?php echo sprintf(__('Procent trafionych cache: %d%%', 'cleanseo-optimizer'), $hit_percentage); ?></p>
                <p><?php echo sprintf(__('Szacowane oszczędności: %.2f PLN', 'cleanseo-optimizer'), $saved_costs); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Weryfikuj klucz API
     */
    public function verify_api_key() {
        // Sprawdź nonce
        check_ajax_referer('cleanseo_ai_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Niewystarczające uprawnienia', 'cleanseo-optimizer'));
        }

        // Pobierz i sanityzuj klucz API
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(__('Klucz API nie może być pusty', 'cleanseo-optimizer'));
        }

        try {
            // Próba weryfikacji klucza API
            $result = $this->ai_models->verify_api_key($api_key);
            
            if ($result) {
                $this->logger->log('info', 'Klucz API został pomyślnie zweryfikowany');
                wp_send_json_success(array(
                    'message' => __('Klucz API został zweryfikowany pomyślnie', 'cleanseo-optimizer')
                ));
            } else {
                throw new Exception(__('Weryfikacja klucza API nie powiodła się', 'cleanseo-optimizer'));
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Weryfikacja klucza API nie powiodła się', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Pobierz dostępne modele
     */
    public function get_available_models() {
        // Sprawdź nonce
        check_ajax_referer('cleanseo_ai_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Niewystarczające uprawnienia', 'cleanseo-optimizer'));
        }

        try {
            // Próba pobrania dostępnych modeli
            $models = $this->ai_models->get_available_models();
            
            if (!empty($models)) {
                $this->logger->log('info', 'Pobrano listę dostępnych modeli AI', array(
                    'count' => count($models)
                ));
                wp_send_json_success($models);
            } else {
                throw new Exception(__('Nie udało się pobrać listy modeli', 'cleanseo-optimizer'));
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Nie udało się pobrać dostępnych modeli', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Testuj generowanie AI
     */
    public function test_ai_generation() {
        // Sprawdź nonce
        check_ajax_referer('cleanseo_ai_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Niewystarczające uprawnienia', 'cleanseo-optimizer'));
        }

        // Pobierz i sanityzuj prompt
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt nie może być pusty', 'cleanseo-optimizer'));
        }

        try {
            // Inicjalizuj klasę OpenAI
            $openai = new CleanSEO_OpenAI();
            
            // Pobierz aktualne ustawienia
            $settings = $this->db->get_settings();
            
            // Ustaw parametry generowania
            $params = array(
                'model' => $settings->model,
                'prompt' => $prompt,
                'max_tokens' => $settings->max_tokens,
                'temperature' => $settings->temperature,
                'frequency_penalty' => $settings->frequency_penalty,
                'presence_penalty' => $settings->presence_penalty,
                'top_p' => $settings->top_p
            );
            
            // Wyłącz cache dla testu
            add_filter('cleanseo_use_cache', '__return_false');
            
            // Czas rozpoczęcia
            $start_time = microtime(true);
            
            // Wygeneruj odpowiedź
            $response = $openai->generate_completion($params);
            
            // Czas zakończenia
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000); // w milisekundach
            
            // Przygotuj statystyki
            $stats = array(
                'execution_time' => $execution_time,
                'model' => $settings->model,
                'tokens_input' => isset($response['usage']['prompt_tokens']) ? $response['usage']['prompt_tokens'] : 0,
                'tokens_output' => isset($response['usage']['completion_tokens']) ? $response['usage']['completion_tokens'] : 0,
                'tokens_total' => isset($response['usage']['total_tokens']) ? $response['usage']['total_tokens'] : 0
            );
            
            $this->logger->log('info', 'Test generowania AI wykonany pomyślnie', array(
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($response['text']),
                'stats' => $stats
            ));
            
            wp_send_json_success(array(
                'text' => $response['text'],
                'stats' => $stats
            ));
        } catch (Exception $e) {
            $this->logger->log('error', 'Test generowania AI nie powiódł się', array(
                'error' => $e->getMessage(),
                'prompt' => $prompt
            ));
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Wyczyść cache AI
     */
    public function clear_ai_cache() {
        // Sprawdź nonce
        check_ajax_referer('cleanseo_ai_nonce', 'nonce');

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Niewystarczające uprawnienia', 'cleanseo-optimizer'));
        }

        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'seo_ai_cache';
            
            // Sprawdź czy tabela istnieje
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                throw new Exception(__('Tabela cache AI nie istnieje', 'cleanseo-optimizer'));
            }
            
            // Zlicz wpisy przed usunięciem
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // Wyczyść tabelę
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            
            if ($result !== false) {
                $this->logger->log('info', 'Cache AI został wyczyszczony', array(
                    'entries_removed' => $count_before
                ));
                
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('Cache AI został wyczyszczony. Usunięto %d wpisów.', 'cleanseo-optimizer'),
                        $count_before
                    )
                ));
            } else {
                throw new Exception($wpdb->last_error);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Nie udało się wyczyścić cache AI', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Sanityzacja klucza API
     */
    public function sanitize_api_key($key) {
        $key = sanitize_text_field($key);
        
        // Format klucza API OpenAI to 'sk-' + 32+ znaków
        if (!empty($key) && !preg_match('/^sk-[a-zA-Z0-9]{32,}$/', $key)) {
            add_settings_error(
                'cleanseo_ai_settings',
                'invalid_api_key',
                __('Nieprawidłowy format klucza API', 'cleanseo-optimizer')
            );
            
            $this->logger->log('warning', 'Próba zapisania nieprawidłowego formatu klucza API');
            
            return '';
        }
        
        return $key;
    }

    /**
     * Sanityzacja wartości zmiennoprzecinkowej
     */
    public function sanitize_float($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}