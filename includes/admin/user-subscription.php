<?php
// Add subscription fields to user profile
add_action('show_user_profile', 'pc_user_subscription_fields');
add_action('edit_user_profile', 'pc_user_subscription_fields');

function pc_user_subscription_fields($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $is_member = get_user_meta($user->ID, 'pc_subscription_active', true);
    $expiry_date = get_user_meta($user->ID, 'pc_subscription_expiry', true);
    
    ?>
    <h3><?php _e('Personalized Cards Membership', 'personalized-cards'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="pc_subscription_active"><?php _e('Active Member', 'personalized-cards'); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="pc_subscription_active" id="pc_subscription_active" value="1" <?php checked($is_member, '1'); ?>>
                    <?php _e('Active Member', 'personalized-cards'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th><label for="pc_subscription_expiry"><?php _e('Expiry Date', 'personalized-cards'); ?></label></th>
            <td>
                <input type="date" name="pc_subscription_expiry" id="pc_subscription_expiry"
                       value="<?php echo $expiry_date ? esc_attr(date('Y-m-d', strtotime($expiry_date))) : ''; ?>">
                <?php if ($expiry_date):
                    $days_left = floor((strtotime($expiry_date) - time()) / (60 * 60 * 24));
                    if ($days_left > 0) {
                        echo '<span style="color:green;margin-left:8px;">' . sprintf(__('%d days remaining', 'personalized-cards'), $days_left) . '</span>';
                    } else {
                        echo '<span style="color:red;margin-left:8px;">' . __('Expired', 'personalized-cards') . '</span>';
                    }
                endif; ?>
                <p class="description"><?php _e('Set a custom expiry date. Leave blank for no expiry.', 'personalized-cards'); ?></p>
            </td>
        </tr>
    </table>

    <h3><?php _e('Personalized Cards — Card Fields', 'personalized-cards'); ?></h3>
    <?php
    $father_name  = get_user_meta($user->ID, 'pc_father_name', true);
    $sport        = get_user_meta($user->ID, 'pc_sport', true);
    $member_id    = get_user_meta($user->ID, 'pc_member_id', true);
    $member_image = get_user_meta($user->ID, 'pc_member_image', true);
    wp_enqueue_media();
    ?>
    <table class="form-table">
        <tr>
            <th><label for="pc_father_name"><?php _e('Father Name', 'personalized-cards'); ?></label></th>
            <td><input type="text" name="pc_father_name" id="pc_father_name" value="<?php echo esc_attr($father_name); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="pc_sport"><?php _e('Sport', 'personalized-cards'); ?></label></th>
            <td><input type="text" name="pc_sport" id="pc_sport" value="<?php echo esc_attr($sport); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="pc_member_id"><?php _e('Member ID', 'personalized-cards'); ?></label></th>
            <td>
                <input type="text" name="pc_member_id" id="pc_member_id" value="<?php echo esc_attr($member_id); ?>" class="regular-text">
                <p class="description"><?php _e('Leave blank to use the WordPress user ID.', 'personalized-cards'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="pc_member_image"><?php _e('Member Photo', 'personalized-cards'); ?></label></th>
            <td>
                <input type="text" name="pc_member_image" id="pc_member_image" value="<?php echo esc_attr($member_image); ?>" class="regular-text">
                <button type="button" class="button" id="pc_member_image_pick"><?php _e('Choose Image', 'personalized-cards'); ?></button>
                <button type="button" class="button" id="pc_member_image_clear"><?php _e('Remove', 'personalized-cards'); ?></button>
                <div style="margin-top:8px;">
                    <img id="pc_member_image_preview" src="<?php echo esc_url($member_image); ?>"
                         style="max-width:180px;height:auto;border:1px solid #ddd;<?php echo $member_image ? '' : 'display:none;'; ?>">
                </div>
            </td>
        </tr>
    </table>
    <script>
    jQuery(function($) {
        var frame;
        $('#pc_member_image_pick').on('click', function(e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: '<?php echo esc_js(__('Select Member Photo', 'personalized-cards')); ?>',
                button: { text: '<?php echo esc_js(__('Use this photo', 'personalized-cards')); ?>' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function() {
                var a = frame.state().get('selection').first().toJSON();
                $('#pc_member_image').val(a.url);
                $('#pc_member_image_preview').attr('src', a.url).show();
            });
            frame.open();
        });
        $('#pc_member_image_clear').on('click', function(e) {
            e.preventDefault();
            $('#pc_member_image').val('');
            $('#pc_member_image_preview').hide().attr('src', '');
        });
    });
    </script>
    <?php
}

// Save subscription fields
add_action('personal_options_update', 'pc_save_user_subscription_fields');
add_action('edit_user_profile_update', 'pc_save_user_subscription_fields');

function pc_save_user_subscription_fields($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $was_member      = get_user_meta($user_id, 'pc_subscription_active', true);
    $old_expiry      = get_user_meta($user_id, 'pc_subscription_expiry', true);
    $is_member       = isset($_POST['pc_subscription_active']) ? '1' : '0';

    update_user_meta($user_id, 'pc_subscription_active', $is_member);

    // Save custom expiry date
    $expiry_input = sanitize_text_field($_POST['pc_subscription_expiry'] ?? '');
    $just_activated = ($is_member === '1' && $was_member !== '1');
    if ($expiry_input && strtotime($expiry_input)) {
        $new_expiry = date('Y-m-d', strtotime($expiry_input));
        update_user_meta($user_id, 'pc_subscription_expiry', $new_expiry);

        if ($just_activated) {
            update_user_meta($user_id, 'pc_subscription_started', current_time('mysql'));
        }

        // Welcome email: send once when a user is first activated.
        if ($just_activated) {
            $welcome_sent_for = get_user_meta($user_id, 'pc_welcome_sent_at', true);
            if (!$welcome_sent_for) {
                $user = get_userdata($user_id);
                if ($user) {
                    $sent = PC_Email_Handler::send_template_email($user, 'welcome', array(
                        '{expiry_date}' => date_i18n('F j, Y', strtotime($new_expiry)),
                    ));
                    if ($sent) {
                        update_user_meta($user_id, 'pc_welcome_sent_at', current_time('mysql'));
                        PC_Activity_Log::log('welcome_email_sent', 'Welcome email sent.', $user_id);
                    } else {
                        $err = PC_Email_Handler::get_last_error();
                        PC_Activity_Log::log(
                            'welcome_email_failed',
                            'Welcome email failed: ' . ($err ?: 'unknown error'),
                            $user_id
                        );
                    }
                }
            }
        }

        // Send renewal confirmation if expiry date changed and member is active
        $expiry_changed = ($new_expiry !== date('Y-m-d', strtotime($old_expiry ?: '1970-01-01')));
        if ($is_member === '1' && $expiry_changed && !$just_activated) {
            $user = get_userdata($user_id);
            if ($user) {
                pc_send_renewal_confirmation($user, $new_expiry);
                PC_Activity_Log::log('renewal_confirmation_sent', 'Renewal confirmation sent. New expiry: ' . $new_expiry, $user_id);
            }
        }
    } elseif ($is_member === '0') {
        delete_user_meta($user_id, 'pc_subscription_expiry');
    }

    // Card field meta
    if (isset($_POST['pc_father_name'])) {
        update_user_meta($user_id, 'pc_father_name', sanitize_text_field($_POST['pc_father_name']));
    }
    if (isset($_POST['pc_sport'])) {
        update_user_meta($user_id, 'pc_sport', sanitize_text_field($_POST['pc_sport']));
    }
    if (isset($_POST['pc_member_id'])) {
        update_user_meta($user_id, 'pc_member_id', sanitize_text_field($_POST['pc_member_id']));
    }
    if (isset($_POST['pc_member_image'])) {
        update_user_meta($user_id, 'pc_member_image', esc_url_raw($_POST['pc_member_image']));
    }
}

// Add subscription column to users list
add_filter('manage_users_columns', 'pc_add_user_subscription_column');
function pc_add_user_subscription_column($columns) {
    $columns['pc_subscription'] = __('Card Membership', 'personalized-cards');
    return $columns;
}

add_filter('manage_users_custom_column', 'pc_show_user_subscription_column', 10, 3);
function pc_show_user_subscription_column($value, $column_name, $user_id) {
    if ($column_name === 'pc_subscription') {
        $is_member = get_user_meta($user_id, 'pc_subscription_active', true);
        $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
        
        if ($is_member === '1' && $expiry_date) {
            $days_left = floor((strtotime($expiry_date) - time()) / (60 * 60 * 24));
            if ($days_left > 0) {
                return '<span style="color: green;">✓ Active (' . $days_left . ' days)</span>';
            } else {
                return '<span style="color: red;">✗ Expired</span>';
            }
        }
        return '<span style="color: gray;">—</span>';
    }
    return $value;
}

function pc_send_renewal_confirmation($user, $expiry_date) {
    $from_name  = get_option('pc_email_from_name', get_bloginfo('name'));
    $from_email = get_option('pc_email_from_address', get_bloginfo('admin_email'));
    $site_name  = get_bloginfo('name');
    $expiry_label = date_i18n('F j, Y', strtotime($expiry_date));

    $subject = sprintf(__('[%s] Your membership has been renewed', 'personalized-cards'), $site_name);

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
    <body style="font-family:Arial,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:#0073aa;color:#fff;padding:20px;text-align:center;">
            <h1>' . esc_html($site_name) . '</h1>
        </div>
        <div style="padding:30px;background:#f9f9f9;">
            <p>' . sprintf(esc_html__('Dear %s,', 'personalized-cards'), esc_html($user->display_name)) . '</p>
            <p>' . esc_html__('Your membership has been successfully renewed.', 'personalized-cards') . '</p>
            <p style="font-size:18px;font-weight:bold;text-align:center;padding:16px;background:#fff;border:1px solid #ddd;border-radius:4px;">
                ' . sprintf(esc_html__('Valid until: %s', 'personalized-cards'), esc_html($expiry_label)) . '
            </p>
            <p>' . esc_html__('You can log in at any time to view and download your membership card.', 'personalized-cards') . '</p>
        </div>
        <div style="text-align:center;padding:20px;font-size:12px;color:#666;">
            &copy; ' . date('Y') . ' ' . esc_html($site_name) . '
        </div>
    </div></body></html>';

    wp_mail(
        $user->user_email,
        $subject,
        $body,
        array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        )
    );
}
