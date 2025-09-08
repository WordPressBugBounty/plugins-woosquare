<?php
/**
 * Square Payment Gateway Settings Configuration.
 *
 * This file contains the settings configuration for the Square Payment Gateway integration.
 * It defines various options for enabling/disabling payment methods, capturing payments, etc.
 *
 * @package Woosquare_Plus
 */

?>
<div class="bodycontainerWrap">


	<div class="bodycontainer">

		<div id="tabs" class="md-elevation-4dp bg-theme-primary">
			<?php
			$woosquare_plus = new Woosquare_Plus();
			echo wp_kses_post( $woosquare_plus->wooplus_get_toptabs() );
			?>

		</div>

		<?php
			$data = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
			parse_str( $data, $query_params );
		?>

		<div class="welcome-panel <?php echo isset( $query_params['page'] ) ? esc_html( sanitize_text_field( wp_unslash( $query_params['page'] ) ) ) : ''; ?>">

			<h1 class="m-0"><svg height="20px" viewBox="0 0 512 511" width="20px" xmlns="http://www.w3.org/2000/svg">
					<path
							d="m405.332031 256.484375c-11.796875 0-21.332031 9.558594-21.332031 21.332031v170.667969c0 11.753906-9.558594 21.332031-21.332031 21.332031h-298.667969c-11.777344 0-21.332031-9.578125-21.332031-21.332031v-298.667969c0-11.753906 9.554687-21.332031 21.332031-21.332031h170.667969c11.796875 0 21.332031-9.558594 21.332031-21.332031 0-11.777344-9.535156-21.335938-21.332031-21.335938h-170.667969c-35.285156 0-64 28.714844-64 64v298.667969c0 35.285156 28.714844 64 64 64h298.667969c35.285156 0 64-28.714844 64-64v-170.667969c0-11.796875-9.539063-21.332031-21.335938-21.332031zm0 0" />
					<path
							d="m200.019531 237.050781c-1.492187 1.492188-2.496093 3.390625-2.921875 5.4375l-15.082031 75.4375c-.703125 3.496094.40625 7.101563 2.921875 9.640625 2.027344 2.027344 4.757812 3.113282 7.554688 3.113282.679687 0 1.386718-.0625 2.089843-.210938l75.414063-15.082031c2.089844-.429688 3.988281-1.429688 5.460937-2.925781l168.789063-168.789063-75.414063-75.410156zm0 0" />
					<path
							d="m496.382812 16.101562c-20.796874-20.800781-54.632812-20.800781-75.414062 0l-29.523438 29.523438 75.414063 75.414062 29.523437-29.527343c10.070313-10.046875 15.617188-23.445313 15.617188-37.695313s-5.546875-27.648437-15.617188-37.714844zm0 0" />
				</svg> Square Payment Gateway Settings</h1>

			<h1 class="screen-reader-text">Checkout</h1>



			<p class="p-l-10">Square works by adding payments fields in an iframe and then sending the details to Square
				for verification
				and processing.</p>
			<form action="<?php echo esc_url( get_admin_url() ); ?>admin-post.php" method="post">
				<input type="hidden" name="action" value="add_foobar">
				<?php
				if ( ! empty( $square_payment_settin ) ) {

					$unserialize_array = $square_payment_settin;

				} else {

					$unserialize_array = array(
						'enabled'            => 'no',
						'title'              => 'Credit card (Square)',
						'description'        => 'Pay with your credit card via Square.',
						'capture'            => 'no',
						'create_customer'    => 'no',
						'google_pay' . get_transient( 'is_sandbox' ) . '_enabled' => 'no',
						'gift_card_enabled'  => 'no',
						'after_pay_enabled'  => 'no',
						'cash_app_pay' . get_transient( 'is_sandbox' ) . '_enabled' => 'no',
						'Send_customer_info' => 'no',
						'logging'            => 'no',
					);
				}
				?>

				<div class="formWrap">
					<ul>
						<li>
							<strong>Enable/Disable</strong>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Enable/Disable</span></legend>
									<label for="woocommerce_square_enabled">
										<input type="checkbox" name="woocommerce_square_enabled"
												id="woocommerce_square_enabled" value="1"
												<?php checked( 'yes', $unserialize_array['enabled'] ?? '' ); ?> />Enable
										Square</label><br>
								</fieldset>
							</div>
						</li>
						<li>
							<strong>Title</strong>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Title</span></legend>

									<input class="form-control m-b-10 " type="text" name="woocommerce_square_title"
											id="woocommerce_square_title" style=""
											value="<?php echo esc_html( $unserialize_array['title'] ?? null ); ?>" placeholder="">

									<p class="help-text">This controls the title which the user sees during checkout.
									</p>

								</fieldset>
							</div>
						</li>
						<li>
							<strong>Description</strong>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Description</span></legend>

									<textarea rows="5" cols="180" class="form-control m-b-10 wide-input "
												type="textarea" name="woocommerce_square_description"
												id="woocommerce_square_description" style=""
												placeholder=""><?php echo esc_html( $unserialize_array['description'] ?? null ); ?></textarea>

									<p class="help-text">This controls the description which the user sees during
										checkout.</p>

								</fieldset>
							</div>
						</li>
						<li>
							<strong>
								Delay Capture
							</strong>
							<p class="description ext">When enabled, the request will only perform an Auth on the
								provided card. You can then later perform either a Capture or Void.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Delay Capture</span></legend>

									<label for="woocommerce_square_capture">

										<input type="checkbox" name="woocommerce_square_capture"
												id="woocommerce_square_capture" value="1"
												<?php checked( 'yes', $unserialize_array['capture'] ?? '' ); ?> />Enable Delay
										Capture</label><br>



								</fieldset>
							</div>

						</li>
						<li>
							<strong>Create Customer</strong>
							<p class="description ext">When enabled, processing a payment will create a customer
								profile
								on Square.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Create Customer</span></legend>

									<label for="woocommerce_square_create_customer">

										<input type="checkbox" name="woocommerce_square_create_customer"
												id="woocommerce_square_create_customer" value="1"
												<?php checked( 'yes', $unserialize_array['create_customer'] ?? '' ); ?> />Enable
										Create Customer</label>



								</fieldset>
							</div>
						</li>
						<li>
							<strong>Logging</strong>
							<p class="description ext">Save debug messages to the WooCommerce System Status log.</p>

							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span>Logging</span></legend>

									<label for="woocommerce_square_logging">

										<input type="checkbox" name="woocommerce_square_logging"
												id="woocommerce_square_logging" value="1"
												<?php checked( 'yes', $unserialize_array['logging'] ?? '' ); ?> />Log debug
										messages</label>



								</fieldset>
							</div>

						</li>
						<?php
						$activate_modules_woosquare_plus = get_option( 'activate_modules_woosquare_plus' . get_transient( 'is_sandbox' ), true );
						if ( ! $activate_modules_woosquare_plus['woosquare_transaction_addon']['module_activate'] ) {
							?>
							<?php if ( 'WC Shop Sync Pro' === WOOSQU_PLUS_LABEL ) { ?>
							<li>



								<strong>Send Customer Info</strong>
								<p class="description ext">Send first name last name with order to square.</p>
								<div class="elementBlock">
									<fieldset>
										<legend class="screen-reader-text"><span>Send Customer Info</span></legend>
										<label for="Send_customer_info">
											<input type="checkbox" name="Send_customer_info" id="Send_customer_info"
													value="1"
								<?php checked( 'yes', $unserialize_array['Send_customer_info'] ?? '' ); ?> />Send
											first name last name</label>

									</fieldset>
								</div>




							</li>
							<?php } ?>
						<?php } ?>
						<?php if ( 'WC Shop Sync Pro' === WOOSQU_PLUS_LABEL ) { ?>
						<li>
							<strong> Enable/Disable Payment Reporting </strong>
							<p class="description ext">Click below button to enable payment reporting.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="payment_reporting">

										<input type="checkbox" name="woocommerce_square_payment_reporting"
												id="payment_reporting" value="1"
												<?php checked( 'yes' === $woocommerce_square_payment_reporting ); ?> /> Enable Payment Reporting </label><br>

								</fieldset>
							</div>

						</li>
						<?php } ?>
						<li>
							<strong>Enable/Disable Google Pay</strong>
							<p class="description ext">Click below button to enable Google Pay.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="google_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled">

										<input type="checkbox" name="woocommerce_square_google_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
												id="google_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled" value="1"
												<?php checked( isset( $square_payment_setting_google_pay['enabled'] ) && 'yes' === $square_payment_setting_google_pay['enabled'] ); ?> />Enable Google Pay</label><br>

								</fieldset>
							</div>

						</li>
						<?php if ( 'WC Shop Sync Pro' === WOOSQU_PLUS_LABEL ) { ?>
						<li>
							<strong>Enable/Disable Gift Card</strong>
							<p class="description ext">Click below button to enable Gift Card .</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="gift_card_enabled">

										<input type="checkbox" name="woocommerce_square_gift_card_pay_enabled<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>"
												id="gift_card_enabled" value="1"
												<?php checked( isset( $woocommerce_square_gift_card_pay_enabled ) && 'yes' === $woocommerce_square_gift_card_pay_enabled ); ?> />Enable Gift Card</label><br>

								</fieldset>
							</div>

						</li>
						<?php } ?>

						<li>
							<strong>Enable/Disable ACH Payment</strong>
							<p class="description ext">Click below button to enable ACH Payment.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="ach_payment<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled">

										<input type="checkbox" name="woocommerce_square_ach_payment<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
																								id="ach_payment<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled" value="1"
																								<?php checked( isset( $woocommerce_square_ach_payment_settings['enabled'] ) && 'yes' === $woocommerce_square_ach_payment_settings['enabled'] ); ?> />Enable ACH Payment</label><br>
								</fieldset>
							</div>

						</li>

						<li>
							<strong>Enable/Disable Apple Pay</strong>
							<p class="description ext">Click below button to enable Apple Pay.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="apple_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled">

										<input type="checkbox" name="woocommerce_square_apple_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
												id="apple_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled" value="1"
												<?php
												checked( isset( $woocommerce_square_apple_pay_enabled['enabled'] ) && 'yes' === $woocommerce_square_apple_pay_enabled['enabled'] );
												?>
												/>Enable Apple Pay </label><br><br>
												<button class="apple_verify_domain" style="display:none;">Verify domain</button>
												<p class="apple_domain_error_test"></p>
												<input type="hidden" id="apple_domain_verification" name="apple_domain_verification" value="<?php echo esc_attr( wp_create_nonce( 'apple-domain-verification-nonce' ) ); ?>" />

								</fieldset>
							</div>

						</li>
						<?php if ( 'WC Shop Sync Pro' === WOOSQU_PLUS_LABEL ) { ?>
						<li>
							<strong>Select button color</strong>
							<p class="description ext">This will apply on Apple and Google Pay buttons.</p>
							<div class="elementBlock">
								<fieldset>
									<legend class="screen-reader-text"><span>Select Button Color</span></legend>
									<select name="woocommerce_square_button_color" id="woocommerce_square_button_color">
										<option value="black" <?php selected( $unserialize_array['button_color'] ?? 'black', 'black' ); ?>>Black</option>
										<option value="white" <?php selected( $unserialize_array['button_color'] ?? 'black', 'white' ); ?>>White</option>
									</select>
									<br>
								</fieldset>
							</div>
						</li>
						
						<li>
							<strong>Enable Express Checkout</strong>
							<p class="description ext">
								To enable express checkout on the product and cart page, make sure Google Pay and Apple Pay payment gateways are enabled.
							</p>
							<div class="elementBlock">
								<fieldset>
									<legend class="screen-reader-text"><span>Enable digital wallets.</span></legend>
									<label for="apple_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_expressch_enabled">
										<input type="checkbox" name="woocommerce_square_apple_pay_expressch_enabled"
												id="apple_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_expressch_enabled" value="1"
											<?php checked( isset( $unserialize_array['express_checkout_enabled'] ) && 'yes' === $unserialize_array['express_checkout_enabled'] ); ?> />
										Enable digital wallet
										<span style="font-weight: bold; margin-left: 10px;">Express Checkout</span>
									</label>
								</fieldset>
							</div>
						</li>
						<?php } ?>
						<li>
							<strong>Enable/Disable After Pay</strong>
							<p class="description ext">Click below button to enable After Pay. Only USD,CAD,AUD,GBP currency support and merchant account must be onboarded on square!</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="after_pay_enabled">

										<input type="checkbox" name="woocommerce_square_after_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
												id="after_pay_enabled" value="1"
												<?php
												checked( isset( $woocommerce_square_after_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_after_pay_settings['enabled'] );
												?>
												/>Enable After Pay </label><br>
								</fieldset>
							</div>

						</li>

						<li>
							<strong>Enable/Disable CashApp Pay</strong>
							<p class="description ext">Click below button to enable CashApp Pay. Only US ($) currency support!</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="cash_app_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled">

										<input type="checkbox" name="woocommerce_square_cash_app_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
												id="cash_app_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled" value="1"
												<?php
												checked( isset( $woocommerce_square_cash_app_pay_settings['enabled'] ) && 'yes' === $woocommerce_square_cash_app_pay_settings['enabled'] );
												?>
												/>Enable CashApp Pay </label><br>
								</fieldset>
							</div>

						</li>
						<?php if ( 'WC Shop Sync Pro' === WOOSQU_PLUS_LABEL ) { ?>
						<li>
							<strong>Enable/Disable POS Pay</strong>
							<p class="description ext">Click below button to POS Pay.</p>
							<div class="elementBlock">
								<fieldset>

									<legend class="screen-reader-text"><span></span></legend>

									<label for="terminal_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled">

										<input type="checkbox" name="woocommerce_square_pos<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled"
												id="terminal_pay<?php echo esc_html( get_transient( 'is_sandbox' ) ); ?>_enabled" value="1"
												<?php
												checked( isset( $woocommerce_square_terminal_pay['enabled'] ) && 'yes' === $woocommerce_square_terminal_pay['enabled'] );
												?>
												/>Enable POS Pay </label><br>

								</fieldset>
							</div>

						</li>
						<?php } ?>
					</ul>

						<div class="row m-t-20">
							<div class="col-md-6">
							<span class="submit">

								<input name="save" class="btn waves-effect waves-light btn-rounded btn-success"
										type="submit" value="Save changes">

								<input type="hidden" id="_wpnonce" name="_wpnonce" value="6952bcc533"><input
										type="hidden" name="_wp_http_referer"
										value="">
							</span>
							</div>
						</div>


				</div>
				<?php wp_nonce_field( 'woosquare_setting_nonce', 'woosquare_setting' ); ?>
			</form>

		</div>

	</div>


</div>
