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
        if (get_option('pc_field_image_enabled', '0') === '1') {
            $img_x    = (int) get_option('pc_field_image_x', 400);
            $img_y    = (int) get_option('pc_field_image_y', 100);
            $img_w    = (int) get_option('pc_field_image_w', 150);
            $img_h    = (int) get_option('pc_field_image_h', 150);
            $img_opts = array(
                'fit'      => get_option('pc_field_image_fit', 'cover'),
                'circular' => get_option('pc_field_image_circular', '0') === '1',
            );

            $drew = false;
            if (!empty($data['image'])) {
                $drew = self::draw_image($image, $data['image'], $img_x, $img_y, $img_w, $img_h, $img_opts);
            }
            // Fallback to configured default avatar if member has no photo or it failed to load
            if (!$drew) {
                $default = get_option('pc_field_image_default', '');
                if ($default) {
                    self::draw_image($image, $default, $img_x, $img_y, $img_w, $img_h, $img_opts);
                }
            }
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

    /**
     * Composite a (possibly remote) image onto $dest at the given rect.
     * Returns true if the image was drawn, false if it could not be resolved/decoded.
     *
     * $opts:
     *   fit      => 'cover' (default) | 'contain' | 'stretch'
     *   circular => bool — mask the result into a circle inscribed in the rect
     */
    private static function draw_image($dest, $src_path_or_url, $x, $y, $w, $h, $opts = array()) {
        $opts = array_merge(array('fit' => 'cover', 'circular' => false), $opts);

        list($local, $is_temp) = self::resolve_image_to_local($src_path_or_url);
        if (!$local) return false;

        try {
            $info = @getimagesize($local);
            if (!$info) return false;

            switch ($info[2]) {
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($local); break;
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($local);  break;
                case IMAGETYPE_GIF:  $src = @imagecreatefromgif($local);  break;
                default: return false;
            }
            if (!$src) return false;

            $sw = imagesx($src);
            $sh = imagesy($src);

            list($src_x, $src_y, $src_w, $src_h, $dst_x, $dst_y, $dst_w, $dst_h) =
                self::compute_fit_rect($sw, $sh, $x, $y, $w, $h, $opts['fit']);

            if (!empty($opts['circular'])) {
                self::draw_image_circular(
                    $dest, $src, $x, $y, $w, $h,
                    $src_x, $src_y, $src_w, $src_h, $dst_x, $dst_y, $dst_w, $dst_h
                );
            } else {
                imagecopyresampled(
                    $dest, $src,
                    $dst_x, $dst_y, $src_x, $src_y,
                    $dst_w, $dst_h, $src_w, $src_h
                );
            }

            imagedestroy($src);
            return true;
        } finally {
            if ($is_temp && $local && file_exists($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * Resolve a path/URL to a local file. Remote URLs are downloaded to a temp file.
     * Returns [local_path|null, is_temp_bool].
     */
    private static function resolve_image_to_local($src) {
        $src = (string) $src;
        if ($src === '') return array(null, false);

        // Local filesystem path
        if (!preg_match('#^https?://#i', $src)) {
            return array(file_exists($src) ? $src : null, false);
        }

        // Same-host upload URL → map to local path (avoid pointless HTTP roundtrip)
        $upload = wp_upload_dir();
        if (!empty($upload['baseurl']) && strpos($src, $upload['baseurl']) === 0) {
            $local = str_replace($upload['baseurl'], $upload['basedir'], $src);
            return array(file_exists($local) ? $local : null, false);
        }

        // Genuine remote — download to a temp file. Caller is responsible for unlinking.
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tmp = download_url($src, 15);
        if (is_wp_error($tmp) || !$tmp || !file_exists($tmp)) return array(null, false);
        return array($tmp, true);
    }

    /**
     * Given source w/h and destination rect (x,y,w,h), return the source-crop and
     * destination-paste rectangles for the requested fit mode.
     *   stretch — fill the whole dst rect, distorting aspect.
     *   cover   — crop source to match dst aspect, then fill dst (no distortion).
     *   contain — fit source entirely inside dst (letterbox margins).
     *
     * Returns [src_x, src_y, src_w, src_h, dst_x, dst_y, dst_w, dst_h].
     */
    private static function compute_fit_rect($sw, $sh, $x, $y, $w, $h, $fit) {
        if ($fit === 'stretch' || $sw <= 0 || $sh <= 0) {
            return array(0, 0, $sw, $sh, $x, $y, $w, $h);
        }
        $src_aspect = $sw / $sh;
        $dst_aspect = $w / $h;

        if ($fit === 'cover') {
            if ($src_aspect > $dst_aspect) {
                // Source is wider — crop horizontally
                $new_sw = (int) round($sh * $dst_aspect);
                $src_x  = (int) round(($sw - $new_sw) / 2);
                return array($src_x, 0, $new_sw, $sh, $x, $y, $w, $h);
            }
            // Source is taller — crop vertically
            $new_sh = (int) round($sw / $dst_aspect);
            $src_y  = (int) round(($sh - $new_sh) / 2);
            return array(0, $src_y, $sw, $new_sh, $x, $y, $w, $h);
        }

        // contain — letterbox into the rect
        if ($src_aspect > $dst_aspect) {
            $new_dh    = (int) round($w / $src_aspect);
            $dst_y_off = $y + (int) round(($h - $new_dh) / 2);
            return array(0, 0, $sw, $sh, $x, $dst_y_off, $w, $new_dh);
        }
        $new_dw    = (int) round($h * $src_aspect);
        $dst_x_off = $x + (int) round(($w - $new_dw) / 2);
        return array(0, 0, $sw, $sh, $dst_x_off, $y, $new_dw, $h);
    }

    /**
     * Composite a source image onto $dest masked to a circle inscribed in the
     * (x,y,w,h) rect. Pre-computed source/dest sub-rects come from compute_fit_rect.
     */
    private static function draw_image_circular($dest, $src, $x, $y, $w, $h,
        $src_x, $src_y, $src_w, $src_h, $dst_x, $dst_y, $dst_w, $dst_h
    ) {
        // Build a w×h transparent canvas, paint the resampled image into it,
        // then mask everything outside the inscribed ellipse to transparent.
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $transparent);

        // dst_* are in absolute $dest coords; convert to canvas-local.
        $local_dx = $dst_x - $x;
        $local_dy = $dst_y - $y;

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas, $src,
            $local_dx, $local_dy, $src_x, $src_y,
            $dst_w, $dst_h, $src_w, $src_h
        );

        // Punch out pixels outside the inscribed ellipse.
        imagealphablending($canvas, false);
        $cx = $w / 2.0; $cy = $h / 2.0;
        $rx = $w / 2.0; $ry = $h / 2.0;
        for ($py = 0; $py < $h; $py++) {
            $ny = ($py + 0.5 - $cy) / $ry;
            $ny2 = $ny * $ny;
            for ($px = 0; $px < $w; $px++) {
                $nx = ($px + 0.5 - $cx) / $rx;
                if (($nx * $nx + $ny2) > 1.0) {
                    imagesetpixel($canvas, $px, $py, $transparent);
                }
            }
        }

        // Composite onto destination with alpha blending so transparent edges
        // pass through to the underlying card template.
        imagealphablending($dest, true);
        imagecopy($dest, $canvas, $x, $y, 0, 0, $w, $h);
        imagedestroy($canvas);
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
