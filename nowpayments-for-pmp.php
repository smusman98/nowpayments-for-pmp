<?php
/**
 * Plugin Name: NOWPayments for Paid Memberships Pro
 * Plugin URI: https://coderpress.co/products/nowpayments-for-paid-memberships-pro/
 * Author: CoderPress
 * Description: Allow Paid Memberships Pro users to checkout with 300+ cryptocurrencies via NOWPayments. Supports one-time and lifetime payments.
 * Version: 1.0.0
 * Author URI: https://www.coderpress.co/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nowpayments-for-pmp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package NOWPayments_PMP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOWPAYMENTS_PMP_VERSION', '1.0.0' );
define( 'NOWPAYMENTS_PMP_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOWPAYMENTS_PMP_URL', plugin_dir_url( __FILE__ ) );
define( 'NOWPAYMENTS_PMP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Paid Memberships Pro is active.
 *
 * @return bool
 */
function nowpayments_pmp_check_pmpro() {
	if ( ! class_exists( 'PMProGateway' ) ) {
		add_action( 'admin_notices', 'nowpayments_pmp_missing_notice' );
		return false;
	}

	return true;
}

/**
 * Show notice if PMPro is not active.
 */
function nowpayments_pmp_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'NOWPayments for Paid Memberships Pro requires Paid Memberships Pro to be installed and active.', 'nowpayments-for-pmp' ); ?></p>
	</div>
	<?php
}

/**
 * Ensure NOWPayments appears in the gateway list.
 * Hide gateway for recurring levels if Pro add-on is not active.
 *
 * @param array $gateways Gateway list.
 * @return array
 */
function nowpayments_pmp_register_gateway( $gateways ) {
	if ( ! is_array( $gateways ) ) {
		$gateways = array();
	}

	// Check if we're on checkout page with a recurring level.
	global $pmpro_level;
	$is_recurring = false;

	// Get level from various sources.
	if ( ! empty( $pmpro_level ) ) {
		$is_recurring = pmpro_isLevelRecurring( $pmpro_level );
	} elseif ( ! empty( $_REQUEST['level'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$level_id = intval( $_REQUEST['level'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$level    = pmpro_getLevel( $level_id );
		if ( ! empty( $level ) ) {
			$is_recurring = pmpro_isLevelRecurring( $level );
		}
	} elseif ( is_admin() && ! empty( $_REQUEST['page'] ) && 'pmpro-paymentsettings' === $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// On payment settings page, always show gateway.
		$is_recurring = false;
	}

	// If recurring level and Pro add-on is not active, don't register gateway.
	if ( $is_recurring ) {
		$pro_active = defined( 'NOWPAYMENTS_PMP_PRO_VERSION' )
			&& class_exists( 'NowPayments_PMP_Subscription' )
			&& function_exists( 'nowpayments_pmp_pro_init' );

		if ( ! $pro_active ) {
			// Remove gateway from list if it exists.
			unset( $gateways['nowpayments'] );
			return $gateways;
		}
	}

	// Register gateway for one-time/lifetime payments or if Pro is active.
	if ( empty( $gateways['nowpayments'] ) ) {
		$gateways['nowpayments'] = __( 'NOWPayments', 'nowpayments-for-pmp' );
	}

	return $gateways;
}

// Register gateway filter immediately - runs before plugin init.
// Use priority 1 to ensure it runs before other plugins.
add_filter( 'pmpro_gateways', 'nowpayments_pmp_register_gateway', 1 );

/**
 * Initialize the plugin.
 */
function nowpayments_pmp_init() {
	static $initialized = false;
	if ( $initialized ) {
		return;
	}

	if ( ! nowpayments_pmp_check_pmpro() ) {
		return;
	}

	require_once NOWPAYMENTS_PMP_DIR . 'includes/class-nowpayments-pmp-api.php';
	require_once NOWPAYMENTS_PMP_DIR . 'classes/gateways/class-nowpayments-pmp-onetime.php';
	require_once NOWPAYMENTS_PMP_DIR . 'classes/gateways/class-pmprogateway-nowpayments.php';

	if ( class_exists( 'PMProGateway_Nowpayments' ) ) {
		PMProGateway_Nowpayments::init();
	}

	$initialized = true;
}

add_action( 'plugins_loaded', 'nowpayments_pmp_init', 10 );
