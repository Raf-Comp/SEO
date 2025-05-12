/**
 * Skrypt dla panelu administracyjnego Local SEO
 */
jQuery(document).ready(function($) {
    // Obsługa formularza dodawania lokalizacji
    $('#cleanseo-add-location-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'cleanseo_add_location');
        formData.append('nonce', cleanseoLocalSEO.nonce);

        $.ajax({
            url: cleanseoLocalSEO.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $('#cleanseo-add-location-form')[0].reset();
                    refreshLocationsList();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', cleanseoLocalSEO.messages.error);
            }
        });
    });

    // Obsługa edycji lokalizacji
    $(document).on('click', '.edit-location', function() {
        var locationId = $(this).data('id');
        
        $.ajax({
            url: cleanseoLocalSEO.ajaxurl,
            type: 'GET',
            data: {
                action: 'cleanseo_get_location',
                id: locationId,
                nonce: cleanseoLocalSEO.nonce
            },
            success: function(response) {
                if (response.success) {
                    fillEditForm(response.data);
                    $('#edit-location-modal').show();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', cleanseoLocalSEO.messages.error);
            }
        });
    });

    // Obsługa formularza edycji lokalizacji
    $('#cleanseo-edit-location-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'cleanseo_update_location');
        formData.append('nonce', cleanseoLocalSEO.nonce);

        $.ajax({
            url: cleanseoLocalSEO.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $('#edit-location-modal').hide();
                    refreshLocationsList();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', cleanseoLocalSEO.messages.error);
            }
        });
    });

    // Obsługa usuwania lokalizacji
    $(document).on('click', '.delete-location', function() {
        if (!confirm(cleanseoLocalSEO.messages.confirmDelete)) {
            return;
        }

        var locationId = $(this).data('id');
        
        $.ajax({
            url: cleanseoLocalSEO.ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_delete_location',
                id: locationId,
                nonce: cleanseoLocalSEO.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    refreshLocationsList();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', cleanseoLocalSEO.messages.error);
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

    // Pokaż powiadomienie
    function showNotice(type, message) {
        var notice = $('.cleanseo-admin-notice.notice-' + type);
        notice.find('p').text(message);
        notice.show();
        
        setTimeout(function() {
            notice.fadeOut();
        }, 3000);
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
}); 