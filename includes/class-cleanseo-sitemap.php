<?php
/**
 * Klasa odpowiedzialna za generowanie mapy strony XML
 *
 * @package CleanSEO_Optimizer
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_Sitemap {
    private $settings;
    private $excluded_post_types;
    private $excluded_taxonomies;
    private $include_images;
    private $include_video;
    private $include_news;
    private $max_urls_per_sitemap = 50000;
    private $sitemap_name = 'cleanseo-sitemap';
    private $cache_time = 3600; // 1 godzina
    private $cache_group = 'cleanseo_sitemap';
    private $default_priorities = array(
        'post' => 0.8,
        'page' => 0.7,
        'product' => 0.8,
        'category' => 0.6,
        'post_tag' => 0.5,
        'product_cat' => 0.6,
        'product_tag' => 0.5
    );
    private $default_changefreq = array(
        'post' => 'weekly',
        'page' => 'monthly',
        'product' => 'weekly',
        'category' => 'weekly',
        'post_tag' => 'weekly',
        'product_cat' => 'weekly',
        'product_tag' => 'weekly'
    );

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        $this->load_settings();
        add_action('init', array($this, 'init_hooks'));
    }

    /**
     * Ładuje ustawienia mapy strony
     */
    private function load_settings() {
        global $wpdb;
        // Najpierw sprawdzamy, czy tabela istnieje
        $table_name = $wpdb->prefix . 'seo_settings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $this->settings = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1");
        } else {
            // Jeśli tabela nie istnieje, utworzymy tymczasowy obiekt z domyślnymi ustawieniami
            $this->settings = (object) array(
                'sitemap_enabled' => 1,
                'sitemap_robots' => 1,
                'sitemap_images' => 1,
                'sitemap_video' => 0,
                'sitemap_news' => 0
            );
        }
        
        // Ładujemy ustawienia z opcji WordPressa
        $this->excluded_post_types = get_option('cleanseo_excluded_post_types', array());
        $this->excluded_taxonomies = get_option('cleanseo_excluded_taxonomies', array());
        $this->include_images = get_option('cleanseo_sitemap_include_images', 1);
        $this->include_video = get_option('cleanseo_sitemap_include_video', 0);
        $this->include_news = get_option('cleanseo_sitemap_include_news', 0);
    }

    /**
     * Inicjalizuje hooki WordPressa
     */
    public function init_hooks() {
        // Reguły przepisywania adresów URL
        add_rewrite_rule(
            '^' . $this->sitemap_name . '\.xml$',
            'index.php?cleanseo_sitemap=1',
            'top'
        );
        add_rewrite_rule('sitemap-index\.xml$', 'index.php?sitemap_index=1', 'top');
        add_rewrite_rule('sitemap-([a-z0-9_-]+)\.xml$', 'index.php?sitemap_type=$matches[1]', 'top');

        // Filtry i akcje
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_sitemap_request'));
        add_filter('robots_txt', array($this, 'add_sitemap_to_robots'), 10, 2);
        add_action('admin_post_cleanseo_save_robots', array($this, 'save_robots_txt_handler'));
        add_action('wp_ajax_cleanseo_save_sitemap_settings', array($this, 'save_sitemap_settings_ajax'));
        add_action('publish_post', array($this, 'ping_search_engines'));
        add_action('publish_page', array($this, 'ping_search_engines'));
        
        // Czyszczenie pamięci podręcznej przy aktualizacji treści
        add_action('save_post', array($this, 'clear_sitemap_cache'));
        add_action('edited_term', array($this, 'clear_sitemap_cache'));
        add_action('delete_term', array($this, 'clear_sitemap_cache'));
        add_action('cleanseo_settings_updated', array($this, 'clear_sitemap_cache'));
    }

    /**
     * Dodaje zmienne zapytania używane przez mapę strony
     *
     * @param array $vars Zmienne zapytania
     * @return array Zmodyfikowane zmienne zapytania
     */
    public function add_query_vars($vars) {
        $vars[] = 'cleanseo_sitemap';
        $vars[] = 'sitemap_index';
        $vars[] = 'sitemap_type';
        return $vars;
    }

    /**
     * Obsługuje żądania dotyczące mapy strony
     */
    public function handle_sitemap_request() {
        // Obsługa mapy strony głównej
        if (get_query_var('cleanseo_sitemap')) {
            $this->generate_sitemap();
            exit;
        }
        
        // Obsługa indeksu map stron
        if (get_query_var('sitemap_index')) {
            $this->generate_sitemap_index();
            exit;
        }
        
        // Obsługa podmap strony
        if ($type = get_query_var('sitemap_type')) {
            $this->generate_sub_sitemap($type);
            exit;
        }
    }

    /**
     * Generuje główną mapę strony
     */
    public function generate_sitemap() {
        // Sprawdź, czy mapy strony są włączone
        if (!isset($this->settings->sitemap_enabled) || !$this->settings->sitemap_enabled) {
            wp_die('Mapa strony jest wyłączona', 'Mapa strony wyłączona', array('response' => 404));
        }

        // Spróbuj najpierw pobrać z pamięci podręcznej
        $cache_key = 'main_sitemap';
        $sitemap = wp_cache_get($cache_key, $this->cache_group);
        
        if ($sitemap === false) {
            ob_start();
            $this->output_sitemap_header();
            $this->output_sitemap_content();
            $this->output_sitemap_footer();
            $sitemap = ob_get_clean();
            wp_cache_set($cache_key, $sitemap, $this->cache_group, $this->cache_time);
        }

        header('Content-Type: application/xml; charset=UTF-8');
        echo $sitemap;
        exit;
    }

    /**
     * Generuje nagłówek XML dla mapy strony
     */
    private function output_sitemap_header() {
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
            <?php if ($this->include_images): ?>
                xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
            <?php endif; ?>
            <?php if ($this->include_video): ?>
                xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
            <?php endif; ?>
            <?php if ($this->include_news): ?>
                xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
            <?php endif; ?>
            <?php if (function_exists('pll_the_languages')): ?>
                xmlns:xhtml="http://www.w3.org/1999/xhtml"
            <?php endif; ?>
        >
        <?php
    }

    /**
     * Generuje zawartość mapy strony
     */
    private function output_sitemap_content() {
        // Dodaj stronę główną
        $this->output_sitemap_url(home_url(), '1.0', 'daily');

        // Pobierz wszystkie publiczne typy postów
        $post_types = get_post_types(array('public' => true));
        $post_types = array_diff($post_types, $this->excluded_post_types);

        foreach ($post_types as $post_type) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => $this->max_urls_per_sitemap,
                'post_status' => 'publish'
            ));

            foreach ($posts as $post) {
                $priority = $this->get_priority($post_type);
                $changefreq = $this->get_changefreq($post_type);
                $lastmod = $this->get_lastmod($post);
                $images = $this->include_images ? $this->get_post_images($post->ID) : array();
                $videos = $this->include_video ? $this->get_post_videos($post->ID) : array();
                $news = $this->include_news ? $this->get_post_news($post) : '';
                $alternates = $this->get_alternate_urls($post->ID);

                $this->output_sitemap_url(
                    get_permalink($post->ID),
                    $priority,
                    $changefreq,
                    $lastmod,
                    $images,
                    $videos,
                    $news,
                    $alternates
                );
            }
        }

        // Dodaj taksonomie
        $taxonomies = get_taxonomies(array('public' => true));
        $taxonomies = array_diff($taxonomies, $this->excluded_taxonomies);

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true
            ));

            foreach ($terms as $term) {
                $priority = $this->get_priority($taxonomy);
                $changefreq = $this->get_changefreq($taxonomy);
                $lastmod = $this->get_term_lastmod($term);
                $alternates = $this->get_term_alternate_urls($term);

                $this->output_sitemap_url(
                    get_term_link($term),
                    $priority,
                    $changefreq,
                    $lastmod,
                    array(),
                    array(),
                    '',
                    $alternates
                );
            }
        }
    }

    /**
     * Generuje stopkę mapy strony
     */
    private function output_sitemap_footer() {
        echo '</urlset>';
    }

    /**
     * Generuje pojedynczy wpis URL w mapie strony
     *
     * @param string $url URL strony
     * @param string $priority Priorytet strony
     * @param string $changefreq Częstotliwość zmian
     * @param string $lastmod Data ostatniej modyfikacji
     * @param array $images Tablica obrazów
     * @param array $videos Tablica filmów
     * @param string $news Informacje dla Google News
     * @param array $alternates Alternatywne wersje językowe
     */
    private function output_sitemap_url($url, $priority, $changefreq, $lastmod = '', $images = array(), $videos = array(), $news = '', $alternates = array()) {
        // Sprawdź czy URL jest poprawny
        if (!$url || is_wp_error($url)) {
            return;
        }
        
        ?>
        <url>
            <loc><?php echo esc_url($url); ?></loc>
            <?php if ($lastmod) : ?>
                <lastmod><?php echo esc_html($lastmod); ?></lastmod>
            <?php endif; ?>
            <changefreq><?php echo esc_html($changefreq); ?></changefreq>
            <priority><?php echo esc_html($priority); ?></priority>
            <?php if (!empty($images)) :
                foreach ($images as $img) : ?>
                    <image:image>
                        <image:loc><?php echo esc_url($img['url']); ?></image:loc>
                        <?php if (!empty($img['title'])) : ?>
                            <image:title><?php echo esc_html($img['title']); ?></image:title>
                        <?php endif; ?>
                        <?php if (!empty($img['caption'])) : ?>
                            <image:caption><?php echo esc_html($img['caption']); ?></image:caption>
                        <?php endif; ?>
                    </image:image>
                <?php endforeach;
            endif; ?>
            <?php if (!empty($videos)) :
                foreach ($videos as $vid) : ?>
                    <video:video>
                        <video:content_loc><?php echo esc_url($vid['url']); ?></video:content_loc>
                        <?php if (!empty($vid['title'])) : ?>
                            <video:title><?php echo esc_html($vid['title']); ?></video:title>
                        <?php endif; ?>
                        <?php if (!empty($vid['description'])) : ?>
                            <video:description><?php echo esc_html($vid['description']); ?></video:description>
                        <?php endif; ?>
                        <?php if (!empty($vid['thumbnail'])) : ?>
                            <video:thumbnail_loc><?php echo esc_url($vid['thumbnail']); ?></video:thumbnail_loc>
                        <?php endif; ?>
                    </video:video>
                <?php endforeach;
            endif; ?>
            <?php if (!empty($news)) :
                echo $news;
            endif; ?>
            <?php if (!empty($alternates)) :
                foreach ($alternates as $lang => $alt_url) : ?>
                    <xhtml:link rel="alternate" hreflang="<?php echo esc_attr($lang); ?>" href="<?php echo esc_url($alt_url); ?>" />
                <?php endforeach;
            endif; ?>
        </url>
        <?php
    }

    /**
     * Pobiera obrazy dla danego posta
     *
     * @param int $post_id ID posta
     * @return array Tablica obrazów
     */
    private function get_post_images($post_id) {
        $images = array();
        
        // Obrazek wyróżniający
        $thumb = get_the_post_thumbnail_url($post_id, 'full');
        if ($thumb) {
            $images[] = array(
                'url' => $thumb,
                'title' => get_the_title($post_id),
                'caption' => get_the_post_thumbnail_caption($post_id)
            );
        }

        // Obrazy z treści
        $content = get_post_field('post_content', $post_id);
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $img_url = $match[1];
                $img_title = '';
                $img_caption = '';

                // Pobierz tytuł z atrybutu alt lub title
                if (preg_match('/alt=["\']([^"\']+)["\']/i', $match[0], $alt_match)) {
                    $img_title = $alt_match[1];
                } elseif (preg_match('/title=["\']([^"\']+)["\']/i', $match[0], $title_match)) {
                    $img_title = $title_match[1];
                }

                // Pobierz podpis, jeśli obraz jest w figurze z figcaption
                if (preg_match('/<figure[^>]*>.*?<img[^>]+src=["\']' . preg_quote($img_url, '/') . '["\'][^>]*>.*?<figcaption[^>]*>(.*?)<\/figcaption>.*?<\/figure>/is', $content, $caption_match)) {
                    $img_caption = $caption_match[1];
                }

                $images[] = array(
                    'url' => $img_url,
                    'title' => $img_title,
                    'caption' => $img_caption
                );
            }
        }

        // Obsługa obrazów z galerii
        if (class_exists('WP_Block_Parser') && function_exists('parse_blocks')) {
            $blocks = parse_blocks($content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/gallery' && !empty($block['attrs']['ids'])) {
                    foreach ($block['attrs']['ids'] as $attachment_id) {
                        $attachment = get_post($attachment_id);
                        if ($attachment) {
                            $images[] = array(
                                'url' => wp_get_attachment_url($attachment_id),
                                'title' => get_the_title($attachment_id),
                                'caption' => $attachment->post_excerpt
                            );
                        }
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Pobiera filmy dla danego posta
     *
     * @param int $post_id ID posta
     * @return array Tablica filmów
     */
    private function get_post_videos($post_id) {
        $videos = array();
        $content = get_post_field('post_content', $post_id);

        // Natywne tagi wideo
        if (preg_match_all('/<video[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $vid_url = $match[1];
                $vid_title = '';
                $vid_desc = '';
                $vid_thumb = '';

                // Pobierz tytuł z atrybutu title
                if (preg_match('/title=["\']([^"\']+)["\']/i', $match[0], $title_match)) {
                    $vid_title = $title_match[1];
                }

                // Pobierz opis z atrybutu data-description
                if (preg_match('/data-description=["\']([^"\']+)["\']/i', $match[0], $desc_match)) {
                    $vid_desc = $desc_match[1];
                }

                // Pobierz miniaturę z atrybutu poster
                if (preg_match('/poster=["\']([^"\']+)["\']/i', $match[0], $thumb_match)) {
                    $vid_thumb = $thumb_match[1];
                }

                $videos[] = array(
                    'url' => $vid_url,
                    'title' => $vid_title ?: get_the_title($post_id),
                    'description' => $vid_desc ?: get_the_excerpt($post_id),
                    'thumbnail' => $vid_thumb
                );
            }
        }

        // Osadzenia YouTube
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+youtube[^"\']*)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $embed_url = $match[1];
                if (preg_match('/embed\/([^"\'?&]+)/', $embed_url, $vid_id_match)) {
                    $vid_id = $vid_id_match[1];
                    $videos[] = array(
                        'url' => "https://www.youtube.com/watch?v={$vid_id}",
                        'title' => get_the_title($post_id),
                        'description' => get_the_excerpt($post_id),
                        'thumbnail' => "https://img.youtube.com/vi/{$vid_id}/maxresdefault.jpg"
                    );
                }
            }
        }

        // Osadzenia Vimeo
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+vimeo\.com\/video[^"\']*)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $embed_url = $match[1];
                if (preg_match('/video\/([0-9]+)/', $embed_url, $vid_id_match)) {
                    $vid_id = $vid_id_match[1];
                    // Dla Vimeo potrzebujemy dodatkowego zapytania API, aby uzyskać miniatury
                    $videos[] = array(
                        'url' => "https://vimeo.com/{$vid_id}",
                        'title' => get_the_title($post_id),
                        'description' => get_the_excerpt($post_id),
                        'thumbnail' => ''
                    );
                }
            }
        }

        return $videos;
    }

    /**
     * Pobiera dane dla Google News dla danego posta
     *
     * @param object $post Obiekt posta
     * @return string Dane XML dla Google News
     */
    private function get_post_news($post) {
        if ($post->post_type === 'news' || has_term('news', 'category', $post)) {
            $title = esc_html(get_the_title($post));
            $pub_date = esc_html(get_the_date('c', $post));
            $sitename = esc_html(get_bloginfo('name'));
            $keywords = get_post_meta($post->ID, '_cleanseo_news_keywords', true);
            
            $news = "<news:news>";
            $news .= "<news:publication>";
            $news .= "<news:name>{$sitename}</news:name>";
            $news .= "<news:language>" . esc_html(get_locale()) . "</news:language>";
            $news .= "</news:publication>";
            $news .= "<news:title>{$title}</news:title>";
            $news .= "<news:publication_date>{$pub_date}</news:publication_date>";
            if (!empty($keywords)) {
                $news .= "<news:keywords>" . esc_html($keywords) . "</news:keywords>";
            }
            $news .= "</news:news>";
            
            return $news;
        }
        return '';
    }

    /**
     * Pobiera alternatywne wersje językowe dla posta
     *
     * @param int $post_id ID posta
     * @return array Tablica URL alternatywnych wersji
     */
    private function get_alternate_urls($post_id) {
        $alternates = array();
        
        // Integracja z Polylang
        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($post_id);
            foreach ($translations as $lang => $translated_id) {
                $alternates[$lang] = get_permalink($translated_id);
            }
        }
        // Integracja z WPML
        elseif (function_exists('icl_object_id') && defined('ICL_LANGUAGE_CODE')) {
            global $sitepress;
            $languages = apply_filters('wpml_active_languages', null);
            $current_lang = ICL_LANGUAGE_CODE;
            
            if ($languages) {
                foreach ($languages as $lang) {
                    $sitepress->switch_lang($lang['code'], true);
                    $translated_id = apply_filters('wpml_object_id', $post_id, get_post_type($post_id), true, $lang['code']);
                    if ($translated_id) {
                        $alternates[$lang['code']] = get_permalink($translated_id);
                    }
                }
                $sitepress->switch_lang($current_lang, true);
            }
        }
        
        return $alternates;
    }

    /**
     * Pobiera alternatywne wersje językowe dla taksonomii
     *
     * @param object $term Obiekt terminu
     * @return array Tablica URL alternatywnych wersji
     */
    private function get_term_alternate_urls($term) {
        $alternates = array();
        
        // Integracja z Polylang
        if (function_exists('pll_get_term_translations')) {
            $translations = pll_get_term_translations($term->term_id);
            foreach ($translations as $lang => $translated_id) {
                $alternates[$lang] = get_term_link((int)$translated_id, $term->taxonomy);
            }
        }
        // Integracja z WPML
        elseif (function_exists('icl_object_id') && defined('ICL_LANGUAGE_CODE')) {
            global $sitepress;
            $languages = apply_filters('wpml_active_languages', null);
            $current_lang = ICL_LANGUAGE_CODE;
            
            if ($languages) {
                foreach ($languages as $lang) {
                    $sitepress->switch_lang($lang['code'], true);
                    $translated_id = apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, true, $lang['code']);
                    if ($translated_id) {
                        $alternates[$lang['code']] = get_term_link((int)$translated_id, $term->taxonomy);
                    }
                }
                $sitepress->switch_lang($current_lang, true);
            }
        }
        
        return $alternates;
    }

    /**
     * Pobiera priorytet dla danego typu
     *
     * @param string $type Typ treści
     * @return string Wartość priorytetu
     */
    private function get_priority($type) {
        return isset($this->default_priorities[$type]) ? $this->default_priorities[$type] : '0.5';
    }

    /**
     * Pobiera częstotliwość zmian dla danego typu
     *
     * @param string $type Typ treści
     * @return string Częstotliwość zmian
     */
    private function get_changefreq($type) {
        return isset($this->default_changefreq[$type]) ? $this->default_changefreq[$type] : 'weekly';
    }

    /**
     * Pobiera datę ostatniej modyfikacji dla posta
     *
     * @param object $post Obiekt posta
     * @return string Data w formacie ISO 8601
     */
    private function get_lastmod($post) {
        $lastmod = get_post_modified_time('c', true, $post);
        
        // Sprawdź komentarze
        $comments = get_comments(array(
            'post_id' => $post->ID,
            'status' => 'approve',
            'number' => 1,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC'
        ));
        
        if (!empty($comments)) {
            $comment_date = get_comment_date('c', $comments[0]->comment_ID);
            if ($comment_date > $lastmod) {
                $lastmod = $comment_date;
            }
        }
        
        return $lastmod;
    }

    /**
     * Pobiera datę ostatniej modyfikacji dla terminu taksonomii
     *
     * @param object $term Obiekt terminu
     * @return string Data w formacie ISO 8601
     */
    private function get_term_lastmod($term) {
        $lastmod = '';
        
        // Pobierz najnowszy post w tym terminie
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id
                )
            )
        ));
        
        if (!empty($posts)) {
            $lastmod = get_post_modified_time('c', true, $posts[0]);
        }
        
        return $lastmod;
    }

    /**
     * Czyści pamięć podręczną mapy strony
     */
    public function clear_sitemap_cache() {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            // Alternatywna metoda dla WP bez funkcji wp_cache_flush_group
            wp_cache_delete('main_sitemap', $this->cache_group);
            wp_cache_delete('sitemap_index', $this->cache_group);
            
            // Czyści też cache dla podmap
            $post_types = get_post_types(array('public' => true));
            foreach ($post_types as $type) {
                wp_cache_delete("sub_sitemap_{$type}", $this->cache_group);
            }
            
            $taxonomies = get_taxonomies(array('public' => true));
            foreach ($taxonomies as $tax) {
                wp_cache_delete("sub_sitemap_{$tax}", $this->cache_group);
            }
        }
    }

    /**
     * Generuje indeks map stron
     */
    private function generate_sitemap_index() {
        header('Content-Type: application/xml; charset=UTF-8');
        
        // Spróbuj najpierw pobrać z pamięci podręcznej
        $cache_key = 'sitemap_index';
        $sitemap_index = wp_cache_get($cache_key, $this->cache_group);
        
        if ($sitemap_index === false) {
            ob_start();
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            ?>
            <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <?php
                // Dodaj główną mapę strony
                echo "<sitemap><loc>" . esc_url(home_url($this->sitemap_name . '.xml')) . "</loc></sitemap>\n";
            // Dodaj mapy dla typów postów
            $post_types = get_post_types(array('public' => true));
            $post_types = array_diff($post_types, $this->excluded_post_types);
            
            foreach ($post_types as $type) {
                $count = wp_count_posts($type)->publish;
                if ($count > $this->max_urls_per_sitemap) {
                    $pages = ceil($count / $this->max_urls_per_sitemap);
                    for ($i = 1; $i <= $pages; $i++) {
                        echo "<sitemap><loc>" . esc_url(home_url("sitemap-{$type}-{$i}.xml")) . "</loc></sitemap>\n";
                    }
                } else {
                    echo "<sitemap><loc>" . esc_url(home_url("sitemap-{$type}.xml")) . "</loc></sitemap>\n";
                }
            }
            
            // Dodaj mapy dla taksonomii
            $taxonomies = get_taxonomies(array('public' => true));
            $taxonomies = array_diff($taxonomies, $this->excluded_taxonomies);
            
            foreach ($taxonomies as $tax) {
                $terms_count = wp_count_terms(array(
                    'taxonomy' => $tax,
                    'hide_empty' => true
                ));
                
                if ($terms_count > $this->max_urls_per_sitemap) {
                    $pages = ceil($terms_count / $this->max_urls_per_sitemap);
                    for ($i = 1; $i <= $pages; $i++) {
                        echo "<sitemap><loc>" . esc_url(home_url("sitemap-{$tax}-{$i}.xml")) . "</loc></sitemap>\n";
                    }
                } else {
                    echo "<sitemap><loc>" . esc_url(home_url("sitemap-{$tax}.xml")) . "</loc></sitemap>\n";
                }
            }
            
            // Dodaj mapę dla wiadomości, jeśli włączona
            if ($this->include_news) {
                echo "<sitemap><loc>" . esc_url(home_url('sitemap-news.xml')) . "</loc></sitemap>\n";
            }
            ?>
        </sitemapindex>
        <?php
        $sitemap_index = ob_get_clean();
        wp_cache_set($cache_key, $sitemap_index, $this->cache_group, $this->cache_time);
    }
    
    echo $sitemap_index;
    exit;
}

