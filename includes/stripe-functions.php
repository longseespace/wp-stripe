<?php

define('INAIR_2D_PRICE', 14999);
define('INAIR_3D_PRICE', 17999);
define('SHIPPING_PRICE', 3000);

/**
 * Display the Stripe Form in a Thickbox Pop-up
 *
 * @param $atts array Undefined, have not found any use yet
 * @return string Form Pop-up Link (wrapped in <a></a>)
 *
 * @since 1.3
 *
 */
function wp_stripe_shortcode( $atts ) {

	$options = get_option( 'wp_stripe_options' );
	$url     = add_query_arg( array( 'wp-stripe-iframe' => 'true', 'keepThis' => 'true', 'TB_iframe' => 'true', 'height' => 580, 'width' => 400 ), home_url() );
	$count   = 1;

	if ( isset( $options['stripe_modal_ssl'] ) && $options['stripe_modal_ssl'] === 'Yes' ) {
		$url = str_replace( 'http://', 'https://', $url, $count );
	}

	extract( shortcode_atts(array(
		'cards' => 'true'
	), $atts ) );

	if ( $cards === 'true' )  {
		$payments = '<div id="wp-stripe-types"></div>';
	}

	return '<a class="thickbox" id="wp-stripe-modal-button" title="' . esc_attr( $options['stripe_header'] ) . '" href="' . esc_url( $url ) . '"><span>' . esc_html( $options['stripe_header'] ) . '</span></a>' . $payments;

}
add_shortcode( 'wp-stripe', 'wp_stripe_shortcode' );

/**
 * Display Legacy Stripe form in-line
 *
 * @param $atts array Undefined, have not found any use yet
 * @return string Form / DOM Content
 *
 * @since 1.3
 *
 */
function wp_stripe_shortcode_legacy( $atts ){
	return wp_stripe_form();
}
add_shortcode( 'wp-legacy-stripe', 'wp_stripe_shortcode_legacy' );

/**
 * Create Charge using Stripe PHP Library
 *
 * @param $amount int transaction amount in cents (i.e. $1 = '100')
 * @param $card string
 * @param $description string
 * @return array
 *
 * @since 1.0
 *
 */
function wp_stripe_charge($amount, $card, $name, $description) {

	$options = get_option( 'wp_stripe_options' );

	$currency = $options['stripe_currency'];

	/*
	 * Card - Token from stripe.js is provided (not individual card elements)
	 */
	$charge = array(
		'card'     => $card,
		'amount'   => $amount,
		'currency' => $currency,
	);

	if ( $description ) {
		$charge['description'] = $description;
	}

	$response = Stripe_Charge::create( $charge );

	return $response;

}

/**
 * Charge
 *
 * @since 1.0
 *
 */
function inair_charge($amount, $card, $name, $email, $model, $quantity, $metadata) {
	// model name
	if ($model == '2d') {
		$modelName = "InAiR 2D";
	} else {
		$modelName = "InAiR 3D";
	}

	// create customer
	$customer = Stripe_Customer::create(array(
	  "card" => $card,
	  "email" => $email,
	  "description" => $metadata['shipping'] ? $metadata['shipping'] : $name
	));

	// create shipping invoice item
	$shippingInvoiceItem = Stripe_InvoiceItem::create(array(
		'customer' => $customer->id,
		'amount' => SHIPPING_PRICE,
		'currency' => 'usd',
		'description' => 'Shipping & Handling',
		'metadata' => $metadata
	));

	// create device invoice item
	$deviceInvoiceItem = Stripe_InvoiceItem::create(array(
		'customer' => $customer->id,
		'amount' => $model == '2d' ? $quantity * INAIR_2D_PRICE : $quantity * INAIR_3D_PRICE,
		'currency' => 'usd',
		'description' => $quantity.' x '.$modelName,
		'metadata' => $metadata
	));

	// now create invoice
	$invoice = Stripe_Invoice::create(array(
		'customer' => $customer->id,
		'description' => $quantity.' x '.$modelName,
		'metadata' => $metadata
	));
	
	$invoice->pay();

	return $invoice;
}

