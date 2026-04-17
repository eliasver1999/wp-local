<?php
class PC_Card_Creator {
    
    public static function create_personalized_card($template_path, $data, $output_path) {
        // Check if GD library is available
        if (!extension_loaded('gd')) {
            return new WP_Error('gd_missing', 'GD library is not installed');
        }
        
        // Load the template image
        $image = imagecreatefromjpeg($template_path);
        if (!$image) {
            return new WP_Error('image_load_failed', 'Failed to load template image');
        }
        
        // Set up text color (black)
        $text_color = imagecolorallocate($image, 0, 0, 0);
        
        // Set up font path (use a TrueType font)
        $font_path = PC_PLUGIN_DIR . 'assets/fonts/arial.ttf';
        
        // Check if font exists, if not use built-in font
        $use_ttf = file_exists($font_path);
        
        // Add personalized text to the card
        if (isset($data['name'])) {
            if ($use_ttf) {
                imagettftext($image, 24, 0, 100, 150, $text_color, $font_path, $data['name']);
            } else {
                imagestring($image, 5, 100, 150, $data['name'], $text_color);
            }
        }
        
        if (isset($data['message']) && !empty($data['message'])) {
            // Word wrap for longer messages
            $wrapped_text = wordwrap($data['message'], 40, "\n");
            $lines = explode("\n", $wrapped_text);
            $y_position = 250;
            
            foreach ($lines as $line) {
                if ($use_ttf) {
                    imagettftext($image, 16, 0, 100, $y_position, $text_color, $font_path, $line);
                } else {
                    imagestring($image, 3, 100, $y_position, $line, $text_color);
                }
                $y_position += 30;
            }
        }
        
        if (isset($data['date']) && !empty($data['date'])) {
            $formatted_date = date('F j, Y', strtotime($data['date']));
            if ($use_ttf) {
                imagettftext($image, 14, 0, 100, 500, $text_color, $font_path, $formatted_date);
            } else {
                imagestring($image, 2, 100, 500, $formatted_date, $text_color);
            }
        }
        
        // Save the image
        $result = imagejpeg($image, $output_path, 90);
        imagedestroy($image);
        
        return $result ? $output_path : new WP_Error('save_failed', 'Failed to save image');
    }
    
    public static function get_available_templates($subscription_level) {
        $templates_dir = PC_PLUGIN_DIR . 'templates/cards/';
        $templates = array();
        
        // Define template access based on subscription
        $template_access = array(
            'basic' => array('template-basic.jpg'),
            'premium' => array('template-basic.jpg', 'template-premium.jpg', 'template-special.jpg'),
            'vip' => array('template-basic.jpg', 'template-premium.jpg', 'template-special.jpg', 'template-vip.jpg', 'template-exclusive.jpg')
        );
        
        $allowed_templates = isset($template_access[$subscription_level]) ? 
            $template_access[$subscription_level] : 
            $template_access['basic'];
        
        foreach ($allowed_templates as $template_file) {
            if (file_exists($templates_dir . $template_file)) {
                $templates[] = array(
                    'file' => $template_file,
                    'url' => PC_PLUGIN_URL . 'templates/cards/' . $template_file,
                    'name' => ucfirst(str_replace(array('template-', '.jpg'), '', $template_file))
                );
            }
        }
        
        return $templates;
    }
}