/**
 * Generuje podmapę strony dla określonego typu
 *
 * @param string $type Typ podmapy (post_type lub taksonomia)
 */
private function generate_sub_sitemap($type) {
    header('Content-Type: application/xml; charset=UTF-8');
    
    // Spróbuj najpierw pobrać z pamięci podręcznej
    $cache_key = "sub_sitemap_{$type}";
    $sitemap = wp_cache_get($cache_key, $this->cache_group);
    
    if ($sitemap === false) {
        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
            <?php if ($this->include_images): ?>
                xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
            <?php endif; ?>
            <?php if ($this->include_video): ?>
                xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
            <?php endif; ?>
            <?php if ($this->include_news): ?>
                xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
            <?php endif; ?>
            <?php if (function_exists('pll_the_languages')): ?>
                xmlns:xhtml="http://www.w3.org/1999/xhtml"
            <?php endif; ?>
        >
        <?php
        $paged = 1;
        $original_type = $type;
        
        // Sprawdź, czy to strona paginacji
        if (preg_match('/^([a-z0-9_-]+)-(\d+)$/', $type, $matches)) {
            $type = $matches[1];
            $paged = intval($matches[2]);
        }
        
        // Obsługa typów postów
        if (post_type_exists($type)) {
            $posts = get_posts(array(
                'post_type' => $type,
                'posts_per_page' => $this->max_urls_per_sitemap,
                'paged' => $paged,
                'post_status' => 'publish',
                'orderby' => 'modified',
                'order' => 'DESC'
            ));
            
            foreach ($posts as $post) {
                $priority = $this->get_priority($type);
                $changefreq = $this->get_changefreq($type);
                $lastmod = $this->get_lastmod($post);
                $images = $this->include_images ? $this->get_post_images($post->ID) : array();
                $videos = $this->include_video ? $this->get_post_videos($post->ID) : array();
                $news = $this->include_news ? $this->get_post_news($post) : '';
                $alternates = $this->get_alternate_urls($post->ID);
                
                $this->output_sitemap_url(
                    get_permalink($post->ID),
                    $priority,
                    $changefreq,
                    $lastmod,
                    $images,
                    $videos,
                    $news,
                    $alternates
                );
            }
        } 
        // Obsługa taksonomii
        elseif (taxonomy_exists($type)) {
            $terms = get_terms(array(
                'taxonomy' => $type,
                'hide_empty' => true,
                'number' => $this->max_urls_per_sitemap,
                'offset' => ($paged - 1) * $this->max_urls_per_sitemap
            ));
            
            foreach ($terms as $term) {
                $priority = $this->get_priority($type);
                $changefreq = $this->get_changefreq($type);
                $lastmod = $this->get_term_lastmod($term);
                $alternates = $this->get_term_alternate_urls($term);
                
                $this->output_sitemap_url(
                    get_term_link($term),
                    $priority,
                    $changefreq,
                    $lastmod,
                    array(),
                    array(),
                    '',
                    $alternates
                );
            }
        }
        // Obsługa wiadomości
        elseif ($type === 'news' && $this->include_news) {
            $posts = get_posts(array(
                'post_type' => 'any',
                'posts_per_page' => $this->max_urls_per_sitemap,
                'date_query' => array(
                    'after' => '2 days ago' // Wiadomości nie starsze niż 2 dni
                ),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'category',
                        'field' => 'slug',
                        'terms' => 'news'
                    )
                )
            ));
            
            foreach ($posts as $post) {
                $priority = '1.0'; // Najwyższy priorytet dla wiadomości
                $changefreq = 'hourly';
                $lastmod = $this->get_lastmod($post);
                $images = $this->include_images ? $this->get_post_images($post->ID) : array();
                $videos = $this->include_video ? $this->get_post_videos($post->ID) : array();
                $news = $this->get_post_news($post);
                $alternates = $this->get_alternate_urls($post->ID);
                
                $this->output_sitemap_url(
                    get_permalink($post->ID),
                    $priority,
                    $changefreq,
                    $lastmod,
                    $images,
                    $videos,
                    $news,
                    $alternates
                );
            }
        }
        ?>
        </urlset>
        <?php
        $sitemap = ob_get_clean();
        wp_cache_set($cache_key, $sitemap, $this->cache_group, $this->cache_time);
    }
    
    echo $sitemap;
    exit;
}

