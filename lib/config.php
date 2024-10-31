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

define('NS8CSP_API_BASE_URL', 'https://api.ns8.com/v1');
define('NS8CSP_API_BASE_URL_OPTION', 'ns8csp_apibaseurl');
define('NS8CSP_WEBSITE_URL_OPTION', 'ns8csp_websiteurl');
define('NS8CSP_PROJECT_ID_OPTION', 'ns8csp_projectid');
define('NS8CSP_TOKEN_OPTION', 'ns8csp_token');

/**
 * Class to manage NS8 configuration items.
 */
class NS8CSP_Config {

	/**
	 * Return whether WooCommerce is active or not.
	 *
	 * @return bool
	 */
	static function wc_active() {
		return class_exists('WooCommerce');
	}

	/**
	 * Return the WooCommerce version.
	 *
	 * @return null
	 */
	static function wc_version() {
		if ( class_exists( 'WooCommerce' ) ) {
			global $woocommerce;
			return $woocommerce->version;
		}
		return null;
	}

	/**
	 * Check that the version of WooCommerce is supported.
	 *
	 * @param string $version   The version that needs to be supported.
	 *
	 * @return mixed
	 */
	static function wc_version_check( $version ) {
        return version_compare(self::wc_version(), $version, ">=" );
	}

	/**
	 * Return whether a string is valid JSON or not.
	 *
	 * @param $string   The JSON to check
	 *
	 * @return bool
	 */
	static function isJSON( $string ){
		return is_string( $string ) && is_array( json_decode( $string, true ) ) && ( json_last_error() == JSON_ERROR_NONE ) ? true : false;
	}

	/**
	 * Echo an error message.
	 *
	 * @param string $message   The message to display
	 */
	static function display_error( $message ) {
		$html = '<div class="error">';
        $html .= '<p>';
        $html .= $message;
        $html .= '</p>';
        $html .= '</div><!-- /.updated -->';

        echo $html;
	}

	/**
	 * Get the shop information required for the plugin.
	 *
	 * @return array    The shop's info
	 */
	static function get_shop() {
		return array(
			"id" => get_current_blog_id(),
			"email" => get_option( "admin_email" ),
			"baseUrl" => get_bloginfo( 'url' ),
			"shopUrl" => get_permalink( NS8CSP_Config::get_page_id('shop' ) ),
			"multisite" => is_multisite(),
			"description" => get_bloginfo( 'description' ),
			"gmtOffset" => get_option( "gmt_offset" ),
			"timezone" => get_option( "timezone_string" ),
			"name" => get_bloginfo( 'name' ),
			"wordPressVersion" => get_bloginfo( 'version' ),
			"language" => get_bloginfo( 'language' ),
			"currencySymbol" => NS8CSP_Config::wc_active() ? get_woocommerce_currency_symbol() : '$',
			"currencyDecimals" => NS8CSP_Config::wc_active() ? wc_get_price_decimals() : 2,
			"wooVersion" => NS8CSP_Config::wc_version(),
			"moduleVersion" => NS8CSP_VERSION,
			"phpVersion" => phpversion(),
			"phpOS" => PHP_OS
		);
	}

	/**
	 * Get the page id from the page name.
	 *
	 * @param string $page
	 *
	 * @return int
	 */
	static function get_page_id( $page ) {

		if (!NS8CSP_Config::wc_active()) {
			return 0;
		}

		return wc_get_page_id( $page );
	}

	/**
	 * Return the NS8 API base URL.
	 *
	 * @return string
	 */
	static function get_api_base_url() {
		$url = get_option( NS8CSP_API_BASE_URL_OPTION );
		return $url ? $url : NS8CSP_API_BASE_URL;
	}

	/**
	 * Set the NS8 base API URL.
	 *
	 * @param string $value
	 */
	static function set_api_base_url( $value ) {
		update_option( NS8CSP_API_BASE_URL_OPTION, $value );
	}

