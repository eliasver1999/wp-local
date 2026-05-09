<?php
// Admin menu
add_action('admin_menu', 'pc_add_admin_menu');
function pc_add_admin_menu() {
    add_menu_page(
        __('Personalized Cards', 'personalized-cards'),
        __('Personalized Cards', 'personalized-cards'),
        'manage_options',
        'personalized-cards',
        'pc_admin_page',
        'dashicons-id-alt',
        30
    );

    add_submenu_page(
        'personalized-cards',
        __('All Cards', 'personalized-cards'),
        __('All Cards', 'personalized-cards'),
        'manage_options',
        'personalized-cards-all',
        'pc_all_cards_page'
    );

    add_submenu_page(
        'personalized-cards',
        __('Settings', 'personalized-cards'),
        __('Settings', 'personalized-cards'),
        'manage_options',
        'personalized-cards-settings',
        'pc_settings_page'
    );
}

// ── Dashboard ──────────────────────────────────────────────────────────────────
function pc_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';

    $total_cards    = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $cards_today    = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", current_time('Y-m-d')));
    $active_members = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'");
    $total_users    = count_users();

    // Handle bulk actions
    $bulk_notice = '';
    if (isset($_POST['pc_bulk_create_email']) && check_admin_referer('pc_bulk_actions')) {
        $result = pc_admin_bulk_create_and_email();
        $bulk_notice = sprintf(
            __('Done. Created %d new card(s), emailed %d member(s), skipped %d (already had a card).', 'personalized-cards'),
            $result['created'], $result['emailed'], $result['skipped']
        );
    } elseif (isset($_POST['pc_bulk_email']) && check_admin_referer('pc_bulk_actions')) {
        $count = pc_admin_bulk_email_active_members();
        $bulk_notice = sprintf(__('Card emails sent to %d active member(s).', 'personalized-cards'), $count);
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards', 'personalized-cards'); ?></h1>

        <?php if ($bulk_notice): ?>
            <div class="notice notice-success"><p><?php echo esc_html($bulk_notice); ?></p></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="pc-admin-stats">
            <div class="pc-stat-box">
                <h3><?php echo number_format($total_cards); ?></h3>
                <p><?php _e('Total Cards', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($cards_today); ?></h3>
                <p><?php _e('Cards Today', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($active_members); ?></h3>
                <p><?php _e('Active Members', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($total_users['total_users']); ?></h3>
                <p><?php _e('Total Users', 'personalized-cards'); ?></p>
            </div>
        </div>

        <!-- Create card for user -->
        <div class="pc-admin-section">
            <h2><?php _e('Create Card for Member', 'personalized-cards'); ?></h2>
            <?php
            $default_template = get_option('pc_default_template', '');
            $templates_dir    = PC_PLUGIN_DIR . 'templates/cards/';
            $template_files   = glob($templates_dir . '*.jpg') ?: array();
            if (!$default_template && !$template_files): ?>
                <p class="pc-notice-warn">
                    <?php _e('No card templates found. Add a .jpg file to /templates/cards/ and set it as the default template in Settings.', 'personalized-cards'); ?>
                </p>
            <?php else: ?>
            <form id="pc-admin-create-card-form">
                <?php wp_nonce_field('pc_admin_create_card', 'pc_admin_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc-admin-user"><?php _e('Member', 'personalized-cards'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name'             => 'user_id',
                                'id'               => 'pc-admin-user',
                                'show_option_none' => __('— Select member —', 'personalized-cards'),
                                'meta_key'         => 'pc_subscription_active',
                                'meta_value'       => '1',
                                'orderby'          => 'display_name',
                            ));
                            ?>
                            <p class="description"><?php _e('Only active members are shown.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc-admin-card-name"><?php _e('Name on Card', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="card_name" id="pc-admin-card-name" class="regular-text"
                                   placeholder="<?php esc_attr_e('Leave blank to use display name', 'personalized-cards'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc-admin-card-message"><?php _e('Message (optional)', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="card_message" id="pc-admin-card-message" class="regular-text"
                                   maxlength="100">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Send Email', 'personalized-cards'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="send_email" value="1" checked>
                                <?php _e('Email the card to the member after creation', 'personalized-cards'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary" id="pc-create-card-btn">
                        <?php _e('Create Card', 'personalized-cards'); ?>
                    </button>
                    <span id="pc-create-card-result" style="margin-left:12px;"></span>
                </p>
            </form>
            <?php endif; ?>
        </div>

        <!-- Bulk actions -->
        <div class="pc-admin-section">
            <h2><?php _e('Bulk Actions', 'personalized-cards'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pc_bulk_actions'); ?>
                <p>
                    <button type="submit" name="pc_bulk_create_email" class="button button-primary"
                            onclick="return confirm('<?php esc_attr_e('Create a card for every active member who doesn\'t have one yet, then email all of them?', 'personalized-cards'); ?>')">
                        <?php _e('Create & Email All Active Members', 'personalized-cards'); ?>
                    </button>
                    <span style="margin:0 12px;color:#aaa;">|</span>
                    <button type="submit" name="pc_bulk_email" class="button button-secondary"
                            onclick="return confirm('<?php esc_attr_e('Re-send card emails to all active members who already have a card?', 'personalized-cards'); ?>')">
                        <?php _e('Re-email Existing Cards', 'personalized-cards'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php _e('<strong>Create &amp; Email</strong> — generates a card for anyone missing one, then emails everyone.<br><strong>Re-email</strong> — only sends emails; does not create new cards.', 'personalized-cards'); ?>
                </p>
            </form>
        </div>

        <!-- Recent cards -->
        <div class="pc-admin-section">
            <h2><?php _e('Recent Cards', 'personalized-cards'); ?></h2>
            <?php
            $recent = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 10");
            pc_render_cards_table($recent);
            ?>
        </div>
    </div>

    <script>
    jQuery(function($) {
        $('#pc-admin-create-card-form').on('submit', function(e) {
            e.preventDefault();
            var $btn    = $('#pc-create-card-btn');
            var $result = $('#pc-create-card-result');
            $btn.prop('disabled', true).text('<?php esc_js(_e('Creating…', 'personalized-cards')); ?>');
            $result.text('');

            $.post(pcAdminAjax.ajaxurl, $(this).serialize() + '&action=pc_admin_create_card', function(res) {
                if (res.success) {
                    $result.css('color', 'green').text(res.data.message);
                } else {
                    $result.css('color', 'red').text(res.data.message);
                }
                $btn.prop('disabled', false).text('<?php esc_js(_e('Create Card', 'personalized-cards')); ?>');
            });
        });
    });
    </script>
    <?php
}

// ── All Cards page ─────────────────────────────────────────────────────────────
function pc_all_cards_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';
    $cards = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1><?php _e('All Cards', 'personalized-cards'); ?></h1>
        <?php pc_render_cards_table($cards, true); ?>
    </div>
    <?php
}

// ── Shared table renderer ──────────────────────────────────────────────────────
function pc_render_cards_table($cards, $show_all_actions = false) {
    if (!$cards) {
        echo '<p>' . __('No cards found.', 'personalized-cards') . '</p>';
        return;
    }
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . __('ID', 'personalized-cards') . '</th>';
    echo '<th>' . __('Member', 'personalized-cards') . '</th>';
    echo '<th>' . __('Created', 'personalized-cards') . '</th>';
    echo '<th>' . __('Card', 'personalized-cards') . '</th>';
    echo '<th>' . __('Actions', 'personalized-cards') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($cards as $card) {
        $user = get_userdata($card->user_id);
        echo '<tr>';
        echo '<td>' . absint($card->id) . '</td>';
        echo '<td>' . ($user ? esc_html($user->display_name) . '<br><small>' . esc_html($user->user_email) . '</small>' : '<em>Unknown</em>') . '</td>';
        echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($card->created_at))) . '</td>';
        echo '<td>';
        if ($card->card_image) {
            echo '<a href="' . esc_url($card->card_image) . '" target="_blank">';
            echo '<img src="' . esc_url($card->card_image) . '" style="max-width:80px;height:auto;border:1px solid #ddd;">';
            echo '</a>';
        }
        echo '</td>';
        echo '<td>';
        if ($card->card_image) {
            echo '<a href="' . esc_url($card->card_image) . '" download class="button button-small">' . __('Download', 'personalized-cards') . '</a> ';
        }
        if ($user) {
            echo '<button class="button button-small pc-send-card-email" data-card-id="' . absint($card->id) . '" data-user-email="' . esc_attr($user->user_email) . '" data-user-name="' . esc_attr($user->display_name) . '">' . __('Email', 'personalized-cards') . '</button> ';
        }
        if ($show_all_actions) {
            echo '<button class="button button-small pc-delete-card" data-card-id="' . absint($card->id) . '" style="color:red;">' . __('Delete', 'personalized-cards') . '</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    ?>
    <script>
    jQuery(function($) {
        // Individual email
        $(document).on('click', '.pc-send-card-email', function() {
            var $btn = $(this);
            var cardId = $btn.data('card-id');
            $btn.prop('disabled', true).text('<?php esc_js(_e('Sending…', 'personalized-cards')); ?>');
            $.post(pcAdminAjax.ajaxurl, {
                action: 'pc_admin_send_card_email',
                card_id: cardId,
                nonce: pcAdminAjax.nonce
            }, function(res) {
                if (res.success) {
                    $btn.text('<?php esc_js(_e('Sent!', 'personalized-cards')); ?>').css('color', 'green');
                } else {
                    $btn.prop('disabled', false).text('<?php esc_js(_e('Email', 'personalized-cards')); ?>');
                    alert(res.data.message);
                }
            });
        });

        // Delete card
        $(document).on('click', '.pc-delete-card', function() {
            if (!confirm('<?php esc_js(_e('Delete this card?', 'personalized-cards')); ?>')) return;
            var $btn = $(this);
            var $row = $btn.closest('tr');
            $.post(pcAdminAjax.ajaxurl, {
                action: 'pc_delete_card',
                card_id: $btn.data('card-id'),
                nonce: pcAdminAjax.nonce
            }, function(res) {
                if (res.success) {
                    $row.fadeOut();
                } else {
                    alert(res.data.message);
                }
            });
        });
    });
    </script>
    <?php
}

// ── Settings page ──────────────────────────────────────────────────────────────
function pc_settings_page() {
    $templates_dir = PC_PLUGIN_DIR . 'templates/cards/';
    $fonts_dir     = PC_PLUGIN_DIR . 'assets/fonts/';
    $notices = array();

    // Handle template upload (separate form)
    if (isset($_POST['pc_upload_template']) && check_admin_referer('pc_upload_template_action')) {
        if (!empty($_FILES['pc_template_file']['name'])) {
            $file = $_FILES['pc_template_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg'), true)) {
                $notices[] = array('error', __('Only JPG/JPEG files are allowed.', 'personalized-cards'));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $notices[] = array('error', __('Upload error. Please try again.', 'personalized-cards'));
            } else {
                wp_mkdir_p($templates_dir);
                $dest_name = sanitize_file_name($file['name']);
                // Force .jpg extension
                $dest_name = preg_replace('/\.jpeg$/i', '.jpg', $dest_name);
                $dest = $templates_dir . $dest_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Auto-select as default if none set
                    if (!get_option('pc_default_template')) {
                        update_option('pc_default_template', $dest_name);
                    }
                    $notices[] = array('success', sprintf(__('Template "%s" uploaded successfully.', 'personalized-cards'), $dest_name));
                } else {
                    $notices[] = array('error', __('Failed to move uploaded file. Check directory permissions.', 'personalized-cards'));
                }
            }
        }
    }

    // Handle font upload
    if (isset($_POST['pc_upload_font']) && check_admin_referer('pc_upload_font_action')) {
        if (!empty($_FILES['pc_font_file']['name'])) {
            $file = $_FILES['pc_font_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'ttf') {
                $notices[] = array('error', __('Only TTF font files are allowed.', 'personalized-cards'));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $notices[] = array('error', __('Font upload error. Please try again.', 'personalized-cards'));
            } else {
                wp_mkdir_p($fonts_dir);
                $dest_name = sanitize_file_name($file['name']);
                if (move_uploaded_file($file['tmp_name'], $fonts_dir . $dest_name)) {
                    if (!get_option('pc_font_file')) {
                        update_option('pc_font_file', $dest_name);
                    }
                    $notices[] = array('success', sprintf(__('Font "%s" uploaded.', 'personalized-cards'), $dest_name));
                } else {
                    $notices[] = array('error', __('Failed to save font file. Check directory permissions.', 'personalized-cards'));
                }
            }
        }
    }

    // Handle template delete
    if (isset($_POST['pc_delete_template']) && check_admin_referer('pc_delete_template_action')) {
        $del = sanitize_file_name($_POST['pc_template_to_delete'] ?? '');
        $del_path = $templates_dir . $del;
        if ($del && file_exists($del_path) && strpos(realpath($del_path), realpath($templates_dir)) === 0) {
            unlink($del_path);
            if (get_option('pc_default_template') === $del) {
                delete_option('pc_default_template');
            }
            $notices[] = array('success', sprintf(__('Template "%s" deleted.', 'personalized-cards'), $del));
        }
    }

    // Handle main settings save
    if (isset($_POST['pc_save_settings'])) {
        check_admin_referer('pc_settings_save');

        update_option('pc_default_template',       sanitize_text_field($_POST['pc_default_template']));
        update_option('pc_email_from_name',         sanitize_text_field($_POST['pc_email_from_name']));
        update_option('pc_email_from_address',      sanitize_email($_POST['pc_email_from_address']));
        update_option('pc_email_subject',           sanitize_text_field($_POST['pc_email_subject']));
        update_option('pc_email_message',           wp_kses_post($_POST['pc_email_message']));
        update_option('pc_enable_apple_wallet',     isset($_POST['pc_enable_apple_wallet']) ? '1' : '0');
        update_option('pc_enable_google_wallet',    isset($_POST['pc_enable_google_wallet']) ? '1' : '0');
        update_option('pc_google_wallet_issuer_id', sanitize_text_field($_POST['pc_google_wallet_issuer_id']));

        // Font & text overlay settings
        update_option('pc_font_file', sanitize_file_name($_POST['pc_font_file'] ?? ''));
        foreach (array('name', 'expiry') as $field) {
            update_option("pc_field_{$field}_enabled",   isset($_POST["pc_field_{$field}_enabled"]) ? '1' : '0');
            update_option("pc_field_{$field}_x",         absint($_POST["pc_field_{$field}_x"] ?? 0));
            update_option("pc_field_{$field}_y",         absint($_POST["pc_field_{$field}_y"] ?? 0));
            update_option("pc_field_{$field}_size",      absint($_POST["pc_field_{$field}_size"] ?? 20));
            update_option("pc_field_{$field}_color",     sanitize_hex_color($_POST["pc_field_{$field}_color"] ?? '#000000') ?: '#000000');
        }
        update_option('pc_field_expiry_format', sanitize_text_field($_POST['pc_field_expiry_format'] ?? 'd/m/Y'));

        $notices[] = array('success', __('Settings saved.', 'personalized-cards'));
    }

    $default_template     = get_option('pc_default_template', '');
    $email_from_name      = get_option('pc_email_from_name', get_bloginfo('name'));
    $email_from_address   = get_option('pc_email_from_address', get_bloginfo('admin_email'));
    $email_subject        = get_option('pc_email_subject', __('Your Membership Card', 'personalized-cards'));
    $email_message        = get_option('pc_email_message', __('Please find your membership card attached.', 'personalized-cards'));
    $enable_apple_wallet  = get_option('pc_enable_apple_wallet', '0');
    $enable_google_wallet = get_option('pc_enable_google_wallet', '0');
    $google_wallet_issuer = get_option('pc_google_wallet_issuer_id', '');
    $template_files       = glob($templates_dir . '*.jpg') ?: array();
    $font_files           = glob($fonts_dir . '*.ttf') ?: array();
    $active_font          = get_option('pc_font_file', 'arial.ttf');

    $login_page_url   = get_option('pc_login_page_id')   ? get_permalink(get_option('pc_login_page_id'))   : '';
    $my_card_page_url = get_option('pc_my_card_page_id') ? get_permalink(get_option('pc_my_card_page_id')) : '';

    // Text field defaults
    $fields = array(
        'name'   => array('label' => __('Member Name', 'personalized-cards'),    'default_x' => 100, 'default_y' => 150, 'default_size' => 24, 'default_color' => '#000000'),
        'expiry' => array('label' => __('Expiry Date', 'personalized-cards'),    'default_x' => 100, 'default_y' => 220, 'default_size' => 18, 'default_color' => '#000000'),
        'father_name' => array('label' => __('Father Name', 'personalized-cards'),    'default_x' => 100, 'default_y' => 220, 'default_size' => 18, 'default_color' => '#000000'),
        'sport' => array('label' => __('Sport', 'personalized-cards'),    'default_x' => 100, 'default_y' => 220, 'default_size' => 18, 'default_color' => '#000000'),
        'image' => array('label' => __('Image', 'personalized-cards'),    'default_x' => 100, 'default_y' => 220, 'default_size' => 18, 'default_color' => '#000000')
    );
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards Settings', 'personalized-cards'); ?></h1>

        <?php foreach ($notices as [$type, $msg]): ?>
            <div class="notice notice-<?php echo $type === 'error' ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
        <?php endforeach; ?>

        <?php if ($login_page_url || $my_card_page_url): ?>
        <div class="notice notice-info inline">
            <p>
                <?php if ($login_page_url): ?>
                    <strong><?php _e('Login Page:', 'personalized-cards'); ?></strong>
                    <a href="<?php echo esc_url($login_page_url); ?>" target="_blank"><?php echo esc_html($login_page_url); ?></a><br>
                <?php endif; ?>
                <?php if ($my_card_page_url): ?>
                    <strong><?php _e('My Card Page:', 'personalized-cards'); ?></strong>
                    <a href="<?php echo esc_url($my_card_page_url); ?>" target="_blank"><?php echo esc_html($my_card_page_url); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- ── Upload template ──────────────────────────────── -->
        <div class="pc-admin-section">
            <h2><?php _e('Upload Card Template', 'personalized-cards'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_template_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_template_file"><?php _e('JPG Template File', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_template_file" id="pc_template_file" accept=".jpg,.jpeg">
                            <p class="description"><?php _e('Upload the blank card design as a JPG. Text will be overlaid on top at positions you configure below.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Template', 'personalized-cards'), 'secondary', 'pc_upload_template'); ?>
            </form>

            <?php if ($template_files): ?>
            <h3 style="margin-top:20px;"><?php _e('Uploaded Templates', 'personalized-cards'); ?></h3>
            <div class="pc-template-gallery">
                <?php foreach ($template_files as $file):
                    $fname = basename($file); ?>
                    <div class="pc-template-thumb <?php echo $fname === $default_template ? 'pc-template-active' : ''; ?>">
                        <img src="<?php echo esc_url(PC_PLUGIN_URL . 'templates/cards/' . $fname); ?>" alt="<?php echo esc_attr($fname); ?>">
                        <p><?php echo esc_html($fname); ?></p>
                        <?php if ($fname === $default_template): ?>
                            <span class="pc-badge-active"><?php _e('Active', 'personalized-cards'); ?></span>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('pc_delete_template_action'); ?>
                                <input type="hidden" name="pc_template_to_delete" value="<?php echo esc_attr($fname); ?>">
                                <button type="submit" name="pc_delete_template" class="button button-small"
                                        onclick="return confirm('<?php esc_attr_e('Delete this template?', 'personalized-cards'); ?>')">
                                    <?php _e('Delete', 'personalized-cards'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Upload font ──────────────────────────────────── -->
        <div class="pc-admin-section">
            <h2><?php _e('Upload Font', 'personalized-cards'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_font_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_font_file_upload"><?php _e('TTF Font File', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_font_file" id="pc_font_file_upload" accept=".ttf">
                            <p class="description"><?php _e('Upload a TrueType (.ttf) font. This font will be used for all text printed on the card.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Font', 'personalized-cards'), 'secondary', 'pc_upload_font'); ?>
            </form>
            <?php if ($font_files): ?>
                <p><?php _e('Available fonts:', 'personalized-cards'); ?>
                <?php foreach ($font_files as $f): $fn = basename($f); ?>
                    <code style="margin-right:8px;"><?php echo esc_html($fn); ?><?php echo $fn === $active_font ? ' ✓' : ''; ?></code>
                <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- ── Main settings ────────────────────────────────── -->
        <form method="post">
            <?php wp_nonce_field('pc_settings_save'); ?>

            <h2><?php _e('Card Template & Text Layout', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="pc_default_template"><?php _e('Active Template', 'personalized-cards'); ?></label></th>
                    <td>
                        <?php if ($template_files): ?>
                            <select name="pc_default_template" id="pc_default_template">
                                <option value=""><?php _e('— Select —', 'personalized-cards'); ?></option>
                                <?php foreach ($template_files as $file):
                                    $fname = basename($file); ?>
                                    <option value="<?php echo esc_attr($fname); ?>" <?php selected($default_template, $fname); ?>>
                                        <?php echo esc_html($fname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <p class="description"><?php _e('No templates yet. Upload one above.', 'personalized-cards'); ?></p>
                            <input type="hidden" name="pc_default_template" value="">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_font_file_select"><?php _e('Active Font', 'personalized-cards'); ?></label></th>
                    <td>
                        <?php if ($font_files): ?>
                            <select name="pc_font_file" id="pc_font_file_select">
                                <?php foreach ($font_files as $f): $fn = basename($f); ?>
                                    <option value="<?php echo esc_attr($fn); ?>" <?php selected($active_font, $fn); ?>>
                                        <?php echo esc_html($fn); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <p class="description"><?php _e('No fonts uploaded yet. Upload a TTF font above.', 'personalized-cards'); ?></p>
                            <input type="hidden" name="pc_font_file" value="">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ($default_template): ?>
            <div class="pc-text-layout-editor">
                <h3><?php _e('Text Overlay Positions', 'personalized-cards'); ?></h3>
                <p class="description">
                    <?php _e('X = pixels from left edge, Y = pixels from top edge of the card image. Use the preview below to find the right coordinates.', 'personalized-cards'); ?>
                </p>

                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:20px;">
                    <div>
                        <strong><?php _e('Template preview', 'personalized-cards'); ?></strong><br>
                        <div style="position:relative;display:inline-block;margin-top:8px;">
                            <img id="pc-template-preview-img"
                                 src="<?php echo esc_url(PC_PLUGIN_URL . 'templates/cards/' . $default_template); ?>"
                                 style="max-width:400px;height:auto;border:1px solid #ddd;display:block;">
                            <span id="pc-crosshair" style="position:absolute;width:10px;height:10px;background:red;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;display:none;"></span>
                        </div>
                        <p class="description" style="max-width:400px;"><?php _e('Click on the image to get pixel coordinates. The image is shown at reduced size — coordinates are scaled to actual pixels.', 'personalized-cards'); ?></p>
                        <p id="pc-click-coords" style="font-family:monospace;font-weight:bold;"></p>
                    </div>

                    <div style="flex:1;min-width:300px;">
                        <table class="form-table" style="margin-top:0;">
                            <?php foreach ($fields as $key => $cfg):
                                $enabled = get_option("pc_field_{$key}_enabled", '1');
                                $x       = get_option("pc_field_{$key}_x",       $cfg['default_x']);
                                $y       = get_option("pc_field_{$key}_y",       $cfg['default_y']);
                                $size    = get_option("pc_field_{$key}_size",    $cfg['default_size']);
                                $color   = get_option("pc_field_{$key}_color",   $cfg['default_color']);
                            ?>
                            <tr>
                                <th style="padding-top:16px;">
                                    <label>
                                        <input type="checkbox" name="pc_field_<?php echo $key; ?>_enabled" value="1" <?php checked($enabled, '1'); ?>>
                                        <?php echo esc_html($cfg['label']); ?>
                                    </label>
                                </th>
                                <td>
                                    <label><?php _e('X', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_<?php echo $key; ?>_x" value="<?php echo esc_attr($x); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Y', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_<?php echo $key; ?>_y" value="<?php echo esc_attr($y); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Size', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_<?php echo $key; ?>_size" value="<?php echo esc_attr($size); ?>" min="8" max="120" style="width:60px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Color', 'personalized-cards'); ?>
                                        <input type="color" name="pc_field_<?php echo $key; ?>_color" value="<?php echo esc_attr($color); ?>">
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th><label for="pc_field_expiry_format"><?php _e('Expiry Date Format', 'personalized-cards'); ?></label></th>
                                <td>
                                    <input type="text" name="pc_field_expiry_format" id="pc_field_expiry_format"
                                           value="<?php echo esc_attr(get_option('pc_field_expiry_format', 'd/m/Y')); ?>"
                                           style="width:120px;">
                                    <p class="description"><?php _e('PHP date format. Examples: d/m/Y → 31/12/2027, F j, Y → December 31, 2027', 'personalized-cards'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <h2><?php _e('Email Settings', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="pc_email_from_name"><?php _e('From Name', 'personalized-cards'); ?></label></th>
                    <td><input type="text" name="pc_email_from_name" id="pc_email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_from_address"><?php _e('From Email', 'personalized-cards'); ?></label></th>
                    <td><input type="email" name="pc_email_from_address" id="pc_email_from_address" value="<?php echo esc_attr($email_from_address); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_subject"><?php _e('Subject', 'personalized-cards'); ?></label></th>
                    <td><input type="text" name="pc_email_subject" id="pc_email_subject" value="<?php echo esc_attr($email_subject); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="pc_email_message"><?php _e('Message', 'personalized-cards'); ?></label></th>
                    <td>
                        <textarea name="pc_email_message" id="pc_email_message" rows="4" class="large-text"><?php echo esc_textarea($email_message); ?></textarea>
                        <p class="description"><?php _e('Use {name} for member name, {site_name} for site name.', 'personalized-cards'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php _e('Digital Wallet', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('Apple Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_apple_wallet" value="1" <?php checked($enable_apple_wallet, '1'); ?>>
                            <?php _e('Enable Apple Wallet', 'personalized-cards'); ?>
                        </label>
                        <p class="description"><?php _e('Requires Apple Developer certificates in /certificates/.', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Google Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_google_wallet" value="1" <?php checked($enable_google_wallet, '1'); ?>>
                            <?php _e('Enable Google Wallet', 'personalized-cards'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_google_wallet_issuer_id"><?php _e('Google Wallet Issuer ID', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_google_wallet_issuer_id" id="pc_google_wallet_issuer_id" value="<?php echo esc_attr($google_wallet_issuer); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Settings', 'personalized-cards'), 'primary', 'pc_save_settings'); ?>
        </form>
    </div>

    <script>
    jQuery(function($) {
        var $img = $('#pc-template-preview-img');
        var $dot = $('#pc-crosshair');
        var $out = $('#pc-click-coords');

        $img.on('click', function(e) {
            var offset    = $img.offset();
            var clickX    = e.pageX - offset.left;
            var clickY    = e.pageY - offset.top;
            var scaleX    = this.naturalWidth  / $img.width();
            var scaleY    = this.naturalHeight / $img.height();
            var realX     = Math.round(clickX * scaleX);
            var realY     = Math.round(clickY * scaleY);

            $dot.css({ left: clickX, top: clickY, display: 'block' });
            $out.text('Coordinates: X = ' + realX + ', Y = ' + realY);
        });
    });
    </script>
    <?php
}

// ── AJAX: Admin creates card for a user ────────────────────────────────────────
add_action('wp_ajax_pc_admin_create_card', 'pc_ajax_admin_create_card');
function pc_ajax_admin_create_card() {
    check_ajax_referer('pc_admin_create_card', 'pc_admin_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'personalized-cards')));
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Please select a member.', 'personalized-cards')));
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('User not found.', 'personalized-cards')));
    }

    $default_template = basename(get_option('pc_default_template', ''));
    if (!$default_template) {
        wp_send_json_error(array('message' => __('No default template set. Configure it in Settings.', 'personalized-cards')));
    }

    $template_path = PC_PLUGIN_DIR . 'templates/cards/' . $default_template;
    // Defense-in-depth path check
    $templates_root = realpath(PC_PLUGIN_DIR . 'templates/cards/');
    $resolved       = realpath($template_path);
    if (!$resolved || !$templates_root || strpos($resolved, $templates_root) !== 0 || !file_exists($template_path)) {
        wp_send_json_error(array('message' => __('Template file not found.', 'personalized-cards')));
    }

    $card_name    = sanitize_text_field($_POST['card_name'] ?? '');
    $card_message = sanitize_text_field($_POST['card_message'] ?? '');
    $send_email   = !empty($_POST['send_email']);

    if (!$card_name) {
        $card_name = $user->display_name;
    }

    $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
    $card_data   = array(
        'name'    => $card_name,
        'message' => $card_message,
        'date'    => $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : '',
    );

    $card_id = PC_Database::save_card($user_id, $default_template, $card_data, 'standard');

    if (!$card_id) {
        wp_send_json_error(array('message' => __('Failed to save card record.', 'personalized-cards')));
    }

    $upload_dir      = wp_upload_dir();
    $output_filename = 'card_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
    $output_path     = $upload_dir['basedir'] . '/personalized-cards/' . $output_filename;

    // Ensure dir exists
    wp_mkdir_p($upload_dir['basedir'] . '/personalized-cards/');

    $result = PC_Card_Creator::create_personalized_card($template_path, $card_data, $output_path);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $image_url = $upload_dir['baseurl'] . '/personalized-cards/' . $output_filename;
    PC_Database::update_card_image($card_id, $image_url);

    $msg = sprintf(__('Card created for %s.', 'personalized-cards'), $user->display_name);

    if ($send_email) {
        $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $output_path);
        $msg .= $sent
            ? ' ' . __('Email sent.', 'personalized-cards')
            : ' ' . __('Card created but email failed.', 'personalized-cards');
    }

    wp_send_json_success(array('message' => $msg));
}

// ── AJAX: Send card email (individual) ────────────────────────────────────────
add_action('wp_ajax_pc_admin_send_card_email', 'pc_ajax_admin_send_card_email');
function pc_ajax_admin_send_card_email() {
    check_ajax_referer('pc_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'personalized-cards')));
    }

    global $wpdb;
    $card_id = intval($_POST['card_id'] ?? 0);
    $table   = $wpdb->prefix . 'personalized_cards';
    $card    = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $card_id));

    if (!$card) {
        wp_send_json_error(array('message' => __('Card not found.', 'personalized-cards')));
    }

    $user = get_userdata($card->user_id);
    if (!$user) {
        wp_send_json_error(array('message' => __('User not found.', 'personalized-cards')));
    }

    if (!$card->card_image) {
        wp_send_json_error(array('message' => __('Card has no image.', 'personalized-cards')));
    }

    $upload_dir = wp_upload_dir();
    $file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);

    if (!file_exists($file_path)) {
        wp_send_json_error(array('message' => __('Card image file not found on disk.', 'personalized-cards')));
    }

    $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path);

    if ($sent) {
        wp_send_json_success(array('message' => __('Email sent.', 'personalized-cards')));
    } else {
        wp_send_json_error(array('message' => __('Email failed to send.', 'personalized-cards')));
    }
}

// ── Helper: bulk create + email ────────────────────────────────────────────────
function pc_admin_bulk_create_and_email() {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';

    $default_template = basename(get_option('pc_default_template', ''));
    $template_path    = $default_template ? PC_PLUGIN_DIR . 'templates/cards/' . $default_template : '';
    $upload_dir       = wp_upload_dir();
    wp_mkdir_p($upload_dir['basedir'] . '/personalized-cards/');

    $active_user_ids = $wpdb->get_col(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'"
    );

    $created = 0;
    $emailed = 0;
    $skipped = 0;

    foreach ($active_user_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user) continue;

        // Check if member already has a card
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));

        if ($existing && $existing->card_image) {
            // Already has a card — just email it
            $file_path = str_replace(
                $upload_dir['baseurl'], $upload_dir['basedir'], $existing->card_image
            );
            if (file_exists($file_path)) {
                $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path);
                if ($sent) $emailed++;
            }
            $skipped++;
            continue;
        }

        // No card yet — create one (requires a template)
        if (!$template_path || !file_exists($template_path)) continue;

        $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
        $card_data   = array(
            'name'    => $user->display_name,
            'message' => '',
            'date'    => $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : '',
        );

        $card_id = PC_Database::save_card($user_id, $default_template, $card_data, 'standard');
        if (!$card_id) continue;

        $output_filename = 'card_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
        $output_path     = $upload_dir['basedir'] . '/personalized-cards/' . $output_filename;

        $result = PC_Card_Creator::create_personalized_card($template_path, $card_data, $output_path);
        if (is_wp_error($result)) continue;

        $image_url = $upload_dir['baseurl'] . '/personalized-cards/' . $output_filename;
        PC_Database::update_card_image($card_id, $image_url);
        $created++;

        $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $output_path);
        if ($sent) $emailed++;
    }

    return compact('created', 'emailed', 'skipped');
}

// ── Helper: bulk email active members ─────────────────────────────────────────
function pc_admin_bulk_email_active_members() {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';

    $active_user_ids = $wpdb->get_col(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'"
    );

    if (empty($active_user_ids)) {
        return 0;
    }

    $upload_dir = wp_upload_dir();
    $count      = 0;

    foreach ($active_user_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user) continue;

        // Get most recent card
        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));

        if (!$card || !$card->card_image) continue;

        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);
        if (!file_exists($file_path)) continue;

        $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path);
        if ($sent) $count++;
    }

    return $count;
}

// ── AJAX: Delete card ──────────────────────────────────────────────────────────
add_action('wp_ajax_pc_delete_card', 'pc_ajax_delete_card');
function pc_ajax_delete_card() {
    check_ajax_referer('pc_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'personalized-cards')));
    }

    global $wpdb;
    $card_id = intval($_POST['card_id'] ?? 0);
    $table   = $wpdb->prefix . 'personalized_cards';

    $card = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $card_id));

    if ($card && $card->card_image) {
        $upload_dir = wp_upload_dir();
        $file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    $deleted = $wpdb->delete($table, array('id' => $card_id), array('%d'));

    if ($deleted) {
        wp_send_json_success(array('message' => __('Card deleted.', 'personalized-cards')));
    } else {
        wp_send_json_error(array('message' => __('Delete failed.', 'personalized-cards')));
    }
}
