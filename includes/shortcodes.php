<?php
// Shortcode for card creation form
add_shortcode('personalized_cards_form', 'pc_card_form_shortcode');
function pc_card_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="pc-login-notice">
            <p>' . __('Please log in to create personalized cards.', 'personalized-cards') . '</p>
            <a href="' . wp_login_url(get_permalink()) . '" class="pc-login-btn">' . __('Login', 'personalized-cards') . '</a>
        </div>';
    }
    
    $user_id = get_current_user_id();
    
    if (!PC_Subscription_Handler::can_create_card($user_id)) {
        return '<div class="pc-subscription-notice">
            <p>' . __('You need an active membership to create cards.', 'personalized-cards') . '</p>
            <p>' . __('Please contact an administrator to activate your membership.', 'personalized-cards') . '</p>
        </div>';
    }
    
    $subscription_level = PC_Subscription_Handler::get_user_subscription_level($user_id);
    $templates = PC_Card_Creator::get_available_templates($subscription_level);
    $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
    
    ob_start();
    ?>
    <div class="pc-card-creator">
        <h2><?php _e('Create Your Personalized Card', 'personalized-cards'); ?></h2>
        
        <?php if ($expiry_date): ?>
        <div class="pc-membership-info">
            <p><?php _e('Your membership expires on:', 'personalized-cards'); ?> <strong><?php echo date('F j, Y', strtotime($expiry_date)); ?></strong></p>
        </div>
        <?php endif; ?>
        
        <form id="pc-card-form" method="post">
            <?php wp_nonce_field('pc_create_card', 'pc_nonce'); ?>
            
            <div class="pc-form-group">
                <label for="pc-template"><?php _e('Choose Template:', 'personalized-cards'); ?></label>
                <select name="template" id="pc-template" required>
                    <option value=""><?php _e('Select a template', 'personalized-cards'); ?></option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?php echo esc_attr($template['file']); ?>"><?php echo esc_html($template['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pc-template-preview" id="pc-template-preview"></div>
            
            <div class="pc-form-group">
                <label for="pc-name"><?php _e('Name:', 'personalized-cards'); ?></label>
                <input type="text" name="name" id="pc-name" maxlength="50" required placeholder="<?php _e('Enter name for the card', 'personalized-cards'); ?>">
            </div>
            
            <div class="pc-form-group">
                <label for="pc-message"><?php _e('Message:', 'personalized-cards'); ?></label>
                <textarea name="message" id="pc-message" rows="4" maxlength="200" placeholder="<?php _e('Enter your personalized message (optional)', 'personalized-cards'); ?>"></textarea>
            </div>
            
            <div class="pc-form-group">
                <label for="pc-date"><?php _e('Date:', 'personalized-cards'); ?></label>
                <input type="date" name="date" id="pc-date">
            </div>
            
            <div class="pc-form-group">
                <label>
                    <input type="checkbox" name="send_email" id="pc-send-email" value="1" checked>
                    <?php _e('Send card to my email', 'personalized-cards'); ?>
                </label>
            </div>
            
            <button type="submit" class="pc-submit-btn"><?php _e('Create Card', 'personalized-cards'); ?></button>
        </form>
        
        <div id="pc-result"></div>
    </div>
    <?php
    return ob_get_clean();
}

// Shortcode for displaying user's cards
add_shortcode('my_personalized_cards', 'pc_my_cards_shortcode');
function pc_my_cards_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('Please log in to view your cards.', 'personalized-cards') . '</p>';
    }
    
    $user_id = get_current_user_id();
    $cards = PC_Database::get_user_cards($user_id);
    
    ob_start();
    ?>
    <div class="pc-my-cards">
        <h2><?php _e('My Cards', 'personalized-cards'); ?></h2>
        
        <?php if (empty($cards)): ?>
            <p><?php _e('You haven\'t created any cards yet.', 'personalized-cards'); ?></p>
        <?php else: ?>
            <div class="pc-cards-grid">
                <?php foreach ($cards as $card): ?>
                    <div class="pc-card-item">
                        <?php if ($card->card_image): ?>
                            <img src="<?php echo esc_url($card->card_image); ?>" alt="Card">
                        <?php endif; ?>
                        <div class="pc-card-info">
                            <p><strong><?php _e('Created:', 'personalized-cards'); ?></strong> <?php echo date('F j, Y', strtotime($card->created_at)); ?></p>
                            <a href="<?php echo esc_url($card->card_image); ?>" download class="pc-download-btn"><?php _e('Download', 'personalized-cards'); ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pc_dashboard', function () {

    ob_start();

    if (!is_user_logged_in()) {

        wp_login_form(array(
            'redirect' => get_permalink()
        ));

    } else {

        echo do_shortcode('[my_personalized_cards]');
    }

    return ob_get_clean();
});