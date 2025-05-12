(function($) {
    'use strict';

    const CleanSEO = {
        init: function() {
            this.initSettings();
            this.initRedirects();
            this.initCompetitors();
            this.initAudit();
            this.initContentAnalysis();
            this.initLocalSEO();
            this.initAISettings();
            this.initSitemap();
            this.initTrends();
            
            // Inicjalizacja zakładek
            this.initTabs();
        },

        /**
         * Inicjalizacja zakładek
         */
        initTabs: function() {
            $('.tab-btn').on('click', function() {
                const tab = $(this).data('tab');
                
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });
        },

        /**
         * Wykonaj żądanie AJAX
         */
        ajax: function(action, data = {}) {
            return new Promise((resolve, reject) => {
                if (!cleanseo_vars.nonces[action]) {
                    reject(new Error('Brak nonce dla akcji: ' + action));
                    return;
                }

                data.action = action;
                data.nonce = cleanseo_vars.nonces[action];

                $.ajax({
                    url: cleanseo_vars.ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data?.message || cleanseo_vars.i18n.error));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error(error || cleanseo_vars.i18n.error));
                    }
                });
            });
        },

        /**
         * Pokaż komunikat
         */
        showMessage: function(message, type = 'error') {
            const $message = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .append($('<p>').text(message));

            $('.wrap h1').after($message);

            // Dodaj przycisk zamykania komunikatu
            $message.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Zamknij</span></button>');
            
            // Obsługa zamykania
            $message.find('.notice-dismiss').on('click', function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Automatyczne usunięcie po 5 sekundach
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Obsługa ustawień
         */
        initSettings: function() {
            const $form = $('#cleanseo-settings-form');
            if (!$form.length) return;

            $form.on('submit', function(e) {
                e.preventDefault();
                const $submit = $(this).find('input[type="submit"]');
                const originalText = $submit.val();

                $submit.val(cleanseo_vars.i18n.saving).prop('disabled', true);

                CleanSEO.ajax('save_settings', {
                    settings: $(this).serialize()
                })
                .then(() => {
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $submit.val(originalText).prop('disabled', false);
                });
            });
        },

        /**
         * Obsługa przekierowań
         */
        initRedirects: function() {
            const $form = $('#cleanseo-redirect-form');
            const $list = $('#cleanseo-redirects-list');
            if (!$form.length && !$list.length) return;

            // Dodaj przekierowanie
            if ($form.length) {
                $form.on('submit', function(e) {
                    e.preventDefault();
                    const $submit = $(this).find('button[type="submit"]');
                    const originalText = $submit.text();

                    $submit.text(cleanseo_vars.i18n.saving).prop('disabled', true);

                    CleanSEO.ajax('add_redirect', {
                        source_url: $('#source_url').val(),
                        target_url: $('#target_url').val(),
                        status_code: $('#status_code').val()
                    })
                    .then(() => {
                        $form[0].reset();
                        loadRedirects();
                        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                    })
                    .catch(error => {
                        CleanSEO.showMessage(error.message);
                    })
                    .finally(() => {
                        $submit.text(originalText).prop('disabled', false);
                    });
                });
            }

            // Usuń przekierowanie
            if ($list.length) {
                $list.on('click', '.delete-redirect', function() {
                    if (!confirm(cleanseo_vars.i18n.confirmDelete)) return;

                    const $button = $(this);
                    const id = $button.data('id');

                    $button.prop('disabled', true);

                    CleanSEO.ajax('delete_redirect', { id: id })
                    .then(() => {
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                    })
                    .catch(error => {
                        CleanSEO.showMessage(error.message);
                    })
                    .finally(() => {
                        $button.prop('disabled', false);
                    });
                });
            }

            // Eksport przekierowań
            $('#cleanseo-export-redirects').on('click', function(e) {
                e.preventDefault();
                window.location.href = cleanseo_vars.ajaxurl + '?action=cleanseo_export_redirects_csv&nonce=' + cleanseo_vars.nonces.export_redirects_csv;
            });

            // Import przekierowań
            $('#cleanseo-import-redirects-form').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'cleanseo_import_redirects_csv');
                formData.append('nonce', cleanseo_vars.nonces.import_redirects_csv);
                
                const $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).text('Importowanie...');
                
                $.ajax({
                    url: cleanseo_vars.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            CleanSEO.showMessage('Zaimportowano: ' + response.data.imported, 'success');
                            loadRedirects();
                        } else {
                            CleanSEO.showMessage(response.data || cleanseo_vars.i18n.error, 'error');
                        }
                    },
                    error: function() {
                        CleanSEO.showMessage(cleanseo_vars.i18n.error, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Importuj CSV');
                        $('#cleanseo-import-redirects-form')[0].reset();
                    }
                });
            });

            // Batch delete przekierowań
            $('#cleanseo-batch-delete').on('click', function(e) {
                e.preventDefault();
                const ids = $('.redirect-checkbox:checked').map(function() { 
                    return $(this).val(); 
                }).get();
                
                if (ids.length === 0) {
                    CleanSEO.showMessage('Nie zaznaczono żadnych przekierowań.', 'error');
                    return;
                }
                
                if (!confirm('Czy na pewno chcesz usunąć zaznaczone przekierowania?')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('Usuwanie...');
                
                CleanSEO.ajax('cleanseo_batch_delete_redirects', {
                    ids: ids
                })
                .then(response => {
                    CleanSEO.showMessage('Usunięto: ' + response.deleted, 'success');
                    loadRedirects();
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                })
                .finally(() => {
                    $('#cleanseo-batch-delete').prop('disabled', false).text('Usuń zaznaczone');
                });
            });

            // Załaduj listę przekierowań
            function loadRedirects() {
                if (!$list.length) return;
                
                $list.html('<div class="loading">' + cleanseo_vars.i18n.loading + '</div>');

                CleanSEO.ajax('get_redirects')
                .then(data => {
                    if (!data.redirects || data.redirects.length === 0) {
                        $list.html('<div class="no-redirects">Brak przekierowań</div>');
                        return;
                    }
                    
                    let html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr>';
                    html += '<th><input type="checkbox" id="redirects-check-all"></th>';
                    html += '<th>Źródłowy URL</th>';
                    html += '<th>Docelowy URL</th>';
                    html += '<th>Status</th>';
                    html += '<th>Odwiedzenia</th>';
                    html += '<th>Ostatni dostęp</th>';
                    html += '<th>Akcje</th>';
                    html += '</tr></thead><tbody>';
                    
                    data.redirects.forEach(function(redirect) {
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="redirect-checkbox" value="' + redirect.id + '"></td>';
                        html += '<td>' + escapeHtml(redirect.source_url) + '</td>';
                        html += '<td>' + escapeHtml(redirect.target_url) + '</td>';
                        html += '<td class="status-' + redirect.status_code + '">' + redirect.status_code + '</td>';
                        html += '<td class="hits">' + redirect.hits + '</td>';
                        html += '<td class="last-accessed">' + formatDate(redirect.last_accessed) + '</td>';
                        html += '<td class="actions">';
                        html += '<a href="#" class="edit edit-redirect" data-id="' + redirect.id + '">Edytuj</a>';
                        html += '<a href="#" class="delete delete-redirect" data-id="' + redirect.id + '">Usuń</a>';
                        html += '</td></tr>';
                    });
                    
                    html += '</tbody></table>';
                    $list.html(html);
                    
                    // Obsługa zaznacz wszystko
                    $('#redirects-check-all').on('change', function() {
                        $('.redirect-checkbox').prop('checked', this.checked);
                    });
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                    $list.html('<div class="error">' + error.message + '</div>');
                });
            }

            // Funkcja formatowania daty
            function formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                return date.toLocaleString('pl-PL', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Funkcja escapowania HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Załaduj początkową listę
            if ($list.length) {
                loadRedirects();
            }
        },

        /**
         * Obsługa konkurencji
         */
        initCompetitors: function() {
            const $form = $('#cleanseo-competitor-form');
            const $list = $('#cleanseo-competitors-list');
            if (!$form.length && !$list.length) return;

            // Dodaj konkurenta
            if ($form.length) {
                $form.on('submit', function(e) {
                    e.preventDefault();
                    const $submit = $(this).find('button[type="submit"]');
                    const originalText = $submit.text();

                    $submit.text(cleanseo_vars.i18n.saving).prop('disabled', true);

                    CleanSEO.ajax('add_competitor', {
                        domain: $('#competitor-domain').val(),
                        keywords: $('#competitor-keywords').val()
                    })
                    .then(() => {
                        $form[0].reset();
                        loadCompetitors();
                        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                    })
                    .catch(error => {
                        CleanSEO.showMessage(error.message, 'error');
                    })
                    .finally(() => {
                        $submit.text(originalText).prop('disabled', false);
                    });
                });
            }

            // Usuń konkurenta
            if ($list.length) {
                $list.on('click', '.delete-competitor', function() {
                    if (!confirm(cleanseo_vars.i18n.confirmDelete)) return;

                    const $button = $(this);
                    const id = $button.data('id');

                    $button.prop('disabled', true);

                    CleanSEO.ajax('delete_competitor', { id: id })
                    .then(() => {
                        $button.closest('.competitor-row').fadeOut(300, function() {
                            $(this).remove();
                        });
                        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                    })
                    .catch(error => {
                        CleanSEO.showMessage(error.message, 'error');
                    })
                    .finally(() => {
                        $button.prop('disabled', false);
                    });
                });
            }

            // Załaduj listę konkurentów
            function loadCompetitors() {
                if (!$list.length) return;
                
                $list.html('<div class="loading">' + cleanseo_vars.i18n.loading + '</div>');

                CleanSEO.ajax('get_competitors')
                .then(competitors => {
                    if (!competitors || competitors.length === 0) {
                        $list.html('<div class="no-competitors">Brak konkurentów</div>');
                        return;
                    }
                    
                    let html = '';
                    competitors.forEach(function(competitor) {
                        html += '<div class="competitor-row">';
                        html += '<div class="competitor-info">';
                        html += '<h3>' + competitor.domain + '</h3>';
                        html += '<div>Słowa kluczowe: ' + competitor.keywords + '</div>';
                        html += '<div>Ostatnie sprawdzenie: ' + (competitor.last_check ? competitor.last_check : 'Nigdy') + '</div>';
                        html += '</div>';
                        html += '<div class="competitor-actions">';
                        html += '<button type="button" class="button view-competitor" data-id="' + competitor.id + '">Pokaż</button>';
                        html += '<button type="button" class="button delete-competitor" data-id="' + competitor.id + '">Usuń</button>';
                        html += '</div>';
                        html += '</div>';
                    });
                    
                    $list.html(html);
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                    $list.html('<div class="error">' + error.message + '</div>');
                });
            }

            // Załaduj początkową listę
            if ($list.length) {
                loadCompetitors();
            }
        },

        /**
         * Obsługa audytu
         */
        initAudit: function() {
            const $audit = $('#cleanseo-audit');
            if (!$audit.length) return;

            $audit.on('click', '#run-audit', function() {
                const $button = $(this);
                const originalText = $button.text();

                $button.text(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('run_audit')
                .then(() => {
                    CleanSEO.showMessage('Audyt został wykonany pomyślnie.', 'success');
                    // Odświeżamy stronę, aby pokazać najnowszy audyt
                    location.reload();
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                })
                .finally(() => {
                    $button.text(originalText).prop('disabled', false);
                });
            });

            // Zapisywanie harmonogramu
            $audit.on('click', '#save-schedule', function() {
                const frequency = $('#audit-frequency').val();
                
                CleanSEO.ajax('cleanseo_save_schedule', {
                    frequency: frequency
                })
                .then(() => {
                    CleanSEO.showMessage('Harmonogram został zapisany.', 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                });
            });

            // Wyświetlanie wyników audytu
            $audit.on('click', '.view-audit', function() {
                const auditId = $(this).data('id');
                
                CleanSEO.ajax('get_audit_report', {
                    audit_id: auditId
                })
                .then(response => {
                    const results = response.results;
                    const score = response.score;
                    
                    // Aktualizuj wynik
                    $('.score-value').text(score);
                    
                    // Wyczyść poprzednie wyniki
                    $('.audit-results').empty();
                    
                    // Dodaj nowe wyniki
                    Object.keys(results).forEach(function(category) {
                        let categoryHtml = '<div class="audit-category">';
                        categoryHtml += '<h3>' + category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</h3>';
                        
                        if (Array.isArray(results[category])) {
                            results[category].forEach(function(item) {
                                categoryHtml += '<div class="audit-item-result">';
                                categoryHtml += '<span class="audit-status">' + (item.status ? '✔️' : '❌') + '</span>';
                                categoryHtml += '<span class="audit-title">' + item.post_title + '</span>';
                                
                                if (item.recommendation) {
                                    categoryHtml += '<div class="audit-recommendation">' + item.recommendation + '</div>';
                                }
                                
                                categoryHtml += '</div>';
                            });
                        }
                        
                        categoryHtml += '</div>';
                        $('.audit-results').append(categoryHtml);
                    });
                    
                    // Pokaż modal
                    $('#audit-results-modal').show();
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                });
            });

            // Zamykanie modalu
            $('.cleanseo-modal-close').on('click', function() {
                $('#audit-results-modal').hide();
            });

            // Eksport do PDF/CSV
            $audit.on('click', '.export-pdf', function() {
                const auditId = $(this).data('id');
                window.location.href = cleanseo_vars.ajaxurl + '?action=cleanseo_get_audit_report&audit_id=' + auditId + '&format=pdf&nonce=' + cleanseo_vars.nonces.get_audit_report;
            });

            $audit.on('click', '.export-csv', function() {
                const auditId = $(this).data('id');
                window.location.href = cleanseo_vars.ajaxurl + '?action=cleanseo_get_audit_report&audit_id=' + auditId + '&format=csv&nonce=' + cleanseo_vars.nonces.get_audit_report;
            });
        },

        /**
         * Obsługa analizy treści
         */
        initContentAnalysis: function() {
            const $analysis = $('#cleanseo-content-analysis');
            if (!$analysis.length) return;

            $analysis.on('click', '#analyze-content', function() {
                const $button = $(this);
                const originalText = $button.text();

                $button.text(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('get_content_analysis')
                .then(html => {
                    $('#analysis-results').html(html);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message, 'error');
                })
                .finally(() => {
                    $button.text(originalText).prop('disabled', false);
                });
            });
        },

        /**
         * Obsługa SEO lokalnego
         */
        initLocalSEO: function() {
            const $local = $('#cleanseo-local-seo');
            if (!$local.length) return;

            // Obsługa formularza dodawania lokalizacji
            $('#cleanseo-add-location-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'cleanseo_add_location');
                formData.append('nonce', cleanseo_vars.nonces.add_location);

                $.ajax({
                    url: cleanseo_vars.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            CleanSEO.showMessage(response.data.message, 'success');
                            $('#cleanseo-add-location-form')[0].reset();
                            refreshLocationsList();
                        } else {
                            CleanSEO.showMessage(response.data, 'error');
                        }
                    },
                    error: function() {
                        CleanSEO.showMessage(cleanseo_vars.i18n.error, 'error');
                    }
                });
            });

            // Obsługa edycji lokalizacji
            $(document).on('click', '.edit-location', function() {
                var locationId = $(this).data('id');
                
                $.ajax({
                    url: cleanseo_vars.ajaxurl,
                    type: 'GET',
                    data: {
                        action: 'cleanseo_get_location',
                        id: locationId,
                        nonce: cleanseo_vars.nonces.get_location
                    },
                    success: function(response) {
                        if (response.success) {
                            fillEditForm(response.data);
                            $('#edit-location-modal').show();
                        } else {
                            CleanSEO.showMessage(response.data, 'error');
                        }
                    },
                    error: function() {
                        CleanSEO.showMessage(cleanseo_vars.i18n.error, 'error');
                    }
                });
            });

            // Obsługa usuwania lokalizacji
            $(document).on('click', '.delete-location', function() {
                if (!confirm(cleanseo_vars.i18n.confirmDelete)) {
                    return;
                }

                var locationId = $(this).data('id');
                
                $.ajax({
                    url: cleanseo_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cleanseo_delete_location',
                        id: locationId,
                        nonce: cleanseo_vars.nonces.delete_location
                    },
                    success: function(response) {
                        if (response.success) {
                            CleanSEO.showMessage(response.data.message, 'success');
                            refreshLocationsList();
                        } else {
                            CleanSEO.showMessage(response.data, 'error');
                        }
                    },
                    error: function() {
                        CleanSEO.showMessage(cleanseo_vars.i18n.error, 'error');
                    }
                });
            });

            // Obsługa dodawania pól usług
            $('.add-service').on('click', function() {
                var container = $(this).closest('.services-container');
                var newRow = container.find('.service-row').first().clone();
                newRow.find('input').val('');
                container.append(newRow);
            });

            // Obsługa dodawania pól metod płatności
            $('.add-payment-method').on('click', function() {
                var container = $(this).closest('.payment-methods-container');
                var newRow = container.find('.payment-method-row').first().clone();
                newRow.find('input').val('');
                container.append(newRow);
            });

            // Obsługa zamykania modalu
            $('.cleanseo-modal-close, .modal-cancel').on('click', function() {
                $('#edit-location-modal').hide();
            });

            // Wypełnij formularz edycji danymi
            function fillEditForm(location) {
                var form = $('#cleanseo-edit-location-form');
                
                form.find('#edit_location_id').val(location.id);
                form.find('#edit_location_name').val(location.name);
                form.find('#edit_location_street').val(location.street);
                form.find('#edit_location_city').val(location.city);
                form.find('#edit_location_postal_code').val(location.postal_code);
                form.find('#edit_location_country').val(location.country);
                form.find('#edit_location_phone').val(location.phone);
                form.find('#edit_location_email').val(location.email);
                form.find('#edit_location_google_place_id').val(location.google_place_id);
                form.find('#edit_location_google_place_url').val(location.google_place_url);
                form.find('#edit_location_price_range').val(location.price_range);

                // Wypełnij godziny otwarcia
                if (location.opening_hours) {
                    $.each(location.opening_hours, function(day, hours) {
                        form.find('#edit_opening_hours_' + day + '_open').val(hours.open);
                        form.find('#edit_opening_hours_' + day + '_close').val(hours.close);
                    });
                }

                // Wypełnij usługi
                var servicesContainer = form.find('.edit-services-container');
                servicesContainer.empty();
                if (location.services) {
                    $.each(location.services, function(i, service) {
                        var row = $('<div class="service-row"><input type="text" name="services[]" value="' + service + '"><button type="button" class="button remove-service">-</button></div>');
                        servicesContainer.append(row);
                    });
                }

                // Wypełnij metody płatności
                var paymentMethodsContainer = form.find('.edit-payment-methods-container');
                paymentMethodsContainer.empty();
                if (location.payment_methods) {
                    $.each(location.payment_methods, function(i, method) {
                        var row = $('<div class="payment-method-row"><input type="text" name="payment_methods[]" value="' + method + '"><button type="button" class="button remove-payment-method">-</button></div>');
                        paymentMethodsContainer.append(row);
                    });
                }
            }

            // Odśwież listę lokalizacji
            function refreshLocationsList() {
                $.ajax({
                    url: window.location.href,
                    success: function(response) {
                        var newList = $(response).find('#cleanseo-locations-list').html();
                        $('#cleanseo-locations-list').html(newList);
                    }
                });
            }

            // Obsługa usuwania pól
            $(document).on('click', '.remove-service', function() {
                $(this).closest('.service-row').remove();
            });

            $(document).on('click', '.remove-payment-method', function() {
                $(this).closest('.payment-method-row').remove();
            });

            // Obsługa dodawania nowych pól w formularzu edycji
            $('.add-new-service').on('click', function() {
                var container = $('.edit-services-container');
                var row = $('<div class="service-row"><input type="text" name="services[]"><button type="button" class="button remove-service">-</button></div>');
                container.append(row);
            });

            $('.add-new-payment-method').on('click', function() {
                var container = $('.edit-payment-methods-container');
                var row = $('<div class="payment-method-row"><input type="text" name="payment_methods[]"><button type="button" class="button remove-payment-method">-</button></div>');
                container.append(row);
            });
        },

        /**
         * Obsługa ustawień AI
         */
        initAISettings: function() {
            const $form = $('#cleanseo-ai-settings-form');
            if (!$form.length) return;
// Zapisz ustawienia AI
$form.on('submit', function(e) {
    e.preventDefault();
    const $submit = $(this).find('input[type="submit"]');
    const originalText = $submit.val();

    $submit.val(cleanseo_vars.i18n.saving).prop('disabled', true);

    CleanSEO.ajax('save_openai_settings', {
        model: $('#openai_model').val(),
        language: $('#openai_language').val()
    })
    .then(() => {
        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $submit.val(originalText).prop('disabled', false);
    });
});

// Testuj prompt
$('#test-prompt').on('click', function() {
    const $button = $(this);
    const originalText = $button.text();
    const prompt = $('#prompt').val();

    if (!prompt) {
        CleanSEO.showMessage('Prompt nie może być pusty', 'error');
        return;
    }

    $button.text(cleanseo_vars.i18n.loading).prop('disabled', true);

    CleanSEO.ajax('test_openai_prompt', { prompt: prompt })
    .then(result => {
        $('#prompt-result').html(result);
        CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $button.text(originalText).prop('disabled', false);
    });
});

// Obsługa generowania wsadowego
$('#cleanseo-openai-batch-form').on('submit', function(e) {
    e.preventDefault();
    const $button = $(this).find('button[type="submit"]');
    const originalText = $button.text();
    
    const post_ids = $('#post_ids').val();
    const batch_type = $('#batch_type').val();
    
    if (!post_ids) {
        CleanSEO.showMessage('Podaj ID postów', 'error');
        return;
    }

    $button.text('Przetwarzanie...').prop('disabled', true);
    
    CleanSEO.ajax('openai_batch', { 
        post_ids: post_ids,
        batch_type: batch_type
    })
    .then(result => {
        $('#batch-result').html('<pre>' + JSON.stringify(result, null, 2) + '</pre>');
        CleanSEO.showMessage('Operacja zakończona pomyślnie', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $button.text(originalText).prop('disabled', false);
    });
});

// Weryfikacja klucza API
$('#verify-api-key').on('click', function() {
    const $button = $(this);
    const originalText = $button.text();
    const api_key = $('#api_key').val();
    
    if (!api_key) {
        CleanSEO.showMessage('Wprowadź klucz API', 'error');
        return;
    }

    $button.text('Weryfikowanie...').prop('disabled', true);
    
    CleanSEO.ajax('cleanseo_verify_api', { api_key: api_key })
    .then(() => {
        CleanSEO.showMessage('Klucz API został zweryfikowany pomyślnie', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $button.text(originalText).prop('disabled', false);
    });
});
},

/**
* Obsługa sitemap
*/
initSitemap: function() {
const $sitemap = $('#cleanseo-sitemap');
if (!$sitemap.length) return;

// Zapisz ustawienia sitemap
$('#cleanseo-sitemap-settings-form').on('submit', function(e) {
    e.preventDefault();
    const $submit = $(this).find('button[type="submit"]');
    const originalText = $submit.text();

    $submit.text(cleanseo_vars.i18n.saving).prop('disabled', true);

    CleanSEO.ajax('save_sitemap_settings', {
        excluded_post_types: $('input[name="excluded_post_types[]"]:checked').map(function() {
            return $(this).val();
        }).get(),
        excluded_taxonomies: $('input[name="excluded_taxonomies[]"]:checked').map(function() {
            return $(this).val();
        }).get()
    })
    .then(() => {
        CleanSEO.showMessage('Ustawienia sitemap zostały zapisane', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $submit.text(originalText).prop('disabled', false);
    });
});

// Walidacja sitemap
$('#cleanseo-validate-sitemap').on('click', function() {
    const $button = $(this);
    const originalText = $button.text();

    $button.text('Walidacja...').prop('disabled', true);

    CleanSEO.ajax('validate_sitemap')
    .then(message => {
        $('#cleanseo-sitemap-validation-result').html(
            '<div class="notice notice-success"><p>' + message + '</p></div>'
        );
    })
    .catch(error => {
        $('#cleanseo-sitemap-validation-result').html(
            '<div class="notice notice-error"><p>' + error.message + '</p></div>'
        );
    })
    .finally(() => {
        $button.text(originalText).prop('disabled', false);
    });
});

// Zapisz robots.txt
$('#cleanseo-robots-form').on('submit', function(e) {
    e.preventDefault();
    const $submit = $(this).find('button[type="submit"]');
    const originalText = $submit.text();

    $submit.text(cleanseo_vars.i18n.saving).prop('disabled', true);

    CleanSEO.ajax('cleanseo_save_robots', {
        content: $('#robots_content').val()
    })
    .then(() => {
        CleanSEO.showMessage('Plik robots.txt został zapisany', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $submit.text(originalText).prop('disabled', false);
    });
});
},

/**
* Obsługa trendów
*/
initTrends: function() {
const $trends = $('#cleanseo-trends');
if (!$trends.length) return;

// Pobierz trendy
$('#cleanseo-trends-form').on('submit', function(e) {
    e.preventDefault();
    const $submit = $(this).find('button[type="submit"]');
    const originalText = $submit.text();
    const keyword = $('#cleanseo-trends-keyword').val();

    if (!keyword) {
        CleanSEO.showMessage('Słowo kluczowe nie może być puste', 'error');
        return;
    }

    $submit.text('Wyszukiwanie...').prop('disabled', true);

    CleanSEO.ajax('get_trends', { keyword: keyword })
    .then(html => {
        $('#cleanseo-trends-chart').html(html);
        CleanSEO.showMessage('Dane trendów zostały pobrane', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $submit.text(originalText).prop('disabled', false);
    });
});

// Pobierz historię trendów
$('#cleanseo-trends-range').on('change', function() {
    const keyword = $('#cleanseo-trends-keyword').val();
    const range = $(this).val();

    if (!keyword) {
        CleanSEO.showMessage('Najpierw wyszukaj słowo kluczowe', 'error');
        return;
    }

    CleanSEO.ajax('get_trends_history', {
        keyword: keyword,
        range: range
    })
    .then(data => {
        // Tutaj aktualizacja wykresu z nowymi danymi
        CleanSEO.showMessage('Historia trendów została zaktualizowana', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    });
});

// Eksportuj trendy do CSV
$('#cleanseo-trends-export-csv').on('click', function() {
    const keyword = $('#cleanseo-trends-keyword').val();
    const range = $('#cleanseo-trends-range').val();

    if (!keyword) {
        CleanSEO.showMessage('Najpierw wyszukaj słowo kluczowe', 'error');
        return;
    }

    window.location.href = cleanseo_vars.ajaxurl + '?action=export_trends_csv&keyword=' + encodeURIComponent(keyword) + '&range=' + encodeURIComponent(range) + '&nonce=' + cleanseo_vars.nonces.export_trends_csv;
});

// Eksportuj trendy do PDF
$('#cleanseo-trends-export-pdf').on('click', function() {
    const keyword = $('#cleanseo-trends-keyword').val();
    const range = $('#cleanseo-trends-range').val();

    if (!keyword) {
        CleanSEO.showMessage('Najpierw wyszukaj słowo kluczowe', 'error');
        return;
    }

    window.location.href = cleanseo_vars.ajaxurl + '?action=export_trends_pdf&keyword=' + encodeURIComponent(keyword) + '&range=' + encodeURIComponent(range) + '&nonce=' + cleanseo_vars.nonces.export_trends_pdf;
});

// Wybierz słowo kluczowe konkurencji
$('#cleanseo-trends-competitor-keyword').on('change', function() {
    const keyword = $(this).val();
    if (keyword) {
        $('#cleanseo-trends-keyword').val(keyword);
        $('#cleanseo-trends-form').submit();
    }
});

// Wybierz własne słowo kluczowe
$('#cleanseo-trends-my-keyword').on('change', function() {
    const keyword = $(this).val();
    if (keyword) {
        $('#cleanseo-trends-keyword').val(keyword);
        $('#cleanseo-trends-form').submit();
    }
});
},

/**
* Inicjalizacja meta boxu
*/
initMetaBox: function() {
// Obsługa liczników znaków
$('#cleanseo_meta_title, #cleanseo_meta_description').on('keyup', function() {
    const id = $(this).attr('id');
    const length = $(this).val().length;
    let counterClass = '';
    
    if (id === 'cleanseo_meta_title') {
        if (length < 30 || length > 60) {
            counterClass = 'bad';
        } else if ((length >= 30 && length < 40) || (length > 50 && length <= 60)) {
            counterClass = 'medium';
        } else {
            counterClass = 'good';
        }
        $('#cleanseo_title_length').text(length).attr('class', counterClass);
    } else {
        if (length < 120 || length > 160) {
            counterClass = 'bad';
        } else if ((length >= 120 && length < 140) || (length > 150 && length <= 160)) {
            counterClass = 'medium';
        } else {
            counterClass = 'good';
        }
        $('#cleanseo_description_length').text(length).attr('class', counterClass);
    }
    
    // Aktualizuj podgląd
    if (id === 'cleanseo_meta_title') {
        $('.cleanseo-serp-title').text($(this).val() || 'Tytuł strony');
    } else {
        $('.cleanseo-serp-description').text($(this).val() || 'Opis strony');
    }
});

// Generowanie meta tagów
$('#cleanseo_generate_meta').on('click', function() {
    const $button = $(this);
    const post_id = $button.data('post-id');
    const originalText = $button.text();
    
    $button.text('Generowanie...').prop('disabled', true);
    
    CleanSEO.ajax('generate_product_meta', {
        post_id: post_id
    })
    .then(result => {
        $('#cleanseo_meta_title').val(result.title).trigger('keyup');
        $('#cleanseo_meta_description').val(result.description).trigger('keyup');
        CleanSEO.showMessage('Meta tagi zostały wygenerowane', 'success');
    })
    .catch(error => {
        CleanSEO.showMessage(error.message, 'error');
    })
    .finally(() => {
        $button.text(originalText).prop('disabled', false);
    });
});

// Inicjalizuj liczniki przy ładowaniu
$('#cleanseo_meta_title, #cleanseo_meta_description').trigger('keyup');
}
};

// Inicjalizacja po załadowaniu dokumentu
$(document).ready(function() {
CleanSEO.init();

// Inicjalizuj meta box, jeśli jest obecny
if ($('#cleanseo_meta_box').length) {
CleanSEO.initMetaBox();
}
});

})(jQuery);
          