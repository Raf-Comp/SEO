<?php
// Sprawdzenie uprawnień
if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień.');
}

// Załadowanie wymaganych klas
if (!class_exists('CleanSEO_AI_Settings')) {
    require_once plugin_dir_path(__DIR__) . '../includes/class-cleanseo-ai-settings.php';
}

if (!class_exists('CleanSEO_AI_Models')) {
    require_once plugin_dir_path(__DIR__) . '../includes/class-cleanseo-ai-models.php';
}

if (!class_exists('CleanSEO_AI_Logger')) {
    require_once plugin_dir_path(__DIR__) . '../includes/class-cleanseo-ai-logger.php';
}

// Inicjalizacja klas
$logger = new CleanSEO_AI_Logger('ai_settings');
$settings = new CleanSEO_AI_Settings($logger);
$models = new CleanSEO_AI_Models($logger);

// Obsługa eksportu CSV - to musi być na samym początku
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $from_value = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
    $to_value = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
    
    // Pobierz statystyki
    $usage_stats = $settings->get_usage_stats($period, $from_value, $to_value);
    
    // Ustaw nagłówki dla CSV
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=cleanseo-ai-usage-" . date('Y-m-d') . ".csv");
    $out = fopen("php://output", "w");
    
    // Dodaj BOM dla UTF-8
    fputs($out, "\xEF\xBB\xBF");
    
    // Nagłówki
    fputcsv($out, array(
        __('Dostawca', 'cleanseo-optimizer'),
        __('Model', 'cleanseo-optimizer'),
        __('Liczba zapytań', 'cleanseo-optimizer'),
        __('Tokeny', 'cleanseo-optimizer'),
        __('Koszt', 'cleanseo-optimizer')
    ), ',', '"', '\\');
    
    // Dane
    if (!empty($usage_stats['by_model'])) {
        foreach ($usage_stats['by_model'] as $model_stats) {
            fputcsv($out, array(
                isset($model_stats['provider']) ? $model_stats['provider'] : '',
                $model_stats['model'],
                $model_stats['requests'],
                $model_stats['tokens'],
                $model_stats['cost']
            ), ',', '"', '\\');
        }
    }
    
    fclose($out);
    exit;
}

// Pobierz aktualne ustawienia
$current_settings = $settings->get_settings();
$current_providers = $settings->get_provider_settings();
$current_templates = $settings->get_prompt_templates();
$available_models = $models->get_available_models();

// Obsługa zapisu ustawień ogólnych
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_general' && check_admin_referer('cleanseo_ai_settings_general')) {
    $general_settings = array(
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'default_provider' => sanitize_text_field($_POST['default_provider']),
        'default_model' => sanitize_text_field($_POST['default_model']),
        'max_tokens' => intval($_POST['max_tokens']),
        'temperature' => floatval($_POST['temperature']),
        'cache_enabled' => isset($_POST['cache_enabled']) ? 1 : 0,
        'cache_time' => intval($_POST['cache_time']),
        'logging_enabled' => isset($_POST['logging_enabled']) ? 1 : 0,
        'auto_generate' => isset($_POST['auto_generate']) ? 1 : 0,
        'auto_generate_post_types' => isset($_POST['auto_generate_post_types']) ? $_POST['auto_generate_post_types'] : array(),
        'auto_generate_fields' => isset($_POST['auto_generate_fields']) ? $_POST['auto_generate_fields'] : array()
    );
    
    $settings->update_settings($general_settings);
    $success_message = __('Ustawienia ogólne zostały pomyślnie zapisane.', 'cleanseo-optimizer');
    $current_settings = $settings->get_settings();
}

// Obsługa zapisu kluczy API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_api_keys' && check_admin_referer('cleanseo_ai_settings_api_keys')) {
    $updated_providers = array();
    
    foreach ($settings->get_available_providers() as $provider_id => $provider_name) {
        $api_key = isset($_POST['api_keys'][$provider_id]) ? sanitize_text_field($_POST['api_keys'][$provider_id]) : '';
        
        if (!empty($api_key)) {
            $settings->save_api_key($provider_id, $api_key);
            $updated_providers[] = $provider_name;
        }
    }
    
    if (!empty($updated_providers)) {
        $success_message = sprintf(
            __('Klucze API zostały zaktualizowane dla: %s', 'cleanseo-optimizer'),
            implode(', ', $updated_providers)
        );
    } else {
        $warning_message = __('Nie wprowadzono żadnych kluczy API.', 'cleanseo-optimizer');
    }
    
    $current_providers = $settings->get_provider_settings();
}

// Obsługa zapisu szablonów promptów
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_templates' && check_admin_referer('cleanseo_ai_settings_templates')) {
    $updated_templates = array();
    
    foreach ($settings->get_prompt_types() as $type_id => $type_name) {
        if (isset($_POST['templates'][$type_id])) {
            $template = $_POST['templates'][$type_id];
            $settings->save_prompt_template($type_id, $template);
            $updated_templates[] = $type_name;
        }
    }
    
    if (!empty($updated_templates)) {
        $success_message = sprintf(
            __('Szablony promptów zostały zaktualizowane dla: %s', 'cleanseo-optimizer'),
            implode(', ', $updated_templates)
        );
    } else {
        $warning_message = __('Nie zaktualizowano żadnych szablonów.', 'cleanseo-optimizer');
    }
    
    $current_templates = $settings->get_prompt_templates();
}

// Obsługa zapisu ustawień zaawansowanych
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_advanced' && check_admin_referer('cleanseo_ai_settings_advanced')) {
    $advanced_settings = array(
        'request_timeout' => intval($_POST['request_timeout']),
        'max_retries' => intval($_POST['max_retries']),
        'backoff_strategy' => sanitize_text_field($_POST['backoff_strategy']),
        'parallel_requests' => isset($_POST['parallel_requests']) ? 1 : 0,
        'max_parallel_requests' => intval($_POST['max_parallel_requests']),
        'budget_limit' => floatval($_POST['budget_limit']),
        'daily_request_limit' => intval($_POST['daily_request_limit']),
        'error_notifications' => isset($_POST['error_notifications']) ? 1 : 0,
        'notification_email' => sanitize_email($_POST['notification_email'])
    );
    
    $settings->update_settings($advanced_settings);
    $success_message = __('Ustawienia zaawansowane zostały pomyślnie zapisane.', 'cleanseo-optimizer');
    $current_settings = $settings->get_settings();
}

// Obsługa weryfikacji klucza API
if (isset($_GET['action']) && $_GET['action'] === 'verify_api_key' && isset($_GET['provider']) && check_admin_referer('cleanseo_verify_api_key')) {
    $provider = sanitize_text_field($_GET['provider']);
    $api_key = $settings->get_api_key($provider);
    
    if (empty($api_key)) {
        $error_message = __('Brak klucza API do weryfikacji.', 'cleanseo-optimizer');
    } else {
        $verification_result = $models->verify_api_key($api_key, $provider);
        
        if (is_wp_error($verification_result)) {
            $error_message = sprintf(
                __('Weryfikacja nie powiodła się: %s', 'cleanseo-optimizer'),
                $verification_result->get_error_message()
            );
        } else {
            $success_message = sprintf(
                __('Klucz API dla %s został pomyślnie zweryfikowany. Dostępne modele: %s', 'cleanseo-optimizer'),
                $settings->get_available_providers()[$provider],
                implode(', ', $verification_result['supported_models'])
            );
        }
    }
}

