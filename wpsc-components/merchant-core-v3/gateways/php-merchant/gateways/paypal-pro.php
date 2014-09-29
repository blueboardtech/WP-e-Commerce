<?php
require_once( 'paypal.php' );
require_once( 'paypal-pro-response.php' );

class PHP_Merchant_Paypal_Pro extends PHP_Merchant_Paypal
{
	const API_VERSION = '104.0';
	const SANDBOX_URL = 'https://api-3t.sandbox.paypal.com/nvp';
	const LIVE_URL = 'https://api-3t.paypal.com/nvp';

	public function __construct( $options = array() ) {
		parent::__construct( $options );
	}

	/**
	 * Add the payment details to the PayPal request
	 *
	 * @param array $action
	 * @return array
	 * @since 3.9
	 */
	protected function add_payment( $action ) {

		// Total Payment details
		$request = array(
			'amount'           => $this->format( $this->options['amount'] ),
			'currency_code'  => $this->options['currency'],
			'paymentaction' => $action,
		);

		foreach ( array( 'subtotal', 'shipping', 'handling', 'tax' ) as $key ) {
			if ( isset( $this->options[$key] ) ) {
				$this->options[$key] = $this->format( $this->options[$key] );
			}
		}

		$request += phpme_map( $this->options, array(
			'subtotal'     => 'subtotal',
			'shipping' => 'shipping',
			'handling' => 'handling',
			'tax'      => 'tax',
			'item_name'        => 'description',
			'invoice'      => 'invoice',
			'notify_url'   => 'notify_url',
		) );

		$subtotal = 0;

		// Shopping Cart details
		$i = 0;
		foreach ( $this->options['items'] as $item ) {
			// Options Fields
			$item_optionals = array(
				'tax'         => "tax{$i}",	
			);

			// Format Amount Field 
			$item['amount'] = $this->format( $item['amount'] );

			// Required Fields
			$request += phpme_map( $item, array(
				"item_name{$i}" => 'name',
				"amount{$i}"  => 'amount',
				"quantity{$i}"  => 'quantity',
			) );

			foreach ( $item_optionals as $key => $param ) {
				if ( ! empty( $this->options['items'][$i][$key] ) )
					if ( $key == 'tax' ) {
						$request[$param] = $this->format( $this->options['items'][$i][$key] );
					} else {
						$request[$param] = $this->options['items'][$i][$key];
					}
			}

			$i ++;
		}

		return $request;
	}
	/**
	 * Add a shipping address to the PayPal request
	 *
	 * @return array
	 * @since 3.9
	 */
	protected function add_address() {
		$map = array(
			'name'     => 'first_name',
			'street'   => 'address1',
			'street2'  => 'address2',
			'city'     => 'city',
			'state'    => 'state',
			'zip'      => 'zip',
			'country'  => 'country',
			'phone'    => 'night_phone_1',
		);

		$request = array();

		foreach ( $map as $key => $param ) {
			if ( ! empty( $this->options['shipping_address'][$key] ) ) {
				$request[$param] = $this->options['shipping_address'][$key];
			}
		}

		return $request;
	}

	/**
	 * Gateway implementation for BMCreateButton
	 *
	 * @param array $options 
	 * @return PHP_Merchant_Paypal_Pro_Response
	 * @since 3.9
	 */
	public function createButton( $options = array() ) {
		$this->options = array_merge( $this->options, $options );
		$this->requires( array( 'amount', 'vendor', 'paymentaction', 'template' ) );

		$request = $this->build_button_vars( $this->options );
		$request['BUTTONCODE'] = 'TOKEN';
		$request['BUTTONTYPE'] = 'PAYMENT';
		$request['cmd'] = '_cart';

		/*
		$post = array(
			'BUTTONCODE' => 'TOKEN',
			'BUTTONTYPE' => 'PAYMENT',	
			'L_BUTTONVAR3' => 'vendor=wpp@omarabid.com',
			'L_BUTTONVAR0' => 'subtotal=11',
			'L_BUTTONVAR1' => 'paymentaction=sale',
			'L_BUTTONVAR2' => 'template=templateD',
			'L_BUTTONVAR4' => 'item_name=Wireless%20Mouse',
			'L_BUTTONVAR5' => 'amount=11',
		);
		 */

		//st_log( $request );
		$response_str = $this->commit( 'BMCreateButton', $request );
		return new PHP_Merchant_Paypal_Pro_Response( $response_str );
	}

	/**
	 * Build the request array
	 * 
	 * @param array $options
	 * @return array
	 * @since 3.9
	 */
	protected function build_button_vars( $options = array(), $action = 'sale' ) {
		$request = array();

		if ( isset( $this->options['return_url'] ) ) {
			$request['return'] = $this->options['return_url'];
		}

		if ( isset( $this->options['cancel_url'] ) ) {
			$request['cancel_return'] = $this->options['cancel_url'];
		}

		if ( isset( $this->options['notify_url'] ) ) {
			$request['notify_url'] = $this->options['notify_url'];
		}

		if ( $action != false ) {
			$request += $this->add_payment( $action );
			$request['display'] = '1';	
		}

		if ( ! empty( $this->options['shipping_address'] ) ) {
			$request += $this->add_address();
		}

		if ( isset( $this->options['no_shipping'] ) ) {
			$request['no_shipping'] = '1';
		}

		// Common Fields
		$request += phpme_map( $this->options, array(
			'amount'          => 'amount',			
			'subtotal' => 'subtotal',
			'tax' => 'tax',
			'shipping' => 'shipping',
			'paymentaction' => 'paymentaction',
			'template' => 'template',
			'address_override' => 'address_override',
			'vendor'      => 'merchant_email',
			'invoice'	   => 'invoice',
			'currency' => 'currency',
		) );	

		$request = $this->add_sub( 'L_BUTTONVAR', $request );

		return $request;
	}

	/**
	 * Add a subline for the HTML variables array
	 *
	 * @param string $sub
	 * @param array $array
	 * @return array
	 * @since 3.9
	 */
	private function add_sub( $sub, $arr ) {	
		$request = array();

		$i = 0;
		foreach( $arr as $key=>$value) {
			$request[$sub . $i] = $key . '=' . $value;
			$i++;
		}

		return $request;
	}

	/**
	 * Gateway implementation for "purchase" operation 
	 *
	 * @param array $options
	 * @return PHP_Merchant_Paypal_Pro_Response
	 * @since 3.9
	 */
	public function purchase( $options = array(), $action = 'Sale' ) {

	}

	/**
	 * Gateway implementation for "authorize" operation 
	 *
	 * @param array $options
	 * @return PHP_Merchant_Paypal_Pro_Response
	 * @since 3.9
	 */
	public function authorize( $options = array() ) {

	}

	/**
	 * Gateway implementation for "capture" operation 
	 *
	 * @param array $options
	 * @return PHP_Merchant_Paypal_Pro_Response
	 * @since 3.9
	 */
	public function capture( $options = array() ) {

	}

	/**
	 * Gateway implementation for "void" operation
	 *
	 * @param array $options
	 * @return PHP_Merchant_Paypal_Pro_Response
	 * @since 3.9
	 */
	public function void( $options = array() ) {

}

/**
 * Gateway implementation for RefundTransaction
 *
 * @param array $options
 * @return PHP_Merchant_Paypal_Pro_Response
 * @since 3.9
 */
public function credit( $options = array() ) {

}
}
