<?php

class CleanSEO_WooCommerce {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->init_hooks();
    }

    public function init_hooks() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Dodaj pola SEO do produktu
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_seo_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_seo_fields'));
        
        // Dodaj pola SEO do wariantów
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_seo_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_seo_fields'), 10, 2);
        
        // Dodaj pola identyfikatorów produktu
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_identifiers'));
        
        // Dodaj pola dla Open Graph
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_og_fields'));
        
        // Dodaj pola dla danych strukturalnych
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_schema_fields'));
        
        // Filtry dla meta tagów
        add_filter('woocommerce_structured_data_product', array($this, 'modify_product_structured_data'), 10, 2);
        add_action('woocommerce_before_single_product', array($this, 'add_product_schema_markup'));
        
        // Filtry dla tytułu i opisu
        add_filter('wp_title', array($this, 'modify_product_title'), 10, 2);
        add_filter('woocommerce_product_description_heading', array($this, 'modify_product_description_heading'));
        
        // Dodaj przycisk generowania meta
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_generate_meta_button'));
        
        // Obsługa AJAX dla generowania meta
        add_action('wp_ajax_cleanseo_generate_product_meta', array($this, 'handle_generate_product_meta'));
    }

    public function add_product_seo_fields() {
        global $post;
        
        echo '<div class="options_group">';
        echo '<h4>' . __('SEO Settings', 'cleanseo-optimizer') . '</h4>';
        
        // Meta Title
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_meta_title',
            'label' => __('Meta Title', 'cleanseo-optimizer'),
            'description' => __('Enter a custom meta title for this product. Leave empty to use the default.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'custom_attributes' => array(
                'maxlength' => '60',
                'placeholder' => __('Enter meta title (max 60 characters)', 'cleanseo-optimizer')
            )
        ));
        
        // Meta Description
        woocommerce_wp_textarea_input(array(
            'id' => '_cleanseo_meta_description',
            'label' => __('Meta Description', 'cleanseo-optimizer'),
            'description' => __('Enter a custom meta description for this product. Leave empty to use the default.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'custom_attributes' => array(
                'maxlength' => '155',
                'placeholder' => __('Enter meta description (max 155 characters)', 'cleanseo-optimizer')
            )
        ));
        
        // Focus Keyword
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_focus_keyword',
            'label' => __('Focus Keyword', 'cleanseo-optimizer'),
            'description' => __('Enter the main keyword for this product.', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        // Keywords
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_keywords',
            'label' => __('Keywords', 'cleanseo-optimizer'),
            'description' => __('Enter additional keywords, separated by commas.', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        echo '</div>';
    }

    public function add_product_identifiers() {
        echo '<div class="options_group">';
        echo '<h4>' . __('Product Identifiers', 'cleanseo-optimizer') . '</h4>';
        
        // GTIN
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_gtin',
            'label' => __('GTIN', 'cleanseo-optimizer'),
            'description' => __('Global Trade Item Number (GTIN)', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        // EAN
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_ean',
            'label' => __('EAN', 'cleanseo-optimizer'),
            'description' => __('European Article Number (EAN)', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        // ISBN
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_isbn',
            'label' => __('ISBN', 'cleanseo-optimizer'),
            'description' => __('International Standard Book Number (ISBN)', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        echo '</div>';
    }

    public function add_product_og_fields() {
        echo '<div class="options_group">';
        echo '<h4>' . __('Social Media', 'cleanseo-optimizer') . '</h4>';
        
        // OG Title
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_og_title',
            'label' => __('Social Media Title', 'cleanseo-optimizer'),
            'description' => __('Enter a custom title for social media sharing. Leave empty to use the meta title.', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        // OG Description
        woocommerce_wp_textarea_input(array(
            'id' => '_cleanseo_og_description',
            'label' => __('Social Media Description', 'cleanseo-optimizer'),
            'description' => __('Enter a custom description for social media sharing. Leave empty to use the meta description.', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        // OG Image
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_og_image',
            'label' => __('Social Media Image', 'cleanseo-optimizer'),
            'description' => __('Enter a custom image URL for social media sharing. Leave empty to use the product image.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'type' => 'url'
        ));
        
        echo '</div>';
    }

    public function add_product_schema_fields() {
        echo '<div class="options_group">';
        echo '<h4>' . __('Schema.org Data', 'cleanseo-optimizer') . '</h4>';
        
        // Product Type
        woocommerce_wp_select(array(
            'id' => '_cleanseo_schema_type',
            'label' => __('Product Type', 'cleanseo-optimizer'),
            'description' => __('Select the type of product for schema.org markup.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'options' => array(
                'Product' => __('Generic Product', 'cleanseo-optimizer'),
                'Book' => __('Book', 'cleanseo-optimizer'),
                'Clothing' => __('Clothing', 'cleanseo-optimizer'),
                'Electronics' => __('Electronics', 'cleanseo-optimizer'),
                'Food' => __('Food', 'cleanseo-optimizer'),
                'Furniture' => __('Furniture', 'cleanseo-optimizer'),
                'Jewelry' => __('Jewelry', 'cleanseo-optimizer'),
                'Toy' => __('Toy', 'cleanseo-optimizer')
            )
        ));
        
        // Brand
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_schema_brand',
            'label' => __('Brand', 'cleanseo-optimizer'),
            'description' => __('Enter the brand name for schema.org markup.', 'cleanseo-optimizer'),
            'desc_tip' => true
        ));
        
        echo '</div>';
    }

    public function add_generate_meta_button() {
        global $post;
        
        echo '<div class="options_group">';
        echo '<p class="form-field">';
        echo '<button type="button" class="button cleanseo-generate-meta" data-product-id="' . esc_attr($post->ID) . '">' . __('Generate Meta (AI/Trends)', 'cleanseo-optimizer') . '</button>';
        echo '<span class="cleanseo-meta-loader" style="display:none;margin-left:10px;">⏳</span>';
        echo '</p>';
        echo '</div>';
    }

    public function save_product_seo_fields($post_id) {
        $fields = array(
            '_cleanseo_meta_title',
            '_cleanseo_meta_description',
            '_cleanseo_focus_keyword',
            '_cleanseo_keywords',
            '_cleanseo_gtin',
            '_cleanseo_ean',
            '_cleanseo_isbn',
            '_cleanseo_og_title',
            '_cleanseo_og_description',
            '_cleanseo_og_image',
            '_cleanseo_schema_type',
            '_cleanseo_schema_brand'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    public function add_variation_seo_fields($loop, $variation_data, $variation) {
        echo '<div class="options_group">';
        echo '<h4>' . __('SEO Settings', 'cleanseo-optimizer') . '</h4>';
        
        // Meta Title
        woocommerce_wp_text_input(array(
            'id' => '_cleanseo_variation_meta_title_' . $variation->ID,
            'label' => __('Meta Title', 'cleanseo-optimizer'),
            'description' => __('Enter a custom meta title for this variation. Leave empty to use the default.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'value' => get_post_meta($variation->ID, '_cleanseo_variation_meta_title', true)
        ));
        
        // Meta Description
        woocommerce_wp_textarea_input(array(
            'id' => '_cleanseo_variation_meta_description_' . $variation->ID,
            'label' => __('Meta Description', 'cleanseo-optimizer'),
            'description' => __('Enter a custom meta description for this variation. Leave empty to use the default.', 'cleanseo-optimizer'),
            'desc_tip' => true,
            'value' => get_post_meta($variation->ID, '_cleanseo_variation_meta_description', true)
        ));
        
        echo '</div>';
    }

    public function save_variation_seo_fields($variation_id, $i) {
        $fields = array(
            '_cleanseo_variation_meta_title',
            '_cleanseo_variation_meta_description'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field . '_' . $variation_id])) {
                update_post_meta($variation_id, $field, sanitize_text_field($_POST[$field . '_' . $variation_id]));
            }
        }
    }

    public function modify_product_title($title, $sep) {
        if (is_product()) {
            global $product;
            $meta_title = get_post_meta($product->get_id(), '_cleanseo_meta_title', true);
            if (!empty($meta_title)) {
                return $meta_title . ' ' . $sep . ' ' . get_bloginfo('name');
            }
        }
        return $title;
    }

    public function modify_product_description_heading($heading) {
        if (is_product()) {
            global $product;
            $meta_description = get_post_meta($product->get_id(), '_cleanseo_meta_description', true);
            if (!empty($meta_description)) {
                return $meta_description;
            }
        }
        return $heading;
    }

    public function handle_generate_product_meta() {
        check_ajax_referer('cleanseo_generate_meta', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('Invalid product');
        }
        
        // Pobierz dane produktu
        $product_data = array(
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
            'attributes' => $product->get_attributes()
        );
        
        // Wygeneruj meta dane
        $meta = $this->generate_product_meta($product_data);
        
        if ($meta) {
            // Zapisz wygenerowane meta dane
            update_post_meta($product_id, '_cleanseo_meta_title', $meta['title']);
            update_post_meta($product_id, '_cleanseo_meta_description', $meta['description']);
            update_post_meta($product_id, '_cleanseo_focus_keyword', $meta['focus_keyword']);
            update_post_meta($product_id, '_cleanseo_keywords', $meta['keywords']);
            
            wp_send_json_success($meta);
        } else {
            wp_send_json_error('Failed to generate meta data');
        }
    }

    private function generate_product_meta($product_data) {
        // Tutaj możesz zaimplementować generowanie meta danych
        // na podstawie danych produktu, np. używając AI lub analizy trendów
        
        $title = $product_data['name'];
        if (!empty($product_data['categories'])) {
            $title .= ' - ' . $product_data['categories'][0];
        }
        
        $description = $product_data['short_description'] ?: $product_data['description'];
        if (mb_strlen($description) > 155) {
            $description = mb_substr($description, 0, 152) . '...';
        }
        
        $keywords = array_merge($product_data['categories'], $product_data['tags']);
        $focus_keyword = !empty($product_data['categories']) ? $product_data['categories'][0] : '';
        
        return array(
            'title' => $title,
            'description' => $description,
            'focus_keyword' => $focus_keyword,
            'keywords' => implode(', ', array_unique($keywords))
        );
    }

    public function modify_product_structured_data($markup, $product) {
        // Add additional schema markup
        $markup['@type'] = 'Product';
        
        // Add brand if available
        if (taxonomy_exists('product_brand')) {
            $brands = wp_get_post_terms($product->get_id(), 'product_brand');
            if (!empty($brands)) {
                $markup['brand'] = array(
                    '@type' => 'Brand',
                    'name' => $brands[0]->name
                );
            }
        }
        
        // Add review data if available
        if ($product->get_review_count() > 0) {
            $markup['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count()
            );
        }
        
        // Add availability
        $markup['offers']['availability'] = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        
        return $markup;
    }

    public function add_product_schema_markup() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'sku' => $product->get_sku(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'offers' => array(
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => get_permalink($product->get_id())
            )
        );
        
        // Add brand if available
        if (taxonomy_exists('product_brand')) {
            $brands = wp_get_post_terms($product->get_id(), 'product_brand');
            if (!empty($brands)) {
                $schema['brand'] = array(
                    '@type' => 'Brand',
                    'name' => $brands[0]->name
                );
            }
        }
        
        // Add review data if available
        if ($product->get_review_count() > 0) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count()
            );
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
} 