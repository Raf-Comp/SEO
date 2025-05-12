<?php
/**
 * Widok panelu administracyjnego wtyczki
 */

if (!defined('WPINC')) {
    die;
}

// Sprawdzenie uprawnień
if (!current_user_can('manage_options')) {
    wp_die(__('Nie masz wystarczających uprawnień, aby uzyskać dostęp do tej strony.'));
}

// Pobierz aktualne ustawienia
global $wpdb;
$table_name = $wpdb->prefix . 'seo_settings';
$settings = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");

// Inicjalizacja domyślnych ustawień
if (!$settings) {
    $settings = (object) array(
        'meta_title' => '',
        'meta_description' => '',
        'og_image_url' => '',
        'sitemap_enabled' => 0
    );
}

// Pobierz instancję mapy witryny
$sitemap = new CleanSEO_Sitemap();
$post_types = $sitemap->get_post_types();
$excluded_post_types = get_option('cleanseo_excluded_post_types', array());

// Pobierz zawartość robots.txt
$robots_content = $sitemap->get_robots_txt();

// Pobierz aktualną zakładkę
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'seo';

// Pobierz dane konkurencji
$competitors = new CleanSEO_Competitors();
$competitors_data = $competitors->get_competitors_data();

// Pobierz dane analizy treści
$content_analysis = new CleanSEO_ContentAnalysis();
$content_length = $content_analysis->get_content_length();
$keyword_density = $content_analysis->get_keyword_density();
$headers = $content_analysis->get_headers();
$flesch_score = $content_analysis->get_flesch_score();
$avg_sentence_length = $content_analysis->get_avg_sentence_length();
$passive_voice_percentage = $content_analysis->get_passive_voice_percentage();
$internal_links_count = $content_analysis->get_internal_links_count();
$unique_target_pages = $content_analysis->get_unique_target_pages();
$link_suggestions = $content_analysis->get_link_suggestions();
$content_length_score = $content_analysis->get_content_length_score();
$headers_score = $content_analysis->get_headers_score();
$readability_score = $content_analysis->get_readability_score();
$seo_score = $content_analysis->get_seo_score();
?>

<div class="cleanseo-admin-wrapper">
    <!-- Logo i nagłówek -->
    <header class="cleanseo-header">
        <img src="<?php echo CLEANSEO_PLUGIN_URL; ?>assets/images/logo.svg" alt="CleanSEO" class="cleanseo-logo" />
        <h1>CleanSEO Optimizer</h1>
    </header>

    <!-- Nawigacja (zakładki) -->
    <nav class="cleanseo-tabs">
        <button class="tab-btn active" data-tab="seo">Ustawienia SEO</button>
        <button class="tab-btn" data-tab="sitemap">Mapa witryny</button>
        <button class="tab-btn" data-tab="redirects">Przekierowania</button>
        <button class="tab-btn" data-tab="competitors">Konkurencja</button>
        <button class="tab-btn" data-tab="schema">Dane strukturalne</button>
        <button class="tab-btn" data-tab="content">Analiza treści</button>
        <button class="tab-btn" data-tab="integrations">Integracje i monitoring</button>
        <button class="tab-btn" data-tab="local">SEO Lokalne</button>
        <button class="tab-btn" data-tab="audit">Audyt SEO</button>
    </nav>

    <main class="cleanseo-content">
        <!-- Ustawienia SEO -->
        <section class="tab-content active" id="tab-seo">
            <form id="cleanseo-settings-form">
                <?php wp_nonce_field('cleanseo_save_settings', 'cleanseo_settings_nonce'); ?>
                <div class="form-group">
                    <label for="meta_title">Tytuł meta</label>
                    <input type="text" id="meta_title" name="meta_title" value="<?php echo esc_attr($settings->meta_title ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="meta_description">Opis meta</label>
                    <textarea id="meta_description" name="meta_description"><?php echo esc_textarea($settings->meta_description ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="og_image_url">URL obrazu OG</label>
                    <input type="url" id="og_image_url" name="og_image_url" value="<?php echo esc_url($settings->og_image_url ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="sitemap_enabled" value="1" <?php checked($settings->sitemap_enabled ?? 0, 1); ?>> Włącz mapę witryny</label>
                </div>
                <button type="submit" class="button button-primary" id="save-settings">Zapisz ustawienia</button>
            </form>
        </section>

        <!-- Mapa witryny -->
        <section class="tab-content" id="tab-sitemap">
            <div class="cleanseo-card">
                <h2>Mapa witryny</h2>
                <p>Mapa witryny generowana automatycznie. <a href="<?php echo home_url('/sitemap.xml'); ?>" target="_blank">Zobacz mapę witryny</a></p>
            </div>
        </section>

        <!-- Przekierowania -->
        <section class="tab-content" id="tab-redirects">
            <div class="cleanseo-card">
                <h2>Przekierowania</h2>
                <form id="redirects-form">
                    <?php wp_nonce_field('cleanseo_save_redirects', 'cleanseo_redirects_nonce'); ?>
                    <div class="form-row">
                        <input type="text" name="source_url" placeholder="Źródłowy URL">
                        <input type="text" name="target_url" placeholder="Docelowy URL">
                        <select name="status_code">
                            <option value="301">301</option>
                            <option value="302">302</option>
                        </select>
                        <button type="submit" class="button">Dodaj przekierowanie</button>
                    </div>
                </form>
                <div id="redirects-list"></div>
            </div>
        </section>

        <!-- Konkurencja -->
        <section class="tab-content" id="tab-competitors">
            <div class="cleanseo-card">
                <h2>Konkurencja</h2>
                <form id="competitor-form">
                    <?php wp_nonce_field('cleanseo_add_competitor', 'cleanseo_competitor_nonce'); ?>
                    <div class="form-row">
                        <input type="text" id="competitor-domain" name="domain" placeholder="Domena konkurenta">
                        <input type="text" id="competitor-keywords" name="keywords" placeholder="Słowa kluczowe (oddziel przecinkami)">
                        <button type="submit" class="button" id="add-competitor">Dodaj konkurenta</button>
                    </div>
                </form>
                <div id="competitors-list"></div>
            </div>
        </section>

        <!-- Dane strukturalne -->
        <section class="tab-content" id="tab-schema">
            <div class="cleanseo-card">
                <h2>Dane strukturalne</h2>
                <p>Automatyczne generowanie danych schema.org dla strony i produktów.</p>
            </div>
        </section>

        <!-- Analiza treści -->
        <section class="tab-content" id="tab-content">
            <div class="cleanseo-card">
                <h2>Analiza treści</h2>
                <div id="content-analysis"></div>
            </div>
        </section>

        <!-- Integracje i monitoring -->
        <section class="tab-content" id="tab-integrations">
            <div class="cleanseo-card">
                <h2>Integracje i monitoring</h2>
                <p>Google Search Console, Google Analytics 4, WooCommerce, monitoring 404, edycja .htaccess.</p>
            </div>
        </section>

        <!-- SEO Lokalne -->
        <section class="tab-content" id="tab-local">
            <div class="cleanseo-card">
                <h2>SEO Lokalne</h2>
                <div id="local-seo"></div>
            </div>
        </section>

        <!-- Audyt SEO -->
        <section class="tab-content" id="tab-audit">
            <div class="cleanseo-card">
                <h2>Audyt SEO</h2>
                <button type="button" class="button button-primary" id="run-audit">Uruchom audyt teraz</button>
                <div id="audit-results"></div>
            </div>
        </section>
    </main>
</div>

<!-- Modale, powiadomienia, loading -->
<div id="cleanseo-modal" class="cleanseo-modal" style="display:none;"></div>
<div id="cleanseo-toast" class="cleanseo-toast" style="display:none;"></div>

<style>
.cleanseo-admin-wrapper {
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    padding: 0 0 40px 0;
    font-family: 'Inter', Arial, sans-serif;
}
.cleanseo-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 32px 40px 0 40px;
}
.cleanseo-logo {
    width: 48px;
    height: 48px;
}
.cleanseo-header h1 {
    font-size: 2.2rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}
