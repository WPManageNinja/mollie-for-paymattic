<?php

/**
 * @package mollie-for-paymattic
 */
/*

Plugin Name: Mollie for paymattic
Plugin URI: https://paymattic.com/
Description: A custom payment gateway for paymattic.
Version: 1.0.0
Author: WPManageNinja LLC
Author URI: https://paymattic.com/
License: GPLv2 or later
Text Domain: mollie-for-paymattic
*/

if (!defined('ABSPATH')) {
    exit;
}

defined('ABSPATH') or die;
define('MOLLIE_FOR_PAYMATTIC', true);
define('MOLLIE_FOR_PAYMATTIC_DIR', __DIR__);
define('MOLLIE_FOR_PAYMATTIC_VERSION', '1.0');


add_action('wppayform_loaded', function () {
    // here we also need to check the payform version 
    // as custom payment gateway is not available before 4.3.1
    if (defined('WPPAYFORMHASPRO')) {
        if (!class_exists('MollieForPaymattic\MollieProcessor')) {
            require_once MOLLIE_FOR_PAYMATTIC_DIR . '/API/MollieProcessor.php';
            (new MollieForPaymattic\API\MollieProcessor())->init();
        };
    } else {
        add_action('admin_notices', function () {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>';
                echo __('Please install Paymattic  and Paymattic Pro to use !', 'mollie-for-paymattic');
                echo '</p></div>';
            }
        });
    }
});
