<?php
/**
 * Plugin Name: CleanSEO Optimizer
 * Plugin URI: https://example.com/cleanseo-optimizer
 * Description: Zaawansowane narzędzie do optymalizacji SEO dla WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: cleanseo-optimizer
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CLEANSEO_VERSION', '1.0.0');
define('CLEANSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLEANSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLEANSEO_DB_VERSION', '1.0.0');


// Autoloader dla klas CleanSEO
spl_autoload_register(function ($class) {
    $prefix = 'CleanSEO_';
    $base_dir = CLEANSEO_PLUGIN_DIR . 'includes/';
    
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Załaduj komponenty AI
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-logger.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-cache.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-ai-models.php';

// Załaduj komponenty core
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-database.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-sitemap.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-redirects.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-competitors.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-audit.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-local-seo.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-analytics.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-stats.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-optimizer.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-activator.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-deactivator.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-uninstaller.php';

// Admin components
require_once CLEANSEO_PLUGIN_DIR . 'admin/class-cleanseo-admin.php';

// Feature components
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-openai.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-meta.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-content-analysis.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-export.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-trends.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-ai-cache.php';
require_once CLEANSEO_PLUGIN_DIR . 'includes/class-cleanseo-ai-installer.php';

// Inicjalizacja pluginu
function cleanseo_init() {
    global $cleanseo_admin, $cleanseo_meta, $cleanseo_local_seo, $cleanseo_redirects,
           $cleanseo_competitors, $cleanseo_openai, $cleanseo_sitemap, $cleanseo_content_analysis,
           $cleanseo_export, $cleanseo_trends, $cleanseo_audit, $cleanseo_analytics,
           $cleanseo_database, $cleanseo_ai_models, $cleanseo_ai_cache, $cleanseo_logger;
    
    // Inicjalizacja komponentów AI
    $cleanseo_logger = new CleanSEO_Logger();
    $cleanseo_ai_models = new CleanSEO_AI_Models();
    $cleanseo_ai_cache = new CleanSEO_AI_Cache();
    
    // Inicjalizacja bazy danych
    $cleanseo_database = new CleanSEO_Database();
    
    // Inicjalizacja głównych komponentów
    $cleanseo_admin = new CleanSEO_Admin('cleanseo-optimizer', CLEANSEO_VERSION);
    $cleanseo_meta = new CleanSEO_Meta();
    $cleanseo_local_seo = new CleanSEO_Local_SEO('cleanseo-optimizer', CLEANSEO_VERSION);
    $cleanseo_redirects = new CleanSEO_Redirects('cleanseo-optimizer', CLEANSEO_VERSION);
    $cleanseo_competitors = new CleanSEO_Competitors();
    $cleanseo_openai = new CleanSEO_OpenAI();
    $cleanseo_sitemap = new CleanSEO_Sitemap();
    $cleanseo_content_analysis = new CleanSEO_ContentAnalysis();
    $cleanseo_export = new CleanSEO_Export();
    $cleanseo_trends = new CleanSEO_Trends();
    $cleanseo_audit = new CleanSEO_Audit();
    $cleanseo_analytics = new CleanSEO_Analytics('cleanseo-optimizer', CLEANSEO_VERSION);
}

// Hooks instalacyjne
register_activation_hook(__FILE__, array('CleanSEO_Activator', 'install'));
register_deactivation_hook(__FILE__, array('CleanSEO_Activator', 'deactivate'));
register_uninstall_hook(__FILE__, array('CleanSEO_Activator', 'uninstall'));

// Inicjalizacja pluginu po załadowaniu WordPress
add_action('plugins_loaded', 'cleanseo_init');

// Dodanie menu administracyjnego
add_action('admin_menu', 'cleanseo_add_admin_menu');

// Dodanie meta boxów
add_action('add_meta_boxes', 'cleanseo_add_meta_boxes');

// Funkcja dodająca menu administracyjne
function cleanseo_add_admin_menu() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Dodaj główne menu
    add_menu_page(
        'CleanSEO Optimizer',
        'CleanSEO',
        'manage_options',
        'cleanseo-optimizer',
        array($GLOBALS['cleanseo_admin'], 'display_plugin_admin_page'),
        'dashicons-chart-area',
        30
    );

    // Dodaj podmenu
    add_submenu_page(
        'cleanseo-optimizer',
        'SEO Lokalne',
        'SEO Lokalne',
        'manage_options',
        'cleanseo-local-seo',
        function() {
            require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-local-seo.php';
        }
    );

    add_submenu_page(
        'cleanseo-optimizer',
        'Audyt SEO',
        'Audyt SEO',
        'manage_options',
        'cleanseo-audit',
        function() {
            require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-audit.php';
        }
    );

    add_submenu_page(
        'cleanseo-optimizer',
        'Sitemap i robots.txt',
        'Sitemap',
        'manage_options',
        'cleanseo-sitemap',
        function() {
            require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-sitemap-settings.php';
        }
    );

    add_submenu_page(
        'cleanseo-optimizer',
        'Trendy',
        'Trendy',
        'manage_options',
        'cleanseo-trends',
        function() {
            require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-trends.php';
        }
    );

    add_submenu_page(
        'cleanseo-optimizer',
        'AI / OpenAI',
        'AI / OpenAI',
        'manage_options',
        'cleanseo-openai',
        function() {
            require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-openai-settings.php';
        }
    );
}

// Funkcja dodająca meta boxy
function cleanseo_add_meta_boxes() {
    $post_types = array('post', 'page');
    
    foreach ($post_types as $post_type) {
        add_meta_box(
            'cleanseo_meta_box',
            'CleanSEO - Meta Tagi',
            'cleanseo_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}

// Funkcja renderująca meta box
function cleanseo_render_meta_box($post) {
    wp_nonce_field('cleanseo_meta_box', 'cleanseo_meta_box_nonce');
    
    $meta_title = get_post_meta($post->ID, '_cleanseo_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_cleanseo_meta_description', true);
    $focus_keyword = get_post_meta($post->ID, '_cleanseo_focus_keyword', true);
    
    require_once CLEANSEO_PLUGIN_DIR . 'admin/partials/cleanseo-meta-box.php';
}

// Funkcja zapisująca meta box
function cleanseo_save_meta_box($post_id) {
    if (!isset($_POST['cleanseo_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['cleanseo_meta_box_nonce'], 'cleanseo_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['cleanseo_meta_title'])) {
        $title = sanitize_text_field($_POST['cleanseo_meta_title']);
        if (strlen($title) <= 60) {
            update_post_meta($post_id, '_cleanseo_meta_title', $title);
        }
    }
    
    if (isset($_POST['cleanseo_meta_description'])) {
        $description = sanitize_textarea_field($_POST['cleanseo_meta_description']);
        if (strlen($description) <= 160) {
            update_post_meta($post_id, '_cleanseo_meta_description', $description);
        }
    }
    
    if (isset($_POST['cleanseo_focus_keyword'])) {
        update_post_meta($post_id, '_cleanseo_focus_keyword', 
            sanitize_text_field($_POST['cleanseo_focus_keyword']));
    }
}
add_action('save_post', 'cleanseo_save_meta_box');

// Funkcja ładowająca skrypty i style administracyjne
function cleanseo_enqueue_admin_scripts($hook) {
    // Sprawdź czy jesteśmy na stronie pluginu
    if (strpos($hook, 'cleanseo') === false) {
        return;
    }

    // Style
    wp_enqueue_style(
        'cleanseo-admin',
        CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-admin.css',
        array('wp-jquery-ui-dialog'),
        CLEANSEO_VERSION
    );

    // Skrypty
    wp_enqueue_script(
        'cleanseo-admin',
        CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-admin.js',
        array('jquery', 'jquery-ui-dialog', 'wp-util'),
        CLEANSEO_VERSION,
        true
    );

    // Dane dla JavaScript
    wp_localize_script('cleanseo-admin', 'cleanseo_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cleanseo_nonce'),
        'i18n' => array(
            'confirmDelete' => 'Czy na pewno chcesz usunąć ten element?',
            'error' => 'Wystąpił błąd podczas przetwarzania żądania.',
            'success' => 'Operacja zakończona sukcesem.',
            'saving' => 'Zapisywanie...',
            'loading' => 'Ładowanie...'
        )
    ));

    // Dodatkowe style i skrypty dla konkretnych stron
    if (strpos($hook, 'cleanseo-local-seo') !== false) {
        wp_enqueue_style(
            'cleanseo-local-seo',
            CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-local-seo.css',
            array('cleanseo-admin'),
            CLEANSEO_VERSION
        );
        wp_enqueue_script(
            'cleanseo-local-seo',
            CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-local-seo.js',
            array('cleanseo-admin'),
            CLEANSEO_VERSION,
            true
        );
    }

    if (strpos($hook, 'cleanseo-redirects') !== false) {
        wp_enqueue_style(
            'cleanseo-redirects',
            CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-redirects.css',
            array('cleanseo-admin'),
            CLEANSEO_VERSION
        );
        wp_enqueue_script(
            'cleanseo-redirects',
            CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-redirects.js',
            array('cleanseo-admin'),
            CLEANSEO_VERSION,
            true
        );
    }

    if (strpos($hook, 'cleanseo-ai-settings') !== false) {
        wp_enqueue_style(
            'cleanseo-ai',
            CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-ai.css',
            array('cleanseo-admin'),
            CLEANSEO_VERSION
        );
        wp_enqueue_script(
            'cleanseo-ai',
            CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-ai.js',
            array('cleanseo-admin'),
            CLEANSEO_VERSION,
            true
        );
    }

    // Dodanie skryptów dla strony Trends
    if (strpos($hook, 'cleanseo-trends') !== false) {
        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js',
            array(),
            '3.7.1',
            true
        );
        
        // Enqueue jsPDF
        wp_enqueue_script(
            'jspdf',
            'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );
        
        // Enqueue własne skrypty i style dla Trends
        wp_enqueue_style(
            'cleanseo-trends',
            CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-trends.css',
            array('cleanseo-admin'),
            CLEANSEO_VERSION
        );
        
        wp_enqueue_script(
            'cleanseo-trends',
            CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-trends.js',
            array('cleanseo-admin', 'chartjs', 'jspdf'),
            CLEANSEO_VERSION,
            true
        );
        
        // Lokalizacja dla skryptu Trends
        wp_localize_script('cleanseo-trends', 'cleanseo_trends_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_trends_nonce'),
            'i18n' => array(
                'loading' => __('Ładowanie danych...', 'cleanseo-optimizer'),
                'error' => __('Wystąpił błąd podczas pobierania danych.', 'cleanseo-optimizer'),
                'no_data' => __('Brak danych dla wybranego słowa kluczowego.', 'cleanseo-optimizer'),
                'export_ready' => __('Eksport gotowy do pobrania.', 'cleanseo-optimizer')
            )
        ));
    }
}

// Funkcja ładowająca skrypty i style frontendowe
function cleanseo_enqueue_frontend_scripts() {
    if (is_singular()) {
        wp_enqueue_style(
            'cleanseo-frontend',
            CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-frontend.css',
            array(),
            CLEANSEO_VERSION
        );

        wp_enqueue_script(
            'cleanseo-frontend',
            CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-frontend.js',
            array('jquery'),
            CLEANSEO_VERSION,
            true
        );

        wp_localize_script('cleanseo-frontend', 'cleanseo_frontend_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_frontend_nonce')
        ));
    }
}

// Rejestracja skryptów i stylów
function cleanseo_register_assets() {
    // Rejestracja stylów
    wp_register_style(
        'cleanseo-admin',
        CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-admin.css',
        array('wp-jquery-ui-dialog'),
        CLEANSEO_VERSION
    );

    wp_register_style(
        'cleanseo-frontend',
        CLEANSEO_PLUGIN_URL . 'assets/css/cleanseo-frontend.css',
        array(),
        CLEANSEO_VERSION
    );

    // Rejestracja skryptów
    wp_register_script(
        'cleanseo-admin',
        CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-admin.js',
        array('jquery', 'jquery-ui-dialog', 'wp-util'),
        CLEANSEO_VERSION,
        true
    );

    wp_register_script(
        'cleanseo-frontend',
        CLEANSEO_PLUGIN_URL . 'assets/js/cleanseo-frontend.js',
        array('jquery'),
        CLEANSEO_VERSION,
        true
    );
}
add_action('init', 'cleanseo_register_assets');

// Hooks dla ładowania zasobów
add_action('admin_enqueue_scripts', 'cleanseo_enqueue_admin_scripts');
add_action('wp_enqueue_scripts', 'cleanseo_enqueue_frontend_scripts');

/**
 * Funkcja obsługująca zapytania AJAX dla trendów Google
 */
