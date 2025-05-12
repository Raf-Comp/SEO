<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap cleanseo-admin">
    <div class="cleanseo-accessibility">
        <button class="cleanseo-accessibility-button" data-tooltip="Zmień motyw">
            <i class="fas fa-moon"></i>
        </button>
        <button class="cleanseo-accessibility-button" data-tooltip="Zmień rozmiar czcionki">
            <i class="fas fa-text-height"></i>
        </button>
        <button class="cleanseo-accessibility-button" data-tooltip="Zmień układ">
            <i class="fas fa-th-large"></i>
        </button>
    </div>

    <div class="cleanseo-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="cleanseo-header-actions">
            <button class="cleanseo-button cleanseo-button-primary" id="save-settings">
                <i class="fas fa-save"></i> Zapisz ustawienia
            </button>
            <div class="cleanseo-export-import">
                <button class="cleanseo-export-import-button" id="export-settings">
                    <i class="fas fa-download"></i> Eksportuj
                </button>
                <button class="cleanseo-export-import-button" id="import-settings">
                    <i class="fas fa-upload"></i> Importuj
                </button>
            </div>
        </div>
    </div>

    <div class="cleanseo-settings">
        <!-- Podstawowe ustawienia -->
        <div class="cleanseo-settings-section">
            <h2 class="cleanseo-settings-section-title">Podstawowe ustawienia</h2>
            
            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="api_key">
                    Klucz API
                    <span class="cleanseo-hint" data-hint="Wprowadź klucz API z panelu OpenAI">?</span>
                </label>
                <input type="password" id="api_key" name="api_key" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['api_key']); ?>">
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="default_model">
                    Domyślny model
                    <span class="cleanseo-hint" data-hint="Wybierz domyślny model AI">?</span>
                </label>
                <select id="default_model" name="default_model" class="cleanseo-select2">
                    <?php foreach ($settings['available_models'] as $model => $config): ?>
                        <option value="<?php echo esc_attr($model); ?>" 
                                <?php selected($settings['default_model'], $model); ?>>
                            <?php echo esc_html($config['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="max_tokens">
                    Maksymalna liczba tokenów
                    <span class="cleanseo-hint" data-hint="Maksymalna liczba tokenów w odpowiedzi">?</span>
                </label>
                <div class="cleanseo-slider" data-min="1" data-max="4096" data-step="1"></div>
                <input type="number" id="max_tokens" name="max_tokens" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['max_tokens']); ?>">
                <span class="slider-value"><?php echo esc_html($settings['max_tokens']); ?></span>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="temperature">
                    Temperatura
                    <span class="cleanseo-hint" data-hint="Kontroluje losowość odpowiedzi (0-1)">?</span>
                </label>
                <div class="cleanseo-slider" data-min="0" data-max="1" data-step="0.1"></div>
                <input type="number" id="temperature" name="temperature" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['temperature']); ?>" step="0.1">
                <span class="slider-value"><?php echo esc_html($settings['temperature']); ?></span>
            </div>
        </div>

        <!-- Ustawienia cache -->
        <div class="cleanseo-settings-section">
            <h2 class="cleanseo-settings-section-title">Ustawienia cache</h2>
            
            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label">
                    Włącz cache
                    <span class="cleanseo-hint" data-hint="Zapisz odpowiedzi API w cache">?</span>
                </label>
                <label class="cleanseo-switch">
                    <input type="checkbox" name="cache_enabled" 
                           <?php checked($settings['cache_enabled']); ?>>
                    <span class="cleanseo-switch-slider"></span>
                </label>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="cache_time">
                    Czas cache (sekundy)
                    <span class="cleanseo-hint" data-hint="Jak długo przechowywać odpowiedzi w cache">?</span>
                </label>
                <input type="number" id="cache_time" name="cache_time" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['cache_time']); ?>">
            </div>
        </div>

        <!-- Ustawienia generowania -->
        <div class="cleanseo-settings-section">
            <h2 class="cleanseo-settings-section-title">Ustawienia generowania</h2>
            
            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label">
                    Auto-generowanie
                    <span class="cleanseo-hint" data-hint="Automatycznie generuj treść przy tworzeniu posta">?</span>
                </label>
                <label class="cleanseo-switch">
                    <input type="checkbox" name="auto_generate_enabled" 
                           <?php checked($settings['auto_generate_enabled']); ?>>
                    <span class="cleanseo-switch-slider"></span>
                </label>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label">
                    Typy postów
                    <span class="cleanseo-hint" data-hint="Wybierz typy postów do auto-generowania">?</span>
                </label>
                <select name="enabled_post_types[]" class="cleanseo-select2" multiple>
                    <?php foreach (get_post_types(['public' => true], 'objects') as $post_type): ?>
                        <option value="<?php echo esc_attr($post_type->name); ?>"
                                <?php selected(in_array($post_type->name, $settings['enabled_post_types'])); ?>>
                            <?php echo esc_html($post_type->label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label">
                    Pola do generowania
                    <span class="cleanseo-hint" data-hint="Wybierz pola do auto-generowania">?</span>
                </label>
                <select name="enabled_fields[]" class="cleanseo-select2" multiple>
                    <option value="title" <?php selected(in_array('title', $settings['enabled_fields'])); ?>>
                        Tytuł
                    </option>
                    <option value="description" <?php selected(in_array('description', $settings['enabled_fields'])); ?>>
                        Opis
                    </option>
                    <option value="content" <?php selected(in_array('content', $settings['enabled_fields'])); ?>>
                        Treść
                    </option>
                </select>
            </div>
        </div>

        <!-- Zaawansowane ustawienia -->
        <div class="cleanseo-settings-section">
            <h2 class="cleanseo-settings-section-title">Zaawansowane ustawienia</h2>
            
            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="frequency_penalty">
                    Frequency Penalty
                    <span class="cleanseo-hint" data-hint="Kontroluje powtarzanie słów">?</span>
                </label>
                <div class="cleanseo-slider" data-min="-2" data-max="2" data-step="0.1"></div>
                <input type="number" id="frequency_penalty" name="frequency_penalty" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['frequency_penalty']); ?>" step="0.1">
                <span class="slider-value"><?php echo esc_html($settings['advanced_settings']['frequency_penalty']); ?></span>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="presence_penalty">
                    Presence Penalty
                    <span class="cleanseo-hint" data-hint="Kontroluje powtarzanie tematów">?</span>
                </label>
                <div class="cleanseo-slider" data-min="-2" data-max="2" data-step="0.1"></div>
                <input type="number" id="presence_penalty" name="presence_penalty" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['presence_penalty']); ?>" step="0.1">
                <span class="slider-value"><?php echo esc_html($settings['advanced_settings']['presence_penalty']); ?></span>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="top_p">
                    Top P
                    <span class="cleanseo-hint" data-hint="Kontroluje różnorodność odpowiedzi">?</span>
                </label>
                <div class="cleanseo-slider" data-min="0" data-max="1" data-step="0.1"></div>
                <input type="number" id="top_p" name="top_p" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['top_p']); ?>" step="0.1">
                <span class="slider-value"><?php echo esc_html($settings['advanced_settings']['top_p']); ?></span>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="retry_attempts">
                    Liczba prób
                    <span class="cleanseo-hint" data-hint="Liczba prób w przypadku błędu API">?</span>
                </label>
                <input type="number" id="retry_attempts" name="retry_attempts" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['retry_attempts']); ?>">
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="retry_delay">
                    Opóźnienie między próbami (sekundy)
                    <span class="cleanseo-hint" data-hint="Czas oczekiwania między próbami">?</span>
                </label>
                <input type="number" id="retry_delay" name="retry_delay" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['retry_delay']); ?>">
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="timeout">
                    Timeout (sekundy)
                    <span class="cleanseo-hint" data-hint="Maksymalny czas oczekiwania na odpowiedź API">?</span>
                </label>
                <input type="number" id="timeout" name="timeout" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['timeout']); ?>">
            </div>
        </div>

        <!-- Ustawienia budżetu -->
        <div class="cleanseo-settings-section">
            <h2 class="cleanseo-settings-section-title">Ustawienia budżetu</h2>
            
            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label">
                    Śledzenie kosztów
                    <span class="cleanseo-hint" data-hint="Śledź koszty użycia API">?</span>
                </label>
                <label class="cleanseo-switch">
                    <input type="checkbox" name="cost_tracking" 
                           <?php checked($settings['advanced_settings']['cost_tracking']); ?>>
                    <span class="cleanseo-switch-slider"></span>
                </label>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="budget_limit">
                    Limit budżetu
                    <span class="cleanseo-hint" data-hint="Maksymalny miesięczny budżet">?</span>
                </label>
                <input type="number" id="budget_limit" name="budget_limit" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['budget_limit']); ?>" step="0.01">
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="budget_alert_threshold">
                    Próg alertu (%)
                    <span class="cleanseo-hint" data-hint="Procent wykorzystania budżetu do wysłania alertu">?</span>
                </label>
                <div class="cleanseo-slider" data-min="0" data-max="100" data-step="1"></div>
                <input type="number" id="budget_alert_threshold" name="budget_alert_threshold" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['budget_alert_threshold']); ?>">
                <span class="slider-value"><?php echo esc_html($settings['advanced_settings']['budget_alert_threshold']); ?>%</span>
            </div>

            <div class="cleanseo-form-group">
                <label class="cleanseo-form-label" for="notification_email">
                    Email do powiadomień
                    <span class="cleanseo-hint" data-hint="Email do wysyłania alertów budżetowych">?</span>
                </label>
                <input type="email" id="notification_email" name="notification_email" class="cleanseo-form-control" 
                       value="<?php echo esc_attr($settings['advanced_settings']['notification_email']); ?>">
            </div>
        </div>
    </div>

    <div class="cleanseo-help">
        <button class="cleanseo-help-button">
            <i class="fas fa-question"></i>
        </button>
        <div class="cleanseo-help-content">
            <h3>Pomoc</h3>
            <p>Potrzebujesz pomocy w konfiguracji wtyczki? Sprawdź naszą dokumentację lub skontaktuj się z supportem.</p>
            <div class="cleanseo-help-actions">
                <a href="#" class="cleanseo-button">Dokumentacja</a>
                <a href="#" class="cleanseo-button">Support</a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Inicjalizacja komponentów
    $('.cleanseo-select2').select2({
        theme: 'cleanseo',
        width: '100%'
    });

    $('.cleanseo-slider').each(function() {
        var $slider = $(this);
        var $input = $slider.next('input');
        var $value = $slider.siblings('.slider-value');
        
        $slider.slider({
            min: $slider.data('min'),
            max: $slider.data('max'),
            step: $slider.data('step'),
            value: $input.val(),
            slide: function(event, ui) {
                $input.val(ui.value);
                $value.text(ui.value);
            }
        });
    });

    // Obsługa zapisywania ustawień
    $('#save-settings').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true)
               .html('<i class="fas fa-spinner fa-spin"></i> Zapisywanie...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_save_settings',
                nonce: cleanseo.nonce,
                settings: $('form').serialize()
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', 'Ustawienia zostały zapisane.');
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function() {
                showNotification('error', 'Wystąpił błąd podczas zapisywania ustawień.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Obsługa eksportu/importu
    $('#export-settings').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_export_settings',
                nonce: cleanseo.nonce
            },
            success: function(response) {
                if (response.success) {
                    var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'cleanseo-settings.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    showNotification('error', response.data.message);
                }
            }
        });
    });

    $('#import-settings').on('click', function() {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.onchange = function(e) {
            var file = e.target.files[0];
            var reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cleanseo_import_settings',
                            nonce: cleanseo.nonce,
                            settings: settings
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('success', 'Ustawienia zostały zaimportowane.');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showNotification('error', response.data.message);
                            }
                        }
                    });
                } catch (error) {
                    showNotification('error', 'Nieprawidłowy format pliku.');
                }
            };
            
            reader.readAsText(file);
        };
        
        input.click();
    });

    // Obsługa motywów
    $('.cleanseo-accessibility-button').on('click', function() {
        var $button = $(this);
        var action = $button.data('action');
        
        switch (action) {
            case 'theme':
                var currentTheme = $('body').hasClass('cleanseo-theme-dark') ? 'light' : 'dark';
                $('body').removeClass('cleanseo-theme-light cleanseo-theme-dark')
                        .addClass('cleanseo-theme-' + currentTheme);
                localStorage.setItem('cleanseo_theme', currentTheme);
                break;
                
            case 'font-size':
                var sizes = ['small', 'medium', 'large'];
                var currentSize = sizes.find(size => $('body').hasClass('cleanseo-font-size-' + size)) || 'medium';
                var currentIndex = sizes.indexOf(currentSize);
                var nextSize = sizes[(currentIndex + 1) % sizes.length];
                
                $('body').removeClass('cleanseo-font-size-' + currentSize)
                        .addClass('cleanseo-font-size-' + nextSize);
                localStorage.setItem('cleanseo_font_size', nextSize);
                break;
                
            case 'layout':
                var layouts = ['default', 'compact', 'wide'];
                var currentLayout = layouts.find(layout => $('body').hasClass('cleanseo-layout-' + layout)) || 'default';
                var currentIndex = layouts.indexOf(currentLayout);
                var nextLayout = layouts[(currentIndex + 1) % layouts.length];
                
                $('body').removeClass('cleanseo-layout-' + currentLayout)
                        .addClass('cleanseo-layout-' + nextLayout);
                localStorage.setItem('cleanseo_layout', nextLayout);
                break;
        }
    });

    // Funkcja pokazywania powiadomień
    function showNotification(type, message) {
        var $notification = $('<div class="cleanseo-notification cleanseo-notification-' + type + '">' +
            '<i class="fas fa-' + (type === 'success' ? 'check' : 'times') + '"></i> ' +
            message +
            '</div>');

        $('body').append($notification);
        $notification.fadeIn();

        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
});
</script> 