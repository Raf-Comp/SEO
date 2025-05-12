/**
 * CleanSEO Frontend JavaScript
 */
(function($) {
    'use strict';

    // Inicjalizacja
    function init() {
        // Obsługa meta boxów
        initMetaBoxes();
        
        // Obsługa podglądu meta tagów
        initMetaPreview();
    }

    // Inicjalizacja meta boxów
    function initMetaBoxes() {
        $('.cleanseo-meta-box').each(function() {
            var $metaBox = $(this);
            var $titleInput = $metaBox.find('input[name="cleanseo_meta_title"]');
            var $descInput = $metaBox.find('textarea[name="cleanseo_meta_description"]');
            
            // Aktualizuj podgląd przy zmianie
            $titleInput.on('input', updateMetaPreview);
            $descInput.on('input', updateMetaPreview);
        });
    }

    // Inicjalizacja podglądu meta tagów
    function initMetaPreview() {
        $('.cleanseo-meta-box .meta-preview').each(function() {
            var $preview = $(this);
            var $title = $preview.find('.preview-title');
            var $url = $preview.find('.preview-url');
            var $desc = $preview.find('.preview-description');
            
            // Aktualizuj URL
            $url.text(window.location.href);
        });
    }

    // Aktualizacja podglądu meta tagów
    function updateMetaPreview() {
        var $metaBox = $(this).closest('.cleanseo-meta-box');
        var $preview = $metaBox.find('.meta-preview');
        var $title = $preview.find('.preview-title');
        var $desc = $preview.find('.preview-description');
        
        // Aktualizuj tytuł
        var title = $metaBox.find('input[name="cleanseo_meta_title"]').val();
        if (title) {
            $title.text(title);
        } else {
            $title.text(document.title);
        }
        
        // Aktualizuj opis
        var desc = $metaBox.find('textarea[name="cleanseo_meta_description"]').val();
        if (desc) {
            $desc.text(desc);
        } else {
            $desc.text('');
        }
    }

    // Obsługa błędów AJAX
    function handleAjaxError(jqXHR, textStatus, errorThrown) {
        console.error('CleanSEO AJAX Error:', textStatus, errorThrown);
    }

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(init);

})(jQuery); 