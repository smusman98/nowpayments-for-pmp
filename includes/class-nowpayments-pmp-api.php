<?php
/**
 * NOWPayments API Class for Paid Memberships Pro (Free).
 *
 * @package NOWPayments_PMP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NowPayments_PMP_API Class.
 */
class NowPayments_PMP_API {
	/**
	 * Is Live mode.
	 *
	 * @var bool
	 */
	private $is_live;

	/**
	 * Payment URL base.
	 *
	 * @var string
	 */
	private $payment_url;

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param bool   $is_live Is live mode.
	 * @param string $api_key API key.
	 */
	public function __construct( $is_live = true, $api_key = '' ) {
		$this->is_live = (bool) $is_live;
		$this->api_key = $api_key;

		$this->payment_url = $this->is_live ? 'https://nowpayments.io' : 'https://sandbox.nowpayments.io';
	}

	/**
	 * Create off-page checkout URL.
	 *
	 * @param array $parameters Checkout parameters.
	 * @return string
	 */
	public function off_page_checkout( $parameters = array() ) {
		$parameters['apiKey'] = $this->api_key;
		$encoded_parameters   = rawurlencode( wp_json_encode( $parameters ) );

		return $this->payment_url . '/payment/?data=' . $encoded_parameters;
	}
}
