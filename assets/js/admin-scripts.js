jQuery(document).ready(function($) {
    // Delete card
    $('.pc-delete-card').on('click', function() {
        if (!confirm('Are you sure you want to delete this card?')) {
            return;
        }
        
        var button = $(this);
        var cardId = button.data('card-id');
        
        button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: pcAdminAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'pc_delete_card',
                nonce: pcAdminAjax.nonce,
                card_id: cardId
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                    alert('Card deleted successfully');
                } else {
                    alert('Error: ' + response.data.message);
                    button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert('An error occurred');
                button.prop('disabled', false).text('Delete');
            }
        });
    });
});
