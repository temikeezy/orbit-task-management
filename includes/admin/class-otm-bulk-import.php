<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the bulk user import functionality.
 */
class OTM_Bulk_Import {

    public static function init() {
        // Add the admin menu page
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        // Handle the form submission
        add_action('admin_post_otm_bulk_import_users', [__CLASS__, 'handle_import']);
    }

    /**
     * Adds the "Bulk Import" submenu page under "OTM Dashboard".
     */
    public static function add_menu_page() {
        add_submenu_page(
            'otm-dashboard',
            __('Bulk Import', 'otm'),
            __('Bulk Import', 'otm'),
            'otm_manage_settings',
            'otm-bulk-import',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Renders the HTML for the bulk import page.
     */
    public static function render_page() {
        ?>
        <div class="wrap otm-bulk-import-wrap">
            <h1><?php esc_html_e('Bulk User Import', 'otm'); ?></h1>
            <p><?php esc_html_e('Upload a CSV file to create new users and assign them a role.', 'otm'); ?></p>

            <?php
            // Display feedback messages from the import handler
            $feedback = get_transient('otm_import_feedback');
            $errors = get_transient('otm_import_errors');

            if ($feedback) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($feedback) . '</p></div>';
                delete_transient('otm_import_feedback');
            }

            if (!empty($errors)) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('Import encountered errors:', 'otm') . '</strong></p><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul></div>';
                delete_transient('otm_import_errors');
            }
            ?>

            <div class="otm-dashboard-card">
                <div class="otm-card-content">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="otm-form">
                        
                        <input type="hidden" name="action" value="otm_bulk_import_users">
                        <?php wp_nonce_field('otm_bulk_import_nonce'); ?>

                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="user_csv_file"><?php esc_html_e('CSV File', 'otm'); ?></label>
                                    </th>
                                    <td>
                                        <input type="file" id="user_csv_file" name="user_csv_file" accept=".csv" required>
                                        <p class="description">
                                            <?php esc_html_e('Upload a .csv file. The file must contain one email address per row in the first column.', 'otm'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="otm_user_role"><?php esc_html_e('Assign Role', 'otm'); ?></label>
                                    </th>
                                    <td>
                                        <select id="otm_user_role" name="otm_user_role">
                                            <option value="otm_intern"><?php esc_html_e('OTM Intern', 'otm'); ?></option>
                                            <option value="subscriber"><?php esc_html_e('Subscriber', 'otm'); ?></option>
                                            <option value="otm_moderator"><?php esc_html_e('OTM Moderator', 'otm'); ?></option>
                                            <!-- Add other roles as needed -->
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Select the role to assign to all new users.', 'otm'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Import Users', 'otm'); ?>
                            </button>
                        </p>

                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handles the file upload and user creation process.
     */
    public static function handle_import() {
        // --- Security Checks ---
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'otm_bulk_import_nonce')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('otm_manage_settings')) {
            wp_die('You do not have permission to import users.');
        }

        // --- File Validation ---
        if (empty($_FILES['user_csv_file']) || $_FILES['user_csv_file']['error'] !== UPLOAD_ERR_OK) {
            self::redirect_with_error('No file uploaded or file error occurred.');
            return;
        }

