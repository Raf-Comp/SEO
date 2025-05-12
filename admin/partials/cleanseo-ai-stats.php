<?php
if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień.');
}

$ai_models = new CleanSEO_AI_Models();
$ai_cache = new CleanSEO_AI_Cache();
$ai_logger = new CleanSEO_AI_Logger();

// Pobierz statystyki
$cache_stats = $ai_cache->get_stats();
$usage_stats = $ai_logger->get_usage_stats('day');
$popular_types = $ai_cache->get_popular_types();
$popular_models = $ai_cache->get_popular_models();
$common_errors = $ai_logger->get_common_errors();

// Obsługa eksportu logów
if (isset($_POST['export_logs']) && check_admin_referer('cleanseo_export_logs')) {
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    $csv = $ai_logger->export_to_csv([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ai_logs_' . date('Y-m-d') . '.csv"');
    echo $csv;
    exit;
}
?>
<div class="wrap">
    <h1>Statystyki AI</h1>

    <div class="cleanseo-stats-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin:20px 0;">
        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Statystyki Cache</h2>
            <ul>
                <li>Całkowita liczba wpisów: <?php echo $cache_stats['total']; ?></li>
                <li>Wygasłe wpisy: <?php echo $cache_stats['expired']; ?></li>
                <li>Rozmiar cache: <?php echo size_format($cache_stats['size']); ?></li>
            </ul>
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('cleanseo_clear_cache'); ?>
                <button type="submit" name="clear_cache" class="button">Wyczyść cache</button>
            </form>
        </div>

        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Statystyki Użycia (24h)</h2>
            <ul>
                <li>Całkowita liczba zapytań: <?php echo $usage_stats['total_requests']; ?></li>
                <li>Udane zapytania: <?php echo $usage_stats['successful_requests']; ?></li>
                <li>Błędne zapytania: <?php echo $usage_stats['failed_requests']; ?></li>
                <li>Wykorzystane tokeny: <?php echo $usage_stats['total_tokens']; ?></li>
                <li>Średni czas przetwarzania: <?php echo round($usage_stats['avg_processing_time'], 2); ?>s</li>
            </ul>
        </div>

        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Popularne Typy Zapytań</h2>
            <ul>
                <?php foreach ($popular_types as $type): ?>
                <li><?php echo esc_html($type['type']); ?>: <?php echo $type['count']; ?> zapytań</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Popularne Modele</h2>
            <ul>
                <?php foreach ($popular_models as $model): ?>
                <li><?php echo esc_html($model['model']); ?>: <?php echo $model['count']; ?> zapytań</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Najczęstsze Błędy</h2>
            <ul>
                <?php foreach ($common_errors as $error): ?>
                <li><?php echo esc_html($error['error_message']); ?>: <?php echo $error['count']; ?> wystąpień</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <h2>Eksport Logów</h2>
            <form method="post">
                <?php wp_nonce_field('cleanseo_export_logs'); ?>
                <p>
                    <label>Data początkowa:<br>
                        <input type="date" name="start_date" required>
                    </label>
                </p>
                <p>
                    <label>Data końcowa:<br>
                        <input type="date" name="end_date" required>
                    </label>
                </p>
                <button type="submit" name="export_logs" class="button button-primary">Eksportuj logi</button>
            </form>
        </div>
    </div>

    <div class="cleanseo-stats-card" style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);margin-top:20px;">
        <h2>Ostatnie Logi</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Użytkownik</th>
                    <th>Model</th>
                    <th>Typ</th>
                    <th>Status</th>
                    <th>Tokeny</th>
                    <th>Czas (s)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent_logs = $ai_logger->get_user_logs(get_current_user_id(), ['limit' => 10]);
                foreach ($recent_logs as $log):
                    $user = get_userdata($log['user_id']);
                ?>
                <tr>
                    <td><?php echo esc_html($log['created_at']); ?></td>
                    <td><?php echo esc_html($user ? $user->display_name : 'N/A'); ?></td>
                    <td><?php echo esc_html($log['model']); ?></td>
                    <td><?php echo esc_html($log['type']); ?></td>
                    <td><?php echo esc_html($log['status']); ?></td>
                    <td><?php echo esc_html($log['tokens_used']); ?></td>
                    <td><?php echo round($log['processing_time'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div> 