/**
 * Dodaje odniesienie do mapy strony w robots.txt
 *
 * @param string $output Obecna zawartość robots.txt
 * @param bool $public Czy witryna jest publiczna
 * @return string Zmodyfikowana zawartość robots.txt
 */
public function add_sitemap_to_robots($output, $public) {
    if ($public && isset($this->settings->sitemap_robots) && $this->settings->sitemap_robots) {
        // Sprawdź, czy sitemap już istnieje w robots.txt
        if (strpos($output, 'Sitemap:') === false) {
            $output .= "\nSitemap: " . home_url($this->sitemap_name . '.xml');
        }
    }
    return $output;
}

/**
 * Aktualizuje reguły przepisywania adresów URL i usuwa stare wpisy
 */
public function flush_rules() {
    add_rewrite_rule(
        '^' . $this->sitemap_name . '\.xml$',
        'index.php?cleanseo_sitemap=1',
        'top'
    );
    add_rewrite_rule('sitemap-index\.xml$', 'index.php?sitemap_index=1', 'top');
    add_rewrite_rule('sitemap-([a-z0-9_-]+)\.xml$', 'index.php?sitemap_type=$matches[1]', 'top');
    
    flush_rewrite_rules();
}

/**
 * Wysyła ping do wyszukiwarek o aktualizacji mapy strony
 */
