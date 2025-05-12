<?php
/**
 * Klasa obsługująca interfejs administratora CleanSEO
 *
 * @package CleanSEO
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class CleanSEO_Admin {

    private $plugin_name;
    private $version;
    private $logger;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new CleanSEO_Logger();

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Dodaje menu i podstrony do panelu administracyjnego
     */
    public function add_plugin_admin_menu() {
        global $cleanseo_analytics, $cleanseo_ai_admin;

        // Sprawdź, czy obiekty istnieją, aby uniknąć błędów
        if (!isset($cleanseo_analytics) || !isset($cleanseo_ai_admin)) {
            $this->logger->log('error', 'Brak wymaganych komponentów przy tworzeniu menu');
            return;
        }

        // Menu główne
        $main_page = add_menu_page(
            'CleanSEO',
            'CleanSEO',
            'manage_options',
            'cleanseo-main',
            array($this, 'render_main_page'),
            'dashicons-admin-site-alt3',
            25
        );

        // Podmenu Analytics
        $analytics_page = add_submenu_page(
            'cleanseo-main',
            'Analytics',
            'Analytics',
            'manage_options',
            'cleanseo-analytics',
            array($cleanseo_analytics, 'render_dashboard')
        );

        // Podmenu Ustawienia
        $settings_page = add_submenu_page(
            'cleanseo-main',
            'Ustawienia',
            'Ustawienia',
            'manage_options',
            'cleanseo-settings',
            array($this, 'render_settings_page')
        );

        // Podmenu Ustawienia AI (jako podzakładka Ustawień)
        $ai_settings_page = add_submenu_page(
            'cleanseo-settings',
            'Ustawienia AI',
            'Ustawienia AI',
            'manage_options',
            'cleanseo-ai-settings',
            array($cleanseo_ai_admin, 'render_settings_page')
        );
        
        // Dodaj help tabs
        add_action('load-' . $main_page, array($this, 'add_help_tabs'));
        add_action('load-' . $analytics_page, array($this, 'add_analytics_help_tabs'));
        add_action('load-' . $settings_page, array($this, 'add_settings_help_tabs'));
        
        // Dodaj filtr do modyfikacji pierwszego elementu podmenu
        add_filter('submenu_file', array($this, 'highlight_current_submenu'));
    }

    /**
     * Podświetla aktywne podmenu
     * 
     * @param string $submenu_file Aktualny plik podmenu
     * @return string Zmodyfikowany plik podmenu
     */
    public function highlight_current_submenu($submenu_file) {
        global $plugin_page;
        
        // Jeśli jesteśmy na stronie głównej, podświetl pierwszy element podmenu
        if ($plugin_page === 'cleanseo-main') {
            $submenu_file = 'cleanseo-analytics';
        }
        
        return $submenu_file;
    }
    
    /**
     * Dodaje zakładki pomocy dla głównej strony
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        $screen->add_help_tab(array(
            'id'      => 'cleanseo-overview',
            'title'   => __('Przegląd', 'cleanseo-optimizer'),
            'content' => '<p>' . __('CleanSEO Optimizer to zaawansowane narzędzie do optymalizacji SEO dla WordPress. Ta strona główna zawiera podsumowanie najważniejszych funkcji.', 'cleanseo-optimizer') . '</p>'
        ));
        
        $screen->add_help_tab(array(
            'id'      => 'cleanseo-getting-started',
            'title'   => __('Pierwsze kroki', 'cleanseo-optimizer'),
            'content' => '<p>' . __('Aby rozpocząć pracę z CleanSEO, najpierw skonfiguruj podstawowe ustawienia w zakładce Ustawienia.', 'cleanseo-optimizer') . '</p>'
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('Więcej informacji:', 'cleanseo-optimizer') . '</strong></p>' .
            '<p><a href="https://example.com/docs" target="_blank">' . __('Dokumentacja', 'cleanseo-optimizer') . '</a></p>' .
            '<p><a href="https://example.com/support" target="_blank">' . __('Wsparcie techniczne', 'cleanseo-optimizer') . '</a></p>'
        );
    }
    
    /**
     * Dodaje zakładki pomocy dla strony Analytics
     */
    public function add_analytics_help_tabs() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        $screen->add_help_tab(array(
            'id'      => 'cleanseo-analytics-overview',
            'title'   => __('Analityka', 'cleanseo-optimizer'),
            'content' => '<p>' . __('Zakładka Analytics pozwala na śledzenie wyników SEO Twojej strony.', 'cleanseo-optimizer') . '</p>'
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('Więcej informacji:', 'cleanseo-optimizer') . '</strong></p>' .
            '<p><a href="https://example.com/docs/analytics" target="_blank">' . __('Dokumentacja Analytics', 'cleanseo-optimizer') . '</a></p>'
        );
    }
    
    /**
     * Dodaje zakładki pomocy dla strony Ustawienia
     */
    public function add_settings_help_tabs() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        $screen->add_help_tab(array(
            'id'      => 'cleanseo-settings-overview',
            'title'   => __('Ustawienia', 'cleanseo-optimizer'),
            'content' => '<p>' . __('W tej sekcji możesz skonfigurować podstawowe ustawienia wtyczki CleanSEO.', 'cleanseo-optimizer') . '</p>'
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('Więcej informacji:', 'cleanseo-optimizer') . '</strong></p>' .
            '<p><a href="https://example.com/docs/settings" target="_blank">' . __('Dokumentacja Ustawień', 'cleanseo-optimizer') . '</a></p>'
        );
    }
    
    /**
     * Ładuje skrypty i style dla panelu administracyjnego
     */
    public function enqueue_admin_assets($hook) {
        // Sprawdź, czy jesteśmy na stronie pluginu
        if (strpos($hook, 'cleanseo') === false) {
            return;
        }
        
        // Załaduj style główne
        wp_enqueue_style(
            'cleanseo-admin-core',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-admin.css',
            array(),
            $this->version
        );
        
        // Skrypty główne
        wp_enqueue_script(
            'cleanseo-admin-core',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-admin.js',
            array('jquery', 'jquery-ui-tabs'),
            $this->version,
            true
        );
        
        // Zmienne dla JavaScript
        wp_localize_script('cleanseo-admin-core', 'cleanseoAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_admin_nonce'),
            'version' => $this->version,
            'messages' => array(
                'error' => __('Wystąpił błąd podczas przetwarzania żądania.', 'cleanseo-optimizer'),
                'success' => __('Operacja zakończona sukcesem.', 'cleanseo-optimizer'),
                'loading' => __('Ładowanie...', 'cleanseo-optimizer'),
                'saving' => __('Zapisywanie...', 'cleanseo-optimizer')
            )
        ));
        
        // Załaduj dodatkowe zasoby w zależności od strony
        if (strpos($hook, 'cleanseo-settings') !== false) {
            wp_enqueue_style(
                'cleanseo-settings',
                plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-settings.css',
                array('cleanseo-admin-core'),
                $this->version
            );
            
            wp_enqueue_script(
                'cleanseo-settings',
                plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-settings.js',
                array('cleanseo-admin-core'),
                $this->version,
                true
            );
        }
        
        if (strpos($hook, 'cleanseo-analytics') !== false) {
            wp_enqueue_style(
                'cleanseo-analytics',
                plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-analytics.css',
                array('cleanseo-admin-core'),
                $this->version
            );
            
            wp_enqueue_script(
                'cleanseo-analytics',
                plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-analytics.js',
                array('cleanseo-admin-core', 'jquery-ui-datepicker'),
                $this->version,
                true
            );
            
            // Dodaj Chart.js dla wykresów
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
                array(),
                '3.7.1',
                true
            );
        }
    }

    /**
     * Renderuje główną stronę dashboardu
     */
    public function render_main_page() {
        ?>
        <div class="wrap cleanseo-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cleanseo-dashboard-container">
                <div class="cleanseo-dashboard-header">
                    <div class="cleanseo-dashboard-welcome">
                        <h2><?php _e('Witaj w CleanSEO Optimizer', 'cleanseo-optimizer'); ?></h2>
                        <p><?php _e('Zaawansowane narzędzie do optymalizacji SEO dla WordPress', 'cleanseo-optimizer'); ?></p>
                    </div>
                    <div class="cleanseo-dashboard-version">
                        <span><?php printf(__('Wersja: %s', 'cleanseo-optimizer'), $this->version); ?></span>
                    </div>
                </div>
                
                <div class="cleanseo-dashboard-widgets">
                    <div class="cleanseo-widget">
                        <div class="cleanseo-widget-header">
                            <h3><?php _e('Szybki przegląd', 'cleanseo-optimizer'); ?></h3>
                        </div>
                        <div class="cleanseo-widget-content">
                            <?php $this->render_dashboard_stats(); ?>
                        </div>
                    </div>
                    
                    <div class="cleanseo-widget">
                        <div class="cleanseo-widget-header">
                            <h3><?php _e('Ostatnie przekierowania', 'cleanseo-optimizer'); ?></h3>
                        </div>
                        <div class="cleanseo-widget-content">
                            <?php $this->render_dashboard_redirects(); ?>
                        </div>
                    </div>
                    
                    <div class="cleanseo-widget">
                        <div class="cleanseo-widget-header">
                            <h3><?php _e('Szybkie akcje', 'cleanseo-optimizer'); ?></h3>
                        </div>
                        <div class="cleanseo-widget-content">
                            <div class="cleanseo-quick-actions">
                                <a href="<?php echo admin_url('admin.php?page=cleanseo-analytics'); ?>" class="button">
                                    <?php _e('Zobacz Analytics', 'cleanseo-optimizer'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=cleanseo-settings'); ?>" class="button">
                                    <?php _e('Konfiguruj ustawienia', 'cleanseo-optimizer'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=cleanseo-redirects'); ?>" class="button">
                                    <?php _e('Zarządzaj przekierowaniami', 'cleanseo-optimizer'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=cleanseo-openai'); ?>" class="button">
                                    <?php _e('Ustawienia AI', 'cleanseo-optimizer'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-dashboard-stats">
                    <div class="cleanseo-widget cleanseo-widget-full">
                        <div class="cleanseo-widget-header">
                            <h3><?php _e('Aktywność witryny', 'cleanseo-optimizer'); ?></h3>
                        </div>
                        <div class="cleanseo-widget-content">
                            <?php $this->render_dashboard_activity(); ?>
                        </div>
                    </div>
                </div>
                
                <?php do_action('cleanseo_dashboard_widgets'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderuje statystyki dla dashboardu
     */
    private function render_dashboard_stats() {
        // Sprawdź, czy klasa statystyk istnieje
        if (!class_exists('CleanSEO_Stats')) {
            echo '<p>' . __('Moduł statystyk nie jest dostępny.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        $stats = new CleanSEO_Stats();
        $summary = $stats->get_stats_summary(null, null, true);
        
        if (!$summary || !isset($summary->total_page_views)) {
            echo '<p>' . __('Brak danych statystycznych.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        ?>
        <div class="cleanseo-stats-summary">
            <div class="cleanseo-stat-box">
                <span class="cleanseo-stat-value"><?php echo esc_html(number_format_i18n($summary->total_page_views)); ?></span>
                <span class="cleanseo-stat-label"><?php _e('Odsłony', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-stat-box">
                <span class="cleanseo-stat-value"><?php echo esc_html(number_format_i18n($summary->total_unique_visitors)); ?></span>
                <span class="cleanseo-stat-label"><?php _e('Unikalni odwiedzający', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-stat-box">
                <span class="cleanseo-stat-value"><?php echo esc_html(round($summary->avg_bounce_rate, 2)) . '%'; ?></span>
                <span class="cleanseo-stat-label"><?php _e('Średni współczynnik odrzuceń', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-stat-box">
                <span class="cleanseo-stat-value"><?php echo esc_html(round($summary->avg_time_on_site)) . 's'; ?></span>
                <span class="cleanseo-stat-label"><?php _e('Średni czas na stronie', 'cleanseo-optimizer'); ?></span>
            </div>
        </div>
        <div class="cleanseo-view-more">
            <a href="<?php echo admin_url('admin.php?page=cleanseo-analytics'); ?>" class="button button-small">
                <?php _e('Zobacz szczegóły', 'cleanseo-optimizer'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Renderuje ostatnie przekierowania dla dashboardu
     */
    private function render_dashboard_redirects() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_redirects';
        
        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            echo '<p>' . __('Tabela przekierowań nie istnieje.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        $redirects = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY id DESC LIMIT 5",
            ARRAY_A
        );
        
        if (!$redirects) {
            echo '<p>' . __('Brak skonfigurowanych przekierowań.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        ?>
        <table class="cleanseo-redirects-table">
            <thead>
                <tr>
                    <th><?php _e('Źródło', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Cel', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Kod', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Hits', 'cleanseo-optimizer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($redirects as $redirect): ?>
                <tr>
                    <td title="<?php echo esc_attr($redirect['source_url']); ?>">
                        <?php echo esc_html(substr($redirect['source_url'], 0, 30) . (strlen($redirect['source_url']) > 30 ? '...' : '')); ?>
                    </td>
                    <td title="<?php echo esc_attr($redirect['target_url']); ?>">
                        <?php echo esc_html(substr($redirect['target_url'], 0, 30) . (strlen($redirect['target_url']) > 30 ? '...' : '')); ?>
                    </td>
                    <td><?php echo esc_html($redirect['status_code']); ?></td>
                    <td><?php echo esc_html($redirect['hits']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="cleanseo-view-more">
            <a href="<?php echo admin_url('admin.php?page=cleanseo-redirects'); ?>" class="button button-small">
                <?php _e('Zarządzaj przekierowaniami', 'cleanseo-optimizer'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Renderuje aktywność witryny dla dashboardu
     */
    private function render_dashboard_activity() {
        // Sprawdź, czy klasa logów istnieje
        if (!class_exists('CleanSEO_Logger')) {
            echo '<p>' . __('Moduł logów nie jest dostępny.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        $logger = new CleanSEO_Logger();
        $logs = $logger->get_recent_logs(10);
        
        if (empty($logs)) {
            echo '<p>' . __('Brak danych o aktywności.', 'cleanseo-optimizer') . '</p>';
            return;
        }
        
        ?>
        <table class="cleanseo-activity-table">
            <thead>
                <tr>
                    <th><?php _e('Data', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Akcja', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Poziom', 'cleanseo-optimizer'); ?></th>
                    <th><?php _e('Szczegóły', 'cleanseo-optimizer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="cleanseo-log-level-<?php echo esc_attr($log->level); ?>">
                    <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                    <td><?php echo esc_html($log->action); ?></td>
                    <td><?php echo esc_html($log->level); ?></td>
                    <td>
                        <?php 
                        $data = json_decode($log->data, true);
                        if (is_array($data)) {
                            $output = array();
                            foreach ($data as $key => $value) {
                                if (is_array($value)) {
                                    $value = 'array[' . count($value) . ']';
                                } elseif (is_object($value)) {
                                    $value = 'object';
                                }
                                $output[] = esc_html($key) . ': ' . esc_html($value);
                            }
                            echo implode(', ', $output);
                        } else {
                            echo esc_html($log->data);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renderuje stronę ustawień
     */
    public function render_settings_page() {
        ?>
        <div class="wrap cleanseo-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=cleanseo-settings'); ?>" class="nav-tab nav-tab-active">
                    <?php _e('Ogólne', 'cleanseo-optimizer'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=cleanseo-ai-settings'); ?>" class="nav-tab">
                    <?php _e('Ustawienia AI', 'cleanseo-optimizer'); ?>
                </a>
            </h2>
            
            <div class="cleanseo-settings-container">
                <form method="post" action="options.php" id="cleanseo-settings-form">
                    <?php settings_fields('cleanseo_general_settings'); ?>
                    <?php do_settings_sections('cleanseo_general_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cleanseo_enable_analytics"><?php _e('Włącz Analytics', 'cleanseo-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="cleanseo_enable_analytics" name="cleanseo_enable_analytics" value="1" <?php checked(get_option('cleanseo_enable_analytics', '1'), '1'); ?>>
                                <p class="description"><?php _e('Włącz/wyłącz funkcje analityczne', 'cleanseo-optimizer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cleanseo_enable_ai"><?php _e('Włącz AI', 'cleanseo-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="cleanseo_enable_ai" name="cleanseo_enable_ai" value="1" <?php checked(get_option('cleanseo_enable_ai', '1'), '1'); ?>>
                                <p class="description"><?php _e('Włącz/wyłącz funkcje sztucznej inteligencji', 'cleanseo-optimizer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cleanseo_default_language"><?php _e('Domyślny język', 'cleanseo-optimizer'); ?></label>
                            </th>
                            <td>
                                <select id="cleanseo_default_language" name="cleanseo_default_language">
                                    <option value="pl" <?php selected(get_option('cleanseo_default_language', 'pl'), 'pl'); ?>><?php _e('Polski', 'cleanseo-optimizer'); ?></option>
                                    <option value="en" <?php selected(get_option('cleanseo_default_language', 'pl'), 'en'); ?>><?php _e('Angielski', 'cleanseo-optimizer'); ?></option>
                                    <option value="de" <?php selected(get_option('cleanseo_default_language', 'pl'), 'de'); ?>><?php _e('Niemiecki', 'cleanseo-optimizer'); ?></option>
                                    <option value="fr" <?php selected(get_option('cleanseo_default_language', 'pl'), 'fr'); ?>><?php _e('Francuski', 'cleanseo-optimizer'); ?></option>
                                </select>
                                <p class="description"><?php _e('Wybierz domyślny język dla generowanej treści', 'cleanseo-optimizer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cleanseo_sitemap_enabled"><?php _e('Włącz mapy witryny', 'cleanseo-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="cleanseo_sitemap_enabled" name="cleanseo_sitemap_enabled" value="1" <?php checked(get_option('cleanseo_sitemap_enabled', '1'), '1'); ?>>
                                <p class="description"><?php _e('Włącz/wyłącz automatyczne generowanie map witryny', 'cleanseo-optimizer'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cleanseo_log_level"><?php _e('Poziom logowania', 'cleanseo-optimizer'); ?></label>
                            </th>
                            <td>
                                <select id="cleanseo_log_level" name="cleanseo_log_level">
                                    <option value="error" <?php selected(get_option('cleanseo_log_level', 'info'), 'error'); ?>><?php _e('Tylko błędy', 'cleanseo-optimizer'); ?></option>
                                    <option value="warning" <?php selected(get_option('cleanseo_log_level', 'info'), 'warning'); ?>><?php _e('Ostrzeżenia i błędy', 'cleanseo-optimizer'); ?></option>
                                    <option value="info" <?php selected(get_option('cleanseo_log_level', 'info'), 'info'); ?>><?php _e('Informacje, ostrzeżenia i błędy', 'cleanseo-optimizer'); ?></option>
                                    <option value="debug" <?php selected(get_option('cleanseo_log_level', 'info'), 'debug'); ?>><?php _e('Wszystko (debugowanie)', 'cleanseo-optimizer'); ?></option>
                                </select>
                                <p class="description"><?php _e('Wybierz poziom szczegółowości logów', 'cleanseo-optimizer'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="cleanseo-settings-section">
                        <h2><?php _e('Zaawansowane ustawienia', 'cleanseo-optimizer'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_cache_expire"><?php _e('Wygasanie cache (sekundy)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="cleanseo_cache_expire" name="cleanseo_cache_expire" value="<?php echo esc_attr(get_option('cleanseo_cache_expire', '3600')); ?>" class="small-text" min="0" step="1">
                                    <p class="description"><?php _e('Czas wygasania cache w sekundach (0 = wyłączone)', 'cleanseo-optimizer'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_logs_retention"><?php _e('Przechowywanie logów (dni)', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="cleanseo_logs_retention" name="cleanseo_logs_retention" value="<?php echo esc_attr(get_option('cleanseo_logs_retention', '30')); ?>" class="small-text" min="1" step="1">
                                    <p class="description"><?php _e('Liczba dni przechowywania logów', 'cleanseo-optimizer'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cleanseo_db_cleanup"><?php _e('Automatyczne czyszczenie bazy danych', 'cleanseo-optimizer'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="cleanseo_db_cleanup" name="cleanseo_db_cleanup" value="1" <?php checked(get_option('cleanseo_db_cleanup', '1'), '1'); ?>>
                                    <p class="description"><?php _e('Włącz/wyłącz automatyczne czyszczenie starych danych w bazie', 'cleanseo-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <?php submit_button(__('Zapisz ustawienia', 'cleanseo-optimizer')); ?>
                </form>
                
                <div class="cleanseo-settings-tools">
                    <h2><?php _e('Narzędzia', 'cleanseo-optimizer'); ?></h2>
                    
                    <div class="cleanseo-tools-buttons">
                        <button id="cleanseo-clear-cache" class="button button-secondary">
                            <?php _e('Wyczyść cache', 'cleanseo-optimizer'); ?>
                        </button>
                        
                        <button id="cleanseo-export-settings" class="button button-secondary">
                            <?php _e('Eksportuj ustawienia', 'cleanseo-optimizer'); ?>
                        </button>
                        
                        <button id="cleanseo-import-settings" class="button button-secondary">
                            <?php _e('Importuj ustawienia', 'cleanseo-optimizer'); ?>
                        </button>
                    </div>
                    
                    <div id="cleanseo-import-settings-container" style="display: none; margin-top: 15px;">
                        <textarea id="cleanseo-import-settings-data" class="large-text" rows="5" placeholder="<?php esc_attr_e('Wklej dane ustawień', 'cleanseo-optimizer'); ?>"></textarea>
                        <button id="cleanseo-import-settings-submit" class="button button-primary" style="margin-top: 10px;">
                            <?php _e('Importuj', 'cleanseo-optimizer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Rejestruje ustawienia
     */
    public function register_settings() {
        // Główne ustawienia
        register_setting('cleanseo_general_settings', 'cleanseo_enable_analytics', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_enable_ai', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_default_language', array(
            'type' => 'string',
            'default' => 'pl',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_sitemap_enabled', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_log_level', array(
            'type' => 'string',
            'default' => 'info',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Zaawansowane ustawienia
        register_setting('cleanseo_general_settings', 'cleanseo_cache_expire', array(
            'type' => 'integer',
            'default' => 3600,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_logs_retention', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('cleanseo_general_settings', 'cleanseo_db_cleanup', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
    }
}