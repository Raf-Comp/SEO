<?php
/**
 * Klasa obsługująca SEO Lokalne
 *
 * @package CleanSEO
 * @subpackage LocalSEO
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Local_SEO {
    private $wpdb;
    private $tables;
    private $cache;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = array(
            'settings' => $wpdb->prefix . 'seo_settings',
            'redirects' => $wpdb->prefix . 'seo_redirects',
            'competitors' => $wpdb->prefix . 'seo_competitors',
            'logs' => $wpdb->prefix . 'seo_logs',
            'audits' => $wpdb->prefix . 'seo_audits',
            'locations' => $wpdb->prefix . 'seo_locations',
            'stats' => $wpdb->prefix . 'seo_stats',
            'analytics' => $wpdb->prefix . 'seo_analytics'
        );
        $this->cache = new CleanSEO_Cache();
        $this->logger = new CleanSEO_Logger();
        
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_cleanseo_add_location', array($this, 'add_location'));
        add_action('wp_ajax_cleanseo_update_location', array($this, 'update_location'));
        add_action('wp_ajax_cleanseo_delete_location', array($this, 'delete_location'));
        add_action('wp_ajax_cleanseo_get_locations', array($this, 'get_locations'));
        add_action('wp_ajax_cleanseo_get_location', array($this, 'get_location'));
        
        // Frontend hooks
        add_action('wp_head', array($this, 'output_local_business_schema'));
        
        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Załaduj zasoby administracyjne
     */
    public function enqueue_admin_assets($hook) {
        if ('cleanseo-optimizer_page_cleanseo-local-seo' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'cleanseo-local-seo',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/cleanseo-local-seo.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'cleanseo-local-seo',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/cleanseo-local-seo.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('cleanseo-local-seo', 'cleanseoLocalSEO', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cleanseo_local_seo_nonce'),
            'messages' => array(
                'error' => __('Wystąpił błąd podczas przetwarzania żądania.', 'cleanseo-optimizer'),
                'confirmDelete' => __('Czy na pewno chcesz usunąć tę lokalizację?', 'cleanseo-optimizer'),
                'success' => __('Operacja zakończona sukcesem.', 'cleanseo-optimizer')
            )
        ));
    }

    /**
     * Dodaj nową lokalizację
     */
    public function add_location($name, $street, $city, $state, $zip, $country, $phone, $email, $website, $lat, $lng) {
        $result = $this->wpdb->insert(
            $this->tables['locations'],
            array(
                'name' => $name,
                'street' => $street,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => $country,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'lat' => $lat,
                'lng' => $lng,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s')
        );

        if ($result) {
            $this->logger->log('location_added', "Added location: {$name}", array('name' => $name, 'city' => $city));
            $this->cache->delete('locations');
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * Aktualizuj lokalizację
     */
    public function update_location($id, $name, $street, $city, $state, $zip, $country, $phone, $email, $website, $lat, $lng) {
        $result = $this->wpdb->update(
            $this->tables['locations'],
            array(
                'name' => $name,
                'street' => $street,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => $country,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'lat' => $lat,
                'lng' => $lng,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s'),
            array('%d')
        );

        if ($result !== false) {
            $this->logger->log('location_updated', "Updated location: {$name}", array('id' => $id, 'name' => $name));
            $this->cache->delete('locations');
            return true;
        }

        return false;
    }

    /**
     * Usuń lokalizację
     */
    public function delete_location($id) {
        $location = $this->get_location($id);
        if (!$location) {
            return false;
        }

        $result = $this->wpdb->delete(
            $this->tables['locations'],
            array('id' => $id),
            array('%d')
        );

        if ($result) {
            $this->logger->log('location_deleted', "Deleted location: {$location->name}", array('id' => $id, 'name' => $location->name));
            $this->cache->delete('locations');
            return true;
        }

        return false;
    }

    /**
     * Pobierz wszystkie lokalizacje
     */
    public function get_locations($status = 'active') {
        $cached = $this->cache->get('locations');
        if ($cached !== false) {
            return $cached;
        }

        $locations = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['locations']} WHERE status = %s ORDER BY name ASC",
            $status
        ));

        $this->cache->set('locations', $locations);
        return $locations;
    }

    /**
     * Pobierz pojedynczą lokalizację
     */
    public function get_location($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['locations']} WHERE id = %d",
            $id
        ));
    }

    /**
     * Wygeneruj i wyświetl schema.org markup dla lokalizacji
     */
    public function output_local_business_schema() {
        if (is_admin()) {
            return;
        }

        global $wpdb;
        $locations = $wpdb->get_results(
            "SELECT * FROM {$this->tables['locations']} WHERE status = 'active'",
            ARRAY_A
        );

        if (empty($locations)) {
            return;
        }

        $schema = array();
        foreach ($locations as $location) {
            $business_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                '@id' => home_url('/#local-business-' . $location['id']),
                'name' => $location['name'],
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $location['street'],
                    'addressLocality' => $location['city'],
                    'postalCode' => $location['zip'],
                    'addressCountry' => $location['country']
                )
            );

            if (!empty($location['phone'])) {
                $business_schema['telephone'] = $location['phone'];
            }

            if (!empty($location['email'])) {
                $business_schema['email'] = $location['email'];
            }

            if (!empty($location['google_place_id'])) {
                $business_schema['sameAs'] = 'https://www.google.com/maps/place/?q=place_id:' . $location['google_place_id'];
            }

            if (!empty($location['opening_hours'])) {
                $opening_hours = json_decode($location['opening_hours'], true);
                if ($opening_hours) {
                    $business_schema['openingHoursSpecification'] = array();
                    foreach ($opening_hours as $day => $hours) {
                        if (!empty($hours['open']) && !empty($hours['close'])) {
                            $business_schema['openingHoursSpecification'][] = array(
                                '@type' => 'OpeningHoursSpecification',
                                'dayOfWeek' => ucfirst($day),
                                'opens' => $hours['open'],
                                'closes' => $hours['close']
                            );
                        }
                    }
                }
            }

            if (!empty($location['price_range'])) {
                $business_schema['priceRange'] = $location['price_range'];
            }

            if (!empty($location['services'])) {
                $services = json_decode($location['services'], true);
                if ($services) {
                    $business_schema['hasOfferCatalog'] = array(
                        '@type' => 'OfferCatalog',
                        'name' => 'Usługi',
                        'itemListElement' => array_map(function($service) {
                            return array(
                                '@type' => 'Offer',
                                'itemOffered' => array(
                                    '@type' => 'Service',
                                    'name' => $service
                                )
                            );
                        }, $services)
                    );
                }
            }

            $schema[] = $business_schema;
        }

        if (!empty($schema)) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
        }
    }

    /**
     * Migracja lokalizacji z opcji (CleanSEO_Locations) do bazy danych
     */
    public function migrate_locations_from_option() {
        $old_locations = get_option('cleanseo_locations', array());
        if (empty($old_locations) || !is_array($old_locations)) {
            return;
        }
        global $wpdb;
        foreach ($old_locations as $loc) {
            $data = array(
                'name' => $loc['name'],
                'street' => $loc['address']['street'],
                'city' => $loc['address']['city'],
                'postal_code' => $loc['address']['postal_code'],
                'country' => $loc['address']['country'],
                'phone' => $loc['phone'],
                'email' => $loc['email'],
                'google_place_id' => '',
                'google_place_url' => '',
                'opening_hours' => wp_json_encode($loc['opening_hours']),
                'services' => wp_json_encode($loc['services']),
                'payment_methods' => wp_json_encode($loc['payment_methods']),
                'price_range' => $loc['price_range'],
                'status' => 'active'
            );
            // Dodaj do bazy tylko jeśli nie istnieje już lokalizacja o tej nazwie i adresie
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['locations']} WHERE name = %s AND street = %s AND city = %s",
                $data['name'], $data['street'], $data['city']
            ));
            if (!$exists) {
                $wpdb->insert($this->tables['locations'], $data);
            }
        }
        // Usuń starą opcję po migracji
        delete_option('cleanseo_locations');
    }
}