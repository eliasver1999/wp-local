<?php
class PC_Database {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'personalized_cards';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            card_template varchar(255) NOT NULL,
            card_data longtext NOT NULL,
            card_image varchar(255) DEFAULT NULL,
            card_back_image varchar(255) DEFAULT NULL,
            subscription_level varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function save_card($user_id, $template, $data, $subscription_level) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';

        // One card per user: reuse the existing row if the user already has a card.
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, card_image, card_back_image FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ($existing) {
            // Remove any extra duplicate rows for this user (keep the one we update).
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE user_id = %d AND id <> %d",
                $user_id, $existing->id
            ));

            // Delete the old image files — a fresh image is generated right after this.
            $upload = wp_upload_dir();
            foreach (array($existing->card_image, $existing->card_back_image) as $old_url) {
                if (!$old_url) continue;
                $old_path = str_replace($upload['baseurl'], $upload['basedir'], $old_url);
                if ($old_path !== $old_url && file_exists($old_path)) {
                    @unlink($old_path);
                }
            }

            $wpdb->update(
                $table_name,
                array(
                    'card_template'      => $template,
                    'card_data'          => json_encode($data),
                    'subscription_level' => $subscription_level,
                    'card_image'         => null,
                    'card_back_image'    => null,
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            return (int) $existing->id;
        }

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'card_template' => $template,
                'card_data' => json_encode($data),
                'subscription_level' => $subscription_level
            ),
            array('%d', '%s', '%s', '%s')
        );

        return $wpdb->insert_id;
    }
    
    public static function get_user_cards($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC", $user_id)
        );
    }

    public static function get_card($card_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $card_id)
        );
    }
    
    public static function update_card_image($card_id, $image_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';
        return $wpdb->update($table_name, array('card_image' => $image_url), array('id' => $card_id), array('%s'), array('%d'));
    }

    public static function update_card_back_image($card_id, $image_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';
        return $wpdb->update($table_name, array('card_back_image' => $image_url), array('id' => $card_id), array('%s'), array('%d'));
    }

    public static function maybe_add_back_image_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'personalized_cards';
        $cols  = $wpdb->get_col("DESCRIBE $table", 0);
        if (!in_array('card_back_image', $cols, true)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN card_back_image varchar(255) DEFAULT NULL AFTER card_image");
        }
    }
}
