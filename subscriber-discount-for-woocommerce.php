<?php
/**
 * Plugin Name: Subscriber Discount for WooCommerce
 * Plugin URI: https://yourwebsite.com
 * Description: Offer discounts to WooCommerce customers who subscribe via an email form.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create database table on plugin activation
function sdw_create_subscriber_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_discounts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'sdw_create_subscriber_table');

// Enqueue scripts for AJAX
function sdw_enqueue_scripts() {
    wp_enqueue_script('sdw-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
    wp_localize_script('sdw-script', 'sdw_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sdw_nonce')]);
}
add_action('wp_enqueue_scripts', 'sdw_enqueue_scripts');

// Shortcode for Subscription Form
function sdw_subscription_form() {
    return '<form id="sdw-subscribe-form">
        <input type="email" id="sdw-email" placeholder="Enter your email" required>
        <button type="submit">Subscribe</button>
        <p id="sdw-message"></p>
    </form>';
}
add_shortcode('sdw_subscription_form', 'sdw_subscription_form');

// Handle AJAX Subscription
function sdw_handle_subscription() {
    check_ajax_referer('sdw_nonce', 'nonce');
    global $wpdb;
    $email = sanitize_email($_POST['email']);
    
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Invalid email format.']);
    }
    
    $table_name = $wpdb->prefix . 'subscriber_discounts';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    
    if ($exists) {
        wp_send_json_error(['message' => 'You are already subscribed.']);
    }
    
    $wpdb->insert($table_name, ['email' => $email]);
    
    // Send email to admin
    if (get_option('sdw_notify_admin', true)) {
        wp_mail(get_option('admin_email'), 'New Subscriber Registered', "A new user has subscribed with the email: $email");
    }
    
    wp_send_json_success(['message' => 'Subscription successful!']);
}
add_action('wp_ajax_sdw_subscribe', 'sdw_handle_subscription');
add_action('wp_ajax_nopriv_sdw_subscribe', 'sdw_handle_subscription');

// Apply Discount to Subscribers
function sdw_apply_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    global $wpdb;
    
    if (is_user_logged_in()) {
        $user_email = wp_get_current_user()->user_email;
        $table_name = $wpdb->prefix . 'subscriber_discounts';
        $is_subscribed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $user_email));
        
        if ($is_subscribed) {
            $discount_percentage = get_option('sdw_discount_percentage', 10);
            $discount_amount = ($cart->subtotal * $discount_percentage) / 100;
            $cart->add_fee('Subscriber Discount', -$discount_amount);
        }
    }
}
add_action('woocommerce_cart_calculate_fees', 'sdw_apply_discount');

// Admin Menu for Managing Subscribers
function sdw_admin_menu() {
    add_menu_page('Subscriber Discount', 'Subscriber Discount', 'manage_options', 'sdw-subscribers', 'sdw_admin_page');
}
add_action('admin_menu', 'sdw_admin_menu');

function sdw_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_discounts';
    $subscribers = $wpdb->get_results("SELECT * FROM $table_name");
    echo '<h2>Subscriber List</h2>';
    echo '<table><tr><th>ID</th><th>Email</th><th>Subscription Date</th></tr>';
    foreach ($subscribers as $subscriber) {
        echo "<tr><td>{$subscriber->id}</td><td>{$subscriber->email}</td><td>{$subscriber->created_at}</td></tr>";
    }
    echo '</table>';
    echo '<a href="'.admin_url('admin-post.php?action=sdw_export_csv').'" class="button button-primary">Download CSV</a>';
}

// Export Subscribers to CSV
function sdw_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_discounts';
    $subscribers = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=subscribers.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Email', 'Subscription Date'));
    foreach ($subscribers as $subscriber) {
        fputcsv($output, $subscriber);
    }
    fclose($output);
    exit;
}
add_action('admin_post_sdw_export_csv', 'sdw_export_csv');

// Settings Page
function sdw_register_settings() {
    register_setting('sdw_settings_group', 'sdw_discount_percentage');
    register_setting('sdw_settings_group', 'sdw_notify_admin');
}
add_action('admin_init', 'sdw_register_settings');

function sdw_settings_page() {
    add_options_page('Subscriber Discount Settings', 'Subscriber Discount', 'manage_options', 'sdw-settings', 'sdw_settings_page_html');
}
add_action('admin_menu', 'sdw_settings_page');

function sdw_settings_page_html() {
    echo '<form method="post" action="options.php">';
    settings_fields('sdw_settings_group');
    echo '<label>Discount Percentage: <input type="number" name="sdw_discount_percentage" value="' . esc_attr(get_option('sdw_discount_percentage', 10)) . '" /></label><br/>';
    echo '<label><input type="checkbox" name="sdw_notify_admin" value="1" '.checked(1, get_option('sdw_notify_admin', 1), false).'> Notify Admin on Subscription</label><br/>';
    submit_button();
    echo '</form>';
}
