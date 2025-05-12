<?php
if (!defined('WPINC')) {
    die;
}

// Pobierz wszystkie lokalizacje
global $wpdb;
$locations_table = $wpdb->prefix . 'seo_locations';

// Debugowanie tabeli
// error_log('CleanSEO Local SEO: Sprawdzanie tabeli ' . $locations_table);
if (!$wpdb->get_var("SHOW TABLES LIKE '$locations_table'")) {
    // echo '<div class="error"><p>Błąd: Tabela ' . $locations_table . ' nie istnieje!</p></div>';
    // error_log('CleanSEO Local SEO: Tabela nie istnieje');
} else {
    // error_log('CleanSEO Local SEO: Tabela istnieje');
}

$locations = $wpdb->get_results("SELECT * FROM $locations_table ORDER BY name ASC");

// Obsługa formularza
if (isset($_POST['cleanseo_add_location']) && check_admin_referer('cleanseo_add_location')) {
    $data = array(
        'name' => sanitize_text_field($_POST['name']),
        'street' => sanitize_text_field($_POST['street']),
        'city' => sanitize_text_field($_POST['city']),
        'postal_code' => sanitize_text_field($_POST['postal_code']),
        'country' => sanitize_text_field($_POST['country']),
        'phone' => sanitize_text_field($_POST['phone']),
        'email' => sanitize_email($_POST['email']),
        'google_place_id' => sanitize_text_field($_POST['google_place_id']),
        'google_place_url' => esc_url_raw($_POST['google_place_url']),
        'opening_hours' => json_encode($_POST['opening_hours']),
        'services' => json_encode(array_map('sanitize_text_field', $_POST['services'])),
        'payment_methods' => json_encode(array_map('sanitize_text_field', $_POST['payment_methods'])),
        'price_range' => sanitize_text_field($_POST['price_range'])
    );

    // Geokodowanie adresu
    $address = urlencode($data['street'] . ', ' . $data['city'] . ', ' . $data['postal_code'] . ', ' . $data['country']);
    $response = wp_remote_get("https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key=" . get_option('cleanseo_google_api_key'));
    
    if (!is_wp_error($response)) {
        $geo_data = json_decode(wp_remote_retrieve_body($response), true);
        if ($geo_data['status'] === 'OK') {
            $data['latitude'] = $geo_data['results'][0]['geometry']['location']['lat'];
            $data['longitude'] = $geo_data['results'][0]['geometry']['location']['lng'];
        }
    }

    $wpdb->insert($locations_table, $data);
    wp_redirect(add_query_arg('message', 'location_added'));
    exit;
}
?>