.cleanseo-tabs {
    display: flex;
    gap: 8px;
    padding: 24px 40px 0 40px;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
}
.tab-btn {
    background: none;
    border: none;
    font-size: 1rem;
    font-weight: 600;
    color: #4a5568;
    padding: 12px 20px;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
}
.tab-btn.active, .tab-btn:hover {
    background: #3182ce;
    color: #fff;
}
.cleanseo-content {
    padding: 32px 40px 0 40px;
    display: grid;
    gap: 32px;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.cleanseo-card {
    background: #f7fafc;
    border-radius: 8px;
    padding: 32px;
    box-shadow: 0 2px 8px rgba(49,130,206,0.04);
    margin-bottom: 24px;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
}
input[type="text"], input[type="url"], textarea, select {
    width: 100%;
    padding: 0.7rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    background: #fff;
    margin-top: 0.2rem;
}
textarea {
    min-height: 80px;
}
.button {
    background: #3182ce;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 24px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.button:hover {
    background: #2563eb;
}
.form-row {
    display: flex;
    gap: 12px;
    margin-bottom: 1rem;
}
.cleanseo-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.cleanseo-toast {
    position: fixed;
    bottom: 32px;
    right: 32px;
    background: #3182ce;
    color: #fff;
    padding: 16px 32px;
    border-radius: 8px;
    font-size: 1.1rem;
    box-shadow: 0 2px 8px rgba(49,130,206,0.12);
    z-index: 9999;
}
@media (max-width: 900px) {
    .cleanseo-header, .cleanseo-tabs, .cleanseo-content {
        padding-left: 16px;
        padding-right: 16px;
    }
    .cleanseo-card {
        padding: 16px;
    }
}
</style>
<script>
jQuery(document).ready(function($) {
    // Zakładki
    $('.tab-btn').click(function() {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        var tab = $(this).data('tab');
        $('.tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    // Toast
    window.cleanseoToast = function(msg) {
        $('#cleanseo-toast').text(msg).fadeIn(200);
        setTimeout(function() { $('#cleanseo-toast').fadeOut(200); }, 3000);
    };
    // Modal
    window.cleanseoModal = function(html) {
        $('#cleanseo-modal').html(html).fadeIn(200);
    };
    $(document).on('click', '#cleanseo-modal', function(e) {
        if (e.target === this) $(this).fadeOut(200);
    });
});
</script>