<?php
/**
 * Checkout Template
 *
 * @package     EDD
 * @subpackage  Checkout
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get Checkout Form
 *
 * @since 1.0
 * @return string
 */
function edd_checkout_form() {
	$payment_mode = edd_get_chosen_gateway();
	$form_action  = esc_url( edd_get_checkout_uri( 'payment-mode=' . $payment_mode ) );

	ob_start();
		echo '<div id="edd_checkout_wrap">';
		if ( edd_get_cart_contents() || edd_cart_has_fees() ) :
			edd_checkout_cart(); ?>
			<div id="edd_checkout_form_wrap" class="edd_clearfix">
				<?php do_action( 'edd_before_purchase_form' ); ?>
				<form id="edd_purchase_form" class="edd_form" action="<?php echo $form_action; ?>" method="POST">
					<?php
					/**
					 * Hooks in at the top of the checkout form
					 *
					 * @since 1.0
					 */
					do_action( 'edd_checkout_form_top' );

					if ( edd_is_ajax_disabled() && ! empty( $_REQUEST['payment-mode'] ) ) {
						do_action( 'edd_purchase_form' );
					} elseif ( edd_show_gateways() ) {
						do_action( 'edd_payment_mode_select'  );
					} else {
						do_action( 'edd_purchase_form' );
					}

					/**
					 * Hooks in at the bottom of the checkout form
					 *
					 * @since 1.0
					 */
					do_action( 'edd_checkout_form_bottom' )
					?>
				</form>
				<?php do_action( 'edd_after_purchase_form' ); ?>
			</div><!--end #edd_checkout_form_wrap-->
		<?php
		else:
			/**
			 * Fires off when there is nothing in the cart
			 *
			 * @since 1.0
			 */
			do_action( 'edd_cart_empty' );
		endif;
		echo '</div><!--end #edd_checkout_wrap-->';
	return ob_get_clean();
}

/**
 * Renders the Purchase Form, hooks are provided to add to the purchase form.
 * The default Purchase Form rendered displays a list of the enabled payment
 * gateways, a user registration form (if enable) and a credit card info form
 * if credit cards are enabled
 *
 * @since 1.4
 * @return string
 */
function edd_show_purchase_form() {
	$payment_mode = edd_get_chosen_gateway();

	/**
	 * Hooks in at the top of the purchase form.
	 *
	 * @since 1.4
	 */
	do_action( 'edd_purchase_form_top' );

	// Maybe load purchase form.
	if ( edd_can_checkout() ) {

		/**
		 * Fires before the register/login form.
		 *
		 * @since 1.4
		 */
		do_action( 'edd_purchase_form_before_register_login' );

		$show_register_form = edd_get_option( 'show_register_form', 'none' );
		if ( ( 'registration' === $show_register_form || ( 'both' === $show_register_form && ! isset( $_GET['login'] ) ) ) && ! is_user_logged_in() ) : ?>
			<div id="edd_checkout_login_register">
				<?php do_action( 'edd_purchase_form_register_fields' ); ?>
			</div>
		<?php elseif ( ( 'login' === $show_register_form || ( 'both' === $show_register_form && isset( $_GET['login'] ) ) ) && ! is_user_logged_in() ) : ?>
			<div id="edd_checkout_login_register">
				<?php do_action( 'edd_purchase_form_login_fields' ); ?>
			</div>
		<?php endif; ?>

		<?php
		if ( ( ! isset( $_GET['login'] ) && is_user_logged_in() ) || ! isset( $show_register_form ) || 'none' === $show_register_form || 'login' === $show_register_form ) { // WPCS: CSRF ok.
			do_action( 'edd_purchase_form_after_user_info' );
		}

		/**
		 * Hooks in before the credit card form.
		 *
		 * @since 1.4
		 */
		do_action( 'edd_purchase_form_before_cc_form' );

		if ( edd_get_cart_total() > 0 ) {

			// Load the credit card form and allow gateways to load their own if they wish.
			if ( has_action( 'edd_' . $payment_mode . '_cc_form' ) ) {
				do_action( 'edd_' . $payment_mode . '_cc_form' );
			} else {
				do_action( 'edd_cc_form' );
			}
		}

		/**
		 * Hooks in after the credit card form.
		 *
		 * @since 1.4
		 */
		do_action( 'edd_purchase_form_after_cc_form' );

	// Can't checkout.
	} else {
		do_action( 'edd_purchase_form_no_access' );
	}

	/**
	 * Hooks in at the bottom of the purchase form.
	 *
	 * @since 1.4
	 */
	do_action( 'edd_purchase_form_bottom' );
}
add_action( 'edd_purchase_form', 'edd_show_purchase_form' );

/**
 * Shows the User Info fields in the Personal Info box, more fields can be added
 * via the hooks provided.
 *
 * @since 1.3.3
 * @return void
 */
