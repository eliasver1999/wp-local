<?php
// [pc_login] — login page shortcode; redirects logged-in users to My Card page
add_shortcode('pc_login', 'pc_login_shortcode');
function pc_login_shortcode() {
    if (is_user_logged_in()) {
        $my_card_page_id = get_option('pc_my_card_page_id');
        $redirect_url = $my_card_page_id ? get_permalink($my_card_page_id) : home_url('/my-card/');
        wp_safe_redirect($redirect_url);
        exit;
    }

    $login_page_id = get_option('pc_login_page_id');
    $redirect_after = get_option('pc_my_card_page_id') ? get_permalink(get_option('pc_my_card_page_id')) : home_url('/my-card/');

    ob_start();
    ?>
    <div class="pc-login-wrap">
        <h2 class="pc-login-title"><?php _e('Member Login', 'personalized-cards'); ?></h2>
        <?php
        if (isset($_GET['login']) && $_GET['login'] === 'failed') {
            echo '<p class="pc-login-error">' . __('Invalid username or password. Please try again.', 'personalized-cards') . '</p>';
        }
        wp_login_form(array(
            'redirect'       => esc_url($redirect_after),
            'label_username' => __('Username or Email', 'personalized-cards'),
            'label_password' => __('Password', 'personalized-cards'),
            'label_remember' => __('Keep me logged in', 'personalized-cards'),
            'label_log_in'   => __('Log In', 'personalized-cards'),
            'remember'       => true,
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// [pc_my_card] — shows user's card with download and wallet options
add_shortcode('pc_my_card', 'pc_my_card_shortcode');
function pc_my_card_shortcode() {
    if (!is_user_logged_in()) {
        $login_page_id = get_option('pc_login_page_id');
        $login_url = $login_page_id ? get_permalink($login_page_id) : wp_login_url(get_permalink());
        wp_safe_redirect($login_url);
        exit;
    }

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $cards   = PC_Database::get_user_cards($user_id);

    $is_active   = get_user_meta($user_id, 'pc_subscription_active', true);
    $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
    $days_left   = $expiry_date ? floor((strtotime($expiry_date) - time()) / DAY_IN_SECONDS) : 0;
    $is_expired  = ($is_active !== '1') || ($expiry_date && $days_left <= 0);

    // Auto-deactivate silently if expired
    if ($is_expired && $is_active === '1') {
        update_user_meta($user_id, 'pc_subscription_active', '0');
    }

    ob_start();
    ?>
    <div class="pc-my-card-wrap">
        <div class="pc-member-info">
            <p><?php echo esc_html(sprintf(__('Welcome, %s', 'personalized-cards'), $user->display_name)); ?></p>
            <?php if (!$is_expired): ?>
                <p class="pc-expiry-notice">
                    <?php echo esc_html(sprintf(__('Membership active — expires %s (%d days left)', 'personalized-cards'), date_i18n('F j, Y', strtotime($expiry_date)), $days_left)); ?>
                </p>
            <?php else: ?>
                <p class="pc-expiry-notice pc-expired">
                    <?php _e('Your membership has expired. Please contact an administrator to renew.', 'personalized-cards'); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (empty($cards)): ?>
            <p class="pc-no-card"><?php _e('Your card has not been created yet. Please check back later.', 'personalized-cards'); ?></p>
        <?php else:
            $latest = $cards[0];
            ?>
            <div class="pc-card-display">
                <?php if ($latest->card_image): ?>
                    <div class="pc-card-image-wrap <?php echo $is_expired ? 'pc-card-expired' : ''; ?>">
                        <img src="<?php echo esc_url($latest->card_image); ?>" alt="<?php esc_attr_e('Your Membership Card', 'personalized-cards'); ?>">
                        <?php if ($is_expired): ?>
                            <div class="pc-expired-overlay" aria-hidden="true">
                                <span><?php _e('EXPIRED', 'personalized-cards'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($latest->card_back_image)): ?>
                    <div class="pc-card-image-wrap pc-card-back" style="margin-top:12px;">
                        <img src="<?php echo esc_url($latest->card_back_image); ?>" alt="<?php esc_attr_e('Card Back', 'personalized-cards'); ?>">
                    </div>
                <?php endif; ?>

                <div class="pc-card-actions">
                    <?php if ($latest->card_image && !$is_expired): ?>
                        <a href="<?php echo esc_url($latest->card_image); ?>" download class="pc-btn pc-btn-download">
                            <?php _e('Download Card', 'personalized-cards'); ?>
                        </a>
                    <?php elseif ($latest->card_image && $is_expired): ?>
                        <span class="pc-btn pc-btn-disabled" title="<?php esc_attr_e('Renew your membership to download', 'personalized-cards'); ?>">
                            <?php _e('Download Card', 'personalized-cards'); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (get_option('pc_enable_apple_wallet') && !$is_expired): ?>
                        <button class="pc-btn pc-btn-apple-wallet" data-card-id="<?php echo esc_attr($latest->id); ?>">
                            <?php _e('Add to Apple Wallet', 'personalized-cards'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (get_option('pc_enable_google_wallet') && !$is_expired): ?>
                        <?php
                        $card_data = json_decode($latest->card_data, true) ?: array('name' => $user->display_name);
                        $gw_link   = PC_Wallet_Handler::create_google_wallet_link($card_data, $user_id, $latest->card_image);
                        $gw_link   = is_wp_error($gw_link) ? false : $gw_link;
                        if ($gw_link): ?>
                            <a href="<?php echo esc_url($gw_link); ?>" target="_blank" class="pc-btn pc-btn-google-wallet">
                                <?php _e('Add to Google Wallet', 'personalized-cards'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($is_expired): ?>
                        <p class="pc-renew-msg"><?php _e('Contact an administrator to renew your membership and restore full access.', 'personalized-cards'); ?></p>
                    <?php endif; ?>
                </div>

                <p class="pc-card-date">
                    <?php echo esc_html(sprintf(__('Issued: %s', 'personalized-cards'), date_i18n('F j, Y', strtotime($latest->created_at)))); ?>
                </p>
            </div>

            <?php if (count($cards) > 1): ?>
                <div class="pc-previous-cards">
                    <h3><?php _e('Previous Cards', 'personalized-cards'); ?></h3>
                    <div class="pc-cards-grid">
                        <?php foreach (array_slice($cards, 1) as $card): ?>
                            <div class="pc-card-item">
                                <?php if ($card->card_image): ?>
                                    <img src="<?php echo esc_url($card->card_image); ?>" alt="Card">
                                <?php endif; ?>
                                <p><?php echo esc_html(date_i18n('F j, Y', strtotime($card->created_at))); ?></p>
                                <?php if ($card->card_image): ?>
                                    <a href="<?php echo esc_url($card->card_image); ?>" download class="pc-btn pc-btn-download-sm">
                                        <?php _e('Download', 'personalized-cards'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <p class="pc-logout-link">
            <a href="<?php echo esc_url(wp_logout_url(get_permalink(get_option('pc_login_page_id')))); ?>">
                <?php _e('Log Out', 'personalized-cards'); ?>
            </a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}

// Legacy shortcodes kept for backward compatibility but hidden from users
add_shortcode('my_personalized_cards', 'pc_my_card_shortcode');
add_shortcode('pc_dashboard', 'pc_my_card_shortcode');
