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
                    <?php _e('Enable 2-year membership', 'personalized-cards'); ?>
                </label>
                <p class="description"><?php _e('Check this to grant user a 2-year membership from today.', 'personalized-cards'); ?></p>
            </td>
        </tr>
        <?php if ($expiry_date): ?>
        <tr>
            <th><?php _e('Expiry Date', 'personalized-cards'); ?></th>
            <td>
                <strong><?php echo date('F j, Y', strtotime($expiry_date)); ?></strong>
                <?php 
                $days_left = floor((strtotime($expiry_date) - time()) / (60 * 60 * 24));
                if ($days_left > 0) {
                    echo '<span style="color: green;"> (' . sprintf(__('%d days remaining', 'personalized-cards'), $days_left) . ')</span>';
                } else {
                    echo '<span style="color: red;"> (' . __('Expired', 'personalized-cards') . ')</span>';
                }
                ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}

// Save subscription fields
add_action('personal_options_update', 'pc_save_user_subscription_fields');
add_action('edit_user_profile_update', 'pc_save_user_subscription_fields');

function pc_save_user_subscription_fields($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $was_member = get_user_meta($user_id, 'pc_subscription_active', true);
    $is_member = isset($_POST['pc_subscription_active']) ? '1' : '0';
    
    update_user_meta($user_id, 'pc_subscription_active', $is_member);
    
    // If toggled ON and wasn't a member before, set 2-year expiry
    if ($is_member === '1' && $was_member !== '1') {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+2 years'));
        update_user_meta($user_id, 'pc_subscription_expiry', $expiry_date);
        update_user_meta($user_id, 'pc_subscription_started', current_time('mysql'));
    }
    
    // If toggled OFF, clear expiry
    if ($is_member === '0') {
        delete_user_meta($user_id, 'pc_subscription_expiry');
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
