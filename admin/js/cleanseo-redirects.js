jQuery(document).ready(function($) {
    // Inicjalizacja
    loadRedirects();
    
    // Obsługa formularza dodawania/edycji
    $('#cleanseo-redirects-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitButton = $form.find('button[type="submit"]');
        const isEdit = $form.data('edit-mode') === true;
        
        // Wyłącz przycisk podczas przetwarzania
        $submitButton.prop('disabled', true).text('Przetwarzanie...');
        
        // Przygotuj dane
        const formData = {
            action: isEdit ? 'cleanseo_update_redirect' : 'cleanseo_add_redirect',
            nonce: cleanseoRedirects.nonce,
            source_url: $form.find('input[name="source_url"]').val(),
            target_url: $form.find('input[name="target_url"]').val(),
            status_code: $form.find('select[name="status_code"]').val()
        };
        
        if (isEdit) {
            formData.redirect_id = $form.data('redirect-id');
        }
        
        // Wyślij żądanie AJAX
        $.post(cleanseoRedirects.ajaxurl, formData, function(response) {
            if (response.success) {
                showNotice('success', response.data.message);
                resetForm();
                loadRedirects();
            } else {
                showNotice('error', response.data || cleanseoRedirects.messages.error);
            }
        }).fail(function() {
            showNotice('error', cleanseoRedirects.messages.error);
        }).always(function() {
            $submitButton.prop('disabled', false).text(isEdit ? 'Aktualizuj' : 'Dodaj');
        });
    });
    
    // Obsługa edycji przekierowania
    $(document).on('click', '.edit-redirect', function(e) {
        e.preventDefault();
        
        const redirectId = $(this).data('id');
        
        $.post(cleanseoRedirects.ajaxurl, {
            action: 'cleanseo_get_redirect',
            nonce: cleanseoRedirects.nonce,
            id: redirectId
        }, function(response) {
            if (response.success) {
                const redirect = response.data;
                const $form = $('#cleanseo-redirects-form');
                
                $form.find('input[name="source_url"]').val(redirect.source_url);
                $form.find('input[name="target_url"]').val(redirect.target_url);
                $form.find('select[name="status_code"]').val(redirect.status_code);
                
                $form.data('edit-mode', true);
                $form.data('redirect-id', redirectId);
                $form.find('button[type="submit"]').text('Aktualizuj');
                
                // Przewiń do formularza
                $('html, body').animate({
                    scrollTop: $form.offset().top - 50
                }, 500);
            } else {
                showNotice('error', response.data || cleanseoRedirects.messages.error);
            }
        });
    });
    
    // Obsługa usuwania przekierowania
    $(document).on('click', '.delete-redirect', function(e) {
        e.preventDefault();
        
        if (!confirm(cleanseoRedirects.messages.confirmDelete)) {
            return;
        }
        
        const $button = $(this);
        const redirectId = $button.data('id');
        
        $button.prop('disabled', true);
        
        $.post(cleanseoRedirects.ajaxurl, {
            action: 'cleanseo_delete_redirect',
            nonce: cleanseoRedirects.nonce,
            id: redirectId
        }, function(response) {
            if (response.success) {
                showNotice('success', response.data.message);
                loadRedirects();
            } else {
                showNotice('error', response.data || cleanseoRedirects.messages.error);
            }
        }).fail(function() {
            showNotice('error', cleanseoRedirects.messages.error);
        }).always(function() {
            $button.prop('disabled', false);
        });
    });
    
    // Eksport przekierowań do CSV
    $('#cleanseo-export-redirects').on('click', function(e) {
        e.preventDefault();
        window.location.href = cleanseoRedirects.ajaxurl + '?action=cleanseo_export_redirects_csv';
    });

    // Import przekierowań z CSV
    $('#cleanseo-import-redirects-form').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'cleanseo_import_redirects_csv');
        formData.append('nonce', cleanseoRedirects.nonce);
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).text('Importowanie...');
        $.ajax({
            url: cleanseoRedirects.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Zaimportowano: ' + response.data.imported);
                    loadRedirects();
                } else {
                    showNotice('error', response.data || cleanseoRedirects.messages.error);
                }
            },
            error: function() {
                showNotice('error', cleanseoRedirects.messages.error);
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
        const ids = $('.redirect-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) {
            showNotice('error', 'Nie zaznaczono żadnych przekierowań.');
            return;
        }
        if (!confirm('Czy na pewno chcesz usunąć zaznaczone przekierowania?')) {
            return;
        }
        $(this).prop('disabled', true).text('Usuwanie...');
        $.post(cleanseoRedirects.ajaxurl, {
            action: 'cleanseo_batch_delete_redirects',
            nonce: cleanseoRedirects.nonce,
            ids: ids
        }, function(response) {
            if (response.success) {
                showNotice('success', 'Usunięto: ' + response.data.deleted);
                loadRedirects();
            } else {
                showNotice('error', response.data || cleanseoRedirects.messages.error);
            }
        }).fail(function() {
            showNotice('error', cleanseoRedirects.messages.error);
        }).always(() => {
            $('#cleanseo-batch-delete').prop('disabled', false).text('Usuń zaznaczone');
        });
    });
    
    // Funkcja ładowania przekierowań
    function loadRedirects() {
        const $list = $('#cleanseo-redirects-list');
        $list.html('<div class="loading">Ładowanie...</div>');
        
        $.post(cleanseoRedirects.ajaxurl, {
            action: 'cleanseo_get_redirects',
            nonce: cleanseoRedirects.nonce
        }, function(response) {
            if (response.success) {
                const redirects = response.data.redirects;
                
                if (redirects.length === 0) {
                    $list.html('<div class="no-redirects">Brak przekierowań</div>');
                    return;
                }
                
                let html = '<table><thead><tr>' +
                    '<th><input type="checkbox" id="redirects-check-all"></th>' +
                    '<th>Źródłowy URL</th>' +
                    '<th>Docelowy URL</th>' +
                    '<th>Status</th>' +
                    '<th>Odwiedzenia</th>' +
                    '<th>Ostatni dostęp</th>' +
                    '<th>Akcje</th>' +
                    '</tr></thead><tbody>';
                
                redirects.forEach(function(redirect) {
                    html += '<tr>' +
                        '<td><input type="checkbox" class="redirect-checkbox" value="' + redirect.id + '"></td>' +
                        '<td>' + escapeHtml(redirect.source_url) + '</td>' +
                        '<td>' + escapeHtml(redirect.target_url) + '</td>' +
                        '<td class="status-' + redirect.status_code + '">' + redirect.status_code + '</td>' +
                        '<td class="hits">' + redirect.hits + '</td>' +
                        '<td class="last-accessed">' + formatDate(redirect.last_accessed) + '</td>' +
                        '<td class="actions">' +
                        '<a href="#" class="edit edit-redirect" data-id="' + redirect.id + '">Edytuj</a>' +
                        '<a href="#" class="delete delete-redirect" data-id="' + redirect.id + '">Usuń</a>' +
                        '</td></tr>';
                });
                
                html += '</tbody></table>';
                $list.html(html);
                // Obsługa zaznacz wszystko
                $('#redirects-check-all').on('change', function() {
                    $('.redirect-checkbox').prop('checked', this.checked);
                });
            } else {
                showNotice('error', response.data || cleanseoRedirects.messages.error);
            }
        }).fail(function() {
            showNotice('error', cleanseoRedirects.messages.error);
        });
    }
    
    // Funkcja resetowania formularza
    function resetForm() {
        const $form = $('#cleanseo-redirects-form');
        $form[0].reset();
        $form.data('edit-mode', false);
        $form.data('redirect-id', null);
        $form.find('button[type="submit"]').text('Dodaj');
    }
    
    // Funkcja wyświetlania powiadomień
    function showNotice(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.cleanseo-redirects-header').after($notice);
        
        // Automatyczne usunięcie po 3 sekundach
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
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
}); 