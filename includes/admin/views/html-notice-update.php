<?php
/**
 * Update notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$is_installed   = false;
$active_plugins = get_option( 'active_plugins' );
$is_active      = in_array( 'paypal-plus-brasil/paypal-plus-brasil.php', $active_plugins );
if ( function_exists( 'get_plugins' ) ) {
	$all_plugins  = get_plugins();
	$is_installed = ! empty( $all_plugins['paypal-plus-brasil/paypal-plus-brasil.php'] );
}
?>

<div class="error">
    <p>
        <strong><?php esc_html_e( 'PayPal Plus Brazil for WooCommerce', 'woo-paypal-plus-brazil' ); ?></strong> <?php esc_html_e( 'will not be updated anymore! Please update to the official extension.', 'woo-paypal-plus-brazil' ); ?>
    </p>

	<?php if ( $is_installed && ! $is_active && current_user_can( 'install_plugins' ) ) : ?>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=paypal-plus-brasil/paypal-plus-brasil.php&plugin_status=active' ), 'activate-plugin_paypal-plus-brasil/paypal-plus-brasil.php' ) ); ?>"
               class="button button-primary"><?php esc_html_e( 'Activate PayPal Plus Brasil', 'woo-paypal-plus-brazil' ); ?></a>
        </p>
	<?php elseif ( ! $is_installed ) :
		if ( current_user_can( 'install_plugins' ) ) {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=paypal-plus-brasil' ), 'install-plugin_paypal-plus-brasil' );
		} else {
			$url = 'http://wordpress.org/plugins/paypal-plus-brasil/';
		}
		?>
        <p><a href="<?php echo esc_url( $url ); ?>"
              class="button button-primary"><?php esc_html_e( 'Install PayPal Plus Brasil', 'woo-paypal-plus-brazil' ); ?></a>
        </p>
	<?php else: ?>
        <p><?php echo sprintf( __( 'Use <a href="%s">our migration tool</a> or deactivate this plugin and <a href="%s">configure the PayPal Plus again.</a>', 'woo-paypal-plus-brazil' ), esc_url( admin_url( 'tools.php?page=paypal-plus-brazil-migration-tool' ) ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc-ppp-brasil-gateway' ) ) ); ?></p>
	<?php endif; ?>
</div>