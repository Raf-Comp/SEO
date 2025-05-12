<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap cleanseo-competitors">
    <h1>Śledzenie konkurencji</h1>

    <!-- Dodawanie nowego konkurenta -->
    <div class="card">
        <h2>Dodaj nowego konkurenta</h2>
        <form id="add-competitor-form" class="cleanseo-form">
            <?php wp_nonce_field('cleanseo_add_competitor', 'cleanseo_add_competitor_nonce'); ?>
            <div class="form-group">
                <label for="competitor-domain">Domena konkurenta:</label>
                <input type="text" id="competitor-domain" name="domain" class="regular-text" required>
            </div>
            <div class="form-group">
                <label for="competitor-keywords">Słowa kluczowe (po jednym w linii):</label>
                <textarea id="competitor-keywords" name="keywords" rows="5" class="large-text" required></textarea>
            </div>
            <button type="submit" class="button button-primary">Dodaj konkurenta</button>
        </form>
    </div>

    <!-- Lista konkurentów -->
    <div class="card">
        <h2>Twoi konkurenci</h2>
        <div class="competitors-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Domena</th>
                        <th>Średnia pozycja</th>
                        <th>Liczba słów kluczowych</th>
                        <th>Wskaźnik wygranych</th>
                        <th>Ostatnie sprawdzenie</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody id="competitors-list-body">
                    <!-- Dane będą ładowane przez AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal szczegółów konkurenta -->
    <div id="competitor-details-modal" class="cleanseo-modal" style="display: none;">
        <div class="cleanseo-modal-content">
            <span class="cleanseo-modal-close">&times;</span>
            <h2>Szczegóły konkurenta</h2>
            <div class="competitor-details">
                <div class="competitor-stats">
                    <div class="stat-box">
                        <h3>Statystyki ogólne</h3>
                        <div id="competitor-general-stats"></div>
                    </div>
                    <div class="stat-box">
                        <h3>Historia rankingów</h3>
                        <canvas id="rankings-chart"></canvas>
                    </div>
                </div>
                <div class="keywords-list">
                    <h3>Słowa kluczowe</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Słowo kluczowe</th>
                                <th>Nasza pozycja</th>
                                <th>Ich pozycja</th>
                                <th>Wolumen</th>
                                <th>Trudność</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody id="keywords-list-body">
                            <!-- Dane będą ładowane przez AJAX -->
                        </tbody>
                    </table>
                </div>
                <div class="export-options">
                    <button class="button" id="export-csv">Eksportuj do CSV</button>
                    <button class="button" id="export-pdf">Eksportuj do PDF</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cleanseo-competitors .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.cleanseo-competitors .form-group {
    margin-bottom: 15px;
}

.cleanseo-competitors .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.cleanseo-competitors .stat-box {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.cleanseo-competitors .competitor-stats {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-bottom: 20px;
}

.cleanseo-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.cleanseo-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 1200px;
    border-radius: 4px;
    position: relative;
}

.cleanseo-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.cleanseo-modal-close:hover {
    color: black;
}

.export-options {
    margin-top: 20px;
    text-align: right;
}

.export-options .button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Ładowanie listy konkurentów
    function loadCompetitors() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_get_competitors',
                nonce: '<?php echo wp_create_nonce('cleanseo_get_competitors'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    updateCompetitorsList(response.data);
                }
            }
        });
    }

    // Aktualizacja listy konkurentów
    function updateCompetitorsList(competitors) {
        var html = '';
        competitors.forEach(function(competitor) {
            html += '<tr>';
            html += '<td>' + competitor.domain + '</td>';
            html += '<td>' + competitor.stats.avg_our_rank + '</td>';
            html += '<td>' + competitor.stats.total_keywords + '</td>';
            html += '<td>' + competitor.stats.win_rate + '%</td>';
            html += '<td>' + competitor.last_check + '</td>';
            html += '<td>';
            html += '<button class="button view-details" data-id="' + competitor.id + '">Szczegóły</button>';
            html += '<button class="button delete-competitor" data-id="' + competitor.id + '">Usuń</button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#competitors-list-body').html(html);
    }

    // Dodawanie nowego konkurenta
    $('#add-competitor-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_add_competitor',
                nonce: '<?php echo wp_create_nonce('cleanseo_add_competitor'); ?>',
                domain: $('#competitor-domain').val(),
                keywords: $('#competitor-keywords').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('Konkurent dodany pomyślnie');
                    loadCompetitors();
                    $('#add-competitor-form')[0].reset();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Usuwanie konkurenta
    $(document).on('click', '.delete-competitor', function() {
        if (!confirm('Czy na pewno chcesz usunąć tego konkurenta?')) return;
        
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_delete_competitor',
                nonce: '<?php echo wp_create_nonce('cleanseo_delete_competitor'); ?>',
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadCompetitors();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Wyświetlanie szczegółów konkurenta
    $(document).on('click', '.view-details', function() {
        var id = $(this).data('id');
        $('#competitor-details-modal').show();
        loadCompetitorDetails(id);
    });

    // Zamykanie modalu
    $('.cleanseo-modal-close').click(function() {
        $('#competitor-details-modal').hide();
    });

    // Eksport do CSV
    $('#export-csv').click(function() {
        var competitorId = $(this).data('competitor-id');
        window.location.href = ajaxurl + '?action=cleanseo_export_competitor&format=csv&id=' + competitorId + '&nonce=<?php echo wp_create_nonce('cleanseo_export_competitor'); ?>';
    });

    // Eksport do PDF
    $('#export-pdf').click(function() {
        var competitorId = $(this).data('competitor-id');
        window.location.href = ajaxurl + '?action=cleanseo_export_competitor&format=pdf&id=' + competitorId + '&nonce=<?php echo wp_create_nonce('cleanseo_export_competitor'); ?>';
    });

    // Inicjalne ładowanie danych
    loadCompetitors();
});
</script> 