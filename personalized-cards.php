<?php
/**
 * Plugin Name: Personalized Cards Creator
 * Plugin URI: https://yoursite.com
 * Description: Create personalized cards with email delivery and digital wallet integration
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: personalized-cards
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PC_VERSION', '2.0.0');
define('PC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PC_PLUGIN_DIR . 'includes/class-database.php';
require_once PC_PLUGIN_DIR . 'includes/class-card-creator.php';
require_once PC_PLUGIN_DIR . 'includes/class-subscription-handler.php';
require_once PC_PLUGIN_DIR . 'includes/class-email-handler.php';
require_once PC_PLUGIN_DIR . 'includes/class-wallet-handler.php';
require_once PC_PLUGIN_DIR . 'includes/admin/admin-page.php';
require_once PC_PLUGIN_DIR . 'includes/admin/user-subscription.php';
require_once PC_PLUGIN_DIR . 'includes/shortcodes.php';
require_once PC_PLUGIN_DIR . 'includes/ajax-handlers.php';

// Composer autoload for wallet libraries
if (file_exists(PC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once PC_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation hook
register_activation_hook(__FILE__, 'pc_activate_plugin');
function pc_activate_plugin() {
    PC_Database::create_tables();
    
    // Create uploads directory for cards
    $upload_dir = wp_upload_dir();
    $cards_dir = $upload_dir['basedir'] . '/personalized-cards';
    if (!file_exists($cards_dir)) {
        wp_mkdir_p($cards_dir);
    }
    
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'pc_deactivate_plugin');
function pc_deactivate_plugin() {
    flush_rewrite_rules();
}

// Initialize plugin
add_action('plugins_loaded', 'pc_init_plugin');
function pc_init_plugin() {
    load_plugin_textdomain('personalized-cards', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'pc_enqueue_scripts');
function pc_enqueue_scripts() {
    wp_enqueue_style('pc-styles', PC_PLUGIN_URL . 'assets/css/styles.css', array(), PC_VERSION);
    wp_enqueue_script('pc-scripts', PC_PLUGIN_URL . 'assets/js/scripts.js', array('jquery'), PC_VERSION, true);
    
    wp_localize_script('pc-scripts', 'pcAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_nonce'),
        'pluginUrl' => PC_PLUGIN_URL
    ));
}

// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'pc_admin_enqueue_scripts');
function pc_admin_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_personalized-cards' && $hook !== 'users.php' && $hook !== 'user-edit.php' && $hook !== 'profile.php') {
        return;
    }
    
    wp_enqueue_style('pc-admin-styles', PC_PLUGIN_URL . 'assets/css/admin-styles.css', array(), PC_VERSION);
    wp_enqueue_script('pc-admin-scripts', PC_PLUGIN_URL . 'assets/js/admin-scripts.js', array('jquery'), PC_VERSION, true);
    
    wp_localize_script('pc-admin-scripts', 'pcAdminAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pc_admin_nonce')
    ));
}
