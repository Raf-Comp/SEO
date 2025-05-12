<?php
/**
 * Widok panelu Analytics
 *
 * @package CleanSEO
 * @subpackage Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

?>

<div class="cleanseo-analytics-dashboard">
    <div class="cleanseo-analytics-header">
        <div class="cleanseo-analytics-title">
            <i class="dashicons dashicons-chart-bar"></i>
            <h1>CleanSEO Analytics</h1>
        </div>
        <p class="cleanseo-analytics-subtitle">Szczegółowe statystyki witryny i trendy SEO</p>
    </div>

    <?php
    // Sprawdź czy klasa jest zainicjalizowana globalnie
    global $cleanseo_analytics;

    if (!isset($cleanseo_analytics) || !is_object($cleanseo_analytics)) {
        echo '<div class="cleanseo-notice cleanseo-notice-error">
                <i class="dashicons dashicons-warning"></i>
                <p>Błąd: Klasa Analytics nie została zainicjalizowana.</p>
              </div>';
        return;
    }

    $top_pages = $cleanseo_analytics->get_top_pages(10);
    $bounce_trend = $cleanseo_analytics->get_bounce_rate_trend();
    
    // Obliczamy trendy dla wykresu
    $dates = [];
    $bounce_rates = [];
    
    if ($bounce_trend) {
        foreach ($bounce_trend as $row) {
            $dates[] = "'" . esc_js($row->date) . "'";
            $bounce_rates[] = (float)$row->avg_bounce_rate;
        }
    }
    ?>

    <div class="cleanseo-analytics-grid">
        <!-- Karta statystyk popularności stron -->
        <div class="cleanseo-analytics-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-chart-area"></i> Najpopularniejsze strony</h2>
                <div class="cleanseo-card-actions">
                    <a href="#" class="cleanseo-btn-icon" title="Odśwież dane"><i class="dashicons dashicons-update"></i></a>
                    <a href="#" class="cleanseo-btn-icon" title="Pobierz CSV"><i class="dashicons dashicons-download"></i></a>
                </div>
            </div>
            
            <div class="cleanseo-card-content">
                <div class="cleanseo-data-table-wrapper">
                    <table class="cleanseo-data-table">
                        <thead>
                            <tr>
                                <th>URL strony</th>
                                <th class="text-right">Odsłony</th>
                                <th class="text-right">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($top_pages): ?>
                            <?php foreach ($top_pages as $page): ?>
                                <tr>
                                    <td class="cleanseo-url-cell">
                                        <div class="cleanseo-url-container">
                                            <i class="dashicons dashicons-admin-page"></i>
                                            <span class="cleanseo-url-text"><?php echo esc_html($page->url ?? '—'); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="cleanseo-pageviews">
                                            <span class="cleanseo-value"><?php echo number_format_i18n(intval($page->total_pageviews)); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="cleanseo-actions">
                                            <a href="<?php echo esc_url($page->url); ?>" target="_blank" class="cleanseo-action-btn" title="Otwórz stronę">
                                                <i class="dashicons dashicons-external"></i>
                                            </a>
                                            <a href="#" class="cleanseo-action-btn" title="Pokaż szczegóły">
                                                <i class="dashicons dashicons-info"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="cleanseo-no-data">
                                    <div class="cleanseo-empty-state">
                                        <i class="dashicons dashicons-chart-pie"></i>
                                        <p>Brak danych o popularnych stronach.</p>
                                        <a href="#" class="cleanseo-btn cleanseo-btn-sm">Odśwież dane</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Karta trendu Bounce Rate -->
        <div class="cleanseo-analytics-card">
            <div class="cleanseo-card-header">
                <h2><i class="dashicons dashicons-chart-line"></i> Trend współczynnika odrzuceń (Bounce Rate)</h2>
                <div class="cleanseo-card-actions">
                    <select class="cleanseo-select-sm">
                        <option value="7">Ostatnie 7 dni</option>
                        <option value="30" selected>Ostatnie 30 dni</option>
                        <option value="90">Ostatnie 90 dni</option>
                    </select>
                </div>
            </div>
            
            <div class="cleanseo-card-content">
                <?php if ($bounce_trend && count($bounce_trend) > 1): ?>
                <!-- Obszar wykresu -->
                <div class="cleanseo-chart-container">
                    <canvas id="bounceRateChart"></canvas>
                </div>
                <?php endif; ?>
                
                <div class="cleanseo-data-table-wrapper">
                    <table class="cleanseo-data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th class="text-right">Współczynnik odrzuceń</th>
                                <th class="text-right">Zmiana</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($bounce_trend): ?>
                            <?php 
                            $prev_rate = null;
                            foreach ($bounce_trend as $row): 
                                $current_rate = (float)$row->avg_bounce_rate;
                                $change = null;
                                $change_class = '';
                                
                                if ($prev_rate !== null) {
                                    $change = $current_rate - $prev_rate;
                                    $change_class = ($change < 0) ? 'cleanseo-trend-down' : (($change > 0) ? 'cleanseo-trend-up' : '');
                                }
                                
                                $prev_rate = $current_rate;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($row->date); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$row->avg_bounce_rate, 2); ?>%</td>
                                    <td class="text-right">
                                        <?php if ($change !== null): ?>
                                        <div class="cleanseo-trend <?php echo $change_class; ?>">
                                            <?php if ($change < 0): ?>
                                                <i class="dashicons dashicons-arrow-down-alt"></i>
                                            <?php elseif ($change > 0): ?>
                                                <i class="dashicons dashicons-arrow-up-alt"></i>
                                            <?php else: ?>
                                                <i class="dashicons dashicons-minus"></i>
                                            <?php endif; ?>
                                            <span><?php echo abs($change) > 0 ? number_format(abs($change), 2) . '%' : '—'; ?></span>
                                        </div>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="cleanseo-no-data">
                                    <div class="cleanseo-empty-state">
                                        <i class="dashicons dashicons-chart-line"></i>
                                        <p>Brak danych o trendzie współczynnika odrzuceń.</p>
                                        <a href="#" class="cleanseo-btn cleanseo-btn-sm">Odśwież dane</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Skrypty dla wykresów -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($bounce_trend && count($bounce_trend) > 1): ?>
    // Inicjalizacja wykresu Bounce Rate
    const ctx = document.getElementById('bounceRateChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php echo implode(', ', $dates); ?>],
            datasets: [{
                label: 'Współczynnik odrzuceń (%)',
                data: [<?php echo implode(', ', $bounce_rates); ?>],
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2271b1',
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return `Bounce Rate: ${context.raw.toFixed(2)}%`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
:root {
    --cleanseo-primary: #2271b1;
    --cleanseo-primary-light: #72aee6;
    --cleanseo-primary-dark: #135e96;
    --cleanseo-secondary: #f0f6fc;
    --cleanseo-background: #f0f0f1;
    --cleanseo-card-bg: #ffffff;
    --cleanseo-text: #1d2327;
    --cleanseo-text-light: #646970;
    --cleanseo-border: #dcdcde;
    --cleanseo-success: #00a32a;
    --cleanseo-warning: #dba617;
    --cleanseo-error: #d63638;
    
    --cleanseo-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    --cleanseo-radius: 8px;
}

/* Reset */
.cleanseo-analytics-dashboard * {
    box-sizing: border-box;
}

