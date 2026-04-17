<?php
class PC_Email_Handler {
    
    public static function send_card_email($user_email, $user_name, $card_image_path) {
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
                        <p><strong>Card Preview:</strong></p>
                        <img src="cid:card_image" alt="Your Card" style="max-width: 100%; height: auto;">
                    </div>
                    <p>Your personalized card is attached to this email. You can download it for printing or sharing on social media.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . esc_html(get_bloginfo('name')) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Use WordPress PHPMailer
        add_action('phpmailer_init', function($phpmailer) use ($card_image_path) {
            // Add inline image
            if (file_exists($card_image_path)) {
                $phpmailer->AddEmbeddedImage($card_image_path, 'card_image', basename($card_image_path));
                // Also attach as file
                $phpmailer->AddAttachment($card_image_path, basename($card_image_path));
            }
        });
        
        $sent = wp_mail($user_email, $subject, $html_message, $headers);
        
        return $sent;
    }
}
