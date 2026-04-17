<?php
class PC_Wallet_Handler {
    
    /**
     * Create Apple Wallet Pass (.pkpass)
     * Note: Requires PKPass library and Apple Developer certificates
     */
    public static function create_apple_wallet_pass($card_data, $card_image_path) {
        if (!get_option('pc_enable_apple_wallet')) {
            return new WP_Error('disabled', 'Apple Wallet is not enabled');
        }
        
        // Check if PKPass library is available
        if (!class_exists('PKPass\\PKPass')) {
            return new WP_Error('library_missing', 'PKPass library not installed. Run: composer require pkpass/pkpass');
        }
        
        try {
            $pass = new PKPass\PKPass();
            
            // Set pass information
            $pass_data = array(
                'formatVersion' => 1,
                'passTypeIdentifier' => 'pass.com.yoursite.personalizedcard',
                'serialNumber' => 'PC-' . time(),
                'teamIdentifier' => 'YOUR_TEAM_ID',
                'organizationName' => get_bloginfo('name'),
                'description' => 'Personalized Card',
                'backgroundColor' => 'rgb(255, 255, 255)',
                'foregroundColor' => 'rgb(0, 0, 0)',
                'labelColor' => 'rgb(100, 100, 100)',
                'generic' => array(
                    'primaryFields' => array(
                        array(
                            'key' => 'name',
                            'label' => 'Name',
                            'value' => $card_data['name']
                        )
                    ),
                    'secondaryFields' => array(
                        array(
                            'key' => 'message',
                            'label' => 'Message',
                            'value' => isset($card_data['message']) ? $card_data['message'] : ''
                        )
                    )
                )
            );
            
            $pass->setData($pass_data);
            
            // Add card image
            if (file_exists($card_image_path)) {
                $pass->addFile($card_image_path, 'strip.png');
            }
            
            // Set certificate paths
            $cert_path = PC_PLUGIN_DIR . 'certificates/';
            $pass->setCertificate($cert_path . 'certificate.pem');
            $pass->setWWDRcertPath($cert_path . 'wwdr.pem');
            
            // Create the pass
            $upload_dir = wp_upload_dir();
            $pkpass_filename = 'card_' . time() . '.pkpass';
            $pkpass_path = $upload_dir['path'] . '/' . $pkpass_filename;
            
            if ($pass->create(true)) {
                file_put_contents($pkpass_path, $pass->get());
                return $upload_dir['url'] . '/' . $pkpass_filename;
            }
            
            return new WP_Error('creation_failed', 'Failed to create Apple Wallet pass');
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Generate simple Google Wallet link
     */
    public static function create_simple_google_wallet_link($card_data, $card_image_url) {
        if (!get_option('pc_enable_google_wallet')) {
            return false;
        }
        
        $issuer_id = get_option('pc_google_wallet_issuer_id');
        if (!$issuer_id) {
            return false;
        }
        
        // Simple JWT payload
        $payload = array(
            'iss' => $issuer_id,
            'aud' => 'google',
            'typ' => 'savetowallet',
            'origins' => array(home_url()),
            'payload' => array(
                'genericObjects' => array(
                    array(
                        'id' => $issuer_id . '.card_' . time(),
                        'classId' => $issuer_id . '.personalized_card',
                        'cardTitle' => array(
                            'defaultValue' => array(
                                'language' => 'en',
                                'value' => $card_data['name']
                            )
                        ),
                        'header' => array(
                            'defaultValue' => array(
                                'language' => 'en',
                                'value' => 'Personalized Card'
                            )
                        ),
                        'hexBackgroundColor' => '#ffffff'
                    )
                )
            )
        );
        
        $jwt = base64_encode(json_encode($payload));
        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }
}
