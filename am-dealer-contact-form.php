<?php
/**
 * Plugin Name: AM Dealer Contact Form
 * Plugin URI: https://example.com/am-dealer-contact-form
 * Description: A contact form for dealers to submit defect reports (STORM)
 * Version: 1.0.7
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: am-dealer-contact-form
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AM_DCF_VERSION', '1.0.7');
define('AM_DCF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AM_DCF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AM_DCF_PLUGIN_DIR . 'includes/class-am-dcf-database.php';
require_once AM_DCF_PLUGIN_DIR . 'includes/class-am-dcf-form-handler.php';
require_once AM_DCF_PLUGIN_DIR . 'includes/class-am-dcf-admin.php';

// Activation hook
register_activation_hook(__FILE__, 'am_dcf_activate');
function am_dcf_activate() {
    AM_DCF_Database::create_table();
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'am_dcf_deactivate');
function am_dcf_deactivate() {
    flush_rewrite_rules();
}

// Initialize plugin
add_action('plugins_loaded', 'am_dcf_init');
function am_dcf_init() {
    // Check for version change to update database
    if (get_option('am_dcf_version') !== AM_DCF_VERSION) {
        AM_DCF_Database::create_table();
        update_option('am_dcf_version', AM_DCF_VERSION);
    }

    // Load text domain for translations
    load_plugin_textdomain('am-dealer-contact-form', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize form handler
    new AM_DCF_Form_Handler();
    
    // Initialize admin if in admin area
    if (is_admin()) {
        new AM_DCF_Admin();
    }
}

// Add HEIC support to WordPress mime types
add_filter('upload_mimes', 'am_dcf_add_heic_mime_type');
function am_dcf_add_heic_mime_type($mimes) {
    $mimes['heic'] = 'image/heic';
    $mimes['heif'] = 'image/heif';
    return $mimes;
}

// Register shortcode for the form
add_shortcode('am_dealer_contact_form', 'am_dcf_display_form');
function am_dcf_display_form($atts) {
    ob_start();
    include AM_DCF_PLUGIN_DIR . 'templates/form.php';
    return ob_get_clean();
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'am_dcf_enqueue_scripts');
function am_dcf_enqueue_scripts() {
    wp_enqueue_script('am-dcf-form', AM_DCF_PLUGIN_URL . 'assets/js/form.js', array('jquery'), AM_DCF_VERSION, true);
    wp_enqueue_style('am-dcf-form', AM_DCF_PLUGIN_URL . 'assets/css/form.css', array(), AM_DCF_VERSION);
    
    // Localize script for AJAX
    wp_localize_script('am-dcf-form', 'amDcfAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('am_dcf_nonce')
    ));
}

