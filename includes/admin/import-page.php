<?php
add_action('admin_menu', 'pc_add_import_menu');
function pc_add_import_menu() {
    add_submenu_page(
        'personalized-cards',
        __('Import Users', 'personalized-cards'),
        __('Import Users', 'personalized-cards'),
        'manage_options',
        'personalized-cards-import',
        'pc_import_page'
    );
}

function pc_import_page() {
    $results = null;

    if (isset($_POST['pc_import_csv']) && check_admin_referer('pc_import_csv_action')) {
        if (!empty($_FILES['pc_csv_file']['name'])) {
            $file = $_FILES['pc_csv_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('csv', 'txt'), true)) {
                $results = array('error' => __('Only CSV files are allowed.', 'personalized-cards'));
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $results = array('error' => __('Upload error. Please try again.', 'personalized-cards'));
            } else {
                $results = pc_process_csv_import($file['tmp_name']);
            }
        } else {
            $results = array('error' => __('Please select a CSV file.', 'personalized-cards'));
        }
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Import Users from CSV', 'personalized-cards'); ?></h1>

        <?php if ($results && isset($results['error'])): ?>
            <div class="notice notice-error"><p><?php echo esc_html($results['error']); ?></p></div>
        <?php elseif ($results): ?>
            <div class="notice notice-success">
                <p><?php printf(
                    __('Import complete: <strong>%d created</strong>, <strong>%d updated</strong>, <strong>%d skipped</strong>.', 'personalized-cards'),
                    $results['created'], $results['updated'], $results['skipped']
                ); ?></p>
            </div>
            <?php if (!empty($results['rows'])): ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                <thead><tr>
                    <th><?php _e('Row', 'personalized-cards'); ?></th>
                    <th><?php _e('Email', 'personalized-cards'); ?></th>
                    <th><?php _e('Status', 'personalized-cards'); ?></th>
                    <th><?php _e('Note', 'personalized-cards'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($results['rows'] as $row): ?>
                    <tr>
                        <td><?php echo absint($row['row']); ?></td>
                        <td><?php echo esc_html($row['email']); ?></td>
                        <td style="color:<?php echo $row['status'] === 'error' ? 'red' : ($row['status'] === 'created' ? 'green' : '#888'); ?>">
                            <?php echo esc_html($row['status']); ?>
                        </td>
                        <td><?php echo esc_html($row['note']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>

        <div class="pc-admin-section">
            <h2><?php _e('Upload CSV', 'personalized-cards'); ?></h2>
            <p><?php _e('Required column: <code>email</code>. Optional columns:', 'personalized-cards'); ?>
                <code>display_name</code>, <code>first_name</code>, <code>last_name</code>,
                <code>father_name</code>, <code>sport</code>, <code>member_id</code>,
                <code>expiry_date</code> (YYYY-MM-DD), <code>member_image</code> (URL), <code>password</code>
            </p>
            <p><?php _e('If the user already exists (matched by email), their meta fields are updated. If not, a new user is created. The membership is activated for all imported rows that have a valid email.', 'personalized-cards'); ?></p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_import_csv_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pc_csv_file"><?php _e('CSV File', 'personalized-cards'); ?></label></th>
                        <td>
                            <input type="file" name="pc_csv_file" id="pc_csv_file" accept=".csv,.txt">
                            <p class="description"><?php _e('First row must be the header row.', 'personalized-cards'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Activate Membership', 'personalized-cards'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pc_import_activate" value="1" checked>
                                <?php _e('Mark all imported users as active members', 'personalized-cards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Send Welcome Email', 'personalized-cards'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pc_import_notify" value="1">
                                <?php _e('Send WordPress new-user notification email to newly created users', 'personalized-cards'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Import', 'personalized-cards'), 'primary', 'pc_import_csv'); ?>
            </form>
        </div>

        <div class="pc-admin-section">
            <h2><?php _e('Sample CSV', 'personalized-cards'); ?></h2>
            <pre style="background:#f6f7f7;padding:12px;border:1px solid #ddd;display:inline-block;">email,display_name,first_name,last_name,father_name,sport,member_id,expiry_date,member_image,password
john@example.com,John Doe,John,Doe,Michael Doe,Football,M001,2027-12-31,,
jane@example.com,Jane Smith,Jane,Smith,,Tennis,M002,2026-06-30,,secretpass</pre>
            <p>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=pc_download_csv_sample')); ?>" class="button button-secondary">
                    <?php _e('Download Sample CSV', 'personalized-cards'); ?>
                </a>
            </p>
        </div>
    </div>
    <?php
}

function pc_process_csv_import($file_path) {
    $activate  = !empty($_POST['pc_import_activate']);
    $notify    = !empty($_POST['pc_import_notify']);

    $handle = fopen($file_path, 'r');
    if (!$handle) return array('error' => __('Could not read file.', 'personalized-cards'));

    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return array('error' => __('Empty or invalid CSV file.', 'personalized-cards'));
    }
    $header = array_map('trim', array_map('strtolower', $header));

    if (!in_array('email', $header, true)) {
        fclose($handle);
        return array('error' => __('CSV must have an "email" column.', 'personalized-cards'));
    }

    $col = array_flip($header);
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $rows    = array();
    $row_num = 1;

    while (($data = fgetcsv($handle)) !== false) {
        $row_num++;
        $get = function($key) use ($data, $col) {
            return isset($col[$key], $data[$col[$key]]) ? trim($data[$col[$key]]) : '';
        };

        $email = sanitize_email($get('email'));
        if (!$email) {
            $skipped++;
            $rows[] = array('row' => $row_num, 'email' => $get('email'), 'status' => 'skipped', 'note' => 'Invalid email');
            continue;
        }

        $existing_user = get_user_by('email', $email);

        if ($existing_user) {
            $user_id = $existing_user->ID;
            $status  = 'updated';
            $note    = 'User already existed';

            $display_name = $get('display_name');
            $first_name   = $get('first_name');
            $last_name    = $get('last_name');
            $update_args  = array('ID' => $user_id);
            if ($display_name) $update_args['display_name'] = sanitize_text_field($display_name);
            if ($first_name)   $update_args['first_name']   = sanitize_text_field($first_name);
            if ($last_name)    $update_args['last_name']     = sanitize_text_field($last_name);
            if (count($update_args) > 1) wp_update_user($update_args);

            $updated++;
        } else {
            $display_name = $get('display_name') ?: trim($get('first_name') . ' ' . $get('last_name')) ?: $email;
            $password     = $get('password') ?: wp_generate_password(12, false);
            $username     = sanitize_user(strtolower(explode('@', $email)[0]), true);
            // Ensure unique username
            $base = $username;
            $i    = 1;
            while (username_exists($username)) {
                $username = $base . $i++;
            }

            $user_id = wp_insert_user(array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password,
                'display_name' => sanitize_text_field($display_name),
                'first_name'   => sanitize_text_field($get('first_name')),
                'last_name'    => sanitize_text_field($get('last_name')),
                'role'         => 'subscriber',
            ));

            if (is_wp_error($user_id)) {
                $skipped++;
                $rows[] = array('row' => $row_num, 'email' => $email, 'status' => 'error', 'note' => $user_id->get_error_message());
                continue;
            }

            if ($notify) wp_new_user_notification($user_id, null, 'user');

            $status = 'created';
            $note   = 'New user created';
            $created++;
        }

        // Save card meta fields
        $father_name  = sanitize_text_field($get('father_name'));
        $sport        = sanitize_text_field($get('sport'));
        $member_id    = sanitize_text_field($get('member_id'));
        $expiry_date  = sanitize_text_field($get('expiry_date'));
        $member_image = esc_url_raw($get('member_image'));

        if ($father_name)  update_user_meta($user_id, 'pc_father_name',  $father_name);
        if ($sport)        update_user_meta($user_id, 'pc_sport',         $sport);
        if ($member_id)    update_user_meta($user_id, 'pc_member_id',     $member_id);
        if ($member_image) update_user_meta($user_id, 'pc_member_image',  $member_image);

        if ($activate) {
            update_user_meta($user_id, 'pc_subscription_active', '1');
            if ($expiry_date && strtotime($expiry_date)) {
                update_user_meta($user_id, 'pc_subscription_expiry', date('Y-m-d', strtotime($expiry_date)));
            }
            do_action('pc_membership_changed', $user_id);
        }

        $rows[] = array('row' => $row_num, 'email' => $email, 'status' => $status, 'note' => $note);
    }

    fclose($handle);
    PC_Activity_Log::log('csv_import', "CSV import: {$created} created, {$updated} updated, {$skipped} skipped");
    return compact('created', 'updated', 'skipped', 'rows');
}

// Download sample CSV
add_action('admin_post_pc_download_csv_sample', 'pc_download_csv_sample');
function pc_download_csv_sample() {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="import-sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('email','display_name','first_name','last_name','father_name','sport','member_id','expiry_date','member_image','password'));
    fputcsv($out, array('john@example.com','John Doe','John','Doe','Michael Doe','Football','M001','2027-12-31','',''));
    fputcsv($out, array('jane@example.com','Jane Smith','Jane','Smith','','Tennis','M002','2026-06-30','','secretpass'));
    fclose($out);
    exit;
}
