<?php
 /*
  * Plugin Name: Thawani Payment Gateway for Woocommerce and Multivendor
  * Description: Thawani Payment Gateway for Oman Users Only.
  * Author: Umer Naeem
  * Author URI: https://www.freelancer.com/u/umernaeemucp
  * Version: 1.0
  */
defined('ABSPATH') OR exit('Direct access not allowed');
if (!defined('WC_THAWANI_PATH')) {
    define('WC_THAWANI_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WC_THAWANI_URL')) {
    define('WC_THAWANI_URL', plugins_url('', __FILE__));
}


add_action('plugins_loaded', 'woocommerce_thawani_init', 0);
function woocommerce_thawani_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;
    include_once 'classes/class-thawani-gateway.php';

    function woocommerce_add_thawani_gateway($methods)
    {
        $methods[] = 'WC_Thawani';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_thawani_gateway');

    

    
}





/**
 * -----------------------
 * Plugin Activation Hook
 * -----------------------
 */
register_activation_hook(__FILE__, 'pluginActivate');
function pluginActivate()
{
    
}

/**
 * -------------------------
 * Plugin Deactivation Hook
 * -------------------------
 */
register_deactivation_hook(__FILE__, 'pluginDeactivate');
function pluginDeactivate()
{

}

/**
 * ----------------------
 * Plugin Uninstall Hook
 * ----------------------
 */
register_uninstall_hook(__FILE__, 'pluginUninstall');
function pluginUninstall()
{

}