<div class="wrap cleanseo-admin">
    <h1>SEO Lokalne</h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success">
            <p>
                <?php
                switch ($_GET['message']) {
                    case 'location_added':
                        echo 'Lokalizacja została dodana pomyślnie.';
                        break;
                    case 'location_updated':
                        echo 'Lokalizacja została zaktualizowana pomyślnie.';
                        break;
                    case 'location_deleted':
                        echo 'Lokalizacja została usunięta pomyślnie.';
                        break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="cleanseo-admin-grid">
        <!-- Formularz dodawania lokalizacji -->
        <div class="cleanseo-admin-card">
            <h2>Dodaj nową lokalizację</h2>
            <form method="post" action="">
                <?php wp_nonce_field('cleanseo_add_location'); ?>
                
                <div class="form-group">
                    <label for="name">Nazwa firmy *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="street">Ulica *</label>
                    <input type="text" id="street" name="street" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Miasto *</label>
                        <input type="text" id="city" name="city" required>
                    </div>

                    <div class="form-group">
                        <label for="postal_code">Kod pocztowy *</label>
                        <input type="text" id="postal_code" name="postal_code" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="country">Kraj *</label>
                    <input type="text" id="country" name="country" value="Polska" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="google_place_id">ID miejsca w Google</label>
                    <input type="text" id="google_place_id" name="google_place_id">
                </div>

                <div class="form-group">
                    <label for="google_place_url">Link do wizytówki Google</label>
                    <input type="url" id="google_place_url" name="google_place_url">
                </div>

                <div class="form-group">
                    <label>Godziny otwarcia</label>
                    <div class="opening-hours-grid">
                        <?php
                        $days = array(
                            'monday' => 'Poniedziałek',
                            'tuesday' => 'Wtorek',
                            'wednesday' => 'Środa',
                            'thursday' => 'Czwartek',
                            'friday' => 'Piątek',
                            'saturday' => 'Sobota',
                            'sunday' => 'Niedziela'
                        );
                        foreach ($days as $key => $day):
                        ?>
                        <div class="opening-hours-row">
                            <label><?php echo $day; ?></label>
                            <input type="time" name="opening_hours[<?php echo $key; ?>][open]">
                            <span>do</span>
                            <input type="time" name="opening_hours[<?php echo $key; ?>][close]">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="services">Usługi</label>
                    <div class="services-container">
                        <div class="service-row">
                            <input type="text" name="services[]" placeholder="Dodaj usługę">
                            <button type="button" class="button add-service">+</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_methods">Metody płatności</label>
                    <div class="payment-methods-container">
                        <div class="payment-method-row">
                            <input type="text" name="payment_methods[]" placeholder="Dodaj metodę płatności">
                            <button type="button" class="button add-payment-method">+</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price_range">Zakres cenowy</label>
                    <select id="price_range" name="price_range">
                        <option value="">Wybierz...</option>
                        <option value="€">€</option>
                        <option value="€€">€€</option>
                        <option value="€€€">€€€</option>
                        <option value="€€€€">€€€€</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" name="cleanseo_add_location" class="button button-primary">Dodaj lokalizację</button>
                </div>
            </form>
        </div>

        <!-- Lista lokalizacji -->
        <div class="cleanseo-admin-card">
            <h2>Twoje lokalizacje</h2>
            <?php if ($locations): ?>
                <div class="locations-grid">
                    <?php foreach ($locations as $location): ?>
                        <div class="location-card">
                            <h3><?php echo esc_html($location->name); ?></h3>
                            <p>
                                <?php echo esc_html($location->street); ?><br>
                                <?php echo esc_html($location->postal_code . ' ' . $location->city); ?><br>
                                <?php echo esc_html($location->country); ?>
                            </p>
                            <?php if ($location->phone): ?>
                                <p>Tel: <?php echo esc_html($location->phone); ?></p>
                            <?php endif; ?>
                            <?php if ($location->email): ?>
                                <p>Email: <?php echo esc_html($location->email); ?></p>
                            <?php endif; ?>
                            <div class="location-actions">
                                <button type="button" class="button edit-location" data-id="<?php echo $location->id; ?>">Edytuj</button>
                                <button type="button" class="button button-link-delete delete-location" data-id="<?php echo $location->id; ?>">Usuń</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nie dodano jeszcze żadnych lokalizacji.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cleanseo-admin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

.cleanseo-admin-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="url"],
select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.opening-hours-grid {
    display: grid;
    gap: 0.5rem;
}

.opening-hours-row {
    display: grid;
    grid-template-columns: 120px 1fr auto 1fr;
    align-items: center;
    gap: 0.5rem;
}

.services-container,
.payment-methods-container {
    display: grid;
    gap: 0.5rem;
}

.service-row,
.payment-method-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.5rem;
}

.locations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.location-card {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.location-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 1024px) {
    .cleanseo-admin-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Dodawanie nowego pola usługi
    $('.add-service').click(function() {
        var row = $(this).closest('.service-row').clone();
        row.find('input').val('');
        $(this).closest('.services-container').append(row);
    });

    // Dodawanie nowego pola metody płatności
    $('.add-payment-method').click(function() {
        var row = $(this).closest('.payment-method-row').clone();
        row.find('input').val('');
        $(this).closest('.payment-methods-container').append(row);
    });

    // Usuwanie lokalizacji
    $('.delete-location').click(function() {
        if (confirm('Czy na pewno chcesz usunąć tę lokalizację?')) {
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'cleanseo_delete_location',
                location_id: id,
                nonce: '<?php echo wp_create_nonce('cleanseo_delete_location'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
});
</script> 