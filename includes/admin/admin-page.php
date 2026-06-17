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
    $um    = $wpdb->usermeta;
    $today = current_time('Y-m-d');
    $in30  = date('Y-m-d', strtotime($today . ' +30 days'));

    // ── KPIs ──────────────────────────────────────────────────────────────
    $total_cards     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $cards_month     = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE created_at >= %s", current_time('Y-m-01') . ' 00:00:00'));
    $active_members  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $um WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'");
    $expired_members = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $um WHERE meta_key = 'pc_subscription_expiry' AND meta_value <> '' AND meta_value < %s", $today));
    $expiring_soon   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $um WHERE meta_key = 'pc_subscription_expiry' AND meta_value >= %s AND meta_value <= %s", $today, $in30));

    // Apple Wallet device registrations (table may be absent on older installs).
    $reg_table      = $wpdb->prefix . 'pc_wallet_registrations';
    $wallet_devices = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reg_table)) === $reg_table)
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $reg_table")
        : 0;

    // ── Cards issued per month (last 6) ───────────────────────────────────
    $monthly = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(created_at, '%%Y-%%m') ym, COUNT(*) c FROM $table WHERE created_at >= %s GROUP BY ym",
        date('Y-m-01', strtotime('-5 months'))
    ));
    $buckets = array();
    for ($i = 5; $i >= 0; $i--) { $buckets[date('Y-m', strtotime("-$i months"))] = 0; }
    foreach ($monthly as $r) { if (isset($buckets[$r->ym])) $buckets[$r->ym] = (int) $r->c; }
    $max_month = max(1, max($buckets));

    // ── Status breakdown for the stacked bar ──────────────────────────────
    $active_only  = max(0, $active_members - $expiring_soon);
    $break_total  = max(1, $active_only + $expiring_soon + $expired_members);

    $recent = class_exists('PC_Activity_Log') ? PC_Activity_Log::get_recent(8) : array();
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards', 'personalized-cards'); ?></h1>

        <!-- KPIs -->
        <div class="pc-admin-stats">
            <div class="pc-stat-box">
                <h3><?php echo number_format($active_members); ?></h3>
                <p><?php _e('Active Members', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($expiring_soon); ?></h3>
                <p><?php _e('Expiring in 30 days', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($expired_members); ?></h3>
                <p><?php _e('Expired', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($total_cards); ?></h3>
                <p><?php _e('Total Cards', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($cards_month); ?></h3>
                <p><?php _e('Cards This Month', 'personalized-cards'); ?></p>
            </div>
            <div class="pc-stat-box">
                <h3><?php echo number_format($wallet_devices); ?></h3>
                <p><?php _e('Wallet Devices', 'personalized-cards'); ?></p>
            </div>
        </div>

        <!-- Analytics -->
        <div class="pc-analytics">
            <div class="pc-analytics-card">
                <h2><?php _e('Cards issued — last 6 months', 'personalized-cards'); ?></h2>
                <div class="pc-bars">
                    <?php foreach ($buckets as $ym => $count): ?>
                        <div class="pc-bar-col" title="<?php echo esc_attr(sprintf(__('%1$s: %2$d cards', 'personalized-cards'), $ym, $count)); ?>">
                            <span class="pc-bar-count"><?php echo number_format($count); ?></span>
                            <span class="pc-bar" style="height:<?php echo (int) round(($count / $max_month) * 100); ?>%"></span>
                            <span class="pc-bar-label"><?php echo esc_html(date_i18n('M', strtotime($ym . '-01'))); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pc-analytics-card">
                <h2><?php _e('Membership status', 'personalized-cards'); ?></h2>
                <?php if ($break_total <= 1 && !$active_members && !$expired_members): ?>
                    <p class="description"><?php _e('No membership data yet.', 'personalized-cards'); ?></p>
                <?php else: ?>
                    <div class="pc-stacked">
                        <span class="pc-seg pc-seg-active"  style="width:<?php echo round($active_only / $break_total * 100); ?>%"></span>
                        <span class="pc-seg pc-seg-soon"    style="width:<?php echo round($expiring_soon / $break_total * 100); ?>%"></span>
                        <span class="pc-seg pc-seg-expired" style="width:<?php echo round($expired_members / $break_total * 100); ?>%"></span>
                    </div>
                    <ul class="pc-legend">
                        <li><span class="pc-dot pc-seg-active"></span><?php printf(__('Active: %d', 'personalized-cards'), $active_only); ?></li>
                        <li><span class="pc-dot pc-seg-soon"></span><?php printf(__('Expiring soon: %d', 'personalized-cards'), $expiring_soon); ?></li>
                        <li><span class="pc-dot pc-seg-expired"></span><?php printf(__('Expired: %d', 'personalized-cards'), $expired_members); ?></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="pc-admin-section">
            <h2><?php _e('Recent Activity', 'personalized-cards'); ?></h2>
            <?php if (empty($recent)): ?>
                <p class="description"><?php _e('No activity logged yet.', 'personalized-cards'); ?></p>
            <?php else: ?>
                <table class="pc-activity-table widefat striped">
                    <tbody>
                    <?php foreach ($recent as $row):
                        $who = $row->user_id ? get_userdata($row->user_id) : null; ?>
                        <tr>
                            <td><code><?php echo esc_html($row->action); ?></code></td>
                            <td><?php echo esc_html($row->note); ?></td>
                            <td><?php echo $who ? esc_html($who->display_name) : '—'; ?></td>
                            <td class="pc-activity-time"><?php echo esc_html(human_time_diff(strtotime($row->created_at), current_time('timestamp'))) . ' ' . __('ago', 'personalized-cards'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin:10px 0 0;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=personalized-cards-log')); ?>"><?php _e('View all activity →', 'personalized-cards'); ?></a>
                </p>
            <?php endif; ?>
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
                        <th><label for="pc-admin-card-father"><?php _e('Father Name', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="father_name" id="pc-admin-card-father" class="regular-text"
                                   placeholder="<?php esc_attr_e('Leave blank to use user meta pc_father_name', 'personalized-cards'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc-admin-card-sport"><?php _e('Sport', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="sport" id="pc-admin-card-sport" class="regular-text"
                                   placeholder="<?php esc_attr_e('Leave blank to use user meta pc_sport', 'personalized-cards'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc-admin-card-memberid"><?php _e('Member ID', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="member_id" id="pc-admin-card-memberid" class="regular-text"
                                   placeholder="<?php esc_attr_e('Leave blank to use WordPress user ID', 'personalized-cards'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc-admin-card-image"><?php _e('Member Photo URL', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="text" name="member_image" id="pc-admin-card-image" class="regular-text"
                                   placeholder="<?php esc_attr_e('Media library URL or leave blank to use user meta pc_member_image', 'personalized-cards'); ?>">
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
            <p>
                <button type="button" id="pc-bulk-create-email" class="button button-primary"
                        data-mode="create_email"
                        data-confirm="<?php esc_attr_e('Create a card for every active member who doesn\'t have one yet, then email all of them?', 'personalized-cards'); ?>">
                    <?php _e('Create & Email All Active Members', 'personalized-cards'); ?>
                </button>
                <span style="margin:0 12px;color:#aaa;">|</span>
                <button type="button" id="pc-bulk-email" class="button button-secondary"
                        data-mode="email_only"
                        data-confirm="<?php esc_attr_e('Re-send card emails to all active members who already have a card?', 'personalized-cards'); ?>">
                    <?php _e('Re-email Existing Cards', 'personalized-cards'); ?>
                </button>
            </p>
            <p class="description">
                <?php _e('<strong>Create &amp; Email</strong> — generates a card for anyone missing one, then emails everyone.<br><strong>Re-email</strong> — only sends emails; does not create new cards.', 'personalized-cards'); ?>
            </p>

            <div id="pc-bulk-progress" style="display:none;margin-top:18px;padding:16px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;max-width:640px;">
                <div id="pc-bulk-progress-title" style="font-weight:600;margin-bottom:8px;"></div>
                <div style="background:#e5e5e5;border-radius:3px;overflow:hidden;height:18px;">
                    <div id="pc-bulk-progress-bar" style="background:#0073aa;height:100%;width:0;transition:width 0.2s;"></div>
                </div>
                <div id="pc-bulk-progress-status" style="margin-top:8px;font-family:monospace;font-size:13px;color:#444;"></div>
                <div id="pc-bulk-progress-counts" style="margin-top:6px;font-size:13px;color:#666;"></div>
                <div id="pc-bulk-progress-errors" style="margin-top:8px;font-size:12px;color:#a00;max-height:120px;overflow:auto;"></div>
            </div>
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

        // ── Bulk: AJAX-driven, one user per request, with live progress ────────
        var bulkLabels = {
            create_email: '<?php echo esc_js(__('Creating & emailing cards', 'personalized-cards')); ?>',
            email_only:   '<?php echo esc_js(__('Re-emailing cards', 'personalized-cards')); ?>',
            startingFor:  '<?php echo esc_js(__('Starting…', 'personalized-cards')); ?>',
            sending:      '<?php echo esc_js(__('Sending to', 'personalized-cards')); ?>',
            sent:         '<?php echo esc_js(__('Sent', 'personalized-cards')); ?>',
            done:         '<?php echo esc_js(__('Done.', 'personalized-cards')); ?>',
            noUsers:      '<?php echo esc_js(__('No active members to process.', 'personalized-cards')); ?>',
            failed:       '<?php echo esc_js(__('Request failed', 'personalized-cards')); ?>'
        };

        function setBar(pct) {
            $('#pc-bulk-progress-bar').css('width', pct + '%');
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function runBulk(mode) {
            var $allBtns = $('#pc-bulk-create-email, #pc-bulk-email');
            var $progress = $('#pc-bulk-progress');
            var $title    = $('#pc-bulk-progress-title');
            var $status   = $('#pc-bulk-progress-status');
            var $counts   = $('#pc-bulk-progress-counts');
            var $errors   = $('#pc-bulk-progress-errors');

            $allBtns.prop('disabled', true);
            $progress.show();
            $title.text(bulkLabels[mode] || '');
            $status.text(bulkLabels.startingFor);
            $counts.text('');
            $errors.empty();
            setBar(0);

            $.post(pcAdminAjax.ajaxurl, {
                action: 'pc_admin_bulk_init',
                nonce:  pcAdminAjax.nonce,
                mode:   mode
            }).done(function(res) {
                if (!res || !res.success) {
                    $status.text((res && res.data && res.data.message) || bulkLabels.failed);
                    $allBtns.prop('disabled', false);
                    return;
                }

                var ids   = res.data.user_ids || [];
                var total = res.data.total || 0;
                if (total === 0) {
                    $status.text(bulkLabels.noUsers);
                    setBar(100);
                    $allBtns.prop('disabled', false);
                    return;
                }

                var i = 0;
                var stats = { created: 0, emailed: 0, skipped: 0, errors: 0 };

                function renderCounts() {
                    var parts = [];
                    if (mode === 'create_email') {
                        parts.push('<?php echo esc_js(__('Created', 'personalized-cards')); ?>: ' + stats.created);
                    }
                    parts.push('<?php echo esc_js(__('Emailed', 'personalized-cards')); ?>: ' + stats.emailed);
                    if (mode === 'create_email') {
                        parts.push('<?php echo esc_js(__('Skipped (already had card)', 'personalized-cards')); ?>: ' + stats.skipped);
                    }
                    parts.push('<?php echo esc_js(__('Errors', 'personalized-cards')); ?>: ' + stats.errors);
                    $counts.text(parts.join('  ·  '));
                }

                function step() {
                    if (i >= total) {
                        $status.text(bulkLabels.done + '  ' + bulkLabels.sent + ' ' + stats.emailed + ' / ' + total);
                        setBar(100);
                        renderCounts();
                        $allBtns.prop('disabled', false);
                        return;
                    }
                    var userId = ids[i];
                    $status.text(bulkLabels.sending + '  ' + (i + 1) + ' / ' + total + '…');

                    $.post(pcAdminAjax.ajaxurl, {
                        action:  'pc_admin_bulk_step',
                        nonce:   pcAdminAjax.nonce,
                        mode:    mode,
                        user_id: userId
                    }).done(function(r) {
                        if (r && r.success && r.data) {
                            if (r.data.created) stats.created++;
                            if (r.data.emailed) stats.emailed++;
                            if (r.data.skipped) stats.skipped++;
                            if (r.data.error)   {
                                stats.errors++;
                                $errors.append('<div>• ' + escapeHtml(r.data.name || ('user #' + userId)) + ': ' + escapeHtml(r.data.error) + '</div>');
                            }
                        } else {
                            stats.errors++;
                            var msg = (r && r.data && r.data.message) || bulkLabels.failed;
                            $errors.append('<div>• user #' + userId + ': ' + escapeHtml(msg) + '</div>');
                        }
                    }).fail(function(xhr) {
                        stats.errors++;
                        $errors.append('<div>• user #' + userId + ': ' + bulkLabels.failed + ' (' + (xhr.status || '?') + ')</div>');
                    }).always(function() {
                        i++;
                        setBar(Math.round((i / total) * 100));
                        renderCounts();
                        // Yield to the browser briefly so the UI repaints between requests.
                        setTimeout(step, 50);
                    });
                }

                renderCounts();
                step();
            }).fail(function(xhr) {
                $status.text(bulkLabels.failed + ' (' + (xhr.status || '?') + ')');
                $allBtns.prop('disabled', false);
            });
        }

        $('#pc-bulk-create-email, #pc-bulk-email').on('click', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;
            var msg = $btn.data('confirm');
            if (msg && !window.confirm(msg)) return;
            runBulk($btn.data('mode'));
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
    echo '<th>' . __('Front', 'personalized-cards') . '</th>';
    echo '<th>' . __('Back', 'personalized-cards') . '</th>';
    echo '<th>' . __('Actions', 'personalized-cards') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($cards as $card) {
        $user     = get_userdata($card->user_id);
        $card_data = json_decode($card->card_data, true) ?: array();
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
        if (!empty($card->card_back_image)) {
            echo '<a href="' . esc_url($card->card_back_image) . '" target="_blank">';
            echo '<img src="' . esc_url($card->card_back_image) . '" style="max-width:80px;height:auto;border:1px solid #ddd;">';
            echo '</a>';
        } else {
            echo '<span style="color:#aaa;">—</span>';
        }
        echo '</td>';
        echo '<td>';
        if ($card->card_image) {
            echo '<a href="' . esc_url($card->card_image) . '" download class="button button-small">' . __('Front', 'personalized-cards') . '</a> ';
        }
        if (!empty($card->card_back_image)) {
            echo '<a href="' . esc_url($card->card_back_image) . '" download class="button button-small">' . __('Back', 'personalized-cards') . '</a> ';
        }
        if ($user) {
            echo '<button class="button button-small pc-send-card-email" data-card-id="' . absint($card->id) . '">' . __('Email', 'personalized-cards') . '</button> ';
        }
        // Edit button — always shown
        echo '<button class="button button-small pc-edit-card"'
            . ' data-card-id="' . absint($card->id) . '"'
            . ' data-name="' . esc_attr($card_data['name'] ?? '') . '"'
            . ' data-father="' . esc_attr($card_data['father_name'] ?? '') . '"'
            . ' data-sport="' . esc_attr($card_data['sport'] ?? '') . '"'
            . ' data-member-id="' . esc_attr($card_data['member_id'] ?? '') . '"'
            . ' data-image="' . esc_attr($card_data['image'] ?? '') . '"'
            . ' data-date="' . esc_attr($card_data['date'] ?? '') . '"'
            . ' data-message="' . esc_attr($card_data['message'] ?? '') . '"'
            . '>' . __('Edit', 'personalized-cards') . '</button> ';
        if ($show_all_actions) {
            echo '<button class="button button-small pc-delete-card" data-card-id="' . absint($card->id) . '" style="color:red;">' . __('Delete', 'personalized-cards') . '</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Edit modal
    ?>
    <div id="pc-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:24px;border-radius:4px;width:480px;max-width:95vw;max-height:90vh;overflow-y:auto;">
            <h3 style="margin-top:0;"><?php _e('Edit Card', 'personalized-cards'); ?></h3>
            <input type="hidden" id="pc-edit-card-id">
            <table class="form-table" style="margin:0;">
                <tr><th><label><?php _e('Name on Card', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-name" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Father Name', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-father" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Sport', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-sport" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Member ID', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-member-id" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Expiry Date', 'personalized-cards'); ?></label></th>
                    <td><input type="date" id="pc-edit-date" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Photo URL', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-image" class="regular-text"></td></tr>
                <tr><th><label><?php _e('Message', 'personalized-cards'); ?></label></th>
                    <td><input type="text" id="pc-edit-message" class="regular-text" maxlength="100"></td></tr>
                <tr><th><?php _e('Regenerate', 'personalized-cards'); ?></th>
                    <td><label><input type="checkbox" id="pc-edit-regen" checked> <?php _e('Regenerate card image after saving', 'personalized-cards'); ?></label></td></tr>
            </table>
            <p style="margin-top:16px;">
                <button class="button button-primary" id="pc-edit-save"><?php _e('Save', 'personalized-cards'); ?></button>
                &nbsp;
                <button class="button" id="pc-edit-cancel"><?php _e('Cancel', 'personalized-cards'); ?></button>
                <span id="pc-edit-result" style="margin-left:12px;"></span>
            </p>
        </div>
    </div>

    <script>
    jQuery(function($) {
        // Individual email
        $(document).on('click', '.pc-send-card-email', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('<?php echo esc_js(__('Sending…', 'personalized-cards')); ?>');
            $.post(pcAdminAjax.ajaxurl, {
                action: 'pc_admin_send_card_email',
                card_id: $btn.data('card-id'),
                nonce: pcAdminAjax.nonce
            }, function(res) {
                if (res.success) {
                    $btn.text('<?php echo esc_js(__('Sent!', 'personalized-cards')); ?>').css('color', 'green');
                } else {
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Email', 'personalized-cards')); ?>');
                    alert(res.data.message);
                }
            });
        });

        // Delete card
        $(document).on('click', '.pc-delete-card', function() {
            if (!confirm('<?php echo esc_js(__('Delete this card?', 'personalized-cards')); ?>')) return;
            var $btn = $(this);
            var $row = $btn.closest('tr');
            $.post(pcAdminAjax.ajaxurl, {
                action: 'pc_delete_card',
                card_id: $btn.data('card-id'),
                nonce: pcAdminAjax.nonce
            }, function(res) {
                if (res.success) { $row.fadeOut(); } else { alert(res.data.message); }
            });
        });

        // Open edit modal
        $(document).on('click', '.pc-edit-card', function() {
            var $btn = $(this);
            $('#pc-edit-card-id').val($btn.data('card-id'));
            $('#pc-edit-name').val($btn.data('name'));
            $('#pc-edit-father').val($btn.data('father'));
            $('#pc-edit-sport').val($btn.data('sport'));
            $('#pc-edit-member-id').val($btn.data('member-id'));
            $('#pc-edit-date').val($btn.data('date'));
            $('#pc-edit-image').val($btn.data('image'));
            $('#pc-edit-message').val($btn.data('message'));
            $('#pc-edit-result').text('');
            $('#pc-edit-modal').css('display', 'flex');
        });

        $('#pc-edit-cancel').on('click', function() {
            $('#pc-edit-modal').hide();
        });

        $('#pc-edit-save').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true);
            $('#pc-edit-result').text('<?php echo esc_js(__('Saving…', 'personalized-cards')); ?>');
            $.post(pcAdminAjax.ajaxurl, {
                action:     'pc_admin_edit_card',
                nonce:      pcAdminAjax.nonce,
                card_id:    $('#pc-edit-card-id').val(),
                card_name:  $('#pc-edit-name').val(),
                father_name:$('#pc-edit-father').val(),
                sport:      $('#pc-edit-sport').val(),
                member_id:  $('#pc-edit-member-id').val(),
                date:       $('#pc-edit-date').val(),
                image:      $('#pc-edit-image').val(),
                message:    $('#pc-edit-message').val(),
                regenerate: $('#pc-edit-regen').is(':checked') ? 1 : 0
            }, function(res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $('#pc-edit-result').css('color', 'green').text(res.data.message);
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    $('#pc-edit-result').css('color', 'red').text(res.data.message);
                }
            });
        });
    });
    </script>
    <?php
}

// ── Settings export/import (admin-post handlers) ───────────────────────────────
add_action('admin_post_pc_export_settings', 'pc_export_settings');
function pc_export_settings() {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pc_export_settings_action');

    $keys = array(
        'pc_default_template', 'pc_default_back_template', 'pc_font_file',
        'pc_email_from_name', 'pc_email_from_address', 'pc_email_subject', 'pc_email_message',
        'pc_email_welcome_subject', 'pc_email_welcome_message',
        'pc_email_reminder_10_subject', 'pc_email_reminder_10_message',
        'pc_email_expiration_subject', 'pc_email_expiration_message',
        'pc_enable_apple_wallet', 'pc_enable_google_wallet', 'pc_google_wallet_issuer_id',
        'pc_apple_pass_type_id', 'pc_apple_team_id', 'pc_apple_logo', // cert password intentionally excluded from exports
        'pc_enable_wallet_updates', 'pc_wallet_bg_color', 'pc_wallet_label_color', 'pc_wallet_logo_text',

        'pc_field_expiry_format', 'pc_qr_content_template',
        'pc_field_qr_enabled', 'pc_field_qr_x', 'pc_field_qr_y', 'pc_field_qr_size',
        'pc_field_image_enabled', 'pc_field_image_x', 'pc_field_image_y', 'pc_field_image_w', 'pc_field_image_h',
        'pc_field_image_fit', 'pc_field_image_circular', 'pc_field_image_default',
    );
    foreach (array('name', 'expiry', 'father_name', 'sport', 'member_id') as $f) {
        foreach (array('enabled', 'x', 'y', 'size', 'color') as $prop) {
            $keys[] = "pc_field_{$f}_{$prop}";
        }
    }

    $data = array('_version' => PC_VERSION, '_exported' => date('Y-m-d H:i:s'));
    foreach ($keys as $key) {
        $data[$key] = get_option($key);
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="pc-settings-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

add_action('admin_post_pc_import_settings', 'pc_import_settings');
function pc_import_settings() {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pc_import_settings_action');

    $redirect = admin_url('admin.php?page=personalized-cards-settings');

    if (empty($_FILES['pc_settings_file']['name'])) {
        wp_safe_redirect(add_query_arg('pc_msg', 'no_file', $redirect));
        exit;
    }

    $file = $_FILES['pc_settings_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_safe_redirect(add_query_arg('pc_msg', 'upload_error', $redirect));
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    $data    = json_decode($content, true);

    if (!is_array($data) || empty($data['_version'])) {
        wp_safe_redirect(add_query_arg('pc_msg', 'invalid_file', $redirect));
        exit;
    }

    $skip = array('_version', '_exported');
    foreach ($data as $key => $value) {
        if (in_array($key, $skip, true)) continue;
        if (strpos($key, 'pc_') !== 0) continue; // only import pc_ options
        update_option($key, $value);
    }

    wp_safe_redirect(add_query_arg('pc_msg', 'imported', $redirect));
    exit;
}

// ── Settings page ──────────────────────────────────────────────────────────────
function pc_settings_page() {
    $templates_dir = PC_PLUGIN_DIR . 'templates/cards/';
    $fonts_dir     = PC_PLUGIN_DIR . 'assets/fonts/';
    $notices = array();

    // Needed for the default-avatar media picker.
    wp_enqueue_media();

    // Import/export notices
    $msg_map = array(
        'imported'     => array('success', __('Settings imported successfully.', 'personalized-cards')),
        'no_file'      => array('error',   __('Please select a file to import.', 'personalized-cards')),
        'upload_error' => array('error',   __('File upload error. Please try again.', 'personalized-cards')),
        'invalid_file' => array('error',   __('Invalid settings file. Make sure you selected a file exported from this plugin.', 'personalized-cards')),
    );
    if (!empty($_GET['pc_msg']) && isset($msg_map[$_GET['pc_msg']])) {
        $notices[] = $msg_map[$_GET['pc_msg']];
    }

    // Handle Google Wallet service account key upload
    if (isset($_POST['pc_upload_gw_key']) && check_admin_referer('pc_upload_gw_key_action')) {
        if (!empty($_FILES['pc_gw_key_file']['name'])) {
            $file = $_FILES['pc_gw_key_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'json') {
                $notices[] = array('error', __('Only JSON files are allowed.', 'personalized-cards'));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $notices[] = array('error', __('Upload error. Please try again.', 'personalized-cards'));
            } else {
                $content = file_get_contents($file['tmp_name']);
                $parsed  = json_decode($content, true);
                if (empty($parsed['client_email']) || empty($parsed['private_key'])) {
                    $notices[] = array('error', __('Invalid service account JSON. Make sure you downloaded the correct key file.', 'personalized-cards'));
                } else {
                    wp_mkdir_p(PC_PLUGIN_DIR . 'certificates/');
                    $dest = PC_PLUGIN_DIR . 'certificates/google-wallet-key.json';
                    if (file_put_contents($dest, $content) !== false) {
                        $notices[] = array('success', sprintf(__('Service account key uploaded. Issuer: %s', 'personalized-cards'), esc_html($parsed['client_email'])));
                    } else {
                        $notices[] = array('error', __('Failed to save key file. Check directory permissions.', 'personalized-cards'));
                    }
                }
            }
        }
    }

    // Handle Apple Wallet certificate uploads (.p12 cert + WWDR .pem)
    if (isset($_POST['pc_upload_apple_certs']) && check_admin_referer('pc_upload_apple_certs_action')) {
        wp_mkdir_p(PC_PLUGIN_DIR . 'certificates/');

        $apple_uploads = array(
            'pc_apple_p12_file'  => array('ext' => 'p12', 'dest' => 'apple-certificate.p12', 'label' => __('Pass Type ID certificate (.p12)', 'personalized-cards')),
            'pc_apple_wwdr_file' => array('ext' => 'pem', 'dest' => 'apple-wwdr.pem',        'label' => __('Apple WWDR certificate (.pem)', 'personalized-cards')),
        );

        foreach ($apple_uploads as $field => $cfg) {
            if (empty($_FILES[$field]['name'])) {
                continue;
            }
            $file = $_FILES[$field];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== $cfg['ext']) {
                $notices[] = array('error', sprintf(__('%1$s must be a .%2$s file.', 'personalized-cards'), $cfg['label'], $cfg['ext']));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $notices[] = array('error', sprintf(__('Upload error for %s. Please try again.', 'personalized-cards'), $cfg['label']));
            } else {
                $dest = PC_PLUGIN_DIR . 'certificates/' . $cfg['dest'];
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $notices[] = array('success', sprintf(__('%s uploaded.', 'personalized-cards'), $cfg['label']));
                } else {
                    $notices[] = array('error', sprintf(__('Failed to save %s. Check directory permissions.', 'personalized-cards'), $cfg['label']));
                }
            }
        }
    }

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

    // Handle back template upload
    if (isset($_POST['pc_upload_back_template']) && check_admin_referer('pc_upload_back_template_action')) {
        if (!empty($_FILES['pc_back_template_file']['name'])) {
            $file = $_FILES['pc_back_template_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg'), true)) {
                $notices[] = array('error', __('Only JPG/JPEG files are allowed.', 'personalized-cards'));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $notices[] = array('error', __('Upload error. Please try again.', 'personalized-cards'));
            } else {
                wp_mkdir_p($templates_dir);
                $dest_name = sanitize_file_name($file['name']);
                $dest_name = preg_replace('/\.jpeg$/i', '.jpg', $dest_name);
                $dest = $templates_dir . $dest_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Auto-select as back template if none set
                    if (!get_option('pc_default_back_template')) {
                        update_option('pc_default_back_template', $dest_name);
                    }
                    $notices[] = array('success', sprintf(__('Back template "%s" uploaded successfully.', 'personalized-cards'), $dest_name));
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

    // Handle "Test wallet configuration"
    if (isset($_POST['pc_test_wallet']) && check_admin_referer('pc_test_wallet_action')) {
        foreach (PC_Wallet_Handler::test_configuration() as $r) {
            $type = $r['status'] === 'ok' ? 'success' : ($r['status'] === 'error' ? 'error' : 'warning');
            $notices[] = array($type, sprintf('%s — %s', $r['service'], $r['message']));
        }
    }

    // Handle main settings save
    if (isset($_POST['pc_save_settings'])) {
        check_admin_referer('pc_settings_save');

        update_option('pc_default_template',      sanitize_text_field($_POST['pc_default_template']));
        update_option('pc_default_back_template', sanitize_text_field($_POST['pc_default_back_template']));
        update_option('pc_email_from_name',         sanitize_text_field($_POST['pc_email_from_name']));
        update_option('pc_email_from_address',      sanitize_email($_POST['pc_email_from_address']));
        // Get Card email (legacy keys)
        update_option('pc_email_subject',           sanitize_text_field($_POST['pc_email_subject']));
        update_option('pc_email_message',           wp_kses_post($_POST['pc_email_message']));
        // Welcome / 10-day reminder / Expiration templates
        foreach (array('welcome', 'reminder_10', 'expiration') as $tpl) {
            if (isset($_POST["pc_email_{$tpl}_subject"])) {
                update_option("pc_email_{$tpl}_subject", sanitize_text_field(wp_unslash($_POST["pc_email_{$tpl}_subject"])));
            }
            if (isset($_POST["pc_email_{$tpl}_message"])) {
                update_option("pc_email_{$tpl}_message", wp_kses_post(wp_unslash($_POST["pc_email_{$tpl}_message"])));
            }
        }
        update_option('pc_enable_apple_wallet',     isset($_POST['pc_enable_apple_wallet']) ? '1' : '0');
        update_option('pc_enable_google_wallet',    isset($_POST['pc_enable_google_wallet']) ? '1' : '0');
        update_option('pc_google_wallet_issuer_id', sanitize_text_field($_POST['pc_google_wallet_issuer_id']));
        update_option('pc_apple_pass_type_id',      sanitize_text_field($_POST['pc_apple_pass_type_id'] ?? ''));
        update_option('pc_apple_team_id',           sanitize_text_field($_POST['pc_apple_team_id'] ?? ''));
        // Only overwrite the stored password when a new value is entered (the field is
        // rendered blank), so saving other settings never wipes or exposes it.
        if (isset($_POST['pc_apple_cert_password']) && $_POST['pc_apple_cert_password'] !== '') {
            update_option('pc_apple_cert_password', (string) $_POST['pc_apple_cert_password']);
        }
        update_option('pc_apple_logo',              esc_url_raw($_POST['pc_apple_logo'] ?? ''));
        // Auto-update + branding (shared by both wallets)
        update_option('pc_enable_wallet_updates',   isset($_POST['pc_enable_wallet_updates']) ? '1' : '0');
        update_option('pc_wallet_bg_color',         sanitize_hex_color($_POST['pc_wallet_bg_color'] ?? '') ?: '#1a73e8');
        update_option('pc_wallet_label_color',      sanitize_hex_color($_POST['pc_wallet_label_color'] ?? '') ?: '#ffffff');
        update_option('pc_wallet_logo_text',        sanitize_text_field($_POST['pc_wallet_logo_text'] ?? ''));

        // Font & text overlay settings
        update_option('pc_font_file', sanitize_file_name($_POST['pc_font_file'] ?? ''));
        foreach (array('name', 'expiry', 'father_name', 'sport', 'member_id') as $field) {
            update_option("pc_field_{$field}_enabled",   isset($_POST["pc_field_{$field}_enabled"]) ? '1' : '0');
            update_option("pc_field_{$field}_x",         absint($_POST["pc_field_{$field}_x"] ?? 0));
            update_option("pc_field_{$field}_y",         absint($_POST["pc_field_{$field}_y"] ?? 0));
            update_option("pc_field_{$field}_size",      absint($_POST["pc_field_{$field}_size"] ?? 20));
            update_option("pc_field_{$field}_color",     sanitize_hex_color($_POST["pc_field_{$field}_color"] ?? '#000000') ?: '#000000');
        }
        // Image field (uses width/height instead of font size/color)
        update_option('pc_field_image_enabled', isset($_POST['pc_field_image_enabled']) ? '1' : '0');
        update_option('pc_field_image_x',       absint($_POST['pc_field_image_x'] ?? 0));
        update_option('pc_field_image_y',       absint($_POST['pc_field_image_y'] ?? 0));
        update_option('pc_field_image_w',       absint($_POST['pc_field_image_w'] ?? 150));
        update_option('pc_field_image_h',       absint($_POST['pc_field_image_h'] ?? 150));
        // Photo rendering options
        $fit_choice = isset($_POST['pc_field_image_fit']) ? sanitize_text_field($_POST['pc_field_image_fit']) : 'cover';
        if (!in_array($fit_choice, array('cover', 'contain', 'stretch'), true)) $fit_choice = 'cover';
        update_option('pc_field_image_fit',      $fit_choice);
        update_option('pc_field_image_circular', isset($_POST['pc_field_image_circular']) ? '1' : '0');
        update_option('pc_field_image_default',  esc_url_raw($_POST['pc_field_image_default'] ?? ''));
        update_option('pc_field_expiry_format', sanitize_text_field($_POST['pc_field_expiry_format'] ?? 'd/m/Y'));

        // QR code settings
        update_option('pc_field_qr_enabled',    isset($_POST['pc_field_qr_enabled']) ? '1' : '0');
        update_option('pc_field_qr_x',          absint($_POST['pc_field_qr_x'] ?? 0));
        update_option('pc_field_qr_y',          absint($_POST['pc_field_qr_y'] ?? 0));
        update_option('pc_field_qr_size',       absint($_POST['pc_field_qr_size'] ?? 120));
        update_option('pc_qr_content_template', esc_url_raw($_POST['pc_qr_content_template'] ?? ''));

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
    $apple_pass_type_id   = get_option('pc_apple_pass_type_id', '');
    $apple_team_id        = get_option('pc_apple_team_id', '');
    $apple_cert_password  = get_option('pc_apple_cert_password', '');
    $apple_logo           = get_option('pc_apple_logo', '');
    $enable_wallet_updates = get_option('pc_enable_wallet_updates', '0');
    $wallet_bg_color      = get_option('pc_wallet_bg_color', '#1a73e8');
    $wallet_label_color   = get_option('pc_wallet_label_color', '#ffffff');
    $wallet_logo_text     = get_option('pc_wallet_logo_text', '');
    $template_files       = glob($templates_dir . '*.jpg') ?: array();
    $font_files           = glob($fonts_dir . '*.ttf') ?: array();
    $active_font          = get_option('pc_font_file', 'arial.ttf');

    $login_page_url   = get_option('pc_login_page_id')   ? get_permalink(get_option('pc_login_page_id'))   : '';
    $my_card_page_url = get_option('pc_my_card_page_id') ? get_permalink(get_option('pc_my_card_page_id')) : '';

    // Text field defaults
    $fields = array(
        'name'        => array('label' => __('Member Name', 'personalized-cards'), 'default_x' => 100, 'default_y' => 150, 'default_size' => 24, 'default_color' => '#000000', 'sample' => 'John Doe'),
        'father_name' => array('label' => __('Father Name', 'personalized-cards'), 'default_x' => 100, 'default_y' => 185, 'default_size' => 20, 'default_color' => '#000000', 'sample' => 'Michael Doe'),
        'sport'       => array('label' => __('Sport', 'personalized-cards'),       'default_x' => 100, 'default_y' => 260, 'default_size' => 18, 'default_color' => '#000000', 'sample' => 'Football'),
        'member_id'   => array('label' => __('Member ID', 'personalized-cards'),   'default_x' => 100, 'default_y' => 295, 'default_size' => 18, 'default_color' => '#000000', 'sample' => '#00123'),
        'expiry'      => array('label' => __('Expiry Date', 'personalized-cards'), 'default_x' => 100, 'default_y' => 330, 'default_size' => 18, 'default_color' => '#000000', 'sample' => '31/12/2027'),
    );
    $image_cfg = array(
        'enabled' => get_option('pc_field_image_enabled', '0'),
        'x'       => (int) get_option('pc_field_image_x', 400),
        'y'       => (int) get_option('pc_field_image_y', 100),
        'w'       => (int) get_option('pc_field_image_w', 150),
        'h'       => (int) get_option('pc_field_image_h', 150),
    );
    ?>
    <div class="wrap">
        <h1><?php _e('Personalized Cards Settings', 'personalized-cards'); ?></h1>

        <?php foreach ($notices as [$type, $msg]): ?>
            <?php $notice_cls = in_array($type, array('error', 'warning', 'info'), true) ? $type : 'success'; ?>
            <div class="notice notice-<?php echo esc_attr($notice_cls); ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
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

        <!-- ── Upload back template ─────────────────────────── -->
        <div class="pc-admin-section">
            <h2><?php _e('Upload Card Back Template', 'personalized-cards'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_back_template_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_back_template_file"><?php _e('JPG Back Template File', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_back_template_file" id="pc_back_template_file" accept=".jpg,.jpeg">
                            <p class="description"><?php _e('Upload the back side of the card as a JPG. No text is printed on the back.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Back Template', 'personalized-cards'), 'secondary', 'pc_upload_back_template'); ?>
            </form>
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
                    <th><label for="pc_default_back_template"><?php _e('Back Template', 'personalized-cards'); ?></label></th>
                    <td>
                        <?php
                        $default_back_template = get_option('pc_default_back_template', '');
                        if ($template_files): ?>
                            <select name="pc_default_back_template" id="pc_default_back_template">
                                <option value=""><?php _e('— None (no back side) —', 'personalized-cards'); ?></option>
                                <?php foreach ($template_files as $file):
                                    $fname = basename($file); ?>
                                    <option value="<?php echo esc_attr($fname); ?>" <?php selected($default_back_template, $fname); ?>>
                                        <?php echo esc_html($fname); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select a template for the card back. No text fields are printed on the back.', 'personalized-cards'); ?></p>
                        <?php else: ?>
                            <p class="description"><?php _e('No templates yet. Upload one above.', 'personalized-cards'); ?></p>
                            <input type="hidden" name="pc_default_back_template" value="">
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

            <div class="pc-text-layout-editor">
                <h3><?php _e('Text Overlay Positions', 'personalized-cards'); ?></h3>
                <p class="description">
                    <?php _e('X = pixels from left edge, Y = pixels from top edge of the card image. Use the preview below to find the right coordinates.', 'personalized-cards'); ?>
                </p>

                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:20px;">
                    <div id="pc-preview-column" style="<?php echo $default_template ? '' : 'display:none;'; ?>">
                        <strong><?php _e('Template preview', 'personalized-cards'); ?></strong><br>
                        <div id="pc-preview-wrap" style="position:relative;display:inline-block;margin-top:8px;">
                            <img id="pc-template-preview-img"
                                 src="<?php echo $default_template ? esc_url(PC_PLUGIN_URL . 'templates/cards/' . $default_template) : ''; ?>"
                                 style="max-width:500px;height:auto;border:1px solid #ddd;display:block;">
                            <span id="pc-crosshair" style="position:absolute;width:10px;height:10px;background:red;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;display:none;z-index:5;"></span>
                            <?php foreach ($fields as $key => $cfg): ?>
                                <div class="pc-preview-field" data-field="<?php echo esc_attr($key); ?>"
                                     style="position:absolute;pointer-events:none;white-space:nowrap;font-family:sans-serif;line-height:1;"></div>
                            <?php endforeach; ?>
                            <div class="pc-preview-image" data-field="image"
                                 style="position:absolute;pointer-events:none;border:2px dashed #2271b1;background:rgba(34,113,177,.15);display:flex;align-items:center;justify-content:center;color:#2271b1;font-size:11px;font-family:sans-serif;">PHOTO</div>
                        </div>
                        <p class="description" style="max-width:400px;"><?php _e('Click on the image to get pixel coordinates. The image is shown at reduced size — coordinates are scaled to actual pixels.', 'personalized-cards'); ?></p>
                        <p id="pc-click-coords" style="font-family:monospace;font-weight:bold;"></p>
                    </div>
                    <p id="pc-no-template-msg" style="<?php echo $default_template ? 'display:none;' : ''; ?>" class="description">
                        <?php _e('Upload and select a front template above to see the visual preview.', 'personalized-cards'); ?>
                    </p>

                    <div style="flex:1;min-width:300px;">
                        <table class="form-table" style="margin-top:0;">
                            <?php foreach ($fields as $key => $cfg):
                                $enabled = get_option("pc_field_{$key}_enabled", '1');
                                $x       = get_option("pc_field_{$key}_x",       $cfg['default_x']);
                                $y       = get_option("pc_field_{$key}_y",       $cfg['default_y']);
                                $size    = get_option("pc_field_{$key}_size",    $cfg['default_size']);
                                $color   = get_option("pc_field_{$key}_color",   $cfg['default_color']);
                            ?>
                            <tr data-pc-field="<?php echo esc_attr($key); ?>" data-pc-sample="<?php echo esc_attr($cfg['sample']); ?>">
                                <th style="padding-top:16px;">
                                    <label>
                                        <input type="checkbox" class="pc-fld-enabled" name="pc_field_<?php echo $key; ?>_enabled" value="1" <?php checked($enabled, '1'); ?>>
                                        <?php echo esc_html($cfg['label']); ?>
                                    </label>
                                </th>
                                <td>
                                    <label><?php _e('X', 'personalized-cards'); ?>
                                        <input type="number" class="pc-fld-x" name="pc_field_<?php echo $key; ?>_x" value="<?php echo esc_attr($x); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Y', 'personalized-cards'); ?>
                                        <input type="number" class="pc-fld-y" name="pc_field_<?php echo $key; ?>_y" value="<?php echo esc_attr($y); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Size', 'personalized-cards'); ?>
                                        <input type="number" class="pc-fld-size" name="pc_field_<?php echo $key; ?>_size" value="<?php echo esc_attr($size); ?>" min="8" max="120" style="width:60px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Color', 'personalized-cards'); ?>
                                        <input type="color" class="pc-fld-color" name="pc_field_<?php echo $key; ?>_color" value="<?php echo esc_attr($color); ?>">
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr data-pc-field="image">
                                <th style="padding-top:16px;">
                                    <label>
                                        <input type="checkbox" id="pc-img-enabled" name="pc_field_image_enabled" value="1" <?php checked($image_cfg['enabled'], '1'); ?>>
                                        <?php _e('Member Photo', 'personalized-cards'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label><?php _e('X', 'personalized-cards'); ?>
                                        <input type="number" id="pc-img-x" name="pc_field_image_x" value="<?php echo esc_attr($image_cfg['x']); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Y', 'personalized-cards'); ?>
                                        <input type="number" id="pc-img-y" name="pc_field_image_y" value="<?php echo esc_attr($image_cfg['y']); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Width', 'personalized-cards'); ?>
                                        <input type="number" id="pc-img-w" name="pc_field_image_w" value="<?php echo esc_attr($image_cfg['w']); ?>" min="10" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Height', 'personalized-cards'); ?>
                                        <input type="number" id="pc-img-h" name="pc_field_image_h" value="<?php echo esc_attr($image_cfg['h']); ?>" min="10" style="width:70px;">
                                    </label>
                                </td>
                            </tr>
                            <?php
                            $img_fit       = get_option('pc_field_image_fit', 'cover');
                            $img_circular  = get_option('pc_field_image_circular', '0');
                            $img_default   = get_option('pc_field_image_default', '');
                            ?>
                            <tr data-pc-field="image-options">
                                <th><?php _e('Photo Rendering', 'personalized-cards'); ?></th>
                                <td>
                                    <label style="margin-right:18px;">
                                        <?php _e('Fit', 'personalized-cards'); ?>
                                        <select name="pc_field_image_fit">
                                            <option value="cover"   <?php selected($img_fit, 'cover'); ?>><?php _e('Cover (crop to fill, no distortion)', 'personalized-cards'); ?></option>
                                            <option value="contain" <?php selected($img_fit, 'contain'); ?>><?php _e('Contain (fit whole photo, transparent margins)', 'personalized-cards'); ?></option>
                                            <option value="stretch" <?php selected($img_fit, 'stretch'); ?>><?php _e('Stretch (fill, may distort)', 'personalized-cards'); ?></option>
                                        </select>
                                    </label>
                                    <label style="margin-right:18px;">
                                        <input type="checkbox" name="pc_field_image_circular" value="1" <?php checked($img_circular, '1'); ?>>
                                        <?php _e('Circular crop', 'personalized-cards'); ?>
                                    </label>
                                    <p class="description" style="margin-top:8px;">
                                        <?php _e('Cover is recommended for portrait photos. Stretch matches the legacy behavior.', 'personalized-cards'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr data-pc-field="image-default">
                                <th><label for="pc_field_image_default"><?php _e('Default Avatar (fallback)', 'personalized-cards'); ?></label></th>
                                <td>
                                    <input type="text" name="pc_field_image_default" id="pc_field_image_default" value="<?php echo esc_attr($img_default); ?>" class="regular-text" placeholder="https://...">
                                    <button type="button" class="button" id="pc_default_avatar_pick"><?php _e('Choose Image', 'personalized-cards'); ?></button>
                                    <button type="button" class="button" id="pc_default_avatar_clear"><?php _e('Remove', 'personalized-cards'); ?></button>
                                    <div style="margin-top:8px;">
                                        <img id="pc_default_avatar_preview" src="<?php echo esc_url($img_default); ?>"
                                             style="max-width:120px;height:auto;border:1px solid #ddd;<?php echo $img_default ? '' : 'display:none;'; ?>">
                                    </div>
                                    <p class="description"><?php _e('Used when a member has no photo or their photo cannot be loaded.', 'personalized-cards'); ?></p>
                                </td>
                            </tr>
                            <script>
                            jQuery(function($) {
                                if (typeof wp === 'undefined' || !wp.media) return;
                                var frame;
                                $('#pc_default_avatar_pick').on('click', function(e) {
                                    e.preventDefault();
                                    if (frame) { frame.open(); return; }
                                    frame = wp.media({
                                        title: '<?php echo esc_js(__('Select Default Avatar', 'personalized-cards')); ?>',
                                        button: { text: '<?php echo esc_js(__('Use this image', 'personalized-cards')); ?>' },
                                        library: { type: 'image' },
                                        multiple: false
                                    });
                                    frame.on('select', function() {
                                        var a = frame.state().get('selection').first().toJSON();
                                        $('#pc_field_image_default').val(a.url);
                                        $('#pc_default_avatar_preview').attr('src', a.url).show();
                                    });
                                    frame.open();
                                });
                                $('#pc_default_avatar_clear').on('click', function(e) {
                                    e.preventDefault();
                                    $('#pc_field_image_default').val('');
                                    $('#pc_default_avatar_preview').hide().attr('src', '');
                                });
                            });
                            </script>
                            <tr data-pc-field="qr">
                                <th style="padding-top:16px;">
                                    <label>
                                        <input type="checkbox" name="pc_field_qr_enabled" value="1" <?php checked(get_option('pc_field_qr_enabled', '0'), '1'); ?>>
                                        <?php _e('QR Code', 'personalized-cards'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label><?php _e('X', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_qr_x" value="<?php echo esc_attr(get_option('pc_field_qr_x', 400)); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Y', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_qr_y" value="<?php echo esc_attr(get_option('pc_field_qr_y', 250)); ?>" min="0" style="width:70px;">
                                    </label>
                                    &nbsp;
                                    <label><?php _e('Size (px)', 'personalized-cards'); ?>
                                        <input type="number" name="pc_field_qr_size" value="<?php echo esc_attr(get_option('pc_field_qr_size', 120)); ?>" min="40" max="400" style="width:70px;">
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="pc_qr_content_template"><?php _e('QR Content URL', 'personalized-cards'); ?></label></th>
                                <td>
                                    <input type="text" name="pc_qr_content_template" id="pc_qr_content_template"
                                           value="<?php echo esc_attr(get_option('pc_qr_content_template', home_url('/verify/?id={member_id}'))); ?>"
                                           class="large-text">
                                    <p class="description"><?php _e('URL encoded into the QR code. Use <code>{member_id}</code>, <code>{name}</code>, <code>{site_url}</code> as placeholders.', 'personalized-cards'); ?></p>
                                </td>
                            </tr>
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
            </table>

            <?php
            // ── Email templates editor ───────────────────────────────────────
            $email_tabs = array(
                'get_card' => array(
                    'label'        => __('Get Card Email', 'personalized-cards'),
                    'description'  => __('Sent when a member receives their personalized card (front & back attached).', 'personalized-cards'),
                    'subject_key'  => 'pc_email_subject',
                    'message_key'  => 'pc_email_message',
                    'subject_val'  => $email_subject,
                    'message_val'  => $email_message,
                    'tokens'       => '{name}, {site_name}',
                ),
                'welcome' => array(
                    'label'        => __('Welcome Email', 'personalized-cards'),
                    'description'  => __('Sent the first time a member is activated.', 'personalized-cards'),
                    'subject_key'  => 'pc_email_welcome_subject',
                    'message_key'  => 'pc_email_welcome_message',
                    'subject_val'  => PC_Email_Handler::get_template_subject('welcome'),
                    'message_val'  => PC_Email_Handler::get_template_message('welcome'),
                    'tokens'       => '{name}, {site_name}, {expiry_date}, {login_url}, {my_card_url}',
                ),
                'reminder_10' => array(
                    'label'        => __('10-Day Expiration Reminder', 'personalized-cards'),
                    'description'  => __('Sent automatically once, 10 days before a member\'s expiry date.', 'personalized-cards'),
                    'subject_key'  => 'pc_email_reminder_10_subject',
                    'message_key'  => 'pc_email_reminder_10_message',
                    'subject_val'  => PC_Email_Handler::get_template_subject('reminder_10'),
                    'message_val'  => PC_Email_Handler::get_template_message('reminder_10'),
                    'tokens'       => '{name}, {site_name}, {expiry_date}, {days_left}, {login_url}',
                ),
                'expiration' => array(
                    'label'        => __('Expiration Email', 'personalized-cards'),
                    'description'  => __('Sent automatically when a member\'s subscription expires.', 'personalized-cards'),
                    'subject_key'  => 'pc_email_expiration_subject',
                    'message_key'  => 'pc_email_expiration_message',
                    'subject_val'  => PC_Email_Handler::get_template_subject('expiration'),
                    'message_val'  => PC_Email_Handler::get_template_message('expiration'),
                    'tokens'       => '{name}, {site_name}, {expiry_date}, {login_url}',
                ),
            );
            ?>
            <h3 style="margin-top:24px;"><?php _e('Email Templates', 'personalized-cards'); ?></h3>
            <p class="description" style="margin-bottom:12px;">
                <?php _e('Customize the subject and body for each automatic email. Placeholders are listed under each section and are replaced when the email is sent.', 'personalized-cards'); ?>
            </p>

            <div class="pc-email-tabs">
                <ul class="pc-email-tabs-nav" style="display:flex;gap:4px;list-style:none;margin:0 0 -1px;padding:0;border-bottom:1px solid #c3c4c7;">
                    <?php $i = 0; foreach ($email_tabs as $tab_id => $tab): ?>
                        <li>
                            <a href="#pc-email-tab-<?php echo esc_attr($tab_id); ?>"
                               class="pc-email-tab-link<?php echo $i === 0 ? ' active' : ''; ?>"
                               data-tab="pc-email-tab-<?php echo esc_attr($tab_id); ?>"
                               style="display:inline-block;padding:8px 14px;border:1px solid #c3c4c7;border-bottom:none;background:<?php echo $i === 0 ? '#fff' : '#f0f0f1'; ?>;text-decoration:none;color:#1d2327;border-radius:4px 4px 0 0;">
                                <?php echo esc_html($tab['label']); ?>
                            </a>
                        </li>
                    <?php $i++; endforeach; ?>
                </ul>
                <?php $i = 0; foreach ($email_tabs as $tab_id => $tab): ?>
                    <div id="pc-email-tab-<?php echo esc_attr($tab_id); ?>"
                         class="pc-email-tab-panel"
                         style="<?php echo $i === 0 ? '' : 'display:none;'; ?>border:1px solid #c3c4c7;border-top:none;background:#fff;padding:16px;">
                        <p style="margin-top:0;color:#555;"><?php echo esc_html($tab['description']); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th><label for="<?php echo esc_attr($tab['subject_key']); ?>"><?php _e('Subject', 'personalized-cards'); ?></label></th>
                                <td>
                                    <input type="text"
                                           name="<?php echo esc_attr($tab['subject_key']); ?>"
                                           id="<?php echo esc_attr($tab['subject_key']); ?>"
                                           value="<?php echo esc_attr($tab['subject_val']); ?>"
                                           class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="<?php echo esc_attr($tab['message_key']); ?>"><?php _e('Message', 'personalized-cards'); ?></label></th>
                                <td>
                                    <textarea name="<?php echo esc_attr($tab['message_key']); ?>"
                                              id="<?php echo esc_attr($tab['message_key']); ?>"
                                              rows="8"
                                              class="large-text code"><?php echo esc_textarea($tab['message_val']); ?></textarea>
                                    <p class="description">
                                        <?php _e('Available placeholders:', 'personalized-cards'); ?>
                                        <code><?php echo esc_html($tab['tokens']); ?></code>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php $i++; endforeach; ?>
            </div>

            <script>
            jQuery(function($) {
                $('.pc-email-tab-link').on('click', function(e) {
                    e.preventDefault();
                    var $link = $(this);
                    var target = $link.data('tab');
                    $('.pc-email-tab-link').removeClass('active').css('background', '#f0f0f1');
                    $link.addClass('active').css('background', '#fff');
                    $('.pc-email-tab-panel').hide();
                    $('#' + target).show();
                });
            });
            </script>

            <h2><?php _e('Digital Wallet', 'personalized-cards'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('Apple Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_apple_wallet" value="1" <?php checked($enable_apple_wallet, '1'); ?>>
                            <?php _e('Enable Apple Wallet (iPhone)', 'personalized-cards'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Steps: 1) In the <a href="https://developer.apple.com/account/resources/identifiers/list/passTypeId" target="_blank">Apple Developer portal</a> create a <strong>Pass Type ID</strong> and generate its certificate. 2) Export that certificate from Keychain as a <strong>.p12</strong> file. 3) Download the <strong>Apple WWDR</strong> certificate. 4) Enter the IDs below and upload both certificates further down.', 'personalized-cards'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_apple_pass_type_id"><?php _e('Pass Type ID', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_apple_pass_type_id" id="pc_apple_pass_type_id" value="<?php echo esc_attr($apple_pass_type_id); ?>" class="regular-text" placeholder="pass.com.yoursite.membercard">
                        <p class="description"><?php _e('The Pass Type identifier you registered (starts with "pass.").', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_apple_team_id"><?php _e('Team ID', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_apple_team_id" id="pc_apple_team_id" value="<?php echo esc_attr($apple_team_id); ?>" class="regular-text" placeholder="A1B2C3D4E5">
                        <p class="description"><?php _e('Your 10-character Apple Developer Team ID (Membership page).', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_apple_cert_password"><?php _e('Certificate Password', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="password" name="pc_apple_cert_password" id="pc_apple_cert_password" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $apple_cert_password ? esc_attr__('•••••••• (saved — leave blank to keep)', 'personalized-cards') : ''; ?>">
                        <p class="description"><?php _e('The password you set when exporting the .p12 certificate. Leave blank to keep the saved password (or if the certificate has none).', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_apple_logo"><?php _e('Wallet Logo / Icon URL', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="url" name="pc_apple_logo" id="pc_apple_logo" value="<?php echo esc_attr($apple_logo); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/wp-content/uploads/...png')); ?>">
                        <p class="description"><?php _e('Square PNG shown on the pass (required by Apple). If blank, the site icon or the card image is used.', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Google Wallet', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_google_wallet" value="1" <?php checked($enable_google_wallet, '1'); ?>>
                            <?php _e('Enable Google Wallet (Android)', 'personalized-cards'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Steps: 1) <a href="https://pay.google.com/business/console" target="_blank">Create a Google Wallet Issuer account</a> → copy your Issuer ID below. 2) In Google Cloud Console create a Service Account, enable the <strong>Google Wallet API</strong>, download the JSON key and upload it below.', 'personalized-cards'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_google_wallet_issuer_id"><?php _e('Issuer ID', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_google_wallet_issuer_id" id="pc_google_wallet_issuer_id" value="<?php echo esc_attr($google_wallet_issuer); ?>" class="regular-text" placeholder="3388000000012345678">
                        <p class="description"><?php _e('Found in the Google Wallet Business Console under your issuer account.', 'personalized-cards'); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><hr><h3 style="margin:0;"><?php _e('Pass Branding', 'personalized-cards'); ?></h3></th></tr>
                <tr>
                    <th><label for="pc_wallet_logo_text"><?php _e('Logo Text', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="text" name="pc_wallet_logo_text" id="pc_wallet_logo_text" value="<?php echo esc_attr($wallet_logo_text); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <p class="description"><?php _e('Shown beside the logo on the pass. Defaults to the site name if blank.', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_wallet_bg_color"><?php _e('Background Color', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="color" name="pc_wallet_bg_color" id="pc_wallet_bg_color" value="<?php echo esc_attr($wallet_bg_color); ?>">
                        <p class="description"><?php _e('Background color of the pass (Apple & Google).', 'personalized-cards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pc_wallet_label_color"><?php _e('Text Color', 'personalized-cards'); ?></label></th>
                    <td>
                        <input type="color" name="pc_wallet_label_color" id="pc_wallet_label_color" value="<?php echo esc_attr($wallet_label_color); ?>">
                        <p class="description"><?php _e('Label/foreground text color (Apple Wallet).', 'personalized-cards'); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><hr><h3 style="margin:0;"><?php _e('Automatic Updates', 'personalized-cards'); ?></h3></th></tr>
                <tr>
                    <th><?php _e('Auto-update passes', 'personalized-cards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="pc_enable_wallet_updates" value="1" <?php checked($enable_wallet_updates, '1'); ?>>
                            <?php _e('Push pass updates when a membership is renewed or expires', 'personalized-cards'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Google passes update over the air automatically. Apple passes require the web service URL below to be publicly reachable over HTTPS, plus a valid Pass Type ID certificate (used to sign the push).', 'personalized-cards'); ?>
                        </p>
                        <p class="description">
                            <strong><?php _e('Apple web service URL:', 'personalized-cards'); ?></strong>
                            <code><?php echo esc_html(PC_Wallet_WebService::base_url()); ?></code>
                            <?php if (strpos(home_url(), 'https://') !== 0): ?>
                                <br><span style="color:#b00;"><?php _e('⚠ Your site is not on HTTPS — Apple auto-update will not work until it is.', 'personalized-cards'); ?></span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php
            $gw_key_exists = file_exists(PC_PLUGIN_DIR . 'certificates/google-wallet-key.json');
            ?>
            <h3><?php _e('Google Wallet Service Account Key', 'personalized-cards'); ?></h3>
            <?php if ($gw_key_exists): ?>
                <p style="color:green;">&#10003; <?php _e('Service account key is uploaded.', 'personalized-cards'); ?></p>
            <?php else: ?>
                <p style="color:#b00;">&#10007; <?php _e('No key uploaded yet.', 'personalized-cards'); ?></p>
            <?php endif; ?>

            <?php submit_button(__('Save Settings', 'personalized-cards'), 'primary', 'pc_save_settings'); ?>
        </form>

        <!-- Google Wallet key upload (separate form for enctype) -->
        <div class="pc-admin-section">
            <h2><?php _e('Upload Google Wallet Service Account Key', 'personalized-cards'); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_gw_key_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_gw_key_file"><?php _e('Service Account JSON', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_gw_key_file" id="pc_gw_key_file" accept=".json">
                            <p class="description"><?php _e('Download from Google Cloud Console → IAM → Service Accounts → Keys → Add Key → JSON.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Key', 'personalized-cards'), 'secondary', 'pc_upload_gw_key'); ?>
            </form>
        </div>

        <!-- Apple Wallet certificate upload (separate form for enctype) -->
        <?php
        $apple_p12_exists  = file_exists(PC_PLUGIN_DIR . 'certificates/apple-certificate.p12');
        $apple_wwdr_exists = file_exists(PC_PLUGIN_DIR . 'certificates/apple-wwdr.pem');
        ?>
        <div class="pc-admin-section">
            <h2><?php _e('Upload Apple Wallet Certificates', 'personalized-cards'); ?></h2>
            <p>
                <?php echo $apple_p12_exists
                    ? '<span style="color:green;">&#10003; ' . esc_html__('Pass Type ID certificate uploaded.', 'personalized-cards') . '</span>'
                    : '<span style="color:#b00;">&#10007; ' . esc_html__('No Pass Type ID certificate uploaded.', 'personalized-cards') . '</span>'; ?><br>
                <?php echo $apple_wwdr_exists
                    ? '<span style="color:green;">&#10003; ' . esc_html__('Apple WWDR certificate uploaded.', 'personalized-cards') . '</span>'
                    : '<span style="color:#b00;">&#10007; ' . esc_html__('No Apple WWDR certificate uploaded.', 'personalized-cards') . '</span>'; ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_apple_certs_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_apple_p12_file"><?php _e('Pass Type ID Certificate (.p12)', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_apple_p12_file" id="pc_apple_p12_file" accept=".p12">
                            <p class="description"><?php _e('Export from Keychain Access: select your Pass Type ID certificate → Export → Personal Information Exchange (.p12). Set the password in the Digital Wallet section above.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pc_apple_wwdr_file"><?php _e('Apple WWDR Certificate (.pem)', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_apple_wwdr_file" id="pc_apple_wwdr_file" accept=".pem">
                            <p class="description"><?php _e('Download "Worldwide Developer Relations" certificate from Apple, then convert to PEM: openssl x509 -inform der -in AppleWWDRCAG4.cer -out apple-wwdr.pem', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Certificates', 'personalized-cards'), 'secondary', 'pc_upload_apple_certs'); ?>
            </form>
        </div>

        <!-- Test wallet configuration -->
        <div class="pc-admin-section">
            <h2><?php _e('Test Wallet Configuration', 'personalized-cards'); ?></h2>
            <p class="description"><?php _e('Runs both wallet generators with sample data and reports success or the exact error (missing cert, wrong password, unsigned JWT, etc.). Results appear as notices at the top of this page. No phone required.', 'personalized-cards'); ?></p>
            <form method="post">
                <?php wp_nonce_field('pc_test_wallet_action'); ?>
                <?php submit_button(__('Run Wallet Test', 'personalized-cards'), 'secondary', 'pc_test_wallet', false); ?>
            </form>
        </div>

        <!-- ── Settings export/import ───────────────────────── -->
        <div class="pc-admin-section">
            <h2><?php _e('Export / Import Settings', 'personalized-cards'); ?></h2>
            <div style="display:flex;gap:40px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin-top:0;"><?php _e('Export', 'personalized-cards'); ?></h3>
                    <p class="description"><?php _e('Download all plugin settings as a JSON file. Does not include uploaded templates, fonts, or certificates.', 'personalized-cards'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('pc_export_settings_action'); ?>
                        <input type="hidden" name="action" value="pc_export_settings">
                        <?php submit_button(__('Download Settings JSON', 'personalized-cards'), 'secondary', '', false); ?>
                    </form>
                </div>
                <div>
                    <h3 style="margin-top:0;"><?php _e('Import', 'personalized-cards'); ?></h3>
                    <p class="description"><?php _e('Restore settings from a previously exported JSON file. Existing settings will be overwritten.', 'personalized-cards'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('pc_import_settings_action'); ?>
                        <input type="hidden" name="action" value="pc_import_settings">
                        <input type="file" name="pc_settings_file" accept=".json" style="margin-bottom:8px;display:block;">
                        <?php submit_button(__('Import Settings', 'personalized-cards'), 'secondary', '', false); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var $img = $('#pc-template-preview-img');
        var $dot = $('#pc-crosshair');
        var $out = $('#pc-click-coords');

        function scale() {
            if (!$img[0] || !$img[0].naturalWidth) return 1;
            return $img.width() / $img[0].naturalWidth;
        }

        function renderPreview() {
            var s = scale();
            $('tr[data-pc-field]').each(function() {
                var $row = $(this);
                var key  = $row.data('pc-field');
                if (key === 'image') return;
                var enabled = $row.find('.pc-fld-enabled').is(':checked');
                var x       = parseInt($row.find('.pc-fld-x').val(), 10)    || 0;
                var y       = parseInt($row.find('.pc-fld-y').val(), 10)    || 0;
                var sz      = parseInt($row.find('.pc-fld-size').val(), 10) || 20;
                var color   = $row.find('.pc-fld-color').val() || '#000';
                var sample  = $row.data('pc-sample') || key;
                var $el = $('.pc-preview-field[data-field="' + key + '"]');
                if (!enabled) { $el.hide(); return; }
                // GD imagettftext Y is the text baseline. Approximate by offsetting up by font size.
                $el.show().css({
                    left: (x * s) + 'px',
                    top:  ((y - sz) * s) + 'px',
                    fontSize: (sz * s) + 'px',
                    color: color
                }).text(sample);
            });

            var imgEnabled = $('#pc-img-enabled').is(':checked');
            var ix = parseInt($('#pc-img-x').val(), 10) || 0;
            var iy = parseInt($('#pc-img-y').val(), 10) || 0;
            var iw = parseInt($('#pc-img-w').val(), 10) || 0;
            var ih = parseInt($('#pc-img-h').val(), 10) || 0;
            var $pi = $('.pc-preview-image');
            if (!imgEnabled) { $pi.hide(); }
            else {
                $pi.show().css({
                    left:   (ix * s) + 'px',
                    top:    (iy * s) + 'px',
                    width:  (iw * s) + 'px',
                    height: (ih * s) + 'px'
                });
            }
        }

        $img.on('load', renderPreview);
        if ($img[0] && $img[0].complete) renderPreview();
        $(document).on('input change', '.pc-fld-enabled, .pc-fld-x, .pc-fld-y, .pc-fld-size, .pc-fld-color, #pc-img-enabled, #pc-img-x, #pc-img-y, #pc-img-w, #pc-img-h', renderPreview);
        $(window).on('resize', renderPreview);

        // Update preview image when template dropdown changes
        var templateBaseUrl = '<?php echo esc_js(PC_PLUGIN_URL . 'templates/cards/'); ?>';
        $('#pc_default_template').on('change', function() {
            var fname = $(this).val();
            if (!fname) {
                $('#pc-preview-column').hide();
                $('#pc-no-template-msg').show();
                return;
            }
            $('#pc-no-template-msg').hide();
            $('#pc-preview-column').show();
            $img.attr('src', templateBaseUrl + fname);
        });

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

    $father_name = sanitize_text_field($_POST['father_name'] ?? '');
    if ($father_name === '') $father_name = (string) get_user_meta($user_id, 'pc_father_name', true);

    $sport = sanitize_text_field($_POST['sport'] ?? '');
    if ($sport === '') $sport = (string) get_user_meta($user_id, 'pc_sport', true);

    $member_id = sanitize_text_field($_POST['member_id'] ?? '');
    if ($member_id === '') $member_id = (string) (get_user_meta($user_id, 'pc_member_id', true) ?: $user_id);

    $member_image = esc_url_raw($_POST['member_image'] ?? '');
    if ($member_image === '') $member_image = (string) get_user_meta($user_id, 'pc_member_image', true);

    $card_data = array(
        'name'        => $card_name,
        'father_name' => $father_name,
        'sport'       => $sport,
        'member_id'   => $member_id,
        'image'       => $member_image,
        'message'     => $card_message,
        'date'        => $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : '',
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
    PC_Activity_Log::log('card_created', 'Card created for ' . $user->display_name, $user_id);

    // Generate back side if a back template is configured
    $generated_back_path = '';
    if (get_option('pc_default_back_template', '')) {
        $back_filename = 'card_back_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
        $generated_back_path = $upload_dir['basedir'] . '/personalized-cards/' . $back_filename;
        $back_result   = PC_Card_Creator::create_card_back($generated_back_path);
        if (!is_wp_error($back_result)) {
            PC_Database::update_card_back_image($card_id, $upload_dir['baseurl'] . '/personalized-cards/' . $back_filename);
        } else {
            $generated_back_path = '';
        }
    }

    // Generate Google Wallet link if enabled
    $wallet_url = '';
    if (get_option('pc_enable_google_wallet', '0') === '1') {
        $wallet_result = PC_Wallet_Handler::create_google_wallet_link($card_data, $user_id, $image_url);
        if (!is_wp_error($wallet_result)) {
            $wallet_url = $wallet_result;
        }
    }

    // Generate an Apple Wallet .pkpass to attach, if enabled.
    $pkpass_path = '';
    if (get_option('pc_enable_apple_wallet', '0') === '1') {
        $pk = PC_Wallet_Handler::generate_pkpass_to_file($card_data, $output_path, $user_id);
        if (!is_wp_error($pk)) {
            $pkpass_path = $pk;
        }
    }

    $msg = sprintf(__('Card created for %s.', 'personalized-cards'), $user->display_name);

    if ($send_email) {
        $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $output_path, $wallet_url, $generated_back_path, $pkpass_path);
        $msg .= $sent
            ? ' ' . __('Email sent.', 'personalized-cards')
            : ' ' . __('Card created but email failed.', 'personalized-cards');
    }

    if ($pkpass_path && file_exists($pkpass_path)) {
        @unlink($pkpass_path);
    }

    $response = array('message' => $msg);
    if ($wallet_url) {
        $response['wallet_url'] = $wallet_url;
    }

    wp_send_json_success($response);
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

    $back_file = '';
    if (!empty($card->card_back_image)) {
        $back_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_back_image);
        if (!file_exists($back_file)) $back_file = '';
    }

    $sent = PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path, '', $back_file);

    if ($sent) {
        PC_Activity_Log::log('card_emailed', 'Card emailed to ' . $user->user_email, $user->ID);
        wp_send_json_success(array('message' => __('Email sent.', 'personalized-cards')));
    } else {
        $err = PC_Email_Handler::get_last_error();
        wp_send_json_error(array(
            'message' => $err
                ? sprintf(__('Email failed: %s', 'personalized-cards'), $err)
                : __('Email failed to send.', 'personalized-cards'),
        ));
    }
}

// ── Helper: get active member user IDs ────────────────────────────────────────
function pc_admin_get_active_member_ids() {
    global $wpdb;
    return array_map('intval', $wpdb->get_col(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pc_subscription_active' AND meta_value = '1'"
    ));
}

// ── Per-user: create card if missing, then email ─────────────────────────────
function pc_admin_process_create_and_email_for_user($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';

    $user = get_userdata($user_id);
    if (!$user) {
        return array('created' => false, 'emailed' => false, 'skipped' => false, 'error' => __('User not found.', 'personalized-cards'), 'name' => '');
    }

    $upload_dir = wp_upload_dir();
    wp_mkdir_p($upload_dir['basedir'] . '/personalized-cards/');

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));

    if ($existing && $existing->card_image) {
        $emailed   = false;
        $error     = '';
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $existing->card_image);
        if (file_exists($file_path)) {
            $back_file = '';
            if (!empty($existing->card_back_image)) {
                $b = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $existing->card_back_image);
                if (file_exists($b)) $back_file = $b;
            }
            $emailed = (bool) PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path, '', $back_file);
            if (!$emailed) {
                $smtp = PC_Email_Handler::get_last_error();
                $error = $smtp
                    ? sprintf(__('Email failed: %s', 'personalized-cards'), $smtp)
                    : __('Email failed to send.', 'personalized-cards');
            }
        } else {
            $error = __('Card image file missing on disk.', 'personalized-cards');
        }
        return array('created' => false, 'emailed' => $emailed, 'skipped' => true, 'error' => $error, 'name' => $user->display_name);
    }

    // Need to create a card
    $default_template = basename(get_option('pc_default_template', ''));
    $template_path    = $default_template ? PC_PLUGIN_DIR . 'templates/cards/' . $default_template : '';
    if (!$template_path || !file_exists($template_path)) {
        return array('created' => false, 'emailed' => false, 'skipped' => false, 'error' => __('Default template not configured.', 'personalized-cards'), 'name' => $user->display_name);
    }

    $expiry_date = get_user_meta($user_id, 'pc_subscription_expiry', true);
    $card_data   = array(
        'name'        => $user->display_name,
        'father_name' => (string) get_user_meta($user_id, 'pc_father_name', true),
        'sport'       => (string) get_user_meta($user_id, 'pc_sport', true),
        'member_id'   => (string) (get_user_meta($user_id, 'pc_member_id', true) ?: $user_id),
        'image'       => (string) get_user_meta($user_id, 'pc_member_image', true),
        'message'     => '',
        'date'        => $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : '',
    );

    $card_id = PC_Database::save_card($user_id, $default_template, $card_data, 'standard');
    if (!$card_id) {
        return array('created' => false, 'emailed' => false, 'skipped' => false, 'error' => __('Failed to save card record.', 'personalized-cards'), 'name' => $user->display_name);
    }

    $output_filename = 'card_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
    $output_path     = $upload_dir['basedir'] . '/personalized-cards/' . $output_filename;

    $result = PC_Card_Creator::create_personalized_card($template_path, $card_data, $output_path);
    if (is_wp_error($result)) {
        return array('created' => false, 'emailed' => false, 'skipped' => false, 'error' => $result->get_error_message(), 'name' => $user->display_name);
    }

    $image_url = $upload_dir['baseurl'] . '/personalized-cards/' . $output_filename;
    PC_Database::update_card_image($card_id, $image_url);

    if (get_option('pc_default_back_template', '')) {
        $back_fn   = 'card_back_' . $user_id . '_' . $card_id . '_' . time() . '.jpg';
        $back_path = $upload_dir['basedir'] . '/personalized-cards/' . $back_fn;
        $back_res  = PC_Card_Creator::create_card_back($back_path);
        if (!is_wp_error($back_res)) {
            PC_Database::update_card_back_image($card_id, $upload_dir['baseurl'] . '/personalized-cards/' . $back_fn);
        }
    }

    $wallet_url = '';
    if (get_option('pc_enable_google_wallet', '0') === '1') {
        $w = PC_Wallet_Handler::create_google_wallet_link($card_data, $user_id, $image_url);
        if (!is_wp_error($w)) $wallet_url = $w;
    }

    $pkpass_path = '';
    if (get_option('pc_enable_apple_wallet', '0') === '1') {
        $pk = PC_Wallet_Handler::generate_pkpass_to_file($card_data, $output_path, $user_id);
        if (!is_wp_error($pk)) $pkpass_path = $pk;
    }

    $emailed = (bool) PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $output_path, $wallet_url, '', $pkpass_path);

    if ($pkpass_path && file_exists($pkpass_path)) {
        @unlink($pkpass_path);
    }
    $error = '';
    if (!$emailed) {
        $smtp = PC_Email_Handler::get_last_error();
        $error = $smtp
            ? sprintf(__('Card created, but email failed: %s', 'personalized-cards'), $smtp)
            : __('Card created, but email failed to send.', 'personalized-cards');
    }
    return array(
        'created' => true,
        'emailed' => $emailed,
        'skipped' => false,
        'error'   => $error,
        'name'    => $user->display_name,
    );
}

// ── Per-user: email an already-existing card ─────────────────────────────────
function pc_admin_process_email_for_user($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'personalized_cards';

    $user = get_userdata($user_id);
    if (!$user) {
        return array('emailed' => false, 'error' => __('User not found.', 'personalized-cards'), 'name' => '');
    }

    $card = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));

    if (!$card || !$card->card_image) {
        return array('emailed' => false, 'error' => __('No card to email.', 'personalized-cards'), 'name' => $user->display_name);
    }

    $upload_dir = wp_upload_dir();
    $file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);
    if (!file_exists($file_path)) {
        return array('emailed' => false, 'error' => __('Card image file missing on disk.', 'personalized-cards'), 'name' => $user->display_name);
    }

    $back_file = '';
    if (!empty($card->card_back_image)) {
        $b = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_back_image);
        if (file_exists($b)) $back_file = $b;
    }

    $emailed = (bool) PC_Email_Handler::send_card_email($user->user_email, $user->display_name, $file_path, '', $back_file);
    $error = '';
    if (!$emailed) {
        $smtp = PC_Email_Handler::get_last_error();
        $error = $smtp
            ? sprintf(__('Email failed: %s', 'personalized-cards'), $smtp)
            : __('Email failed to send.', 'personalized-cards');
    }
    return array(
        'emailed' => $emailed,
        'error'   => $error,
        'name'    => $user->display_name,
    );
}

// ── Helper: bulk create + email (kept for non-AJAX callers) ───────────────────
function pc_admin_bulk_create_and_email() {
    $created = 0;
    $emailed = 0;
    $skipped = 0;
    foreach (pc_admin_get_active_member_ids() as $user_id) {
        $r = pc_admin_process_create_and_email_for_user($user_id);
        if (!empty($r['created'])) $created++;
        if (!empty($r['emailed'])) $emailed++;
        if (!empty($r['skipped'])) $skipped++;
    }
    return compact('created', 'emailed', 'skipped');
}

// ── Helper: bulk email active members (kept for non-AJAX callers) ─────────────
function pc_admin_bulk_email_active_members() {
    $count = 0;
    foreach (pc_admin_get_active_member_ids() as $user_id) {
        $r = pc_admin_process_email_for_user($user_id);
        if (!empty($r['emailed'])) $count++;
    }
    return $count;
}

// ── AJAX: Bulk init — return list of user IDs to process ─────────────────────
add_action('wp_ajax_pc_admin_bulk_init', 'pc_ajax_admin_bulk_init');
function pc_ajax_admin_bulk_init() {
    check_ajax_referer('pc_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'personalized-cards')));
    }

    $mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : '';
    if (!in_array($mode, array('create_email', 'email_only'), true)) {
        wp_send_json_error(array('message' => __('Invalid mode.', 'personalized-cards')));
    }

    $user_ids = pc_admin_get_active_member_ids();

    // For email_only, pre-filter to users that actually have a card on disk so
    // the progress total reflects what will really be sent.
    if ($mode === 'email_only' && !empty($user_ids)) {
        global $wpdb;
        $table = $wpdb->prefix . 'personalized_cards';
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $with_card = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $table WHERE user_id IN ($placeholders) AND card_image <> ''",
            $user_ids
        ));
        $with_card = array_map('intval', $with_card);
        $user_ids  = array_values(array_intersect($user_ids, $with_card));
    }

    wp_send_json_success(array(
        'user_ids' => $user_ids,
        'total'    => count($user_ids),
        'mode'     => $mode,
    ));
}

// ── AJAX: Bulk step — process a single user, return per-user result ──────────
add_action('wp_ajax_pc_admin_bulk_step', 'pc_ajax_admin_bulk_step');
function pc_ajax_admin_bulk_step() {
    check_ajax_referer('pc_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'personalized-cards')));
    }

    $mode    = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(array('message' => __('Missing user_id.', 'personalized-cards')));
    }

    if ($mode === 'create_email') {
        $r = pc_admin_process_create_and_email_for_user($user_id);
    } elseif ($mode === 'email_only') {
        $r = pc_admin_process_email_for_user($user_id);
    } else {
        wp_send_json_error(array('message' => __('Invalid mode.', 'personalized-cards')));
    }

    wp_send_json_success($r);
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
        PC_Activity_Log::log('card_deleted', 'Card #' . $card_id . ' deleted', $card->user_id ?? null);
        wp_send_json_success(array('message' => __('Card deleted.', 'personalized-cards')));
    } else {
        wp_send_json_error(array('message' => __('Delete failed.', 'personalized-cards')));
    }
}

// ── AJAX: Edit card ────────────────────────────────────────────────────────────
add_action('wp_ajax_pc_admin_edit_card', 'pc_ajax_admin_edit_card');
function pc_ajax_admin_edit_card() {
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

    $card_data = array_merge(
        json_decode($card->card_data, true) ?: array(),
        array(
            'name'        => sanitize_text_field($_POST['card_name']   ?? ''),
            'father_name' => sanitize_text_field($_POST['father_name'] ?? ''),
            'sport'       => sanitize_text_field($_POST['sport']       ?? ''),
            'member_id'   => sanitize_text_field($_POST['member_id']   ?? ''),
            'date'        => sanitize_text_field($_POST['date']        ?? ''),
            'image'       => esc_url_raw($_POST['image']               ?? ''),
            'message'     => sanitize_text_field($_POST['message']     ?? ''),
        )
    );

    $wpdb->update($table, array('card_data' => json_encode($card_data)), array('id' => $card_id), array('%s'), array('%d'));

    // Regenerate card image if requested
    if (!empty($_POST['regenerate'])) {
        $template_name = basename($card->card_template);
        $template_path = PC_PLUGIN_DIR . 'templates/cards/' . $template_name;

        if (file_exists($template_path)) {
            $upload_dir      = wp_upload_dir();
            $output_filename = 'card_' . $card->user_id . '_' . $card_id . '_' . time() . '.jpg';
            $output_path     = $upload_dir['basedir'] . '/personalized-cards/' . $output_filename;
            wp_mkdir_p($upload_dir['basedir'] . '/personalized-cards/');

            $result = PC_Card_Creator::create_personalized_card($template_path, $card_data, $output_path);
            if (!is_wp_error($result)) {
                // Delete old image file
                if ($card->card_image) {
                    $old = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card->card_image);
                    if (file_exists($old)) @unlink($old);
                }
                $image_url = $upload_dir['baseurl'] . '/personalized-cards/' . $output_filename;
                PC_Database::update_card_image($card_id, $image_url);
            }
        }
    }

    PC_Activity_Log::log('card_edited', 'Card #' . $card_id . ' edited', $card->user_id);
    wp_send_json_success(array('message' => __('Card updated.', 'personalized-cards')));
}
