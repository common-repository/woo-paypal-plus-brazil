<?php
/**
 * WooCommerce PayPal Plus Brazil Order Info class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_PayPal_Plus_Brazil_Order_Info.
 */
class WC_PayPal_Plus_Brazil_Order_Info {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * WC_PayPal_Plus_Brazil_Order_Info constructor.
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Add meta box to the post type
	 *
	 * @param string $post_type Post type.
	 * @param WP_Post $post Post object.
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( 'paypal-plus-brazil' == get_post_meta( $post->ID, '_payment_method', true ) ) {
			add_meta_box( 'woo-paypal-plus-brazil', __( 'PayPal Plus Brazil', 'woo-paypal-plus-brazil' ), array( $this, 'render_metabox' ), 'shop_order', 'side', 'low' );
		}
	}

	/**
	 * Render and output metabox HTML
	 */
	public function render_metabox() {
		include dirname( __FILE__ ) . '/admin/views/html-admin-order-info.php';
	}

}