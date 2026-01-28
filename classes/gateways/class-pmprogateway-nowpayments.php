<?php
/**
 * NOWPayments Gateway for Paid Memberships Pro (Free).
 *
 * @package NOWPayments_PMP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PMPro gateway implementation for NOWPayments.
 */
class PMProGateway_Nowpayments extends PMProGateway {
	/**
	 * Constructor.
	 *
	 * @param object $gateway Gateway object.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Run on WP init.
	 */
	public static function init() {
		add_filter( 'pmpro_gateways', array( 'PMProGateway_Nowpayments', 'pmpro_gateways' ), 10 );

		$gateway = pmpro_getGateway();
		if ( 'nowpayments' === $gateway ) {
			add_filter( 'pmpro_include_billing_address_fields', '__return_false' );
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', array( 'PMProGateway_Nowpayments', 'remove_required_billing_fields' ) );
		}

		add_filter( 'pmpro_payment_options', array( 'PMProGateway_Nowpayments', 'pmpro_payment_options' ) );
		add_action( 'pmpro_payment_option_fields', array( 'PMProGateway_Nowpayments', 'pmpro_payment_option_fields' ), 10, 2 );

		add_action( 'wp_ajax_nopriv_pmpro_nowpayments_webhook', array( 'PMProGateway_Nowpayments', 'webhook_handler' ) );
		add_action( 'wp_ajax_pmpro_nowpayments_webhook', array( 'PMProGateway_Nowpayments', 'webhook_handler' ) );

		add_action( 'admin_init', array( 'PMProGateway_Nowpayments', 'ensure_gateway_ready' ), 1 );
	}

	/**
	 * Make sure this gateway is in the gateways list.
	 *
	 * @param array $gateways Gateways array.
	 * @return array
	 */
	public static function pmpro_gateways( $gateways ) {
		// Gateway registration is handled by nowpayments_pmp_register_gateway() in main plugin file.
		// This method is kept for compatibility but doesn't add gateway here.
		// The main plugin function handles conditional registration based on Pro addon.
		return $gateways;
	}

	/**
	 * Get a description for this gateway.
	 *
	 * @return string
	 */
	public static function get_description_for_gateway_settings() {
		return esc_html__( 'Accept payments with 300+ cryptocurrencies via NOWPayments. Supports one-time and lifetime payments.', 'nowpayments-for-pmp' );
	}

	/**
	 * Check whether or not a gateway supports a specific feature.
	 *
	 * @param string $feature Feature name.
	 * @return bool|string
	 */
	public static function supports( $feature ) {
		$supports = array(
			'subscription_sync'      => false,
			'payment_method_updates' => false,
		);

		$supports = apply_filters( 'nowpayments_pmp_supports', $supports );

		if ( empty( $supports[ $feature ] ) ) {
			return false;
		}

		return $supports[ $feature ];
	}

	/**
	 * Get a list of payment options that this gateway needs/supports.
	 *
	 * @return array
	 */
	public static function getGatewayOptions() {
		$options = array(
			'gateway_environment',
			'nowpayments_live_api_key',
			'nowpayments_sandbox_api_key',
			'nowpayments_live_ipn_key',
			'nowpayments_sandbox_ipn_key',
			'currency',
			'tax_state',
			'tax_rate',
		);

		return $options;
	}

	/**
	 * Remove required billing fields for NOWPayments (offsite gateway).
	 *
	 * @param array $required_fields Required billing fields.
	 * @return array
	 */
	public static function remove_required_billing_fields( $required_fields ) {
		return array();
	}

	/**
	 * Ensure gateway ready status is set in admin area.
	 */
	public static function ensure_gateway_ready() {
		global $pmpro_gateway_ready;

		$gateway = get_option( 'pmpro_gateway' );
		if ( 'nowpayments' === $gateway ) {
			$pmpro_gateway_ready = true;
		}
	}

	/**
	 * Get payment options for settings page.
	 *
	 * @param array $options Options array.
	 * @return array
	 */
	public static function pmpro_payment_options( $options ) {
		$nowpayments_options = array(
			'nowpayments_live_api_key',
			'nowpayments_sandbox_api_key',
			'nowpayments_live_ipn_key',
			'nowpayments_sandbox_ipn_key',
		);

		if (
			defined( 'NOWPAYMENTS_PMP_PRO_VERSION' ) &&
			class_exists( 'NowPayments_PMP_Pro' )
		) {
			$nowpayments_options = array_merge(
				$nowpayments_options,
				array(
					'nowpayments_live_auth_email',
					'nowpayments_live_auth_password',
					'nowpayments_sandbox_auth_email',
					'nowpayments_sandbox_auth_password',
				)
			);
		}

		return array_merge( $nowpayments_options, $options );
	}

