<?php
if (!defined('ABSPATH')) exit;

class TP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_tp_add_password', [$this, 'handle_add_password']);
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
        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>Team Passwords</h1>
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

            <h2>Stored Passwords</h2>
            <table class="widefat fixed">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>URL</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->title); ?></td>
                        <td><?php echo esc_html($row->username); ?></td>
                        <td><?php echo esc_html(TP_Security::decrypt($row->password_encrypted)); ?></td>
                        <td><a href="<?php echo esc_url($row->url); ?>" target="_blank"><?php echo esc_html($row->url); ?></a></td>
                        <td><?php echo esc_html($row->notes); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

