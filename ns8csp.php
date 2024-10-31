<?php
/**
 * Plugin Name: NS8 Protect™
 * Plugin URI: https://www.ns8.com/protect/woocommerce
 * Description: Protect your store from the three big revenue killers: advertising fraud, order fraud, and performance issues.
 * Version: 1.1.24
 * Author: NS8.com
 * Author URI: http://ns8.com/
 * Developer: Phil Vizzaccaro
 * Developer URI: http://ns8.com/
 * Text Domain: ns8csp
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.3
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * NS8.com terms and conditions for subscribers:
 * https://www.ns8.com/policies/eula/
 * https://www.ns8.com/policies/tos/
 */

//  Exit if accessed directly
if (!defined( 'ABSPATH' ) )
	exit();

define('NS8CSP_VERSION', '1.1.24' );
define('NS8CSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once(NS8CSP_PLUGIN_DIR.'lib/setup.php');
require_once(NS8CSP_PLUGIN_DIR.'lib/config.php');
require_once(NS8CSP_PLUGIN_DIR.'lib/logger.php');
require_once(NS8CSP_PLUGIN_DIR.'lib/api.php');
require_once(NS8CSP_PLUGIN_DIR.'admin/container.php');

/**
 * Class NS8CSP - Plugin initialization.
 */
class NS8CSP {

	protected $ns8csp_wc;

	public function __construct() {
		register_activation_hook( __FILE__, array( 'NS8CSP_Setup', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'NS8CSP_Setup', 'deactivate'));
		register_uninstall_hook( __FILE__, array( 'NS8CSP_Setup', 'uninstall' ) );
		add_action( 'wp_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {

		//  if plugin not activated, show activation setup
		if ( !NS8CSP_Config::get_access_token() ) {
			require_once(NS8CSP_PLUGIN_DIR.'lib/setup-inactive.php');
			NS8CSP_Setup_Inactive::init();
		} else {
			add_action( 'admin_menu', array( 'NS8CSP', 'add_menus' ) );
			add_action( 'wp_enqueue_scripts', array( 'NS8CSP', 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( 'NS8CSP', 'enqueue_admin_scripts' ) );

			//  Woo setup if Woo is active
			if ( NS8CSP_Config::wc_active( ) ) {
				add_action( 'wp_enqueue_scripts', array( 'NS8CSP_WC', 'enqueue_scripts' ) );
				require_once(NS8CSP_PLUGIN_DIR.'wc/wc.php');
				$ns8csp_wc = new NS8CSP_WC();
			}
		}
	}

	/**
	 * Enqueue the scripts needed for the plugin.
	 */
	static function enqueue_scripts() {
		$projectId = NS8CSP_Config::get_project_id();

		if ($projectId) {
			wp_register_script(
				'ns8csp',
				'https://api.ns8.com/v1/analytics/script/'.$projectId.'?timing=1&name=ns8csp',
				array(),
				null,
				true
			);
			wp_enqueue_script('ns8csp');
		}
	}

	/**
	 * Enqueue the admin scripts needed for the plugin.
	 */
    static function enqueue_admin_scripts() {
	    wp_enqueue_script( 'jquery-ui-dialog' );
	    wp_enqueue_style ( 'wp-jquery-ui-dialog' );

        wp_register_style( 'ns8csp_toastr_css', plugins_url( 'css/toastr.min.css', __FILE__ ), false, NS8CSP_VERSION );
        wp_enqueue_style( 'ns8csp_toastr_css' );

        wp_register_script( 'ns8csp_toastr_js', plugins_url( 'js/toastr.min.js', __FILE__ ), false, NS8CSP_VERSION );
        wp_enqueue_script( 'ns8csp_toastr_js' );

	    wp_register_script( 'ns8csp_admin', NS8CSP_Config::get_website_base_url().'/js/admin.js', false, NS8CSP_VERSION );
	    wp_enqueue_script( 'ns8csp_admin' );

	    wp_register_style( 'ns8csp_styles_css', plugins_url( 'css/styles.css', __FILE__ ), false, NS8CSP_VERSION );
	    wp_enqueue_style( 'ns8csp_styles_css' );
    }

	/**
	 * Setup the menu for the plugin.
	 */
	static function add_menus() {

		if ( current_user_can( 'manage_options' ) ) {

			add_menu_page(
				'NS8 Protect',
				'NS8 Protect',
				'manage_options',
				'ns8csp_dashboard',
				null,
				'dashicons-shield'
			);

			add_submenu_page(
				'ns8csp_dashboard',
				'NS8 Protect',
				'Dashboard',
				'manage_options',
				'ns8csp_dashboard',
                array( 'NS8CSP_Pages', 'dashboard' )
			);

			if (NS8CSP_Config::wc_active()) {

				add_submenu_page(
					'ns8csp_dashboard',
					'NS8 Protect',
					'Suspicious Orders',
					'manage_options',
					'ns8csp_orders',
					array( 'NS8CSP_Pages', 'orders' )
				);

				add_submenu_page(
					'ns8csp_dashboard',
					'NS8 Protect',
					'Order Rules',
					'manage_options',
					'ns8csp_rules',
					array( 'NS8CSP_Pages', 'rules' )
				);
			}

			add_submenu_page(
				'ns8csp_dashboard',
				'NS8 Protect',
				'Campaigns',
				'manage_options',
				'ns8csp_campaigns',
				array( 'NS8CSP_Pages', 'campaigns' )
			);

			add_submenu_page(
				'ns8csp_dashboard',
				'Monitors',
				'Monitors',
				'manage_options',
				'ns8csp_monitors',
				array( 'NS8CSP_Pages', 'monitors' )
			);
		}
	}
}

new NS8CSP();