/**
 * 3-step function to Process & Save Transaction
 *
 * 1) Capture POST
 * 2) Create Charge using wp_stripe_charge()
 * 3) Store Transaction in Custom Post Type
 *
 * @since 1.0
 *
 */
function wp_stripe_charge_initiate() {

	// Security Check
	if ( ! wp_verify_nonce( $_POST['nonce'], 'wp-stripe-nonce' ) ) {
		wp_die( __( 'Nonce verification failed!', 'wp-stripe' ) );
	}

	// Define/Extract Variables
	$public = sanitize_text_field( $_POST['wp_stripe_public'] );
	$name   = sanitize_text_field( $_POST['wp_stripe_name'] );
	$email  = sanitize_email( $_POST['wp_stripe_email'] );
	$quantity = (int) $_POST['quantity'];
	$model = sanitize_text_field($_POST['model']);

	$shippingAddr = array(
		'shipping_address_apt' => sanitize_text_field($_POST['shipping_address_apt']),
		'shipping_address_city' => sanitize_text_field($_POST['shipping_address_city']),
		'shipping_address_country' => sanitize_text_field($_POST['shipping_address_country']),
		'shipping_address_country_code' => sanitize_text_field($_POST['shipping_address_country_code']),
		'shipping_address_line1' => sanitize_text_field($_POST['shipping_address_line1']),
		'shipping_address_state' => sanitize_text_field($_POST['shipping_address_state']),
		'shipping_address_zip' => sanitize_text_field($_POST['shipping_address_zip']),
		'shipping_name' => sanitize_text_field($_POST['shipping_name'])
	);

	$shippingAddr['summary'] = $shippingAddr['shipping_name'].', '
		.$shippingAddr['shipping_address_line1'].' '
		.$shippingAddr['shipping_address_apt'].','
		.$shippingAddr['shipping_address_city'].' '
		.$shippingAddr['shipping_address_state'].' '
		.$shippingAddr['shipping_address_zip'].', '
		.$shippingAddr['shipping_address_country'];

	$metadata = array(
		'shipping' => $shippingAddr['summary']
	);

	// Strip any comments from the amount
	$amount = str_replace( ',', '', sanitize_text_field( $_POST['wp_stripe_amount'] ) );
	$amount = str_replace( '$', '', $amount ) * 100;

	$card = sanitize_text_field( $_POST['stripeToken'] );

	$widget_comment = '';

	if ( empty( $_POST['wp_stripe_comment'] ) ) {
		$stripe_comment = __( 'E-mail: ', 'wp-stipe') . sanitize_text_field( $_POST['wp_stripe_email'] ) . ' - ' . __( 'This transaction has no additional details', 'wp-stripe' );


	} else {
		$stripe_comment = __( 'E-mail: ', 'wp-stipe' ) . sanitize_text_field( $_POST['wp_stripe_email'] ) . ' - ' . sanitize_text_field( $_POST['wp_stripe_comment'] );
		$widget_comment = sanitize_text_field( $_POST['wp_stripe_comment'] );
	}

	// Create invoice
	try {
		$invoice = inair_charge( $amount, $card, $name, $email, $model, $quantity, $metadata );
		$result = array('success' => true, 'ref' => $invoice->id);
	} catch ( Exception $e ) {
		$result = array('success' => false, 'error' => $e->getMessage(), 'body' => $e->getJsonBody());
		do_action( 'wp_stripe_post_fail_charge', $email, $e->getMessage() );
	}

	// Return Results to JS
	header( 'Content-Type: application/json' );
	echo json_encode( $result );
	exit;

}
add_action('wp_ajax_wp_stripe_charge_initiate', 'wp_stripe_charge_initiate');
add_action('wp_ajax_nopriv_wp_stripe_charge_initiate', 'wp_stripe_charge_initiate');
