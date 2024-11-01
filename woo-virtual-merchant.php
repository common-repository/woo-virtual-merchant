<?php
/**
 * Plugin Name: WooCommerce Virtual Merchant
 * Plugin URI: https://wordpress.org/plugins/woo-virtual-merchant/
 * Description: Easily adds Virtual Merchant payment gateway to the WooCommerce plugin. It allows the customers to checkout via credit card.
 * Version: 1.0
 * Author: Vaibhav Joshi
 * Author URI: https://itvay.com/
 * Requires at least: 3.0
 * License: GPL2 or Later
 */

if (!defined('ABSPATH')) {
    //Exit if accessed directly
    exit;
}

//Slug - wcvmg

if (!class_exists('WC_Virtual_Merchant_Gateway_Addon')) {

    class WC_Virtual_Merchant_Gateway_Addon {

        var $version = '2.2';
        var $db_version = '1.0';
        var $plugin_url;
        var $plugin_path;

        function __construct() {
            $this->define_constants();
            $this->includes();
            $this->loader_operations();
            //Handle any db install and upgrade task
            add_action('init', array(&$this, 'plugin_init'), 0);
            
            add_filter('plugin_action_links', array(&$this, 'add_link_to_settings'), 10, 2);
        }

        function define_constants() {
            define('WC_VM_ADDON_VERSION', $this->version);
            define('WC_VM_ADDON_URL', $this->plugin_url());
            define('WC_VM_ADDON_PATH', $this->plugin_path());
        }

        function includes() {
            include_once('woo-vm-utility-class.php');
        }

        function loader_operations() {
            add_action('plugins_loaded', array(&$this, 'plugins_loaded_handler')); //plugins loaded hook		
        }

        function plugins_loaded_handler() {
            //Runs when plugins_loaded action gets fired
            include_once('woo-vm-gateway-class.php');
            add_filter('woocommerce_payment_gateways', array(&$this, 'init_paypal_pro_gateway'));
        }

        function do_db_upgrade_check() {
            //NOP
        }

        function plugin_url() {
            if ($this->plugin_url)
                return $this->plugin_url;
            return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
        }

        function plugin_path() {
            if ($this->plugin_path)
                return $this->plugin_path;
            return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
        }

        function plugin_init() {//Gets run when WP Init is fired
            
            load_plugin_textdomain('woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            
        }

        function add_link_to_settings($links, $file){
            if ($file == plugin_basename(__FILE__)) {
                $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=virtualmerchant">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;            
        }
        
        function init_paypal_pro_gateway($methods) {
            array_push($methods, 'WC_VM_Gateway');
            return $methods;
        }

    }

    //End of plugin class
}//End of class not exists check

$GLOBALS['WC_Virtual_Merchant_Gateway_Addon'] = new WC_Virtual_Merchant_Gateway_Addon();

