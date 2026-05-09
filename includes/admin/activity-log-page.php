<?php
add_action('admin_menu', 'pc_add_activity_log_menu');
function pc_add_activity_log_menu() {
    add_submenu_page(
        'personalized-cards',
        __('Activity Log', 'personalized-cards'),
        __('Activity Log', 'personalized-cards'),
        'manage_options',
        'personalized-cards-log',
        'pc_activity_log_page'
    );
}

function pc_activity_log_page() {
    global $wpdb;

    // Clear log action
    if (isset($_POST['pc_clear_log']) && check_admin_referer('pc_clear_log_action')) {
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}pc_activity_log");
        echo '<div class="notice notice-success"><p>' . __('Activity log cleared.', 'personalized-cards') . '</p></div>';
    }

    $filter_action = sanitize_key($_GET['filter_action'] ?? '');
    $logs          = PC_Activity_Log::get_recent(200, $filter_action);

    $action_labels = array(
        'card_created'          => __('Card Created', 'personalized-cards'),
        'card_emailed'          => __('Card Emailed', 'personalized-cards'),
        'card_edited'           => __('Card Edited', 'personalized-cards'),
        'card_deleted'          => __('Card Deleted', 'personalized-cards'),
        'membership_expired'    => __('Membership Expired', 'personalized-cards'),
        'renewal_reminder_sent' => __('Renewal Reminder Sent', 'personalized-cards'),
        'membership_activated'  => __('Membership Activated', 'personalized-cards'),
        'csv_import'            => __('CSV Import', 'personalized-cards'),
    );

    $action_colors = array(
        'card_created'          => '#46b450',
        'card_emailed'          => '#0073aa',
        'card_edited'           => '#f0a500',
        'card_deleted'          => '#dc3232',
        'membership_expired'    => '#dc3232',
        'renewal_reminder_sent' => '#f0a500',
        'membership_activated'  => '#46b450',
        'csv_import'            => '#0073aa',
    );
    ?>
    <div class="wrap">
        <h1><?php _e('Activity Log', 'personalized-cards'); ?></h1>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <!-- Filter -->
            <form method="get">
                <input type="hidden" name="page" value="personalized-cards-log">
                <select name="filter_action" onchange="this.form.submit()">
                    <option value=""><?php _e('All actions', 'personalized-cards'); ?></option>
                    <?php foreach ($action_labels as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_action, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Clear log -->
            <form method="post">
                <?php wp_nonce_field('pc_clear_log_action'); ?>
                <button type="submit" name="pc_clear_log" class="button button-secondary"
                        onclick="return confirm('<?php esc_attr_e('Clear the entire activity log?', 'personalized-cards'); ?>')">
                    <?php _e('Clear Log', 'personalized-cards'); ?>
                </button>
            </form>
        </div>

        <?php if (!$logs): ?>
            <p><?php _e('No activity recorded yet.', 'personalized-cards'); ?></p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width:140px;"><?php _e('Date / Time', 'personalized-cards'); ?></th>
                <th style="width:160px;"><?php _e('Action', 'personalized-cards'); ?></th>
                <th style="width:200px;"><?php _e('User', 'personalized-cards'); ?></th>
                <th><?php _e('Note', 'personalized-cards'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $entry):
                $user  = $entry->user_id ? get_userdata($entry->user_id) : null;
                $label = $action_labels[$entry->action] ?? $entry->action;
                $color = $action_colors[$entry->action] ?? '#666';
            ?>
                <tr>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($entry->created_at))); ?></td>
                    <td><span style="color:<?php echo esc_attr($color); ?>;font-weight:600;"><?php echo esc_html($label); ?></span></td>
                    <td>
                        <?php if ($user): ?>
                            <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>"><?php echo esc_html($user->display_name); ?></a>
                            <br><small><?php echo esc_html($user->user_email); ?></small>
                        <?php elseif ($entry->user_id): ?>
                            <em><?php echo sprintf(__('User #%d (deleted)', 'personalized-cards'), absint($entry->user_id)); ?></em>
                        <?php else: ?>
                            <em><?php _e('System', 'personalized-cards'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($entry->note); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php _e('Showing last 200 entries.', 'personalized-cards'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
