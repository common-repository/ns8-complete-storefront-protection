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
 * Class NS8CSP WooCommerce integration
 */
class NS8CSP_WC {
	protected $processor;

	public function __construct() {
		require_once(NS8CSP_PLUGIN_DIR.'lib/async-request.php');
		require_once(NS8CSP_PLUGIN_DIR.'lib/background-process.php');
		require_once(NS8CSP_PLUGIN_DIR.'wc/sync.php');

		$this->processor = new NS8CSP_Sync();

		//  columns
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_columns' ), 11 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_columns_content' ), 10, 2 );
		add_action( 'woocommerce_order_action_ns8csp_approve', array( $this, 'approve_order' ) );
		add_action( 'add_meta_boxes', array( $this, 'order_meta_box' ) );

		add_action( 'save_post_shop_order', array( $this, 'order_saved' ) );

		add_action( 'wp_ajax_ns8csp_sync_order', array( $this, 'sync_order_ajax' ) );
		add_action( 'wp_ajax_ns8csp_approve_order', array( $this, 'approve_order_ajax' ) );
		add_action( 'wp_ajax_ns8csp_hold_order', array( $this, 'hold_order_ajax' ) );
		add_action( 'wp_ajax_ns8csp_cancel_order', array( $this, 'cancel_order_ajax' ) );
		add_action( 'wp_ajax_ns8csp_sms_check', array( $this, 'sms_check_ajax' ) );
		add_action( 'woocommerce_archive_description', array( $this, 'display_content'), 9 );
	}

	/**
	 * Setup the scripts required for the integration.
	 */
	static function enqueue_scripts() {
		if ( isset( $_GET['ns8cspcontent'] ) ) {
			wp_register_script( 'ns8csp_front', plugins_url( 'js/ns8csp.js', __FILE__ ), false, NS8CSP_VERSION );
			wp_enqueue_script( 'ns8csp_front' );

			wp_localize_script( 'ns8csp_front', 'NS8CSP_Local', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'ns8csp-check' )
			));
		}
	}

	/**
	 * Display page content in the container.
	 */
	public function display_content() {
		if ( isset( $_GET['ns8cspcontent'] ) ) {
			$url = NS8CSP_Config::get_website_base_url().$_GET['ns8cspcontent'];
			echo '<iframe id="ns8-app-iframe" src="'.$url.'" frameborder="0" scrolling="no" style="min-height: 300px;width:100%; border: none !important"></iframe>';
		}
	}

	/**
	 * Send an SMS validation check to a customer.
	 */
	public function sms_check_ajax() {
		$order_id = intval( $_POST['order_id'] );

		$params = array(
			'orderName' => $order_id,
			'accessToken' => NS8CSP_Config::get_access_token()
		);

		$result = NS8CSP_API::post( '/protect/woo/orders/validate', $params );

		if ( $result['code'] != 200 ) {
			echo $result['message'];
		}  else {
			echo 'SMS check sent';
		}
		die();
	}

	/**
	 * Create a task to sync an order.
	 */
	public function sync_order_ajax() {
		check_ajax_referer( 'ns8csp-check', 'security' );

		$order_id = intval( $_POST['order_id'] );

		//  front-end order sync should force an update
		$this->create_sync_order_task( $order_id, '1' );
		die();
	}

	/**
	 * Create a task to approve an order via ajax.
	 */
	public function approve_order_ajax() {
		$order_id = intval( $_POST['order_id'] );
		$note = sanitize_text_field( $_POST['note'] );

		$message = $this->approve_order( $order_id, $note );

		if ( isset( $message ) ) {
			echo $message;
		}

		die();
	}

	/**
	 * Create a task to cancel an order via ajax.
	 */
	public function cancel_order_ajax() {
		$order_id = intval( $_POST['order_id'] );
		$note = sanitize_text_field( $_POST['note'] );

		$message = $this->cancel_order( $order_id, $note );

		if ( isset( $message ) ) {
			echo $message;
		} else {
			echo 'Order cancelled.';
		}

		die();
	}

	/**
	 * Create a task to approve an order.
	 */
	public function approve_order( $order_id, $note ) {
		$message = $this->set_NS8_status( $order_id, 'approved' );

		if ( isset( $message ) ) {
			return $message;
		}

		update_post_meta( $order_id, 'NS8 Status', 'approved' );
		$order = new WC_Order( $order_id );

		if ( isset( $note ) && strlen( $note ) > 0 ) {
			$order->add_order_note( '[NS8] Order approved - '.$note, 0, true );
		} else {
			$order->add_order_note( '[NS8] Order approved', 0, true );
		}

		return null;
	}

	/**
	 * Create a task to cancel an order.
	 */
	public function cancel_order( $order_id, $note ) {

		$order = new WC_Order( $order_id );
		$order->update_status( 'cancelled' );

		$message = $this->set_NS8_status( $order_id, 'canceled' );

		if ( isset( $message ) ) {
			return $message;
		}

		update_post_meta( $order_id, 'NS8 Status', 'canceled' );

		if ( isset( $note ) && strlen( $note ) > 0 ) {
			$order->add_order_note( '[NS8] Order cancelled - '.$note, 0, true );
		} else {
			$order->add_order_note( '[NS8] Order cancelled', 0, true );
		}

		return null;
	}

	/**
	 * Create a task to hold an order via ajax.
	 */
	public function hold_order_ajax() {
		$order_id = intval( $_POST['order_id'] );
		$note = sanitize_text_field( $_POST['note'] );

		$message = $this->hold_order( $order_id, $note );

		if ( isset( $message ) ) {
			echo $message;
		}

		die();
	}

	/**
	 * Create a task to hold an order.
	 */
	public function hold_order( $order_id, $note ) {

		$order = new WC_Order( $order_id );
		$order->update_status( 'on-hold' );

		if ( isset( $note ) && strlen( $note ) > 0 ) {
			$order->add_order_note( '[NS8] Order put on hold - '.$note, 0, true );
		}

		return null;
	}

	/**
	 * Set the NS8 status on an order.
	 *
	 * @param $order_id
	 * @param $ns8_status
	 *
	 * @return null|string
	 */
	static function set_NS8_status( $order_id, $ns8_status ) {

		$params = array(
			'order_id' => $order_id,
			'NS8Status' => $ns8_status,
			'accessToken' => NS8CSP_Config::get_access_token()
		);

		$response = wp_remote_post( NS8CSP_Config::get_api_base_url().'/protect/woo/orders/status', array(
			'method' => 'POST',
			'timeout' => 10,
			'redirection' => 2,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'body' => $params,
			'cookies' => array()
		) );

		if ( isset( $response->errors ) || !isset( $response['response'] ) || !isset( $response['response']['code'] ) || $response['response']['code'] != 200 ) {
			return 'Unable to set order status';
		} else {
			return null;
		}
	}

	/**
	 * When an order is saved, create a task to sync the order status with NS8.
	 *
	 * @param $order_id
	 */
	public function order_saved( $order_id ) {
		$this->create_sync_order_task( $order_id, 1 );
	}

	/**
	 * Create the task to sync an order's status with NS8.
	 *
	 * @param $order_id
	 */
	public function create_sync_order_task( $order_id, $force ) {
		NS8CSP_Logger::info('create_sync_order_task', $order_id );

		$user_id = null;
		$ua = null;
		$language = null;
		$user_id_cookie = '__na_u_'.NS8CSP_Config::get_project_id();

		if ( isset( $_COOKIE[$user_id_cookie] ) ) {
			$user_id = $_COOKIE[$user_id_cookie];
		}

		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}

		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		}

		if ( !isset( $force ) ) {
			$force = false;
		}

		$task = array(
			'type' => 'sync_order',
			'order_id' => $order_id,
			'ip' => NS8CSP_Config::remote_address(),
			'ua' => $ua,
			'user_id' => $user_id,
			'language' => $language,
			'shop' => NS8CSP_Config::get_shop(),
            'force' => $force
		);

		$this->processor->push_to_queue( $task );
		$this->processor->save()->dispatch();
	}

	/**
	 * Add a meta box to the order screen with order status info.
	 */
	public function order_meta_box() {
		global $current_screen;

		if ( $current_screen->action != 'add' ) {
			add_meta_box(
				'ns8csp_order_meta_box',
				__( 'NS8 Order Analysis' ),
				array( $this, 'order_meta_box_content' ),
				'shop_order',
				'side',
				'high'
			);
		}
	}

	/**
	 * Display the order info meta box content.
	 */
	public function order_meta_box_content() {
		global $post;
		$id = $post->ID;
		$status = get_post_meta( $id, 'NS8 Status', true );

		if ( !$status ) {

			$order = new WC_Order( $id );

			echo '<div style="width:100%; text-align:center; margin-bottom:5px" id="ns8csp_meta_box" post_id="' . $id . '">';

			if ( isset( $order ) && $order->get_status() == 'pending' ) {
				echo '<h4>Pending orders are not processed by NS8.</h4>';
			} else {
				echo '<h4>This order has not been processed by NS8.</h4>';
				echo '<input type="button" class="button button-secondary button-small" value="Add to queue" onclick="NS8CSP.processOrder(' . $id . ')" />';
			}
			echo '</div>';
		} else {

			$score = get_post_meta( $id, 'EQ8 Score', true );

			echo '<div class="ns8-order-metabox-section">';
			echo 'EQ8 Score: <strong>'.$score.'</strong>';

			if ( $score <= 100 ) {
				echo ', which is highly suspicious';
			}
			echo '</div>';

			echo '<div class="ns8-order-metabox-section">';

			switch ( $status ) {
				case 'approved':
				case 'canceled':
					echo 'NS8 Status: <strong>'.NS8CSP_WC::status_desc( $status ).'</strong>';
					break;
				default:
					echo 'Recommendation: <strong>'.NS8CSP_WC::status_desc( $status ).'</strong>';
			}
			echo ' <span class="ns8-metabox-icon ns8-'.$status.'-icon-text"></span>';
			echo '</div>';

			$order_url = NS8CSP_Config::container_link('/order-detail?order='.$id);

			echo '<div class="ns8-order-metabox-section ns8-order-metabox-actions">';
			echo '<a href="'.$order_url.'" style="position:relative;top:4px">Details</a>';

			$approve_class = $status == 'accept' ? 'primary' : 'secondary';

			switch ( $status ) {
				case 'accept':
				case 'investigate':
				case 'cancel':
					echo '<input style="float:right" type="button" class="button button-secondary" value="SMS check" onclick="NS8CSP.smsCheck(' . $id . ')" />';
					echo '&nbsp;<input style="float:right;margin-right:4px" type="button" class="button button-'.$approve_class.'" value="Approve" onclick="NS8CSP.approveOrderFromAdmin('.$id.')" />';
					break;
			}
			echo '</div>';
		}
	}

	/**
	 * Setup the order columns for NS8 data.
	 * @param $columns
	 *
	 * @return array
	 */
	public function order_columns( $columns ) {
		$columns = ( is_array( $columns ) ) ? $columns : array();
		$columns['ns8_status'] = 'NS8 Status';
		return $columns;
	}

	/**
	 * Get the order id from an order.
	 *
	 * @param $order
	 *
	 * @return mixed
	 */
	public function get_order_id( $order ) {
		return method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
	}

	/**
	 * Format an order status (replace canceled with cancelled to match Woo's spelling).
	 * @param $status
	 *
	 * @return string
	 */
	public function status_desc( $status ) {
		if ( $status == 'canceled' ) {
			return 'cancelled';
		} else {
			return $status;
		}
	}

	/**
	 * Gets the content for an NS8 column on the orders screen.
	 *
	 * @param string $column    The column name
	 */
	public function order_columns_content( $column ) {
		global $the_order;

		switch ( $column ) {

			case 'ns8_status' :
				$status = get_post_meta( $the_order->get_id(), 'NS8 Status', true );
				$score = get_post_meta( $the_order->get_id(), 'EQ8 Score', true );
				$url = NS8CSP_Config::container_link('/order-detail?order='.$the_order->get_id());

				switch ($status) {
					case '':
						break;
					case 'approved':
					case 'canceled':
						echo
							'<a title="View analysis" href="'.$url.'">'
							.'<div class="ns8-order-grid-cell ns8-'.$status.'-badge">'
							.' <div class="ns8-order-grid-resolved">'.$this->status_desc( $status ).'</div>'
							.'</div>'
							.'</a>';
						break;
					default:
						echo
							'<a title="View analysis" href="'.$url.'">'
							.'<div class="ns8-order-grid-cell ns8-'.$status.'-badge">'
							.' <div class="ns8-order-grid-score">'.$score.'</div>'
							.' <div class="ns8-order-grid-icon"><span class="ns8-'.$this->status_desc( $status ).'-icon"></div>'
							.'</div>'
							.'</a>';
				}
				break;
		}
	}
}