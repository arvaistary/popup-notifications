<?php
/**
 * Uninstall file for Popup Notifications Plugin
 * 
 * This file is executed when the plugin is deleted through the WordPress admin.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('popup_notifications_settings');

// Remove any transients
delete_transient('popup_notifications_cache');