/* Główny kontener */
.cleanseo-analytics-dashboard {
    max-width: 1400px;
    margin: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

/* Nagłówek strony */
.cleanseo-analytics-header {
    margin-bottom: 24px;
}

.cleanseo-analytics-title {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}

.cleanseo-analytics-title i {
    font-size: 28px;
    color: var(--cleanseo-primary);
    margin-right: 12px;
}

.cleanseo-analytics-title h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: var(--cleanseo-text);
}

.cleanseo-analytics-subtitle {
    margin: 0 0 0 40px;
    color: var(--cleanseo-text-light);
    font-size: 14px;
}

/* Grid do układu kart */
.cleanseo-analytics-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
}

@media (min-width: 992px) {
    .cleanseo-analytics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Karty */
.cleanseo-analytics-card {
    background: var(--cleanseo-card-bg);
    border-radius: var(--cleanseo-radius);
    box-shadow: var(--cleanseo-shadow);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.cleanseo-card-header {
    padding: 16px 20px;
    background: var(--cleanseo-primary);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cleanseo-card-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.cleanseo-card-header h2 i {
    margin-right: 8px;
    font-size: 18px;
}

.cleanseo-card-actions {
    display: flex;
    gap: 8px;
}

.cleanseo-btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    background: rgba(255, 255, 255, 0.2);
    width: 28px;
    height: 28px;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.cleanseo-btn-icon:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.cleanseo-select-sm {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    cursor: pointer;
}

.cleanseo-select-sm:focus {
    outline: none;
    box-shadow: 0 0 0 1px white;
}

.cleanseo-card-content {
    padding: 20px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

/* Wykres */
.cleanseo-chart-container {
    height: 250px;
    margin-bottom: 20px;
}

/* Tabela danych */
.cleanseo-data-table-wrapper {
    overflow-x: auto;
    margin-top: auto;
}

.cleanseo-data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
}

.cleanseo-data-table th {
    text-align: left;
    padding: 12px 16px;
    background: var(--cleanseo-secondary);
    font-weight: 600;
    color: var(--cleanseo-text);
    border-bottom: 1px solid var(--cleanseo-border);
}

.cleanseo-data-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--cleanseo-border);
}

.cleanseo-data-table tbody tr:last-child td {
    border-bottom: none;
}

.cleanseo-data-table tbody tr:hover {
    background-color: var(--cleanseo-secondary);
}

/* Wyrównanie tekstu */
.text-right {
    text-align: right;
}

/* Komórka z URL */
.cleanseo-url-container {
    display: flex;
    align-items: center;
}

.cleanseo-url-container i {
    color: var(--cleanseo-text-light);
    margin-right: 8px;
}

.cleanseo-url-text {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Liczba odsłon */
.cleanseo-pageviews {
    font-weight: 600;
}

.cleanseo-value {
    color: var(--cleanseo-text);
}

/* Przyciski akcji */
.cleanseo-actions {
    display: flex;
    justify-content: flex-end;
    gap: 5px;
}

.cleanseo-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    color: var(--cleanseo-text-light);
    text-decoration: none;
    transition: all 0.2s ease;
}

.cleanseo-action-btn:hover {
    background: var(--cleanseo-secondary);
    color: var(--cleanseo-primary);
}

/* Trendy wzrostu/spadku */
.cleanseo-trend {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
}

.cleanseo-trend-up {
    color: var(--cleanseo-error);
}

.cleanseo-trend-down {
    color: var(--cleanseo-success);
}

.cleanseo-trend i {
    margin-right: 4px;
}

/* Powiadomienia */
.cleanseo-notice {
    border-left: 4px solid var(--cleanseo-primary);
    background: white;
    box-shadow: var(--cleanseo-shadow);
    margin-bottom: 20px;
    padding: 12px 16px;
    border-radius: 4px;
    display: flex;
    align-items: center;
}

.cleanseo-notice i {
    margin-right: 10px;
    font-size: 20px;
}

.cleanseo-notice p {
    margin: 0;
}

.cleanseo-notice-error {
    border-left-color: var(--cleanseo-error);
}

.cleanseo-notice-error i {
    color: var(--cleanseo-error);
}

/* Stan pustych danych */
.cleanseo-no-data {
    padding: 30px !important;
}

.cleanseo-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    text-align: center;
}

.cleanseo-empty-state i {
    font-size: 42px;
    color: var(--cleanseo-border);
    margin-bottom: 10px;
}

.cleanseo-empty-state p {
    margin: 0 0 15px 0;
    color: var(--cleanseo-text-light);
}

/* Przyciski */
.cleanseo-btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    background: var(--cleanseo-primary);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cleanseo-btn:hover {
    background: var(--cleanseo-primary-dark);
    color: white;
}

.cleanseo-btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Responsywność dla małych ekranów */
@media (max-width: 782px) {
    .cleanseo-analytics-subtitle {
        margin-left: 0;
    }
    
    .cleanseo-data-table th, 
    .cleanseo-data-table td {
        padding: 10px 12px;
    }
    
    .cleanseo-url-text {
        max-width: 150px;
    }
}
</style>