// Obsługa eksportu ustawień
if (isset($_GET['action']) && $_GET['action'] === 'export_settings' && check_admin_referer('cleanseo_export_settings')) {
    $export_data = $settings->export();
    
    // Sprawdź czy funkcja header() może być wywołana
    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="cleanseo-ai-settings-' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    } else {
        $error_message = __('Nie można wygenerować pliku eksportu - nagłówki zostały już wysłane.', 'cleanseo-optimizer');
    }
}

// Obsługa importu ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_settings' && check_admin_referer('cleanseo_import_settings')) {
    if (!empty($_FILES['settings_file']['tmp_name'])) {
        $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if ($import_data === null) {
            $error_message = __('Nieprawidłowy format pliku JSON.', 'cleanseo-optimizer');
        } else {
            $result = $settings->import($import_data);
            
            if ($result) {
                $success_message = __('Ustawienia zostały pomyślnie zaimportowane.', 'cleanseo-optimizer');
                $current_settings = $settings->get_settings();
                $current_providers = $settings->get_provider_settings();
                $current_templates = $settings->get_prompt_templates();
            } else {
                $error_message = __('Wystąpił błąd podczas importu ustawień.', 'cleanseo-optimizer');
            }
        }
    } else {
        $error_message = __('Nie wybrano pliku do importu.', 'cleanseo-optimizer');
    }
}

// Obsługa resetowania ustawień
if (isset($_GET['action']) && $_GET['action'] === 'reset_settings' && check_admin_referer('cleanseo_reset_settings')) {
    $reset_api_keys = isset($_GET['reset_api_keys']) && $_GET['reset_api_keys'] === '1';
    $result = $settings->reset($reset_api_keys);
    
    if ($result) {
        $success_message = $reset_api_keys 
            ? __('Wszystkie ustawienia i klucze API zostały zresetowane do wartości domyślnych.', 'cleanseo-optimizer')
            : __('Ustawienia zostały zresetowane do wartości domyślnych. Klucze API zostały zachowane.', 'cleanseo-optimizer');
        
        $current_settings = $settings->get_settings();
        $current_providers = $settings->get_provider_settings();
        $current_templates = $settings->get_prompt_templates();
    } else {
        $error_message = __('Wystąpił błąd podczas resetowania ustawień.', 'cleanseo-optimizer');
    }
}

// Obsługa czyszczenia cache
if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && check_admin_referer('cleanseo_clear_cache')) {
    // Tworzenie instancji cache
    $cache = new CleanSEO_AI_Cache('ai');
    $result = $cache->clear();
    
    if ($result) {
        $success_message = __('Cache AI został pomyślnie wyczyszczony.', 'cleanseo-optimizer');
    } else {
        $error_message = __('Wystąpił błąd podczas czyszczenia cache.', 'cleanseo-optimizer');
    }
}

