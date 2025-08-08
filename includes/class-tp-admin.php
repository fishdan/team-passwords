<?php
if (!defined('ABSPATH')) exit;

class TP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_tp_add_password', [$this, 'handle_add_password']);
        add_action('admin_post_tp_edit_password', [$this, 'handle_edit_password']);     // NEW
        add_action('admin_post_tp_delete_password', [$this, 'handle_delete_password']); // NEW
    }


    public function add_menu() {
        add_menu_page(
            'Team Passwords',
            'Team Passwords',
            'manage_options', // Only admins for now
            'team-passwords',
            [$this, 'render_admin_page'],
            'dashicons-lock'
        );
    }

    private function get_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    public function handle_delete_password() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id || !check_admin_referer('tp_delete_password_' . $id)) wp_die('Bad nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';
        $wpdb->delete($table, ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=team-passwords&deleted=1'));
        exit;
    }

    public function handle_edit_password() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id || !check_admin_referer('tp_edit_password_' . $id)) wp_die('Bad nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';

        $existing = $this->get_item($id);
        if (!$existing) wp_die('Not found');

        $title = sanitize_text_field($_POST['title']);
        $username = sanitize_text_field($_POST['username']);
        $new_password = trim((string)($_POST['password'] ?? ''));
        $url = esc_url_raw($_POST['url']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Keep existing encrypted password unless a new one was provided
        $password_encrypted = $existing->password_encrypted;
        if ($new_password !== '') {
            $password_encrypted = TP_Security::encrypt($new_password);
        }

        $wpdb->update(
            $table,
            [
                'title' => $title,
                'username' => $username,
                'password_encrypted' => $password_encrypted,
                'url' => $url,
                'notes' => $notes,
            ],
            ['id' => $id],
            ['%s','%s','%s','%s','%s'],
            ['%d']
        );

        wp_redirect(admin_url('admin.php?page=team-passwords&updated=1'));
        exit;
    }


    public function handle_add_password() {
        if (!current_user_can('manage_options') || !check_admin_referer('tp_add_password')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';

        $title = sanitize_text_field($_POST['title']);
        $username = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);
        $url = esc_url_raw($_POST['url']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $owner = get_current_user_id();

        $encrypted = TP_Security::encrypt($password);

        $wpdb->insert($table, [
            'title' => $title,
            'username' => $username,
            'password_encrypted' => $encrypted,
            'url' => $url,
            'notes' => $notes,
            'owner_user_id' => $owner,
            'shared_roles' => json_encode(['administrator']),
        ]);

        wp_redirect(admin_url('admin.php?page=team-passwords&added=1'));
        exit;
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';

        // Notices
        if (!empty($_GET['added'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Entry added.</p></div>';
        }
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Entry updated.</p></div>';
        }
        if (!empty($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Entry deleted.</p></div>';
        }

        // Are we editing?
        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $edit_item = $edit_id ? $this->get_item($edit_id) : null;

        ?>
        <div class="wrap">
            <h1>Team Passwords</h1>

            <?php if ($edit_item): ?>
                <h2>Edit Entry</h2>
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('tp_edit_password_' . $edit_item->id); ?>
                    <input type="hidden" name="action" value="tp_edit_password">
                    <input type="hidden" name="id" value="<?php echo esc_attr($edit_item->id); ?>">

                    <p><input type="text" name="title" class="regular-text" value="<?php echo esc_attr($edit_item->title); ?>" required></p>
                    <p><input type="text" name="username" class="regular-text" value="<?php echo esc_attr($edit_item->username); ?>"></p>
                    <p>
                        <input type="text" name="password" class="regular-text" placeholder="Leave blank to keep existing">
                    </p>
                    <p><input type="url" name="url" class="regular-text" value="<?php echo esc_attr($edit_item->url); ?>"></p>
                    <p><textarea name="notes" class="large-text"><?php echo esc_textarea($edit_item->notes); ?></textarea></p>

                    <p>
                        <button class="button button-primary">Save Changes</button>
                        <a class="button" href="<?php echo admin_url('admin.php?page=team-passwords'); ?>">Cancel</a>
                    </p>
                </form>
                <hr>
            <?php else: ?>
                <!-- Add form (unchanged) -->
                <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('tp_add_password'); ?>
                    <input type="hidden" name="action" value="tp_add_password">

                    <p><input type="text" name="title" placeholder="Title" required></p>
                    <p><input type="text" name="username" placeholder="Username"></p>
                    <p><input type="text" name="password" placeholder="Password" required></p>
                    <p><input type="url" name="url" placeholder="URL"></p>
                    <p><textarea name="notes" placeholder="Notes"></textarea></p>
                    <p><button class="button button-primary">Add Password</button></p>
                </form>
            <?php endif; ?>

            <h2>Stored Passwords</h2>
            <table class="widefat fixed">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>URL</th>
                    <th>Notes</th>
                    <th style="width:140px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $results = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
                foreach ($results as $row):
                    $edit_url = add_query_arg(
                        ['page' => 'team-passwords', 'edit' => $row->id],
                        admin_url('admin.php')
                    );
                    $delete_url = wp_nonce_url(
                        admin_url('admin-post.php?action=tp_delete_password&id=' . $row->id),
                        'tp_delete_password_' . $row->id
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html($row->title); ?></td>
                        <td><?php echo esc_html($row->username); ?></td>
                        <td><?php echo esc_html(TP_Security::decrypt($row->password_encrypted)); ?></td>
                        <td><a href="<?php echo esc_url($row->url); ?>" target="_blank"><?php echo esc_html($row->url); ?></a></td>
                        <td><?php echo esc_html($row->notes); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Edit</a>
                            <a class="button button-small button-link-delete"
                               href="<?php echo esc_url($delete_url); ?>"
                               onclick="return confirm('Delete this entry?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

}

