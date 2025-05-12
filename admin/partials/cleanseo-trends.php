<?php
if (!defined('WPINC')) { die; }

// Pobierz słowa kluczowe konkurencji (przykład: z bazy lub opcji)
$competitor_keywords = get_option('cleanseo_competitor_keywords', array());
$my_keywords = get_option('cleanseo_my_keywords', array());

// Sprawdź czy API jest gotowe do użycia
$trends_api_ready = false;
if (class_exists('CleanSEO_Trends')) {
    $trends_instance = new CleanSEO_Trends();
    $trends_api_ready = $trends_instance->is_api_ready();
}
?>
<div class="wrap cleanseo-trends-panel">
    <h1><?php _e('Trendy Google', 'cleanseo-optimizer'); ?></h1>
    
    <?php if (!$trends_api_ready): ?>
        <div class="notice notice-warning">
            <p><?php _e('Klucz API Google nie jest skonfigurowany. Przejdź do Ustawień, aby dodać klucz API.', 'cleanseo-optimizer'); ?></p>
        </div>
    <?php endif; ?>
    
    <form id="cleanseo-trends-form">
        <label><strong><?php _e('Wpisz słowo kluczowe:', 'cleanseo-optimizer'); ?></strong></label>
        <input type="text" name="keyword" id="cleanseo-trends-keyword" style="width:300px;" placeholder="<?php _e('np. AI, SEO, WordPress...', 'cleanseo-optimizer'); ?>">
        <button type="submit" class="button button-primary" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <?php _e('Szukaj trendów', 'cleanseo-optimizer'); ?>
        </button>
        <br><br>
        <label><strong><?php _e('Lub wybierz słowo konkurencji:', 'cleanseo-optimizer'); ?></strong></label>
        <select id="cleanseo-trends-competitor-keyword" style="width:300px;" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <option value=""><?php _e('-- wybierz --', 'cleanseo-optimizer'); ?></option>
            <?php foreach ($competitor_keywords as $kw) : ?>
                <option value="<?php echo esc_attr($kw); ?>"><?php echo esc_html($kw); ?></option>
            <?php endforeach; ?>
        </select>
        <label style="margin-left:20px;"><strong><?php _e('Twoje słowa kluczowe:', 'cleanseo-optimizer'); ?></strong></label>
        <select id="cleanseo-trends-my-keyword" style="width:300px;" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <option value=""><?php _e('-- wybierz --', 'cleanseo-optimizer'); ?></option>
            <?php foreach ($my_keywords as $kw) : ?>
                <option value="<?php echo esc_attr($kw); ?>"><?php echo esc_html($kw); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <div id="cleanseo-trends-controls" style="margin-top:20px;">
        <label><strong><?php _e('Zakres czasu:', 'cleanseo-optimizer'); ?></strong></label>
        <select id="cleanseo-trends-range" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <option value="7d"><?php _e('7 dni', 'cleanseo-optimizer'); ?></option>
            <option value="30d" selected><?php _e('30 dni', 'cleanseo-optimizer'); ?></option>
            <option value="90d"><?php _e('90 dni', 'cleanseo-optimizer'); ?></option>
            <option value="365d"><?php _e('365 dni', 'cleanseo-optimizer'); ?></option>
        </select>
        <button id="cleanseo-trends-export-csv" class="button" style="margin-left:10px;" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <?php _e('Eksportuj CSV', 'cleanseo-optimizer'); ?>
        </button>
        <button id="cleanseo-trends-export-pdf" class="button" style="margin-left:5px;" <?php echo !$trends_api_ready ? 'disabled' : ''; ?>>
            <?php _e('Eksportuj PDF', 'cleanseo-optimizer'); ?>
        </button>
    </div>
    
    <!-- Dodajemy elementy dla stanu ładowania i błędów -->
    <div id="cleanseo-trends-loading" style="display:none; margin-top:20px;">
        <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>
        <span><?php _e('Ładowanie danych...', 'cleanseo-optimizer'); ?></span>
    </div>
    
    <div id="cleanseo-trends-error" class="notice notice-error" style="display:none; margin-top:20px; padding:10px;"></div>
    
    <div style="margin-top:30px;">
        <canvas id="cleanseo-trends-chart" height="100"></canvas>
    </div>
    
    <!-- Dodajemy sekcję dla powiązanych zapytań -->
    <div id="cleanseo-related-queries" style="margin-top:30px; display:none;">
        <h3><?php _e('Powiązane zapytania', 'cleanseo-optimizer'); ?></h3>
        <div id="cleanseo-related-queries-content"></div>
    </div>
</div>

<style>
.cleanseo-trends-panel label { margin-right: 10px; }
.cleanseo-trends-panel select, .cleanseo-trends-panel input[type=text] { margin-bottom: 10px; }
.cleanseo-trends-panel .spinner { visibility: visible; }
</style>