public function ping_search_engines() {
    if (!isset($this->settings->sitemap_enabled) || !$this->settings->sitemap_enabled) {
        return;
    }
    
    $sitemap_url = esc_url(home_url($this->sitemap_name . '.xml'));
    $google_ping = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
    $bing_ping = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url);
    
    // Użyj nieblokowalnych żądań HTTP
    wp_remote_get($google_ping, array('timeout' => 5, 'blocking' => false));
    wp_remote_get($bing_ping, array('timeout' => 5, 'blocking' => false));
    
    // Dodanie loga
    if (class_exists('CleanSEO_Logger')) {
        $logger = new CleanSEO_Logger();
        $logger->log('sitemap_ping', sprintf(
            __('Wysłano ping do wyszukiwarek o aktualizacji mapy strony: %s', 'cleanseo-optimizer'),
            $sitemap_url
        ));
    }
}

/**
 * Pobiera wszystkie publiczne typy postów, które nie są wykluczone
 *
 * @return array Tablica obiektów typów postów
 */
public function get_post_types() {
    $post_types = get_post_types(array(
        'public' => true,
        'show_ui' => true
    ), 'objects');

    // Usuń wykluczone typy postów
    $post_types = array_diff_key($post_types, array_flip($this->excluded_post_types));

    // Dodaj dodatkowe informacje do każdego typu postu
    foreach ($post_types as $post_type) {
        $post_type->priority = $this->get_priority($post_type->name);
        $post_type->changefreq = $this->get_changefreq($post_type->name);
        $post_type->count = wp_count_posts($post_type->name)->publish;
    }

    return $post_types;
}