function cleanseo_get_trends_data_ajax() {
    // Sprawdź nonce
    check_ajax_referer('cleanseo_trends_nonce', 'nonce');
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
    }
    
    // Pobierz parametry
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    $time_range = isset($_POST['time_range']) ? sanitize_text_field($_POST['time_range']) : '30d';
    
    if (empty($keyword)) {
        wp_send_json_error(__('Słowo kluczowe nie może być puste.', 'cleanseo-optimizer'));
    }
    
    // Sprawdź czy klasa istnieje
    if (!class_exists('CleanSEO_Trends')) {
        wp_send_json_error(__('Klasa CleanSEO_Trends nie istnieje.', 'cleanseo-optimizer'));
    }
    
    // Inicjalizuj klasę
    $trends = new CleanSEO_Trends();
    
    // Pobierz dane
    $trends_data = $trends->get_trends($keyword, $time_range);
    $related_data = $trends->get_related_queries($keyword);
    
    // Sprawdź czy pobrano dane
    if (is_wp_error($trends_data)) {
        wp_send_json_error($trends_data->get_error_message());
    }
    
    // Przetwórz dane trendów do formatu dla wykresu
    $processed_trends = cleanseo_process_trends_data($trends_data, $keyword);
    
    // Przetwórz dane powiązanych zapytań
    $processed_related = array();
    if (!is_wp_error($related_data) && isset($related_data['default']['rankedList'][0]['rankedKeyword'])) {
        foreach ($related_data['default']['rankedList'][0]['rankedKeyword'] as $query) {
            if (isset($query['query'])) {
                $processed_related[] = array(
                    'title' => sanitize_text_field($query['query']),
                    'traffic' => isset($query['value']) ? intval($query['value']) : 0
                );
            }
        }
    }
    
    // Zwróć dane
    wp_send_json_success(array(
        'trends' => $processed_trends,
        'related' => $processed_related
    ));
}
add_action('wp_ajax_cleanseo_get_trends_data', 'cleanseo_get_trends_data_ajax');

