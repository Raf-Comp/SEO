<?php
/**
 * Panel ustawień mapy strony XML
 *
 * @package CleanSEO_Optimizer
 * @since 1.0.0
 */

if (!defined('WPINC')) { die; }

// Pobierz obiekt sitemap
$sitemap = new CleanSEO_Sitemap();

// Pobierz dostępne typy postów i taksonomie
$post_types = $sitemap->get_post_types();
$taxonomies = $sitemap->get_taxonomies();
$excluded_post_types = get_option('cleanseo_excluded_post_types', array());
$excluded_taxonomies = get_option('cleanseo_excluded_taxonomies', array());

// Pobierz ustawienia
global $wpdb;
$table_name = $wpdb->prefix . 'seo_settings';
$settings = null;
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
    $settings = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1");
}
if (!$settings) {
    $settings = (object) array(
        'sitemap_enabled' => 1,
        'sitemap_robots' => 1,
        'sitemap_images' => 1,
        'sitemap_video' => 0,
        'sitemap_news' => 0
    );
}

// Pobierz robots.txt
$robots_content = $sitemap->get_robots_txt();

// Sprawdź, czy nastąpiła aktualizacja
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
$error = isset($_GET['error']) && $_GET['error'] == '1';
?>

<div class="wrap cleanseo-sitemap-settings">
    <h1><?php _e('Ustawienia Mapy Strony i robots.txt', 'cleanseo-optimizer'); ?></h1>
    
    <?php if ($updated): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Ustawienia zostały pomyślnie zapisane.', 'cleanseo-optimizer'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('Wystąpił błąd podczas zapisywania ustawień.', 'cleanseo-optimizer'); ?></p>
    </div>
    <?php endif; ?>

    <div class="cleanseo-card">
        <h2><?php _e('Główne ustawienia mapy strony', 'cleanseo-optimizer'); ?></h2>
        <form id="cleanseo-sitemap-settings-form" method="post">
            <?php wp_nonce_field('cleanseo_sitemap_settings_save', 'cleanseo_sitemap_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Włącz mapę strony XML', 'cleanseo-optimizer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sitemap_enabled" value="1" <?php checked(isset($settings->sitemap_enabled) && $settings->sitemap_enabled); ?>>
                            <?php _e('Generuj mapę strony XML', 'cleanseo-optimizer'); ?>
                        </label>
                        <p class="description"><?php _e('Adres mapy strony: ', 'cleanseo-optimizer'); ?><a href="<?php echo esc_url(home_url('cleanseo-sitemap.xml')); ?>" target="_blank"><?php echo esc_html(home_url('cleanseo-sitemap.xml')); ?></a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Dodaj odniesienie w robots.txt', 'cleanseo-optimizer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sitemap_robots" value="1" <?php checked(isset($settings->sitemap_robots) && $settings->sitemap_robots); ?>>
                            <?php _e('Automatycznie dodaj mapę strony do robots.txt', 'cleanseo-optimizer'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Zawartość mapy strony', 'cleanseo-optimizer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sitemap_images" value="1" <?php checked(isset($settings->sitemap_images) && $settings->sitemap_images); ?>>
                            <?php _e('Uwzględnij obrazy', 'cleanseo-optimizer'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="sitemap_video" value="1" <?php checked(isset($settings->sitemap_video) && $settings->sitemap_video); ?>>
                            <?php _e('Uwzględnij wideo', 'cleanseo-optimizer'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="sitemap_news" value="1" <?php checked(isset($settings->sitemap_news) && $settings->sitemap_news); ?>>
                            <?php _e('Uwzględnij wiadomości (Google News)', 'cleanseo-optimizer'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Wyklucz typy postów z mapy strony', 'cleanseo-optimizer'); ?></h3>
            <div class="cleanseo-types-list">
                <?php foreach ($post_types as $type) : ?>
                    <label class="cleanseo-checkbox-label">
                        <input type="checkbox" name="excluded_post_types[]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $excluded_post_types)); ?>>
                        <?php echo esc_html($type->label); ?>
                        <span class="cleanseo-post-count">(<?php echo esc_html($type->count); ?> <?php _e('wpisów', 'cleanseo-optimizer'); ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <h3><?php _e('Wyklucz taksonomie z mapy strony', 'cleanseo-optimizer'); ?></h3>
            <div class="cleanseo-tax-list">
                <?php foreach ($taxonomies as $tax) : ?>
                    <label class="cleanseo-checkbox-label">
                        <input type="checkbox" name="excluded_taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $excluded_taxonomies)); ?>>
                        <?php echo esc_html($tax->label); ?>
                        <span class="cleanseo-post-count">(<?php echo esc_html($tax->count); ?> <?php _e('terminów', 'cleanseo-optimizer'); ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div class="cleanseo-button-row">
                <button type="submit" class="button button-primary"><?php _e('Zapisz ustawienia', 'cleanseo-optimizer'); ?></button>
                <button type="button" id="cleanseo-ping-search-engines" class="button"><?php _e('Wyślij ping do wyszukiwarek', 'cleanseo-optimizer'); ?></button>
            </div>
        </form>
    </div>

    <div class="cleanseo-card">
        <h2><?php _e('Edycja pliku robots.txt', 'cleanseo-optimizer'); ?></h2>
        <form id="cleanseo-robots-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cleanseo_save_robots">
            <?php wp_nonce_field('cleanseo_save_robots', 'cleanseo_robots_nonce'); ?>
            
            <p class="description"><?php _e('Edytuj zawartość pliku robots.txt. Jeśli włączono opcję "Dodaj odniesienie w robots.txt", to odniesienie do mapy strony zostanie dodane automatycznie.', 'cleanseo-optimizer'); ?></p>
            
            <textarea name="robots_content" id="cleanseo-robots-content" rows="12" class="widefat code"><?php echo esc_textarea($robots_content); ?></textarea>
            
            <div class="cleanseo-button-row">
                <button type="submit" class="button button-primary"><?php _e('Zapisz robots.txt', 'cleanseo-optimizer'); ?></button>
                <button type="button" id="cleanseo-reset-robots" class="button"><?php _e('Przywróć domyślne', 'cleanseo-optimizer'); ?></button>
            </div>
        </form>
    </div>

    <div class="cleanseo-card">
        <h2><?php _e('Podgląd i walidacja mapy strony', 'cleanseo-optimizer'); ?></h2>
        
        <p><?php _e('Kliknij poniższe przyciski, aby zobaczyć lub zweryfikować swoją mapę strony:', 'cleanseo-optimizer'); ?></p>
        
        <div class="cleanseo-button-row">
            <a href="<?php echo esc_url(home_url('cleanseo-sitemap.xml')); ?>" target="_blank" class="button"><?php _e('Otwórz mapę strony', 'cleanseo-optimizer'); ?></a>
            <a href="<?php echo esc_url(home_url('sitemap-index.xml')); ?>" target="_blank" class="button"><?php _e('Otwórz indeks map', 'cleanseo-optimizer'); ?></a>
            <button type="button" id="cleanseo-validate-sitemap" class="button"><?php _e('Zweryfikuj mapę strony', 'cleanseo-optimizer'); ?></button>
        </div>
        
        <div id="cleanseo-sitemap-validation-result" class="cleanseo-validation-result"></div>
        
        <div class="cleanseo-sitemap-preview">
            <h3><?php _e('Podgląd mapy strony', 'cleanseo-optimizer'); ?></h3>
            <iframe id="cleanseo-sitemap-preview" src="<?php echo esc_url(home_url('cleanseo-sitemap.xml')); ?>" frameborder="0"></iframe>
        </div>
    </div>
</div>

<style>
.cleanseo-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 3px;
}
.cleanseo-types-list, .cleanseo-tax-list {
    margin: 10px 0 20px;
    display: flex;
    flex-wrap: wrap;
}
.cleanseo-checkbox-label {
    display: block;
    width: 33%;
    margin-bottom: 8px;
    padding: 3px 5px;
}
.cleanseo-post-count {
    color: #777;
    font-size: 0.9em;
}
.cleanseo-button-row {
    margin: 20px 0 10px;
}
.cleanseo-button-row .button {
    margin-right: 10px;
}
.cleanseo-sitemap-preview {
    margin-top: 20px;
}
#cleanseo-sitemap-preview {
    width: 100%;
    height: 400px;
    border: 1px solid #ddd;
    margin-top: 10px;
}
.cleanseo-validation-result {
    margin: 15px 0;
    padding: 10px;
    display: none;
}
.cleanseo-validation-success {
    background: #f0f8e6;
    border-left: 4px solid #7ad03a;
}
.cleanseo-validation-error {
    background: #fef1f1;
    border-left: 4px solid #dc3232;
}
@media screen and (max-width: 782px) {
    .cleanseo-checkbox-label {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Walidacja mapy strony
    $('#cleanseo-validate-sitemap').on('click', function() {
        var $result = $('#cleanseo-sitemap-validation-result');
        $result.removeClass('cleanseo-validation-success cleanseo-validation-error').html('').show();
        $result.html('<p><span class="spinner is-active" style="float:none;margin:0;"></span> <?php _e('Weryfikacja mapy strony...', 'cleanseo-optimizer'); ?></p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_validate_sitemap',
                nonce: '<?php echo wp_create_nonce('cleanseo_validate_sitemap'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('cleanseo-validation-success').html('<p>' + response.data.message + '</p>');
                } else {
                    $result.addClass('cleanseo-validation-error').html('<p>' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.addClass('cleanseo-validation-error').html('<p><?php _e('Wystąpił błąd podczas weryfikacji mapy strony.', 'cleanseo-optimizer'); ?></p>');
            }
        });
    });
    
    // Ping wyszukiwarek
    $('#cleanseo-ping-search-engines').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Wysyłanie...', 'cleanseo-optimizer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_ping_search_engines',
                nonce: '<?php echo wp_create_nonce('cleanseo_ping_search_engines'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
                $button.prop('disabled', false).text('<?php _e('Wyślij ping do wyszukiwarek', 'cleanseo-optimizer'); ?>');
            },
            error: function() {
                alert('<?php _e('Wystąpił błąd podczas wysyłania pingu.', 'cleanseo-optimizer'); ?>');
                $button.prop('disabled', false).text('<?php _e('Wyślij ping do wyszukiwarek', 'cleanseo-optimizer'); ?>');
            }
        });
        
        return false;
    });
    
    // Resetowanie robots.txt
    $('#cleanseo-reset-robots').on('click', function() {
        if (confirm('<?php _e('Czy na pewno chcesz przywrócić domyślną zawartość pliku robots.txt?', 'cleanseo-optimizer'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_reset_robots',
                    nonce: '<?php echo wp_create_nonce('cleanseo_reset_robots'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#cleanseo-robots-content').val(response.data.content);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e('Wystąpił błąd podczas resetowania pliku robots.txt.', 'cleanseo-optimizer'); ?>');
                }
            });
        }
        return false;
    });
});
</script>