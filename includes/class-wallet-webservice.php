<?php
/**
 * Apple Wallet (PassKit) web service: device registration + push updates.
 *
 * Implements the endpoints Apple calls to keep a pass up to date:
 *   POST   /v1/devices/{device}/registrations/{passType}/{serial}   register
 *   DELETE /v1/devices/{device}/registrations/{passType}/{serial}   unregister
 *   GET    /v1/devices/{device}/registrations/{passType}            list updatable serials
 *   GET    /v1/passes/{passType}/{serial}                           latest pass
 *   POST   /v1/log                                                  device logging
 *
 * Routes live under the REST namespace "pc-wallet", so the pass's
 * webServiceURL is rest_url('pc-wallet') and Apple appends "/v1/...".
 */
class PC_Wallet_WebService {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'pc_wallet_registrations';
    }

    public static function create_table() {
        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            device_library_identifier varchar(191) NOT NULL,
            pass_type_identifier varchar(191) NOT NULL,
            serial_number varchar(191) NOT NULL,
            push_token varchar(191) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY device_serial (device_library_identifier, serial_number),
            KEY serial_number (serial_number)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Base webServiceURL written into each pass. */
    public static function base_url() {
        return untrailingslashit(rest_url('pc-wallet'));
    }

    /** Stable secret used to derive per-pass authentication tokens. */
    private static function auth_secret() {
        $secret = get_option('pc_apple_web_auth_secret', '');
        if (!$secret) {
            $secret = wp_generate_password(64, false, false);
            update_option('pc_apple_web_auth_secret', $secret, false);
        }
        return $secret;
    }

    /** Token embedded in the pass and echoed back by Apple in the Authorization header. */
    public static function auth_token($serial) {
        return hash_hmac('sha256', $serial, self::auth_secret());
    }

    private static function check_auth($request, $serial) {
        $header = $request->get_header('authorization');
        if (!$header || stripos($header, 'ApplePass ') !== 0) {
            return false;
        }
        $token = trim(substr($header, strlen('ApplePass ')));
        return hash_equals(self::auth_token($serial), $token);
    }

    public static function register_routes() {
        $serial_route = '/v1/devices/(?P<device>[^/]+)/registrations/(?P<pass_type>[^/]+)/(?P<serial>[^/]+)';

        register_rest_route('pc-wallet', $serial_route, array(
            array('methods' => 'POST',   'callback' => array(__CLASS__, 'register'),   'permission_callback' => '__return_true'),
            array('methods' => 'DELETE', 'callback' => array(__CLASS__, 'unregister'), 'permission_callback' => '__return_true'),
        ));

        register_rest_route('pc-wallet', '/v1/devices/(?P<device>[^/]+)/registrations/(?P<pass_type>[^/]+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'list_updatable'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('pc-wallet', '/v1/passes/(?P<pass_type>[^/]+)/(?P<serial>[^/]+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_latest_pass'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('pc-wallet', '/v1/log', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'log'),
            'permission_callback' => '__return_true',
        ));
    }

    // ── Endpoint handlers ────────────────────────────────────────────────────────

    public static function register($request) {
        $serial = $request['serial'];
        if (!self::check_auth($request, $serial)) {
            return new WP_REST_Response(null, 401);
        }

        $body = json_decode($request->get_body(), true);
        $push = isset($body['pushToken']) ? sanitize_text_field($body['pushToken']) : '';
        if (!$push) {
            return new WP_REST_Response(null, 400);
        }

        global $wpdb;
        $table  = self::table();
        $device = $request['device'];
        $now    = current_time('mysql', true);

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE device_library_identifier = %s AND serial_number = %s",
            $device, $serial
        ));

        if ($existing) {
            $wpdb->update($table, array('push_token' => $push, 'updated_at' => $now), array('id' => $existing));
            return new WP_REST_Response(null, 200);
        }

        $wpdb->insert($table, array(
            'device_library_identifier' => $device,
            'pass_type_identifier'      => $request['pass_type'],
            'serial_number'             => $serial,
            'push_token'                => $push,
            'created_at'                => $now,
            'updated_at'                => $now,
        ));
        return new WP_REST_Response(null, 201);
    }

    public static function unregister($request) {
        $serial = $request['serial'];
        if (!self::check_auth($request, $serial)) {
            return new WP_REST_Response(null, 401);
        }
        global $wpdb;
        $wpdb->delete(self::table(), array(
            'device_library_identifier' => $request['device'],
            'serial_number'             => $serial,
        ));
        return new WP_REST_Response(null, 200);
    }

    public static function list_updatable($request) {
        global $wpdb;
        $table  = self::table();
        $rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT serial_number, updated_at FROM $table WHERE device_library_identifier = %s AND pass_type_identifier = %s",
            $request['device'], $request['pass_type']
        ));
        if (empty($rows)) {
            return new WP_REST_Response(null, 204);
        }

        $since    = $request->get_param('passesUpdatedSince');
        $since_ts = $since !== null && $since !== '' ? (is_numeric($since) ? (int) $since : (int) strtotime($since)) : 0;

        $serials = array();
        $last    = 0;
        foreach ($rows as $r) {
            $ts = (int) strtotime($r->updated_at . ' UTC');
            if ($since_ts && $ts <= $since_ts) continue;
            $serials[] = $r->serial_number;
            if ($ts > $last) $last = $ts;
        }

        if (empty($serials)) {
            return new WP_REST_Response(null, 204);
        }

        return new WP_REST_Response(array(
            'lastUpdated'   => (string) $last,
            'serialNumbers' => $serials,
        ), 200);
    }

    /** Streams the freshly generated .pkpass for a serial. */
    public static function get_latest_pass($request) {
        $serial = $request['serial'];
        if (!self::check_auth($request, $serial)) {
            return new WP_REST_Response(null, 401);
        }

        $user_id = PC_Wallet_Handler::user_id_from_apple_serial($serial);
        if (!$user_id) {
            return new WP_REST_Response(null, 404);
        }

        $cards = PC_Database::get_user_cards($user_id);
        if (empty($cards)) {
            return new WP_REST_Response(null, 404);
        }
        $latest    = $cards[0];
        $card_data = json_decode($latest->card_data, true) ?: array();
        $image     = PC_Wallet_Handler::url_to_path($latest->card_image);

        $bytes = PC_Wallet_Handler::create_apple_wallet_pass($card_data, $image, $user_id);
        if (is_wp_error($bytes)) {
            return new WP_REST_Response(null, 500);
        }

        $modified = strtotime($latest->updated_at . ' UTC') ?: time();

        // If the device already has this version, tell it so.
        $ims = $request->get_header('if_modified_since');
        if ($ims && strtotime($ims) >= $modified) {
            return new WP_REST_Response(null, 304);
        }

        if (!headers_sent()) {
            status_header(200);
            header('Content-Type: application/vnd.apple.pkpass');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
            header('Content-Length: ' . strlen($bytes));
        }
        echo $bytes;
        exit;
    }

    public static function log($request) {
        $body = json_decode($request->get_body(), true);
        if (!empty($body['logs']) && is_array($body['logs'])) {
            foreach ($body['logs'] as $line) {
                error_log('[PC Apple Wallet] ' . (is_string($line) ? $line : wp_json_encode($line)));
            }
        }
        return new WP_REST_Response(null, 200);
    }

    // ── Push (APNs) ───────────────────────────────────────────────────────────────

    /** Notify all devices holding this member's pass to pull a fresh copy. */
    public static function push_for_user($user_id) {
        self::push_to_serial(PC_Wallet_Handler::apple_serial_for_user($user_id));
    }

    public static function push_to_serial($serial) {
        if (get_option('pc_enable_wallet_updates', '0') !== '1') return;

        global $wpdb;
        $table = self::table();
        $now   = current_time('mysql', true);

        // Bump updated_at so list_updatable reports this serial as changed.
        $wpdb->query($wpdb->prepare("UPDATE $table SET updated_at = %s WHERE serial_number = %s", $now, $serial));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT push_token, pass_type_identifier FROM $table WHERE serial_number = %s",
            $serial
        ));
        foreach ($rows as $r) {
            self::send_apns($r->push_token, $r->pass_type_identifier);
        }
    }

    /**
     * Send an empty APNs push (HTTP/2) signed with the Pass Type ID certificate.
     * The pass topic IS the pass type identifier.
     */
    private static function send_apns($push_token, $topic) {
        $p12 = PC_PLUGIN_DIR . 'certificates/apple-certificate.p12';
        if (!file_exists($p12) || !function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init('https://api.push.apple.com/3/device/' . $push_token);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => array('apns-topic: ' . $topic, 'apns-push-type: background'),
            CURLOPT_HTTP_VERSION   => defined('CURL_HTTP_VERSION_2_0') ? CURL_HTTP_VERSION_2_0 : 3,
            CURLOPT_SSLCERT        => $p12,
            CURLOPT_SSLCERTTYPE    => 'P12',
            CURLOPT_SSLCERTPASSWD  => (string) get_option('pc_apple_cert_password', ''),
            CURLOPT_TIMEOUT        => 15,
        ));
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200;
    }
}
