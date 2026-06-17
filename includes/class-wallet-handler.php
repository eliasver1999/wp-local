<?php
class PC_Wallet_Handler {

    // ── Branding helpers (shared by both wallets) ────────────────────────────────

    public static function brand_bg_hex() {
        $c = get_option('pc_wallet_bg_color', '#1a73e8');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#1a73e8';
    }

    public static function brand_label_hex() {
        $c = get_option('pc_wallet_label_color', '#ffffff');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#ffffff';
    }

    public static function brand_logo_text() {
        $t = trim((string) get_option('pc_wallet_logo_text', ''));
        return $t !== '' ? $t : get_bloginfo('name');
    }

    private static function hex_to_rgb_string($hex) {
        $hex = ltrim($hex, '#');
        return sprintf('rgb(%d, %d, %d)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    /** Expiry as a unix timestamp (end of day), or 0 if the card has no date. */
    private static function card_expiry_ts($card_data) {
        if (empty($card_data['date'])) return 0;
        $ts = strtotime($card_data['date'] . ' 23:59:59');
        return $ts ?: 0;
    }

    // ── Google Wallet ────────────────────────────────────────────────────────────

    private static function google_service_account() {
        $key_path = PC_PLUGIN_DIR . 'certificates/google-wallet-key.json';
        if (!file_exists($key_path)) {
            return new WP_Error('no_key', 'Google Wallet service account key not found. Upload it in Settings.');
        }
        $sa = json_decode(file_get_contents($key_path), true);
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            return new WP_Error('bad_key', 'Invalid service account JSON key.');
        }
        return $sa;
    }

    private static function google_issuer()    { return get_option('pc_google_wallet_issuer_id', ''); }
    private static function google_class_id()   { return self::google_issuer() . '.membercard'; }
    private static function google_object_id($user_id) { return self::google_issuer() . '.member_' . (int) $user_id; }

    /**
     * Build the genericObject describing one member's pass.
     * Shared by the save-link JWT and the REST PATCH update so both stay in sync.
     */
    private static function build_google_object($card_data, $user_id, $card_image_url = '') {
        $expiry_display = '';
        $expiry_ts      = self::card_expiry_ts($card_data);
        if ($expiry_ts) {
            $fmt = get_option('pc_field_expiry_format', 'd/m/Y');
            $expiry_display = date($fmt, $expiry_ts);
        }

        $text_modules = array();
        if (!empty($card_data['father_name'])) {
            $text_modules[] = array('header' => 'Father Name', 'body' => $card_data['father_name'], 'id' => 'father_name');
        }
        if (!empty($card_data['sport'])) {
            $text_modules[] = array('header' => 'Sport', 'body' => $card_data['sport'], 'id' => 'sport');
        }
        if (!empty($card_data['member_id'])) {
            $text_modules[] = array('header' => 'Member ID', 'body' => (string) $card_data['member_id'], 'id' => 'member_id');
        }
        if ($expiry_display) {
            $text_modules[] = array('header' => 'Valid Until', 'body' => $expiry_display, 'id' => 'expiry');
        }

        $expired = $expiry_ts && $expiry_ts < time();

        $object = array(
            'id'      => self::google_object_id($user_id),
            'classId' => self::google_class_id(),
            'state'   => $expired ? 'EXPIRED' : 'ACTIVE',
            'cardTitle' => array(
                'defaultValue' => array('language' => 'en-US', 'value' => self::brand_logo_text()),
            ),
            'header' => array(
                'defaultValue' => array('language' => 'en-US', 'value' => $card_data['name'] ?? ''),
            ),
            'hexBackgroundColor' => self::brand_bg_hex(),
        );

        // Real expiration — Google greys/expires the pass past this instant.
        if ($expiry_ts) {
            $object['validTimeInterval'] = array('end' => array('date' => date('c', $expiry_ts)));
        }

        $logo_uri = get_site_icon_url(512);
        if ($logo_uri) {
            $object['logo'] = array(
                'sourceUri' => array('uri' => $logo_uri),
                'contentDescription' => array('defaultValue' => array('language' => 'en-US', 'value' => 'Logo')),
            );
        }

        if ($card_image_url) {
            $object['heroImage'] = array(
                'sourceUri' => array('uri' => $card_image_url),
                'contentDescription' => array('defaultValue' => array('language' => 'en-US', 'value' => 'Membership Card')),
            );
        }

        if ($text_modules) {
            $object['textModulesData'] = $text_modules;
        }

        return $object;
    }

    /**
     * Build a signed "Save to Google Wallet" JWT link.
     *
     * @return string|WP_Error  URL to redirect/link to
     */
    public static function create_google_wallet_link($card_data, $user_id, $card_image_url = '') {
        if (get_option('pc_enable_google_wallet', '0') !== '1') {
            return new WP_Error('disabled', 'Google Wallet is not enabled.');
        }

        $sa = self::google_service_account();
        if (is_wp_error($sa)) return $sa;

        if (!self::google_issuer()) {
            return new WP_Error('no_issuer', 'Google Wallet Issuer ID not set in Settings.');
        }

        // The class must exist before an object can reference it. Define it inline in
        // the JWT so Google creates it on first save (and leaves it untouched after).
        $generic_class = array('id' => self::google_class_id());
        $pass_object   = self::build_google_object($card_data, $user_id, $card_image_url);

        $jwt_payload = array(
            'iss'     => $sa['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'iat'     => time(),
            'origins' => array(home_url()),
            'payload' => array(
                'genericClasses' => array($generic_class),
                'genericObjects' => array($pass_object),
            ),
        );

        $token = self::sign_jwt($jwt_payload, $sa['private_key']);
        if (is_wp_error($token)) return $token;

        return 'https://pay.google.com/gp/v/save/' . $token;
    }

    /**
     * Push the member's current data to Google so the saved pass updates on-device.
     * Safe to call any time membership changes; no-ops if the object was never created.
     *
     * @return true|WP_Error|false
     */
    public static function push_google_update($user_id) {
        if (get_option('pc_enable_google_wallet', '0') !== '1') return false;
        if (!self::google_issuer()) return false;

        $cards = PC_Database::get_user_cards($user_id);
        if (empty($cards)) return false;
        $latest    = $cards[0];
        $card_data = json_decode($latest->card_data, true) ?: array();
        $object    = self::build_google_object($card_data, $user_id, $latest->card_image);

        $token = self::get_google_access_token();
        if (is_wp_error($token)) return $token;

        $url  = 'https://walletobjects.googleapis.com/walletobjects/v1/genericObject/' . rawurlencode(self::google_object_id($user_id));
        $resp = wp_remote_request($url, array(
            'method'  => 'PATCH',
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($object),
        ));
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        // 404 simply means the member never saved the pass yet — not an error.
        return ($code >= 200 && $code < 300);
    }

    /** Exchange the service account for an OAuth2 access token (JWT bearer grant). */
    private static function get_google_access_token() {
        // Tokens last an hour — cache so renewals/bulk imports don't re-auth each call.
        $cached = get_transient('pc_google_wallet_token');
        if ($cached) return $cached;

        $sa = self::google_service_account();
        if (is_wp_error($sa)) return $sa;

        $now    = time();
        $claim  = array(
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        );
        $assertion = self::sign_jwt($claim, $sa['private_key']);
        if (is_wp_error($assertion)) return $assertion;

        $resp = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ),
        ));
        if (is_wp_error($resp)) return $resp;

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['access_token'])) {
            return new WP_Error('oauth_failed', 'Could not obtain Google access token.');
        }

        $ttl = isset($data['expires_in']) ? max(60, (int) $data['expires_in'] - 300) : 3000;
        set_transient('pc_google_wallet_token', $data['access_token'], $ttl);
        return $data['access_token'];
    }

    /** Sign a JWT with RS256 using a PEM private key. */
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

    // ── Apple Wallet ─────────────────────────────────────────────────────────────

    /** Stable per-member serial so renewals update the same pass instead of duplicating. */
    public static function apple_serial_for_user($user_id) {
        return 'PCMEMBER-' . (int) $user_id;
    }

    public static function user_id_from_apple_serial($serial) {
        if (strpos($serial, 'PCMEMBER-') === 0) {
            return (int) substr($serial, strlen('PCMEMBER-'));
        }
        return 0;
    }

    /**
     * Build a .pkpass package for Apple Wallet and return its raw bytes.
     *
     * @param array  $card_data        Keys: name, father_name, sport, member_id, date
     * @param string $card_image_path  Absolute path to the card image (used for thumbnail)
     * @param int    $user_id          WP user ID — enables a stable serial + auto-update
     * @return string|WP_Error         Raw .pkpass bytes on success
     */
    public static function create_apple_wallet_pass($card_data, $card_image_path = '', $user_id = 0) {
        if (get_option('pc_enable_apple_wallet', '0') !== '1') {
            return new WP_Error('disabled', 'Apple Wallet is not enabled.');
        }
        if (!class_exists('PKPass\\PKPass')) {
            return new WP_Error('library_missing', 'PKPass library not installed. Run: composer require pkpass/pkpass:^1.8 in the plugin folder.');
        }

        $cert_dir  = PC_PLUGIN_DIR . 'certificates/';
        $p12_path  = $cert_dir . 'apple-certificate.p12';
        $wwdr_path = $cert_dir . 'apple-wwdr.pem';

        if (!file_exists($p12_path)) {
            return new WP_Error('no_cert', 'Apple Pass Type ID certificate (apple-certificate.p12) not found. Upload it in Settings.');
        }
        if (!file_exists($wwdr_path)) {
            return new WP_Error('no_wwdr', 'Apple WWDR certificate (apple-wwdr.pem) not found. Upload it in Settings.');
        }

        $pass_type_id = get_option('pc_apple_pass_type_id', '');
        $team_id      = get_option('pc_apple_team_id', '');
        if (!$pass_type_id || !$team_id) {
            return new WP_Error('no_ids', 'Apple Pass Type ID and Team ID must be set in Settings.');
        }

        $expiry_ts      = self::card_expiry_ts($card_data);
        $expiry_display = '';
        if ($expiry_ts) {
            $fmt = get_option('pc_field_expiry_format', 'd/m/Y');
            $expiry_display = date($fmt, $expiry_ts);
        }

        $member_id = isset($card_data['member_id']) && $card_data['member_id'] !== ''
            ? (string) $card_data['member_id']
            : (string) time();

        $serial = $user_id ? self::apple_serial_for_user($user_id) : ('PC-' . $member_id);

        $secondary = array();
        if (!empty($card_data['sport'])) {
            $secondary[] = array('key' => 'sport', 'label' => 'Sport', 'value' => $card_data['sport']);
        }
        if ($expiry_display) {
            $secondary[] = array('key' => 'expiry', 'label' => 'Valid Until', 'value' => $expiry_display);
        }

        $auxiliary = array();
        if (!empty($card_data['father_name'])) {
            $auxiliary[] = array('key' => 'father', 'label' => 'Father', 'value' => $card_data['father_name']);
        }
        if ($member_id) {
            $auxiliary[] = array('key' => 'member', 'label' => 'Member ID', 'value' => $member_id);
        }

        $back = array();
        if (!empty($card_data['message'])) {
            $back[] = array('key' => 'message', 'label' => 'Message', 'value' => $card_data['message']);
        }

        $pass_data = array(
            'formatVersion'      => 1,
            'passTypeIdentifier' => $pass_type_id,
            'serialNumber'       => $serial,
            'teamIdentifier'     => $team_id,
            'organizationName'   => get_bloginfo('name'),
            'description'        => 'Membership Card',
            'logoText'           => self::brand_logo_text(),
            'foregroundColor'    => self::hex_to_rgb_string(self::brand_label_hex()),
            'backgroundColor'    => self::hex_to_rgb_string(self::brand_bg_hex()),
            'labelColor'         => self::hex_to_rgb_string(self::brand_label_hex()),
            'generic'            => array(
                'primaryFields'   => array(
                    array('key' => 'name', 'label' => 'Name', 'value' => $card_data['name'] ?? ''),
                ),
                'secondaryFields' => $secondary,
                'auxiliaryFields' => $auxiliary,
                'backFields'      => $back,
            ),
        );

        // Real expiration — Apple greys out the pass and marks it expired.
        if ($expiry_ts) {
            $pass_data['expirationDate'] = date('c', $expiry_ts);
            if ($expiry_ts < time()) {
                $pass_data['voided'] = true;
            }
        }

        // QR code so the pass is scannable, mirroring the printed card.
        $pass_data['barcodes'] = array(
            array(
                'format'          => 'PKBarcodeFormatQR',
                'message'         => $member_id ?: home_url(),
                'messageEncoding' => 'iso-8859-1',
            ),
        );

        // Wire up auto-update: tells the phone where to fetch refreshed passes.
        if ($user_id && get_option('pc_enable_wallet_updates', '0') === '1' && class_exists('PC_Wallet_WebService')) {
            $pass_data['webServiceURL']       = PC_Wallet_WebService::base_url();
            $pass_data['authenticationToken'] = PC_Wallet_WebService::auth_token($serial);
        }

        try {
            $pass = new PKPass\PKPass();
            $pass->setCertificate($p12_path);
            $pass->setCertificatePassword((string) get_option('pc_apple_cert_password', ''));
            $pass->setWWDRcertPath($wwdr_path);
            $pass->setData($pass_data);

            // icon.png is REQUIRED by Apple or the pass will not open.
            $icon_path = self::resolve_apple_icon_path($card_image_path);
            if (!$icon_path) {
                return new WP_Error('no_icon', 'No icon image available for the Apple pass. Set a Wallet logo in Settings.');
            }
            $pass->addFile($icon_path, 'icon.png');
            $pass->addFile($icon_path, 'logo.png');

            if ($card_image_path && file_exists($card_image_path)) {
                $pass->addFile($card_image_path, 'thumbnail.png');
            }

            $contents = $pass->create(false);
            if (!$contents) {
                return new WP_Error('creation_failed', 'Failed to create Apple Wallet pass (check certificate and password).');
            }
            return $contents;
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Generate the .pkpass and write it to a temp file (for email attachment).
     *
     * @return string|WP_Error  Absolute path to a .pkpass file (caller should unlink it)
     */
    public static function generate_pkpass_to_file($card_data, $card_image_path = '', $user_id = 0) {
        $bytes = self::create_apple_wallet_pass($card_data, $card_image_path, $user_id);
        if (is_wp_error($bytes)) return $bytes;

        $base = tempnam(get_temp_dir(), 'pc-pass-');
        if (!$base) return new WP_Error('tmp', 'Could not create a temporary file for the pass.');
        $path = $base . '.pkpass';
        if (file_put_contents($path, $bytes) === false) {
            @unlink($base);
            return new WP_Error('write_failed', 'Could not write the pass file.');
        }
        @unlink($base);
        return $path;
    }

    /**
     * Resolve a usable icon image path for the Apple pass.
     * Order: admin-configured Wallet logo → site icon → the card image itself.
     */
    private static function resolve_apple_icon_path($card_image_path = '') {
        $logo_url = get_option('pc_apple_logo', '');
        if ($logo_url) {
            $path = self::url_to_path($logo_url);
            if ($path && file_exists($path)) return $path;
        }

        $site_icon_id = (int) get_option('site_icon');
        if ($site_icon_id) {
            $path = get_attached_file($site_icon_id);
            if ($path && file_exists($path)) return $path;
        }

        if ($card_image_path && file_exists($card_image_path)) {
            return $card_image_path;
        }

        return '';
    }

    // ── Lifecycle: push updates when a membership changes ────────────────────────

    /** Hooked to pc_membership_changed — refresh both wallets for this member. */
    public static function on_membership_changed($user_id) {
        $user_id = (int) $user_id;
        if (!$user_id) return;

        if (get_option('pc_enable_google_wallet', '0') === '1') {
            self::push_google_update($user_id);
        }
        if (get_option('pc_enable_apple_wallet', '0') === '1'
            && get_option('pc_enable_wallet_updates', '0') === '1'
            && class_exists('PC_Wallet_WebService')) {
            PC_Wallet_WebService::push_for_user($user_id);
        }
    }

    // ── Configuration test ──────────────────────────────────────────────────────

    /**
     * Run both wallet generators with sample data and report the outcome.
     *
     * @return array[]  Each: ['service' => string, 'status' => 'ok'|'error'|'disabled', 'message' => string]
     */
    public static function test_configuration() {
        $results = array();

        $sample = array(
            'name'        => 'Test Member',
            'father_name' => 'Test Father',
            'sport'       => 'Testing',
            'member_id'   => 'TEST-0001',
            'date'        => date('Y-m-d', strtotime('+1 year')),
        );

        if (get_option('pc_enable_google_wallet', '0') !== '1') {
            $results[] = array('service' => 'Google Wallet', 'status' => 'disabled', 'message' => 'Disabled in settings.');
        } else {
            $gw = self::create_google_wallet_link($sample, 0, '');
            $results[] = is_wp_error($gw)
                ? array('service' => 'Google Wallet', 'status' => 'error', 'message' => $gw->get_error_message())
                : array('service' => 'Google Wallet', 'status' => 'ok', 'message' => 'Save link generated and JWT signed successfully.');
        }

        if (get_option('pc_enable_apple_wallet', '0') !== '1') {
            $results[] = array('service' => 'Apple Wallet', 'status' => 'disabled', 'message' => 'Disabled in settings.');
        } else {
            $tmp_icon = self::make_test_png();
            $ap = self::create_apple_wallet_pass($sample, $tmp_icon, 0);
            if ($tmp_icon && file_exists($tmp_icon)) {
                @unlink($tmp_icon);
            }
            $results[] = is_wp_error($ap)
                ? array('service' => 'Apple Wallet', 'status' => 'error', 'message' => $ap->get_error_message())
                : array('service' => 'Apple Wallet', 'status' => 'ok', 'message' => sprintf('.pkpass generated and signed successfully (%s).', size_format(strlen($ap))));
        }

        return $results;
    }

    /**
     * Create a small valid PNG in the temp dir for the configuration test.
     * Returns '' if GD is unavailable (the Apple test then falls back to the site icon).
     */
    private static function make_test_png() {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            return '';
        }
        $img = imagecreatetruecolor(29, 29);
        $bg  = imagecolorallocate($img, 26, 115, 232);
        imagefilledrectangle($img, 0, 0, 29, 29, $bg);
        $tmp = tempnam(get_temp_dir(), 'pc-icon-');
        if (!$tmp) {
            imagedestroy($img);
            return '';
        }
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
    }

    /**
     * Map a local uploads URL to its filesystem path. Returns '' for remote URLs.
     */
    public static function url_to_path($url) {
        if (!$url) return '';
        $uploads = wp_upload_dir();
        if (!empty($uploads['baseurl']) && strpos($url, $uploads['baseurl']) === 0) {
            return $uploads['basedir'] . substr($url, strlen($uploads['baseurl']));
        }
        $content_url = content_url();
        if (strpos($url, $content_url) === 0) {
            return WP_CONTENT_DIR . substr($url, strlen($content_url));
        }
        return '';
    }
}