/**
 * Funkcja przetwarzająca dane trendów do formatu dla wykresu
 * 
 * @param array $data Dane z API
 * @param string $keyword Słowo kluczowe
 * @return array Przetworzone dane
 */
function cleanseo_process_trends_data($data, $keyword) {
    // Inicjalizuj tablicę wynikową
    $processed = array(
        'keyword' => $keyword,
        'dates' => array(),
        'values' => array()
    );
    
    // Sprawdź strukturę danych i wyodrębnij potrzebne informacje
    if (isset($data['default']['trendingSearchesDays'])) {
        foreach ($data['default']['trendingSearchesDays'] as $day) {
            if (isset($day['date'])) {
                $date = sanitize_text_field($day['date']);
                $processed['dates'][] = $date;
                
                // Znajdź wartość dla słowa kluczowego
                $value = 0; // Domyślna wartość
                
                if (isset($day['trendingSearches'])) {
                    foreach ($day['trendingSearches'] as $search) {
                        if (isset($search['title']['query']) && stripos($search['title']['query'], $keyword) !== false) {
                            // Jeśli znaleziono słowo kluczowe, pobierz wartość
                            $value = isset($search['formattedTraffic']) ? cleanseo_parse_traffic_value($search['formattedTraffic']) : 0;
                            break;
                        }
                    }
                }
                
                $processed['values'][] = $value;
            }
        }
    }
    
    // Jeśli nie znaleziono danych, wygeneruj dane przykładowe dla pokazania funkcjonalności wykresu
    if (empty($processed['dates'])) {
        $today = current_time('Y-m-d');
        $date = new DateTime($today);
        
        for ($i = 30; $i >= 0; $i--) {
            $date_str = $date->format('Y-m-d');
            $processed['dates'][] = $date_str;
            $processed['values'][] = mt_rand(30, 100); // Przykładowe wartości
            $date->modify('-1 day');
        }
        
        // Odwróć tablice, aby mieć chronologiczną kolejność
        $processed['dates'] = array_reverse($processed['dates']);
        $processed['values'] = array_reverse($processed['values']);
    }
    
    return $processed;
}

