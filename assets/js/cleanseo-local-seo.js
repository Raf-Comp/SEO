(function($) {
    'use strict';

    const CleanSEOLocalSEO = {
        init: function() {
            this.initLocationForm();
            this.initLocationCards();
            this.initBusinessHours();
        },

        initLocationForm: function() {
            const $form = $('#cleanseo-location-form');
            if (!$form.length) return;

            $form.on('submit', function(e) {
                e.preventDefault();
                CleanSEOLocalSEO.saveLocation($(this));
            });

            // Initialize business hours
            this.initBusinessHoursForm();
        },

        initLocationCards: function() {
            $('.location-card').each(function() {
                const $card = $(this);
                const $actions = $card.find('.location-actions');

                $actions.on('click', '.location-edit', function() {
                    CleanSEOLocalSEO.editLocation($card.data('id'));
                });

                $actions.on('click', '.location-delete', function() {
                    if (confirm('Czy na pewno chcesz usunąć tę lokalizację?')) {
                        CleanSEOLocalSEO.deleteLocation($card.data('id'));
                    }
                });
            });
        },

        initBusinessHours: function() {
            $('.business-hours').each(function() {
                const $hours = $(this);
                const hours = $hours.data('hours');
                
                if (hours) {
                    const $list = $hours.find('.hours-list');
                    Object.entries(hours).forEach(([day, schedule]) => {
                        const $day = $('<div class="hours-day"></div>');
                        $day.append(`<span class="day">${day}</span>`);
                        $day.append(`<span class="time">${schedule.open} - ${schedule.close}</span>`);
                        $list.append($day);
                    });
                }
            });
        },

        initBusinessHoursForm: function() {
            const $hoursContainer = $('#business-hours-container');
            if (!$hoursContainer.length) return;

            const days = ['Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota', 'Niedziela'];
            
            days.forEach(day => {
                const $dayGroup = $(`
                    <div class="hours-form-group">
                        <label>${day}</label>
                        <div class="hours-inputs">
                            <input type="time" name="hours[${day}][open]" class="hours-open">
                            <span>do</span>
                            <input type="time" name="hours[${day}][close]" class="hours-close">
                        </div>
                    </div>
                `);
                $hoursContainer.append($dayGroup);
            });
        },

        saveLocation: function($form) {
            const $submitButton = $form.find('button[type="submit"]');
            const originalText = $submitButton.text();
            
            $submitButton.prop('disabled', true).text('Zapisywanie...');
            
            const formData = new FormData($form[0]);
            formData.append('action', 'cleanseo_save_location');
            formData.append('nonce', cleanseoLocalSEO.nonce);

            $.ajax({
                url: cleanseoLocalSEO.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        CleanSEOLocalSEO.showMessage('success', 'Lokalizacja została zapisana pomyślnie.');
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        CleanSEOLocalSEO.showMessage('error', response.data.message || 'Wystąpił błąd podczas zapisywania lokalizacji.');
                    }
                },
                error: function() {
                    CleanSEOLocalSEO.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        },

        editLocation: function(id) {
            window.location.href = `${cleanseoLocalSEO.adminUrl}?page=cleanseo-local-seo&action=edit&id=${id}`;
        },

        deleteLocation: function(id) {
            $.ajax({
                url: cleanseoLocalSEO.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_delete_location',
                    nonce: cleanseoLocalSEO.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        CleanSEOLocalSEO.showMessage('success', 'Lokalizacja została usunięta pomyślnie.');
                        $(`.location-card[data-id="${id}"]`).fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        CleanSEOLocalSEO.showMessage('error', response.data.message || 'Wystąpił błąd podczas usuwania lokalizacji.');
                    }
                },
                error: function() {
                    CleanSEOLocalSEO.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                }
            });
        },

        showMessage: function(type, message) {
            const $message = $(`<div class="message ${type}-message">${message}</div>`);
            $('.cleanseo-local-seo').prepend($message);
            
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        CleanSEOLocalSEO.init();
    });

})(jQuery); 