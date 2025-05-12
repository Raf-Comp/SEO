(function($) {
    'use strict';

    const CleanSEOAI = {
        init: function() {
            this.initApiKeyVerification();
            this.initModelSelection();
            this.initSliders();
            this.initCostTracking();
        },

        initApiKeyVerification: function() {
            const $verifyButton = $('#verify-api-key');
            if (!$verifyButton.length) return;

            $verifyButton.on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $input = $('#api-key');
                const apiKey = $input.val().trim();

                if (!apiKey) {
                    CleanSEOAI.showMessage('error', 'Wprowadź klucz API.');
                    return;
                }

                $button.addClass('loading').prop('disabled', true);
                
                $.ajax({
                    url: cleanseoAI.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cleanseo_verify_api_key',
                        nonce: cleanseoAI.nonce,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            CleanSEOAI.showMessage('success', 'Klucz API został zweryfikowany pomyślnie.');
                            if (response.data.models) {
                                CleanSEOAI.updateModelSelect(response.data.models);
                            }
                        } else {
                            CleanSEOAI.showMessage('error', response.data.message || 'Wystąpił błąd podczas weryfikacji klucza API.');
                        }
                    },
                    error: function() {
                        CleanSEOAI.showMessage('error', 'Wystąpił błąd podczas komunikacji z serwerem.');
                    },
                    complete: function() {
                        $button.removeClass('loading').prop('disabled', false);
                    }
                });
            });
        },

        initModelSelection: function() {
            const $modelSelect = $('#ai-model');
            if (!$modelSelect.length) return;

            $modelSelect.on('change', function() {
                const selectedModel = $(this).val();
                if (selectedModel) {
                    CleanSEOAI.updateModelInfo(selectedModel);
                }
            });
        },

        initSliders: function() {
            $('.slider-group input[type="range"]').each(function() {
                const $slider = $(this);
                const $value = $slider.siblings('.value');
                
                $slider.on('input', function() {
                    $value.text($(this).val());
                });
            });
        },

        initCostTracking: function() {
            const $costBar = $('.cost-progress');
            if (!$costBar.length) return;

            const currentCost = parseFloat($costBar.data('cost'));
            const budgetLimit = parseFloat($costBar.data('limit'));
            const percentage = (currentCost / budgetLimit) * 100;

            $costBar.css('width', `${percentage}%`);
            
            if (percentage >= 90) {
                $costBar.addClass('danger');
            } else if (percentage >= 75) {
                $costBar.addClass('warning');
            }
        },

        updateModelSelect: function(models) {
            const $select = $('#ai-model');
            $select.empty();
            
            models.forEach(model => {
                $select.append(`<option value="${model.id}">${model.name}</option>`);
            });

            $select.prop('disabled', false);
        },

        updateModelInfo: function(modelId) {
            $.ajax({
                url: cleanseoAI.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cleanseo_get_model_info',
                    nonce: cleanseoAI.nonce,
                    model_id: modelId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const model = response.data;
                        $('#model-info').html(`
                            <div class="model-details">
                                <p><strong>Model:</strong> ${model.name}</p>
                                <p><strong>Maksymalna długość:</strong> ${model.max_tokens} tokenów</p>
                                <p><strong>Koszt:</strong> ${model.cost_per_1k} / 1k tokenów</p>
                            </div>
                        `);
                    }
                }
            });
        },

        showMessage: function(type, message) {
            const $message = $(`<div class="message ${type}-message">${message}</div>`);
            $('.cleanseo-ai-settings').prepend($message);
            
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        CleanSEOAI.init();
    });

})(jQuery); 