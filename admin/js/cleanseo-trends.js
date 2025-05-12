/**
 * CleanSEO Optimizer - Skrypt dla modułu Trendy Google
 * 
 * @since      1.0.0
 * @package    CleanSEO_Optimizer
 */

(function($) {
    'use strict';

    // Inicjalizacja zmiennych globalnych
    let trendsChart = null;
    let currentKeyword = '';
    let chartData = {
        dates: [],
        values: []
    };

    /**
     * Inicjalizacja po załadowaniu dokumentu
     */
    $(document).ready(function() {
        initTrendsModule();
    });

    /**
     * Inicjalizacja modułu Trendy
     */
    function initTrendsModule() {
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
        
        // Obsługa przycisku eksportu CSV
        $('#cleanseo-trends-export-csv').on('click', function() {
            exportToCsv();
        });
        
        // Obsługa przycisku eksportu PDF
        $('#cleanseo-trends-export-pdf').on('click', function() {
            exportToPdf();
        });
    }

    /**
     * Pobieranie danych trendów
     * 
     * @param {string} keyword Słowo kluczowe
     */
    function fetchTrendsData(keyword) {
        showLoading(true);
        hideError();
        
        currentKeyword = keyword;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_get_trends_data',
                keyword: keyword,
                time_range: $('#cleanseo-trends-range').val(),
                nonce: cleanseo_trends_vars.nonce
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    // Zapisz dane wykresu
                    chartData = {
                        dates: response.data.trends.dates,
                        values: response.data.trends.values
                    };
                    
                    // Wyświetl wykres
                    displayChart(response.data.trends);
                    
                    // Wyświetl powiązane zapytania
                    if (response.data.related && response.data.related.length > 0) {
                        displayRelatedQueries(response.data.related);
                    } else {
                        $('#cleanseo-related-queries').hide();
                    }
                    
                    // Włącz przyciski eksportu
                    $('#cleanseo-trends-export-csv, #cleanseo-trends-export-pdf').prop('disabled', false);
                } else {
                    showError(response.data);
                    $('#cleanseo-related-queries').hide();
                    $('#cleanseo-trends-export-csv, #cleanseo-trends-export-pdf').prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                showLoading(false);
                showError(cleanseo_trends_vars.i18n.error + ' ' + error);
                $('#cleanseo-related-queries').hide();
                $('#cleanseo-trends-export-csv, #cleanseo-trends-export-pdf').prop('disabled', true);
            }
        });
    }

    /**
     * Wyświetlanie wykresu
     * 
     * @param {object} data Dane wykresu
     */
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
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Popularność'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    }
                }
            }
        });
    }

    /**
     * Wyświetlanie powiązanych zapytań
     * 
     * @param {Array} queries Powiązane zapytania
     */
    function displayRelatedQueries(queries) {
        const container = $('#cleanseo-related-queries-content');
        container.empty();
        
        // Tworzenie tabeli
        const table = $('<table class="wp-list-table widefat fixed striped">');
        const thead = $('<thead>').appendTo(table);
        const tbody = $('<tbody>').appendTo(table);
        
        // Nagłówki tabeli
        $('<tr>')
            .append($('<th>').text('Zapytanie'))
            .append($('<th width="150">').text('Popularność'))
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

    /**
     * Eksport do CSV
     */
    function exportToCsv() {
        if (!trendsChart) return;
        
        // Zbierz dane do CSV
        const data = {
            action: 'cleanseo_export_trends_csv',
            keyword: currentKeyword,
            time_range: $('#cleanseo-trends-range').val(),
            dates: chartData.dates,
            values: chartData.values,
            nonce: cleanseo_trends_vars.nonce
        };
        
        // Wyślij żądanie AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Przygotuj link do pobrania
                    const csvContent = response.data.content;
                    const encodedUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
                    const link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', response.data.filename);
                    document.body.appendChild(link);
                    
                    // Wywołaj kliknięcie na link
                    link.click();
                    
                    // Usuń link
                    document.body.removeChild(link);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(cleanseo_trends_vars.i18n.error);
            }
        });
    }

    /**
     * Eksport do PDF
     */
    function exportToPdf() {
        if (!trendsChart) return;
        
        // Stwórz nowy dokument PDF
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('landscape');
        
        // Dodaj tytuł
        pdf.setFontSize(18);
        pdf.text(`Trendy Google: ${currentKeyword}`, 14, 22);
        
        // Dodaj informacje o zakresie czasu
        pdf.setFontSize(12);
        pdf.text(`Zakres czasu: ${$('#cleanseo-trends-range').val()}`, 14, 30);
        pdf.text(`Wygenerowano: ${new Date().toLocaleDateString()}`, 14, 36);
        
        // Pobierz obraz wykresu
        const canvas = document.getElementById('cleanseo-trends-chart');
        const imgData = canvas.toDataURL('image/png', 1.0);
        
        // Dodaj wykres do PDF
        const pdfWidth = pdf.internal.pageSize.getWidth() - 28;
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        pdf.addImage(imgData, 'PNG', 14, 45, pdfWidth, pdfHeight);
        
        // Dodaj powiązane zapytania (jeśli istnieją)
        if ($('#cleanseo-related-queries').is(':visible')) {
            const startY = 45 + pdfHeight + 15;
            
            pdf.setFontSize(16);
            pdf.text('Powiązane zapytania', 14, startY);
            
            pdf.setFontSize(12);
            let y = startY + 10;
            
            // Pobierz dane z tabeli
            $('#cleanseo-related-queries-content table tbody tr').each(function(index) {
                if (index < 15) { // Ogranicz do 15 wierszy
                    const query = $(this).find('td:first').text();
                    const traffic = $(this).find('td:last').text();
                    pdf.text(`${query} - ${traffic}`, 14, y);
                    y += 8;
                }
            });
        }
        
        // Zapisz PDF
        pdf.save(`trendy_${currentKeyword.replace(/\s+/g, '_')}.pdf`);
    }

    /**
     * Pokazuje/ukrywa stan ładowania
     * 
     * @param {boolean} show Czy pokazać stan ładowania
     */
    function showLoading(show) {
        if (show) {
            $('#cleanseo-trends-loading').show();
        } else {
            $('#cleanseo-trends-loading').hide();
        }
    }

    /**
     * Pokazuje komunikat o błędzie
     * 
     * @param {string} message Treść błędu
     */
    function showError(message) {
        $('#cleanseo-trends-error')
            .html('<p>' + message + '</p>')
            .show();
    }

    /**
     * Ukrywa komunikat o błędzie
     */
    function hideError() {
        $('#cleanseo-trends-error').hide();
    }

})(jQuery);