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

    // ── Presentation data ──────────────────────────────────────────
    $member_photo = get_user_meta($user_id, 'pc_member_image', true);
    $member_id    = get_user_meta($user_id, 'pc_member_id', true);
    $started      = get_user_meta($user_id, 'pc_subscription_started', true);

    if ($is_expired) {
        $status_key = 'expired';
        $status_lbl = __('Expired', 'personalized-cards');
    } elseif ($expiry_date && $days_left <= 30) {
        $status_key = 'soon';
        $status_lbl = __('Expiring soon', 'personalized-cards');
    } else {
        $status_key = 'active';
        $status_lbl = __('Active', 'personalized-cards');
    }

    // Days-left meter: proportion of the term remaining (falls back to a 1-year term).
    $meter_pct = 0;
    if (!$is_expired && $expiry_date) {
        $term = ($started && strtotime($started))
            ? max(1, (strtotime($expiry_date) - strtotime($started)) / DAY_IN_SECONDS)
            : 365;
        $meter_pct = max(4, min(100, round(($days_left / $term) * 100)));
    }

    ob_start();
    ?>
    <div class="pc-my-card-wrap pc-status-<?php echo esc_attr($status_key); ?>">

        <header class="pc-mc-header">
            <div class="pc-mc-avatar">
                <?php if ($member_photo): ?>
                    <img src="<?php echo esc_url($member_photo); ?>" alt="">
                <?php else: ?>
                    <?php echo get_avatar($user_id, 64); ?>
                <?php endif; ?>
            </div>
            <div class="pc-mc-identity">
                <h2 class="pc-mc-name"><?php echo esc_html($user->display_name); ?></h2>
                <?php if ($member_id): ?>
                    <p class="pc-mc-memberid"><?php echo esc_html(sprintf(__('Member #%s', 'personalized-cards'), $member_id)); ?></p>
                <?php endif; ?>
            </div>
            <span class="pc-status-badge pc-badge-<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_lbl); ?></span>
        </header>

        <div class="pc-mc-status">
            <?php if (!$is_expired): ?>
                <div class="pc-meter" role="img" aria-label="<?php echo esc_attr(sprintf(__('%d days remaining', 'personalized-cards'), max(0, $days_left))); ?>">
                    <span class="pc-meter-fill" style="width:<?php echo (int) $meter_pct; ?>%"></span>
                </div>
                <p class="pc-mc-status-text">
                    <?php echo esc_html(sprintf(
                        _n('%1$d day left · expires %2$s', '%1$d days left · expires %2$s', max(0, $days_left), 'personalized-cards'),
                        max(0, $days_left),
                        date_i18n('F j, Y', strtotime($expiry_date))
                    )); ?>
                </p>
            <?php else: ?>
                <p class="pc-mc-status-text pc-mc-expired-text">
                    <?php _e('Your membership has expired. Please contact an administrator to renew.', 'personalized-cards'); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (empty($cards)): ?>
            <div class="pc-no-card">
                <p><?php _e('Your card has not been created yet. Please check back later.', 'personalized-cards'); ?></p>
            </div>
        <?php else:
            $latest    = $cards[0];
            $card_data = json_decode($latest->card_data, true) ?: array('name' => $user->display_name);
            $has_back  = !empty($latest->card_back_image);
            ?>
            <div class="pc-card-display">
                <?php if ($latest->card_image): ?>
                    <div class="pc-card-viewer">
                        <div class="pc-card-stage <?php echo $is_expired ? 'pc-card-expired' : ''; ?>">
                            <img class="pc-card-face is-active" data-face="front"
                                 src="<?php echo esc_url($latest->card_image); ?>"
                                 alt="<?php esc_attr_e('Your Membership Card', 'personalized-cards'); ?>">
                            <?php if ($has_back): ?>
                                <img class="pc-card-face" data-face="back"
                                     src="<?php echo esc_url($latest->card_back_image); ?>"
                                     alt="<?php esc_attr_e('Card Back', 'personalized-cards'); ?>">
                            <?php endif; ?>
                            <?php if ($is_expired): ?>
                                <div class="pc-expired-overlay" aria-hidden="true"><span><?php _e('EXPIRED', 'personalized-cards'); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_back): ?>
                            <div class="pc-flip-tabs">
                                <button type="button" class="pc-flip-tab is-active" data-face="front"><?php _e('Front', 'personalized-cards'); ?></button>
                                <button type="button" class="pc-flip-tab" data-face="back"><?php _e('Back', 'personalized-cards'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="pc-card-date">
                    <?php echo esc_html(sprintf(__('Issued %s', 'personalized-cards'), date_i18n('F j, Y', strtotime($latest->created_at)))); ?>
                </p>

                <div class="pc-card-actions">
                    <?php if ($latest->card_image && !$is_expired): ?>
                        <a href="<?php echo esc_url($latest->card_image); ?>" download class="pc-btn pc-btn-download">
                            <span class="pc-btn-ico" aria-hidden="true">⬇</span><?php _e('Download', 'personalized-cards'); ?>
                        </a>
                    <?php elseif ($latest->card_image && $is_expired): ?>
                        <span class="pc-btn pc-btn-disabled" title="<?php esc_attr_e('Renew your membership to download', 'personalized-cards'); ?>">
                            <?php _e('Download', 'personalized-cards'); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (get_option('pc_enable_apple_wallet') && !$is_expired):
                        $apple_url = wp_nonce_url(
                            admin_url('admin-ajax.php?action=pc_apple_wallet&card_id=' . absint($latest->id)),
                            'pc_apple_wallet_' . $latest->id
                        ); ?>
                        <a href="<?php echo esc_url($apple_url); ?>" class="pc-btn pc-btn-apple-wallet">
                            <?php _e('Add to Apple Wallet', 'personalized-cards'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (get_option('pc_enable_google_wallet') && !$is_expired):
                        $gw_link = PC_Wallet_Handler::create_google_wallet_link($card_data, $user_id, $latest->card_image);
                        $gw_link = is_wp_error($gw_link) ? false : $gw_link;
                        if ($gw_link): ?>
                            <a href="<?php echo esc_url($gw_link); ?>" target="_blank" rel="noopener" class="pc-btn pc-btn-google-wallet">
                                <?php _e('Add to Google Wallet', 'personalized-cards'); ?>
                            </a>
                        <?php endif;
                    endif; ?>
                </div>

                <?php if ($is_expired): ?>
                    <p class="pc-renew-msg"><?php _e('Contact an administrator to renew your membership and restore full access.', 'personalized-cards'); ?></p>
                <?php endif; ?>
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
                                <p><?php echo esc_html(date_i18n('M j, Y', strtotime($card->created_at))); ?></p>
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
            <a href="<?php echo esc_url(wp_logout_url(get_permalink(get_option('pc_login_page_id')))); ?>"><?php _e('Log Out', 'personalized-cards'); ?></a>
        </p>
    </div>

    <?php if (!empty($latest) && !empty($latest->card_back_image)): ?>
    <script>
    (function(){
        document.querySelectorAll('.pc-my-card-wrap .pc-flip-tabs').forEach(function(tabs){
            var viewer = tabs.closest('.pc-card-viewer');
            tabs.querySelectorAll('.pc-flip-tab').forEach(function(tab){
                tab.addEventListener('click', function(){
                    var face = tab.getAttribute('data-face');
                    tabs.querySelectorAll('.pc-flip-tab').forEach(function(t){ t.classList.toggle('is-active', t === tab); });
                    viewer.querySelectorAll('.pc-card-face').forEach(function(img){ img.classList.toggle('is-active', img.getAttribute('data-face') === face); });
                });
            });
        });
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// Legacy shortcodes kept for backward compatibility but hidden from users
add_shortcode('my_personalized_cards', 'pc_my_card_shortcode');
add_shortcode('pc_dashboard', 'pc_my_card_shortcode');

// ── [pc_member_photo_upload] — frontend self-service photo upload ────────────
add_shortcode('pc_member_photo_upload', 'pc_member_photo_upload_shortcode');
function pc_member_photo_upload_shortcode($atts = array()) {
    if (!is_user_logged_in()) {
        return '<p class="pc-photo-upload-login">'
             . esc_html__('Please log in to upload your photo.', 'personalized-cards')
             . '</p>';
    }

    $user_id   = get_current_user_id();
    $current   = (string) get_user_meta($user_id, 'pc_member_image', true);
    $max_bytes = (int) apply_filters('pc_member_photo_max_bytes', 5 * 1024 * 1024); // 5 MB
    $max_mb    = round($max_bytes / 1024 / 1024, 1);

    ob_start();
    ?>
    <div class="pc-photo-upload-wrap" data-max-bytes="<?php echo esc_attr($max_bytes); ?>">
        <h3 class="pc-photo-upload-title"><?php esc_html_e('Your Photo', 'personalized-cards'); ?></h3>

        <div class="pc-photo-upload-preview-wrap">
            <img class="pc-photo-upload-preview"
                 src="<?php echo esc_url($current); ?>"
                 alt="<?php esc_attr_e('Current member photo', 'personalized-cards'); ?>"
                 style="max-width:180px;height:auto;border:1px solid #ddd;<?php echo $current ? '' : 'display:none;'; ?>">
            <p class="pc-photo-upload-empty" <?php echo $current ? 'style="display:none;"' : ''; ?>>
                <?php esc_html_e('No photo on file yet.', 'personalized-cards'); ?>
            </p>
        </div>

        <form class="pc-photo-upload-form" enctype="multipart/form-data" style="margin-top:12px;">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif" required>
            <button type="submit" class="pc-btn pc-btn-upload"><?php esc_html_e('Upload', 'personalized-cards'); ?></button>
            <span class="pc-photo-upload-status" aria-live="polite" style="margin-left:8px;"></span>
        </form>

        <p class="pc-photo-upload-help" style="font-size:13px;color:#666;margin-top:8px;">
            <?php echo esc_html(sprintf(
                __('JPG, PNG or GIF. Max %s MB. Photo applies to your next card.', 'personalized-cards'),
                $max_mb
            )); ?>
        </p>
    </div>
    <script>
    (function($){
        if (typeof pcAjax === 'undefined') return;
        $(function(){
            $('.pc-photo-upload-wrap').each(function(){
                var $wrap    = $(this);
                var maxBytes = parseInt($wrap.data('max-bytes'), 10) || 0;
                var $form    = $wrap.find('.pc-photo-upload-form');
                var $status  = $wrap.find('.pc-photo-upload-status');
                var $preview = $wrap.find('.pc-photo-upload-preview');
                var $empty   = $wrap.find('.pc-photo-upload-empty');

                $form.on('submit', function(e){
                    e.preventDefault();
                    var input = $form.find('input[type=file]')[0];
                    if (!input || !input.files || !input.files.length) return;
                    var file = input.files[0];

                    if (maxBytes && file.size > maxBytes) {
                        $status.css('color', '#a00').text('<?php echo esc_js(__('File is too large.', 'personalized-cards')); ?>');
                        return;
                    }

                    var fd = new FormData();
                    fd.append('action', 'pc_upload_member_photo');
                    fd.append('nonce',  pcAjax.nonce);
                    fd.append('photo',  file);

                    var $btn = $form.find('button');
                    $btn.prop('disabled', true);
                    $status.css('color', '#555').text('<?php echo esc_js(__('Uploading…', 'personalized-cards')); ?>');

                    $.ajax({
                        url:         pcAjax.ajaxurl,
                        method:      'POST',
                        data:        fd,
                        processData: false,
                        contentType: false
                    }).done(function(res){
                        if (res && res.success && res.data && res.data.url) {
                            $preview.attr('src', res.data.url + '?t=' + Date.now()).show();
                            $empty.hide();
                            $status.css('color', 'green').text(res.data.message || '<?php echo esc_js(__('Photo updated.', 'personalized-cards')); ?>');
                            $form[0].reset();
                        } else {
                            var msg = (res && res.data && res.data.message) || '<?php echo esc_js(__('Upload failed.', 'personalized-cards')); ?>';
                            $status.css('color', '#a00').text(msg);
                        }
                    }).fail(function(xhr){
                        $status.css('color', '#a00').text('<?php echo esc_js(__('Upload failed', 'personalized-cards')); ?>' + ' (' + (xhr.status||'?') + ')');
                    }).always(function(){
                        $btn.prop('disabled', false);
                    });
                });
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX: handle frontend member-photo upload ────────────────────────────────
add_action('wp_ajax_pc_upload_member_photo', 'pc_ajax_upload_member_photo');
function pc_ajax_upload_member_photo() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in to upload a photo.', 'personalized-cards')));
    }
    check_ajax_referer('pc_nonce', 'nonce');

    if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) {
        wp_send_json_error(array('message' => __('No file provided.', 'personalized-cards')));
    }
    $file = $_FILES['photo'];
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => __('Upload failed: server reported an error.', 'personalized-cards')));
    }

    // Size cap (defaults to 5 MB; filterable).
    $max_bytes = (int) apply_filters('pc_member_photo_max_bytes', 5 * 1024 * 1024);
    if ((int) $file['size'] > $max_bytes) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('File is too large. Max %s MB.', 'personalized-cards'),
                round($max_bytes / 1024 / 1024, 1)
            ),
        ));
    }

    // MIME whitelist — verify magic bytes via WP helper, not just filename.
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (empty($check['type']) || strpos($check['type'], 'image/') !== 0) {
        wp_send_json_error(array('message' => __('Only image files (JPG, PNG, GIF) are allowed.', 'personalized-cards')));
    }
    $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif');
    if (!in_array($check['type'], $allowed_mimes, true)) {
        wp_send_json_error(array('message' => __('Only JPG, PNG, and GIF images are allowed.', 'personalized-cards')));
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $overrides = array(
        'test_form' => false,
        'mimes'     => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif'          => 'image/gif',
            'png'          => 'image/png',
        ),
    );
    $upload = wp_handle_upload($file, $overrides);
    if (isset($upload['error'])) {
        wp_send_json_error(array('message' => $upload['error']));
    }

    $user_id = get_current_user_id();
    update_user_meta($user_id, 'pc_member_image', esc_url_raw($upload['url']));

    if (class_exists('PC_Activity_Log')) {
        PC_Activity_Log::log('member_photo_uploaded', 'Member uploaded a new photo via frontend.', $user_id);
    }

    wp_send_json_success(array(
        'url'     => $upload['url'],
        'message' => __('Photo updated. Your next card will use this image.', 'personalized-cards'),
    ));
}
