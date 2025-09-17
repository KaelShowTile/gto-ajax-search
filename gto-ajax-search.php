<?php

/*
Plugin Name: GTO Ajax Search Bar
Description: A plugin for GTO/CHT, design for searching products on realtime.  
Version: 1.1.1
Author: Kael
*/

// Prevent direct access to the file

if (!defined('ABSPATH')) {
    exit;
}

// Database table creation on activation
register_activation_hook(__FILE__, 'gto_ajax_search_create_table');

function gto_ajax_search_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'glint_wc_ajax_search';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        option_name varchar(191) NOT NULL,
        option_value longtext NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY option_name (option_name)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add default options if they don't exist
    $options = ['custom_post_type' => '', 'exclude_from_search_result' => ''];
    foreach ($options as $name => $value) {
        $wpdb->replace($table_name, [
            'option_name' => $name,
            'option_value' => $value
        ]);
    }
}

require_once plugin_dir_path(__FILE__) . 'includes/main.php';
require_once plugin_dir_path(__FILE__) . 'includes/search-result.php';
require_once plugin_dir_path(__FILE__) . 'includes/setting.php';

function gto_ajax_search_style_and_script() {
    wp_enqueue_style('woocommerce-ajax-search', plugin_dir_url(__FILE__) . 'css/woocommerce-ajax-search.css');
    wp_enqueue_script('woocommerce-ajax-search', plugin_dir_url(__FILE__) . 'js/woocommerce-ajax-search.js', array('jquery'), '1.2', true);

    // Get settings data
    $settings = new GTO_AJAX_Search_Settings();
    
    // Get excluded items
    $excluded_items = $settings->get_option('exclude_from_search_result');
    $excluded_array = [];
    if ($excluded_items) {
        $items = array_map('trim', explode("\n", $excluded_items));
        foreach ($items as $item) {
            if (preg_match('/^(product|category):\d+$/', $item)) {
                $excluded_array[] = $item;
            }
        }
    }
    
    // Get highest priority items
    $highest_priority = $settings->get_option('highest_priority');
    $highest_array = [];
    if ($highest_priority) {
        $items = array_map('trim', explode("\n", $highest_priority));
        foreach ($items as $item) {
            if (preg_match('/^(product|category):\d+$/', $item)) {
                $highest_array[] = $item;
            }
        }
    }
    
    // Get lowest priority items
    $lowest_priority = $settings->get_option('lowest_priority');
    $lowest_array = [];
    if ($lowest_priority) {
        $items = array_map('trim', explode("\n", $lowest_priority));
        foreach ($items as $item) {
            if (preg_match('/^(product|category):\d+$/', $item)) {
                $lowest_array[] = $item;
            }
        }
    }

    // Localize script with parameters
    wp_localize_script('woocommerce-ajax-search', 'woocommerce_ajax_search_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'min_characters' => 3,
        'min_chars_message' => esc_js(sprintf(
            __('Please enter at least %d characters', 'woocommerce'), 
            3
        )),
        'nonce' => wp_create_nonce('woocommerce_ajax_search_nonce'),
    ));
}

add_action('wp_enqueue_scripts', 'gto_ajax_search_style_and_script');

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce') && class_exists('GTO_AJAX_Search')) {
        new GTO_AJAX_Search();
        //new GTO_AJAX_Search_Settings();
    } else {
        if (current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                _e('GTO Ajax Search requires WooCommerce to be installed and activated!', 'woocommerce');
                echo '</p></div>';
            });
        }
    }
});

add_action('admin_init', function() {
    new GTO_AJAX_Search_Settings();
});