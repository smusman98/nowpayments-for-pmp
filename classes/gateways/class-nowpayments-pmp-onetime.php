<?php
/**
 * NOWPayments One-Time Payment Handler.
 *
 * @package NOWPayments_PMP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NowPayments_PMP_OneTime Class.
 */
class NowPayments_PMP_OneTime {
	/**
	 * Process one-time payment.
	 *
	 * @param MemberOrder         $order Order object.
	 * @param object              $level Level object.
	 * @param NowPayments_PMP_API $nowpayments API instance.
	 * @param float               $amount Payment amount.
	 * @param string              $currency Currency code.
	 * @return bool|string Redirect URL on success, false on failure.
	 */
	public static function process_payment( $order, $level, $nowpayments, $amount, $currency ) {
		if ( $amount <= 0 ) {
			$order->error = __( 'Payment amount must be greater than zero.', 'nowpayments-for-pmp' );
			if ( function_exists( 'pmpro_setMessage' ) ) {
				pmpro_setMessage( $order->error, 'pmpro_error' );
			}
			return false;
		}

		$parameters = array(
			'dataSource'      => 'paid-memberships-pro',
			'ipnURL'          => self::get_webhook_url(),
			'paymentCurrency' => $currency,
			'successURL'      => self::get_success_url( $order ),
			'cancelURL'       => self::get_cancel_url( $order ),
			'orderID'         => $order->id,
			'customerName'    => trim( $order->FirstName . ' ' . $order->LastName ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'customerEmail'   => $order->Email, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'paymentAmount'   => number_format( $amount, 8, '.', '' ),
			'productName'     => $level->name,
		);

		$parameters = apply_filters( 'nowpayments_pmp_onetime_parameters', $parameters, $order, $level );

		$redirect_url = $nowpayments->off_page_checkout( $parameters );

		if ( empty( $redirect_url ) ) {
			$order->error = __( 'Failed to create payment', 'nowpayments-for-pmp' );
			return false;
		}

		update_pmpro_membership_order_meta( $order->id, 'nowpayments_redirect_url', $redirect_url );
		update_pmpro_membership_order_meta( $order->id, 'nowpayments_payment_parameters', $parameters );

		return $redirect_url;
	}

	/**
	 * Get webhook URL.
	 *
	 * @return string
	 */
	protected static function get_webhook_url() {
		return add_query_arg( 'action', 'pmpro_nowpayments_webhook', admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Get success URL.
	 *
	 * @param MemberOrder $order Order object.
	 * @return string
	 */
	protected static function get_success_url( $order ) {
		return add_query_arg(
			array(
				'pmpro_order' => $order->code,
				'pmpro_level' => $order->membership_id,
			),
			pmpro_url( 'checkout' )
		);
	}

	/**
	 * Get cancel URL.
	 *
	 * @param MemberOrder $order Order object (unused).
	 * @return string
	 */
	protected static function get_cancel_url( $order ) {
		unset( $order );
		return pmpro_url( 'levels' );
	}
}