/**
 * Pobiera wszystkie publiczne taksonomie, które nie są wykluczone
 *
 * @return array Tablica obiektów taksonomii
 */
public function get_taxonomies() {
    $taxonomies = get_taxonomies(array(
        'public' => true,
        'show_ui' => true
    ), 'objects');

    // Usuń wykluczone taksonomie
    $taxonomies = array_diff_key($taxonomies, array_flip($this->excluded_taxonomies));

    // Dodaj dodatkowe informacje do każdej taksonomii
    foreach ($taxonomies as $taxonomy) {
        $taxonomy->priority = $this->get_priority($taxonomy->name);
        $taxonomy->changefreq = $this->get_changefreq($taxonomy->name);
        $taxonomy->count = wp_count_terms(array('taxonomy' => $taxonomy->name, 'hide_empty' => true));
    }

    return $taxonomies;
}

/**
 * Pobiera aktualną zawartość robots.txt
 *
 * @return string Zawartość robots.txt
 */
public function get_robots_txt() {
    $robots_file = ABSPATH . 'robots.txt';
    
    // Jeśli robots.txt istnieje, odczytaj jego zawartość
    if (file_exists($robots_file)) {
        $content = file_get_contents($robots_file);
    } else {
        // Domyślna zawartość robots.txt
        $content = "User-agent: *\n";
        $content .= "Disallow: /wp-admin/\n";
        $content .= "Disallow: /wp-includes/\n";
        $content .= "Allow: /wp-admin/admin-ajax.php\n";
        $content .= "Allow: /wp-admin/load-scripts.php\n";
        $content .= "Allow: /wp-admin/load-styles.php\n";
        
        // Dodaj odniesienie do mapy strony
        if (isset($this->settings->sitemap_robots) && $this->settings->sitemap_robots) {
            $content .= "\nSitemap: " . home_url($this->sitemap_name . '.xml');
        }
    }
    
    return $content;
}

