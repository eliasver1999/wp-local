<?php
// Handle card creation via AJAX
add_action('wp_ajax_pc_create_card', 'pc_ajax_create_card');
function pc_ajax_create_card() {
    check_ajax_referer('pc_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in.', 'personalized-cards')));
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    
    if (!PC_Subscription_Handler::can_create_card($user_id)) {
        wp_send_json_error(array('message' => __('You need an active subscription.', 'personalized-cards')));
    }
    
    $template = sanitize_text_field($_POST['template']);
    $name = sanitize_text_field($_POST['name']);
    $message = sanitize_textarea_field($_POST['message']);
    $date = sanitize_text_field($_POST['date']);
    $send_email = isset($_POST['send_email']) && $_POST['send_email'] === 'true';
    
    $card_data = array(
        'name' => $name,
        'message' => $message,
        'date' => $date
    );
    
    $subscription_level = PC_Subscription_Handler::get_user_subscription_level($user_id);
    
    // Save card to database
    $card_id = PC_Database::save_card($user_id, $template, $card_data, $subscription_level);
    
    if (!$card_id) {
        wp_send_json_error(array('message' => __('Failed to save card.', 'personalized-cards')));
    }
    
    // Create the personalized image
    $template_path = PC_PLUGIN_DIR . 'templates/cards/' . $template;
    $upload_dir = wp_upload_dir();
    $output_filename = 'card_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
    $output_path = $upload_dir['path'] . '/' . $output_filename;
    
    $result = PC_Card_Creator::create_personalized_card($template_path, $card_data, $output_path);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }
    
    $image_url = $upload_dir['url'] . '/' . $output_filename;
    PC_Database::update_card_image($card_id, $image_url);
    
    $response_data = array(
        'message' => __('Card created successfully!', 'personalized-cards'),
        'image_url' => $image_url
    );
    
    // Send email if requested
    if ($send_email) {
        $email_sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $output_path);
        $response_data['email_sent'] = $email_sent;
        if ($email_sent) {
            $response_data['message'] .= ' ' . __('Email sent successfully!', 'personalized-cards');
        }
    }
    
    // Create wallet passes
    $wallet_links = array();
    
    // Apple Wallet
    if (get_option('pc_enable_apple_wallet')) {
        $apple_pass = PC_Wallet_Handler::create_apple_wallet_pass($card_data, $output_path);
        if (!is_wp_error($apple_pass)) {
            $wallet_links['apple'] = $apple_pass;
        }
    }
    
    // Google Wallet
    if (get_option('pc_enable_google_wallet')) {
        $google_pass = PC_Wallet_Handler::create_simple_google_wallet_link($card_data, $image_url);
        if ($google_pass) {
            $wallet_links['google'] = $google_pass;
        }
    }
    
    if (!empty($wallet_links)) {
        $response_data['wallet_links'] = $wallet_links;
    }
    
    wp_send_json_success($response_data);
}