	/**
	 * Display payment option fields.
	 *
	 * @param array  $values Current values.
	 * @param string $gateway Gateway name.
	 */
	public static function pmpro_payment_option_fields( $values, $gateway ) {
		if ( 'nowpayments' !== $gateway ) {
			return;
		}
		?>
		<tr class="pmpro_settings_divider gateway gateway_nowpayments">
			<td colspan="2">
				<hr />
				<h2><?php esc_html_e( 'NOWPayments Settings', 'nowpayments-for-pmp' ); ?></h2>
			</td>
		</tr>
		<tr class="gateway gateway_nowpayments">
			<th scope="row" valign="top">
				<label for="nowpayments_live_api_key"><?php esc_html_e( 'Live API Key', 'nowpayments-for-pmp' ); ?></label>
			</th>
			<td>
				<input type="password" id="nowpayments_live_api_key" name="nowpayments_live_api_key" value="<?php echo esc_attr( get_option( 'pmpro_nowpayments_live_api_key' ) ); ?>" class="regular-text code pmpro-admin-secure-key" />
				<p class="description">
					<?php
					/* translators: %s: NOWPayments account settings URL. */
					printf( esc_html__( 'Get your API: %s', 'nowpayments-for-pmp' ), '<a href="' . esc_url( 'https://account.nowpayments.io/store-settings' ) . '" target="_blank">' . esc_url( 'https://account.nowpayments.io/store-settings' ) . '</a>' );
					?>
				</p>
			</td>
		</tr>
		<tr class="gateway gateway_nowpayments">
			<th scope="row" valign="top">
				<label for="nowpayments_live_ipn_key"><?php esc_html_e( 'Live IPN Secret Key', 'nowpayments-for-pmp' ); ?></label>
			</th>
			<td>
				<input type="text" id="nowpayments_live_ipn_key" name="nowpayments_live_ipn_key" value="<?php echo esc_attr( get_option( 'pmpro_nowpayments_live_ipn_key' ) ); ?>" class="regular-text code pmpro-admin-secure-key" />
				<p class="description">
					<?php
					/* translators: %s: NOWPayments account settings URL. */
					printf( esc_html__( 'Get your IPN Secret Key: %s', 'nowpayments-for-pmp' ), '<a href="' . esc_url( 'https://account.nowpayments.io/store-settings' ) . '" target="_blank">' . esc_url( 'https://account.nowpayments.io/store-settings' ) . '</a>' );
					?>
				</p>
			</td>
		</tr>
		<tr class="gateway gateway_nowpayments">
			<th scope="row" valign="top">
				<label for="nowpayments_sandbox_api_key"><?php esc_html_e( 'SandBox API Key', 'nowpayments-for-pmp' ); ?></label>
			</th>
			<td>
				<input type="password" id="nowpayments_sandbox_api_key" name="nowpayments_sandbox_api_key" value="<?php echo esc_attr( get_option( 'pmpro_nowpayments_sandbox_api_key' ) ); ?>" class="regular-text code pmpro-admin-secure-key" />
				<p class="description">
					<?php
					/* translators: %s: NOWPayments sandbox settings URL. */
					printf( esc_html__( 'Get your API: %s', 'nowpayments-for-pmp' ), '<a href="' . esc_url( 'https://account-sandbox.nowpayments.io/store-settings' ) . '" target="_blank">' . esc_url( 'https://account-sandbox.nowpayments.io/store-settings' ) . '</a>' );
					?>
				</p>
			</td>
		</tr>
		<tr class="gateway gateway_nowpayments">
			<th scope="row" valign="top">
				<label for="nowpayments_sandbox_ipn_key"><?php esc_html_e( 'SandBox IPN Secret Key', 'nowpayments-for-pmp' ); ?></label>
			</th>
			<td>
				<input type="text" id="nowpayments_sandbox_ipn_key" name="nowpayments_sandbox_ipn_key" value="<?php echo esc_attr( get_option( 'pmpro_nowpayments_sandbox_ipn_key' ) ); ?>" class="regular-text code pmpro-admin-secure-key" />
				<p class="description">
					<?php
					/* translators: %s: NOWPayments sandbox settings URL. */
					printf( esc_html__( 'Get your IPN Secret Key: %s', 'nowpayments-for-pmp' ), '<a href="' . esc_url( 'https://account-sandbox.nowpayments.io/store-settings' ) . '" target="_blank">' . esc_url( 'https://account-sandbox.nowpayments.io/store-settings' ) . '</a>' );
					?>
				</p>
			</td>
		</tr>
		<tr class="gateway gateway_nowpayments">
			<th scope="row" valign="top">
				<label><?php esc_html_e( 'Webhook URL', 'nowpayments-for-pmp' ); ?></label>
			</th>
			<td>
				<input type="text" value="<?php echo esc_attr( add_query_arg( 'action', 'pmpro_nowpayments_webhook', admin_url( 'admin-ajax.php' ) ) ); ?>" class="regular-text" readonly="readonly" />
			</td>
		</tr>
		<?php
		if ( class_exists( 'NowPayments_PMP_Pro' ) && method_exists( 'NowPayments_PMP_Pro', 'pmpro_payment_option_fields' ) ) {
			NowPayments_PMP_Pro::pmpro_payment_option_fields( $values, $gateway );
		}
		?>
		<?php
	}

