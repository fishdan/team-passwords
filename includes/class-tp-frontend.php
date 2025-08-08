<?php
if (!defined('ABSPATH'))
    exit;

class TP_Frontend
{
    public function __construct()
    {
        add_shortcode('team_passwords', [$this, 'render_passwords_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_tp_frontend_add_password', [$this, 'handle_add_password']);
        add_action('wp_footer', [$this, 'force_modal_to_body'], 100);
    }

    public function force_modal_to_body()
    {
        if (!is_page())
            return;
        global $post;
        if (!$post || !is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'team_passwords'))
            return;
        ?>
                <script>
                    (function () {
                        var m = document.getElementById('tpAddModal');
                        if (m && m.parentNode !== document.body) {
                            document.body.appendChild(m);
                        }
                    })();
                </script>
                <?php
    }


    public function handle_add_password()
    {
        if (!is_user_logged_in() || !check_admin_referer('tp_frontend_add_password')) {
            wp_die('Unauthorized');
        }

        if (!current_user_can('add_team_passwords')) {
            wp_die('Insufficient permissions');
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
        // was: wp_redirect(add_query_arg('added', '1', wp_get_referer()));
        wp_redirect(add_query_arg('added', '1', tp_get_vault_url()));
        exit;

    }


    public function enqueue_assets()
    {
        if (!is_page())
            return;

        global $post;
        if (!$post || !is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'team_passwords'))
            return;

        // CSS
        wp_enqueue_style('tp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
        wp_enqueue_style('tp-frontend-style', plugin_dir_url(__DIR__) . 'assets/css/frontend.css');

        // JS (Bootstrap bundle includes Popper)
        wp_enqueue_script('tp-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], null, true);
    }


    public function render_passwords_shortcode()
    {
        if (!is_user_logged_in()) {

            ob_start(); ?>
            <div class="d-flex justify-content-center mt-5">
                <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
                    <h4 class="mb-3 text-center">Team Vault Login</h4>
                    <?php
                    $form = wp_login_form([
                        'redirect' => tp_get_vault_url(),
                        'form_id' => 'team-passwords-loginform',
                        'label_username' => __('Username or Email'),
                        'label_password' => __('Password'),
                        'label_remember' => __('Remember Me'),
                        'label_log_in' => __('Log In'),
                        'remember' => true,
                        'echo' => false,
                    ]);

                    // Wrap the username row + add Bootstrap classes
                    $form = preg_replace(
                        '#<p class="login-username">(.*?)</p>#s',
                        '<div class="mb-3">$1</div>',
                        $form
                    );
                    $form = str_replace(
                        '<label for="user_login">',
                        '<label class="form-label" for="user_login">',
                        $form
                    );
                    $form = str_replace(
                        'id="user_login"',
                        'class="form-control" id="user_login"',
                        $form
                    );

                    // Wrap the password row + add Bootstrap classes
                    $form = preg_replace(
                        '#<p class="login-password">(.*?)</p>#s',
                        '<div class="mb-3">$1</div>',
                        $form
                    );
                    $form = str_replace(
                        '<label for="user_pass">',
                        '<label class="form-label" for="user_pass">',
                        $form
                    );
                    $form = str_replace(
                        'id="user_pass"',
                        'class="form-control" id="user_pass"',
                        $form
                    );

                    // Remember me checkbox
                    $form = preg_replace(
                        '#<p class="login-remember">(.*?)</p>#s',
                        '<div class="form-check mb-3">$1</div>',
                        $form
                    );
                    $form = str_replace(
                        'name="rememberme" id="rememberme"',
                        'name="rememberme" id="rememberme" class="form-check-input"',
                        $form
                    );
                    $form = str_replace(
                        '<label for="rememberme">',
                        '<label class="form-check-label" for="rememberme">',
                        $form
                    );

                    // Submit button row
                    $form = preg_replace(
                        '#<p class="login-submit">(.*?)</p>#s',
                        '<div class="d-grid mb-0">$1</div>',
                        $form
                    );
                    $form = str_replace(
                        'id="wp-submit"',
                        'id="wp-submit" class="btn btn-primary"',
                        $form
                    );

                    echo $form;
                    ?>
                </div>
            </div>
            <?php
            echo ob_get_clean();
            return;

        }



        if (!current_user_can('view_team_passwords')) {
            return '<p>You do not have permission to view this page.</p>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'team_passwords';
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

        ob_start();
        ?>
        <div class="team-passwords">
            <h2>Team Passwords</h2>

            <?php if (current_user_can('add_team_passwords')): ?>
                                                                <div class="d-flex justify-content-end mb-3">
                                                                    <!-- Button stays the same -->
                                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tpAddModal">
                                                                        Add Password
                                                                    </button>
                                                    
                                                                    <!-- Modal -->
                                                                    <div class="modal fade tp-modal" id="tpAddModal" tabindex="-1" aria-labelledby="tpAddModalLabel" aria-hidden="true">
                                                                        <div class="modal-dialog modal-dialog-centered">
                                                                            <div class="modal-content">
                                                                                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                                        <?php wp_nonce_field('tp_frontend_add_password'); ?>
                                                                        <input type="hidden" name="action" value="tp_frontend_add_password">

                                    <div class="modal-header px-3 py-2">
                                        <h5 class="modal-title" id="tpAddModalLabel">Add Password</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body p-3">
                                        <div class="mb-3">
                                            <label for="tp_title" class="form-label">Title</label>
                                            <input type="text" id="tp_title" name="title" class="form-control" required autofocus>
                                        </div>

                                        <div class="mb-3">
                                            <label for="tp_username" class="form-label">Username</label>
                                            <input type="text" id="tp_username" name="username" class="form-control">
                                        </div>

                                        <div class="mb-3">
                                            <label for="tp_password" class="form-label">Password</label>
                                            <input type="text" id="tp_password" name="password" class="form-control" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="tp_url" class="form-label">URL</label>
                                            <input type="url" id="tp_url" name="url" class="form-control" placeholder="https://">
                                        </div>

                                        <div class="mb-3">
                                            <label for="tp_notes" class="form-label">Notes</label>
                                            <textarea id="tp_notes" name="notes" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>

                                    <div class="modal-footer p-3">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>

            <?php endif; ?>

            <table class="table table-bordered team-passwords-table">
                <thead>
                    <tr>
                        <th style="width:15%;">Title</th>
                        <th style="width:15%;">Username</th>
                        <th style="width:20%;">Password</th>
                        <th style="width:20%;">URL</th>
                        <th style="width:30%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->title); ?></td>
                            <td><?php echo esc_html($row->username); ?></td>
                            <td>
                                <input type="password"
                                    value="<?php echo esc_attr(TP_Security::decrypt($row->password_encrypted)); ?>" readonly
                                    class="form-control-plaintext" style="width: 100px; display: inline;" />
                                <button class="btn btn-sm btn-outline-secondary reveal-btn"
                                    onclick="this.previousElementSibling.type='text'; this.remove();">Show</button>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($row->url); ?>" target="_blank"><?php echo esc_html($row->url); ?></a>
                            </td>
                            <td><?php echo esc_html($row->notes); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
