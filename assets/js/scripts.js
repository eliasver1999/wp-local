jQuery(document).ready(function($) {
    // Template preview
    $('#pc-template').on('change', function() {
        var template = $(this).val();
        if (template) {
            var templateUrl = pcAjax.pluginUrl + 'templates/cards/' + template;
            $('#pc-template-preview').html('<img src="' + templateUrl + '" alt="Template Preview">');
        } else {
            $('#pc-template-preview').html('');
        }
    });
    
    // Form submission
    $('#pc-card-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'pc_create_card',
            nonce: pcAjax.nonce,
            template: $('#pc-template').val(),
            name: $('#pc-name').val(),
            message: $('#pc-message').val(),
            date: $('#pc-date').val(),
            send_email: $('#pc-send-email').is(':checked')
        };
        
        $('#pc-result').html('<p class="pc-loading">Creating your card...</p>');
        $('.pc-submit-btn').prop('disabled', true);
        
        $.ajax({
            url: pcAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    var html = '<div class="pc-success">';
                    html += '<p>' + response.data.message + '</p>';
                    html += '<div class="pc-card-result">';
                    html += '<img src="' + response.data.image_url + '" alt="Your Card">';
                    html += '<div class="pc-action-buttons">';
                    html += '<a href="' + response.data.image_url + '" download class="pc-download-btn">Download Card</a>';
                    
                    // Add wallet buttons if available
                    if (response.data.wallet_links) {
                        if (response.data.wallet_links.apple) {
                            html += '<a href="' + response.data.wallet_links.apple + '" class="pc-wallet-btn pc-apple-wallet">Add to Apple Wallet</a>';
                        }
                        if (response.data.wallet_links.google) {
                            html += '<a href="' + response.data.wallet_links.google + '" class="pc-wallet-btn pc-google-wallet" target="_blank">Add to Google Wallet</a>';
                        }
                    }
                    
                    html += '</div></div></div>';
                    
                    $('#pc-result').html(html);
                    $('#pc-card-form')[0].reset();
                    $('#pc-template-preview').html('');
                } else {
                    $('#pc-result').html('<div class="pc-error">' + response.data.message + '</div>');
                }
                $('.pc-submit-btn').prop('disabled', false);
            },
            error: function() {
                $('#pc-result').html('<div class="pc-error">An error occurred. Please try again.</div>');
                $('.pc-submit-btn').prop('disabled', false);
            }
        });
    });
});
