<?php
/**
 * Klasa do analizy treści
 */

if (!defined('WPINC')) {
    die;
}

class CleanSEO_ContentAnalysis {
    private $content;
    private $keyword;
    private $post_id;

    public function __construct($post_id = null) {
        $this->post_id = $post_id;
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $this->content = $post->post_content;
                $this->keyword = get_post_meta($post_id, '_cleanseo_focus_keyword', true);
            }
        }
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     */
    public function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_seo_analysis_metabox'));
        add_action('save_post', array($this, 'save_seo_analysis_data'));
        add_action('wp_ajax_cleanseo_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_cleanseo_export_content_csv', array($this, 'ajax_export_content_csv'));
        add_action('wp_ajax_cleanseo_export_content_pdf', array($this, 'ajax_export_content_pdf'));
    }

    /**
     * Dodaj metabox analizy SEO
     */
    public function add_seo_analysis_metabox() {
        $post_types = array('post', 'page');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'cleanseo_analysis_metabox',
                'CleanSEO - Analiza treści',
                array($this, 'render_analysis_metabox'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Renderuj metabox analizy
     */
    public function render_analysis_metabox($post) {
        wp_nonce_field('cleanseo_analysis_nonce', 'cleanseo_analysis_nonce');
        
        $keyword = get_post_meta($post->ID, '_cleanseo_focus_keyword', true);
        
        echo '<div class="cleanseo-analysis-container">';
        echo '<p><label for="cleanseo_focus_keyword">Słowo kluczowe: </label>';
        echo '<input type="text" id="cleanseo_focus_keyword" name="cleanseo_focus_keyword" value="' . esc_attr($keyword) . '">';
        echo '<button type="button" id="cleanseo_analyze_button" class="button">Analizuj</button></p>';
        
        echo '<div id="cleanseo_analysis_results" class="cleanseo-results">';
        // Wyniki analizy będą tutaj
        if ($keyword) {
            // Jeśli już mamy słowo kluczowe, pokażmy wyniki
            $this->content = $post->post_content;
            $this->keyword = $keyword;
            $this->post_id = $post->ID;
            echo $this->generate_analysis_html();
        }
        echo '</div>';
        echo '</div>';
        
        $this->output_analysis_scripts($post->ID);
    }

    /**
     * Zapisz dane analizy
     */
    public function save_seo_analysis_data($post_id) {
        if (!isset($_POST['cleanseo_analysis_nonce']) || !wp_verify_nonce($_POST['cleanseo_analysis_nonce'], 'cleanseo_analysis_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['cleanseo_focus_keyword'])) {
            update_post_meta($post_id, '_cleanseo_focus_keyword', sanitize_text_field($_POST['cleanseo_focus_keyword']));
        }
    }

    /**
     * Generuj skrypty JavaScript
     */
    private function output_analysis_scripts($post_id) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#cleanseo_analyze_button').on('click', function() {
                var keyword = $('#cleanseo_focus_keyword').val();
                
                $('#cleanseo_analysis_results').html('<p>Analizowanie treści...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cleanseo_analyze_content',
                        post_id: <?php echo $post_id; ?>,
                        keyword: keyword,
                        nonce: $('#cleanseo_analysis_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#cleanseo_analysis_results').html(response.data.html);
                        } else {
                            $('#cleanseo_analysis_results').html('<p>Wystąpił błąd podczas analizy: ' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#cleanseo_analysis_results').html('<p>Wystąpił błąd podczas połączenia z serwerem.</p>');
                    }
                });
            });
        });
        </script>
        <style>
        .cleanseo-analysis-results {
            margin-top: 15px;
        }
        .cleanseo-score {
            display: inline-block;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            text-align: center;
            line-height: 1;
            margin-bottom: 20px;
            color: white;
            position: relative;
        }
        .cleanseo-score span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: bold;
        }
        .cleanseo-score p {
            position: absolute;
            top: 75%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            width: 100%;
        }
        .cleanseo-score.good {
            background-color: #5cb85c;
        }
        .cleanseo-score.medium {
            background-color: #f0ad4e;
        }
        .cleanseo-score.bad {
            background-color: #d9534f;
        }
        .cleanseo-metric {
            margin-bottom: 10px;
            padding: 10px;
            border-left: 4px solid #ddd;
        }
        .cleanseo-metric.good {
            border-color: #5cb85c;
            background-color: #f9fff9;
        }
        .cleanseo-metric.medium {
            border-color: #f0ad4e;
            background-color: #fffcf9;
        }
        .cleanseo-metric.bad {
            border-color: #d9534f;
            background-color: #fff9f9;
        }
        .cleanseo-metric span {
            font-weight: bold;
        }
        .cleanseo-metric p {
            margin: 5px 0 0;
        }
        </style>
        <?php
    }

    /**
     * Obsługa AJAX analizy treści
     */
    public function ajax_analyze_content() {
        check_ajax_referer('cleanseo_analysis_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (!$post_id) {
            wp_send_json_error('Nieprawidłowy ID posta');
        }
        
        update_post_meta($post_id, '_cleanseo_focus_keyword', $keyword);
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Nie znaleziono posta');
        }
        
        $this->content = $post->post_content;
        $this->keyword = $keyword;
        $this->post_id = $post_id;
        
        $html = $this->generate_analysis_html();
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Generuj HTML wyników analizy
     */
    private function generate_analysis_html() {
        $content_length = $this->get_content_length();
        $keyword_density = $this->get_keyword_density();
        $flesch_score = $this->get_flesch_score();
        $avg_sentence_length = $this->get_avg_sentence_length();
        $passive_voice_percentage = $this->get_passive_voice_percentage();
        $headers = $this->get_headers();
        $internal_links = $this->get_internal_links_count();
        
        $h1_count = 0;
        $h2_count = 0;
        $keyword_in_headers = 0;
        
        foreach ($headers as $header) {
            if ($header['level'] === '1') {
                $h1_count++;
            } elseif ($header['level'] === '2') {
                $h2_count++;
            }
            
            if (!empty($this->keyword) && stripos($header['text'], $this->keyword) !== false) {
                $keyword_in_headers++;
            }
        }
        
        // Sprawdź obecność obrazów i atrybutów alt
        $images = $this->count_images_without_alt();
        
        $html = '<div class="cleanseo-analysis-results">';
        
        // Ogólny wynik SEO
        $seo_score = $this->get_seo_score();
        $html .= sprintf(
            '<div class="cleanseo-score %s"><span>%d%%</span><p>Ogólny wynik SEO</p></div>',
            $seo_score >= 80 ? 'good' : ($seo_score >= 60 ? 'medium' : 'bad'),
            $seo_score
        );
        
        // Długość treści
        $html .= '<h3>Długość treści</h3>';
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>%d słów</span><p>%s</p></div>',
            $content_length >= 300 ? 'good' : 'bad',
            $content_length,
            $content_length >= 300 ? 'Dobra długość treści' : 'Treść zbyt krótka, zalecane minimum 300 słów'
        );
        
        // Słowo kluczowe
        if (!empty($this->keyword)) {
            $html .= '<h3>Analiza słowa kluczowego</h3>';
            $html .= sprintf(
                '<div class="cleanseo-metric %s"><span>%.2f%%</span><p>%s</p></div>',
                ($keyword_density >= 0.5 && $keyword_density <= 2.5) ? 'good' : 'bad',
                $keyword_density,
                ($keyword_density >= 0.5 && $keyword_density <= 2.5) ? 'Optymalne nasycenie słowem kluczowym' : 'Nasycenie słowem kluczowym poza optymalnym zakresem 0.5-2.5%'
            );
            
            $html .= sprintf(
                '<div class="cleanseo-metric %s"><span>%d</span><p>%s</p></div>',
                $keyword_in_headers > 0 ? 'good' : 'bad',
                $keyword_in_headers,
                $keyword_in_headers > 0 ? 'Słowo kluczowe występuje w nagłówkach' : 'Dodaj słowo kluczowe do nagłówków'
            );
        }
        
        // Nagłówki
        $html .= '<h3>Struktura nagłówków</h3>';
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>H1: %d</span><p>%s</p></div>',
            $h1_count === 1 ? 'good' : 'bad',
            $h1_count,
            $h1_count === 1 ? 'Prawidłowa liczba nagłówków H1' : 'Strona powinna zawierać dokładnie jeden nagłówek H1'
        );
        
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>H2: %d</span><p>%s</p></div>',
            $h2_count > 0 ? 'good' : 'medium',
            $h2_count,
            $h2_count > 0 ? 'Dobra struktura podtytułów H2' : 'Dodaj nagłówki H2 dla lepszej struktury treści'
        );
        
        // Czytelność
        $html .= '<h3>Czytelność tekstu</h3>';
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>%d</span><p>%s</p></div>',
            $flesch_score >= 60 ? 'good' : ($flesch_score >= 50 ? 'medium' : 'bad'),
            $flesch_score,
            $flesch_score >= 60 ? 'Dobra czytelność tekstu' : ($flesch_score >= 50 ? 'Średnia czytelność tekstu' : 'Tekst trudny do czytania, spróbuj uprościć')
        );
        
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>%d słów</span><p>%s</p></div>',
            $avg_sentence_length <= 20 ? 'good' : 'medium',
            $avg_sentence_length,
            $avg_sentence_length <= 20 ? 'Dobra długość zdań' : 'Zdania są zbyt długie, spróbuj skrócić'
        );
        
        // Obrazy
        $html .= '<h3>Obrazy</h3>';
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>%d</span><p>%s</p></div>',
            $images['without_alt'] === 0 ? 'good' : 'bad',
            $images['without_alt'],
            $images['without_alt'] === 0 ? 'Wszystkie obrazy mają atrybut alt' : 'Obrazy bez atrybutu alt: ' . $images['without_alt'] . ' (dodaj opis dla lepszego SEO)'
        );
        
        // Linkowanie wewnętrzne
        $html .= '<h3>Linkowanie</h3>';
        $html .= sprintf(
            '<div class="cleanseo-metric %s"><span>%d</span><p>%s</p></div>',
            $internal_links > 0 ? 'good' : 'medium',
            $internal_links,
            $internal_links > 0 ? 'Strona zawiera linki wewnętrzne' : 'Dodaj linki do innych stron w swojej witrynie'
        );
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Pobierz długość treści w słowach
     */
    public function get_content_length() {
        if (!$this->content) return 0;
        
        // Usuń HTML i specjalne znaki
        $text = strip_tags($this->content);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        
        // Podziel na słowa i policz
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    /**
     * Pobierz nasycenie słowem kluczowym
     */
    public function get_keyword_density() {
        if (!$this->content || !$this->keyword) return 0;
        
        $content_length = $this->get_content_length();
        if ($content_length === 0) return 0;
        
        // Usuń HTML i specjalne znaki
        $text = strip_tags($this->content);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        
        // Policz wystąpienia słowa kluczowego
        $keyword_count = substr_count(mb_strtolower($text), mb_strtolower($this->keyword));
        
        return round(($keyword_count / $content_length) * 100, 2);
    }

    /**
     * Pobierz analizę nagłówków
     */
    public function get_headers() {
        if (!$this->content) return array();
        
        $headers = array();
        $pattern = '/<h([1-6])[^>]*>(.*?)<\/h\1>/i';
        
        preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $headers[] = array(
                'level' => $match[1],
                'text' => strip_tags($match[2])
            );
        }
        
        return $headers;
    }

    /**
     * Pobierz wynik testu Flesch-Kincaid
     */
    public function get_flesch_score() {
        if (!$this->content) return 0;
        
        // Usuń HTML i specjalne znaki
        $text = strip_tags($this->content);
        $text = preg_replace('/[^\p{L}\p{N}\s.,!?]/u', '', $text);
        
        // Policz zdania
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) return 0;
        
        // Policz słowa
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $word_count = count($words);
        
        if ($word_count === 0) return 0;
        
        // Policz sylaby - uproszczona metoda dla języka polskiego
        $syllable_count = 0;
        foreach ($words as $word) {
            $syllable_count += $this->count_syllables($word);
        }
        
        // Oblicz wynik Flesch-Kincaid
        $score = 206.835 - (1.015 * ($word_count / $sentence_count)) - (84.6 * ($syllable_count / $word_count));
        
        // Limit wyniku do zakresu 0-100
        return min(100, max(0, round($score)));
    }

    /**
     * Policz sylaby w słowie
     */
    private function count_syllables($word) {
        $word = mb_strtolower($word);
        $count = 0;
        
        // Podstawowe reguły dla języka polskiego
        $vowels = 'aąeęioóuy';
        $word = preg_replace('/[^' . $vowels . 'bcćdfghjklłmnńpqrsśtvwxzźż]/ui', '', $word);
        
        // Liczba samogłosek w słowie
        preg_match_all('/[' . $vowels . ']/ui', $word, $matches);
        $count = count($matches[0]);
        
        // Zapobieganie zliczaniu diftongów jako dwóch sylab
        $word = preg_replace('/[' . $vowels . ']{2,}/ui', 'a', $word);
        $count -= (mb_strlen($word) - mb_strlen(preg_replace('/[' . $vowels . ']{2,}/ui', 'a', $word)));
        
        // Minimalnie 1 sylaba na słowo
        return max(1, $count);
    }

    /**
     * Pobierz średnią długość zdań
     */
    public function get_avg_sentence_length() {
        if (!$this->content) return 0;
        
        // Usuń HTML i specjalne znaki
        $text = strip_tags($this->content);
        $text = preg_replace('/[^\p{L}\p{N}\s.,!?]/u', '', $text);
        
        // Podziel na zdania
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) return 0;
        
        // Policz słowa w każdym zdaniu
        $total_words = 0;
        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/u', trim($sentence), -1, PREG_SPLIT_NO_EMPTY);
            $total_words += count($words);
        }
        
        return round($total_words / $sentence_count);
    }

    /**
     * Pobierz procent zdań w głosie biernym
     */
    public function get_passive_voice_percentage() {
        if (!$this->content) return 0;
        
        // Usuń HTML i specjalne znaki
        $text = strip_tags($this->content);
        $text = preg_replace('/[^\p{L}\p{N}\s.,!?]/u', '', $text);
        
        // Podziel na zdania
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) return 0;
        
        // Wzorce głosu biernego w języku polskim (uproszczone)
        $passive_patterns = array(
            '/jest\s+[^\s]+[any|ane|ani|ony|one|oni]/ui',
            '/został[a|o]?\s+[^\s]+[any|ane|ani|ony|one|oni]/ui',
            '/był[a|o]?\s+[^\s]+[any|ane|ani|ony|one|oni]/ui',
            '/został[a|o]?\s+[^\s]+[iony|iona|ione]/ui',
            '/został[a|o]?\s+[^\s]+[ty|ta|te]/ui'
        );
        
        $passive_count = 0;
        foreach ($sentences as $sentence) {
            foreach ($passive_patterns as $pattern) {
                if (preg_match($pattern, $sentence)) {
                    $passive_count++;
                    break;
                }
            }
        }
        
        return $sentence_count > 0 ? round(($passive_count / $sentence_count) * 100) : 0;
    }

    /**
     * Pobierz liczbę linków wewnętrznych
     */
    public function get_internal_links_count() {
        if (!$this->content) return 0;
        
        $site_url = get_site_url();
        $pattern = '/<a[^>]+href=["\'](' . preg_quote($site_url, '/') . '[^"\']*)["\'][^>]*>/i';
        preg_match_all($pattern, $this->content, $matches);
        
        return count($matches[0]);
    }

    /**
     * Pobierz liczbę unikalnych stron docelowych
     */
    public function get_unique_target_pages() {
        if (!$this->content) return 0;
        
        $site_url = get_site_url();
        $pattern = '/<a[^>]+href=["\'](' . preg_quote($site_url, '/') . '[^"\']*)["\'][^>]*>/i';
        preg_match_all($pattern, $this->content, $matches);
        
        return count(array_unique($matches[1]));
    }

    /**
     * Policz obrazy bez atrybutu alt
     */
    public function count_images_without_alt() {
        if (!$this->content) return array('total' => 0, 'without_alt' => 0);
        
        preg_match_all('/<img[^>]+>/i', $this->content, $img_tags);
        $total = count($img_tags[0]);
        $without_alt = 0;
        
        foreach ($img_tags[0] as $img_tag) {
            if (!preg_match('/alt=["\'](.*?)["\']/i', $img_tag) || preg_match('/alt=["\'][\s]*["\']/i', $img_tag)) {
                $without_alt++;
            }
        }
        
        return array(
            'total' => $total,
            'without_alt' => $without_alt
        );
    }

    /**
     * Pobierz podpowiedzi linków
     */
    public function get_link_suggestions() {
        if (!$this->post_id || !$this->keyword) return array();
        
        $suggestions = array();
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => array($this->post_id),
            's' => $this->keyword
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $relevance = $this->calculate_relevance(get_the_title(), get_the_excerpt());
                
                $suggestions[] = array(
                    'post_id' => get_the_ID(),
                    'text' => get_the_title(),
                    'relevance' => $relevance
                );
            }
        }
        
        wp_reset_postdata();
        
        // Sortuj po trafności
        usort($suggestions, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        return $suggestions;
    }

    /**
     * Oblicz trafność podpowiedzi
     */
    private function calculate_relevance($title, $content) {
        if (!$this->keyword) return 0;
        
        $relevance = 0;
        
        // Sprawdź słowo kluczowe w tytule
        if (stripos($title, $this->keyword) !== false) {
            $relevance += 50;
        }
        
        // Sprawdź słowo kluczowe w treści
        $content = strip_tags($content);
        $keyword_count = substr_count(mb_strtolower($content), mb_strtolower($this->keyword));
        $relevance += min($keyword_count * 10, 50);
        
        return $relevance;
    }

    /**
     * Pobierz wskaźnik długości treści
     */
    public function get_content_length_score() {
        $length = $this->get_content_length();
        
        if ($length >= 1500) return 100;
        if ($length >= 1000) return 80;
        if ($length >= 750) return 60;
        if ($length >= 500) return 40;
        if ($length >= 300) return 20;
        
        return 0;
    }

    /**
     * Pobierz wskaźnik nagłówków
     */
    public function get_headers_score() {
        $headers = $this->get_headers();
        $score = 0;
        
        // Sprawdź H1
        $has_h1 = false;
        foreach ($headers as $header) {
            if ($header['level'] === '1') {
                $has_h1 = true;
                $score += 40;
                break;
            }
        }
        
        // Sprawdź strukturę nagłówków
        $levels = array();
        foreach ($headers as $header) {
            $levels[] = $header['level'];
        }
        
        if (count($levels) >= 3) $score += 30;
        if (count($levels) >= 5) $score += 30;
        
        return min(100, $score);
    }

    /**
     * Pobierz wskaźnik czytelności
     */
    public function get_readability_score() {
        $flesch_score = $this->get_flesch_score();
        $passive_percentage = $this->get_passive_voice_percentage();
        $avg_sentence_length = $this->get_avg_sentence_length();
        
        $score = 0;
        
        // Ocena Flesch-Kincaid
        if ($flesch_score >= 80) $score += 40;
        elseif ($flesch_score >= 60) $score += 30;
        elseif ($flesch_score >= 50) $score += 20;
        else $score += 10;
        
        // Ocena głosu biernego
        if ($passive_percentage <= 10) $score += 30;
        elseif ($passive_percentage <= 20) $score += 20;
        else $score += 10;
        
        // Ocena długości zdań
        if ($avg_sentence_length <= 15) $score += 30;
        elseif ($avg_sentence_length <= 20) $score += 20;
        else $score += 10;
        
        return min(100, $score);
    }

    /**
     * Pobierz ogólny wskaźnik SEO
     */
    public function get_seo_score() {
        // Jeśli nie mamy contentu lub posta, zwróć 0
        if (!$this->content || !$this->post_id) return 0;
        
        $scores = array();
        
        // 1. Długość treści (waga: 20%)
        $scores['content_length'] = $this->get_content_length_score();
        
        // 2. Struktura nagłówków (waga: 15%)
        $scores['headers'] = $this->get_headers_score();
        
        // 3. Czytelność (waga: 15%)
        $scores['readability'] = $this->get_readability_score();
        
        // 4. Słowo kluczowe (waga: 20%)
        $scores['keyword'] = 0;
        if (!empty($this->keyword)) {
            $density = $this->get_keyword_density();
            if ($density >= 0.5 && $density <= 2.5) {
                $scores['keyword'] += 50;
            } elseif ($density > 0 && $density < 4) {
                $scores['keyword'] += 25;
            }
            
            // Słowo kluczowe w nagłówkach
            $headers = $this->get_headers();
            $keyword_in_headers = 0;
            foreach ($headers as $header) {
                if (stripos($header['text'], $this->keyword) !== false) {
                    $keyword_in_headers++;
                }
            }
            
            if ($keyword_in_headers > 0) {
                $scores['keyword'] += 50;
            }
        } else {
            $scores['keyword'] = 30; // Brak słowa kluczowego - uśredniona ocena
        }
        
        // 5. Obrazy (waga: 15%)
        $images = $this->count_images_without_alt();
        $scores['images'] = 0;
        if ($images['total'] > 0) {
            $percent_with_alt = 100 - (($images['without_alt'] / $images['total']) * 100);
            $scores['images'] = $percent_with_alt;
        } else {
            $scores['images'] = 50; // Brak obrazów - uśredniona ocena
        }
        
        // 6. Linkowanie wewnętrzne (waga: 15%)
        $internal_links = $this->get_internal_links_count();
        if ($internal_links >= 3) {
            $scores['links'] = 100;
        } elseif ($internal_links > 0) {
            $scores['links'] = 50;
        } else {
            $scores['links'] = 0;
        }
        
        // Wagi dla poszczególnych wskaźników
        $weights = array(
            'content_length' => 0.20,
            'headers' => 0.15,
            'readability' => 0.15,
            'keyword' => 0.20,
            'images' => 0.15,
            'links' => 0.15
        );
        
        // Oblicz wynik końcowy
        $final_score = 0;
        foreach ($scores as $key => $score) {
            $final_score += $score * $weights[$key];
        }
        
        return round($final_score);
    }

    /**
     * AJAX: Eksport analizy do CSV
     */
    public function ajax_export_content_csv() {
        check_ajax_referer('cleanseo_export_content', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id) {
            wp_die(__('Nieprawidłowy ID posta', 'cleanseo-optimizer'));
        }
        $this->post_id = $post_id;
        $post = get_post($post_id);
        $this->content = $post->post_content;
        $this->keyword = get_post_meta($post_id, '_cleanseo_focus_keyword', true);
        $metrics = $this->get_all_metrics();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=content-analysis-' . $post_id . '-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Metryka', 'Wartość', 'Rekomendacja'));
        foreach ($metrics as $m) {
            fputcsv($output, array($m['label'], $m['value'], $m['recommendation']));
        }
        fclose($output);
        exit;
    }

    /**
     * AJAX: Eksport analizy do PDF (mPDF)
     */
    public function ajax_export_content_pdf() {
        check_ajax_referer('cleanseo_export_content', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień.', 'cleanseo-optimizer'));
        }
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id) {
            wp_die(__('Nieprawidłowy ID posta', 'cleanseo-optimizer'));
        }
        $this->post_id = $post_id;
        $post = get_post($post_id);
        $this->content = $post->post_content;
        $this->keyword = get_post_meta($post_id, '_cleanseo_focus_keyword', true);
        $metrics = $this->get_all_metrics();
        require_once(ABSPATH . 'wp-content/plugins/cleanseo-optimizer/includes/vendor/mpdf/mpdf.php');
        $mpdf = new \Mpdf\Mpdf(['utf-8', 'A4']);
        $html = '<h1>Raport analizy treści</h1>';
        $html .= '<p><strong>Tytuł:</strong> ' . esc_html($post->post_title) . '</p>';
        $html .= '<p><strong>Słowo kluczowe:</strong> ' . esc_html($this->keyword) . '</p>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<thead><tr><th>Metryka</th><th>Wartość</th><th>Rekomendacja</th></tr></thead><tbody>';
        foreach ($metrics as $m) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($m['label']) . '</td>';
            $html .= '<td>' . esc_html($m['value']) . '</td>';
            $html .= '<td>' . esc_html($m['recommendation']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
        $filename = 'content-analysis-' . $post_id . '-' . date('Y-m-d') . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
        exit;
    }

    /**
     * Zbierz wszystkie metryki i rekomendacje (PL)
     */
    public function get_all_metrics() {
        $metrics = array();
        $metrics[] = array(
            'label' => 'Długość treści (znaki)',
            'value' => $this->get_content_length(),
            'recommendation' => $this->get_content_length() < 1500 ? 'Zalecana długość to min. 1500 znaków.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Nasycenie słowem kluczowym (%)',
            'value' => $this->get_keyword_density(),
            'recommendation' => $this->get_keyword_density() < 0.5 ? 'Zbyt niskie nasycenie.' : ($this->get_keyword_density() > 2.5 ? 'Zbyt wysokie nasycenie.' : 'OK')
        );
        $metrics[] = array(
            'label' => 'Flesch Reading Ease',
            'value' => $this->get_flesch_score(),
            'recommendation' => $this->get_flesch_score() < 45 ? 'Tekst trudny do czytania.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Średnia długość zdania',
            'value' => $this->get_avg_sentence_length(),
            'recommendation' => $this->get_avg_sentence_length() > 20 ? 'Zdania są zbyt długie.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Procent zdań w stronie biernej',
            'value' => $this->get_passive_voice_percentage() . '%',
            'recommendation' => $this->get_passive_voice_percentage() > 10 ? 'Za dużo strony biernej.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Liczba nagłówków H1',
            'value' => $this->count_headers('1'),
            'recommendation' => $this->count_headers('1') !== 1 ? 'Powinien być dokładnie jeden nagłówek H1.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Liczba nagłówków H2',
            'value' => $this->count_headers('2'),
            'recommendation' => $this->count_headers('2') < 2 ? 'Zalecane min. 2 nagłówki H2.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Obrazy bez ALT',
            'value' => $this->count_images_without_alt(),
            'recommendation' => $this->count_images_without_alt() > 0 ? 'Dodaj ALT do wszystkich obrazów.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Liczba linków wewnętrznych',
            'value' => $this->get_internal_links_count(),
            'recommendation' => $this->get_internal_links_count() < 2 ? 'Dodaj więcej linków wewnętrznych.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Liczba linków zewnętrznych',
            'value' => $this->get_external_links_count(),
            'recommendation' => $this->get_external_links_count() < 1 ? 'Dodaj przynajmniej jeden link zewnętrzny.' : 'OK'
        );
        $metrics[] = array(
            'label' => 'Fraza w pierwszym akapicie',
            'value' => $this->is_keyword_in_first_paragraph() ? 'TAK' : 'NIE',
            'recommendation' => $this->is_keyword_in_first_paragraph() ? 'OK' : 'Dodaj frazę do pierwszego akapitu.'
        );
        return $metrics;
    }

    /**
     * Zlicz nagłówki danego poziomu
     */
    public function count_headers($level = '1') {
        $headers = $this->get_headers();
        $count = 0;
        foreach ($headers as $header) {
            if ($header['level'] === $level) $count++;
        }
        return $count;
    }

    /**
     * Zlicz linki zewnętrzne
     */
    public function get_external_links_count() {
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>/i', $this->content, $links);
        $count = 0;
        foreach ($links[1] as $url) {
            if (preg_match('/^https?:\/\//', $url) && strpos($url, home_url()) === false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Czy fraza kluczowa jest w pierwszym akapicie?
     */
    public function is_keyword_in_first_paragraph() {
        if (empty($this->keyword)) return false;
        if (preg_match('/<p>(.*?)<\/p>/is', $this->content, $matches)) {
            return stripos($matches[1], $this->keyword) !== false;
        }
        return false;
    }
}