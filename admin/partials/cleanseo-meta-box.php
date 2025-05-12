<?php
if (!defined('WPINC')) {
    die;
}
?>
<div class="cleanseo-meta-box-container">
    <p>
        <label for="cleanseo_meta_title">Meta Tytuł:</label>
        <input type="text" id="cleanseo_meta_title" name="cleanseo_meta_title" value="<?php echo esc_attr($meta_title); ?>" class="widefat">
        <span class="description">Zalecana długość: 50-60 znaków. Aktualnie: <span id="cleanseo_title_length"><?php echo mb_strlen($meta_title); ?></span></span>
    </p>
    
    <p>
        <label for="cleanseo_meta_description">Meta Opis:</label>
        <textarea id="cleanseo_meta_description" name="cleanseo_meta_description" class="widefat" rows="3"><?php echo esc_textarea($meta_description); ?></textarea>
        <span class="description">Zalecana długość: 150-160 znaków. Aktualnie: <span id="cleanseo_description_length"><?php echo mb_strlen($meta_description); ?></span></span>
    </p>
    
    <p>
        <label for="cleanseo_focus_keyword">Słowo kluczowe:</label>
        <input type="text" id="cleanseo_focus_keyword" name="cleanseo_focus_keyword" value="<?php echo esc_attr($focus_keyword); ?>" class="widefat">
        <span class="description">Główne słowo kluczowe dla tej strony.</span>
    </p>
    
    <?php if (!empty($meta_title) || !empty($meta_description)): ?>
    <div class="cleanseo-preview">
        <h4>Podgląd w wynikach wyszukiwania:</h4>
        <div class="cleanseo-serp-preview">
            <div class="cleanseo-serp-title"><?php echo esc_html($meta_title); ?></div>
            <div class="cleanseo-serp-url"><?php echo esc_url(get_permalink($post->ID)); ?></div>
            <div class="cleanseo-serp-description"><?php echo esc_html($meta_description); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="cleanseo-buttons">
        <button type="button" class="button" id="cleanseo_generate_meta">Generuj z AI</button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Liczniki znaków
    $('#cleanseo_meta_title').on('keyup', function() {
        var length = $(this).val().length;
        $('#cleanseo_title_length').text(length);
        
        if (length < 30 || length > 60) {
            $('#cleanseo_title_length').css('color', 'red');
        } else {
            $('#cleanseo_title_length').css('color', 'green');
        }
    });
    
    $('#cleanseo_meta_description').on('keyup', function() {
        var length = $(this).val().length;
        $('#cleanseo_description_length').text(length);
        
        if (length < 120 || length > 160) {
            $('#cleanseo_description_length').css('color', 'red');
        } else {
            $('#cleanseo_description_length').css('color', 'green');
        }
    });
    
    // Generowanie z AI
    $('#cleanseo_generate_meta').on('click', function() {
        $(this).prop('disabled', true).text('Generowanie...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cleanseo_generate_meta',
                post_id: <?php echo $post->ID; ?>,
                nonce: $('#cleanseo_meta_box_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#cleanseo_meta_title').val(response.data.title).trigger('keyup');
                    $('#cleanseo_meta_description').val(response.data.description).trigger('keyup');
                } else {
                    alert('Błąd: ' + response.data);
                }
            },
            error: function() {
                alert('Wystąpił błąd podczas komunikacji z serwerem.');
            },
            complete: function() {
                $('#cleanseo_generate_meta').prop('disabled', false).text('Generuj z AI');
            }
        });
    });
});
</script>

<style>
.cleanseo-meta-box-container {
    margin-bottom: 15px;
}
.cleanseo-meta-box-container label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}
.cleanseo-meta-box-container .description {
    font-style: italic;
    color: #666;
    font-size: 12px;
}
.cleanseo-preview {
    margin-top: 15px;
    border: 1px solid #ddd;
    padding: 10px;
    background: #f9f9f9;
}
.cleanseo-preview h4 {
    margin-top: 0;
    margin-bottom: 10px;
}
.cleanseo-serp-preview {
    background: white;
    padding: 10px;
    border: 1px solid #eee;
}
.cleanseo-serp-title {
    color: #1a0dab;
    font-size: 18px;
    text-decoration: none;
    margin-bottom: 3px;
}
.cleanseo-serp-url {
    color: #006621;
    font-size: 14px;
    margin-bottom: 3px;
}
.cleanseo-serp-description {
    color: #545454;
    font-size: 13px;
    line-height: 1.4;
}
.cleanseo-buttons {
    margin-top: 15px;
}
</style> 