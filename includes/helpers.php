<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve a member's photo URL, with automatic lookup by member ID.
 *
 * Priority:
 *   1. Explicit pc_member_image meta (set via CSV import or the admin field).
 *   2. A Media Library attachment whose file is named <member_id>.<ext>
 *      (works with a plain Media Library upload, wherever WP stored it).
 *   3. A file at uploads/<dir>/<member_id>.<ext> (default dir: seo-photos).
 *
 * Returns '' when no photo can be found.
 *
 * @param int    $user_id
 * @param string $member_id Optional; falls back to the pc_member_id meta.
 * @return string
 */
function pc_resolve_member_image($user_id, $member_id = '') {
    // 1. Explicit value always wins.
    $explicit = (string) get_user_meta($user_id, 'pc_member_image', true);
    if ($explicit !== '') {
        return $explicit;
    }

    if ($member_id === '') {
        $member_id = (string) get_user_meta($user_id, 'pc_member_id', true);
    }
    if ($member_id === '') {
        return '';
    }

    // 2. Media Library: an attachment whose filename is <member_id>.<ext>.
    global $wpdb;
    $in_subdir = '%/' . $wpdb->esc_like($member_id) . '.%'; // e.g. 2026/07/030-002-2004.png
    $at_root   = $wpdb->esc_like($member_id) . '.%';        // e.g. 030-002-2004.png (no date folder)
    $attach_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = '_wp_attached_file'
           AND (meta_value LIKE %s OR meta_value LIKE %s)
         ORDER BY post_id DESC LIMIT 1",
        $in_subdir,
        $at_root
    ));
    if ($attach_id) {
        $url = wp_get_attachment_url($attach_id);
        if ($url) {
            return $url;
        }
    }

    // 3. Convention folder on disk: uploads/<dir>/<member_id>.<ext>.
    $dir    = trim((string) apply_filters('pc_member_photo_dir', 'seo-photos'), '/');
    $exts   = apply_filters('pc_member_photo_exts', array('png', 'jpg', 'jpeg', 'gif'));
    $upload = wp_upload_dir();
    if ($dir !== '' && !empty($upload['basedir']) && !empty($upload['baseurl'])) {
        foreach ($exts as $ext) {
            $rel = $dir . '/' . $member_id . '.' . $ext;
            if (file_exists($upload['basedir'] . '/' . $rel)) {
                return $upload['baseurl'] . '/' . $rel;
            }
        }
    }

    return '';
}
