<?php
/**
 * Created by PhpStorm.
 * User: biont
 * Date: 22.11.16
 * Time: 17:36
 */

namespace PayPalPlusPlugin\WC;

class ReceiptPageView {

	public function render() {
		?>
		<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js"
			type="text/javascript"></script>
		<script>
			function paypal_plus_redirect() {
				jQuery.blockUI( {
					message   : "<?php echo esc_js( __( 'Thank you for your order. We are now redirecting you to PayPal to make payment.',
						'woo-paypal-plus' ) ) ?>",
					baseZ     : 99999,
					overlayCSS: {
						background: "#fff",
						opacity   : 0.6
					},
					css       : {
						padding        : "20px",
						zindex         : "9999999",
						textAlign      : "center",
						color          : "#555",
						border         : "3px solid #aaa",
						backgroundColor: "#fff",
						cursor         : "wait",
						lineHeight     : "24px"
					}
				} );
				if ( typeof PAYPAL != "undefined" ) {
					PAYPAL.apps.PPP.doCheckout();
				} else {
					setTimeout( function() {
						PAYPAL.apps.PPP.doCheckout();
					}, 500 );
				}
			}
			jQuery( window ).load( function() {
				paypal_plus_redirect();
			} );
			jQuery( document ).ready( function() {
				paypal_plus_redirect();
			} );
		</script>
		<?php
	}
}