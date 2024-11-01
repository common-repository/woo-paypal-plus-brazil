<?php
global $post;
$sale_id           = get_post_meta( $post->ID, '_wc_paypal_plus_payment_sale_id', true );
$sale_fee          = get_post_meta( $post->ID, '_wc_paypal_plus_payment_sale_fee', true );
$payment_data      = get_post_meta( $post->ID, '_wc_paypal_plus_payment_data', true );
$sandbox           = get_post_meta( $post->ID, '_wc_paypal_plus_payment_sandbox', true );
$sale_link_prefix  = 'yes' == $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_history-details-from-hub&id=' : 'https://history.paypal.com/cgi-bin/webscr?cmd=_history-details-from-hub&id=';
$sale_link         = $sale_link_prefix . $sale_id;
$sale_details_link = 'https://www.paypal.com/myaccount/transaction/print-details/' . $sale_id;
?>
<?php if ( $sale_id ): ?>
	<p><strong><?php _e( 'Sale:', 'woo-paypal-plus-brazil' ); ?></strong> <a href="<?php echo $sale_link; ?>" target="_blank"><?php echo $sale_id; ?></a></p>
	<p><strong><?php _e( 'Sale fee:', 'woo-paypal-plus-brazil' ); ?></strong> <?php echo wc_price( $sale_fee ); ?></p>
	<?php if ( 'yes' == $sandbox ): ?>
		<p><strong><?php _e( 'Sandbox:', 'woo-paypal-plus-brazil' ); ?></strong> <?php _e( 'yes', 'woo-paypal-plus-brazil' ); ?></p>
	<?php else: ?>
		<p><a class="button" href="<?php echo $sale_details_link; ?>" target="_blank"><?php _e( 'Print details', 'woo-paypal-plus-brazil' ); ?></a></p>
	<?php endif; ?>
<?php else: ?>
	<p><?php _e( 'Payment not completed yet.', 'woo-paypal-plus-brazil' ); ?></p>
<?php endif; ?>