<script>
jQuery(document).ready(function($) {
    // Inicjalizacja wykresu
    let trendsChart = null;
    
    // Obsługa formularza
    $('#cleanseo-trends-form').on('submit', function(e) {
        e.preventDefault();
        const keyword = $('#cleanseo-trends-keyword').val().trim();
        if (keyword) {
            fetchTrendsData(keyword);
        }
    });
    
    // Obsługa wyboru słowa kluczowego konkurencji
    $('#cleanseo-trends-competitor-keyword').on('change', function() {
        const keyword = $(this).val();
        if (keyword) {
            $('#cleanseo-trends-keyword').val(keyword);
            $('#cleanseo-trends-my-keyword').val('');
            fetchTrendsData(keyword);
        }
    });
    
    // Obsługa wyboru własnego słowa kluczowego
    $('#cleanseo-trends-my-keyword').on('change', function() {
        const keyword = $(this).val();
        if (keyword) {
            $('#cleanseo-trends-keyword').val(keyword);
            $('#cleanseo-trends-competitor-keyword').val('');
            fetchTrendsData(keyword);
        }
    });
    
    // Obsługa zmiany zakresu czasu
    $('#cleanseo-trends-range').on('change', function() {
        const keyword = $('#cleanseo-trends-keyword').val().trim();
        if (keyword) {
            fetchTrendsData(keyword);
        }
    });
    
    // Funkcja do pobierania danych
    function fetchTrendsData(keyword) {
        $('#cleanseo-trends-loading').show();
        $('#cleanseo-trends-error').hide();
        $('#cleanseo-related-queries').hide();
        
        // Przygotowanie danych do wysłania
        const data = {
            action: 'cleanseo_get_trends_data',
            keyword: keyword,
            time_range: $('#cleanseo-trends-range').val(),
            nonce: '<?php echo wp_create_nonce('cleanseo_trends_nonce'); ?>'
        };
        
        // Wysłanie zapytania AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                $('#cleanseo-trends-loading').hide();
                
                if (response.success) {
                    // Wyświetl wykres
                    displayChart(response.data.trends);
                    
                    // Wyświetl powiązane zapytania
                    if (response.data.related && response.data.related.length > 0) {
                        displayRelatedQueries(response.data.related);
                    }
                } else {
                    // Wyświetl błąd
                    $('#cleanseo-trends-error')
                        .html('<p>' + response.data + '</p>')
                        .show();
                }
            },
            error: function() {
                $('#cleanseo-trends-loading').hide();
                $('#cleanseo-trends-error')
                    .html('<p><?php _e('Wystąpił błąd podczas komunikacji z serwerem.', 'cleanseo-optimizer'); ?></p>')
                    .show();
            }
        });
    }
    
    // Funkcja do wyświetlania wykresu
    function displayChart(data) {
        const ctx = document.getElementById('cleanseo-trends-chart').getContext('2d');
        
        // Jeśli wykres już istnieje, zniszcz go
        if (trendsChart) {
            trendsChart.destroy();
        }
        
        // Utwórz nowy wykres
        trendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: data.keyword,
                    data: data.values,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '<?php _e('Popularność', 'cleanseo-optimizer'); ?>'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '<?php _e('Data', 'cleanseo-optimizer'); ?>'
                        }
                    }
                }
            }
        });
    }
    
    // Funkcja do wyświetlania powiązanych zapytań
    function displayRelatedQueries(queries) {
        const container = $('#cleanseo-related-queries-content');
        container.empty();
        
        // Tworzenie tabeli
        const table = $('<table class="wp-list-table widefat fixed striped">');
        const thead = $('<thead>').appendTo(table);
        const tbody = $('<tbody>').appendTo(table);
        
        // Nagłówki tabeli
        $('<tr>')
            .append($('<th>').text('<?php _e('Zapytanie', 'cleanseo-optimizer'); ?>'))
            .append($('<th>').text('<?php _e('Popularność', 'cleanseo-optimizer'); ?>'))
            .appendTo(thead);
        
        // Wiersze z danymi
        $.each(queries, function(i, query) {
            $('<tr>')
                .append($('<td>').text(query.title))
                .append($('<td>').text(query.traffic))
                .appendTo(tbody);
        });
        
        // Dodanie tabeli do kontenera
        container.append(table);
        $('#cleanseo-related-queries').show();
    }
    
    // Obsługa eksportu do CSV
    $('#cleanseo-trends-export-csv').on('click', function() {
        if (!trendsChart) return;
        
        const data = trendsChart.data;
        let csvContent = 'data:text/csv;charset=utf-8,';
        
        // Nagłówki CSV
        csvContent += 'Data,Popularność\r\n';
        
        // Dane
        for (let i = 0; i < data.labels.length; i++) {
            csvContent += data.labels[i] + ',' + data.datasets[0].data[i] + '\r\n';
        }
        
        // Utworzenie linku do pobrania
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'trendy_' + data.datasets[0].label + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Obsługa eksportu do PDF
    $('#cleanseo-trends-export-pdf').on('click', function() {
        if (!trendsChart) return;
        
        // Tu należy dodać kod do generowania PDF
        // Wymaga załadowania biblioteki jsPDF lub podobnej
        alert('<?php _e('Funkcja eksportu do PDF będzie dostępna wkrótce.', 'cleanseo-optimizer'); ?>');
    });
});
</script>