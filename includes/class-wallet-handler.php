<?php
class PC_Wallet_Handler {

    // ── Google Wallet ────────────────────────────────────────────────────────────

    /**
     * Build a signed "Save to Google Wallet" JWT link.
     * Requires a service account JSON key uploaded in Settings.
     *
     * @param array  $card_data  Keys: name, father_name, sport, member_id, date, image (URL)
     * @param int    $user_id    WP user ID (used to make a stable pass object ID)
     * @param string $card_image_url  Publicly accessible card image URL
     * @return string|WP_Error  URL to redirect/link to
     */
    public static function create_google_wallet_link($card_data, $user_id, $card_image_url = '') {
        if (get_option('pc_enable_google_wallet', '0') !== '1') {
            return new WP_Error('disabled', 'Google Wallet is not enabled.');
        }

        $key_path = PC_PLUGIN_DIR . 'certificates/google-wallet-key.json';
        if (!file_exists($key_path)) {
            return new WP_Error('no_key', 'Google Wallet service account key not found. Upload it in Settings.');
        }

        $sa = json_decode(file_get_contents($key_path), true);
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            return new WP_Error('bad_key', 'Invalid service account JSON key.');
        }

        $issuer_id = get_option('pc_google_wallet_issuer_id', '');
        if (!$issuer_id) {
            return new WP_Error('no_issuer', 'Google Wallet Issuer ID not set in Settings.');
        }

        // Stable IDs — class is shared, object is per user
        $class_id  = $issuer_id . '.membercard';
        $object_id = $issuer_id . '.member_' . $user_id;

        $expiry_display = '';
        if (!empty($card_data['date'])) {
            $fmt = get_option('pc_field_expiry_format', 'd/m/Y');
            $expiry_display = date($fmt, strtotime($card_data['date']));
        }

        $text_modules = array();
        if (!empty($card_data['father_name'])) {
            $text_modules[] = array('header' => 'Father Name', 'body' => $card_data['father_name'], 'id' => 'father_name');
        }
        if (!empty($card_data['sport'])) {
            $text_modules[] = array('header' => 'Sport',       'body' => $card_data['sport'],       'id' => 'sport');
        }
        if (!empty($card_data['member_id'])) {
            $text_modules[] = array('header' => 'Member ID',   'body' => (string) $card_data['member_id'], 'id' => 'member_id');
        }
        if ($expiry_display) {
            $text_modules[] = array('header' => 'Valid Until',  'body' => $expiry_display,           'id' => 'expiry');
        }

        $pass_object = array(
            'id'      => $object_id,
            'classId' => $class_id,
            'state'   => 'ACTIVE',
            'cardTitle' => array(
                'defaultValue' => array('language' => 'en-US', 'value' => get_bloginfo('name')),
            ),
            'header' => array(
                'defaultValue' => array('language' => 'en-US', 'value' => $card_data['name'] ?? ''),
            ),
            'hexBackgroundColor' => '#1a73e8',
            'logo' => array(
                'sourceUri' => array('uri' => get_site_icon_url(512) ?: ''),
                'contentDescription' => array('defaultValue' => array('language' => 'en-US', 'value' => 'Logo')),
            ),
        );

        if ($card_image_url) {
            $pass_object['heroImage'] = array(
                'sourceUri' => array('uri' => $card_image_url),
                'contentDescription' => array('defaultValue' => array('language' => 'en-US', 'value' => 'Membership Card')),
            );
        }

        if ($text_modules) {
            $pass_object['textModulesData'] = $text_modules;
        }

        $jwt_payload = array(
            'iss'     => $sa['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'iat'     => time(),
            'origins' => array(home_url()),
            'payload' => array(
                'genericObjects' => array($pass_object),
            ),
        );

        $token = self::sign_jwt($jwt_payload, $sa['private_key']);
        if (is_wp_error($token)) return $token;

        return 'https://pay.google.com/gp/v/save/' . $token;
    }

    /**
     * Sign a JWT with RS256 using a PEM private key.
     */
    private static function sign_jwt($payload, $private_key_pem) {
        if (!function_exists('openssl_sign')) {
            return new WP_Error('no_openssl', 'OpenSSL extension is required for Google Wallet.');
        }

        $header  = self::base64url(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
        $body    = self::base64url(json_encode($payload));
        $to_sign = $header . '.' . $body;

        $key = openssl_pkey_get_private($private_key_pem);
        if (!$key) {
            return new WP_Error('bad_pem', 'Could not load private key from service account JSON.');
        }

        $ok = openssl_sign($to_sign, $signature, $key, OPENSSL_ALGO_SHA256);
        if (PHP_MAJOR_VERSION < 8) openssl_free_key($key);

        if (!$ok) {
            return new WP_Error('sign_failed', 'JWT signing failed.');
        }

        return $to_sign . '.' . self::base64url($signature);
    }

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ── Apple Wallet (stub — requires Apple Developer certs) ─────────────────────

    public static function create_apple_wallet_pass($card_data, $card_image_path) {
        if (!get_option('pc_enable_apple_wallet')) {
            return new WP_Error('disabled', 'Apple Wallet is not enabled.');
        }
        if (!class_exists('PKPass\\PKPass')) {
            return new WP_Error('library_missing', 'PKPass library not installed. Run: composer require pkpass/pkpass');
        }

        try {
            $pass      = new PKPass\PKPass();
            $cert_path = PC_PLUGIN_DIR . 'certificates/';
            $pass->setCertificate($cert_path . 'certificate.pem');
            $pass->setWWDRcertPath($cert_path . 'wwdr.pem');

            $pass->setData(array(
                'formatVersion'      => 1,
                'passTypeIdentifier' => get_option('pc_apple_pass_type_id', 'pass.com.example.membercard'),
                'serialNumber'       => 'PC-' . $card_data['member_id'] ?? time(),
                'teamIdentifier'     => get_option('pc_apple_team_id', ''),
                'organizationName'   => get_bloginfo('name'),
                'description'        => 'Membership Card',
                'generic'            => array(
                    'primaryFields'   => array(array('key' => 'name',   'label' => 'Name',   'value' => $card_data['name'])),
                    'secondaryFields' => array(
                        array('key' => 'sport',  'label' => 'Sport',       'value' => $card_data['sport']       ?? ''),
                        array('key' => 'expiry', 'label' => 'Valid Until', 'value' => $card_data['date']        ?? ''),
                    ),
                    'auxiliaryFields' => array(array('key' => 'father', 'label' => 'Father', 'value' => $card_data['father_name'] ?? '')),
                ),
            ));

            if (file_exists($card_image_path)) {
                $pass->addFile($card_image_path, 'strip.png');
            }

            $upload_dir      = wp_upload_dir();
            $pkpass_filename = 'card_' . $card_data['member_id'] . '_' . time() . '.pkpass';
            $pkpass_path     = $upload_dir['basedir'] . '/personalized-cards/' . $pkpass_filename;

            if ($pass->create(true)) {
                file_put_contents($pkpass_path, $pass->get());
                return $upload_dir['baseurl'] . '/personalized-cards/' . $pkpass_filename;
            }
            return new WP_Error('creation_failed', 'Failed to create Apple Wallet pass.');
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
}