	/**
	 * Process payment at checkout.
	 *
	 * @param MemberOrder $order Order object.
	 * @return bool
	 */
	public function process( &$order ) {
		global $pmpro_currency;

		// FIRST CHECK: Recurring level check BEFORE anything else - NO REDIRECT.
		$level = $order->getMembershipLevelAtCheckout();
		if ( pmpro_isLevelRecurring( $level ) ) {
			// Strict check: Pro add-on must be active.
			$pro_active = defined( 'NOWPAYMENTS_PMP_PRO_VERSION' )
				&& class_exists( 'NowPayments_PMP_Subscription' )
				&& function_exists( 'nowpayments_pmp_pro_init' );

			if ( ! $pro_active ) {
				// Delete order if it exists.
				if ( ! empty( $order->id ) ) {
					$order->deleteMe();
				}

				// Set error - NO REDIRECT, just return false.
				$order->error = __( 'Recurring payments are not supported in the free version. Please install and activate NOWPayments for Paid Memberships Pro Pro add-on for recurring payment support.', 'nowpayments-for-pmp' );

				// Show error message on checkout page.
				pmpro_setMessage( $order->error, 'pmpro_error' );

				// Return false - NO REDIRECT to NOWPayments.
				return false;
			}

			// Match legacy flow: save order before redirect for recurring.
			if ( empty( $order->code ) ) {
				$order->code = $order->getRandomCode();
			}
			$order->status                 = 'token';
			$order->payment_transaction_id = 'NOWPayments-' . $order->code;
			$order->saveOrder();

			// Pro add-on is active, let it handle recurring payments.
			$is_live = get_option( 'pmpro_gateway_environment' ) !== 'sandbox';
			$api_key = $is_live ? get_option( 'pmpro_nowpayments_live_api_key' ) : get_option( 'pmpro_nowpayments_sandbox_api_key' );

			if ( empty( $api_key ) ) {
				$order->error = __( 'API key is not configured', 'nowpayments-for-pmp' );
				return false;
			}

			$nowpayments = new NowPayments_PMP_API( $is_live, $api_key );
			$amount      = (float) $order->subtotal;

			$redirect_url = apply_filters(
				'nowpayments_pmp_process_recurring_payment',
				false,
				$order,
				$level,
				$nowpayments,
				$is_live,
				$amount,
				$pmpro_currency
			);

			if ( empty( $redirect_url ) ) {
				if ( empty( $order->error ) ) {
					$order->error = __( 'Failed to process recurring payment. Please check your Pro add-on configuration.', 'nowpayments-for-pmp' );
				}
				pmpro_setMessage( $order->error, 'pmpro_error' );
				return false;
			}

			// Only redirect if Pro addon provided the URL.
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// One-time or lifetime payment - free plugin handles this.
		$is_live = get_option( 'pmpro_gateway_environment' ) !== 'sandbox';
		$api_key = $is_live ? get_option( 'pmpro_nowpayments_live_api_key' ) : get_option( 'pmpro_nowpayments_sandbox_api_key' );

		if ( empty( $api_key ) ) {
			$order->error = __( 'API key is not configured', 'nowpayments-for-pmp' );
			return false;
		}

		// One-time or lifetime payment - free plugin handles this.
		$nowpayments = new NowPayments_PMP_API( $is_live, $api_key );

		$amount = (float) $order->subtotal;
		if ( $amount <= 0 && ! empty( $order->InitialPayment ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$amount = (float) $order->InitialPayment; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		if ( $amount <= 0 && ! empty( $order->total ) ) {
			$amount = (float) $order->total;
		}
		if ( $amount <= 0 && ! empty( $level->initial_payment ) ) {
			$amount = (float) $level->initial_payment;
		}

		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}
		$order->status                 = 'token';
		$order->payment_transaction_id = 'NOWPayments-' . $order->code;
		$order->payment_type           = 'NOWPayments';
		$order->gateway                = 'nowpayments';
		$order->saveOrder();

		// Process one-time payment only.
		$redirect_url = NowPayments_PMP_OneTime::process_payment( $order, $level, $nowpayments, $amount, $pmpro_currency );

		if ( false === $redirect_url ) {
			return false;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Cancel subscription (handled by Pro add-on if available).
	 *
	 * @param PMPro_Subscription $subscription Subscription object.
	 * @return bool
	 */
	public function cancel_subscription( $subscription ) {
		$result = apply_filters( 'nowpayments_pmp_cancel_subscription', null, $subscription );
		return ( null !== $result ) ? (bool) $result : false;
	}

	/**
	 * Webhook handler.
	 */
	public static function webhook_handler() {
		$request_json = file_get_contents( 'php://input' );
		$request_data = json_decode( $request_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			status_header( 400 );
			wp_die( 'Invalid JSON' );
		}

		$is_live    = get_option( 'pmpro_gateway_environment' ) !== 'sandbox';
		$ipn_secret = $is_live ? get_option( 'pmpro_nowpayments_live_ipn_key' ) : get_option( 'pmpro_nowpayments_sandbox_ipn_key' );

		if ( ! empty( $ipn_secret ) && ! self::verify_ipn_signature( $request_json, $request_data, $ipn_secret ) ) {
			status_header( 401 );
			wp_die( 'Invalid signature' );
		}

		self::process_webhook( $request_data );

		status_header( 200 );
		wp_die( 'OK' );
	}

	/**
	 * Verify IPN signature.
	 *
	 * @param string $request_json Raw JSON.
	 * @param array  $request_data Decoded data.
	 * @param string $ipn_secret IPN secret key.
	 * @return bool
	 */
	protected static function verify_ipn_signature( $request_json, $request_data, $ipn_secret ) {
		if ( empty( $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ) ) {
			return false;
		}

		$received_hmac   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ) );
		$sorted_data     = self::sort_array_recursive( $request_data );
		$sorted_json     = wp_json_encode( $sorted_data, JSON_UNESCAPED_SLASHES );
		$calculated_hmac = hash_hmac( 'sha512', $sorted_json, trim( $ipn_secret ) );

		return hash_equals( $calculated_hmac, $received_hmac );
	}

	/**
	 * Sort array recursively.
	 *
	 * @param mixed $data Data to sort.
	 * @return mixed
	 */
	protected static function sort_array_recursive( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		ksort( $data );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::sort_array_recursive( $value );
			}
		}

		return $data;
	}

