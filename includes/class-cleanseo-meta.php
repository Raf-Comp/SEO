<?php
/**
 * Klasa obsługująca meta tagi i dane strukturalne
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Meta {
    private $settings;
    private $options;
    private $post_meta;

    public function __construct() {
        global $wpdb;
        $this->settings = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}seo_settings LIMIT 1");
        $this->options = get_option('cleanseo_options', array());
        $this->init_hooks();
    }

    /**
     * Załaduj globalne ustawienia SEO
     */
    private function load_settings() {
        global $wpdb;
        $this->settings = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}seo_settings LIMIT 1");
        
        if (!$this->settings) {
            // Utwórz domyślne ustawienia, jeśli nie istnieją
            $this->settings = (object) array(
                'meta_title' => get_bloginfo('name'),
                'meta_description' => get_bloginfo('description'),
                'og_image_url' => '',
                'sitemap_enabled' => 1
            );
        }
    }

    /**
     * Inicjalizacja hooków
     */
    private function init_hooks() {
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_schema_markup'), 2);
        
        // WooCommerce Integration
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_structured_data_product', array($this, 'enhance_woocommerce_product_schema'), 10, 2);
        }

        // Dodaj hooki do meta boxa noindex/nofollow
        add_action('add_meta_boxes', ['CleanSEO_Meta', 'add_noindex_metabox']);
        add_action('save_post', ['CleanSEO_Meta', 'save_noindex_metabox']);
    }

    /**
     * Wygeneruj i wyświetl meta tagi
     */
    public function output_meta_tags() {
        if (is_admin()) {
            return;
        }

        // Podstawowe informacje
        $post_id = get_the_ID();
        $meta_tags = array();

        // Sprawdź, czy jest to pojedynczy post/strona/produkt
        if (is_singular()) {
            $post = get_post($post_id);
            if (!$post) {
                return;
            }
            
            // Pobierz meta dane dla konkretnego postu
            $this->post_meta = array(
                'title' => get_post_meta($post_id, '_cleanseo_meta_title', true),
                'description' => get_post_meta($post_id, '_cleanseo_meta_description', true),
                'keywords' => get_post_meta($post_id, '_cleanseo_focus_keyword', true),
                'noindex' => get_post_meta($post_id, '_cleanseo_noindex', true),
                'nofollow' => get_post_meta($post_id, '_cleanseo_nofollow', true),
                'canonical' => get_post_meta($post_id, '_cleanseo_canonical_url', true)
            );
            
            // Tytuł - użyj niestandardowego lub domyślnego szablonu
            $title = !empty($this->post_meta['title']) ? $this->post_meta['title'] : $this->generate_title($post);
            
            // Opis - użyj niestandardowego lub generuj z treści
            $description = !empty($this->post_meta['description']) ? $this->post_meta['description'] : $this->generate_description($post);
            
            // Słowa kluczowe
            $keywords = $this->post_meta['keywords'];
            
            // URL kanoniczny
            $canonical = !empty($this->post_meta['canonical']) ? $this->post_meta['canonical'] : get_permalink($post_id);
            
            // Obraz dla Open Graph
            $og_image = $this->get_post_image($post_id);
            
            // Dane autora dla artykułów
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            
            // Daty publikacji i modyfikacji
            $pub_date = get_the_date('c', $post);
            $mod_date = get_the_modified_date('c', $post);
            
        } else {
            // Dla stron archiwów, kategorii, tagów
            if (is_home() || is_front_page()) {
                // Strona główna
                $title = $this->settings->meta_title ?: get_bloginfo('name');
                $description = $this->settings->meta_description ?: get_bloginfo('description');
                $canonical = home_url('/');
            } elseif (is_category() || is_tag() || is_tax()) {
                // Kategoria, tag lub taksonomia
                $term = get_queried_object();
                $title = single_term_title('', false) . ' - ' . get_bloginfo('name');
                $description = term_description() ?: 'Przeglądasz archiwum ' . single_term_title('', false);
                $canonical = get_term_link($term);
            } elseif (is_search()) {
                // Strona wyszukiwania
                $title = 'Wyniki wyszukiwania dla: ' . get_search_query() . ' - ' . get_bloginfo('name');
                $description = 'Wyniki wyszukiwania dla zapytania ' . get_search_query() . ' na stronie ' . get_bloginfo('name');
                $canonical = get_search_link();
            } elseif (is_author()) {
                // Strona autora
                $author = get_queried_object();
                $title = 'Wpisy autora: ' . $author->display_name . ' - ' . get_bloginfo('name');
                $description = 'Przeglądasz wpisy autora ' . $author->display_name . ' na stronie ' . get_bloginfo('name');
                $canonical = get_author_posts_url($author->ID);
            } elseif (is_date()) {
                // Archiwum dat
                if (is_day()) {
                    $title = 'Archiwum: ' . get_the_date() . ' - ' . get_bloginfo('name');
                    $description = 'Przeglądasz wpisy z dnia ' . get_the_date() . ' na stronie ' . get_bloginfo('name');
                } elseif (is_month()) {
                    $title = 'Archiwum: ' . get_the_date('F Y') . ' - ' . get_bloginfo('name');
                    $description = 'Przeglądasz wpisy z miesiąca ' . get_the_date('F Y') . ' na stronie ' . get_bloginfo('name');
                } elseif (is_year()) {
                    $title = 'Archiwum: ' . get_the_date('Y') . ' - ' . get_bloginfo('name');
                    $description = 'Przeglądasz wpisy z roku ' . get_the_date('Y') . ' na stronie ' . get_bloginfo('name');
                }
                $canonical = get_permalink();
            } else {
                // Inne strony
                $title = wp_get_document_title();
                $description = $this->settings->meta_description ?: get_bloginfo('description');
                $canonical = get_permalink();
            }
            
            // Wspólne dane dla stron niebędących pojedynczymi wpisami
            $keywords = '';
            $og_image = $this->settings->og_image_url ?: '';
            $author_name = '';
            $pub_date = '';
            $mod_date = '';
        }
        
        // Domyślne meta tagi dla niestandardowych typów postów (CPT)
        if (is_singular() && !in_array(get_post_type($post_id), ['post', 'page'])) {
            // Jeśli nie ustawiono indywidualnych meta, użyj globalnych
            if (empty($title)) {
                $title = $this->settings->meta_title ?: get_the_title($post_id);
            }
            if (empty($description)) {
                $description = $this->settings->meta_description ?: '';
            }
            if (empty($og_image)) {
                $og_image = $this->settings->og_image_url ?: '';
            }
        }
        
        // Podstawowe meta tagi
        $meta_tags[] = '<meta charset="' . get_bloginfo('charset') . '" />';
        $meta_tags[] = '<meta name="viewport" content="width=device-width, initial-scale=1" />';
        
        // Title tag
        $meta_tags[] = '<title>' . esc_html($title) . '</title>';
        
        // Meta description
        if (!empty($description)) {
            $meta_tags[] = '<meta name="description" content="' . esc_attr($description) . '" />';
        }
        
        // Meta keywords
        if (!empty($keywords)) {
            $meta_tags[] = '<meta name="keywords" content="' . esc_attr($keywords) . '" />';
        }
        
        // Canonical URL
        if (!empty($canonical)) {
            $meta_tags[] = '<link rel="canonical" href="' . esc_url($canonical) . '" />';
        }
        
        // Robots directives
        $robots = array();
        
        if (is_singular() && !empty($this->post_meta['noindex'])) {
            $robots[] = 'noindex';
        }
        
        if (is_singular() && !empty($this->post_meta['nofollow'])) {
            $robots[] = 'nofollow';
        }
        
        if (!empty($robots)) {
            $meta_tags[] = '<meta name="robots" content="' . implode(',', $robots) . '" />';
        }
        
        // Social meta
        $social = $this->get_social_meta($post_id, $title, $description, $og_image);
        
        // Open Graph tags
        $meta_tags[] = '<meta property="og:locale" content="' . get_locale() . '" />';
        $meta_tags[] = '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '" />';
        $meta_tags[] = '<meta property="og:title" content="' . esc_attr($social['og_title']) . '" />';
        if (!empty($social['og_desc'])) {
            $meta_tags[] = '<meta property="og:description" content="' . esc_attr($social['og_desc']) . '" />';
        }
        if (!empty($canonical)) {
            $meta_tags[] = '<meta property="og:url" content="' . esc_url($canonical) . '" />';
        }
        $meta_tags[] = '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />';
        if (!empty($social['og_image'])) {
            $meta_tags[] = '<meta property="og:image" content="' . esc_url($social['og_image']) . '" />';
            $meta_tags[] = '<meta property="og:image:secure_url" content="' . esc_url(str_replace('http://', 'https://', $social['og_image'])) . '" />';
        }
        
        // Article specific Open Graph tags
        if (is_singular('post')) {
            if (!empty($pub_date)) {
                $meta_tags[] = '<meta property="article:published_time" content="' . esc_attr($pub_date) . '" />';
            }
            
            if (!empty($mod_date)) {
                $meta_tags[] = '<meta property="article:modified_time" content="' . esc_attr($mod_date) . '" />';
            }
            
            if (!empty($author_name)) {
                $meta_tags[] = '<meta property="article:author" content="' . esc_attr($author_name) . '" />';
            }
            
            // Add categories and tags as article:section and article:tag
            $categories = get_the_category($post_id);
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $meta_tags[] = '<meta property="article:section" content="' . esc_attr($category->name) . '" />';
                }
            }
            
            $tags = get_the_tags($post_id);
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    $meta_tags[] = '<meta property="article:tag" content="' . esc_attr($tag->name) . '" />';
                }
            }

            // Google News/Discover tags
            $news_keywords = get_post_meta($post_id, '_cleanseo_news_keywords', true);
            if (!empty($news_keywords)) {
                $meta_tags[] = '<meta name="news_keywords" content="' . esc_attr($news_keywords) . '" />';
            }
            $standout = get_post_meta($post_id, '_cleanseo_standout', true);
            if (!empty($standout)) {
                $meta_tags[] = '<link rel="standout" href="' . esc_url($standout) . '" />';
            }
        }
        
        // Twitter Card tags
        $meta_tags[] = '<meta name="twitter:card" content="summary_large_image" />';
        $meta_tags[] = '<meta name="twitter:title" content="' . esc_attr($social['twitter_title']) . '" />';
        if (!empty($social['twitter_desc'])) {
            $meta_tags[] = '<meta name="twitter:description" content="' . esc_attr($social['twitter_desc']) . '" />';
        }
        if (!empty($social['twitter_image'])) {
            $meta_tags[] = '<meta name="twitter:image" content="' . esc_url($social['twitter_image']) . '" />';
        }
        
        // Dublin Core tags
        $meta_tags[] = '<meta name="dc.title" content="' . esc_attr($title) . '" />';
        
        if (!empty($description)) {
            $meta_tags[] = '<meta name="dc.description" content="' . esc_attr($description) . '" />';
        }
        
        if (!empty($canonical)) {
            $meta_tags[] = '<meta name="dc.relation" content="' . esc_url($canonical) . '" />';
        }
        
        if (!empty($pub_date)) {
            $meta_tags[] = '<meta name="dc.date" content="' . esc_attr($pub_date) . '" />';
        }
        
        if (!empty($author_name)) {
            $meta_tags[] = '<meta name="dc.creator" content="' . esc_attr($author_name) . '" />';
        }
        
        // LinkedIn (og:title, og:description, og:image są używane przez LinkedIn)
        // ... existing code ...
        
        // Meta tagi paginacji (rel=prev/next)
        global $paged, $wp_query;
        $paged = max(1, get_query_var('paged', 1));
        if ($paged > 1) {
            $prev_url = get_pagenum_link($paged - 1);
            $meta_tags[] = '<link rel="prev" href="' . esc_url($prev_url) . '" />';
        }
        if ($wp_query && $paged < $wp_query->max_num_pages) {
            $next_url = get_pagenum_link($paged + 1);
            $meta_tags[] = '<link rel="next" href="' . esc_url($next_url) . '" />';
        }
        
        // Meta tagi hreflang (SEO wielojęzyczne)
        $hreflangs = $this->get_hreflang_tags($post_id);
        foreach ($hreflangs as $hreflang) {
            $meta_tags[] = '<link rel="alternate" href="' . esc_url($hreflang['url']) . '" hreflang="' . esc_attr($hreflang['lang']) . '" />';
        }
        
        // Breadcrumbs schema.org (JSON-LD)
        if (!is_front_page()) {
            $breadcrumbs = $this->get_breadcrumbs();
            if (!empty($breadcrumbs)) {
                $breadcrumb_schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => array()
                );
                foreach ($breadcrumbs as $position => $breadcrumb) {
                    $breadcrumb_schema['itemListElement'][] = array(
                        '@type' => 'ListItem',
                        'position' => $position + 1,
                        'item' => array(
                            '@id' => $breadcrumb['url'],
                            'name' => $breadcrumb['text']
                        )
                    );
                }
                echo '<script type="application/ld+json">' . wp_json_encode($breadcrumb_schema) . '</script>';
            }
        }
        
        // Meta tag Google Search Console (weryfikacja)
        $gsc_verification = get_option('cleanseo_gsc_verification', '');
        if (!empty($gsc_verification)) {
            $meta_tags[] = '<meta name="google-site-verification" content="' . esc_attr($gsc_verification) . '" />';
        }
        
        // Ręczne meta tagi z panelu (ustawienie: cleanseo_custom_meta_tags)
        $custom_meta = get_option('cleanseo_custom_meta_tags', '');
        if (!empty($custom_meta)) {
            $meta_tags[] = $custom_meta;
        }
        
        // Wyświetl wszystkie tagi
        echo "\n<!-- CleanSEO Meta Tags -->\n";
        echo implode("\n", $meta_tags);
        echo "\n<!-- / CleanSEO Meta Tags -->\n";
    }
    
    /**
     * Wygeneruj i wyświetl dane strukturalne schema.org (Article, Product, Event, FAQPage, HowTo)
     */
    public function output_schema_markup() {
        if (is_admin()) {
            return;
        }
        $schema = null;
        if (is_singular('post')) {
            $schema = $this->generate_article_schema();
        } elseif (function_exists('is_product') && is_product()) {
            $schema = $this->generate_product_schema();
        } elseif (is_singular('event')) {
            $schema = $this->generate_event_schema();
        } elseif (is_singular() && $this->is_faq_page()) {
            $schema = $this->generate_faq_schema();
        } elseif (is_singular() && $this->is_howto_page()) {
            $schema = $this->generate_howto_schema();
        }
        if ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
        }
    }

    /**
     * Article schema
     */
    private function generate_article_schema() {
        global $post;
        $author_id = $post->post_author;
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'description' => $this->generate_description($post),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $author_id)
            ],
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'mainEntityOfPage' => get_permalink($post),
            'image' => $this->get_post_image($post->ID)
        ];
        return $schema;
    }

    /**
     * Product schema (WooCommerce)
     */
    private function generate_product_schema() {
        if (!function_exists('wc_get_product')) return null;
        
        global $post;
        $product = wc_get_product($post->ID);
        if (!$product) return null;

        // Podstawowe dane produktu
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'sku' => $product->get_sku(),
            'brand' => $this->get_product_brand($product),
            'offers' => $this->get_product_offers($product)
        ];

        // Dodaj identyfikatory produktu
        $identifiers = $this->get_product_identifiers($product);
        if (!empty($identifiers)) {
            $schema = array_merge($schema, $identifiers);
        }

        // Dodaj dane o kategorii
        $category = $this->get_product_category($product);
        if ($category) {
            $schema['category'] = $category;
        }

        // Dodaj dane o opiniach
        $reviews = $this->get_product_reviews($product);
        if ($reviews) {
            $schema['aggregateRating'] = $reviews;
        }

        // Dodaj dane o wariantach
        if ($product->is_type('variable')) {
            $schema['hasVariant'] = $this->get_product_variants($product);
        }

        return $schema;
    }

    /**
     * Pobierz markę produktu
     */
    private function get_product_brand($product) {
        if (taxonomy_exists('product_brand')) {
            $brands = wp_get_post_terms($product->get_id(), 'product_brand');
            if (!empty($brands)) {
                return [
                    '@type' => 'Brand',
                    'name' => $brands[0]->name
                ];
            }
        }
        return null;
    }

    /**
     * Pobierz oferty produktu
     */
    private function get_product_offers($product) {
        $offers = [
            '@type' => 'Offer',
            'priceCurrency' => get_woocommerce_currency(),
            'price' => $product->get_price(),
            'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => get_permalink($product->get_id())
        ];

        // Dodaj cenę promocyjną
        if ($product->is_on_sale()) {
            $offers['price'] = $product->get_sale_price();
            $offers['priceValidUntil'] = $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('c') : null;
        }

        // Dodaj cenę regularną
        if ($product->get_regular_price()) {
            $offers['priceSpecification'] = [
                '@type' => 'PriceSpecification',
                'price' => $product->get_regular_price(),
                'priceCurrency' => get_woocommerce_currency()
            ];
        }

        return $offers;
    }

    /**
     * Pobierz identyfikatory produktu
     */
    private function get_product_identifiers($product) {
        $identifiers = [];

        // SKU
        if ($product->get_sku()) {
            $identifiers['sku'] = $product->get_sku();
            $identifiers['mpn'] = $product->get_sku();
        }

        // GTIN
        $gtin = get_post_meta($product->get_id(), '_cleanseo_gtin', true);
        if ($gtin) {
            $identifiers['gtin'] = $gtin;
        }

        // EAN
        $ean = get_post_meta($product->get_id(), '_cleanseo_ean', true);
        if ($ean) {
            $identifiers['ean'] = $ean;
        }

        // ISBN
        $isbn = get_post_meta($product->get_id(), '_cleanseo_isbn', true);
        if ($isbn) {
            $identifiers['isbn'] = $isbn;
        }

        return $identifiers;
    }

    /**
     * Pobierz kategorię produktu
     */
    private function get_product_category($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        if (!empty($categories)) {
            return $categories[0]->name;
        }
        return null;
    }

    /**
     * Pobierz opinie o produkcie
     */
    private function get_product_reviews($product) {
        if ($product->get_review_count() > 0) {
            return [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }
        return null;
    }

    /**
     * Pobierz warianty produktu
     */
    private function get_product_variants($product) {
        $variants = [];
        $available_variations = $product->get_available_variations();

        foreach ($available_variations as $variation) {
            $variant = [
                '@type' => 'Product',
                'name' => $variation['variation_description'],
                'sku' => $variation['sku'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => $variation['display_price'],
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability' => $variation['is_in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
                ]
            ];

            // Dodaj atrybuty wariantu
            if (!empty($variation['attributes'])) {
                $variant['additionalProperty'] = [];
                foreach ($variation['attributes'] as $attribute => $value) {
                    $variant['additionalProperty'][] = [
                        '@type' => 'PropertyValue',
                        'name' => wc_attribute_label(str_replace('attribute_', '', $attribute)),
                        'value' => $value
                    ];
                }
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Rozszerz schemat produktu WooCommerce
     */
    public function enhance_woocommerce_product_schema($markup, $product) {
        // Dodaj markę
        $brand = $this->get_product_brand($product);
        if ($brand) {
            $markup['brand'] = $brand;
        }

        // Dodaj identyfikatory
        $identifiers = $this->get_product_identifiers($product);
        if (!empty($identifiers)) {
            $markup = array_merge($markup, $identifiers);
        }

        // Dodaj kategorię
        $category = $this->get_product_category($product);
        if ($category) {
            $markup['category'] = $category;
        }

        // Dodaj opinie
        $reviews = $this->get_product_reviews($product);
        if ($reviews) {
            $markup['aggregateRating'] = $reviews;
        }

        // Dodaj warianty
        if ($product->is_type('variable')) {
            $markup['hasVariant'] = $this->get_product_variants($product);
        }

        return $markup;
    }

    /**
     * Pobierz meta dane produktu
     */
    private function get_product_meta($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return [
                'title' => '',
                'description' => '',
                'keywords' => ''
            ];
        }

        // Pobierz niestandardowe meta dane
        $meta_title = get_post_meta($product_id, '_cleanseo_meta_title', true);
        $meta_description = get_post_meta($product_id, '_cleanseo_meta_description', true);
        $meta_keywords = get_post_meta($product_id, '_cleanseo_keywords', true);

        // Jeśli nie ma niestandardowych meta danych, użyj domyślnych
        if (empty($meta_title)) {
            $meta_title = $product->get_name();
        }

        if (empty($meta_description)) {
            $meta_description = $product->get_short_description() ?: $product->get_description();
            // Skróć opis jeśli jest za długi
            if (mb_strlen($meta_description) > 155) {
                $meta_description = mb_substr($meta_description, 0, 152) . '...';
            }
        }

        if (empty($meta_keywords)) {
            // Generuj słowa kluczowe z kategorii i tagów
            $keywords = [];
            $categories = wp_get_post_terms($product_id, 'product_cat');
            foreach ($categories as $category) {
                $keywords[] = $category->name;
            }
            $tags = wp_get_post_terms($product_id, 'product_tag');
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
            $meta_keywords = implode(', ', array_unique($keywords));
        }

        return [
            'title' => $meta_title,
            'description' => $meta_description,
            'keywords' => $meta_keywords
        ];
    }

    /**
     * Zapisz meta dane produktu
     */
    private function save_product_meta($product_id, $meta_data) {
        if (!current_user_can('edit_post', $product_id)) {
            return false;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        // Sanityzacja danych
        $meta_title = sanitize_text_field($meta_data['title'] ?? '');
        $meta_description = sanitize_textarea_field($meta_data['description'] ?? '');
        $meta_keywords = sanitize_text_field($meta_data['keywords'] ?? '');

       // Zapisz meta dane
        update_post_meta($product_id, '_cleanseo_meta_title', $meta_title);
        update_post_meta($product_id, '_cleanseo_meta_description', $meta_description);
        update_post_meta($product_id, '_cleanseo_keywords', $meta_keywords);

        return true;
    }

    /**
     * Event schema
     */
    private function generate_event_schema() {
        global $post;
        $start = get_post_meta($post->ID, '_event_start', true);
        $end = get_post_meta($post->ID, '_event_end', true);
        $location = get_post_meta($post->ID, '_event_location', true);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => get_the_title($post),
            'startDate' => $start,
            'endDate' => $end,
            'location' => [
                '@type' => 'Place',
                'name' => $location
            ],
            'description' => $this->generate_description($post),
            'url' => get_permalink($post)
        ];
        return $schema;
    }

    /**
     * FAQPage schema
     */
    private function generate_faq_schema() {
        global $post;
        $faqs = get_post_meta($post->ID, '_cleanseo_faq', true);
        if (empty($faqs) || !is_array($faqs)) return null;
        $mainEntity = [];
        foreach ($faqs as $faq) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity
        ];
        return $schema;
    }

    /**
     * HowTo schema
     */
    private function generate_howto_schema() {
        global $post;
        $steps = get_post_meta($post->ID, '_cleanseo_howto_steps', true);
        if (empty($steps) || !is_array($steps)) return null;
        $howtoSteps = [];
        foreach ($steps as $step) {
            $howtoSteps[] = [
                '@type' => 'HowToStep',
                'name' => $step['title'],
                'text' => $step['content']
            ];
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => get_the_title($post),
            'step' => $howtoSteps
        ];
        return $schema;
    }

    /**
     * Sprawdź, czy post to FAQ
     */
    private function is_faq_page() {
        global $post;
        $faqs = get_post_meta($post->ID, '_cleanseo_faq', true);
        return !empty($faqs) && is_array($faqs);
    }

    /**
     * Sprawdź, czy post to HowTo
     */
    private function is_howto_page() {
        global $post;
        $steps = get_post_meta($post->ID, '_cleanseo_howto_steps', true);
        return !empty($steps) && is_array($steps);
    }
    
    /**
     * Pobierz obrazek posta dla Open Graph
     */
    private function get_post_image($post_id) {
        // Sprawdź, czy jest ustawiony niestandardowy obraz OG
        $custom_og_image = get_post_meta($post_id, '_cleanseo_og_image', true);
        if (!empty($custom_og_image)) {
            return $custom_og_image;
        }
        
        // Sprawdź, czy post ma obrazek wyróżniający
        if (has_post_thumbnail($post_id)) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
            if ($thumbnail) {
                return $thumbnail[0];
            }
        }
        
        // Domyślny obraz OG z ustawień
        if (!empty($this->settings->og_image_url)) {
            return $this->settings->og_image_url;
        }
        
        // Pierwszy obraz z treści
        $post = get_post($post_id);
        preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_content, $matches);
        
        if (isset($matches[1][0])) {
            return $matches[1][0];
        }
        
        // Brak obrazu
        return '';
    }
    
    /**
     * Generuj tytuł dla posta na podstawie szablonu
     */
    private function generate_title($post) {
        $title = $this->settings->meta_title;
        
        if (empty($title)) {
            return get_the_title($post->ID) . ' - ' . get_bloginfo('name');
        }
        
        // Zastąp zmienne w szablonie
        $title = str_replace('%title%', get_the_title($post->ID), $title);
        $title = str_replace('%sitename%', get_bloginfo('name'), $title);
        
        // Kategoria
        $categories = get_the_category($post->ID);
        $category_name = !empty($categories) ? $categories[0]->name : '';
        $title = str_replace('%category%', $category_name, $title);
        
        // Autor
        $author = get_the_author_meta('display_name', $post->post_author);
        $title = str_replace('%author%', $author, $title);
        
        return $title;
    }
    
    /**
     * Generuj opis dla posta
     */
    private function generate_description($post) {
        $description = $this->settings->meta_description;
        
        if (!empty($description)) {
            // Zastąp zmienne w szablonie
            $description = str_replace('%title%', get_the_title($post->ID), $description);
            $description = str_replace('%sitename%', get_bloginfo('name'), $description);
            
            // Kategoria
            $categories = get_the_category($post->ID);
            $category_name = !empty($categories) ? $categories[0]->name : '';
            $description = str_replace('%category%', $category_name, $description);
            
            // Autor
            $author = get_the_author_meta('display_name', $post->post_author);
            $description = str_replace('%author%', $author, $description);
        } else {
            // Generuj opis z treści
            $excerpt = get_the_excerpt($post->ID);
            
            if (!empty($excerpt)) {
                $description = $excerpt;
            } else {
                // Usuń HTML i skróć treść
                $content = wp_strip_all_tags($post->post_content);
                $content = preg_replace('/\s+/', ' ', $content);
                $description = mb_substr($content, 0, 155);
                
                if (mb_strlen($content) > 155) {
                    $description .= '...';
                }
            }
        }
        
        return $description;
    }
    
    /**
     * Pobierz główną kategorię posta
     */
    private function get_post_primary_category($post_id) {
        $categories = get_the_category($post_id);
        
        // Sprawdź czy wtyczka Yoast SEO jest używana do określenia głównej kategorii
        if (function_exists('yoast_get_primary_term_id')) {
            $primary_term_id = yoast_get_primary_term_id('category', $post_id);
            
            if ($primary_term_id) {
                $primary_term = get_term($primary_term_id, 'category');
                if (!is_wp_error($primary_term)) {
                    return $primary_term->name;
                }
            }
        }
        
        // Użyj pierwszej kategorii, jeśli nie znaleziono głównej
        if (!empty($categories)) {
            return $categories[0]->name;
        }
        
        return '';
    }
    
    /**
     * Pobierz okruszki
     */
    private function get_breadcrumbs() {
        $breadcrumbs = array();
        
        // Dodaj stronę główną
        $breadcrumbs[] = array(
            'text' => 'Strona główna',
            'url' => home_url('/')
        );
        
        if (is_category() || is_single()) {
            // Dla kategorii lub pojedynczego wpisu
            if (is_category()) {
                $cat = get_category(get_query_var('cat'));
                $breadcrumbs[] = array(
                    'text' => $cat->name,
                    'url' => get_category_link($cat->term_id)
                );
            } elseif (is_single()) {
                $categories = get_the_category();
                
                if (!empty($categories)) {
                    $breadcrumbs[] = array(
                        'text' => $categories[0]->name,
                        'url' => get_category_link($categories[0]->term_id)
                    );
                }
                
                $breadcrumbs[] = array(
                    'text' => get_the_title(),
                    'url' => get_permalink()
                );
            }
        } elseif (is_page()) {
            // Dla stron
            $post = get_post();
            
            if ($post->post_parent) {
                $parent_id = $post->post_parent;
                $parent_breadcrumbs = array();
                
                while ($parent_id) {
                    $page = get_post($parent_id);
                    $parent_breadcrumbs[] = array(
                        'text' => get_the_title($page->ID),
                        'url' => get_permalink($page->ID)
                    );
                    $parent_id = $page->post_parent;
                }
                
                $parent_breadcrumbs = array_reverse($parent_breadcrumbs);
                
                foreach ($parent_breadcrumbs as $crumb) {
                    $breadcrumbs[] = $crumb;
                }
            }
            
            $breadcrumbs[] = array(
                'text' => get_the_title(),
                'url' => get_permalink()
            );
        } elseif (is_tag()) {
            // Dla stron tagów
            $breadcrumbs[] = array(
                'text' => 'Tag: ' . single_tag_title('', false),
                'url' => get_tag_link(get_queried_object_id())
            );
        } elseif (is_author()) {
           // Dla stron autorów
           $breadcrumbs[] = array(
            'text' => 'Autor: ' . get_the_author(),
            'url' => get_author_posts_url(get_the_author_meta('ID'))
        );
    } elseif (is_search()) {
        // Dla stron wyników wyszukiwania
        $breadcrumbs[] = array(
            'text' => 'Wyniki wyszukiwania dla: ' . get_search_query(),
            'url' => get_search_link()
        );
    } elseif (is_year()) {
        // Dla archiwum rocznego
        $breadcrumbs[] = array(
            'text' => get_the_time('Y'),
            'url' => get_year_link(get_the_time('Y'))
        );
    } elseif (is_month()) {
        // Dla archiwum miesięcznego
        $breadcrumbs[] = array(
            'text' => get_the_time('Y'),
            'url' => get_year_link(get_the_time('Y'))
        );
        $breadcrumbs[] = array(
            'text' => get_the_time('F'),
            'url' => get_month_link(get_the_time('Y'), get_the_time('m'))
        );
    } elseif (is_day()) {
        // Dla archiwum dziennego
        $breadcrumbs[] = array(
            'text' => get_the_time('Y'),
            'url' => get_year_link(get_the_time('Y'))
        );
        $breadcrumbs[] = array(
            'text' => get_the_time('F'),
            'url' => get_month_link(get_the_time('Y'), get_the_time('m'))
        );
        $breadcrumbs[] = array(
            'text' => get_the_time('j'),
            'url' => get_day_link(get_the_time('Y'), get_the_time('m'), get_the_time('j'))
        );
    }
    
    return $breadcrumbs;
}

    /**
     * Pobierz dane do social media (Open Graph, Twitter, LinkedIn)
     */
    private function get_social_meta($post_id, $title, $description, $image) {
        // Per post
        $og_title = get_post_meta($post_id, '_cleanseo_og_title', true);
        $og_desc = get_post_meta($post_id, '_cleanseo_og_desc', true);
        $og_image = get_post_meta($post_id, '_cleanseo_og_image', true);
        $twitter_title = get_post_meta($post_id, '_cleanseo_twitter_title', true);
        $twitter_desc = get_post_meta($post_id, '_cleanseo_twitter_desc', true);
        $twitter_image = get_post_meta($post_id, '_cleanseo_twitter_image', true);
        $linkedin_title = get_post_meta($post_id, '_cleanseo_linkedin_title', true);
        $linkedin_desc = get_post_meta($post_id, '_cleanseo_linkedin_desc', true);
        $linkedin_image = get_post_meta($post_id, '_cleanseo_linkedin_image', true);
        // Global fallback
        $global_og_image = !empty($this->settings->og_image_url) ? $this->settings->og_image_url : $image;
        return [
            'og_title' => $og_title ?: $title,
            'og_desc' => $og_desc ?: $description,
            'og_image' => $og_image ?: $global_og_image,
            'twitter_title' => $twitter_title ?: $og_title ?: $title,
            'twitter_desc' => $twitter_desc ?: $og_desc ?: $description,
            'twitter_image' => $twitter_image ?: $og_image ?: $global_og_image,
            'linkedin_title' => $linkedin_title ?: $og_title ?: $title,
            'linkedin_desc' => $linkedin_desc ?: $og_desc ?: $description,
            'linkedin_image' => $linkedin_image ?: $og_image ?: $global_og_image
        ];
    }

    /**
     * Dodaj meta box noindex/nofollow do edycji postów i stron
     */
    public static function add_noindex_metabox() {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $pt) {
            add_meta_box(
                'cleanseo_noindex_box',
                __('Ustawienia indeksowania (SEO)', 'cleanseo-optimizer'),
                [self::class, 'render_noindex_metabox'],
                $pt,
                'side',
                'default'
            );
        }
    }

    public static function render_noindex_metabox($post) {
        $noindex = get_post_meta($post->ID, '_cleanseo_noindex', true);
        $nofollow = get_post_meta($post->ID, '_cleanseo_nofollow', true);
        wp_nonce_field('cleanseo_noindex_box', 'cleanseo_noindex_box_nonce');
        echo '<p><label><input type="checkbox" name="cleanseo_noindex" value="1"' . checked($noindex, '1', false) . '> ' . __('Nie indeksuj tej strony (noindex)', 'cleanseo-optimizer') . '</label></p>';
        echo '<p><label><input type="checkbox" name="cleanseo_nofollow" value="1"' . checked($nofollow, '1', false) . '> ' . __('Nie podążaj za linkami (nofollow)', 'cleanseo-optimizer') . '</label></p>';
    }

    public static function save_noindex_metabox($post_id) {
        if (!isset($_POST['cleanseo_noindex_box_nonce']) || !wp_verify_nonce($_POST['cleanseo_noindex_box_nonce'], 'cleanseo_noindex_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_cleanseo_noindex', isset($_POST['cleanseo_noindex']) ? '1' : '');
        update_post_meta($post_id, '_cleanseo_nofollow', isset($_POST['cleanseo_nofollow']) ? '1' : '');
    }

    /**
     * Pobierz hreflang dla aktualnej strony (WPML/Polylang)
     */
    private function get_hreflang_tags($post_id) {
        $hreflangs = array();
        // WPML
        if (function_exists('icl_get_languages')) {
            $langs = icl_get_languages('skip_missing=0');
            if (!empty($langs) && is_array($langs)) {
                foreach ($langs as $lang) {
                    $hreflangs[] = array(
                        'lang' => $lang['language_code'],
                        'url' => $lang['url']
                    );
                }
            }
        }
        // Polylang
        elseif (function_exists('pll_the_languages')) {
            $langs = pll_the_languages(array('raw' => 1));
            if (!empty($langs) && is_array($langs)) {
                foreach ($langs as $lang) {
                    $hreflangs[] = array(
                        'lang' => $lang['slug'],
                        'url' => $lang['url']
                    );
                }
            }
        }
        return $hreflangs;
    }

    /**
     * Wyświetl breadcrumbs na stronie (HTML)
     */
    public static function render_breadcrumbs() {
        $instance = new self();
        $breadcrumbs = $instance->get_breadcrumbs();
        if (empty($breadcrumbs)) return;
        echo '<nav class="cleanseo-breadcrumbs" aria-label="Breadcrumbs"><ol>';
        foreach ($breadcrumbs as $i => $crumb) {
            if ($i < count($breadcrumbs) - 1) {
                echo '<li><a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['text']) . '</a></li>';
            } else {
                echo '<li class="current">' . esc_html($crumb['text']) . '</li>';
            }
        }
        echo '</ol></nav>';
    }
}