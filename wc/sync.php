<?php

/**
 * NS8CSP class to sync data between site and NS8.
 */
class NS8CSP_Sync extends NS8CSP_Background_Process {

	protected $action = 'ns8csp_sync';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $task_item Queue item to iterate over
	 *
	 * @return mixed  false to remove item, $item to requeue
	 */
	protected function task( $task_item ) {

		try {
			NS8CSP_Logger::info('in task', $task_item );

			if ( !isset( $task_item ) || !isset( $task_item[ 'order_id' ] ) ) {
				return false;
			}

			switch ( $task_item[ 'type' ] ) {
				case 'sync_order':
					return self::sync_order( $task_item );
				case 'sync_status':
					return self::sync_status( $task_item );
				default:
					return false;
			}
		} catch ( Exception $e ) {
			NS8CSP_Logger::error('sync', $e->getMessage() );
			return self::maybe_fail( $task_item );
		}
	}

	/**
	 * @param $task_item
	 *
	 * @return bool
	 */
	static function sync_order( $task_item ) {
		try {
			NS8CSP_Logger::info('sync_order-start', $task_item[ 'order_id' ] );

			$order_id = $task_item[ 'order_id' ];
			$order = new WC_Order( $task_item[ 'order_id' ] );

			if ( !isset($order) ) {
				NS8CSP_Logger::info('sync_order-no-order', $task_item[ 'order_id' ] );

				return self::maybe_fail( $task_item );
			}

			$status = $order->get_status();

			//  only sync if there is no NS8 status or the order status has changed
            if ( !isset($task_item[ 'force' ]) || $task_item[ 'force' ] != '1' ) {

                if ( strlen( get_post_meta( $order_id, 'NS8 Status', true ) ) > 0 ) {
                    if ( $status == get_post_meta( $order_id, '_ns8_synced_status', true ) ) {
                        NS8CSP_Logger::info('sync_order-already synced', 'order: '.$task_item[ 'order_id' ].', status: '.$status);

                        return false;
                    }
                }
            }

			//  only sync orders with certain statuses
			switch ( $status ) {
				case 'processing':
				case 'completed':
				case 'on-hold':
				case 'failed':
				case 'cancelled':
				case 'refunded':
					$params = self::to_call_structure( $task_item );

					if ( $params == false ) {
						NS8CSP_Logger::info('sync_order-bad-call', $task_item[ 'order_id' ] );

						return self::maybe_fail( $task_item );
					}

					$options = array(
						'timeout' => 30
					);

					$result = NS8CSP_API::post( '/protect/woo/orders', $params, $options );

					$statusSet = strlen( get_post_meta( $order_id, 'NS8 Status', true ) ) > 0 ;

					if ( $result['code'] == 409 && $statusSet ) {    //  order already exists - remove from queue
						update_post_meta( $order_id, '_ns8_synced_status', $order->get_status() );

						if ( isset( $result[ 'data' ] ) ) {
							$data = $result[ 'data' ];

							if ( isset( $data->data ) ) {
								if ( isset( $data->data->NS8Status ) ) {
								    update_post_meta( $order_id, 'NS8 Status', $data->data->NS8Status );
								}

								if ( isset( $data->data->score ) ) {
								    update_post_meta( $order_id, 'EQ8 Score', $data->data->score );
								}
							}
						}
						return false;
					} else if ( $result['code'] != 200 && $result['code'] != 409 ) {
						NS8CSP_Logger::info('sync_order-fail '.$result['code'], $task_item[ 'order_id' ] );

						return self::maybe_fail( $task_item );
					} else {
						if ( !isset( $result['data'] ) ) {
							NS8CSP_Logger::info('sync_order-fail-no-data', $task_item[ 'order_id' ] );
							return self::maybe_fail( $task_item );
						} else {
							$data = $result['data'];
							update_post_meta( $order_id, '_ns8_synced_status', $order->get_status() );
							update_post_meta( $order_id, 'NS8 Status', $data->NS8Status );
							update_post_meta( $order_id, 'EQ8 Score', $data->score );

							if ( isset( $data->riskInfo ) && isset( $data->riskInfo->setStatus )) {

								if ( $data->riskInfo->setStatus == 'canceled' ) {
									$data->riskInfo->setStatus = 'cancelled';
								}

								if ( $order->get_status() != $data->riskInfo->setStatus ) {
									$order->update_status( $data->riskInfo->setStatus );
								}
							}
							NS8CSP_Logger::info('sync_order-success', $task_item[ 'order_id' ] );

							return false;
						}
					}
					break;
				default:
					NS8CSP_Logger::info('sync_order-skipped-status-'.$status, $task_item[ 'order_id' ] );

					return false;
			}
		} catch( Exception $e ) {
			NS8CSP_Logger::error('sync order', $e->getMessage() );
			return self::maybe_fail( $task_item );
		}
	}