	/**
	 * Process webhook data.
	 *
	 * @param array $data Webhook data.
	 */
	protected static function process_webhook( $data ) {
		$payment_status = isset( $data['payment_status'] ) ? $data['payment_status'] : '';
		$order_id       = isset( $data['order_id'] ) ? intval( $data['order_id'] ) : 0;

		if ( empty( $order_id ) ) {
			status_header( 400 );
			wp_die( 'Missing order ID' );
		}

		$order = new MemberOrder();
		$order->getMemberOrderByCode( $order_id );

		if ( empty( $order->id ) ) {
			$order = new MemberOrder();
			$order->getMemberOrderByPaymentTransactionID( 'NOWPayments-' . $order_id );
		}

		if ( empty( $order->id ) ) {
			status_header( 404 );
			wp_die( 'Order not found' );
		}

		do_action( 'nowpayments_pmp_webhook_order', $order, $data );

		switch ( strtoupper( $payment_status ) ) {
			case 'CONFIRMED':
			case 'COMPLETED':
				self::record_payment( $order, $data );
				break;
			case 'FAILED':
			case 'EXPIRED':
				self::record_payment_failure( $order, $data );
				break;
			case 'REFUNDED':
				self::process_refund( $order, $data );
				break;
		}
	}

	/**
	 * Record payment.
	 *
	 * @param MemberOrder $order Order object.
	 * @param array       $data Webhook data.
	 */
	protected static function record_payment( $order, $data ) {
		$order->status = 'success';

		if ( ! empty( $data['payment_id'] ) ) {
			$order->payment_transaction_id = sanitize_text_field( $data['payment_id'] );
		} else {
			$order->payment_transaction_id = 'NOWPayments-' . $order->code;
		}

		$order->saveOrder();

		pmpro_changeMembershipLevel( $order->membership_id, $order->user_id );
		do_action( 'pmpro_after_checkout', $order->user_id, $order );
		do_action( 'nowpayments_pmp_payment_confirmed', $order, $data );
	}

	/**
	 * Record payment failure.
	 *
	 * @param MemberOrder $order Order object.
	 * @param array       $data Webhook data.
	 */
	protected static function record_payment_failure( $order, $data ) {
		$order->status = 'error';
		$order->error  = isset( $data['message'] ) ? $data['message'] : __( 'Payment failed', 'nowpayments-for-pmp' );
		$order->saveOrder();
	}

	/**
	 * Process refund.
	 *
	 * @param MemberOrder $order Order object.
	 * @param array       $data Webhook data.
	 */
	protected static function process_refund( $order, $data ) {
		$order->status = 'refunded';
		$order->saveOrder();
	}
}

if ( ! class_exists( 'PMProGateway_nowpayments' ) ) {
	class_alias( 'PMProGateway_Nowpayments', 'PMProGateway_nowpayments' );
}
