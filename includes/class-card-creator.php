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
        if (!file_exists($font_path)) {
            $found = glob(PC_PLUGIN_DIR . 'assets/fonts/*.ttf');
            $font_path = $found ? $found[0] : $font_path;
        }
        $use_ttf = file_exists($font_path);

        // Composite member photo first so text draws on top
        if (get_option('pc_field_image_enabled', '0') === '1' && !empty($data['image'])) {
            self::draw_image(
                $image,
                $data['image'],
                (int) get_option('pc_field_image_x', 400),
                (int) get_option('pc_field_image_y', 100),
                (int) get_option('pc_field_image_w', 150),
                (int) get_option('pc_field_image_h', 150)
            );
        }

        // Text fields: key => data key
        $text_fields = array(
            'name'        => 'name',
            'father_name' => 'father_name',
            'sport'       => 'sport',
            'member_id'   => 'member_id',
        );
        foreach ($text_fields as $opt_key => $data_key) {
            if (empty($data[$data_key])) continue;
            if (get_option("pc_field_{$opt_key}_enabled", '1') !== '1') continue;
            self::draw_text(
                $image, $use_ttf, $font_path,
                (string) $data[$data_key],
                (int) get_option("pc_field_{$opt_key}_x", 100),
                (int) get_option("pc_field_{$opt_key}_y", 150),
                (int) get_option("pc_field_{$opt_key}_size", 24),
                get_option("pc_field_{$opt_key}_color", '#000000')
            );
        }

        // Expiry (formatted)
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

    /**
     * Generate the back of the card — plain copy of the back template, no text.
     */
    public static function create_card_back($output_path) {
        $back_template = get_option('pc_default_back_template', '');
        if (!$back_template) return new WP_Error('no_back_template', 'No back template configured.');

        $src = PC_PLUGIN_DIR . 'templates/cards/' . basename($back_template);
        if (!file_exists($src)) return new WP_Error('back_template_missing', 'Back template file not found.');

        return copy($src, $output_path)
            ? $output_path
            : new WP_Error('back_copy_failed', 'Failed to copy back template.');
    }

    private static function draw_text($image, $use_ttf, $font_path, $text, $x, $y, $size, $hex_color) {
        $color = self::hex_to_gd_color($image, $hex_color);

        if ($use_ttf) {
            imagettftext($image, $size, 0, $x, $y, $color, $font_path, $text);
        } else {
            imagestring($image, 5, $x, $y, $text, $color);
        }
    }

    private static function draw_image($dest, $src_path_or_url, $x, $y, $w, $h) {
        // Resolve URL to local path when possible
        $local = $src_path_or_url;
        if (preg_match('#^https?://#i', $local)) {
            $upload = wp_upload_dir();
            if (strpos($local, $upload['baseurl']) === 0) {
                $local = str_replace($upload['baseurl'], $upload['basedir'], $local);
            }
        }
        if (!file_exists($local)) return;

        $info = @getimagesize($local);
        if (!$info) return;

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($local); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($local);  break;
            case IMAGETYPE_GIF:  $src = @imagecreatefromgif($local);  break;
            default: return;
        }
        if (!$src) return;

        imagecopyresampled($dest, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);
    }

    private static function draw_qr($image, $content, $x, $y, $size) {
        $url      = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($content);
        $response = wp_remote_get($url, array('timeout' => 10));
        if (is_wp_error($response)) return;

        $body = wp_remote_retrieve_body($response);
        if (!$body) return;

        $src = @imagecreatefromstring($body);
        if (!$src) return;

        imagecopyresampled($image, $src, $x, $y, 0, 0, $size, $size, imagesx($src), imagesy($src));
        imagedestroy($src);
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
