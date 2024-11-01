<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_PayPal_Plus_Brazil_IPN {

	private $event;
	private $order_id;
	private $payment_id;
	private $sale_id;
	private $gateway;

	public function __construct( $event, $gateway ) {
		$this->gateway = $gateway;
		$this->event   = $event;
		$this->init();
	}

	/**
	 * Search in database order ID.
	 * @return int
	 */
	private function init() {
		global $wpdb;

		// Define payment id
		$this->payment_id = $this->event['resource']['parent_payment'];
		// Define sale id
		$this->sale_id = isset( $this->event['resource']['sale_id'] ) ? $this->event['resource']['sale_id'] : $this->event['resource']['id'];
		// Define order id
		$this->order_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc_paypal_plus_payment_sale_id' AND meta_value = %s", $this->sale_id ) );
	}

	public function get_order_id() {
		return $this->order_id;
	}

	public function get_payment_id() {
		return $this->get_payment_id();
	}

	public function get_sale_id() {
		return $this->sale_id;
	}

	public function get_order() {
		$order_id = $this->get_order_id();

		return wc_get_order( $order_id );
	}

	/**
	 * When payment is marked as completed.
	 */
	public function ipn_process_payment_sale_completed() {
		$order = $this->get_order();

		// Check if order exists.
		if ( ! $order ) {
			return;
		}

		// Check if the current status isn't processing or completed.
		if ( ! in_array( $order->get_status(), array( 'processing', 'completed', 'refunded', 'cancelled' ), true ) ) {
			$order->add_order_note( __( 'PayPal Plus: Transaction paid.', 'woo-paypal-plus-brazil' ) );
			$order->payment_complete();
		}
	}

	/**
	 * When payment is denied.
	 */
	public function ipn_process_payment_sale_denied() {
		$order = $this->get_order();

		// Check if order exists.
		if ( ! $order ) {
			return;
		}

		// Check if the current status isn't failed.
		if ( ! in_array( $order->get_status(), array( 'failed', 'completed', 'processing' ), true ) ) {
			$order->update_status( 'failed', __( 'PayPal Plus: The transaction was rejected by the card company or by fraud.', 'woo-paypal-plus-brazil' ) );
		}
	}

	/**
	 * When payment is refunded.
	 */
	public function ipn_process_payment_sale_refunded() {
		$order = $this->get_order();

		// Check if order exists.
		if ( ! $order ) {
			return;
		}

		// Check if is total refund
		if ( $order->get_total() == floatval( $this->event['resource']['amount']['total'] ) ) {
			return;
		}

		// Check if the current status isn't refunded.
		if ( ! in_array( $order->get_status(), array( 'refunded' ), true ) ) {
			$order->update_status( 'refunded', __( 'PayPal Plus: The transaction was refunded.', 'woo-paypal-plus-brazil' ) );
		}
	}

	/**
	 * When payment is reversed.
	 */
	public function ipn_process_payment_sale_reversed() {
		$order = $this->get_order();

		// Check if order exists.
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'refunded', __( 'PayPal Plus: The transaction was reversed.', 'woo-paypal-plus-brazil' ) );
	}

}