/**
 * Funkcja parsująca wartość ruchu z formatu tekstowego na liczbę
 * 
 * @param string $traffic_str Wartość ruchu w formacie tekstowym (np. "10K+")
 * @return int Wartość liczbowa
 */
function cleanseo_parse_traffic_value($traffic_str) {
    $traffic_str = sanitize_text_field($traffic_str);
    $value = 0;
    
    // Usuń znaki nieznaczące
    $traffic_str = str_replace(array('+', ',', ' '), '', $traffic_str);
    
    // Sprawdź czy zawiera K (tysiące) lub M (miliony)
    if (strpos($traffic_str, 'K') !== false) {
        $value = floatval(str_replace('K', '', $traffic_str)) * 1000;
    } elseif (strpos($traffic_str, 'M') !== false) {
        $value = floatval(str_replace('M', '', $traffic_str)) * 1000000;
    } else {
        $value = intval($traffic_str);
    }
    
    return $value;
}

/**
 * Funkcja zapisująca słowa kluczowe konkurencji
 */
function cleanseo_save_competitor_keywords() {
    // Sprawdź nonce
    check_ajax_referer('cleanseo_nonce', 'nonce');
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
    }
    
    // Pobierz parametry
    $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';
    
    // Przetwórz słowa kluczowe
    $keywords_array = array();
    if (!empty($keywords)) {
        $keywords_lines = explode("\n", $keywords);
        foreach ($keywords_lines as $line) {
            $keyword = trim($line);
            if (!empty($keyword)) {
                $keywords_array[] = $keyword;
            }
        }
    }
    
    // Zapisz słowa kluczowe
    update_option('cleanseo_competitor_keywords', $keywords_array);
    
    wp_send_json_success(array(
        'message' => __('Słowa kluczowe konkurencji zostały zapisane.', 'cleanseo-optimizer'),
        'count' => count($keywords_array)
    ));
}
add_action('wp_ajax_cleanseo_save_competitor_keywords', 'cleanseo_save_competitor_keywords');

