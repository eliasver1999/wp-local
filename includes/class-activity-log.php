<?php
class PC_Activity_Log {

    public static function create_table() {
        global $wpdb;
        $table           = $wpdb->prefix . 'pc_activity_log';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            action varchar(100) NOT NULL,
            note text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log($action, $note = '', $user_id = null) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'pc_activity_log',
            array(
                'user_id'    => $user_id,
                'action'     => sanitize_key($action),
                'note'       => sanitize_text_field($note),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    public static function get_recent($limit = 100, $action = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_activity_log';
        if ($action) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE action = %s ORDER BY created_at DESC LIMIT %d",
                $action, $limit
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d", $limit
        ));
    }
}