/**
 * Obsługuje zapisywanie pliku robots.txt
 */
public function save_robots_txt_handler() {
    // Sprawdź nonce
    if (!isset($_POST['cleanseo_robots_nonce']) || !wp_verify_nonce($_POST['cleanseo_robots_nonce'], 'cleanseo_save_robots')) {
        wp_die(__('Nieprawidłowy token bezpieczeństwa', 'cleanseo-optimizer'));
    }
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_die(__('Nie masz wystarczających uprawnień, aby wykonać tę akcję.', 'cleanseo-optimizer'));
    }
    
    // Pobierz zawartość
    $content = isset($_POST['robots_content']) ? sanitize_textarea_field($_POST['robots_content']) : '';
    
    // Zapisz plik
    $success = $this->save_robots_txt($content);
    
    // Przekieruj z powrotem z odpowiednim komunikatem
    if ($success) {
        wp_redirect(add_query_arg('updated', '1', wp_get_referer()));
    } else {
        wp_redirect(add_query_arg('error', '1', wp_get_referer()));
    }
    exit;
}

/**
 * Zapisuje zawartość robots.txt
 *
 * @param string $content Zawartość do zapisania
 * @return bool Prawda w przypadku powodzenia, fałsz w przypadku niepowodzenia
 */
public function save_robots_txt($content) {
    $robots_file = ABSPATH . 'robots.txt';
    
    // Upewnij się, że zawartość ma odniesienie do mapy strony
    if (isset($this->settings->sitemap_robots) && $this->settings->sitemap_robots && strpos($content, 'Sitemap:') === false) {
        $content .= "\nSitemap: " . home_url($this->sitemap_name . '.xml');
    }
    
    // Spróbuj zapisać plik
    $result = @file_put_contents($robots_file, $content);
    
    if ($result === false) {
        // Jeśli bezpośrednie zapisywanie zawiedzie, spróbuj użyć systemu plików WordPress
        $wp_filesystem = $this->get_wp_filesystem();
        if ($wp_filesystem) {
            $result = $wp_filesystem->put_contents($robots_file, $content, FS_CHMOD_FILE);
        }
    }
    
    return $result !== false;
}

