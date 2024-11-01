<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$plugin_options = get_option( 'woocommerce_paypal-plus-brazil_settings' );
?>
<div class="wrap">
    <h1><?php _e( 'PayPal Plus Migration Tool', 'woo-paypal-plus-brazil' ); ?></h1>
    <p><?php _e( 'We will migrate the settings to the official plugin. The official plugin should be installed to work.', 'woo-paypal-plus-brazil' ); ?></p>
    <form method="post"
          action="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc-ppp-brasil-gateway' ) ); ?>"
          enctype="multipart/form-data">
        <input type="hidden" name="woocommerce_wc-ppp-brasil-gateway_enabled"
               value="<?php echo $plugin_options['enabled']; ?>">
        <input type="hidden" name="woocommerce_wc-ppp-brasil-gateway_mode"
               value="<?php echo $plugin_options['sandbox'] === 'no' ? 'live' : 'sandbox'; ?>">
        <input type="hidden" name="woocommerce_wc-ppp-brasil-gateway_client_id"
               value="<?php echo $plugin_options['client_id']; ?>">
        <input type="hidden" name="woocommerce_wc-ppp-brasil-gateway_client_secret"
               value="<?php echo $plugin_options['client_secret']; ?>">
		<?php wp_nonce_field( 'woocommerce-settings', '_wpnonce' ); ?>
        <button type="submit" class="button button-primary"><?php _e( 'Migrate', 'woo-paypal-plus-brazil' ); ?></button>
    </form>
</div>