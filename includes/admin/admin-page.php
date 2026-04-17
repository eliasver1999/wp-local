<?php
// Add admin menu
add_action('admin_menu', 'pc_add_admin_menu');
function pc_add_admin_menu() {
    add_menu_page(
        __('Personalized Cards', 'personalized-cards'),
        __('Personalized Cards', 'personalized-cards'),
        'manage_options',
        'personalized-cards',
        'pc_admin_page',
        'dashicons-images-alt2',
        30
    );
    
    add_submenu_page(
        'personalized-cards',
        __('Settings', 'personalized-cards'),
        __('Settings', 'personalized-cards'),
        'manage_options',
        'personalized-cards-settings',
        'pc_settings_page'
    );
    
    add_submenu_page(
        'personalized-cards',
        __('All Cards', 'personalized-cards'),
        __('All Cards', 'personalized-cards'),
        'manage_options',
        'personalized-cards-all',
        'pc_all_cards_page'
    );
}

// Main admin page
function pc_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'personalized_cards';
    
    // Get statistics
    $total_cards = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
    $cards_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
        current_time('Y-m-d')
    ));
    $active_members = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'");
    
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards Dashboard', 'personalized-cards'); ?></h1>
        
        <div class="pc-admin-stats">
            <div class="pc-stat-box">
                <h3><?php echo number_format($total_cards); ?></h3>
                <p><?php _e('Total Cards Created', 'personalized-cards'); ?></p>
            </div>
            
            <div class="pc-stat-box">
                <h3><?php echo number_format($cards_today); ?></h3>
                <p><?php _e('Cards Today', 'personalized-cards'); ?></p>
            </div>
            
            <div class="pc-stat-box">
                <h3><?php echo number_format($active_members); ?></h3>
                <p><?php _e('Active Members', 'personalized-cards'); ?></p>
            </div>
            
            <div class="pc-stat-box">
                <h3><?php echo number_format($total_users); ?></h3>
                <p><?php _e('Total Users', 'personalized-cards'); ?></p>
            </div>
        </div>
        
        <h2><?php _e('Recent Cards', 'personalized-cards'); ?></h2>
        <?php
        $recent_cards = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
        
        if ($recent_cards) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . __('ID', 'personalized-cards') . '</th>';
            echo '<th>' . __('User', 'personalized-cards') . '</th>';
            echo '<th>' . __('Template', 'personalized-cards') . '</th>';
            echo '<th>' . __('Created', 'personalized-cards') . '</th>';
            echo '<th>' . __('Preview', 'personalized-cards') . '</th>';
            echo '<th>' . __('Actions', 'personalized-cards') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($recent_cards as $card) {
                $user = get_userdata($card->user_id);
                echo '<tr>';
                echo '<td>' . $card->id . '</td>';
                echo '<td>' . ($user ? $user->display_name : 'Unknown') . '</td>';
                echo '<td>' . esc_html($card->card_template) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($card->created_at)) . '</td>';
                echo '<td>';
                if ($card->card_image) {
                    echo '<a href="' . esc_url($card->card_image) . '" target="_blank">View</a>';
                }
                echo '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($card->card_image) . '" download class="button">Download</a> ';
                echo '<button class="button pc-delete-card" data-card-id="' . $card->id . '">Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No cards created yet.', 'personalized-cards') . '</p>';
        }
        ?>
    </div>
    <?php
}

