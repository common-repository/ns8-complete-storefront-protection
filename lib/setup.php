<?php
/**
 * Author: NS8.com
 * Author URI: https://ns8.com
 * Copyright 2017 NS8.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined( 'ABSPATH' ))
	exit();

/**
 * NS8CSP setup class.
 */
class NS8CSP_Setup {

	/**
	 * Activate ajax for th plugin.
	 */
	public static function activate_ajax() {
		self::activate();
	}

	/**
	 * Install and activate the plugin.
	 */
	public static function activate() {

		try {
			NS8CSP_Logger::info('activate', 'Installing CSP app...');

			$params = array(
				"shop" => NS8CSP_Config::get_shop(),    // default shop
				"email" => get_option( "admin_email" ),
				"accessToken" => NS8CSP_Config::get_access_token(),
				"plugins" => get_option( "active_plugins", true ),
			);

			$result = NS8CSP_API::post( '/protect/woo/install', $params );

			if ( $result['code'] != 200 ) {
				trigger_error( "NS8 activation failed with the error: ".$result['message'], E_USER_ERROR );
			} else {
				NS8CSP_Config::set_access_token( $result['data']->accessToken );
				NS8CSP_Config::set_project_id( $result['data']->projectId );
			}
		} catch ( Exception $e ) {
			NS8CSP_Logger::info('activate', 'error trap', $e->getMessage() );
			trigger_error( "NS8 activation failed with the error: ".$e->getMessage(), E_USER_ERROR );
		}
	}

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		NS8CSP_Logger::info('deactivate', 'Deactivating CSP app...');
		self::uninstall();
	}

	/**
	 * Uninstall the plugin.
	 */
	public static function uninstall() {
		NS8CSP_Logger::info('uninstall', 'Uninstalling CSP app...');

		$params = [
			"accessToken" => NS8CSP_Config::get_access_token()
		];

		NS8CSP_API::post( '/protect/woo/uninstall', $params );
	}
}