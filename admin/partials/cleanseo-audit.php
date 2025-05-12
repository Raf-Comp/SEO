<?php
if (!defined('WPINC')) {
    die;
}

global $wpdb;
$audit_table = $wpdb->prefix . 'seo_audits';

// Sprawdzanie czy tabela istnieje
if (!$wpdb->get_var("SHOW TABLES LIKE '$audit_table'")) {
    echo '<div class="notice notice-error"><p>' . __('Uwaga: Tabela audytów nie istnieje. Należy aktywować wtyczkę ponownie lub skontaktować się z pomocą techniczną.', 'cleanseo-optimizer') . '</p></div>';
} 

// Pobierz ostatnie audyty
$audits = $wpdb->get_results("SELECT * FROM $audit_table ORDER BY timestamp DESC LIMIT 10");

// Pobierz dane do wykresu
$chart_data = $wpdb->get_results(
    "SELECT id, timestamp, score 
    FROM $audit_table 
    ORDER BY timestamp ASC 
    LIMIT 15"
);

// Pobierz dane o częstotliwości
$frequency = get_option('cleanseo_audit_frequency', 'weekly');
$next_scheduled = get_option('cleanseo_next_scheduled_audit', '');
?>

<div class="wrap cleanseo-admin cleanseo-audit-page">
    <div class="cleanseo-header">
        <h1><span class="dashicons dashicons-chart-bar"></span> <?php _e('Audyt SEO', 'cleanseo-optimizer'); ?></h1>
        <p class="cleanseo-header-desc"><?php _e('Kompleksowy audyt SEO analizuje kluczowe aspekty optymalizacji Twojej witryny.', 'cleanseo-optimizer'); ?></p>
    </div>

    <div class="cleanseo-content">
        <div class="cleanseo-dashboard-grid">
            <!-- Panel sterowania audytem -->
            <div class="cleanseo-card cleanseo-control-panel">
                <div class="cleanseo-card-header">
                    <h2><?php _e('Panel sterowania', 'cleanseo-optimizer'); ?></h2>
                </div>
                <div class="cleanseo-card-body">
                    <div class="cleanseo-audit-controls">
                        <div class="cleanseo-run-audit">
                            <h3><?php _e('Uruchom audyt', 'cleanseo-optimizer'); ?></h3>
                            <p><?php _e('Wybierz zakres audytu i uruchom analizę SEO swojej witryny.', 'cleanseo-optimizer'); ?></p>
                            
                            <div class="cleanseo-form-field">
                                <label for="audit-scope"><?php _e('Zakres audytu:', 'cleanseo-optimizer'); ?></label>
                                <select id="audit-scope" class="cleanseo-select">
                                <option value="full"><?php _e('Pełny audyt (wszystkie strony)', 'cleanseo-optimizer'); ?></option>
                                    <option value="posts"><?php _e('Tylko wpisy', 'cleanseo-optimizer'); ?></option>
                                    <option value="pages"><?php _e('Tylko strony', 'cleanseo-optimizer'); ?></option>
                                </select>
                            </div>
                            
                            <button type="button" class="button button-primary button-hero" id="run-audit">
                                <span class="dashicons dashicons-search"></span> <?php _e('Uruchom audyt teraz', 'cleanseo-optimizer'); ?>
                            </button>
                            <p class="audit-status"></p>
                        </div>

                        <div class="cleanseo-schedule-audit">
                            <h3><?php _e('Harmonogram audytów', 'cleanseo-optimizer'); ?></h3>
                            <p><?php _e('Ustaw automatyczne audyty z wybraną częstotliwością.', 'cleanseo-optimizer'); ?></p>
                            
                            <div class="cleanseo-form-field">
                                <label for="audit-frequency"><?php _e('Częstotliwość:', 'cleanseo-optimizer'); ?></label>
                                <select id="audit-frequency" class="cleanseo-select">
                                    <option value="daily" <?php selected($frequency, 'daily'); ?>><?php _e('Codziennie', 'cleanseo-optimizer'); ?></option>
                                    <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php _e('Co tydzień', 'cleanseo-optimizer'); ?></option>
                                    <option value="monthly" <?php selected($frequency, 'monthly'); ?>><?php _e('Co miesiąc', 'cleanseo-optimizer'); ?></option>
                                </select>
                            </div>
                            
                            <button type="button" class="button button-secondary" id="save-schedule">
                                <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Zapisz harmonogram', 'cleanseo-optimizer'); ?>
                            </button>
                            
                            <?php if($next_scheduled): ?>
                            <p class="next-scheduled">
                                <?php _e('Następny audyt:', 'cleanseo-optimizer'); ?> 
                                <strong><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($next_scheduled)); ?></strong>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statystyki audytów -->
            <div class="cleanseo-card cleanseo-audit-stats">
                <div class="cleanseo-card-header">
                    <h2><?php _e('Statystyki audytów', 'cleanseo-optimizer'); ?></h2>
                </div>
                <div class="cleanseo-card-body">
                    <?php if(!empty($chart_data)): ?>
                    <div class="cleanseo-chart-container">
                        <canvas id="audit-history-chart"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="cleanseo-no-data">
                        <p><?php _e('Brak danych do wyświetlenia. Uruchom pierwszy audyt, aby zobaczyć statystyki.', 'cleanseo-optimizer'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lista audytów -->
        <div class="cleanseo-card cleanseo-audits-list">
            <div class="cleanseo-card-header">
                <h2><?php _e('Historia audytów', 'cleanseo-optimizer'); ?></h2>
                <div class="cleanseo-card-actions">
                    <select id="audit-filter" class="cleanseo-select">
                        <option value="all"><?php _e('Wszystkie audyty', 'cleanseo-optimizer'); ?></option>
                        <option value="manual"><?php _e('Ręczne', 'cleanseo-optimizer'); ?></option>
                        <option value="scheduled"><?php _e('Zaplanowane', 'cleanseo-optimizer'); ?></option>
                    </select>
                </div>
            </div>
            <div class="cleanseo-card-body">
                <?php if($audits): ?>
                <div class="cleanseo-table-container">
                    <table class="cleanseo-table">
                        <thead>
                            <tr>
                                <th><?php _e('Data', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Wynik', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Typ', 'cleanseo-optimizer'); ?></th>
                                <th><?php _e('Akcje', 'cleanseo-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($audits as $audit): ?>
                            <tr data-type="<?php echo esc_attr($audit->type); ?>">
                                <td class="audit-date">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($audit->timestamp)); ?>
                                </td>
                                <td class="audit-score">
                                    <div class="score-badge score-<?php echo $this->get_score_class($audit->score); ?>">
                                        <?php echo esc_html($audit->score); ?>%
                                    </div>
                                </td>
                                <td class="audit-type">
                                    <?php echo $audit->type === 'manual' ? __('Ręczny', 'cleanseo-optimizer') : __('Zaplanowany', 'cleanseo-optimizer'); ?>
                                </td>
                                <td class="audit-actions">
                                    <div class="cleanseo-action-buttons">
                                        <button type="button" class="button button-small view-audit" data-id="<?php echo esc_attr($audit->id); ?>">
                                            <span class="dashicons dashicons-visibility"></span> <?php _e('Zobacz', 'cleanseo-optimizer'); ?>
                                        </button>
                                        <div class="cleanseo-dropdown">
                                            <button type="button" class="button button-small">
                                                <span class="dashicons dashicons-download"></span> <?php _e('Eksportuj', 'cleanseo-optimizer'); ?>
                                            </button>
                                            <div class="cleanseo-dropdown-content">
                                                <a href="#" class="export-pdf" data-id="<?php echo esc_attr($audit->id); ?>">
                                                    <span class="dashicons dashicons-pdf"></span> <?php _e('PDF', 'cleanseo-optimizer'); ?>
                                                </a>
                                                <a href="#" class="export-csv" data-id="<?php echo esc_attr($audit->id); ?>">
                                                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('CSV', 'cleanseo-optimizer'); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <button type="button" class="button button-small button-link-delete delete-audit" data-id="<?php echo esc_attr($audit->id); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="cleanseo-no-data">
                    <p><?php _e('Nie wykonano jeszcze żadnego audytu.', 'cleanseo-optimizer'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal z wynikami audytu -->
    <div id="audit-results-modal" class="cleanseo-modal">
        <div class="cleanseo-modal-content">
            <div class="cleanseo-modal-header">
                <h2><?php _e('Raport audytu SEO', 'cleanseo-optimizer'); ?></h2>
                <button type="button" class="cleanseo-modal-close">&times;</button>
            </div>
            <div class="cleanseo-modal-body">
                <div class="cleanseo-audit-overview">
                    <div class="audit-score-display">
                        <div class="score-circle" id="score-circle">
                            <div class="score-value">0</div>
                            <div class="score-percent">%</div>
                        </div>
                        <div class="audit-date" id="audit-date"></div>
                    </div>
                    
                    <div class="audit-summary">
                        <div class="audit-summary-item">
                            <div class="summary-label"><?php _e('Analizowane strony', 'cleanseo-optimizer'); ?></div>
                            <div class="summary-value" id="pages-analyzed">0</div>
                        </div>
                        <div class="audit-summary-item">
                            <div class="summary-label"><?php _e('Problemy', 'cleanseo-optimizer'); ?></div>
                            <div class="summary-value" id="issues-found">0</div>
                        </div>
                        <div class="audit-summary-item">
                            <div class="summary-label"><?php _e('Rekomendacje', 'cleanseo-optimizer'); ?></div>
                            <div class="summary-value" id="recommendations">0</div>
                        </div>
                    </div>
                </div>
                
                <div class="cleanseo-tabs">
                    <div class="cleanseo-tabs-nav">
                        <button type="button" class="tab-button active" data-tab="site-analysis"><?php _e('Analiza witryny', 'cleanseo-optimizer'); ?></button>
                        <button type="button" class="tab-button" data-tab="meta-tags"><?php _e('Meta tagi', 'cleanseo-optimizer'); ?></button>
                        <button type="button" class="tab-button" data-tab="content"><?php _e('Treść', 'cleanseo-optimizer'); ?></button>
                        <button type="button" class="tab-button" data-tab="images"><?php _e('Obrazki', 'cleanseo-optimizer'); ?></button>
                        <button type="button" class="tab-button" data-tab="links"><?php _e('Linki', 'cleanseo-optimizer'); ?></button>
                        <button type="button" class="tab-button" data-tab="schema"><?php _e('Schema', 'cleanseo-optimizer'); ?></button>
                    </div>
                    <div class="cleanseo-tabs-content">
                        <div class="tab-content active" id="site-analysis-tab">
                            <!-- Zawartość zostanie wstawiona dynamicznie -->
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                        <div class="tab-content" id="meta-tags-tab">
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                        <div class="tab-content" id="content-tab">
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                        <div class="tab-content" id="images-tab">
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                        <div class="tab-content" id="links-tab">
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                        <div class="tab-content" id="schema-tab">
                            <div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cleanseo-modal-footer">
                <div class="export-options">
                    <button type="button" class="button export-pdf-modal" data-id="">
                        <span class="dashicons dashicons-pdf"></span> <?php _e('Eksportuj PDF', 'cleanseo-optimizer'); ?>
                    </button>
                    <button type="button" class="button export-csv-modal" data-id="">
                        <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Eksportuj CSV', 'cleanseo-optimizer'); ?>
                    </button>
                </div>
                <button type="button" class="button button-primary close-modal">
                    <?php _e('Zamknij', 'cleanseo-optimizer'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Globalne style */
.cleanseo-audit-page {
    --primary-color: #2271b1;
    --secondary-color: #135e96;
    --success-color: #46b450;
    --warning-color: #ffb900;
    --error-color: #dc3232;
    --light-bg: #f0f6fc;
    --border-color: #c3c4c7;
    --text-color: #1d2327;
    --text-light: #646970;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: var(--text-color);
}

/* Nagłówek strony */
.cleanseo-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.cleanseo-header h1 {
    display: flex;
    align-items: center;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.cleanseo-header h1 .dashicons {
    margin-right: 0.5rem;
    font-size: 2rem;
    width: 2rem;
    height: 2rem;
}

.cleanseo-header-desc {
    color: var(--text-light);
    font-size: 1.1rem;
    margin-top: 0;
}

/* Grid i karty */
.cleanseo-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.cleanseo-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.cleanseo-card-header {
    background: #f9f9f9;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cleanseo-card-header h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--text-color);
}

.cleanseo-card-body {
    padding: 1.5rem;
}

.cleanseo-card-actions {
    display: flex;
    align-items: center;
}

/* Kontrolki audytu */
.cleanseo-audit-controls {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.cleanseo-run-audit, .cleanseo-schedule-audit {
    padding: 1rem;
    background: var(--light-bg);
    border-radius: 5px;
}

.cleanseo-run-audit h3, .cleanseo-schedule-audit h3 {
    margin-top: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
}

.cleanseo-form-field {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.cleanseo-form-field label {
    margin-right: 1rem;
    min-width: 120px;
}

.cleanseo-select {
    width: auto;
    min-width: 200px;
    height: 35px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    padding: 0 10px;
    font-size: 14px;
}

.button-hero {
    height: 46px !important;
    line-height: 44px !important;
    font-size: 14px !important;
    padding: 0 24px !important;
}

.audit-status {
    margin-top: 1rem;
    color: var(--text-light);
    font-style: italic;
}

.next-scheduled {
    margin-top: 1rem;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 4px;
    text-align: center;
}

/* Wykres i statystyki */
.cleanseo-chart-container {
    height: 250px;
    position: relative;
}

/* Tabela audytów */
.cleanseo-table-container {
    overflow-x: auto;
}

.cleanseo-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.cleanseo-table th,
.cleanseo-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.cleanseo-table th {
    background: #f1f1f1;
    font-weight: 600;
}

.cleanseo-table tr:hover {
    background-color: #f9f9f9;
}

.audit-date {
    white-space: nowrap;
}

.audit-score {
    text-align: center;
}

.score-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    min-width: 50px;
    text-align: center;
}

.score-good {
    background-color: var(--success-color);
}

.score-average {
    background-color: var(--warning-color);
}

.score-poor {
    background-color: var(--error-color);
}

.audit-actions {
    white-space: nowrap;
}

.cleanseo-action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.cleanseo-action-buttons .button {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 30px;
}

.cleanseo-action-buttons .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    margin-right: 2px;
}

/* Dropdown */
.cleanseo-dropdown {
    position: relative;
    display: inline-block;
}

.cleanseo-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    min-width: 120px;
    z-index: 1;
    background-color: white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    padding: 0.5rem 0;
}

.cleanseo-dropdown:hover .cleanseo-dropdown-content {
    display: block;
}

.cleanseo-dropdown-content a {
    display: block;
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: var(--text-color);
}

.cleanseo-dropdown-content a:hover {
    background-color: #f1f1f1;
}

/* Modal */
.cleanseo-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.cleanseo-modal-content {
    position: relative;
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 1000px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.cleanseo-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cleanseo-modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.cleanseo-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-light);
}

.cleanseo-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.cleanseo-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Podsumowanie audytu */
.cleanseo-audit-overview {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.audit-score-display {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.score-circle {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 8px solid var(--primary-color);
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    position: relative;
    margin-bottom: 1rem;
}

.score-value {
    font-size: 3rem;
    font-weight: bold;
    line-height: 1;
}

.score-percent {
    font-size: 1.5rem;
    font-weight: bold;
}

.audit-date {
    font-size: 0.9rem;
    color: var(--text-light);
}

.audit-summary {
    display: flex;
    gap: 2rem;
    align-items: center;
    flex: 1;
}

.audit-summary-item {
    text-align: center;
    flex: 1;
    padding: 1rem;
    background: var(--light-bg);
    border-radius: 8px;
}

.summary-label {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-bottom: 0.5rem;
}

.summary-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
}

/* Zakładki */
.cleanseo-tabs {
    margin-top: 1rem;
}

.cleanseo-tabs-nav {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: thin;
}

.cleanseo-tabs-nav::-webkit-scrollbar {
    height: 5px;
}

.cleanseo-tabs-nav::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.cleanseo-tabs-nav::-webkit-scrollbar-thumb {
    background: #c1c1c1;
}

.tab-button {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-light);
    white-space: nowrap;
}

.tab-button:hover {
    color: var(--primary-color);
}

.tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-loader {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

/* Responsywność */
@media (max-width: 1024px) {
    .cleanseo-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .cleanseo-audit-overview {
        flex-direction: column;
        align-items: center;
    }
    
    .audit-summary {
        flex-direction: column;
        width: 100%;
    }
}

/* Elementy wyników audytu */
.audit-item-card {
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    overflow: hidden;
}

.audit-item-header {
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
}

.audit-item-title {
    font-weight: 600;
    display: flex;
    align-items: center;
}

.audit-item-status {
    margin-right: 0.5rem;
}

.audit-item-status.success {
    color: var(--success-color);
}

.audit-item-status.error {
    color: var(--error-color);
}

.audit-item-body {
    padding: 1rem;
    display: none;
}

.audit-item-expanded .audit-item-body {
    display: block;
}

.audit-detail-row {
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.audit-detail-row:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.audit-detail-label {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.audit-recommendation {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: var(--light-bg);
    border-left: 3px solid var(--error-color);
    color: var(--text-color);
    font-style: italic;
}

/* Brak danych */
.cleanseo-no-data {
    text-align: center;
    padding: 2rem;
    background: var(--light-bg);
    border-radius: 6px;
}

.cleanseo-no-data p {
    margin: 0;
    color: var(--text-light);
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Zmienne globalne
    let currentAuditId = 0;
    
    // Uruchomienie audytu
    $('#run-audit').click(function() {
        const scope = $('#audit-scope').val();
        const button = $(this);
        const statusElem = $('.audit-status');
        
        button.prop('disabled', true).text('<?php _e('Wykonywanie audytu...', 'cleanseo-optimizer'); ?>');
        statusElem.text('<?php _e('Audyt w trakcie. To może potrwać kilka minut w zależności od rozmiaru witryny.', 'cleanseo-optimizer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_run_audit',
                nonce: '<?php echo wp_create_nonce('cleanseo_run_audit'); ?>',
                type: 'manual',
                scope: scope
            },
            success: function(response) {
                if (response.success) {
                    statusElem.text('<?php _e('Audyt zakończony pomyślnie!', 'cleanseo-optimizer'); ?>');
                    
                    // Opóźnij odświeżenie strony
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    statusElem.text('<?php _e('Błąd: ', 'cleanseo-optimizer'); ?>' + response.data);
                    button.prop('disabled', false).text('<?php _e('Uruchom audyt teraz', 'cleanseo-optimizer'); ?>');
                }
            },
            error: function() {
                statusElem.text('<?php _e('Wystąpił błąd podczas wykonywania audytu. Spróbuj ponownie.', 'cleanseo-optimizer'); ?>');
                button.prop('disabled', false).text('<?php _e('Uruchom audyt teraz', 'cleanseo-optimizer'); ?>');
            }
        });
    });

    // Zapisywanie harmonogramu
    $('#save-schedule').click(function() {
        const frequency = $('#audit-frequency').val();
        const button = $(this);
        
        button.prop('disabled', true).text('<?php _e('Zapisywanie...', 'cleanseo-optimizer'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_save_schedule',
                frequency: frequency,
                nonce: '<?php echo wp_create_nonce('cleanseo_save_schedule'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Aktualizacja informacji o następnym zaplanowanym audycie
                    if (response.data.next_run) {
                        $('.next-scheduled').html('<?php _e('Następny audyt:', 'cleanseo-optimizer'); ?> <strong>' + response.data.next_run + '</strong>');
                    }
                    
                    // Pokaż komunikat
                    showNotice('success', '<?php _e('Harmonogram został zapisany pomyślnie.', 'cleanseo-optimizer'); ?>');
                    
                    button.prop('disabled', false).text('<?php _e('Zapisz harmonogram', 'cleanseo-optimizer'); ?>');
                } else {
                    showNotice('error', '<?php _e('Błąd: ', 'cleanseo-optimizer'); ?>' + response.data);
                    button.prop('disabled', false).text('<?php _e('Zapisz harmonogram', 'cleanseo-optimizer'); ?>');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Wystąpił błąd podczas zapisywania harmonogramu.', 'cleanseo-optimizer'); ?>');
                button.prop('disabled', false).text('<?php _e('Zapisz harmonogram', 'cleanseo-optimizer'); ?>');
            }
        });
    });

    // Filtrowanie audytów
    $('#audit-filter').on('change', function() {
        const filter = $(this).val();
        
        if (filter === 'all') {
            $('.cleanseo-table tbody tr').show();
        } else {
            $('.cleanseo-table tbody tr').hide();
            $('.cleanseo-table tbody tr[data-type="' + filter + '"]').show();
        }
    });

    // Wyświetlanie wyników audytu
    $('.view-audit').click(function() {
        const auditId = $(this).data('id');
        currentAuditId = auditId;
        
        // Wyczyść poprzednie dane
        resetModalContent();
        
        // Aktualizuj przyciski eksportu w modalu
        $('.export-pdf-modal, .export-csv-modal').data('id', auditId);
        
        // Pokaż modal
        $('#audit-results-modal').fadeIn(300);
        
        // Pobierz dane audytu
        loadAuditData(auditId);
    });

    // Zamykanie modalu
    $('.cleanseo-modal-close, .close-modal').click(function() {
        $('#audit-results-modal').fadeOut(300);
    });

    // Zmiana zakładek
    $('.tab-button').click(function() {
        const tabId = $(this).data('tab');
        
        // Aktywuj zakładkę
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Pokaż zawartość zakładki
        $('.tab-content').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');
    });

    // Eksport do PDF
    $('.export-pdf, .export-pdf-modal').click(function(e) {
        e.preventDefault();
        const auditId = $(this).data('id');
        window.location.href = ajaxurl + '?action=cleanseo_get_audit_report&audit_id=' + auditId + '&format=pdf&nonce=<?php echo wp_create_nonce('cleanseo_get_audit_report'); ?>';
    });

    // Eksport do CSV
    $('.export-csv, .export-csv-modal').click(function(e) {
        e.preventDefault();
        const auditId = $(this).data('id');
        window.location.href = ajaxurl + '?action=cleanseo_get_audit_report&audit_id=' + auditId + '&format=csv&nonce=<?php echo wp_create_nonce('cleanseo_get_audit_report'); ?>';
    });

    // Usuwanie audytu
    $('.delete-audit').click(function() {
        const auditId = $(this).data('id');
        const row = $(this).closest('tr');
        
        if (confirm('<?php _e('Czy na pewno chcesz usunąć ten audyt? Tej operacji nie można cofnąć.', 'cleanseo-optimizer'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_delete_audit',
                    audit_id: auditId,
                    nonce: '<?php echo wp_create_nonce('cleanseo_delete_audit'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Sprawdź czy tabela jest teraz pusta
                            if ($('.cleanseo-table tbody tr').length === 0) {
                                $('.cleanseo-table').replaceWith('<div class="cleanseo-no-data"><p><?php _e('Nie wykonano jeszcze żadnego audytu.', 'cleanseo-optimizer'); ?></p></div>');
                            }
                        });
                        
                        showNotice('success', '<?php _e('Audyt został usunięty pomyślnie.', 'cleanseo-optimizer'); ?>');
                    } else {
                        showNotice('error', '<?php _e('Błąd: ', 'cleanseo-optimizer'); ?>' + response.data);
                    }
                },
                error: function() {
                    showNotice('error', '<?php _e('Wystąpił błąd podczas usuwania audytu.', 'cleanseo-optimizer'); ?>');
                }
            });
        }
    });

    // Obsługa kliknięcia poza modalem
    $(window).click(function(e) {
        if ($(e.target).is('.cleanseo-modal')) {
            $('.cleanseo-modal').fadeOut(300);
        }
    });
    
    // Inicjalizacja wykresu historii audytów
    if ($('#audit-history-chart').length > 0) {
        initAuditHistoryChart();
    }
    
    // Funkcje pomocnicze
    
    // Resetowanie zawartości modalu
    function resetModalContent() {
        $('#score-circle .score-value').text('0');
        $('#audit-date').text('');
        $('#pages-analyzed').text('0');
        $('#issues-found').text('0');
        $('#recommendations').text('0');
        
        // Resetuj zawartość zakładek
        $('.tab-content').each(function() {
            $(this).html('<div class="tab-loader"><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></div>');
        });
    }
    
    // Ładowanie danych audytu
    function loadAuditData(auditId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_get_audit_report',
                audit_id: auditId,
                nonce: '<?php echo wp_create_nonce('cleanseo_get_audit_report'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Aktualizuj wynik
                    $('#score-circle .score-value').text(data.score);
                    $('#score-circle').attr('class', 'score-circle score-' + getScoreClass(data.score));
                    
                    // Aktualizuj datę
                    const date = new Date(data.timestamp);
                    $('#audit-date').text(formatDate(date));
                    
                    // Policz statystyki
                    let totalPages = 0;
                    let totalIssues = 0;
                    let totalRecommendations = 0;
                    
                    // Zlicz strony
                    if (data.results.meta_tags) totalPages += data.results.meta_tags.length;
                    
                    // Zlicz problemy i rekomendacje
                    for (const category in data.results) {
                        if (category === 'site_analysis') {
                            data.results[category].forEach(item => {
                                if (!item.status) {
                                    totalIssues++;
                                    if (item.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                            });
                        } else {
                            data.results[category].forEach(item => {
                                // Dodaj problemy z metadanych
                                if (item.meta_title && !item.meta_title.status) {
                                    totalIssues++;
                                    if (item.meta_title.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                                
                                if (item.meta_description && !item.meta_description.status) {
                                    totalIssues++;
                                    if (item.meta_description.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                                
                                // Dodaj problemy z nagłówków
                                if (item.headers && !item.headers.status) {
                                    totalIssues++;
                                    if (item.headers.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                                
                                // Dodaj problemy z obrazków
                                if (item.alt_tags && !item.alt_tags.status) {
                                    totalIssues++;
                                    if (item.alt_tags.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                                
                                // Dodaj problemy z linków
                                if (item.links && !item.links.status) {
                                    totalIssues++;
                                    if (item.links.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                                
                                // Dodaj problemy ze schema
                                if (item.schema && !item.schema.status) {
                                    totalIssues++;
                                    if (item.schema.recommendation) {
                                        totalRecommendations++;
                                    }
                                }
                            });
                        }
                    }
                    
                    // Aktualizuj statystyki
                    $('#pages-analyzed').text(totalPages);
                    $('#issues-found').text(totalIssues);
                    $('#recommendations').text(totalRecommendations);
                    
                    // Wypełnij zakładki danymi
                    fillTabContent('site-analysis', data.results.site_analysis);
                    fillTabContent('meta-tags', data.results.meta_tags);
                    fillTabContent('content', data.results.content_structure);
                    fillTabContent('images', data.results.images);
                    fillTabContent('links', data.results.links);
                    fillTabContent('schema', data.results.schema);
                } else {
                    // Pokaż komunikat o błędzie
                    $('.tab-content').html('<div class="cleanseo-no-data"><p><?php _e('Wystąpił błąd podczas pobierania danych audytu.', 'cleanseo-optimizer'); ?></p></div>');
                }
            },
            error: function() {
                // Pokaż komunikat o błędzie
                $('.tab-content').html('<div class="cleanseo-no-data"><p><?php _e('Wystąpił błąd podczas pobierania danych audytu.', 'cleanseo-optimizer'); ?></p></div>');
            }
        });
    }
    
    // Wypełnij zawartość zakładki
    function fillTabContent(tabId, data) {
        const tabContent = $('#' + tabId + '-tab');
        let html = '';
        
        if (!data || data.length === 0) {
            html = '<div class="cleanseo-no-data"><p><?php _e('Brak danych do wyświetlenia.', 'cleanseo-optimizer'); ?></p></div>';
            tabContent.html(html);
            return;
        }
        
        // Generuj HTML w zależności od typu zakładki
        if (tabId === 'site-analysis') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const statusClass = item.status ? 'success' : 'error';
                const statusIcon = item.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                <span class="audit-item-status ${statusClass}">${statusIcon}</span>
                                ${item.title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">`;
                
                if (!item.status && item.recommendation) {
                    html += `<div class="audit-recommendation">${item.recommendation}</div>`;
                }
                
                html += `</div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else if (tabId === 'meta-tags') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const titleStatus = item.meta_title.status ? 'success' : 'error';
                const titleIcon = item.meta_title.status ? '✓' : '✗';
                const descStatus = item.meta_description.status ? 'success' : 'error';
                const descIcon = item.meta_description.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                ${item.post_title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${titleStatus}">${titleIcon}</span>
                                    <?php _e('Meta Title', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    ${item.meta_title.value ? item.meta_title.value : '<?php _e('Brak', 'cleanseo-optimizer'); ?>'}
                                </div>
                                ${!item.meta_title.status && item.meta_title.recommendation ? 
                                    `<div class="audit-recommendation">${item.meta_title.recommendation}</div>` : ''}
                            </div>
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${descStatus}">${descIcon}</span>
                                    <?php _e('Meta Description', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    ${item.meta_description.value ? item.meta_description.value : '<?php _e('Brak', 'cleanseo-optimizer'); ?>'}
                                </div>
                                ${!item.meta_description.status && item.meta_description.recommendation ? 
                                    `<div class="audit-recommendation">${item.meta_description.recommendation}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else if (tabId === 'content') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const headerStatus = item.headers.status ? 'success' : 'error';
                const headerIcon = item.headers.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                ${item.post_title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${headerStatus}">${headerIcon}</span>
                                    <?php _e('Nagłówki', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    H1: ${item.headers.h1_count} | H2: ${item.headers.h2_count} | H3: ${item.headers.h3_count}
                                </div>
                                ${!item.headers.status && item.headers.recommendation ? 
                                    `<div class="audit-recommendation">${item.headers.recommendation}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else if (tabId === 'images') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const altStatus = item.alt_tags.status ? 'success' : 'error';
                const altIcon = item.alt_tags.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                ${item.post_title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${altStatus}">${altIcon}</span>
                                    <?php _e('Atrybuty ALT', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    <?php _e('Obrazki:', 'cleanseo-optimizer'); ?> ${item.alt_tags.total_images} | 
                                    <?php _e('Brak ALT:', 'cleanseo-optimizer'); ?> ${item.alt_tags.missing_alt}
                                </div>
                                ${!item.alt_tags.status && item.alt_tags.recommendation ? 
                                    `<div class="audit-recommendation">${item.alt_tags.recommendation}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else if (tabId === 'links') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const linkStatus = item.links.status ? 'success' : 'error';
                const linkIcon = item.links.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                ${item.post_title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${linkStatus}">${linkIcon}</span>
                                    <?php _e('Linki', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    <?php _e('Wewnętrzne:', 'cleanseo-optimizer'); ?> ${item.links.internal_links} | 
                                    <?php _e('Zewnętrzne:', 'cleanseo-optimizer'); ?> ${item.links.external_links} | 
                                    <?php _e('Uszkodzone:', 'cleanseo-optimizer'); ?> ${item.links.broken_links}
                                </div>
                                ${!item.links.status && item.links.recommendation ? 
                                    `<div class="audit-recommendation">${item.links.recommendation}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else if (tabId === 'schema') {
            html += '<div class="audit-items-list">';
            
            data.forEach((item, index) => {
                const schemaStatus = item.schema.status ? 'success' : 'error';
                const schemaIcon = item.schema.status ? '✓' : '✗';
                
                html += `
                    <div class="audit-item-card">
                        <div class="audit-item-header">
                            <div class="audit-item-title">
                                ${item.post_title}
                            </div>
                            <div class="audit-item-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                        </div>
                        <div class="audit-item-body">
                            <div class="audit-detail-row">
                                <div class="audit-detail-label">
                                    <span class="audit-item-status ${schemaStatus}">${schemaIcon}</span>
                                    <?php _e('Schema.org', 'cleanseo-optimizer'); ?>
                                </div>
                                <div class="audit-detail-value">
                                    ${item.schema.status ? '<?php _e('Znaleziono dane strukturalne', 'cleanseo-optimizer'); ?>' : '<?php _e('Brak danych strukturalnych', 'cleanseo-optimizer'); ?>'}
                                </div>
                                ${!item.schema.status && item.schema.recommendation ? 
                                    `<div class="audit-recommendation">${item.schema.recommendation}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        tabContent.html(html);
        
        // Dodaj obsługę rozwijanych elementów
        $('.audit-item-header').click(function() {
            $(this).parent().toggleClass('audit-item-expanded');
            $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });
    }
    
    // Inicjalizacja wykresu historii audytów
    function initAuditHistoryChart() {
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        if (chartData.length === 0) {
            return;
        }
        
        const labels = chartData.map(item => formatDateShort(new Date(item.timestamp)));
        const scores = chartData.map(item => item.score);
        
        // Gradient dla linii wykresu
        const ctx = document.getElementById('audit-history-chart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 250);
        gradient.addColorStop(0, 'rgba(34, 113, 177, 0.7)');
        gradient.addColorStop(1, 'rgba(34, 113, 177, 0.1)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '<?php _e('Wynik audytu (%)', 'cleanseo-optimizer'); ?>',
                    data: scores,
                    backgroundColor: gradient,
                    borderColor: '#2271b1',
                    borderWidth: 2,
                    pointBackgroundColor: '#2271b1',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        usePointStyle: true,
                        padding: 10,
                        cornerRadius: 4,
                        caretSize: 6
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        },
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Formatuj datę
    function formatDate(date) {
        return date.toLocaleDateString('<?php echo get_locale(); ?>', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Formatuj datę w formacie skróconym
    function formatDateShort(date) {
        return date.toLocaleDateString('<?php echo get_locale(); ?>', {
            day: 'numeric',
            month: 'short'
        });
    }
    
    // Określ klasę koloru na podstawie wyniku
    function getScoreClass(score) {
        if (score >= 70) {
            return 'good';
        } else if (score >= 50) {
            return 'average';
        } else {
            return 'poor';
        }
    }
    
    // Wyświetl powiadomienie
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Dodaj przycisk zamykania
        const button = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e('Zamknij', 'cleanseo-optimizer'); ?></span></button>');
        button.on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        notice.append(button);
        
        // Dodaj powiadomienie na początku strony
        $('.wrap').prepend(notice);
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>
<?php
    /**
     * Pomocnicza funkcja do określania klasy dla wyniku
     */
    function get_score_class($score) {
        if ($score >= 70) {
            return 'good';
        } elseif ($score >= 50) {
            return 'average';
        } else {
            return 'poor';
        }
    }
?>