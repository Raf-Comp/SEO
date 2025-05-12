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

            // Automatyczne usunięcie po 5 sekundach
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
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
            if (!$form.length || !$list.length) return;

            // Dodaj przekierowanie
            $form.on('submit', function(e) {
                e.preventDefault();
                const $submit = $(this).find('input[type="submit"]');
                const originalText = $submit.val();

                $submit.val(cleanseo_vars.i18n.saving).prop('disabled', true);

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
                    $submit.val(originalText).prop('disabled', false);
                });
            });

            // Usuń przekierowanie
            $list.on('click', '.delete-redirect', function() {
                if (!confirm(cleanseo_vars.i18n.confirmDelete)) return;

                const $button = $(this);
                const id = $button.data('id');

                $button.prop('disabled', true);

                CleanSEO.ajax('delete_redirect', { id: id })
                .then(() => {
                    $button.closest('.redirect-row').fadeOut(() => {
                        $(this).remove();
                    });
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                    $button.prop('disabled', false);
                });
            });

            // Załaduj listę przekierowań
            function loadRedirects() {
                $list.html('<p>' + cleanseo_vars.i18n.loading + '</p>');

                CleanSEO.ajax('get_redirects')
                .then(html => {
                    $list.html(html);
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                    $list.html('<p class="error">' + error.message + '</p>');
                });
            }

            // Załaduj początkową listę
            loadRedirects();
        },

        /**
         * Obsługa konkurencji
         */
        initCompetitors: function() {
            const $form = $('#cleanseo-competitor-form');
            const $list = $('#cleanseo-competitors-list');
            if (!$form.length || !$list.length) return;

            // Dodaj konkurenta
            $form.on('submit', function(e) {
                e.preventDefault();
                const $submit = $(this).find('input[type="submit"]');
                const originalText = $submit.val();

                $submit.val(cleanseo_vars.i18n.saving).prop('disabled', true);

                CleanSEO.ajax('add_competitor', {
                    domain: $('#domain').val(),
                    keywords: $('#keywords').val()
                })
                .then(() => {
                    $form[0].reset();
                    loadCompetitors();
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $submit.val(originalText).prop('disabled', false);
                });
            });

            // Usuń konkurenta
            $list.on('click', '.delete-competitor', function() {
                if (!confirm(cleanseo_vars.i18n.confirmDelete)) return;

                const $button = $(this);
                const id = $button.data('id');

                $button.prop('disabled', true);

                CleanSEO.ajax('delete_competitor', { id: id })
                .then(() => {
                    $button.closest('.competitor-row').fadeOut(() => {
                        $(this).remove();
                    });
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                    $button.prop('disabled', false);
                });
            });

            // Załaduj listę konkurentów
            function loadCompetitors() {
                $list.html('<p>' + cleanseo_vars.i18n.loading + '</p>');

                CleanSEO.ajax('get_competitors')
                .then(html => {
                    $list.html(html);
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                    $list.html('<p class="error">' + error.message + '</p>');
                });
            }

            // Załaduj początkową listę
            loadCompetitors();
        },

        /**
         * Obsługa audytu
         */
        initAudit: function() {
            const $audit = $('#cleanseo-audit');
            if (!$audit.length) return;

            $audit.on('click', '#run-audit', function() {
                const $button = $(this);
                const originalText = $button.val();

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('run_audit')
                .then(data => {
                    $('#audit-results').html(data);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
                });
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
                const originalText = $button.val();

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('get_content_analysis')
                .then(html => {
                    $('#analysis-results').html(html);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
                });
            });
        },

        /**
         * Obsługa SEO lokalnego
         */
        initLocalSEO: function() {
            const $local = $('#cleanseo-local-seo');
            if (!$local.length) return;

            $local.on('click', '#get-local-seo', function() {
                const $button = $(this);
                const originalText = $button.val();

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('get_local_seo')
                .then(html => {
                    $('#local-seo-results').html(html);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
                });
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
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $submit.val(originalText).prop('disabled', false);
                });
            });

            // Testuj prompt
            $('#test-prompt').on('click', function() {
                const $button = $(this);
                const originalText = $button.val();
                const prompt = $('#prompt').val();

                if (!prompt) {
                    CleanSEO.showMessage('Prompt nie może być pusty');
                    return;
                }

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('test_openai_prompt', { prompt: prompt })
                .then(result => {
                    $('#prompt-result').html(result);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
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
            $('#sitemap-settings-form').on('submit', function(e) {
                e.preventDefault();
                const $submit = $(this).find('input[type="submit"]');
                const originalText = $submit.val();

                $submit.val(cleanseo_vars.i18n.saving).prop('disabled', true);

                CleanSEO.ajax('save_sitemap_settings', {
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

            // Waliduj sitemap
            $('#validate-sitemap').on('click', function() {
                const $button = $(this);
                const originalText = $button.val();

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('validate_sitemap')
                .then(message => {
                    $('#sitemap-validation').html(message);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
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
            $('#get-trends').on('click', function() {
                const $button = $(this);
                const originalText = $button.val();
                const keyword = $('#trend-keyword').val();

                if (!keyword) {
                    CleanSEO.showMessage('Słowo kluczowe nie może być puste');
                    return;
                }

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('get_trends', { keyword: keyword })
                .then(html => {
                    $('#trends-results').html(html);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
                });
            });

            // Pobierz historię trendów
            $('#get-trends-history').on('click', function() {
                const $button = $(this);
                const originalText = $button.val();
                const keyword = $('#trend-keyword').val();
                const range = $('#trend-range').val();

                if (!keyword) {
                    CleanSEO.showMessage('Słowo kluczowe nie może być puste');
                    return;
                }

                $button.val(cleanseo_vars.i18n.loading).prop('disabled', true);

                CleanSEO.ajax('get_trends_history', {
                    keyword: keyword,
                    range: range
                })
                .then(data => {
                    // Tutaj możesz użyć biblioteki do wykresów, np. Chart.js
                    console.log('Dane trendów:', data);
                    CleanSEO.showMessage(cleanseo_vars.i18n.success, 'success');
                })
                .catch(error => {
                    CleanSEO.showMessage(error.message);
                })
                .finally(() => {
                    $button.val(originalText).prop('disabled', false);
                });
            });

            // Eksportuj trendy do CSV
            $('#export-trends').on('click', function() {
                const keyword = $('#trend-keyword').val();
                const range = $('#trend-range').val();

                if (!keyword) {
                    CleanSEO.showMessage('Słowo kluczowe nie może być puste');
                    return;
                }

                window.location.href = cleanseo_vars.ajaxurl + '?action=export_trends_csv&keyword=' + encodeURIComponent(keyword) + '&range=' + encodeURIComponent(range);
            });
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        CleanSEO.init();
    });

})(jQuery);