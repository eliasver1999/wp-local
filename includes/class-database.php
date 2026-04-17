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
    
    public static function update_card_image($card_id, $image_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'personalized_cards';
        
        return $wpdb->update(
            $table_name,
            array('card_image' => $image_url),
            array('id' => $card_id),
            array('%s'),
            array('%d')
        );
    }
}
