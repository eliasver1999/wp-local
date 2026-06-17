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

        $today          = current_time('Y-m-d');
        $reminder_day   = date('Y-m-d', strtotime($today . ' +10 days'));

        $rows = $wpdb->get_results(
            "SELECT user_id, meta_value AS expiry
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'pc_subscription_expiry'
               AND meta_value != ''"
        );

        // How long after an expiry date we keep retrying the expiration email if
        // it hasn't gone through yet. Filterable so operators can extend it.
        $expiration_retry_days = (int) apply_filters('pc_expiration_email_retry_days', 7);

        foreach ($rows as $row) {
            $user_id = (int) $row->user_id;
            $expiry  = $row->expiry;

            if (!$expiry || !strtotime($expiry)) continue;

            $expiry_date  = date('Y-m-d', strtotime($expiry));
            $expiry_label = date_i18n('F j, Y', strtotime($expiry_date));
            $is_active    = get_user_meta($user_id, 'pc_subscription_active', true);

            // ── Auto-expire (idempotent: only flips active=1 → active=0 once) ──
            if ($expiry_date < $today && $is_active === '1') {
                update_user_meta($user_id, 'pc_subscription_active', '0');
                PC_Activity_Log::log('membership_expired', 'Auto-expired. Expiry was ' . $expiry_date, $user_id);
                // Push the now-expired state to saved wallet passes.
                do_action('pc_membership_changed', $user_id);
            }

            // ── Expiration email — gated on sent_for (not is_active) so we
            //    keep retrying for a few days if a previous send failed. ──
            if ($expiry_date < $today) {
                $sent_for = get_user_meta($user_id, 'pc_expiration_sent_for', true);
                if ($sent_for !== $expiry_date) {
                    $days_since = (int) floor((strtotime($today) - strtotime($expiry_date)) / DAY_IN_SECONDS);
                    if ($days_since <= $expiration_retry_days) {
                        $user = get_userdata($user_id);
                        if ($user) {
                            $sent = PC_Email_Handler::send_template_email($user, 'expiration', array(
                                '{expiry_date}' => $expiry_label,
                            ));
                            if ($sent) {
                                update_user_meta($user_id, 'pc_expiration_sent_for', $expiry_date);
                                PC_Activity_Log::log('expiration_email_sent', 'Expiration email sent. Expiry: ' . $expiry_date, $user_id);
                            } else {
                                $err = PC_Email_Handler::get_last_error();
                                PC_Activity_Log::log(
                                    'expiration_email_failed',
                                    sprintf('Expiration email failed (day %d/%d): %s. Expiry: %s',
                                        $days_since, $expiration_retry_days, ($err ?: 'unknown error'), $expiry_date),
                                    $user_id
                                );
                            }
                        }
                    }
                }
                continue; // already-expired members skip the reminder logic
            }

            // ── 10-day reminder: only on the trigger day, once per expiry ──
            if ($expiry_date === $reminder_day && $is_active === '1') {
                $sent_for = get_user_meta($user_id, 'pc_reminder_10_sent_for', true);
                if ($sent_for === $expiry_date) continue;

                $user = get_userdata($user_id);
                if (!$user) continue;

                $days_left = max(0, (int) round((strtotime($expiry_date) - strtotime($today)) / DAY_IN_SECONDS));
                $sent = PC_Email_Handler::send_template_email($user, 'reminder_10', array(
                    '{expiry_date}' => $expiry_label,
                    '{days_left}'   => (string) $days_left,
                ));
                if ($sent) {
                    update_user_meta($user_id, 'pc_reminder_10_sent_for', $expiry_date);
                    // Keep the legacy "last sent today" key for any external code that checks it.
                    update_user_meta($user_id, 'pc_reminder_sent', $today);
                    PC_Activity_Log::log('renewal_reminder_sent', '10-day renewal reminder sent. Expiry: ' . $expiry_date, $user_id);
                } else {
                    $err = PC_Email_Handler::get_last_error();
                    PC_Activity_Log::log(
                        'renewal_reminder_failed',
                        sprintf('10-day reminder failed: %s. Expiry: %s', ($err ?: 'unknown error'), $expiry_date),
                        $user_id
                    );
                }
            }
        }
    }
}