	/**
	 * Sync the order status between the shop and NS8.
	 *
	 * @param $task_item    The task item containing the order
	 *
	 * @return bool         The maybe fail result
	 */
	static function sync_status( $task_item ) {
		try {
			$order_id = $task_item[ 'order_id' ];
			$order = new WC_Order( $order_id );

			if ( !isset( $order ) ) {
				return self::maybe_fail( $task_item );
			}

			$params = array(
				'order_id' => $order_id,
				'NS8Status' =>  get_post_meta( $order_id, 'NS8 Status', true ),
				'status' => $order->get_status(),
				'accessToken' => NS8CSP_Config::get_access_token()
			);

			$result = NS8CSP_API::post( '/protect/woo/orders/status', $params );

			if ( !isset( $result ) || !isset( $result['code'] ) || $result['code'] != 200 ) {
				return self::maybe_fail( $task_item );
			} else {
				return false;
			}
		} catch( Exception $e ) {
			NS8CSP_Logger::error('sync', $e->getMessage() );
			return self::maybe_fail( $task_item );
		}
	}

	/**
	 * Return false if the task has too many failures, otherwise return the task.
	 *
	 * @param $task_item    The task to test
	 *
	 * @return bool|array
	 */
	static function maybe_fail( $task_item ) {
		$order_id = $task_item[ 'order_id' ];

		$failures = isset( $task_item[ 'failures' ] ) ? $task_item[ 'failures' ] : 0;

		if ( (int)$failures > 5 ) {
			NS8CSP_Logger::error('sync', 'unable to get risk info', $order_id );
			return false;
		}

		$task_item[ 'failures' ] = (int)$failures + 1;

		return $task_item;
	}

	/**
	 * Return the REST call data for an order, or false if the order does not exist.
	 *
	 * @param $task_item    The task containing the order
	 *
	 * @return array|bool
	 * @throws Exception
	 */
	static function to_call_structure( $task_item ) {

		$order = new WC_Order( $task_item[ 'order_id' ] );

		if ( !$order ) {
			return false;
		}

		$customer = new WC_Customer( $order->get_customer_id() );

		if ( isset( $customer ) ) {
			$customer = $customer->get_data();
		}

		$order_items = $order->get_items();
		$items = [];

		foreach ( $order_items as $order_item ) {

			$item_data = $order_item->get_data();

			if ( $order_item->get_product_id() != false ) {
				$product = wc_get_product($order_item->get_product_id());


				if ( $product != false ) {

					$sku = $product->get_sku();

					if ( $sku != false ) {
						$item_data[ 'sku' ] = $sku;
					}

					$price = $product->get_price();

					if ( $price != false ) {
						$item_data[ 'price' ] = $price;
					}
				}
			}

			array_push( $items, $item_data );
		}

		return [
			"userId" => $task_item[ 'user_id' ],
			"accessToken" => NS8CSP_Config::get_access_token(),
			"ip" => $task_item[ 'ip' ] ,
			"ua" => $task_item[ 'ua' ],
			"language" => $task_item[ 'language' ],
			"order" => $order->get_data(),
			"customer" => $customer,
			"items" => $items,
			"phpVersion" => PHP_VERSION,
			"phpOS" => PHP_OS,
			"wooVersion" => NS8CSP_Config::wc_version(),
			"moduleVersion" => NS8CSP_VERSION,
			"shop" => NS8CSP_Config::get_shop()
		];
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
	}

}