        $file_path = $_FILES['user_csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['user_csv_file']['name'], PATHINFO_EXTENSION));

        if ($file_ext !== 'csv') {
            self::redirect_with_error('Invalid file type. Please upload a .csv file.');
            return;
        }

        // --- Role Validation ---
        $selected_role = sanitize_text_field($_POST['otm_user_role']);
        $allowed_roles = ['otm_intern', 'otm_moderator', 'subscriber']; // Whitelist of roles
        
        if (!in_array($selected_role, $allowed_roles) || !get_role($selected_role)) {
            self::redirect_with_error('Invalid user role selected.');
            return;
        }

        // --- Process CSV ---
        $created_count = 0;
        $skipped_count = 0;
        $errors = [];

        // Increase time limit for larger files
        set_time_limit(300); 

        $file_handle = fopen($file_path, 'r');
        if ($file_handle === FALSE) {
            self::redirect_with_error('Could not open the uploaded file.');
            return;
        }

        while (($line = fgetcsv($file_handle)) !== FALSE) {
            if (empty($line[0])) {
                continue;
            }

            $email = sanitize_email(trim($line[0]));

            if (!is_email($email)) {
                $errors[] = "Invalid email format: " . esc_html($line[0]);
                continue;
            }

            if (email_exists($email)) {
                $skipped_count++;
                continue;
            }

            $username_base = sanitize_user(explode('@', $email)[0]);
            $username = $username_base;
            $i = 1;
            while (username_exists($username)) {
                $username = $username_base . $i;
                $i++;
            }

            // We set a dummy password; the user will set their own.
            $password = wp_generate_password(12, true, true);

            $user_data = [
                'user_login' => $username,
                'user_pass'  => $password,
                'user_email' => $email,
                'role'       => $selected_role,
            ];

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                $errors[] = "Failed to create user for " . esc_html($email) . ": " . $user_id->get_error_message();
            } else {

                // --- START OF NEW BYPASS (Aggressive Activation) ---
                
                // 1. Try the core BuddyPress/BuddyBoss function if it exists.
                if ( function_exists( 'bp_core_activate_user' ) ) {
                    bp_core_activate_user( $user_id );
                }
        
                // 2. Explicitly set the account status meta key to 'active'.
                // This overrides any 'pending' flag BuddyBoss may have set.
                update_user_meta( $user_id, 'bp_account_status', 'active' );
        
                // 3. (Just in case) Delete the old 'activation key' meta, as it's no longer needed.
                delete_user_meta( $user_id, 'activation_key' );
                // --- END OF NEW BYPASS ---

                
                // --- THIS IS THE MANUAL EMAIL LOGIC ---
                // We are bypassing wp_new_user_notification() entirely to avoid the BuddyBoss conflict.
                
                $user = get_user_by('id', $user_id);
                $key = get_password_reset_key($user);
                
                if (is_wp_error($key)) {
                    $errors[] = "Failed to generate password key for " . esc_html($email);
                    continue;
                }

                // Manually build the password reset link
                $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');
                $site_name = get_bloginfo('name');
                $subject = sprintf(__('[%s] Welcome to %s - Set Your Password'), $site_name, $site_name);
                
                // Manually build the email body
                $message  = sprintf(__('<p>Welcome to %s!</p>'), $site_name) . "\r\n\r\n";
                $message .= sprintf(__('<p>Your username is: <strong>%s</strong></p>'), $user->user_login) . "\r\n\r\n";
                $message .= __('<p>To set your password, please click the link below:</p>') . "\r\n\r\n";
                
                // --- THIS IS THE FIXED LINE ---
                // The extra parenthesis was removed from the end of the sprintf format string.
                $message .= sprintf('<p><a href="%s">%s</a></p>', $reset_link, $reset_link) . "\r\n";

                // Set headers for HTML email
                $headers = ['Content-Type: text/html; charset=UTF-8'];

                // Send the email using wp_mail(). WP Mail SMTP will intercept this.
                if ( ! wp_mail($user->user_email, $subject, $message, $headers) ) {
                    $errors[] = "Failed to send welcome email to " . esc_html($email);
                } else {
                    $created_count++;
                }
                // --- END OF NEW LOGIC ---
            }
        }

        fclose($file_handle);

        // --- Store Feedback and Redirect ---
        $feedback = sprintf(
            __('Import complete. Created: %d users. Skipped (already exist): %d users.', 'otm'),
            $created_count,
            $skipped_count
        );
        
        set_transient('otm_import_feedback', $feedback, 30);
        if (!empty($errors)) {
            set_transient('otm_import_errors', $errors, 30);
        }

        wp_redirect(admin_url('admin.php?page=otm-bulk-import'));
        exit;
    }

    /**
     * Helper to redirect back with an error message.
     */
    private static function redirect_with_error($message) {
        set_transient('otm_import_errors', [$message], 30);
        wp_redirect(admin_url('admin.php?page=otm-bulk-import'));
        exit;
    }
}