/**
 * Obsługuje zapisywanie ustawień mapy strony przez AJAX
 */
public function save_sitemap_settings_ajax() {
    // Sprawdź nonce
    check_ajax_referer('cleanseo_sitemap_settings', 'nonce');
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => __('Nie masz wystarczających uprawnień, aby wykonać tę akcję.', 'cleanseo-optimizer')
        ));
    }
    
    // Pobierz i sanityzuj dane
    $sitemap_enabled = isset($_POST['sitemap_enabled']) ? 1 : 0;
    $sitemap_robots = isset($_POST['sitemap_robots']) ? 1 : 0;
    $include_images = isset($_POST['include_images']) ? 1 : 0;
    $include_video = isset($_POST['include_video']) ? 1 : 0;
    $include_news = isset($_POST['include_news']) ? 1 : 0;
    $excluded_post_types = isset($_POST['excluded_post_types']) ? array_map('sanitize_text_field', $_POST['excluded_post_types']) : array();
    $excluded_taxonomies = isset($_POST['excluded_taxonomies']) ? array_map('sanitize_text_field', $_POST['excluded_taxonomies']) : array();
    
    // Zapisz ustawienia w bazie danych
    global $wpdb;
    $table_name = $wpdb->prefix . 'seo_settings';
    
    // Sprawdź, czy tabela istnieje
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $wpdb->update(
            $table_name,
            array(
                'sitemap_enabled' => $sitemap_enabled,
                'sitemap_robots' => $sitemap_robots,
                'sitemap_images' => $include_images,
                'sitemap_video' => $include_video,
                'sitemap_news' => $include_news
            ),
            array('id' => 1)
        );
    }
    
    // Zapisz opcje
    update_option('cleanseo_excluded_post_types', $excluded_post_types);
    update_option('cleanseo_excluded_taxonomies', $excluded_taxonomies);
    update_option('cleanseo_sitemap_include_images', $include_images);
    update_option('cleanseo_sitemap_include_video', $include_video);
    update_option('cleanseo_sitemap_include_news', $include_news);
    
    // Aktualizuj reguły przepisywania adresów URL
    $this->flush_rules();
    
    // Wyczyść pamięć podręczną mapy strony
    $this->clear_sitemap_cache();
    
    // Wyślij ping do wyszukiwarek
    if ($sitemap_enabled) {
        $this->ping_search_engines();
    }
    
    // Wywołaj akcję dla innych komponentów
    do_action('cleanseo_settings_updated', 'sitemap');
    
    // Zwróć odpowiedź
    wp_send_json_success(array(
        'message' => __('Ustawienia mapy strony zostały pomyślnie zapisane.', 'cleanseo-optimizer')
    ));
}

