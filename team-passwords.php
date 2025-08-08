<?php
/**
 * Plugin Name: Team Passwords
 * Description: Simple team password manager for WordPress admin.
 * Version: 0.1
 * Author: Dan Fishman
 */

if (!defined('ABSPATH'))
    exit;

define('TP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TP_SECRET_KEY', 'pF8kLq2mN7jH1sD4tR9gV3cY6bX0wZ5eU7'); // Example: generate via openssl_random_pseudo_bytes(32)


/**
 * Registers a hook to execute plugin setup functions upon activation.
 *
 * This hook performs the following actions:
 * - Installs the required database tables for the plugin.
 * - Adds custom user roles and capabilities needed by the plugin.
 * - Creates the vault page for storing team passwords.
 *
 * @see tp_install_db()          Installs plugin database tables.
 * @see tp_add_role_and_caps()   Adds custom roles and capabilities.
 * @see tp_create_vault_page()   Creates the vault page.
 */
register_activation_hook(__FILE__, function () {
    tp_install_db();
    tp_add_role_and_caps();
    tp_create_vault_page();
});

/**
 * Creates the 'team_passwords' database table if it does not already exist.
 *
 * The table stores team password entries with fields for title, username, encrypted password,
 * URL, notes, owner user ID, shared roles, and timestamps for creation and updates.
 * Uses WordPress's dbDelta function to safely create or update the table structure.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function tp_install_db()
{
    global $wpdb;
    $table = $wpdb->prefix . 'team_passwords';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        username VARCHAR(255) DEFAULT NULL,
        password_encrypted TEXT NOT NULL,
        url VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        owner_user_id BIGINT(20) UNSIGNED NOT NULL,
        shared_roles TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Retrieves the URL for the Team Passwords vault page.
 *
 * This function checks if a custom vault page ID is set in the WordPress options.
 * If set, it returns the permalink for that page. Otherwise, it returns the default
 * vault URL at '/team-passwords' on the home site.
 *
 * @return string The URL of the vault page.
 */

function tp_get_vault_url(): string
{
    $page_id = get_option('tp_vault_page_id');
    if ($page_id) {
        return get_permalink($page_id);
    }
    return home_url('/team-passwords');
}

/**
 * Registers the 'team_passwords_user' role with specific capabilities and ensures
 * that administrators have the necessary capabilities for the Team Passwords plugin.
 *
 * This function is hooked to the plugin activation event.
 *
 * - Adds a custom role 'team_passwords_user' with capabilities:
 *   - 'read': Allows login and profile viewing.
 *   - 'view_team_passwords': Allows viewing team passwords.
 *   - Optionally, 'add_team_passwords': Allows adding team passwords (commented out).
 * - Ensures the 'administrator' role has both 'view_team_passwords' and 'add_team_passwords' capabilities.
 *
 * @return void
 */
function tp_add_role_and_caps()
{
    // Create minimal role
    add_role('team_passwords_user', 'Team Passwords', [
        'read' => true, // lets them log in and view their profile; doesn't grant admin access
        'view_team_passwords' => true,
        // 'add_team_passwords' => true, // uncomment if you want them to add items
    ]);

    // Ensure admins have the caps too
    if ($admin = get_role('administrator')) {
        $admin->add_cap('view_team_passwords');
        $admin->add_cap('add_team_passwords');
    }


}

function tp_create_vault_page()
{
    $slug = 'team-passwords';
    $title = 'Team Passwords';
    $shortcode = '[team_passwords]';

    // Check if page exists
    $existing = get_page_by_path($slug);
    if (!$existing) {
        $page_id = wp_insert_post([
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $shortcode,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);

        if (!is_wp_error($page_id)) {
            update_option('tp_vault_page_id', $page_id);
        }
    } else {
        update_option('tp_vault_page_id', $existing->ID);
    }
}

add_action('admin_init', function () {
    if (!is_user_logged_in() || wp_doing_ajax()) {
        return;
    }

    // Only act inside wp-admin
    if (!is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    // If they're a dedicated team user (and NOT an admin), redirect them away from wp-admin
    if (in_array('team_passwords_user', $roles, true) && !in_array('administrator', $roles, true)) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Allow profile page if you want; comment this block out to block profile too
        $is_profile = str_contains($request_uri, 'profile.php');

        if (!$is_profile) {
            wp_safe_redirect(tp_get_vault_url());
            exit;
        }
    }
});




// Include admin page logic
require_once TP_PLUGIN_DIR . 'includes/class-tp-admin.php';
require_once TP_PLUGIN_DIR . 'includes/class-tp-security.php';
require_once TP_PLUGIN_DIR . 'includes/class-tp-frontend.php';


// Instantiate classes
new TP_Admin();
new TP_Frontend();
