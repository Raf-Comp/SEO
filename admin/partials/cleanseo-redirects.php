<?php
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap cleanseo-redirects-container">
    <div class="cleanseo-redirects-header">
        <h1><?php _e('Przekierowania', 'cleanseo-optimizer'); ?></h1>
    </div>

    <div class="cleanseo-redirects-form">
        <form id="cleanseo-redirects-form">
            <?php wp_nonce_field('cleanseo_redirects_nonce', 'cleanseo_redirects_nonce'); ?>
            
            <div class="form-row">
                <input type="text" name="source_url" placeholder="<?php _e('Źródłowy URL', 'cleanseo-optimizer'); ?>" required>
                <input type="text" name="target_url" placeholder="<?php _e('Docelowy URL', 'cleanseo-optimizer'); ?>" required>
                <select name="status_code">
                    <option value="301">301 - Stałe przekierowanie</option>
                    <option value="302">302 - Tymczasowe przekierowanie</option>
                </select>
                <button type="submit" class="button button-primary"><?php _e('Dodaj przekierowanie', 'cleanseo-optimizer'); ?></button>
            </div>
        </form>
    </div>

    <div class="cleanseo-redirects-tools" style="margin-bottom:20px;">
        <button id="cleanseo-export-redirects" class="button">Eksportuj CSV</button>
        <form id="cleanseo-import-redirects-form" enctype="multipart/form-data" style="display:inline-block; margin-left:10px;">
            <input type="file" name="csv" accept=".csv" style="display:inline-block; width:auto;">
            <button type="submit" class="button">Importuj CSV</button>
        </form>
        <button id="cleanseo-batch-delete" class="button button-danger" style="margin-left:10px;">Usuń zaznaczone</button>
    </div>

    <div id="cleanseo-redirects-list">
        <div class="loading"><?php _e('Ładowanie...', 'cleanseo-optimizer'); ?></div>
    </div>

    <div class="cleanseo-redirects-help">
        <h3><?php _e('Pomoc', 'cleanseo-optimizer'); ?></h3>
        <p><?php _e('Przekierowania pozwalają na automatyczne przekierowanie użytkowników z jednego adresu URL na inny. Jest to przydatne w przypadku:', 'cleanseo-optimizer'); ?></p>
        <ul>
            <li><?php _e('Zmiany adresów URL stron', 'cleanseo-optimizer'); ?></li>
            <li><?php _e('Konsolidacji treści', 'cleanseo-optimizer'); ?></li>
            <li><?php _e('Naprawy błędów 404', 'cleanseo-optimizer'); ?></li>
            <li><?php _e('Optymalizacji struktury strony', 'cleanseo-optimizer'); ?></li>
        </ul>
        <p><strong><?php _e('Typy przekierowań:', 'cleanseo-optimizer'); ?></strong></p>
        <ul>
            <li><strong>301</strong> - <?php _e('Stałe przekierowanie. Używaj gdy strona została trwale przeniesiona.', 'cleanseo-optimizer'); ?></li>
            <li><strong>302</strong> - <?php _e('Tymczasowe przekierowanie. Używaj gdy strona jest tymczasowo niedostępna.', 'cleanseo-optimizer'); ?></li>
        </ul>
    </div>
</div>

<style>
.cleanseo-redirects-help {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cleanseo-redirects-help h3 {
    margin-top: 0;
    color: #1d2327;
}

.cleanseo-redirects-help ul {
    margin-left: 20px;
    list-style-type: disc;
}

.cleanseo-redirects-help li {
    margin-bottom: 5px;
}
</style> 