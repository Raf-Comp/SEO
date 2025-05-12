<?php
/**
 * Klasa do obsługi wielu lokalizacji i schematu LocalBusiness
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Locations {
    private $locations;

    public function __construct() {
        $this->locations = get_option('cleanseo_locations', array());
    }

    /**
     * Dodaj nową lokalizację
     */
    public function add_location($data) {
        $location = array(
            'name' => sanitize_text_field($data['name']),
            'address' => array(
                'street' => sanitize_text_field($data['street']),
                'city' => sanitize_text_field($data['city']),
                'postal_code' => sanitize_text_field($data['postal_code']),
                'country' => sanitize_text_field($data['country'])
            ),
            'geo' => array(
                'latitude' => floatval($data['latitude']),
                'longitude' => floatval($data['longitude'])
            ),
            'phone' => sanitize_text_field($data['phone']),
            'email' => sanitize_email($data['email']),
            'opening_hours' => $this->sanitize_opening_hours($data['opening_hours']),
            'services' => array_map('sanitize_text_field', $data['services']),
            'payment_methods' => array_map('sanitize_text_field', $data['payment_methods']),
            'price_range' => sanitize_text_field($data['price_range'])
        );

        $this->locations[] = $location;
        update_option('cleanseo_locations', $this->locations);

        return true;
    }

    /**
     * Aktualizuj lokalizację
     */
    public function update_location($index, $data) {
        if (!isset($this->locations[$index])) {
            return false;
        }

        $this->locations[$index] = array(
            'name' => sanitize_text_field($data['name']),
            'address' => array(
                'street' => sanitize_text_field($data['street']),
                'city' => sanitize_text_field($data['city']),
                'postal_code' => sanitize_text_field($data['postal_code']),
                'country' => sanitize_text_field($data['country'])
            ),
            'geo' => array(
                'latitude' => floatval($data['latitude']),
                'longitude' => floatval($data['longitude'])
            ),
            'phone' => sanitize_text_field($data['phone']),
            'email' => sanitize_email($data['email']),
            'opening_hours' => $this->sanitize_opening_hours($data['opening_hours']),
            'services' => array_map('sanitize_text_field', $data['services']),
            'payment_methods' => array_map('sanitize_text_field', $data['payment_methods']),
            'price_range' => sanitize_text_field($data['price_range'])
        );

        update_option('cleanseo_locations', $this->locations);
        return true;
    }

    /**
     * Usuń lokalizację
     */
    public function remove_location($index) {
        if (!isset($this->locations[$index])) {
            return false;
        }

        unset($this->locations[$index]);
        $this->locations = array_values($this->locations);
        update_option('cleanseo_locations', $this->locations);

        return true;
    }

    /**
     * Pobierz wszystkie lokalizacje
     */
    public function get_locations() {
        return $this->locations;
    }

    /**
     * Generuj schemat JSON-LD dla LocalBusiness
     */
    public function generate_schema($location_index = null) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => home_url('/#organization')
        );

        if ($location_index !== null && isset($this->locations[$location_index])) {
            $location = $this->locations[$location_index];
        } else {
            $location = $this->locations[0];
        }

        $schema['name'] = $location['name'];
        $schema['address'] = array(
            '@type' => 'PostalAddress',
            'streetAddress' => $location['address']['street'],
            'addressLocality' => $location['address']['city'],
            'postalCode' => $location['address']['postal_code'],
            'addressCountry' => $location['address']['country']
        );

        $schema['geo'] = array(
            '@type' => 'GeoCoordinates',
            'latitude' => $location['geo']['latitude'],
            'longitude' => $location['geo']['longitude']
        );

        $schema['telephone'] = $location['phone'];
        $schema['email'] = $location['email'];

        // Opening Hours
        $opening_hours = array();
        foreach ($location['opening_hours'] as $day => $hours) {
            if (!empty($hours['open']) && !empty($hours['close'])) {
                $opening_hours[] = $day . ' ' . $hours['open'] . '-' . $hours['close'];
            }
        }
        $schema['openingHoursSpecification'] = $opening_hours;

        // Services
        if (!empty($location['services'])) {
            $schema['hasOfferCatalog'] = array(
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
                }, $location['services'])
            );
        }

        // Payment Methods
        if (!empty($location['payment_methods'])) {
            $schema['paymentAccepted'] = $location['payment_methods'];
        }

        // Price Range
        if (!empty($location['price_range'])) {
            $schema['priceRange'] = $location['price_range'];
        }

        return $schema;
    }

    /**
     * Sanityzuj godziny otwarcia
     */
    private function sanitize_opening_hours($hours) {
        $sanitized = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        foreach ($days as $day) {
            if (isset($hours[$day])) {
                $sanitized[$day] = array(
                    'open' => sanitize_text_field($hours[$day]['open']),
                    'close' => sanitize_text_field($hours[$day]['close'])
                );
            }
        }

        return $sanitized;
    }
} 