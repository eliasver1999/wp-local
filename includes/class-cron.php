<?php
class PC_Cron {

    public static function register() {
        add_action('pc_daily_cron', array(__CLASS__, 'run_daily'));

        if (!wp_next_scheduled('pc_daily_cron')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'pc_daily_cron');
        }
    }

    public static function unregister() {
        $ts = wp_next_scheduled('pc_daily_cron');
        if ($ts) wp_unschedule_event($ts, 'pc_daily_cron');
    }

    public static function run_daily() {
        global $wpdb;

        $today        = current_time('Y-m-d');
        $reminder_day = date('Y-m-d', strtotime($today . ' +30 days'));

        $active_users = $wpdb->get_results(
            "SELECT user_id, meta_value AS expiry
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'pc_subscription_expiry'
               AND meta_value != ''"
        );

        foreach ($active_users as $row) {
            $user_id = (int) $row->user_id;
            $expiry  = $row->expiry;

            if (!$expiry || !strtotime($expiry)) continue;

            $expiry_date = date('Y-m-d', strtotime($expiry));
            $is_active   = get_user_meta($user_id, 'pc_subscription_active', true);

            // Auto-expire
            if ($expiry_date < $today && $is_active === '1') {
                update_user_meta($user_id, 'pc_subscription_active', '0');
                PC_Activity_Log::log('membership_expired', 'Auto-expired. Expiry was ' . $expiry_date, $user_id);
                continue;
            }

            // 30-day reminder — only if active and not already sent today
            if ($expiry_date === $reminder_day && $is_active === '1') {
                $last_reminder = get_user_meta($user_id, 'pc_reminder_sent', true);
                if ($last_reminder === $today) continue; // already sent today

                $user = get_userdata($user_id);
                if (!$user) continue;

                self::send_renewal_reminder($user, $expiry_date);
                update_user_meta($user_id, 'pc_reminder_sent', $today);
                PC_Activity_Log::log('renewal_reminder_sent', '30-day renewal reminder sent. Expiry: ' . $expiry_date, $user_id);
            }
        }
    }

    private static function send_renewal_reminder($user, $expiry_date) {
        $from_name    = get_option('pc_email_from_name', get_bloginfo('name'));
        $from_email   = get_option('pc_email_from_address', get_bloginfo('admin_email'));
        $site_name    = get_bloginfo('name');
        $expiry_label = date_i18n('F j, Y', strtotime($expiry_date));

        $subject = sprintf(__('[%s] Your membership expires in 30 days', 'personalized-cards'), $site_name);

        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;">
        <div style="max-width:600px;margin:0 auto;padding:20px;">
            <div style="background:#0073aa;color:#fff;padding:20px;text-align:center;">
                <h1>' . esc_html($site_name) . '</h1>
            </div>
            <div style="padding:30px;background:#f9f9f9;">
                <p>' . sprintf(esc_html__('Dear %s,', 'personalized-cards'), esc_html($user->display_name)) . '</p>
                <p>' . sprintf(esc_html__('This is a friendly reminder that your membership at %1$s will expire on %2$s (in 30 days).', 'personalized-cards'), esc_html($site_name), '<strong>' . esc_html($expiry_label) . '</strong>') . '</p>
                <p>' . esc_html__('Please contact an administrator to renew your membership and keep access to your card.', 'personalized-cards') . '</p>
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
}