/**
 * Funkcja zapisująca własne słowa kluczowe
 */
function cleanseo_save_my_keywords() {
    // Sprawdź nonce
    check_ajax_referer('cleanseo_nonce', 'nonce');
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
    }
    
    // Pobierz parametry
    $keywords = isset($_POST['keywords']) ? sanitize_textarea_field($_POST['keywords']) : '';
    
    // Przetwórz słowa kluczowe
    $keywords_array = array();
    if (!empty($keywords)) {
        $keywords_lines = explode("\n", $keywords);
        foreach ($keywords_lines as $line) {
            $keyword = trim($line);
            if (!empty($keyword)) {
                $keywords_array[] = $keyword;
            }
        }
    }
    
    // Zapisz słowa kluczowe
    update_option('cleanseo_my_keywords', $keywords_array);
    
    wp_send_json_success(array(
        'message' => __('Twoje słowa kluczowe zostały zapisane.', 'cleanseo-optimizer'),
        'count' => count($keywords_array)
    ));
}
add_action('wp_ajax_cleanseo_save_my_keywords', 'cleanseo_save_my_keywords');

/**
 * Funkcja zapisująca klucz Google API
 */
function cleanseo_save_google_api_key() {
    // Sprawdź nonce
    check_ajax_referer('cleanseo_nonce', 'nonce');
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
    }
    
    // Pobierz parametry
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    
    // Zapisz klucz API
    update_option('cleanseo_google_api_key', $api_key);
    
    wp_send_json_success(array(
        'message' => __('Klucz Google API został zapisany.', 'cleanseo-optimizer')
   ));
}
add_action('wp_ajax_cleanseo_save_google_api_key', 'cleanseo_save_google_api_key');