function edd_user_info_fields() {
	$customer = EDD()->session->get( 'customer' );
	$customer = wp_parse_args( $customer, array( 'first_name' => '', 'last_name' => '', 'email' => '' ) );

	if ( is_user_logged_in() ) {
		$user_data = get_userdata( get_current_user_id() );
		foreach ( $customer as $key => $field ) {
			if ( 'email' === $key && empty( $field ) ) {
				$customer[ $key ] = $user_data->user_email;
			} elseif ( empty( $field ) ) {
				$customer[ $key ] = $user_data->$key;
			}
		}
	}

	$customer = array_map( 'sanitize_text_field', $customer );
	?>

	<fieldset id="edd_checkout_user_info">
		<legend><?php echo apply_filters( 'edd_checkout_personal_info_text', esc_html__( 'Personal Info', 'easy-digital-downloads' ) ); ?></legend>

		<?php
		/**
		 * Allow output before the email field.
		 *
		 * @since 1.3.3
		 *
		 * @param array $customer Customer data.
		 */
		do_action( 'edd_purchase_form_before_email', $customer );

		echo EDD()->html->text( array(
			'id'          => 'edd_email',
			'name'        => 'edd_email',
			'label'       => __( 'Email Address', 'easy-digital-downloads' ),
			'desc'        => __( 'We will send the purchase receipt to this address.', 'easy-digital-downloads' ),
			'placeholder' => __( 'Email address', 'easy-digital-downloads' ),
			'value'       => $customer['email'],
			'required'    => edd_field_is_required( 'edd_email' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		/**
		 * Allow output after the email field.
		 *
		 * @since 1.3.3
		 *
		 * @param array $customer Customer data.
		 */
		do_action( 'edd_purchase_form_after_email', $customer );

		echo EDD()->html->text( array(
			'id'          => 'edd_first',
			'name'        => 'edd_first',
			'label'       => __( 'First Name', 'easy-digital-downloads' ),
			'desc'        => __( 'We will use this to personalize your account experience.', 'easy-digital-downloads' ),
			'placeholder' => __( 'First name', 'easy-digital-downloads' ),
			'value'       => $customer['first_name'],
			'required'    => edd_field_is_required( 'edd_first' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->text( array(
			'id'          => 'edd_last',
			'name'        => 'edd_last',
			'label'       => __( 'Last Name', 'easy-digital-downloads' ),
			'desc'        => __( 'We will use this to personalize your account experience.', 'easy-digital-downloads' ),
			'placeholder' => __( 'Last name', 'easy-digital-downloads' ),
			'value'       => $customer['first_name'],
			'required'    => edd_field_is_required( 'edd_first' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		/**
		 * Allow output after the user info field s.
		 *
		 * @since 1.3.3
		 *
		 * @param array $customer Customer data.
		 */
		do_action( 'edd_purchase_form_user_info', $customer );

		/**
		 * Allow output of additional user info fields.
		 *
		 * @todo Deprecate this? Seems unneeded.
		 *
		 * @since 1.3.3
		 *
		 * @param array $customer Customer data.
		 */
		do_action( 'edd_purchase_form_user_info_fields', $customer );
		?>
	</fieldset>

	<?php
}
add_action( 'edd_purchase_form_after_user_info', 'edd_user_info_fields' );
add_action( 'edd_register_fields_before', 'edd_user_info_fields' );

/**
 * Renders the credit card info form.
 *
 * @since 1.0
 * @return void
 */
function edd_get_cc_form() {
	/**
	 * Allow output before credit card fields.
	 *
	 * @since 1.0
	 */
	do_action( 'edd_before_cc_fields' );
?>

	<fieldset id="edd_cc_fields" class="edd-do-validate">
		<legend><?php esc_html_e( 'Credit Card Info', 'easy-digital-downloads' ); ?></legend>

		<?php if ( is_ssl() ) : ?>
			<div id="edd_secure_site_wrapper">
				<?php
					echo edd_get_payment_icon(
						array(
							'icon'    => 'lock',
							'width'   => 16,
							'height'  => 16,
							'title'   => __( 'Secure SSL encrypted payment', 'easy-digital-downloads' ),
							'classes' => array( 'edd-icon', 'edd-icon-lock' )
						)
					);
				?>

				<span><?php esc_html_e( 'This is a secure SSL encrypted payment.', 'easy-digital-downloads' ); ?></span>
			</div>
		<?php endif; ?>

		<?php
		echo EDD()->html->card_number( array(
			'id'           => 'card_number',
			'name'         => 'card_number',
			'label'        => __( 'Card Number', 'easy-digital-downloads' ),
			'desc'         => __( 'The (typically) 16 digits on the front of your credit card.', 'easy-digital-downloads' ),
			'placeholder'  => __( 'Card number', 'easy-digital-downloads' ),
			'wrapper_tag'  => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->card_cvc( array(
			'id'           => 'card_cvc',
			'name'         => 'card_cvc',
			'label'        => __( 'CVC', 'easy-digital-downloads' ),
			'desc'         => __( 'The 3 digit (back) or 4 digit (front) value on your card.', 'easy-digital-downloads' ),
			'placeholder'  => __( 'Security code', 'easy-digital-downloads' ),
			'wrapper_tag'  => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->text( array(
			'id'           => 'card_name',
			'name'         => 'card_name',
			'label'        => __( 'Name on the Card', 'easy-digital-downloads' ),
			'desc'         => __( 'The name printed on the front of your credit card.', 'easy-digital-downloads' ),
			'placeholder'  => __( 'Card name', 'easy-digital-downloads' ),
			'required'     => true,
			'autocomplete' => 'off',
			'wrapper_tag'  => 'p',
		) ); // WPCS: XSS okay.

		/**
		 * Allow output before the card expiration fields.
		 *
		 * @since 1.0
		 */
		do_action( 'edd_before_cc_expiration' );

		echo EDD()->html->card_expiration( array(
			'id'    => 'card_expiration',
			'name'  => 'card_expiration',
			'label' => __( 'Expiration (MM/YY)', 'easy-digital-downloads' ),
			'desc'  => __( 'The date your credit card expires, typically on the front of the card.', 'easy-digital-downloads' ),
		) ); // WPCS: XSS okay.

		/**
		 * Allow output after the card expiration fields.
		 *
		 * @since 1.0
		 */
		do_action( 'edd_after_cc_expiration' );
		?>

	</fieldset>

	<?php
	/**
	 * Allow output after the credit card fields.
	 *
	 * @since 1.0
	 */
	do_action( 'edd_after_cc_fields' );
}
add_action( 'edd_cc_form', 'edd_get_cc_form' );

/**
 * Outputs the default credit card address fields
 *
 * @since 1.0
 * @since 3.0 Updated to use `edd_get_customer_address()`.
 */
function edd_default_cc_address_fields() {
	$logged_in = is_user_logged_in();

	$customer = EDD()->session->get( 'customer' );

	$customer = wp_parse_args( $customer, array(
		'address' => array(
			'line1'   => '',
			'line2'   => '',
			'city'    => '',
			'zip'     => '',
			'state'   => '',
			'country' => '',
		),
	) );

	$customer['address'] = array_map( 'sanitize_text_field', $customer['address'] );

	if ( $logged_in ) {
		$user_address = edd_get_customer_address();

		foreach ( $customer['address'] as $key => $field ) {
			if ( empty( $field ) && ! empty( $user_address[ $key ] ) ) {
				$customer['address'][ $key ] = $user_address[ $key ];
			} else {
				$customer['address'][ $key ] = '';
			}
		}
	}

	/**
	 * Filter the billing address details that will be pre-populated on the checkout form..
	 *
	 * @since 2.8
	 *
	 * @param array $address The customer address.
	 * @param array $customer The customer data from the session
	 */
	$customer['address'] = apply_filters( 'edd_checkout_billing_details_address', $customer['address'], $customer );

	// Create a country list.
	$countries        = edd_get_country_list();
	$selected_country = edd_get_shop_country();

	if ( ! empty( $customer['address']['country'] ) && '*' !== $customer['address']['country'] ) {
		$selected_country = $customer['address']['country'];
	}

	// Create a state list.
	$states         = edd_get_shop_states( $selected_country );
	$selected_state = edd_get_shop_state();

	if( ! empty( $customer['address']['state'] ) ) {
		$selected_state = $customer['address']['state'];
	}

	// Determine the state field.
	if ( ! empty( $states ) ) {
		$state_field = EDD()->html->select( array(
			'id'               => 'card_state',
			'name'             => 'card_state',
			'options'          => $states,
			'selected'         => $selected_state,
			'class'            => 'card_state',
			'show_option_none' => false,
			'show_option_all'  => false,
			'required'         => edd_field_is_required( 'card_state' ),
		) );
	} else {
		$state_field = EDD()->html->text( array(
			'id'          => 'card_state',
			'name'        => 'card_state',
			'value'       => ! empty( $customer['address']['state'] ) ? $customer['address']['state'] : '',
			'required'    => edd_field_is_required( 'card_state' ),
			'placeholder' => __( 'State / Province', 'easy-digital-downloads' ),
			'wrapper'     => false,
		) );
	}
?>

	<fieldset id="edd_cc_address" class="cc-address">
		<legend><?php esc_html_e( 'Billing Details', 'easy-digital-downloads' ); ?></legend>

		<?php
		/**
		 * Allow output at the top of the credit card billing address form.
		 *
		 * @since 1.0
		 */
		do_action( 'edd_cc_billing_top' );

		echo EDD()->html->text( array(
			'id'          => 'card_address',
			'name'        => 'card_address',
			'label'       => __( 'Billing Address', 'easy-digital-downloads' ),
			'desc'        => __( 'The primary billing address for your credit card.', 'easy-digital-downloads' ),
			'placeholder' => __( 'Address line 1', 'easy-digital-downloads' ),
			'value'       => $customer['address']['line1'],
			'required'    => edd_field_is_required( 'card_address' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->text( array(
			'id'          => 'card_address_2',
			'name'        => 'card_address_2',
			'label'       => __( 'Billing Address Line 2 (optional)', 'easy-digital-downloads' ),
			'desc'        => __( 'The suite, apt no, PO box, etc, associated with your billing address.', 'easy-digital-downloads' ),
			'placeholder' => __( 'Address line 2', 'easy-digital-downloads' ),
			'value'       => $customer['address']['line2'],
			'required'    => edd_field_is_required( 'card_address_2' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->text( array(
			'id'          => 'card_city',
			'name'        => 'card_city',
			'label'       => __( 'Billing City', 'easy-digital-downloads' ),
			'desc'        => __( 'The city for your billing address.', 'easy-digital-downloads' ),
			'placeholder' => __( 'City', 'easy-digital-downloads' ),
			'value'       => $customer['address']['city'],
			'required'    => edd_field_is_required( 'card_city' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->text( array(
			'id'          => 'card_zip',
			'name'        => 'card_zip',
			'label'       => __( 'Billing Zip / Postal Code', 'easy-digital-downloads' ),
			'desc'        => __( 'The zip or postal code for your billing address.', 'easy-digital-downloads' ),
			'placeholder' => __( 'Zip / Postal Code', 'easy-digital-downloads' ),
			'value'       => $customer['address']['zip'],
			'required'    => edd_field_is_required( 'card_zip' ),
			'wrapper_tag' => 'p',
		) ); // WPCS: XSS okay.

		echo EDD()->html->labeled_field(
			array(
				'id'       => 'billing_country',
				'name'     => 'billing_country',
				'label'    => __( 'Billing Country', 'easy-digital-downloads' ),
				'desc'     => __( 'The country for your billing address.', 'easy-digital-downloads' ),
				'required' => edd_field_is_required( 'billing_country' ),
			),
			EDD()->html->select( array(
				'id'               => 'billing_country',
				'name'             => 'billing_country',
				'options'          => $countries,
				'selected'         => $selected_country,
				'class'            => 'billing_country',
				'show_option_none' => false,
				'show_option_all'  => false,
				'required'         => edd_field_is_required( 'billing_country' ),
				'data'             => array(
					'nonce' => wp_create_nonce( 'edd-country-field-nonce' )
				),
			) )
		);

		echo EDD()->html->labeled_field(
			array(
				'id'       => 'card_state',
				'name'     => 'card_state',
				'label'    => __( 'Billing State / Province', 'easy-digital-downloads' ),
				'desc'     => __( 'The state or province for your billing address.', 'easy-digital-downloads' ),
				'required' => edd_field_is_required( 'card_state' )
			),
			$state_field
		);

		/**
		 * Allow output at the bottom of the credit card billing address form.
		 *
		 * @since 1.0
		 */
		do_action( 'edd_cc_billing_bottom' );

		wp_nonce_field( 'edd-checkout-address-fields', 'edd-checkout-address-fields-nonce', false, true );
		?>
	</fieldset>
	<?php
}
add_action( 'edd_after_cc_fields', 'edd_default_cc_address_fields' );


/**
 * Renders the billing address fields for cart taxation.
 *
 * @since 1.6
 */
function edd_checkout_tax_fields() {
	if ( edd_cart_needs_tax_address_fields() && edd_get_cart_total() ) {
		edd_default_cc_address_fields();
	}
}
add_action( 'edd_purchase_form_after_cc_form', 'edd_checkout_tax_fields', 999 );


/**
 * Renders the user registration fields. If the user is logged in, a login
 * form is displayed other a registration form is provided for the user to
 * create an account.
 *
 * @since 1.0
 *
 * @return string
 */
function edd_get_register_fields() {
	$show_register_form = edd_get_option( 'show_register_form', 'none' );

	ob_start(); ?>
	<fieldset id="edd_register_fields">

		<?php if ( 'both' === $show_register_form ) { ?>
			<p id="edd-login-account-wrap"><?php _e( 'Already have an account?', 'easy-digital-downloads' ); ?> <a href="<?php echo esc_url( add_query_arg( 'login', 1 ) ); ?>" class="edd_checkout_register_login" data-action="checkout_login" data-nonce="<?php echo wp_create_nonce( 'edd_checkout_login' ); ?>"><?php _e( 'Login', 'easy-digital-downloads' ); ?></a></p>
		<?php } ?>

		<?php do_action( 'edd_register_fields_before' ); ?>

		<fieldset id="edd_register_account_fields">
			<legend><?php _e( 'Create an account', 'easy-digital-downloads' ); if( !edd_no_guest_checkout() ) { echo ' ' . __( '(optional)', 'easy-digital-downloads' ); } ?></legend>
			<?php do_action( 'edd_register_account_fields_before' ); ?>
			<p id="edd-user-login-wrap">
				<label for="edd_user_login">
					<?php _e( 'Username', 'easy-digital-downloads' ); ?>
					<?php if ( edd_no_guest_checkout() ) : ?>
					<span class="edd-required-indicator">*</span>
					<?php endif; ?>
				</label>
				<span class="edd-description"><?php _e( 'The username you will use to log into your account.', 'easy-digital-downloads' ); ?></span>
				<input name="edd_user_login" id="edd_user_login" class="<?php if(edd_no_guest_checkout()) { echo sanitize_html_class( 'required ' ); } ?>edd-input" type="text" placeholder="<?php _e( 'Username', 'easy-digital-downloads' ); ?>"/>
			</p>
			<p id="edd-user-pass-wrap">
				<label for="edd_user_pass">
					<?php _e( 'Password', 'easy-digital-downloads' ); ?>
					<?php if ( edd_no_guest_checkout() ) : ?>
					<span class="edd-required-indicator">*</span>
					<?php endif; ?>
				</label>
				<span class="edd-description"><?php _e( 'The password used to access your account.', 'easy-digital-downloads' ); ?></span>
				<input name="edd_user_pass" id="edd_user_pass" class="<?php if(edd_no_guest_checkout()) { echo sanitize_html_class( 'required ' ); } ?>edd-input" placeholder="<?php _e( 'Password', 'easy-digital-downloads' ); ?>" type="password"/>
			</p>
			<p id="edd-user-pass-confirm-wrap" class="edd_register_password">
				<label for="edd_user_pass_confirm">
					<?php _e( 'Password Again', 'easy-digital-downloads' ); ?>
					<?php if ( edd_no_guest_checkout() ) : ?>
					<span class="edd-required-indicator">*</span>
					<?php endif; ?>
				</label>
				<span class="edd-description"><?php _e( 'Confirm your password.', 'easy-digital-downloads' ); ?></span>
				<input name="edd_user_pass_confirm" id="edd_user_pass_confirm" class="<?php if ( edd_no_guest_checkout() ) { echo sanitize_html_class( 'required ' ); } ?>edd-input" placeholder="<?php _e( 'Confirm password', 'easy-digital-downloads' ); ?>" type="password"/>
			</p>
			<?php do_action( 'edd_register_account_fields_after' ); ?>
		</fieldset>

		<?php do_action('edd_register_fields_after'); ?>

		<input type="hidden" name="edd-purchase-var" value="needs-to-register"/>

		<?php do_action( 'edd_purchase_form_user_info' ); ?>
		<?php do_action( 'edd_purchase_form_user_register_fields' ); ?>

	</fieldset>
	<?php
	echo ob_get_clean();
}
add_action( 'edd_purchase_form_register_fields', 'edd_get_register_fields' );

/**
 * Gets the login fields for the login form on the checkout. This function hooks
 * on the edd_purchase_form_login_fields to display the login form if a user already
 * had an account.
 *
 * @since 1.0
 * @return string
 */
function edd_get_login_fields() {
	$color = edd_get_option( 'checkout_color', 'gray' );

	$color = 'inherit' === $color
		? ''
		: $color;

	$style = edd_get_option( 'button_style', 'button' );

	$show_register_form = edd_get_option( 'show_register_form', 'none' );

	ob_start(); ?>
		<fieldset id="edd_login_fields">
			<?php if ( 'both' === $show_register_form ) : ?>
				<p id="edd-new-account-wrap">
					<?php _e( 'Need to create an account?', 'easy-digital-downloads' ); ?>
					<a href="<?php echo esc_url( remove_query_arg( 'login' ) ); ?>" class="edd_checkout_register_login" data-action="checkout_register"  data-nonce="<?php echo wp_create_nonce( 'edd_checkout_register' ); ?>">
						<?php _e( 'Register', 'easy-digital-downloads' ); if ( ! edd_no_guest_checkout() ) { echo esc_html( ' ' . __( 'or checkout as a guest.', 'easy-digital-downloads' ) ); } ?>
					</a>
				</p>
			<?php endif; ?>

			<?php do_action( 'edd_checkout_login_fields_before' ); ?>

			<p id="edd-user-login-wrap">
				<label class="edd-label" for="edd_user_login">
					<?php _e( 'Username or Email', 'easy-digital-downloads' ); ?>
					<?php if ( edd_no_guest_checkout() ) : ?>
					<span class="edd-required-indicator">*</span>
					<?php endif; ?>
				</label>
				<input class="<?php if(edd_no_guest_checkout()) { echo sanitize_html_class( 'required ' ); } ?>edd-input" type="text" name="edd_user_login" id="edd_user_login" value="" placeholder="<?php _e( 'Your username or email address', 'easy-digital-downloads' ); ?>"/>
			</p>
			<p id="edd-user-pass-wrap" class="edd_login_password">
				<label class="edd-label" for="edd_user_pass">
					<?php _e( 'Password', 'easy-digital-downloads' ); ?>
					<?php if ( edd_no_guest_checkout() ) : ?>
					<span class="edd-required-indicator">*</span>
					<?php endif; ?>
				</label>
				<input class="<?php if ( edd_no_guest_checkout() ) { echo sanitize_html_class( 'required '); } ?>edd-input" type="password" name="edd_user_pass" id="edd_user_pass" placeholder="<?php _e( 'Your password', 'easy-digital-downloads' ); ?>"/>
				<?php if ( edd_no_guest_checkout() ) : ?>
					<input type="hidden" name="edd-purchase-var" value="needs-to-login"/>
				<?php endif; ?>
			</p>
			<p id="edd-user-login-submit">
				<input type="submit" class="edd-submit <?php echo sanitize_html_class( $color ); ?> <?php echo sanitize_html_class( $style ); ?>" name="edd_login_submit" value="<?php _e( 'Login', 'easy-digital-downloads' ); ?>"/>
				<?php wp_nonce_field( 'edd-login-form', 'edd_login_nonce', false, true ); ?>
			</p>

			<?php do_action( 'edd_checkout_login_fields_after' ); ?>
		</fieldset><!--end #edd_login_fields-->
	<?php
	echo ob_get_clean();
}
add_action( 'edd_purchase_form_login_fields', 'edd_get_login_fields' );

/**
 * Renders the payment mode form by getting all the enabled payment gateways and
 * outputting them as radio buttons for the user to choose the payment gateway. If
 * a default payment gateway has been chosen from the EDD Settings, it will be
 * automatically selected.
 *
 * @since 1.2.2
 */
function edd_payment_mode_select() {
	$gateways = edd_get_enabled_payment_gateways( true );
	$page_URL = edd_get_current_page_url();
	$chosen_gateway = edd_get_chosen_gateway();
	?>
	<div id="edd_payment_mode_select_wrap">
		<?php do_action('edd_payment_mode_top'); ?>

		<?php if( edd_is_ajax_disabled() ) { ?>
		<form id="edd_payment_mode" action="<?php echo $page_URL; ?>" method="GET">
		<?php } ?>

			<fieldset id="edd_payment_mode_select">
				<legend><?php _e( 'Select Payment Method', 'easy-digital-downloads' ); ?></legend>
				<?php do_action( 'edd_payment_mode_before_gateways_wrap' ); ?>
				<div id="edd-payment-mode-wrap">
					<?php
					do_action( 'edd_payment_mode_before_gateways' );

					foreach ( $gateways as $gateway_id => $gateway ) {
						$label         = apply_filters( 'edd_gateway_checkout_label_' . $gateway_id, $gateway['checkout_label'] );
						$checked       = checked( $gateway_id, $chosen_gateway, false );
						$checked_class = $checked ? ' edd-gateway-option-selected' : '';
						$nonce         = ' data-' . esc_attr( $gateway_id ) . '-nonce="' . wp_create_nonce( 'edd-gateway-selected-' . esc_attr( $gateway_id ) ) .'"';

						echo '<label for="edd-gateway-' . esc_attr( $gateway_id ) . '" class="edd-gateway-option' . $checked_class . '" id="edd-gateway-option-' . esc_attr( $gateway_id ) . '">';
							echo '<input type="radio" name="payment-mode" class="edd-gateway" id="edd-gateway-' . esc_attr( $gateway_id ) . '" value="' . esc_attr( $gateway_id ) . '"' . $checked . $nonce . '>' . esc_html( $label );
						echo '</label>';
					}

					do_action( 'edd_payment_mode_after_gateways' );
					?>
				</div>

				<?php do_action( 'edd_payment_mode_after_gateways_wrap' ); ?>
			</fieldset>

			<fieldset id="edd_payment_mode_submit" class="edd-no-js">
				<p id="edd-next-submit-wrap">
					<?php echo edd_checkout_button_next(); ?>
				</p>
			</fieldset>

		<?php if ( edd_is_ajax_disabled() ) : ?>
		</form>
		<?php endif; ?>

	</div>
	<div id="edd_purchase_form_wrap"></div><!-- the checkout fields are loaded into this-->

	<?php do_action( 'edd_payment_mode_bottom' );
}
add_action( 'edd_payment_mode_select', 'edd_payment_mode_select' );

/**
 * Show Payment Icons by getting all the accepted icons from the EDD Settings
 * then outputting the icons.
 *
 * @since 1.0
 * @return void
*/
function edd_show_payment_icons() {

	if ( edd_show_gateways() && did_action( 'edd_payment_mode_top' ) ) {
		return;
	}

	$payment_methods = edd_get_option( 'accepted_cards', array() );

	if ( empty( $payment_methods ) ) {
		return;
	}

	// Get the icon order option
	$order = edd_get_option( 'payment_icons_order', '' );

	// If order is set, enforce it
	if ( ! empty( $order ) ) {
		$order           = array_flip( explode( ',', $order ) );
		$order           = array_intersect_key( $order, $payment_methods );
		$payment_methods = array_merge( $order, $payment_methods );
	}

	echo '<div class="edd-payment-icons">';

	foreach ( $payment_methods as $key => $option ) {
		if ( edd_string_is_image_url( $key ) ) {
			echo '<img class="payment-icon" src="' . esc_url( $key ) . '"/>';
		} else {
			$type = '';
			$card = strtolower( str_replace( ' ', '', $option ) );

			if ( has_filter( 'edd_accepted_payment_' . $card . '_image' ) ) {
				$image = apply_filters( 'edd_accepted_payment_' . $card . '_image', '' );

			} elseif ( has_filter( 'edd_accepted_payment_' . $key . '_image' ) ) {
				$image = apply_filters( 'edd_accepted_payment_' . $key  . '_image', '' );

			} else {
				// Set the type to SVG.
				$type = 'svg';

				// Get SVG dimensions.
				$dimensions = edd_get_payment_icon_dimensions( $key );

				// Get SVG markup.
				$image = edd_get_payment_icon(
					array(
						'icon'    => $key,
						'width'   => $dimensions['width'],
						'height'  => $dimensions['height'],
						'title'   => $option,
						'classes' => array( 'payment-icon' )
					)
				);
			}

			if ( edd_is_ssl_enforced() || is_ssl() ) {
				$image = edd_enforced_ssl_asset_filter( $image );
			}

			if ( 'svg' === $type ) {
				echo $image;
			} else {
				echo '<img class="payment-icon" src="' . esc_url( $image ) . '"/>';
			}
		}
	}

	echo '</div>';
}
add_action( 'edd_payment_mode_top', 'edd_show_payment_icons' );
add_action( 'edd_checkout_form_top', 'edd_show_payment_icons' );


/**
 * Renders the Discount Code field which allows users to enter a discount code.
 * This field is only displayed if there are any active discounts on the site else
 * it's not displayed.
 *
 * @since 1.2.2
 * @return void
*/
function edd_discount_field() {
	if ( isset( $_GET['payment-mode'] ) && edd_is_ajax_disabled() ) {
		return; // Only show before a payment method has been selected if ajax is disabled
	}

	if ( ! edd_is_checkout() ) {
		return;
	}

	if ( edd_has_active_discounts() && edd_get_cart_total() ) :
		$color = edd_get_option( 'checkout_color', 'blue' );
		$color = ( $color == 'inherit' ) ? '' : $color;
		$style = edd_get_option( 'button_style', 'button' ); ?>
		<fieldset id="edd_discount_code">
			<p id="edd_show_discount" style="display:none;">
				<?php _e( 'Have a discount code?', 'easy-digital-downloads' ); ?> <a href="#" class="edd_discount_link"><?php echo _x( 'Click to enter it', 'Entering a discount code', 'easy-digital-downloads' ); ?></a>
			</p>
			<p id="edd-discount-code-wrap" class="edd-cart-adjustment">
				<label class="edd-label" for="edd-discount">
					<?php _e( 'Discount', 'easy-digital-downloads' ); ?>
				</label>
				<span class="edd-description"><?php _e( 'Enter a coupon code if you have one.', 'easy-digital-downloads' ); ?></span>
				<span class="edd-discount-code-field-wrap">
					<input class="edd-input" type="text" id="edd-discount" name="edd-discount" placeholder="<?php _e( 'Enter discount', 'easy-digital-downloads' ); ?>"/>
					<input type="submit" class="edd-apply-discount edd-submit <?php echo sanitize_html_class( $color ); ?> <?php echo sanitize_html_class( $style ); ?>" value="<?php echo _x( 'Apply', 'Apply discount at checkout', 'easy-digital-downloads' ); ?>"/>
				</span>
				<span class="edd-discount-loader edd-loading" id="edd-discount-loader" style="display:none;"></span>
				<span id="edd-discount-error-wrap" class="edd_error edd-alert edd-alert-error" aria-hidden="true" style="display:none;"></span>
			</p>
		</fieldset><?php
	endif;
}
add_action( 'edd_checkout_form_top', 'edd_discount_field', -1 );

/**
 * Renders the Checkout Agree to Terms, this displays a checkbox for users to
 * agree the T&Cs set in the EDD Settings. This is only displayed if T&Cs are
 * set in the EDD Settings.
 *
 * @since 1.3.2
 */
function edd_terms_agreement() {
	if ( edd_get_option( 'show_agree_to_terms', false ) ) {
		$agree_text  = edd_get_option( 'agree_text', '' );
		$agree_label = edd_get_option( 'agree_label', __( 'Agree to Terms?', 'easy-digital-downloads' ) );

		ob_start(); ?>
		<fieldset id="edd_terms_agreement">
			<div id="edd_terms" class="edd-terms" style="display:none;">
				<?php
					do_action( 'edd_before_terms' );
					echo wpautop( stripslashes( $agree_text ) );
					do_action( 'edd_after_terms' );
				?>
			</div>
			<div id="edd_show_terms" class="edd-show-terms">
				<a href="#" class="edd_terms_links"><?php _e( 'Show Terms', 'easy-digital-downloads' ); ?></a>
				<a href="#" class="edd_terms_links" style="display:none;"><?php _e( 'Hide Terms', 'easy-digital-downloads' ); ?></a>
			</div>

			<?php if ( '1' !== edd_get_option( 'show_agree_to_privacy_policy', false ) && '1' === edd_get_option( 'show_privacy_policy_on_checkout', false ) ) : ?>
				<?php
				$privacy_page = get_option( 'wp_page_for_privacy_policy' );

				if ( ! empty( $privacy_page ) ) :
					$privacy_text = get_post_field( 'post_content', $privacy_page );

					if ( ! empty( $privacy_text ) ) : ?>
						<div id="edd-privacy-policy" class="edd-terms" style="display:none;">
							<?php
							do_action( 'edd_before_privacy_policy' );
							echo wpautop( do_shortcode( stripslashes( $privacy_text ) ) );
							do_action( 'edd_after_privacy_policy' );
							?>
						</div>
						<div id="edd-show-privacy-policy" class="edd-show-terms">
							<a href="#" class="edd_terms_links"><?php _e( 'Show Privacy Policy', 'easy-digital-downloads' ); ?></a>
							<a href="#" class="edd_terms_links" style="display:none;"><?php _e( 'Hide Privacy Policy', 'easy-digital-downloads' ); ?></a>
						</div>
						<?php
					endif;
				endif;
			endif ?>
			<div class="edd-terms-agreement">
				<input name="edd_agree_to_terms" class="required" type="checkbox" id="edd_agree_to_terms" value="1"/>
				<label for="edd_agree_to_terms"><?php echo stripslashes( $agree_label ); ?></label>
			</div>
		</fieldset><?php

		$html_output = ob_get_clean();

		echo apply_filters( 'edd_checkout_terms_agreement_html', $html_output );
	}
}
add_action( 'edd_purchase_form_before_submit', 'edd_terms_agreement' );


/**
 * Renders the Checkout Agree to Privacy Policy, this displays a checkbox for users to
 * agree the Privacy Policy set in the EDD Settings. This is only displayed if T&Cs are
 * set in the EDD Settings.
 *
 * @since 2.9.1
 */
function edd_privacy_agreement() {
	if ( '1' === edd_get_option( 'show_agree_to_privacy_policy', false ) ) {
		$agree_label = edd_get_option( 'privacy_agree_label', __( 'Agree to Terms?', 'easy-digital-downloads' ) );

		ob_start(); ?>
		<fieldset id="edd-privacy-policy-agreement">
			<?php if ( '1' === edd_get_option( 'show_privacy_policy_on_checkout', false ) ) : ?>
				<?php
				$privacy_page = get_option( 'wp_page_for_privacy_policy' );

				if ( ! empty( $privacy_page ) ) :
					$privacy_text = get_post_field( 'post_content', $privacy_page );

					if ( ! empty( $privacy_text  ) ) : ?>
						<div id="edd-privacy-policy" class="edd-terms" style="display:none;">
							<?php
							do_action( 'edd_before_privacy_policy' );
							echo wpautop( do_shortcode( stripslashes( $privacy_text ) ) );
							do_action( 'edd_after_privacy_policy' );
							?>
						</div>
						<div id="edd-show-privacy-policy" class="edd-show-terms">
							<a href="#" class="edd_terms_links"><?php _e( 'Show Privacy Policy', 'easy-digital-downloads' ); ?></a>
							<a href="#" class="edd_terms_links" style="display:none;"><?php _e( 'Hide Privacy Policy', 'easy-digital-downloads' ); ?></a>
						</div>
						<?php
					endif;
				endif;
			endif ?>

			<div class="edd-privacy-policy-agreement">
				<input name="edd_agree_to_privacy_policy" class="required" type="checkbox" id="edd-agree-to-privacy-policy" value="1"/>
				<label for="edd-agree-to-privacy-policy"><?php echo stripslashes( $agree_label ); ?></label>
			</div>
		</fieldset>
		<?php
		$html_output = ob_get_clean();

		echo apply_filters( 'edd_checkout_privacy_policy_agreement_html', $html_output );
	}
}
add_action( 'edd_purchase_form_before_submit', 'edd_privacy_agreement' );

/**
 * Shows the final purchase total at the bottom of the checkout page.
 *
 * @since 1.5
 */
function edd_checkout_final_total() {
?>
<p id="edd_final_total_wrap">
	<strong><?php _e( 'Purchase Total:', 'easy-digital-downloads' ); ?></strong>
	<span class="edd_cart_amount" data-subtotal="<?php echo edd_get_cart_subtotal(); ?>" data-total="<?php echo edd_get_cart_total(); ?>"><?php edd_cart_total(); ?></span>
</p>
<?php
}
add_action( 'edd_purchase_form_before_submit', 'edd_checkout_final_total', 999 );

/**
 * Renders the Checkout Submit section.
 *
 * @since 1.3.3
 */
function edd_checkout_submit() {
?>
	<fieldset id="edd_purchase_submit">
		<?php do_action( 'edd_purchase_form_before_submit' ); ?>

		<?php edd_checkout_hidden_fields(); ?>

		<?php echo edd_checkout_button_purchase(); ?>

		<?php do_action( 'edd_purchase_form_after_submit' ); ?>

		<?php if ( edd_is_ajax_disabled() ) : ?>
			<p class="edd-cancel"><a href="<?php echo edd_get_checkout_uri(); ?>"><?php _e( 'Go back', 'easy-digital-downloads' ); ?></a></p>
		<?php endif; ?>
	</fieldset>
<?php
}
add_action( 'edd_purchase_form_after_cc_form', 'edd_checkout_submit', 9999 );

/**
 * Renders the Next button on the Checkout
 *
 * @since 1.2
 * @return string
 */
function edd_checkout_button_next() {
	$color = edd_get_option( 'checkout_color', 'blue' );
	$color = ( $color == 'inherit' ) ? '' : $color;
	$style = edd_get_option( 'button_style', 'button' );
	$purchase_page = edd_get_option( 'purchase_page', '0' );

	ob_start(); ?>
	<input type="hidden" name="edd_action" value="gateway_select" />
	<input type="hidden" name="page_id" value="<?php echo absint( $purchase_page ); ?>"/>
	<input type="submit" name="gateway_submit" id="edd_next_button" class="edd-submit <?php echo sanitize_html_class( $color ); ?> <?php echo sanitize_html_class( $style ); ?>" value="<?php _e( 'Next', 'easy-digital-downloads' ); ?>"/>

	<?php
	return apply_filters( 'edd_checkout_button_next', ob_get_clean() );
}

/**
 * Renders the Purchase button on the Checkout
 *
 * @since 1.2
 * @return string
 */
function edd_checkout_button_purchase() {
	$color = edd_get_option( 'checkout_color', 'blue' );
	$color = ( $color == 'inherit' ) ? '' : $color;
	$style = edd_get_option( 'button_style', 'button' );
	$label = edd_get_checkout_button_purchase_label();

	ob_start(); ?>
	<input type="submit" class="edd-submit <?php echo sanitize_html_class( $color ); ?> <?php echo sanitize_html_class( $style ); ?>" id="edd-purchase-button" name="edd-purchase" value="<?php echo $label; ?>"/>
	<?php
	return apply_filters( 'edd_checkout_button_purchase', ob_get_clean() );
}

/**
 * Retrieves the label for the purchase button.
 *
 * @since 2.7.6
 *
 * @return string Purchase button label.
 */
function edd_get_checkout_button_purchase_label() {
	if ( edd_get_cart_total() ) {
		$label             = edd_get_option( 'checkout_label', '' );
		$complete_purchase = ! empty( $label )
			? $label
			: __( 'Purchase', 'easy-digital-downloads' );
	} else {
		$label             = edd_get_option( 'free_checkout_label', '' );
		$complete_purchase = ! empty( $label )
			? $label
			: __( 'Free Download', 'easy-digital-downloads' );
	}

	return apply_filters( 'edd_get_checkout_button_purchase_label', $complete_purchase, $label );
}

/**
 * Outputs the JavaScript code for the Agree to Terms section to toggle
 * the T&Cs text
 *
 * @since 1.0
 */
function edd_agree_to_terms_js() {
	if ( edd_get_option( 'show_agree_to_terms', false ) || edd_get_option( 'show_agree_to_privacy_policy', false ) ) : ?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$( document.body ).on( 'click', '.edd_terms_links', function() {
				$( this ).parent().prev( '.edd-terms' ).slideToggle();
				$( this ).parent().find( '.edd_terms_links' ).toggle();

				return false;
			});
		});
	</script><?php
	endif;
}
add_action( 'edd_checkout_form_top', 'edd_agree_to_terms_js' );

/**
 * Renders the hidden Checkout fields
 *
 * @since 1.3.2
 */
function edd_checkout_hidden_fields() {
	if ( is_user_logged_in() ) : ?>
	<input type="hidden" name="edd-user-id" value="<?php echo get_current_user_id(); ?>"/>
	<?php endif; ?>
	<input type="hidden" name="edd_action" value="purchase"/>
	<input type="hidden" name="edd-gateway" value="<?php echo edd_get_chosen_gateway(); ?>" />
	<?php wp_nonce_field( 'edd-process-checkout', 'edd-process-checkout-nonce', false, true );
}

/**
 * Applies filters to the success page content.
 *
 * @since 1.0
 *
 * @param string $content Content before filters.
 * @return string $content Filtered content.
 */
function edd_filter_success_page_content( $content ) {
	if ( isset( $_GET['payment-confirmation'] ) && edd_is_success_page() ) {
		if ( has_filter( 'edd_payment_confirm_' . $_GET['payment-confirmation'] ) ) {
			$content = apply_filters( 'edd_payment_confirm_' . $_GET['payment-confirmation'], $content );
		}
	}

	return $content;
}
add_filter( 'the_content', 'edd_filter_success_page_content', 99999 );

/**
 * Show a download's files in the purchase receipt.
 *
 * @since 1.8.6
 *
 * @param  int   $item_id      Download ID.
 * @param  array $receipt_args Args specified in the [edd_receipt] shortcode.
 * @param  array $item         Cart item array.
 *
 * @return bool True if files should be shown, false otherwise.
 */
function edd_receipt_show_download_files( $item_id, $receipt_args, $item = array() ) {
	$ret = true;

	/*
	 * If re-download is disabled, set return to false.
	 *
	 * When the purchase session is still present AND the receipt being shown is for that purchase,
	 * file download links are still shown. Once session expires, links are disabled.
	 */
	if ( edd_no_redownload() ) {
		$key = isset( $_GET['payment_key'] )
			? sanitize_text_field( $_GET['payment_key'] )
			: '';

		$session = edd_get_purchase_session();

		// We have session data but the payment key provided is not for this session.
		if ( ! empty( $key ) && ! empty( $session ) && $key != $session['purchase_key'] ) {
			$ret = false;

		// No session data is present but a key has been provided.
		} elseif ( empty( $session ) ) {
			$ret = false;
		}
	}

	return apply_filters( 'edd_receipt_show_download_files', $ret, $item_id, $receipt_args, $item );
}