/**
 * Pobiera obiekt systemu plików WordPress
 *
 * @return WP_Filesystem_Base|false Obiekt systemu plików lub fałsz w przypadku niepowodzenia
 */
private function get_wp_filesystem() {
    global $wp_filesystem;
    
    if (!$wp_filesystem) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $access_type = get_filesystem_method();
        
        if ($access_type === 'direct') {
            $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());
            
            if (!WP_Filesystem($creds)) {
                return false;
            }
        } else {
            return false;
        }
    }
    
    return $wp_filesystem;
} 

/**
 * Wyświetla ustawienia mapy strony w panelu administracyjnym
 */
public function render_settings() {
    // Obsługa formularza
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['cleanseo_sitemap_settings_nonce']) &&
        wp_verify_nonce($_POST['cleanseo_sitemap_settings_nonce'], 'cleanseo_sitemap_settings_save')
    ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_settings';
        
        // Sprawdź, czy tabela istnieje
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wpdb->update(
                $table_name,
                array(
                    'sitemap_enabled' => isset($_POST['sitemap_enabled']) ? 1 : 0,
                    'sitemap_robots' => isset($_POST['sitemap_robots']) ? 1 : 0,
                    'sitemap_images' => isset($_POST['sitemap_images']) ? 1 : 0,
                    'sitemap_video' => isset($_POST['sitemap_video']) ? 1 : 0,
                    'sitemap_news' => isset($_POST['sitemap_news']) ? 1 : 0
                ),
                array('id' => 1)
            );
        }
        
        // Zapisz opcje
        $excluded_post_types = isset($_POST['excluded_post_types']) ? array_map('sanitize_text_field', $_POST['excluded_post_types']) : array();
        $excluded_taxonomies = isset($_POST['excluded_taxonomies']) ? array_map('sanitize_text_field', $_POST['excluded_taxonomies']) : array();
        
        update_option('cleanseo_excluded_post_types', $excluded_post_types);
        update_option('cleanseo_excluded_taxonomies', $excluded_taxonomies);
        update_option('cleanseo_sitemap_include_images', isset($_POST['sitemap_images']) ? 1 : 0);
        update_option('cleanseo_sitemap_include_video', isset($_POST['sitemap_video']) ? 1 : 0);
        update_option('cleanseo_sitemap_include_news', isset($_POST['sitemap_news']) ? 1 : 0);
        
        // Aktualizuj reguły przepisywania adresów URL
        $this->flush_rules();
        
        // Wyczyść pamięć podręczną mapy strony
        $this->clear_sitemap_cache();
        
        // Wyślij ping do wyszukiwarek
        if (isset($_POST['sitemap_enabled'])) {
            $this->ping_search_engines();
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Ustawienia mapy strony zapisane.', 'cleanseo-optimizer') . '</p></div>';
    }

    // Załaduj szablon ustawień
    include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cleanseo-sitemap-settings.php';
}
}    
                