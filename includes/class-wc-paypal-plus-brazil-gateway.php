<?php
/**
 * WooCommerce PayPal Plus Brazil Gateway class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_PayPal_Plus_Brazil_Gateway class.
 */
class WC_PayPal_Plus_Brazil_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'paypal-plus-brazil';
		$this->icon               = apply_filters( 'woocommerce_paypal_plus_brazil_icon', plugins_url( 'assets/images/paypal-plus.png', plugin_dir_path( __FILE__ ) ) );
		$this->method_title       = __( 'PayPal Plus Brazil', 'woo-paypal-plus-brazil' );
		$this->method_description = __( 'Accept payments by credit card using PayPal Plus.', 'woo-paypal-plus-brazil' );
		$this->order_button_text  = __( 'Confirm payment', 'woo-paypal-plus-brazil' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds'
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title                 = $this->get_option( 'title' );
		$this->description           = $this->get_option( 'description' );
		$this->client_id             = $this->get_option( 'client_id' );
		$this->client_secret         = $this->get_option( 'client_secret' );
		$this->experience_profile_id = $this->get_option( 'experience_profile_id' );
		$this->webhook_id            = $this->get_option( 'webhook_id' );
		$this->sandbox               = $this->get_option( 'sandbox', 'no' );
		$this->debug                 = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Set the API.
		$this->api = new WC_PayPal_Plus_Brazil_API( $this );

		// Main actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'remove_transient' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'get_experience_profile_id' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'create_webhook' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		add_action( 'woocommerce_api_wc_paypal_plus_brazil_gateway', array( $this, 'ipn_handler' ) );
	}

	/**
	 * Get experience profile ID after save settings.
	 */
	public function get_experience_profile_id() {
		// First check if client id and client secret is posted
		$client_id_key     = $this->get_field_key( 'client_id' );
		$client_secret_key = $this->get_field_key( 'client_secret' );
		if ( isset( $_POST[ $client_id_key ] ) && isset( $_POST[ $client_secret_key ] ) ) {
			$this->client_id     = $_POST[ $client_id_key ];
			$this->client_secret = $_POST[ $client_secret_key ];
		} else {
			// Don't try to get without access token
			return;
		}

		// If empty experience profile id, get a new one
		$experience_profile_id_key = $this->get_field_key( 'experience_profile_id' );
		if ( isset( $_POST[ $experience_profile_id_key ] ) && $_POST[ $experience_profile_id_key ] == '' ) {
			$hash     = hash( 'md5', home_url( '/' ) . time() );
			$response = $this->api->create_web_experience( array( 'name' => $hash ) );
			if ( $response ) {
				$_POST[ $experience_profile_id_key ]         = $response['id'];
				$_SESSION['woo-paypal-plus-brazil-notice'][] = 'success_experience_profile_id';
			} else {
				$_SESSION['woo-paypal-plus-brazil-notice'][] = 'error_experience_profile_id';
			}
		}
	}

	public function create_webhook() {
		// First check if webhook was already created
		if ( ! $this->webhook_id ) {
			// First check if client id and client secret is posted
			$client_id_key     = $this->get_field_key( 'client_id' );
			$client_secret_key = $this->get_field_key( 'client_secret' );
			if ( isset( $_POST[ $client_id_key ] ) && isset( $_POST[ $client_secret_key ] ) ) {
				$this->client_id     = $_POST[ $client_id_key ];
				$this->client_secret = $_POST[ $client_secret_key ];
			} else {
				// Don't try to get without access token
				return;
			}
			// Now create a new one
			$webhook_id_key = $this->get_field_key( 'webhook_id' );
			if ( isset( $_POST[ $webhook_id_key ] ) && $_POST[ $webhook_id_key ] == '' ) {
				$response = $this->api->create_webhook();
				if ( $response ) {
					$_POST[ $webhook_id_key ]                    = $response['id'];
					$_SESSION['woo-paypal-plus-brazil-notice'][] = 'success_webhook_id';
				} else {
					$_SESSION['woo-paypal-plus-brazil-notice'][] = 'error_webhook_id';
				}
			}
		}
	}

	/**
	 * Remove transients after save settings.
	 */
	public function remove_transient() {
		$old_client_id     = $this->client_id;
		$old_client_secret = $this->client_secret;
		$new_client_id     = $_POST[ $this->get_field_key( 'client_id' ) ];
		$new_client_secret = $_POST[ $this->get_field_key( 'client_secret' ) ];
		if ( ( $old_client_id != $new_client_id ) || ( $old_client_secret != $new_client_secret ) ) {
			delete_transient( 'woo_paypal_plus_brazil_access_token' );
		}
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' === get_woocommerce_currency();
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = 'yes' === $this->get_option( 'enabled' ) && '' !== $this->client_secret && '' !== $this->webhook_id && '' !== $this->experience_profile_id && '' !== $this->client_id && $this->using_supported_currency();

		return $available;
	}

	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		if ( ( is_checkout() && $this->is_available() ) || get_query_var( 'order-pay' ) ) {
			if ( ! get_query_var( 'order-received' ) ) {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_enqueue_style( 'paypal-plus-brazil-checkout', plugins_url( 'assets/css/checkout.css', plugin_dir_path( __FILE__ ) ), array(), WC_PayPay_Plus_Brazil::VERSION, 'all' );
				wp_enqueue_script( 'paypal-plus-library', '//www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min.js', array(), null, true );
				wp_enqueue_script( 'paypal-plus-brazil-checkout', plugins_url( 'assets/js/checkout' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'paypal-plus-library' ), WC_PayPay_Plus_Brazil::VERSION, true );

				wp_localize_script(
					'paypal-plus-brazil-checkout',
					'wc_ppb_params',
					array(
						'mode'                      => 'yes' === $this->sandbox ? 'sandbox' : 'live',
						'order_id'                  => get_query_var( 'order-pay' ),
						'remembered_cards'          => $this->get_customer_cards(),
						'paypal_loading_bg_color'   => $this->filter_hex_color( $this->get_option( 'loading_bg_color' ) ),
						'paypal_loading_bg_opacity' => $this->filter_opacity( $this->get_option( 'loading_bg_opacity' ) ),
						'paypal_loading_message'    => __( 'Loading...', 'woo-paypal-plus-brazil' ),
						'paypal_plus_not_available' => __( 'PayPal Plus is not active for this PayPal account. Please contact us and try another payment method.', 'woo-paypal-plus-brazil' ),
						'check_entry'               => __( 'Please fill all required fields.', 'woo-paypal-plus-brazil' ),
						'unknown_error'             => __( 'Unknown error. Please contact us and try another payment method.', 'woo-paypal-plus-brazil' ),
						'unknown_error_json'        => __( 'Unknown error in PayPal response. Please contact us and try another payment method.', 'woo-paypal-plus-brazil' ),
					)
				);
			}
		}
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woo-paypal-plus-brazil' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'               => array(
				'title'   => __( 'Enable/Disable', 'woo-paypal-plus-brazil' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayPal Plus Brazil', 'woo-paypal-plus-brazil' ),
				'default' => 'yes',
			),
			'title'                 => array(
				'title'       => __( 'Title', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-paypal-plus-brazil' ),
				'desc_tip'    => true,
				'default'     => __( 'Credit card', 'woo-paypal-plus-brazil' ),
			),
			'description'           => array(
				'title'       => __( 'Description', 'woo-paypal-plus-brazil' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-paypal-plus-brazil' ),
				'default'     => __( 'Use your credit card to checkout.', 'woo-paypal-plus-brazil' ),
			),
			'sandbox'               => array(
				'title'       => __( 'PayPal Plus Sandbox', 'woo-paypal-plus-brazil' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable PayPal Plus Sandbox', 'woo-paypal-plus-brazil' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'PayPal Plus Sandbox can be used to test the payments.', 'woo-paypal-plus-brazil' ),
			),
			'client_id'             => array(
				'title'       => __( 'PayPal Plus Client ID', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( 'Please enter your PayPal Plus client ID.', 'woo-paypal-plus-brazil' ),
				'default'     => '',
			),
			'client_secret'         => array(
				'title'       => __( 'PayPal Plus Client Secret', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( 'Please enter your PayPal Plus Client Secret.', 'woo-paypal-plus-brazil' ),
				'default'     => '',
			),
			'experience_profile_id' => array(
				'title'       => __( 'PayPal Plus Experience Profile ID', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( "Please enter your PayPal Plus Experience Profile ID. Leave empty if you don't have, the API will get one.", 'woo-paypal-plus-brazil' ),
				'default'     => '',
			),
			'webhook_id'            => array(
				'title'       => __( 'PayPal Plus Webhook ID', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( "Please enter your PayPal Webhook ID. Leave empty if you don't have, the API will get one.", 'woo-paypal-plus-brazil' ),
				'default'     => '',
			),
			'design'                => array(
				'title'       => __( 'Design', 'woo-paypal-plus-brazil' ),
				'type'        => 'title',
				'description' => '',
			),
			'loading_bg_color'      => array(
				'title'       => __( 'Loading background color', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( 'Please enter hex color to loading background. Eg.: #CCCCCC', 'woo-paypal-plus-brazil' ),
				'default'     => '#CCCCCC',
			),
			'loading_bg_opacity'    => array(
				'title'       => __( 'Loading background opacity', 'woo-paypal-plus-brazil' ),
				'type'        => 'text',
				'description' => __( 'Please enter a percentage value to background opacity. Eg.: 50%.', 'woo-paypal-plus-brazil' ),
				'default'     => '60',
			),
			'testing'               => array(
				'title'       => __( 'Gateway Testing', 'woo-paypal-plus-brazil' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug'                 => array(
				'title'       => __( 'Debug Log', 'woo-paypal-plus-brazil' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woo-paypal-plus-brazil' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log PayPal Plus Brazil events, such as API requests, inside %s', 'woo-paypal-plus-brazil' ), $this->get_log_view() ),
			),
		);
	}

	/**
	 * Remove # from the color and return correct value.
	 *
	 * @param $color
	 *
	 * @return string
	 */
	public function filter_hex_color( $color ) {
		return '#' . str_replace( '#', '', $color );
	}

	/**
	 * Return opacity from percentage to decimal.
	 *
	 * @param $value
	 *
	 * @return float
	 */
	public function filter_opacity( $value ) {
		$value = str_replace( '%', '', $value );

		return $value / 100;
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'paypay-plus-brazil-admin', plugins_url( 'assets/js/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_PayPay_Plus_Brazil::VERSION, true );
		include dirname( __FILE__ ) . '/admin/views/html-admin-page.php';
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		wp_enqueue_script( 'wc-credit-card-form' );
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		$cart_total = $this->get_order_total();
		wc_get_template( 'checkout-form.php', array(
			'api'        => $this->api,
			'gateway'    => $this,
			'cart_total' => $cart_total,
			'order_id'   => get_query_var( 'order-pay' ),
		), 'woocommerce/paypal-plus-brazil/', WC_PayPay_Plus_Brazil::get_templates_path() );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order  = new WC_Order( $order_id );
		$result = array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);

		// Check first if is missing data.
		if ( empty( $_POST['paypal-plus-brazil-rememberedcards'] ) || empty( $_POST['paypal-plus-brazil-payerid'] ) || empty( $_POST['paypal-plus-brazil-payment-id'] ) ) {
			$order->update_status( 'failed', __( 'Missing PayPal payment data.', 'woo-paypal-plus-brazil' ) );
		} else {
			$payment_id    = $_POST['paypal-plus-brazil-payment-id'];
			$remembercards = $_POST['paypal-plus-brazil-rememberedcards'];
			$payer_id      = $_POST['paypal-plus-brazil-payerid'];
			$execute       = $this->api->process_payment( $order, $payment_id, $payer_id, $remembercards );

			// Check if success.
			if ( $execute['status'] === 'completed' ) {
				$result['result'] = 'success';
				$order->payment_complete();
			} else if ( $execute['status'] === 'denied' ) {
				$order->update_status( 'failed', __( 'Payment denied.', 'woo-paypal-plus-brazil' ) );
			} else if ( $execute['status'] === 'pending' ) {
				$result['result'] = 'success';
				$order->reduce_order_stock();
				$order->update_status( 'on-hold', __( 'Waiting payment confirmation.', 'woo-paypal-plus-brazil' ) );
			} else {
				$order->update_status( 'failed', __( 'Could not execute the payment.', 'woo-paypal-plus-brazil' ) );
			}
		}

		return $result;
	}

	/**
	 * Store customer data to retrive later.
	 *
	 * @param $data Posted data encoded and not parsed.
	 */
	public static function save_customer_info( $data ) {
		$decoded = urldecode( $data );
		parse_str( $decoded, $posted );

		$customer = array(
			'billing_first_name'  => '',
			'billing_last_name'   => '',
			'billing_person_type' => '',
			'billing_person_id'   => '',
			'billing_email'       => '',
			'shipping_address'    => '',
			'shipping_address_2'  => '',
			'data'                => $posted,
		);

		// Set the first name.
		if ( isset( $posted['billing_first_name'] ) ) {
			$customer['billing_first_name'] = sanitize_text_field( $posted['billing_first_name'] );
		}

		// Set the last name.
		if ( isset( $posted['billing_last_name'] ) ) {
			$customer['billing_last_name'] = sanitize_text_field( $posted['billing_last_name'] );
		}

		// Set the person type.
		if ( isset( $posted['billing_persontype'] ) ) {
			$customer['billing_person_type'] = sanitize_text_field( $posted['billing_persontype'] );
		}

		// Set the phone.
		if ( isset( $posted['billing_phone'] ) ) {
			$customer['billing_phone'] = sanitize_text_field( $posted['billing_phone'] );
		}

		// Set the person type.
		if ( isset( $posted['billing_persontype'] ) ) {
			switch ( $posted['billing_persontype'] ) {
				case '1':
					$customer['billing_person_type'] = 'BR_CPF';
					break;
				case '2':
					$customer['billing_person_type'] = 'BR_CNPJ';
					break;
			}
		}

		// Set the person id.
		if ( $customer['billing_person_type'] ) {
			switch ( $customer['billing_person_type'] ) {
				case 'BR_CPF':
					if ( isset( $posted['billing_cpf'] ) ) {
						$customer['billing_person_id'] = sanitize_text_field( $posted['billing_cpf'] );
					}
					break;
				case 'BR_CNPJ':
					if ( isset( $posted['billing_cnpj'] ) ) {
						$customer['billing_person_id'] = sanitize_text_field( $posted['billing_cnpj'] );
					}
					break;
			}
		}

		// Set the first name.
		if ( isset( $posted['billing_email'] ) ) {
			$customer['billing_email'] = sanitize_text_field( $posted['billing_email'] );
		}

		// Set the address.
		{
			$fields = array(
				's_country',
				's_state',
				's_postcode',
				's_city',
				's_address',
			);

			$field_empty = false;

			foreach ( $fields as $field ) {
				if ( ! isset( $_POST[ $field ] ) || empty( $field ) ) {
					$field_empty = true;
				}
			}

			$ships_diff = isset( $posted['ship_to_different_address'] ) && $posted['ship_to_different_address'] == '1';

			if ( $ships_diff && ( ! isset( $posted['shipping_number'] ) || empty( $posted['shipping_number'] ) ) ) {
				$field_empty = true;
			} else if ( $ships_diff && ( ! isset( $posted['billing_number'] ) || empty( $posted['billing_number'] ) ) ) {
				$field_empty = true;
			}

			if ( ! $field_empty ) {
				$address = $_POST['s_address'];
				if ( $ships_diff ) {
					$customer['shipping_address_2'] = $posted['shipping_neighborhood'];
					$address .= ', ' . $posted['shipping_number'];
					if ( $posted['shipping_address_2'] ) {
						$address .= ', ' . $posted['shipping_address_2'];
					}
				} else {
					$customer['shipping_address_2'] = $posted['billing_neighborhood'];
					$address .= ', ' . $posted['billing_number'];
					if ( $posted['billing_address_2'] ) {
						$address .= ', ' . $posted['billing_address_2'];
					}
				}
				$customer['shipping_address'] = $address;
			}

		}

		$defaults = array(
			'billing_first_name'  => '',
			'billing_last_name'   => '',
			'billing_email'       => '',
			'billing_phone'       => '',
			'billing_person_type' => '',
			'billing_person_id'   => '',
			'shipping_address'    => '',
			'shipping_address_2'  => '',
		);

		$customer = wp_parse_args( $customer, $defaults );

		// Store data in a session to retrive later.
		WC()->session->set( 'paypal_plus_customer_info', $customer );
	}

	/**
	 * Get customer info.
	 * @return array
	 */
	public function get_customer_info( $order_id = false ) {
		if ( $order_id ) {
			$order         = wc_get_order( $order_id );
			$customer_info = array(
				'billing_first_name'  => $order->billing_first_name,
				'billing_last_name'   => $order->billing_last_name,
				'billing_email'       => $order->billing_email,
				'billing_phone'       => $order->billing_phone,
				'billing_person_type' => '',
				'billing_person_id'   => '',
				'shipping_address'    => $order->shipping_address_1 . ', ' . get_post_meta( $order->id, '_shipping_number', true ),
				'shipping_address_2'  => get_post_meta( $order->id, '_shipping_neighborhood', true ),
			);

			if ( $order->shipping_address_2 ) {
				$customer_info['shipping_address'] .= ', ' . $order->shipping_address_2;
			}

			if ( $person_type = get_post_meta( $order->id, '_billing_persontype', true ) ) {
				if ( $person_type === '1' ) {
					$customer_info['billing_person_type'] = 'BR_CPF';
				} else if ( $person_type === '2' ) {
					$customer_info['billing_person_type'] = 'BR_CNPJ';
				}
			}

			if ( $customer_info['billing_person_type'] === 'BR_CPF' ) {
				$customer_info['billing_person_id'] = get_post_meta( $order->id, '_billing_cpf', true );
			} else if ( $customer_info['billing_person_type'] === 'BR_CNPJ' ) {
				$customer_info['billing_person_id'] = get_post_meta( $order->id, '_billing_cnpj', true );
			}
		} else {
			$customer_info = WC()->session->get( 'paypal_plus_customer_info' );
		}

		return $customer_info;
	}

	/**
	 * Get customer credit card.
	 *
	 * @return mixed|string
	 */
	public function get_customer_cards() {
		$current_user_id = get_current_user_id();
		if ( $current_user_id ) {
			return get_user_meta( $current_user_id, 'paypal_plus_remembered_cards', true );
		}

		return '';
	}

	/**
	 * Get customer info json encoded and special chars.
	 *
	 * @return string
	 */
	public function get_customer_info_json_specialchars( $order_id = false ) {
		$customer_info = $this->get_customer_info( $order_id );

		return htmlspecialchars( json_encode( $customer_info ) );
	}

	/**
	 * Check if user info in the form is valid.
	 *
	 * @return bool
	 */
	public function is_valid_customer_info( $order_id = false ) {
		$customer_info = $this->get_customer_info( $order_id );

		return $this->validate_user_fields( $customer_info );
	}

	/**
	 * Validate user fields.
	 */
	public function validate_user_fields( $fields ) {
		$customer = WC()->customer;
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		$default_fields = array(
			'billing_first_name'    => '',
			'billing_last_name'     => '',
			'billing_person_type'   => '',
			'billing_person_id'     => '',
			'billing_email'         => '',
			'shipping_address'      => '',
			'shipping_city'         => $customer->get_shipping_city(),
			'shipping_country_code' => $customer->get_shipping_country(),
			'shipping_postal_code'  => $customer->get_shipping_postcode(),
			'shipping_state'        => $customer->get_shipping_state(),
		);
		$fields         = wp_parse_args( $fields, $default_fields );
		foreach ( $fields as $id => $field ) {
			if ( empty( $field ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Register log to WooCommerce logs.
	 *
	 * @param $log
	 */
	public function log( $log ) {
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $log );
		}
	}

	/**
	 * Return the name of the option in the WP DB.
	 * @return string
	 */
	public function get_option_key() {
		return $this->plugin_id . $this->id . '_settings';
	}

	/**
	 * Webhook handler.
	 */
	public function ipn_handler() {
		$this->api->ipn_handler();
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param  int $order_id
	 * @param  float $amount
	 * @param  string $reason
	 *
	 * @return bool True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		// Check if amount
		if ( ! $amount ) {
			$this->log( 'Refund Failed: No amount.' );

			return new WP_Error( 'error', __( 'Refund Failed: No amount', 'woo-paypal-plus-brazil' ) );
		}

		// Check if refund is available.
		if ( ! $this->can_refund_order( $order ) ) {
			$this->log( 'Refund Failed: No sale ID' );

			return new WP_Error( 'error', __( 'Refund Failed: No sale ID', 'woo-paypal-plus-brazil' ) );
		}

		$sale_id  = get_post_meta( $order->id, '_wc_paypal_plus_payment_sale_id', true );
		$response = $this->api->refund_order( $this->id, $amount, $sale_id );
		if ( $response ) {
			$order->add_order_note( sprintf( __( 'Order refunded ID: %s. Total refunded: %s.', 'woo-paypal-plus-brazil' ), $response['id'], wc_price( $amount ) ) );

			return true;
		}

		return new WP_Error( 'error', __( 'Refund Failed.', 'woo-paypal-plus-brazil' ) );
	}

	/**
	 * Can the order be refunded via PayPal?
	 *
	 * @param  WC_Order $order
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		$sale_id = get_post_meta( $order->id, '_wc_paypal_plus_payment_sale_id', true );

		return $sale_id ? true : false;
	}


}