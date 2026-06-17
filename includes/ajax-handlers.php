<?php
// Legacy user-facing card creation has been removed.
// Admins now create cards from the admin dashboard (see includes/admin/admin-page.php).

// ── Apple Wallet pass download ──────────────────────────────────────────────────
// Generates a .pkpass for the requested card and streams it with the correct MIME
// type so iOS Safari offers "Add to Apple Wallet".
add_action('wp_ajax_pc_apple_wallet', 'pc_apple_wallet_download');
function pc_apple_wallet_download() {
    if (!is_user_logged_in()) {
        wp_die(__('You must be logged in.', 'personalized-cards'), '', array('response' => 403));
    }

    $card_id = isset($_GET['card_id']) ? absint($_GET['card_id']) : 0;
    $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!$card_id || !wp_verify_nonce($nonce, 'pc_apple_wallet_' . $card_id)) {
        wp_die(__('Invalid or expired request.', 'personalized-cards'), '', array('response' => 403));
    }

    $card = PC_Database::get_card($card_id);
    if (!$card) {
        wp_die(__('Card not found.', 'personalized-cards'), '', array('response' => 404));
    }

    // A member may only download their own card; admins may download any.
    if ((int) $card->user_id !== get_current_user_id() && !current_user_can('manage_options')) {
        wp_die(__('You are not allowed to access this card.', 'personalized-cards'), '', array('response' => 403));
    }

    $card_data  = json_decode($card->card_data, true);
    if (!is_array($card_data)) $card_data = array();

    $image_path = PC_Wallet_Handler::url_to_path($card->card_image);

    $result = PC_Wallet_Handler::create_apple_wallet_pass($card_data, $image_path, (int) $card->user_id);
    if (is_wp_error($result)) {
        // Admins get the technical reason; members get a friendly message.
        $detail = current_user_can('manage_options')
            ? $result->get_error_message()
            : __('Sorry, your wallet pass could not be generated right now. Please contact an administrator.', 'personalized-cards');
        wp_die(esc_html($detail), __('Wallet pass error', 'personalized-cards'), array('response' => 500));
    }

    nocache_headers();
    header('Content-Type: application/vnd.apple.pkpass');
    header('Content-Disposition: attachment; filename="membership.pkpass"');
    header('Content-Length: ' . strlen($result));
    echo $result;
    exit;
}
