(function($) {
    'use strict';

    const CleanSEORedirects = {
        init: function() {
            this.initRedirectForm();
            this.initRedirectTable();
            this.initBulkActions();
        },

        initRedirectForm: function() {
            const $form = $('#cleanseo-redirect-form');
            if (!$form.length) return;

            $form.on('submit', function(e) {
                e.preventDefault();
                CleanSEORedirects.saveRedirect($(this));
            });

            // Source URL validation
            $form.find('input[name="source"]').on('blur', function() {
                const $input = $(this);
                const value = $input.val();
                
                if (value && !value.startsWith('/')) {
                    $input.val('/' + value);
                }
            });
        },

        initRedirectTable: function() {
            $('.redirects-table').on('click', '.redirect-edit', function() {
                const $row = $(this).closest('tr');
                CleanSEORedirects.editRedirect($row.data('id'));
            });

            $('.redirects-table').on('click', '.redirect-delete', function() {
                const $row = $(this).closest('tr');
                if (confirm('Czy na pewno chcesz usunąć to przekierowanie?')) {
                    CleanSEORedirects.deleteRedirect($row.data('id'));
                }
            });
        },

        initBulkActions: function() {
            const $bulkActions = $('#bulk-actions');
            if (!$bulkActions.length) return;

            $bulkActions.on('change', function() {
                const action = $(this).val();
                if (action) {
                    const $checkboxes = $('.redirect-checkbox:checked');
                    if ($checkboxes.length === 0) {
                        alert('Wybierz przynajmniej jedno przekierowanie.');
                        $(this).val('');
                        return;
                    }

                    const ids = $checkboxes.map(function() {
                        return $(this).val();
                    }).get();

                    if (action === 'delete') {
                        if (confirm('Czy na pewno chcesz usunąć wybrane przekierowania?')) {
                            CleanSEORedirects.bulkDelete(ids);
                        }
                    }
                    $(this).val('');
                }
            });
        },

        saveRedirect: function($form) {
            const $submitButton = $form.find('button[type="submit"]');
            const originalText = $submitButton.text();
            
            $submitButton.prop('disabled', true).text('Zapisywanie...');
            
            const formData = new FormData($form[0]);
            formData.append('action', 'cleanseo_save_redirect');
            formData.append('nonce', cleanseoRedirects.nonce);

            $.ajax({
                url: cleanseoRedirects.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        CleanSEORedirects.showMessage('success', 'Przekierowanie zostało zapisane pomyślnie.');
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        CleanSEORedirects.showMessage('error', response.data.message || 'Wystąpił błąd podczas zapisywania przekierowania.');
                    }
                },
                error: function() {
                    CleanSEORedirects.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        },

        editRedirect: function(id) {
            window.location.href = `${cleanseoRedirects.adminUrl}?page=cleanseo-redirects&action=edit&id=${id}`;
        },

        deleteRedirect: function(id) {
            $.ajax({
                url: cleanseoRedirects.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_delete_redirect',
                    nonce: cleanseoRedirects.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        CleanSEORedirects.showMessage('success', 'Przekierowanie zostało usunięte pomyślnie.');
                        $(`.redirects-table tr[data-id="${id}"]`).fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        CleanSEORedirects.showMessage('error', response.data.message || 'Wystąpił błąd podczas usuwania przekierowania.');
                    }
                },
                error: function() {
                    CleanSEORedirects.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                }
            });
        },

        bulkDelete: function(ids) {
            $.ajax({
                url: cleanseoRedirects.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_bulk_delete_redirects',
                    nonce: cleanseoRedirects.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        CleanSEORedirects.showMessage('success', 'Wybrane przekierowania zostały usunięte pomyślnie.');
                        ids.forEach(id => {
                            $(`.redirects-table tr[data-id="${id}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                    } else {
                        CleanSEORedirects.showMessage('error', response.data.message || 'Wystąpił błąd podczas usuwania przekierowań.');
                    }
                },
                error: function() {
                    CleanSEORedirects.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                }
            });
        },

        showMessage: function(type, message) {
            const $message = $(`<div class="message ${type}-message">${message}</div>`);
            $('.cleanseo-redirects').prepend($message);
            
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        CleanSEORedirects.init();
    });

})(jQuery); 