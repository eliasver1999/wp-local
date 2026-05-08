<?php
class PC_Email_Handler {

    // ── Configurable email templates (welcome / reminder_10 / expiration) ──────
    // The "get_card" template lives in legacy options pc_email_subject / pc_email_message
    // and is handled by send_card_email() (with attachments) below.
    public static function get_default_templates() {
        return array(
            'welcome' => array(
                'subject' => __('Welcome to {site_name}!', 'personalized-cards'),
                'message' => __(
                    "<p>Hi {name},</p>\n<p>Welcome to {site_name}! Your membership is now active.</p>\n<p>Your membership is valid until <strong>{expiry_date}</strong>.</p>\n<p>You can sign in any time at <a href=\"{login_url}\">{login_url}</a> to view and download your card.</p>\n<p>— The {site_name} team</p>",
                    'personalized-cards'
                ),
            ),
            'reminder_10' => array(
                'subject' => __('[{site_name}] Your membership expires in {days_left} days', 'personalized-cards'),
                'message' => __(
                    "<p>Hi {name},</p>\n<p>This is a friendly reminder that your membership at {site_name} will expire on <strong>{expiry_date}</strong> ({days_left} days from today).</p>\n<p>To keep your card active, please contact an administrator to renew.</p>\n<p>— The {site_name} team</p>",
                    'personalized-cards'
                ),
            ),
            'expiration' => array(
                'subject' => __('[{site_name}] Your membership has expired', 'personalized-cards'),
                'message' => __(
                    "<p>Hi {name},</p>\n<p>Your membership at {site_name} expired on <strong>{expiry_date}</strong>.</p>\n<p>To renew and keep access to your card, please contact an administrator.</p>\n<p>— The {site_name} team</p>",
                    'personalized-cards'
                ),
            ),
        );
    }

    public static function get_template_keys() {
        return array_keys(self::get_default_templates());
    }

    public static function get_template_subject($key) {
        $defaults = self::get_default_templates();
        if (!isset($defaults[$key])) return '';
        return get_option("pc_email_{$key}_subject", $defaults[$key]['subject']);
    }

    public static function get_template_message($key) {
        $defaults = self::get_default_templates();
        if (!isset($defaults[$key])) return '';
        return get_option("pc_email_{$key}_message", $defaults[$key]['message']);
    }

    /**
     * Render and send a configurable template email to a user.
     * $key: 'welcome' | 'reminder_10' | 'expiration'
     * $extra_tokens: additional placeholder => value (e.g. {expiry_date})
     */
    public static function send_template_email($user, $key, $extra_tokens = array()) {
        if (!is_object($user) || empty($user->user_email)) return false;

        $subject = self::get_template_subject($key);
        $message = self::get_template_message($key);
        if ($subject === '' || $message === '') return false;

        $login_page_id   = (int) get_option('pc_login_page_id', 0);
        $my_card_page_id = (int) get_option('pc_my_card_page_id', 0);
        $login_url       = $login_page_id   ? get_permalink($login_page_id)   : wp_login_url();
        $my_card_url     = $my_card_page_id ? get_permalink($my_card_page_id) : '';

        $tokens = array_merge(array(
            '{name}'        => isset($user->display_name) ? $user->display_name : '',
            '{site_name}'   => get_bloginfo('name'),
            '{login_url}'   => $login_url,
            '{my_card_url}' => $my_card_url,
            '{expiry_date}' => '',
            '{days_left}'   => '',
        ), $extra_tokens);

        $subject = strtr($subject, $tokens);
        $message = strtr($message, $tokens);

        $from_name  = get_option('pc_email_from_name', get_bloginfo('name'));
        $from_email = get_option('pc_email_from_address', get_bloginfo('admin_email'));

        $html = self::wrap_html($message);

        return (bool) wp_mail(
            $user->user_email,
            $subject,
            $html,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>',
            )
        );
    }

    private static function wrap_html($message) {
        $site_name = get_bloginfo('name');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">
            <div style="max-width:600px;margin:0 auto;padding:20px;">
                <div style="background:#0073aa;color:#fff;padding:20px;text-align:center;">
                    <h1 style="margin:0;">' . esc_html($site_name) . '</h1>
                </div>
                <div style="padding:30px;background:#f9f9f9;">' . wp_kses_post($message) . '</div>
                <div style="text-align:center;padding:20px;font-size:12px;color:#666;">
                    &copy; ' . date('Y') . ' ' . esc_html($site_name) . '. ' . esc_html__('All rights reserved.', 'personalized-cards') . '
                </div>
            </div>
        </body></html>';
    }

    public static function send_card_email($user_email, $user_name, $card_image_path, $google_wallet_url = '', $card_back_image_path = '') {
        $from_name = get_option('pc_email_from_name', get_bloginfo('name'));
        $from_email = get_option('pc_email_from_address', get_bloginfo('admin_email'));
        $subject = get_option('pc_email_subject', __('Your Personalized Card', 'personalized-cards'));
        $message = get_option('pc_email_message', __('Please find your personalized card attached to this email.', 'personalized-cards'));
        
        // Replace placeholders
        $message = str_replace('{name}', $user_name, $message);
        $message = str_replace('{site_name}', get_bloginfo('name'), $message);
        
        // Set up email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        // Build HTML email
        $html_message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background-color: #f9f9f9; }
                .card-preview { text-align: center; margin: 20px 0; }
                .card-preview img { max-width: 100%; height: auto; border: 1px solid #ddd; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . esc_html($from_name) . '</h1>
                </div>
                <div class="content">
                    <p>Dear ' . esc_html($user_name) . ',</p>
                    <p>' . wp_kses_post($message) . '</p>
                    <div class="card-preview">
                        <p><strong>Card Front:</strong></p>
                        <img src="cid:card_image" alt="Card Front" style="max-width: 100%; height: auto;">
                        ' . ($card_back_image_path && file_exists($card_back_image_path) ? '
                        <p style="margin-top:16px;"><strong>Card Back:</strong></p>
                        <img src="cid:card_back_image" alt="Card Back" style="max-width: 100%; height: auto;">' : '') . '
                    </div>
                    <p>Your personalized card is attached to this email. You can download it for printing or sharing on social media.</p>
                    ' . ($google_wallet_url ? '
                    <div style="text-align:center;margin:24px 0;">
                        <a href="' . esc_url($google_wallet_url) . '" target="_blank"
                           style="display:inline-block;background:#000;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:15px;font-family:Arial,sans-serif;">
                            🎫 Add to Google Wallet
                        </a>
                    </div>' : '') . '
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . esc_html(get_bloginfo('name')) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Use WordPress PHPMailer. Bind to a named callback so we can remove it
        // after sending — otherwise repeated calls (bulk send) accumulate
        // closures and every recipient gets every previous user's attachments.
        $attach_cb = function($phpmailer) use ($card_image_path, $card_back_image_path) {
            $phpmailer->clearAttachments();
            if (file_exists($card_image_path)) {
                $phpmailer->AddEmbeddedImage($card_image_path, 'card_image', basename($card_image_path));
                $phpmailer->AddAttachment($card_image_path, basename($card_image_path));
            }
            if ($card_back_image_path && file_exists($card_back_image_path)) {
                $phpmailer->AddEmbeddedImage($card_back_image_path, 'card_back_image', basename($card_back_image_path));
                $phpmailer->AddAttachment($card_back_image_path, basename($card_back_image_path));
            }
        };
        add_action('phpmailer_init', $attach_cb);

        $sent = wp_mail($user_email, $subject, $html_message, $headers);

        remove_action('phpmailer_init', $attach_cb);

        return $sent;
    }
}