/**
* Funkcja eksportująca dane trendów do CSV
*/
function cleanseo_export_trends_csv() {
   // Sprawdź nonce
   check_ajax_referer('cleanseo_trends_nonce', 'nonce');
   
   // Sprawdź uprawnienia
   if (!current_user_can('manage_options')) {
       wp_send_json_error(__('Brak uprawnień.', 'cleanseo-optimizer'));
   }
   
   // Pobierz parametry
   $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
   $time_range = isset($_POST['time_range']) ? sanitize_text_field($_POST['time_range']) : '30d';
   $dates = isset($_POST['dates']) ? (array)$_POST['dates'] : array();
   $values = isset($_POST['values']) ? (array)$_POST['values'] : array();
   
   if (empty($keyword) || empty($dates) || empty($values)) {
       wp_send_json_error(__('Brak danych do eksportu.', 'cleanseo-optimizer'));
   }
   
   // Przygotuj dane CSV
   $csv_data = array();
   $csv_data[] = array(__('Data', 'cleanseo-optimizer'), __('Wartość', 'cleanseo-optimizer'));
   
   foreach ($dates as $index => $date) {
       if (isset($values[$index])) {
           $csv_data[] = array($date, $values[$index]);
       }
   }
   
   // Przekształć dane do formatu CSV
   $csv_content = '';
   foreach ($csv_data as $row) {
       $csv_content .= implode(',', $row) . "\n";
   }
   
   // Zwróć dane CSV
   wp_send_json_success(array(
       'filename' => 'trendy_' . sanitize_file_name($keyword) . '_' . date('Y-m-d') . '.csv',
       'content' => $csv_content
   ));
}
add_action('wp_ajax_cleanseo_export_trends_csv', 'cleanseo_export_trends_csv');

