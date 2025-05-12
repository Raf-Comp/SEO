<?php
if (!current_user_can('manage_options')) {
    wp_die('Brak uprawnień.');
}

if (!class_exists('CleanSEO_AI_Settings')) {
    require_once plugin_dir_path(__DIR__) . '../includes/class-cleanseo-ai-settings.php';
}

$settings = new CleanSEO_AI_Settings();
$current = $settings->get_settings();

// Obsługa zapisu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cleanseo_openai_settings')) {
    $settings->update_settings($_POST);
    echo '<div class="updated"><p>Ustawienia zapisane.</p></div>';
    $current = $settings->get_settings();
}

$from_value = isset($_GET['from']) ? esc_attr($_GET['from']) : '';
$to_value = isset($_GET['to']) ? esc_attr($_GET['to']) : '';
?>

<div class="wrap">
    <h1>Ustawienia AI – Klucze API, prompty i modele</h1>
    <form method="post">
        <?php wp_nonce_field('cleanseo_openai_settings'); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Model domyślny</th>
                <td>
                    <select name="default_model">
                        <option value="gpt-3.5-turbo" <?php selected($current['default_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (OpenAI)</option>
                        <option value="gpt-4" <?php selected($current['default_model'], 'gpt-4'); ?>>GPT-4 (OpenAI)</option>
                        <option value="gemini-pro" <?php selected($current['default_model'], 'gemini-pro'); ?>>Gemini Pro (Google)</option>
                        <option value="claude-3" <?php selected($current['default_model'], 'claude-3'); ?>>Claude 3 (Anthropic)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Klucz API OpenAI</th>
                <td>
                    <input type="text" name="api_keys[openai]" value="<?php echo esc_attr($current['api_keys']['openai']); ?>" size="60" />
                    <br><small><a href="https://platform.openai.com/account/api-keys" target="_blank">Pobierz klucz API z OpenAI →</a></small>
                </td>
            </tr>
            <tr>
                <th scope="row">Klucz API Gemini</th>
                <td>
                    <input type="text" name="api_keys[gemini]" value="<?php echo esc_attr($current['api_keys']['gemini']); ?>" size="60" />
                    <br><small><a href="https://makersuite.google.com/app/apikey" target="_blank">Pobierz klucz API z Gemini →</a></small>
                </td>
            </tr>
            <tr>
                <th scope="row">Klucz API Claude</th>
                <td>
                    <input type="text" name="api_keys[claude]" value="<?php echo esc_attr($current['api_keys']['claude']); ?>" size="60" />
                    <br><small><a href="https://console.anthropic.com/settings/keys" target="_blank">Pobierz klucz API z Claude →</a></small>
                </td>
            </tr>
            <tr>
                <th scope="row">Szablon meta title</th>
                <td><input type="text" name="prompt_templates[meta_title]" value="<?php echo esc_attr($current['prompt_templates']['meta_title']); ?>" size="80"></td>
            </tr>
            <tr>
                <th scope="row">Szablon meta description</th>
                <td><input type="text" name="prompt_templates[meta_description]" value="<?php echo esc_attr($current['prompt_templates']['meta_description']); ?>" size="80"></td>
            </tr>
            <tr>
                <th scope="row">Szablon schema</th>
                <td><input type="text" name="prompt_templates[schema]" value="<?php echo esc_attr($current['prompt_templates']['schema']); ?>" size="80"></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Zapisz zmiany">
        </p>
    </form>

    <h2>📊 Statystyki wykorzystania AI</h2>
    <form method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="cleanseo-openai" />
        <label for="from">Od: <input type="date" name="from" value="<?php echo $from_value; ?>"></label>
        <label for="to">Do: <input type="date" name="to" value="<?php echo $to_value; ?>"></label>
        <input type="submit" class="button" value="Filtruj">
        <a href="<?php echo esc_url(add_query_arg(array('export' => 'csv'))); ?>" class="button">Eksportuj CSV</a>
    </form>
    <table class="widefat fixed striped">
        <thead><tr><th>Dostawca</th><th>Liczba zapytań</th><th>Zużyte tokeny</th><th>Szacowany koszt</th></tr></thead>
        <tbody>
        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'seo_openai_logs';
        $where = '1=1';
        if (!empty($_GET['from'])) {
            $from = sanitize_text_field($_GET['from']);
            $where .= " AND created_at >= '{$from} 00:00:00'";
        }
        if (!empty($_GET['to'])) {
            $to = sanitize_text_field($_GET['to']);
            $where .= " AND created_at <= '{$to} 23:59:59'";
        }

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header("Content-Type: text/csv");
            header("Content-Disposition: attachment; filename=ai-usage.csv");
            $out = fopen("php://output", "w");
            fputcsv($out, ['Model', 'Liczba zapytań', 'Tokeny', 'Koszt']);
            $rows = $wpdb->get_results("SELECT model, COUNT(*) as total, SUM(tokens_used) as tokens, SUM(cost) as cost FROM $table WHERE $where GROUP BY model");
            foreach ($rows as $r) {
                fputcsv($out, [$r->model, $r->total, $r->tokens, $r->cost]);
            }
            fclose($out);
            exit;
        }

        $rows = $wpdb->get_results("SELECT model, COUNT(*) as total, SUM(tokens_used) as tokens, SUM(cost) as cost FROM $table WHERE $where GROUP BY model");
        $providers = array('gpt' => 'OpenAI', 'gemini' => 'Gemini', 'claude' => 'Claude');
        foreach ($rows as $row) {
            $provider = 'gpt';
            if (strpos($row->model, 'gemini') !== false) {
                $provider = 'gemini';
            } elseif (strpos($row->model, 'claude') !== false) {
                $provider = 'claude';
            }

            echo '<tr>';
            echo '<td>' . esc_html($providers[$provider]) . '</td>';
            echo '<td>' . intval($row->total) . '</td>';
            echo '<td>' . intval($row->tokens) . '</td>';
            echo '<td>' . number_format_i18n(floatval($row->cost), 4) . ' $</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
</div>