	/**
	 * Return the NS8 project id associated with this shop.
	 *
	 * @return string|void
	 */
	static function get_project_id() {
		return get_option( NS8CSP_PROJECT_ID_OPTION );
	}

	/**
	 * Set the NS8 project id associated with this shop.
	 *
	 * @param string $value
	 */
	static function set_project_id( $value ) {
		update_option( NS8CSP_PROJECT_ID_OPTION, $value );
	}

	/**
	 * Return the base URL of the console website for the plugin.
	 *
	 * @return string|void
	 */
	static function get_website_base_url() {
		$url = get_option( NS8CSP_WEBSITE_URL_OPTION );
		return $url ? $url : 'https://'.self::get_project_id().'.woo-protect.ns8.com';
	}

	/**
	 * Set the base URL of the console website for the plugin.
	 *
	 * @param string $value
	 */
	static function set_website_base_url( $value ) {
		update_option( NS8CSP_WEBSITE_URL_OPTION, $value );
	}

	/**
	 * Get the access token for the NS8 API.
	 *
	 * @return string|void
	 */
	static function get_access_token() {
		$value = get_option( NS8CSP_TOKEN_OPTION );
		return $value ? $value : null;
	}

	/**
	 * Set the access token for the NS8 API.
	 *
	 * @param $value
	 */
	static function set_access_token( $value ) {
		update_option( NS8CSP_TOKEN_OPTION, $value );
	}

	/**
	 * Clear the website and API URL locations, which will then use the defaults.
	 */
	static function reset_endpoints() {
		delete_option( NS8CSP_WEBSITE_URL_OPTION );
		delete_option( NS8CSP_API_BASE_URL_OPTION );
	}

	/**
	 * Return the link that will load the NS8 console and container.
	 *
	 * @param string $path  The path to load into the container
	 *
	 * @return string
	 */
	static function container_link( $path ) {
		return admin_url().'admin.php?page=ns8csp_dashboard&path='.urlencode($path);
	}

	/**
	 * Calculate the remote I.P. address of the website user.  This considers forwarding headers and IPv6
	 * translated to IPv4.
	 *
	 * @return string
	 */
	static function remote_address() {
        $xf = '';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xf = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $remoteAddr = '';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $remoteAddr = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($xf) && trim($xf) != '') {

            $xf = trim($xf);
            $xfs = array();

            //  see if multiple addresses are in the XFF header
            if (strpos($xf, '.') != false) {
                $xfs = explode(',', $xf);
            } else {
                if (strpos($xf, ' ') != false) {
                    $xfs = explode(' ', $xf);
                }
            }

            if (count($xfs) > 0)
            {

                //  get first public address, since multiple private routings can occur and be added to forwarded list
                for ($i = 0; $i < count($xfs); $i++) {

                    $ipTrim = trim($xfs[$i]);

                    if (substr($ipTrim, 0, 7) == '::ffff:' && count(explode('.', $ipTrim)) == 4)
                        $ipTrim = substr($ipTrim, 7);

                    if ($ipTrim != "" && substr($ipTrim, 0, 3) != "10." && substr($ipTrim, 0, 7) != "172.16." && substr($ipTrim, 0, 7) != "172.31." && substr($ipTrim, 0, 8) != "127.0.0." && substr($ipTrim, 0, 8) != "192.168." && $ipTrim != "unknown" && $ipTrim != "::1")
                        return ($ipTrim);

                }
                $xf = trim($xfs[0]);
            }

            if (substr($xf, 0, 7) == '::ffff:' && count(explode('.', $xf)) == 4)
                $xf = substr($xf, 7);

            //  a tiny % of hits have an unknown ip address
            if (substr($xf, 0, 7) == "unknown")
                return "127.0.0.1";

            return ($xf);

        } else {

            //  a tiny % of hits have an unknown ip address, so return a default address
            if (substr($remoteAddr, 0, 7) == "unknown")
                return "127.0.0.1";

            return ($remoteAddr);
        }
    }
}