// Settings page
function pc_settings_page() {
    if (isset($_POST['pc_save_settings'])) {
        check_admin_referer('pc_settings_save');
        
        update_option('pc_email_from_name', sanitize_text_field($_POST['pc_email_from_name']));
        update_option('pc_email_from_address', sanitize_email($_POST['pc_email_from_address']));
        update_option('pc_email_subject', sanitize_text_field($_POST['pc_email_subject']));
        update_option('pc_email_message', wp_kses_post($_POST['pc_email_message']));
        update_option('pc_enable_apple_wallet', isset($_POST['pc_enable_apple_wallet']) ? '1' : '0');
        update_option('pc_enable_google_wallet', isset($_POST['pc_enable_google_wallet']) ? '1' : '0');
        update_option('pc_google_wallet_issuer_id', sanitize_text_field($_POST['pc_google_wallet_issuer_id']));
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'personalized-cards') . '</p></div>';
    }
    
    $email_from_name = get_option('pc_email_from_name', get_bloginfo('name'));
    $email_from_address = get_option('pc_email_from_address', get_bloginfo('admin_email'));
    $email_subject = get_option('pc_email_subject', __('Your Personalized Card', 'personalized-cards'));
    $email_message = get_option('pc_email_message', __('Please find your personalized card attached to this email.', 'personalized-cards'));
    $enable_apple_wallet = get_option('pc_enable_apple_wallet', '0');
    $enable_google_wallet = get_option('pc_enable_google_wallet', '0');
    $google_wallet_issuer_id = get_option('pc_google_wallet_issuer_id', '');
    
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards Settings', 'personalized-cards'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('pc_settings_save'); ?>
            
            <h2><?php _e('Email Settings', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="pc_email_from_name"><?php _e('From Name', 'personalized-cards'); ?></label></th>
                    <td><input type="text" name="pc_email_from_name" id="pc_email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_from_address"><?php _e('From Email', 'personalized-cards'); ?></label></th>
                    <td><input type="email" name="pc_email_from_address" id="pc_email_from_address" value="<?php echo esc_attr($email_from_address); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_subject"><?php _e('Email Subject', 'personalized-cards'); ?></label></th>
                    <td><input type="text" name="pc_email_subject" id="pc_email_subject" value="<?php echo esc_attr($email_subject); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_message"><?php _e('Email Message', 'personalized-cards'); ?></label></th>
                    <td>
                        <textarea name="pc_email_message" id="pc_email_message" rows="5" class="large-text"><?php echo esc_textarea($email_message); ?></textarea>
                        <p class="description">Use {name} for user name, {site_name} for site name</p>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Digital Wallet Settings', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('Apple Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_apple_wallet" value="1" <?php checked($enable_apple_wallet, '1'); ?>>
                            <?php _e('Enable Apple Wallet integration', 'personalized-cards'); ?>
                        </label>
                        <p class="description"><?php _e('Requires certificate configuration. See documentation.', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Google Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_google_wallet" value="1" <?php checked($enable_google_wallet, '1'); ?>>
                            <?php _e('Enable Google Wallet integration', 'personalized-cards'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_google_wallet_issuer_id"><?php _e('Google Wallet Issuer ID', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_google_wallet_issuer_id" id="pc_google_wallet_issuer_id" value="<?php echo esc_attr($google_wallet_issuer_id); ?>" class="regular-text">
                        <p class="description"><?php _e('Get this from Google Wallet API Console', 'personalized-cards'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Settings', 'personalized-cards'), 'primary', 'pc_save_settings'); ?>
        </form>
    </div>
    <?php
}

// All cards page
function pc_all_cards_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'personalized_cards';
    
    $cards = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    
    ?>
    <div class="wrap">
        <h1><?php _e('All Cards', 'personalized-cards'); ?></h1>
        
        <?php if ($cards): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'personalized-cards'); ?></th>
                        <th><?php _e('User', 'personalized-cards'); ?></th>
                        <th><?php _e('Template', 'personalized-cards'); ?></th>
                        <th><?php _e('Data', 'personalized-cards'); ?></th>
                        <th><?php _e('Created', 'personalized-cards'); ?></th>
                        <th><?php _e('Preview', 'personalized-cards'); ?></th>
                        <th><?php _e('Actions', 'personalized-cards'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): 
                        $user = get_userdata($card->user_id);
                        $data = json_decode($card->card_data, true);
                    ?>
                        <tr>
                            <td><?php echo $card->id; ?></td>
                            <td><?php echo $user ? $user->display_name : 'Unknown'; ?></td>
                            <td><?php echo esc_html($card->card_template); ?></td>
                            <td>
                                <?php 
                                if ($data) {
                                    echo '<strong>Name:</strong> ' . esc_html($data['name']) . '<br>';
                                    if (!empty($data['message'])) {
                                        echo '<strong>Message:</strong> ' . esc_html(substr($data['message'], 0, 50));
                                        if (strlen($data['message']) > 50) echo '...';
                                        echo '<br>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($card->created_at)); ?></td>
                            <td>
                                <?php if ($card->card_image): ?>
                                    <a href="<?php echo esc_url($card->card_image); ?>" target="_blank">
                                        <img src="<?php echo esc_url($card->card_image); ?>" style="max-width: 100px; height: auto;">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($card->card_image); ?>" download class="button"><?php _e('Download', 'personalized-cards'); ?></a>
                                <button class="button pc-delete-card" data-card-id="<?php echo $card->id; ?>"><?php _e('Delete', 'personalized-cards'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No cards found.', 'personalized-cards'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// AJAX: Delete card
add_action('wp_ajax_pc_delete_card', 'pc_ajax_delete_card');
function pc_ajax_delete_card() {
    check_ajax_referer('pc_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    global $wpdb;
    $card_id = intval($_POST['card_id']);
    $table_name = $wpdb->prefix . 'personalized_cards';
    
    // Get card image path to delete file
    $card = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $card_id));
    
    if ($card && $card->card_image) {
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $deleted = $wpdb->delete($table_name, array('id' => $card_id), array('%d'));
    
    if ($deleted) {
        wp_send_json_success(array('message' => 'Card deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete card'));
    }
}