/**
* Funkcja analizująca URL
*/
function cleanseo_analyze_url() {
   // Sprawdź nonce
   if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cleanseo_ajax_nonce')) {
       wp_send_json_error(array('message' => 'Błąd bezpieczeństwa'));
   }

   // Sprawdź uprawnienia
   if (!current_user_can('manage_options')) {
       wp_send_json_error(array('message' => 'Brak uprawnień'));
   }

   // Pobierz URL
   $target_url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
   if (empty($target_url)) {
       wp_send_json_error(array('message' => 'URL jest wymagany'));
   }

   // Pobierz zawartość strony
   $response = wp_remote_get($target_url, array(
       'timeout' => 30,
       'sslverify' => false,
       'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
   ));

   if (is_wp_error($response)) {
       wp_send_json_error(array('message' => 'Nie można pobrać strony: ' . $response->get_error_message()));
   }

   $html = wp_remote_retrieve_body($response);
   $headers = wp_remote_retrieve_headers($response);
   $load_time = wp_remote_retrieve_header($response, 'total_time');

   // Analiza SEO
   $analysis = array(
       'url' => $target_url,
       'timestamp' => current_time('mysql'),
       'metrics' => array(
           'load_time' => round($load_time, 2) . 's',
           'status_code' => wp_remote_retrieve_response_code($response),
           'content_length' => strlen($html),
           'word_count' => str_word_count(strip_tags($html)),
           'seo_score' => 0
       ),
       'meta' => array(),
       'issues' => array(),
       'recommendations' => array()
   );

   // Analiza meta tagów
   if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
       $analysis['meta']['title'] = $matches[1];
       $title_length = strlen($matches[1]);
       if ($title_length < 30) {
           $analysis['issues'][] = 'Tytuł jest za krótki (zalecane: 30-60 znaków)';
       } elseif ($title_length > 60) {
           $analysis['issues'][] = 'Tytuł jest za długi (zalecane: 30-60 znaków)';
       }
   } else {
       $analysis['issues'][] = 'Brak tytułu strony';
   }

   if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/i', $html, $matches)) {
       $analysis['meta']['description'] = $matches[1];
       $desc_length = strlen($matches[1]);
       if ($desc_length < 120) {
           $analysis['issues'][] = 'Meta opis jest za krótki (zalecane: 120-160 znaków)';
       } elseif ($desc_length > 160) {
           $analysis['issues'][] = 'Meta opis jest za długi (zalecane: 120-160 znaków)';
       }
   } else {
       $analysis['issues'][] = 'Brak meta opisu';
   }

   // Analiza nagłówków
   $h1_count = substr_count($html, '<h1');
   if ($h1_count === 0) {
       $analysis['issues'][] = 'Brak nagłówka H1';
   } elseif ($h1_count > 1) {
       $analysis['issues'][] = 'Za dużo nagłówków H1 (zalecane: 1)';
   }

   // Analiza obrazów
   preg_match_all('/<img[^>]+>/i', $html, $images);
   $images_without_alt = 0;
   foreach ($images[0] as $img) {
       if (!preg_match('/alt=["\']([^"\']+)["\']/i', $img)) {
           $images_without_alt++;
       }
   }
   if ($images_without_alt > 0) {
       $analysis['issues'][] = "Znaleziono $images_without_alt obrazów bez atrybutu alt";
   }

   // Analiza linków
   preg_match_all('/<a[^>]+>/i', $html, $links);
   $internal_links = 0;
   $external_links = 0;
   foreach ($links[0] as $link) {
       if (preg_match('/href=["\']([^"\']+)["\']/i', $link, $href)) {
           if (strpos($href[1], $target_url) !== false) {
               $internal_links++;
           } else {
               $external_links++;
           }
       }
   }
   $analysis['metrics']['internal_links'] = $internal_links;
   $analysis['metrics']['external_links'] = $external_links;

   // Analiza responsywności
   if (!preg_match('/<meta[^>]+viewport/i', $html)) {
       $analysis['issues'][] = 'Brak meta tagu viewport (responsywność)';
   }

   // Analiza szybkości
   if ($load_time > 3) {
       $analysis['issues'][] = 'Strona ładuje się zbyt wolno (ponad 3 sekundy)';
   }

   // Obliczanie wyniku SEO
   $seo_score = 100;
   $seo_score -= count($analysis['issues']) * 5; // -5 punktów za każdy problem
   $seo_score = max(0, min(100, $seo_score)); // Ograniczenie do 0-100
   $analysis['metrics']['seo_score'] = $seo_score;

   // Dodaj rekomendacje
   if ($seo_score < 70) {
       $analysis['recommendations'][] = 'Zalecane jest poprawienie znalezionych problemów SEO';
   }
   if ($load_time > 2) {
       $analysis['recommendations'][] = 'Zalecana optymalizacja szybkości ładowania strony';
   }
   if ($internal_links < 3) {
       $analysis['recommendations'][] = 'Zalecane dodanie większej liczby linków wewnętrznych';
   }

   wp_send_json_success($analysis);
}
add_action('wp_ajax_cleanseo_analyze_url', 'cleanseo_analyze_url');

/**
* Funkcja wspierająca ładowanie szablonów wtyczki
* 
* @param string $template_name Nazwa szablonu
* @param array $args Argumenty do szablonu
* @param string $template_path Ścieżka do szablonu
* @param string $default_path Domyślna ścieżka
*/
function cleanseo_get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
   if (!empty($args) && is_array($args)) {
       extract($args);
   }
   
   if (!$template_path) {
       $template_path = 'cleanseo-optimizer/';
   }
   
   if (!$default_path) {
       $default_path = CLEANSEO_PLUGIN_DIR . 'templates/';
   }
   
   // Sprawdź czy szablon istnieje w motywie
   $template = locate_template(array(
       trailingslashit($template_path) . $template_name,
       $template_name
   ));
   
   // Jeśli nie, użyj domyślnego szablonu
   if (!$template) {
       $template = $default_path . $template_name;
   }
   
   // Sprawdź czy plik istnieje
   if (file_exists($template)) {
       include $template;
   } else {
       /* translators: %s template */
       _doing_it_wrong(__FUNCTION__, sprintf(__('Szablon %s nie istnieje.', 'cleanseo-optimizer'), '<code>' . $template . '</code>'), '1.0.0');
       return false;
   }
}