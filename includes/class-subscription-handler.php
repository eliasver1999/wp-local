<?php
class PC_Subscription_Handler {
    
    public static function get_user_subscription_level($user_id) {
        // Check user meta for subscription level
        $level = get_user_meta($user_id, 'subscription_level', true);
        
        // Default to basic if no level set
        return $level ? $level : 'basic';
    }
    
    public static function can_create_card($user_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check if user has active subscription
        $is_active = get_user_meta($user_id, 'pc_subscription_active', true);
        $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
        
        if ($is_active !== '1') {
            return false;
        }
        
        // Check if subscription has expired
        if ($expiry_date && strtotime($expiry_date) < time()) {
            // Subscription expired, deactivate it
            update_user_meta($user_id, 'pc_subscription_active', '0');
            return false;
        }
        
        return true;
    }
    
    public static function get_card_limit($user_id) {
        $subscription_level = self::get_user_subscription_level($user_id);
        
        $limits = array(
            'basic' => 5,
            'premium' => 20,
            'vip' => -1 // unlimited
        );
        
        return isset($limits[$subscription_level]) ? $limits[$subscription_level] : 0;
    }
}