// Parametry do filtrowania statystyk
$from_value = isset($_GET['from']) ? esc_attr($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
$to_value = isset($_GET['to']) ? esc_attr($_GET['to']) : date('Y-m-d');
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';

// Aktywna zakładka
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Pobierz statystyki, jeśli jesteśmy w zakładce statystyki
$usage_stats = ($active_tab === 'stats') ? $settings->get_usage_stats($period) : array();

// Pobierz kolory dla dostawców
$provider_colors = array(
    'openai' => '#10a37f',
    'google' => '#4285f4',
    'anthropic' => '#f58220',
    'mistral' => '#5535f0',
    'cohere' => '#ff00af'
);
?>

<div class="cleanseo-dashboard">
    <div class="cleanseo-header">
        <div class="cleanseo-header-brand">
            <img src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/cleanseo-logo.svg'); ?>" alt="CleanSEO Logo" class="cleanseo-logo">
            <h1><?php _e('CleanSEO AI', 'cleanseo-optimizer'); ?></h1>
        </div>
        <p class="cleanseo-header-desc"><?php _e('Zaawansowane narzędzia AI dla optymalizacji SEO', 'cleanseo-optimizer'); ?></p>
    </div>
    
    <?php if (isset($success_message)): ?>
    <div class="cleanseo-notice cleanseo-notice-success">
        <p><i class="dashicons dashicons-yes"></i> <?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($warning_message)): ?>
    <div class="cleanseo-notice cleanseo-notice-warning">
        <p><i class="dashicons dashicons-warning"></i> <?php echo esc_html($warning_message); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="cleanseo-notice cleanseo-notice-error">
        <p><i class="dashicons dashicons-no"></i> <?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Nawigacja -->
    <div class="cleanseo-tabs">
        <a href="?page=cleanseo-openai&tab=general" class="cleanseo-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-admin-generic"></i> <?php _e('Ogólne', 'cleanseo-optimizer'); ?>
        </a>
        <a href="?page=cleanseo-openai&tab=api_keys" class="cleanseo-tab <?php echo $active_tab === 'api_keys' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-admin-network"></i> <?php _e('Klucze API', 'cleanseo-optimizer'); ?>
        </a>
        <a href="?page=cleanseo-openai&tab=templates" class="cleanseo-tab <?php echo $active_tab === 'templates' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-editor-code"></i> <?php _e('Szablony promptów', 'cleanseo-optimizer'); ?>
        </a>
        <a href="?page=cleanseo-openai&tab=advanced" class="cleanseo-tab <?php echo $active_tab === 'advanced' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-admin-tools"></i> <?php _e('Zaawansowane', 'cleanseo-optimizer'); ?>
        </a>
        <a href="?page=cleanseo-openai&tab=stats" class="cleanseo-tab <?php echo $active_tab === 'stats' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-chart-bar"></i> <?php _e('Statystyki', 'cleanseo-optimizer'); ?>
        </a>
        <a href="?page=cleanseo-openai&tab=tools" class="cleanseo-tab <?php echo $active_tab === 'tools' ? 'active' : ''; ?>">
            <i class="dashicons dashicons-admin-settings"></i> <?php _e('Narzędzia', 'cleanseo-optimizer'); ?>
        </a>
    </div>

    <!-- Zawartość zakładek -->
    <?php if ($active_tab === 'general'): ?>
    <!-- Zakładka Ustawienia Ogólne -->
    <div class="cleanseo-card">
        <div class="cleanseo-card-header">
            <h2><i class="dashicons dashicons-admin-settings"></i> <?php _e('Ustawienia ogólne', 'cleanseo-optimizer'); ?></h2>
            <span class="cleanseo-card-header-desc"><?php _e('Podstawowa konfiguracja AI dla CleanSEO', 'cleanseo-optimizer'); ?></span>
        </div>
        
        <div class="cleanseo-card-content">
            <form method="post">
                <?php wp_nonce_field('cleanseo_ai_settings_general'); ?>
                <input type="hidden" name="action" value="save_general">
                
                <div class="cleanseo-section">
                    <h3><i class="dashicons dashicons-performance"></i> <?php _e('Podstawowe ustawienia', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-form-row">
                        <div class="cleanseo-form-col">
                            <label class="cleanseo-switch-label">
                                <input type="checkbox" name="enabled" <?php checked($current_settings['enabled']); ?> class="cleanseo-switch">
                                <span class="cleanseo-switch-slider"></span>
                                <?php _e('Włącz funkcje AI', 'cleanseo-optimizer'); ?>
                            </label>
                            <p class="cleanseo-help-text"><?php _e('Włącz lub wyłącz wszystkie funkcje AI w CleanSEO', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-row cleanseo-form-row-cols">
                        <div class="cleanseo-form-col">
                            <label for="default_provider"><?php _e('Domyślny dostawca', 'cleanseo-optimizer'); ?></label>
                            <select id="default_provider" name="default_provider" class="cleanseo-select">
                                <?php foreach ($settings->get_available_providers() as $provider_id => $provider_name): ?>
                                <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($current_settings['default_provider'], $provider_id); ?>><?php echo esc_html($provider_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="cleanseo-help-text"><?php _e('Wybierz domyślnego dostawcę API', 'cleanseo-optimizer'); ?></p>
                        </div>
                        
                        <div class="cleanseo-form-col">
                            <label for="default_model"><?php _e('Domyślny model', 'cleanseo-optimizer'); ?></label>
                            <select id="default_model" name="default_model" class="cleanseo-select">
                                <?php foreach ($available_models as $model_id => $model): ?>
                                <option value="<?php echo esc_attr($model_id); ?>" <?php selected($current_settings['default_model'], $model_id); ?>><?php echo esc_html($model['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="cleanseo-help-text"><?php _e('Wybierz model, który będzie używany domyślnie', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-row cleanseo-form-row-cols">
                        <div class="cleanseo-form-col">
                            <label for="max_tokens"><?php _e('Maksymalna liczba tokenów', 'cleanseo-optimizer'); ?></label>
                            <input type="number" id="max_tokens" name="max_tokens" value="<?php echo esc_attr($current_settings['max_tokens']); ?>" min="100" max="8000" step="10" class="cleanseo-input">
                            <p class="cleanseo-help-text"><?php _e('Maksymalna liczba tokenów w generowanej odpowiedzi', 'cleanseo-optimizer'); ?></p>
                        </div>
                        
                        <div class="cleanseo-form-col">
                            <label for="temperature"><?php _e('Temperatura', 'cleanseo-optimizer'); ?></label>
                            <input type="number" id="temperature" name="temperature" value="<?php echo esc_attr($current_settings['temperature']); ?>" min="0" max="1" step="0.1" class="cleanseo-input">
                            <p class="cleanseo-help-text"><?php _e('Kontroluje losowość odpowiedzi (0 = deterministyczne, 1 = kreatywne)', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-section">
                    <h3><i class="dashicons dashicons-database"></i> <?php _e('Cache', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-form-row">
                        <div class="cleanseo-form-col">
                            <label class="cleanseo-switch-label">
                                <input type="checkbox" name="cache_enabled" <?php checked($current_settings['cache_enabled']); ?> class="cleanseo-switch">
                                <span class="cleanseo-switch-slider"></span>
                                <?php _e('Włącz cache', 'cleanseo-optimizer'); ?>
                            </label>
                            <p class="cleanseo-help-text"><?php _e('Włącz cache odpowiedzi AI, aby zmniejszyć liczbę zapytań do API i przyspieszyć generowanie treści', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-row">
                        <div class="cleanseo-form-col">
                            <label for="cache_time"><?php _e('Czas życia cache (w sekundach)', 'cleanseo-optimizer'); ?></label>
                            <input type="number" id="cache_time" name="cache_time" value="<?php echo esc_attr($current_settings['cache_time']); ?>" min="300" class="cleanseo-input">
                            <p class="cleanseo-help-text"><?php _e('Po tym czasie cache zostanie odrzucony (86400 = 24 godziny)', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-section">
                    <h3><i class="dashicons dashicons-admin-page"></i> <?php _e('Automatyczne generowanie', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-form-row">
                        <div class="cleanseo-form-col">
                            <label class="cleanseo-switch-label">
                                <input type="checkbox" name="auto_generate" id="auto_generate" <?php checked($current_settings['auto_generate']); ?> class="cleanseo-switch">
                                <span class="cleanseo-switch-slider"></span>
                                <?php _e('Włącz automatyczne generowanie', 'cleanseo-optimizer'); ?>
                            </label>
                            <p class="cleanseo-help-text"><?php _e('Automatycznie generuj meta dane podczas publikacji lub aktualizacji postów', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-row cleanseo-form-row-cols" id="auto_generate_options" <?php if (!$current_settings['auto_generate']) echo 'style="display:none;"'; ?>>
                        <div class="cleanseo-form-col">
                            <label><?php _e('Typy postów', 'cleanseo-optimizer'); ?></label>
                            <div class="cleanseo-checkbox-list">
                                <?php 
                                $post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($post_types as $post_type): 
                                ?>
                                <label class="cleanseo-checkbox-label">
                                    <input type="checkbox" name="auto_generate_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $current_settings['auto_generate_post_types'])); ?>>
                                    <?php echo esc_html($post_type->labels->name); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="cleanseo-form-col">
                            <label><?php _e('Pola do generowania', 'cleanseo-optimizer'); ?></label>
                            <div class="cleanseo-checkbox-list">
                                <?php foreach ($settings->get_prompt_types() as $type_id => $type_name): ?>
                                <label class="cleanseo-checkbox-label">
                                    <input type="checkbox" name="auto_generate_fields[]" value="<?php echo esc_attr($type_id); ?>" <?php checked(in_array($type_id, $current_settings['auto_generate_fields'])); ?>>
                                    <?php echo esc_html($type_name); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-section">
                    <h3><i class="dashicons dashicons-welcome-write-blog"></i> <?php _e('Logowanie', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-form-row">
                        <div class="cleanseo-form-col">
                            <label class="cleanseo-switch-label">
                                <input type="checkbox" name="logging_enabled" <?php checked($current_settings['logging_enabled']); ?> class="cleanseo-switch">
                                <span class="cleanseo-switch-slider"></span>
                                <?php _e('Włącz logowanie', 'cleanseo-optimizer'); ?>
                            </label>
                            <p class="cleanseo-help-text"><?php _e('Zapisuj logi z zapytań AI (pomocne przy rozwiązywaniu problemów)', 'cleanseo-optimizer'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-form-actions">
                    <button type="submit" class="cleanseo-btn cleanseo-btn-primary">
                        <i class="dashicons dashicons-yes-alt"></i> <?php _e('Zapisz zmiany', 'cleanseo-optimizer'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php elseif ($active_tab === 'api_keys'): ?>
    <!-- Zakładka Klucze API -->
    <div class="cleanseo-card">
        <div class="cleanseo-card-header">
            <h2><i class="dashicons dashicons-admin-network"></i> <?php _e('Klucze API', 'cleanseo-optimizer'); ?></h2>
            <span class="cleanseo-card-header-desc"><?php _e('Konfiguracja kluczy API dla różnych dostawców', 'cleanseo-optimizer'); ?></span>
        </div>
        
        <div class="cleanseo-card-content">
            <form method="post">
                <?php wp_nonce_field('cleanseo_ai_settings_api_keys'); ?>
                <input type="hidden" name="action" value="save_api_keys">
                
                <?php foreach ($settings->get_available_providers() as $provider_id => $provider_name): ?>
                <div class="cleanseo-section">
                    <div class="cleanseo-provider-header">
                    <img src="<?php echo esc_url(plugin_dir_url(__DIR__) . "assets/{$provider_id}-logo.svg"); ?>" alt="<?php echo esc_attr($provider_name); ?>" class="cleanseo-provider-logo">
                        <h3><?php echo esc_html($provider_name); ?></h3>
                    </div>
                    
                    <div class="cleanseo-api-group">
                        <label for="api_key_<?php echo esc_attr($provider_id); ?>"><?php _e('Klucz API', 'cleanseo-optimizer'); ?></label>
                        <div class="cleanseo-api-input">
                            <input type="password" id="api_key_<?php echo esc_attr($provider_id); ?>" name="api_keys[<?php echo esc_attr($provider_id); ?>]" value="<?php echo esc_attr($settings->get_api_key($provider_id)); ?>" class="cleanseo-input" placeholder="<?php printf(__('Wprowadź klucz API dla %s', 'cleanseo-optimizer'), $provider_name); ?>">
                            <button type="button" class="cleanseo-toggle-password" aria-label="<?php _e('Pokaż/Ukryj hasło', 'cleanseo-optimizer'); ?>"><i class="dashicons dashicons-visibility"></i></button>
                        </div>
                        
                        <div class="cleanseo-api-actions">
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cleanseo-openai&tab=api_keys&action=verify_api_key&provider=' . $provider_id), 'cleanseo_verify_api_key')); ?>" class="cleanseo-btn cleanseo-btn-secondary">
                                <i class="dashicons dashicons-yes"></i> <?php _e('Zweryfikuj klucz', 'cleanseo-optimizer'); ?>
                            </a>
                            
                            <?php
                            // Link do strony klucza API dostawcy
                            $api_urls = array(
                                'openai' => 'https://platform.openai.com/account/api-keys',
                                'google' => 'https://makersuite.google.com/app/apikey',
                                'anthropic' => 'https://console.anthropic.com/settings/keys',
                                'mistral' => 'https://console.mistral.ai/api-keys/',
                                'cohere' => 'https://dashboard.cohere.com/api-keys'
                            );
                            
                            if (isset($api_urls[$provider_id])):
                                ?>
                                <a href="<?php echo esc_url($api_urls[$provider_id]); ?>" target="_blank" class="cleanseo-link-external">
                                    <i class="dashicons dashicons-external"></i> <?php printf(__('Pobierz klucz API z %s', 'cleanseo-optimizer'), $provider_name); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php
                            // Wyświetl dostępne modele dla tego dostawcy
                            $provider_models = $models->get_available_models($provider_id);
                            if (!empty($provider_models)):
                            ?>
                            <div class="cleanseo-api-models">
                                <p class="cleanseo-api-models-title"><i class="dashicons dashicons-database"></i> <?php _e('Dostępne modele:', 'cleanseo-optimizer'); ?></p>
                                <div class="cleanseo-model-tags">
                                    <?php foreach ($provider_models as $model_id => $model): ?>
                                    <span class="cleanseo-model-tag" title="<?php echo esc_attr($model['description']); ?>">
                                        <?php echo esc_html($model['name']); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="cleanseo-form-actions">
                        <button type="submit" class="cleanseo-btn cleanseo-btn-primary">
                            <i class="dashicons dashicons-yes-alt"></i> <?php _e('Zapisz klucze API', 'cleanseo-optimizer'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($active_tab === 'templates'): ?>
        <!-- Zakładka Szablony promptów -->
        <div class="cleanseo-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-editor-code"></i> <?php _e('Szablony promptów', 'cleanseo-optimizer'); ?></h2>
                <span class="cleanseo-card-header-desc"><?php _e('Dostosuj szablony używane do generowania zawartości', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-card-content">
                <form method="post">
                    <?php wp_nonce_field('cleanseo_ai_settings_templates'); ?>
                    <input type="hidden" name="action" value="save_templates">
                    
                    <div class="cleanseo-templates-intro">
                        <p><?php _e('Poniżej możesz dostosować szablony promptów używane do generowania różnych typów treści. Możesz użyć następujących zmiennych:', 'cleanseo-optimizer'); ?></p>
                        <ul class="cleanseo-variables-list">
                            <li><code>{title}</code> - <?php _e('tytuł artykułu', 'cleanseo-optimizer'); ?></li>
                            <li><code>{content}</code> - <?php _e('treść artykułu', 'cleanseo-optimizer'); ?></li>
                            <li><code>{excerpt}</code> - <?php _e('fragment artykułu', 'cleanseo-optimizer'); ?></li>
                            <li><code>{keywords}</code> - <?php _e('słowa kluczowe', 'cleanseo-optimizer'); ?></li>
                            <li><code>{length}</code> - <?php _e('żądana długość treści (short, medium, long)', 'cleanseo-optimizer'); ?></li>
                            <li><code>{post_type}</code> - <?php _e('typ wpisu (post, page, product, itp.)', 'cleanseo-optimizer'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="cleanseo-templates-tabs">
                        <div class="cleanseo-templates-nav">
                            <?php $first_template = true; ?>
                            <?php foreach ($settings->get_prompt_types() as $type_id => $type_name): ?>
                            <a href="#template-<?php echo esc_attr($type_id); ?>" class="cleanseo-template-tab <?php echo $first_template ? 'active' : ''; ?>">
                                <?php echo esc_html($type_name); ?>
                            </a>
                            <?php $first_template = false; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cleanseo-templates-content">
                            <?php $first_template = true; ?>
                            <?php foreach ($settings->get_prompt_types() as $type_id => $type_name): ?>
                            <div id="template-<?php echo esc_attr($type_id); ?>" class="cleanseo-template-panel <?php echo $first_template ? 'active' : ''; ?>">
                                <div class="cleanseo-form-row">
                                    <div class="cleanseo-form-col">
                                        <label for="template_<?php echo esc_attr($type_id); ?>"><?php echo esc_html($type_name); ?></label>
                                        <textarea id="template_<?php echo esc_attr($type_id); ?>" name="templates[<?php echo esc_attr($type_id); ?>]" rows="6" class="cleanseo-textarea"><?php 
                                            echo esc_textarea($settings->get_prompt_template($type_id)); 
                                        ?></textarea>
                                        
                                        <div class="cleanseo-template-actions">
                                            <button type="button" class="cleanseo-btn cleanseo-btn-secondary cleanseo-reset-template" data-template-id="<?php echo esc_attr($type_id); ?>" data-default-template="<?php 
                                                echo esc_attr($settings->get_prompt_template($type_id, null)); 
                                            ?>">
                                                <i class="dashicons dashicons-image-rotate"></i> <?php _e('Przywróć domyślny', 'cleanseo-optimizer'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $first_template = false; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-actions">
                        <button type="submit" class="cleanseo-btn cleanseo-btn-primary">
                            <i class="dashicons dashicons-yes-alt"></i> <?php _e('Zapisz szablony', 'cleanseo-optimizer'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($active_tab === 'advanced'): ?>
        <!-- Zakładka Zaawansowane -->
        <div class="cleanseo-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-admin-tools"></i> <?php _e('Ustawienia zaawansowane', 'cleanseo-optimizer'); ?></h2>
                <span class="cleanseo-card-header-desc"><?php _e('Zaawansowana konfiguracja API i limitów', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-card-content">
                <form method="post">
                    <?php wp_nonce_field('cleanseo_ai_settings_advanced'); ?>
                    <input type="hidden" name="action" value="save_advanced">
                    
                    <div class="cleanseo-section">
                        <h3><i class="dashicons dashicons-admin-network"></i> <?php _e('Ustawienia połączenia', 'cleanseo-optimizer'); ?></h3>
                        
                        <div class="cleanseo-form-row cleanseo-form-row-cols">
                            <div class="cleanseo-form-col">
                                <label for="request_timeout"><?php _e('Timeout zapytania (sekundy)', 'cleanseo-optimizer'); ?></label>
                                <input type="number" id="request_timeout" name="request_timeout" value="<?php echo esc_attr($current_settings['request_timeout']); ?>" min="5" max="120" class="cleanseo-input">
                                <p class="cleanseo-help-text"><?php _e('Maksymalny czas oczekiwania na odpowiedź API', 'cleanseo-optimizer'); ?></p>
                            </div>
                            
                            <div class="cleanseo-form-col">
                                <label for="max_retries"><?php _e('Maksymalna liczba prób', 'cleanseo-optimizer'); ?></label>
                                <input type="number" id="max_retries" name="max_retries" value="<?php echo esc_attr($current_settings['max_retries']); ?>" min="0" max="10" class="cleanseo-input">
                                <p class="cleanseo-help-text"><?php _e('Ile razy próbować ponownie w przypadku błędu', 'cleanseo-optimizer'); ?></p>
                            </div>
                        </div>
                        
                        <div class="cleanseo-form-row cleanseo-form-row-cols">
                            <div class="cleanseo-form-col">
                                <label for="backoff_strategy"><?php _e('Strategia ponawiania prób', 'cleanseo-optimizer'); ?></label>
                                <select id="backoff_strategy" name="backoff_strategy" class="cleanseo-select">
                                    <option value="linear" <?php selected($current_settings['backoff_strategy'], 'linear'); ?>><?php _e('Liniowa', 'cleanseo-optimizer'); ?></option>
                                    <option value="exponential" <?php selected($current_settings['backoff_strategy'], 'exponential'); ?>><?php _e('Wykładnicza', 'cleanseo-optimizer'); ?></option>
                                    <option value="constant" <?php selected($current_settings['backoff_strategy'], 'constant'); ?>><?php _e('Stała', 'cleanseo-optimizer'); ?></option>
                                </select>
                                <p class="cleanseo-help-text"><?php _e('Sposób obliczania opóźnienia między próbami', 'cleanseo-optimizer'); ?></p>
                            </div>
                            
                            <div class="cleanseo-form-col parallel-requests-group">
                                <label class="cleanseo-switch-label">
                                    <input type="checkbox" name="parallel_requests" id="parallel_requests" <?php checked($current_settings['parallel_requests']); ?> class="cleanseo-switch">
                                    <span class="cleanseo-switch-slider"></span>
                                    <?php _e('Równoległe zapytania', 'cleanseo-optimizer'); ?>
                                </label>
                                <p class="cleanseo-help-text"><?php _e('Wykonuj wiele zapytań jednocześnie dla zwiększenia wydajności', 'cleanseo-optimizer'); ?></p>
                                
                                <div class="cleanseo-parallel-option" <?php if (!$current_settings['parallel_requests']) echo 'style="display:none;"'; ?>>
                                    <label for="max_parallel_requests"><?php _e('Maksymalna liczba równoległych zapytań', 'cleanseo-optimizer'); ?></label>
                                    <input type="number" id="max_parallel_requests" name="max_parallel_requests" value="<?php echo esc_attr($current_settings['max_parallel_requests']); ?>" min="2" max="10" class="cleanseo-input">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-section">
                        <h3><i class="dashicons dashicons-shield"></i> <?php _e('Limity i powiadomienia', 'cleanseo-optimizer'); ?></h3>
                        
                        <div class="cleanseo-form-row cleanseo-form-row-cols">
                            <div class="cleanseo-form-col">
                                <label for="budget_limit"><?php _e('Miesięczny limit budżetu ($)', 'cleanseo-optimizer'); ?></label>
                                <input type="number" id="budget_limit" name="budget_limit" value="<?php echo esc_attr($current_settings['budget_limit']); ?>" min="0" step="0.01" class="cleanseo-input">
                                <p class="cleanseo-help-text"><?php _e('Maksymalny miesięczny koszt (0 = brak limitu)', 'cleanseo-optimizer'); ?></p>
                            </div>
                            
                            <div class="cleanseo-form-col">
                                <label for="daily_request_limit"><?php _e('Dzienny limit zapytań', 'cleanseo-optimizer'); ?></label>
                                <input type="number" id="daily_request_limit" name="daily_request_limit" value="<?php echo esc_attr($current_settings['daily_request_limit']); ?>" min="0" class="cleanseo-input">
                                <p class="cleanseo-help-text"><?php _e('Maksymalna liczba zapytań dziennie dla użytkownika (0 = brak limitu)', 'cleanseo-optimizer'); ?></p>
                            </div>
                        </div>
                        
                        <div class="cleanseo-form-row">
                            <div class="cleanseo-form-col">
                                <label class="cleanseo-switch-label">
                                    <input type="checkbox" name="error_notifications" <?php checked($current_settings['error_notifications']); ?> class="cleanseo-switch">
                                    <span class="cleanseo-switch-slider"></span>
                                    <?php _e('Powiadomienia e-mail o błędach', 'cleanseo-optimizer'); ?>
                                </label>
                                <p class="cleanseo-help-text"><?php _e('Wysyłaj powiadomienia e-mail o błędach API i przekroczeniu limitów', 'cleanseo-optimizer'); ?></p>
                            </div>
                        </div>
                        
                        <div class="cleanseo-form-row">
                            <div class="cleanseo-form-col">
                                <label for="notification_email"><?php _e('E-mail do powiadomień', 'cleanseo-optimizer'); ?></label>
                                <input type="email" id="notification_email" name="notification_email" value="<?php echo esc_attr($current_settings['notification_email']); ?>" class="cleanseo-input">
                                <p class="cleanseo-help-text"><?php _e('Adres e-mail do wysyłania powiadomień (pozostaw puste aby używać adresu administratora)', 'cleanseo-optimizer'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-form-actions">
                        <button type="submit" class="cleanseo-btn cleanseo-btn-primary">
                            <i class="dashicons dashicons-yes-alt"></i> <?php _e('Zapisz ustawienia zaawansowane', 'cleanseo-optimizer'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($active_tab === 'stats'): ?>
        <!-- Zakładka Statystyki -->
        <div class="cleanseo-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-chart-line"></i> <?php _e('Statystyki wykorzystania AI', 'cleanseo-optimizer'); ?></h2>
                <span class="cleanseo-card-header-desc"><?php _e('Monitoruj wykorzystanie i koszty różnych modeli AI', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-card-content">
                <!-- Filtry dat -->
                <div class="cleanseo-filters">
                    <form method="get" class="cleanseo-filter-form">
                        <input type="hidden" name="page" value="cleanseo-openai" />
                        <input type="hidden" name="tab" value="stats" />
                        
                        <div class="cleanseo-filter-group">
                            <div class="cleanseo-filter-item">
                                <label for="period"><?php _e('Okres:', 'cleanseo-optimizer'); ?></label>
                                <select id="period" name="period" class="cleanseo-select">
                                    <option value="day" <?php selected($period, 'day'); ?>><?php _e('Ostatni dzień', 'cleanseo-optimizer'); ?></option>
                                    <option value="week" <?php selected($period, 'week'); ?>><?php _e('Ostatni tydzień', 'cleanseo-optimizer'); ?></option>
                                    <option value="month" <?php selected($period, 'month'); ?>><?php _e('Ostatni miesiąc', 'cleanseo-optimizer'); ?></option>
                                    <option value="year" <?php selected($period, 'year'); ?>><?php _e('Ostatni rok', 'cleanseo-optimizer'); ?></option>
                                    <option value="custom" <?php selected($period, 'custom'); ?>><?php _e('Niestandardowy', 'cleanseo-optimizer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="cleanseo-filter-item cleanseo-date-range" <?php if ($period !== 'custom') echo 'style="display:none;"'; ?>>
                                <div class="cleanseo-filter-date-group">
                                    <label for="from"><?php _e('Od:', 'cleanseo-optimizer'); ?></label>
                                    <input type="date" id="from" name="from" class="cleanseo-date-input" value="<?php echo esc_attr($from_value); ?>">
                                </div>
                                
                                <div class="cleanseo-filter-date-group">
                                    <label for="to"><?php _e('Do:', 'cleanseo-optimizer'); ?></label>
                                    <input type="date" id="to" name="to" class="cleanseo-date-input" value="<?php echo esc_attr($to_value); ?>">
                                </div>
                            </div>
                            
                            <div class="cleanseo-filter-actions">
                                <button type="submit" class="cleanseo-btn cleanseo-btn-primary">
                                    <i class="dashicons dashicons-filter"></i> <?php _e('Filtruj', 'cleanseo-optimizer'); ?>
                                </button>
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'cleanseo-openai', 'tab' => 'stats', 'export' => 'csv', 'period' => $period, 'from' => $from_value, 'to' => $to_value))); ?>" class="cleanseo-btn cleanseo-btn-secondary">
                                    <i class="dashicons dashicons-download"></i> <?php _e('Eksportuj CSV', 'cleanseo-optimizer'); ?>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Kafelki podsumowujące -->
                <div class="cleanseo-summary-tiles">
                    <div class="cleanseo-tile">
                        <div class="cleanseo-tile-icon">
                            <i class="dashicons dashicons-admin-comments"></i>
                        </div>
                        <div class="cleanseo-tile-content">
                            <h3><?php _e('Zapytania', 'cleanseo-optimizer'); ?></h3>
                            <div class="cleanseo-tile-value"><?php echo number_format_i18n(isset($usage_stats['requests']) ? $usage_stats['requests'] : 0); ?></div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-tile">
                        <div class="cleanseo-tile-icon">
                            <i class="dashicons dashicons-editor-code"></i>
                        </div>
                        <div class="cleanseo-tile-content">
                            <h3><?php _e('Tokeny', 'cleanseo-optimizer'); ?></h3>
                            <div class="cleanseo-tile-value"><?php echo number_format_i18n(isset($usage_stats['tokens']) ? $usage_stats['tokens'] : 0); ?></div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-tile">
                        <div class="cleanseo-tile-icon">
                            <i class="dashicons dashicons-money-alt"></i>
                        </div>
                        <div class="cleanseo-tile-content">
                            <h3><?php _e('Koszt', 'cleanseo-optimizer'); ?></h3>
                            <div class="cleanseo-tile-value"><?php echo number_format_i18n(isset($usage_stats['cost']) ? $usage_stats['cost'] : 0, 4); ?> $</div>
                        </div>
                    </div>
                    
                    <div class="cleanseo-tile">
                        <div class="cleanseo-tile-icon">
                            <i class="dashicons dashicons-database"></i>
                        </div>
                        <div class="cleanseo-tile-content">
                            <h3><?php _e('Cache', 'cleanseo-optimizer'); ?></h3>
                            <div class="cleanseo-tile-value"><?php 
                                if (isset($usage_stats['by_cache'])) {
                                    echo number_format_i18n($usage_stats['by_cache']['ratio'], 1) . '%';
                                } else {
                                    echo '0%';
                                }
                            ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Wykresy -->
                <?php if (!empty($usage_stats) && !empty($usage_stats['by_model'])): ?>
                <div class="cleanseo-charts-container">
                    <div class="cleanseo-chart-wrapper">
                        <h3 class="cleanseo-chart-title"><?php _e('Według modelu', 'cleanseo-optimizer'); ?></h3>
                        <div class="cleanseo-chart">
                            <canvas id="cleanseoModelChart"></canvas>
                        </div>
                    </div>
                    
                    <?php if (!empty($usage_stats['by_day'])): ?>
                    <div class="cleanseo-chart-wrapper">
                        <h3 class="cleanseo-chart-title"><?php _e('Dzienne użycie', 'cleanseo-optimizer'); ?></h3>
                        <div class="cleanseo-chart">
                            <canvas id="cleanseoTimeChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Tabela z danymi -->
                <div class="cleanseo-table-container">
                    <h3 class="cleanseo-table-title"><?php _e('Szczegóły użycia modeli', 'cleanseo-optimizer'); ?></h3>
                    
                    <table class="cleanseo-table">
                        <thead>
                            <tr>
                                <th><?php _e('Dostawca / Model', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Liczba zapytań', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Tokeny', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Koszt', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Udział %', 'cleanseo-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (empty($usage_stats) || empty($usage_stats['by_model'])) {
                                echo '<tr><td colspan="5" class="cleanseo-no-data">' . __('Brak danych dla wybranego okresu', 'cleanseo-optimizer') . '</td></tr>';
                            } else {
                                $total_requests = $usage_stats['requests'];
                                
                                foreach ($usage_stats['by_model'] as $model_stats) {
                                    $model_id = $model_stats['model'];
                                    $provider = 'openai'; // domyślnie OpenAI
                                    
                                    // Określ dostawcę na podstawie nazwy modelu
                                    foreach ($settings->get_available_providers() as $provider_id => $provider_name) {
                                        if (strpos($model_id, $provider_id) !== false) {
                                            $provider = $provider_id;
                                            break;
                                        }
                                    }
                                    
                                    $percentage = $total_requests > 0 ? round(($model_stats['requests'] / $total_requests) * 100) : 0;
                                    $color = isset($provider_colors[$provider]) ? $provider_colors[$provider] : '#999999';
                                    
                                    echo '<tr>';
                                    echo '<td>';
                                    echo '<div class="cleanseo-provider">';
                                    echo '<span class="cleanseo-provider-badge" style="background-color: ' . esc_attr($color) . '">';
                                    echo substr($settings->get_available_providers()[$provider], 0, 1);
                                    echo '</span>';
                                    echo '<span class="cleanseo-provider-name">' . esc_html($settings->get_available_providers()[$provider]) . '</span>';
                                    echo '<span class="cleanseo-model-name">' . esc_html($model_id) . '</span>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td data-label="' . __('Zapytania', 'cleanseo-optimizer') . '">' . number_format_i18n($model_stats['requests']) . '</td>';
                                    echo '<td data-label="' . __('Tokeny', 'cleanseo-optimizer') . '">' . number_format_i18n($model_stats['tokens']) . '</td>';
                                    echo '<td data-label="' . __('Koszt', 'cleanseo-optimizer') . '">' . number_format_i18n($model_stats['cost'], 4) . ' $</td>';
                                    echo '<td data-label="' . __('Udział', 'cleanseo-optimizer') . '">';
                                    echo '<div class="cleanseo-progress">';
                                    echo '<div class="cleanseo-progress-bar" style="width:' . $percentage . '%; background-color: ' . esc_attr($color) . '"></div>';
                                    echo '<span class="cleanseo-progress-text">' . $percentage . '%</span>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($usage_stats['by_user'])): ?>
                <div class="cleanseo-table-container">
                    <h3 class="cleanseo-table-title"><?php _e('Użycie według użytkowników', 'cleanseo-optimizer'); ?></h3>
                    
                    <table class="cleanseo-table">
                        <thead>
                            <tr>
                            <th><?php _e('Użytkownik', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Zapytania', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Tokeny', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Koszt', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Dzienny limit', 'cleanseo-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($usage_stats['by_user'] as $user_stats) {
                                echo '<tr>';
                                echo '<td>' . esc_html($user_stats['user_login']) . '</td>';
                                echo '<td data-label="' . __('Zapytania', 'cleanseo-optimizer') . '">' . number_format_i18n($user_stats['requests']) . '</td>';
                                echo '<td data-label="' . __('Tokeny', 'cleanseo-optimizer') . '">' . number_format_i18n($user_stats['tokens']) . '</td>';
                                echo '<td data-label="' . __('Koszt', 'cleanseo-optimizer') . '">' . number_format_i18n($user_stats['cost'], 4) . ' $</td>';
                                echo '<td data-label="' . __('Limit', 'cleanseo-optimizer') . '">' . number_format_i18n($settings->get_user_daily_limit($user_stats['user_id'])) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($active_tab === 'tools'): ?>
        <!-- Zakładka Narzędzia -->
        <div class="cleanseo-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-admin-settings"></i> <?php _e('Narzędzia', 'cleanseo-optimizer'); ?></h2>
                <span class="cleanseo-card-header-desc"><?php _e('Narzędzia administracyjne dla AI', 'cleanseo-optimizer'); ?></span>
            </div>
            
            <div class="cleanseo-card-content">
                <div class="cleanseo-tools-section">
                    <h3><i class="dashicons dashicons-database"></i> <?php _e('Cache', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-tool-card">
                        <div class="cleanseo-tool-content">
                            <h4><?php _e('Czyszczenie cache AI', 'cleanseo-optimizer'); ?></h4>
                            <p><?php _e('Usuń wszystkie zapisane odpowiedzi AI z cache, aby wymusić generowanie nowych treści. Użyj tej opcji, gdy zmienisz szablony promptów lub klucze API.', 'cleanseo-optimizer'); ?></p>
                        </div>
                        <div class="cleanseo-tool-action">
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cleanseo-openai&tab=tools&action=clear_cache'), 'cleanseo_clear_cache')); ?>" class="cleanseo-btn cleanseo-btn-primary cleanseo-confirm-action" data-confirm="<?php esc_attr_e('Czy na pewno chcesz wyczyścić cały cache AI? Ta operacja nie może być cofnięta.', 'cleanseo-optimizer'); ?>">
                                <i class="dashicons dashicons-trash"></i> <?php _e('Wyczyść cache AI', 'cleanseo-optimizer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-tools-section">
                    <h3><i class="dashicons dashicons-admin-page"></i> <?php _e('Eksport i import', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-tool-card">
                        <div class="cleanseo-tool-content">
                            <h4><?php _e('Eksport ustawień', 'cleanseo-optimizer'); ?></h4>
                            <p><?php _e('Wyeksportuj wszystkie ustawienia AI, w tym szablony promptów i konfigurację API, do pliku JSON. Możesz użyć tego pliku do utworzenia kopii zapasowej lub przeniesienia ustawień na inną stronę.', 'cleanseo-optimizer'); ?></p>
                        </div>
                        <div class="cleanseo-tool-action">
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cleanseo-openai&tab=tools&action=export_settings'), 'cleanseo_export_settings')); ?>" class="cleanseo-btn cleanseo-btn-primary">
                                <i class="dashicons dashicons-download"></i> <?php _e('Eksportuj ustawienia', 'cleanseo-optimizer'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="cleanseo-tool-card">
                        <div class="cleanseo-tool-content">
                            <h4><?php _e('Import ustawień', 'cleanseo-optimizer'); ?></h4>
                            <p><?php _e('Zaimportuj ustawienia AI z pliku JSON. Uwaga: ta operacja zastąpi wszystkie obecne ustawienia AI.', 'cleanseo-optimizer'); ?></p>
                        </div>
                        <div class="cleanseo-tool-action">
                            <form method="post" enctype="multipart/form-data" class="cleanseo-import-form">
                                <?php wp_nonce_field('cleanseo_import_settings'); ?>
                                <input type="hidden" name="action" value="import_settings">
                                <div class="cleanseo-file-input-container">
                                    <input type="file" name="settings_file" id="settings_file" class="cleanseo-file-input" accept=".json">
                                    <label for="settings_file" class="cleanseo-file-label">
                                        <i class="dashicons dashicons-upload"></i> <?php _e('Wybierz plik', 'cleanseo-optimizer'); ?>
                                    </label>
                                    <span class="cleanseo-file-name"><?php _e('Nie wybrano pliku', 'cleanseo-optimizer'); ?></span>
                                </div>
                                <button type="submit" class="cleanseo-btn cleanseo-btn-primary cleanseo-confirm-action" data-confirm="<?php esc_attr_e('Czy na pewno chcesz zaimportować ustawienia? Ta operacja zastąpi wszystkie obecne ustawienia.', 'cleanseo-optimizer'); ?>">
                                    <i class="dashicons dashicons-upload"></i> <?php _e('Importuj ustawienia', 'cleanseo-optimizer'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-tools-section">
                    <h3><i class="dashicons dashicons-image-rotate"></i> <?php _e('Reset', 'cleanseo-optimizer'); ?></h3>
                    
                    <div class="cleanseo-tool-card">
                        <div class="cleanseo-tool-content">
                            <h4><?php _e('Reset ustawień AI', 'cleanseo-optimizer'); ?></h4>
                            <p><?php _e('Zresetuj wszystkie ustawienia AI do domyślnych wartości. Możesz wybrać, czy zachować klucze API, czy też je usunąć.', 'cleanseo-optimizer'); ?></p>
                        </div>
                        <div class="cleanseo-tool-action cleanseo-tool-action-group">
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cleanseo-openai&tab=tools&action=reset_settings&reset_api_keys=0'), 'cleanseo_reset_settings')); ?>" class="cleanseo-btn cleanseo-btn-secondary cleanseo-confirm-action" data-confirm="<?php esc_attr_e('Czy na pewno chcesz zresetować ustawienia AI? Ta operacja nie może być cofnięta. Klucze API zostaną zachowane.', 'cleanseo-optimizer'); ?>">
                                <i class="dashicons dashicons-image-rotate"></i> <?php _e('Reset (zachowaj klucze API)', 'cleanseo-optimizer'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cleanseo-openai&tab=tools&action=reset_settings&reset_api_keys=1'), 'cleanseo_reset_settings')); ?>" class="cleanseo-btn cleanseo-btn-warning cleanseo-confirm-action" data-confirm="<?php esc_attr_e('Czy na pewno chcesz zresetować WSZYSTKIE ustawienia AI, w tym klucze API? Ta operacja nie może być cofnięta.', 'cleanseo-optimizer'); ?>">
                                <i class="dashicons dashicons-trash"></i> <?php _e('Reset kompletny (usuń wszystko)', 'cleanseo-optimizer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pokaż/Ukryj hasło
    const toggleButtons = document.querySelectorAll('.cleanseo-toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            } else {
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            }
        });
    });
    
    // Zakładki szablonów
    const templateTabs = document.querySelectorAll('.cleanseo-template-tab');
    templateTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Usuń aktywną klasę ze wszystkich zakładek
            document.querySelectorAll('.cleanseo-template-tab').forEach(t => {
                t.classList.remove('active');
            });
            
            // Dodaj aktywną klasę do klikniętej zakładki
            this.classList.add('active');
            
            // Ukryj wszystkie panele
            document.querySelectorAll('.cleanseo-template-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Pokaż odpowiedni panel
            const target = this.getAttribute('href');
            document.querySelector(target).classList.add('active');
        });
    });
    
    // Resetowanie szablonów
    const resetButtons = document.querySelectorAll('.cleanseo-reset-template');
    resetButtons.forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.getAttribute('data-template-id');
            const defaultTemplate = this.getAttribute('data-default-template');
            const textarea = document.getElementById('template_' + templateId);
            
            if (textarea && defaultTemplate) {
                if (confirm('<?php _e('Czy na pewno chcesz przywrócić domyślny szablon? Ta operacja zastąpi obecny szablon.', 'cleanseo-optimizer'); ?>')) {
                    textarea.value = defaultTemplate;
                }
            }
        });
    });
    
    // Pokaż/ukryj opcje auto-generacji
    const autoGenerateCheckbox = document.getElementById('auto_generate');
    if (autoGenerateCheckbox) {
        autoGenerateCheckbox.addEventListener('change', function() {
            const optionsDiv = document.getElementById('auto_generate_options');
            if (optionsDiv) {
                optionsDiv.style.display = this.checked ? 'flex' : 'none';
            }
        });
    }
    
    // Pokaż/ukryj opcje równoległych zapytań
    const parallelRequestsCheckbox = document.getElementById('parallel_requests');
    if (parallelRequestsCheckbox) {
        parallelRequestsCheckbox.addEventListener('change', function() {
            const parallelOption = document.querySelector('.cleanseo-parallel-option');
            if (parallelOption) {
                parallelOption.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
    
    // Obsługa niestandardowego okresu w filtrach statystyk
    const periodSelect = document.getElementById('period');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const dateRange = document.querySelector('.cleanseo-date-range');
            if (dateRange) {
                dateRange.style.display = this.value === 'custom' ? 'flex' : 'none';
            }
        });
    }
    
    // Obsługa wyboru pliku importu
    const fileInput = document.getElementById('settings_file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = document.querySelector('.cleanseo-file-name');
            if (fileName) {
                fileName.textContent = this.files.length > 0 ? this.files[0].name : '<?php _e('Nie wybrano pliku', 'cleanseo-optimizer'); ?>';
            }
        });
    }
    
    // Obsługa potwierdzeń akcji
    const confirmActions = document.querySelectorAll('.cleanseo-confirm-action');
    confirmActions.forEach(action => {
        action.addEventListener('click', function(e) {
            const confirmMessage = this.getAttribute('data-confirm');
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    });
    
    <?php if ($active_tab === 'stats' && !empty($usage_stats) && !empty($usage_stats['by_model'])): ?>
    // Załaduj bibliotekę Chart.js do wykresów
    if (typeof Chart !== 'undefined') {
        // Wykres kołowy modeli
        const modelCtx = document.getElementById('cleanseoModelChart').getContext('2d');
        
        // Przygotuj dane do wykresu
        const modelLabels = [];
        const modelData = [];
        const modelColors = [];
        
        <?php
        foreach ($usage_stats['by_model'] as $model_stats) {
            $model_id = $model_stats['model'];
            $provider = 'openai'; // domyślnie OpenAI
            
            // Określ dostawcę na podstawie nazwy modelu
            foreach ($settings->get_available_providers() as $provider_id => $provider_name) {
                if (strpos($model_id, $provider_id) !== false) {
                    $provider = $provider_id;
                    break;
                }
            }
            
            $color = isset($provider_colors[$provider]) ? $provider_colors[$provider] : '#999999';
            ?>
            modelLabels.push('<?php echo esc_js($model_id); ?>');
            modelData.push(<?php echo (int)$model_stats['requests']; ?>);
            modelColors.push('<?php echo esc_js($color); ?>');
            <?php
        }
        ?>
        
        new Chart(modelCtx, {
            type: 'doughnut',
            data: {
                labels: modelLabels,
                datasets: [{
                    data: modelData,
                    backgroundColor: modelColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        <?php if (!empty($usage_stats['by_day'])): ?>
        // Wykres dziennego użycia
        const timeCtx = document.getElementById('cleanseoTimeChart').getContext('2d');
        
        // Przygotuj dane do wykresu
        const timeLabels = [];
        const requestsData = [];
        const tokensData = [];
        
        <?php
        foreach ($usage_stats['by_day'] as $day_stats) {
            ?>
            timeLabels.push('<?php echo esc_js($day_stats['date']); ?>');
            requestsData.push(<?php echo (int)$day_stats['requests']; ?>);
            tokensData.push(<?php echo (int)$day_stats['tokens']; ?>);
            <?php
        }
        ?>
        
        new Chart(timeCtx, {
            type: 'bar',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: '<?php _e('Zapytania', 'cleanseo-optimizer'); ?>',
                        data: requestsData,
                        backgroundColor: '#2271b1',
                        order: 1
                    },
                    {
                        label: '<?php _e('Tokeny (x100)', 'cleanseo-optimizer'); ?>',
                        data: tokensData.map(value => value / 100),
                        type: 'line',
                        borderColor: '#FF6384',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '<?php _e('Liczba zapytań', 'cleanseo-optimizer'); ?>'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '<?php _e('Data', 'cleanseo-optimizer'); ?>'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw;
                                
                                if (context.datasetIndex === 1) {
                                    // Tokeny (pomnóż przez 100, aby pokazać prawdziwą wartość)
                                    value = value * 100;
                                    return `${label}: ${value}`;
                                }
                                
                                return `${label}: ${value}`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    }
    <?php endif; ?>
});
</script>