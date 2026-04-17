<?php
class PC_Card_Creator {

    public static function create_personalized_card($template_path, $data, $output_path) {
        if (!extension_loaded('gd')) {
            return new WP_Error('gd_missing', 'GD library is not installed');
        }

        $image = imagecreatefromjpeg($template_path);
        if (!$image) {
            return new WP_Error('image_load_failed', 'Failed to load template image');
        }

        $font_file = get_option('pc_font_file', 'arial.ttf');
        $font_path = PC_PLUGIN_DIR . 'assets/fonts/' . $font_file;
        // Fall back to any available ttf if the saved one is gone
        if (!file_exists($font_path)) {
            $found = glob(PC_PLUGIN_DIR . 'assets/fonts/*.ttf');
            $font_path = $found ? $found[0] : $font_path;
        }
        $use_ttf = file_exists($font_path);

        // Draw name field
        if (!empty($data['name']) && get_option('pc_field_name_enabled', '1') === '1') {
            self::draw_text(
                $image, $use_ttf, $font_path,
                $data['name'],
                (int) get_option('pc_field_name_x',    100),
                (int) get_option('pc_field_name_y',    150),
                (int) get_option('pc_field_name_size',  24),
                get_option('pc_field_name_color', '#000000')
            );
        }

        // Draw expiry date field
        if (!empty($data['date']) && get_option('pc_field_expiry_enabled', '1') === '1') {
            $fmt            = get_option('pc_field_expiry_format', 'd/m/Y');
            $formatted_date = date($fmt, strtotime($data['date']));
            self::draw_text(
                $image, $use_ttf, $font_path,
                $formatted_date,
                (int) get_option('pc_field_expiry_x',    100),
                (int) get_option('pc_field_expiry_y',    220),
                (int) get_option('pc_field_expiry_size',  18),
                get_option('pc_field_expiry_color', '#000000')
            );
        }

        $result = imagejpeg($image, $output_path, 90);
        imagedestroy($image);

        return $result ? $output_path : new WP_Error('save_failed', 'Failed to save card image');
    }

    private static function draw_text($image, $use_ttf, $font_path, $text, $x, $y, $size, $hex_color) {
        $color = self::hex_to_gd_color($image, $hex_color);

        if ($use_ttf) {
            imagettftext($image, $size, 0, $x, $y, $color, $font_path, $text);
        } else {
            // Fall back to built-in bitmap font (no size/color control)
            imagestring($image, 5, $x, $y, $text, $color);
        }
    }

    private static function hex_to_gd_color($image, $hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return imagecolorallocate($image, $r, $g, $b);
    }
}
