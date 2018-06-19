<?php
/**
 * Formatting functions for taking care of proper number formats and such
 *
 * @package     EDD
 * @subpackage  Functions/Formatting
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Sanitize Amount
 *
 * Returns a sanitized amount by stripping out thousands separators.
 *
 * @since 1.0
 * @param string $amount Price amount to format
 * @return string $amount Newly sanitized amount
 */
function edd_sanitize_amount( $amount = '' ) {
	$is_negative   = false;
	$thousands_sep = edd_get_option( 'thousands_separator', ',' );
	$decimal_sep   = edd_get_option( 'decimal_separator',   '.' );

	// Sanitize the amount
	if ( $decimal_sep === ',' && false !== ( $found = strpos( $amount, $decimal_sep ) ) ) {
		if ( ( $thousands_sep == '.' || $thousands_sep == ' ' ) && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
			$amount = str_replace( $thousands_sep, '', $amount );
		} elseif( empty( $thousands_sep ) && false !== ( $found = strpos( $amount, '.' ) ) ) {
			$amount = str_replace( '.', '', $amount );
		}

		$amount = str_replace( $decimal_sep, '.', $amount );
	} elseif( $thousands_sep === ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( $thousands_sep, '', $amount );
	}

	// Negative
	if ( $amount < 0 ) {
		$is_negative = true;
	}

	// Only numbers
	$amount = preg_replace( '/[^0-9\.]/', '', $amount );

	/**
	 * Filter number of decimals to use for prices
	 *
	 * @since unknown
	 *
	 * @param int $number Number of decimals
	 * @param int|string $amount Price
	 */
	$decimals = apply_filters( 'edd_sanitize_amount_decimals', 2, $amount );
	$amount   = number_format( (float) $amount, $decimals, '.', '' );

	if ( true === $is_negative ) {
		$amount *= -1;
	}

	/**
	 * Filter the sanitized price before returning
	 *
	 * @since unknown
	 *
	 * @param string $amount Price
	 */
	return apply_filters( 'edd_sanitize_amount', $amount );
}

/**
 * Returns a nicely formatted amount.
 *
 * @since 1.0
 *
 * @param string $amount   Price amount to format
 * @param string $decimals Whether or not to use decimals.  Useful when set to false for non-currency numbers.
 *
 * @return string $amount Newly formatted amount or Price Not Available
 */
function edd_format_amount( $amount, $decimals = true ) {
	$thousands_sep = edd_get_option( 'thousands_separator', ',' );
	$decimal_sep   = edd_get_option( 'decimal_separator',   '.' );

	// Format the amount
	if ( $decimal_sep === ',' && false !== ( $sep_found = strpos( $amount, $decimal_sep ) ) ) {
		$whole  = substr( $amount, 0, $sep_found );
		$part   = substr( $amount, $sep_found + 1, ( strlen( $amount ) - 1 ) );
		$amount = $whole . '.' . $part;
	}

	// Strip , from the amount (if set as the thousands separator)
	if ( $thousands_sep === ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( ',', '', $amount );
	}

	// Strip ' ' from the amount (if set as the thousands separator)
	if ( $thousands_sep === ' ' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( ' ', '', $amount );
	}

	if ( empty( $amount ) ) {
		$amount = 0;
	}

	$decimals  = apply_filters( 'edd_format_amount_decimals', $decimals ? 2 : 0, $amount );
	$formatted = number_format( (float) $amount, $decimals, $decimal_sep, $thousands_sep );

	return apply_filters( 'edd_format_amount', $formatted, $amount, $decimals, $decimal_sep, $thousands_sep );
}

/**
 * Formats the currency display
 *
 * @since 1.0
 * @param string $price Price
 * @return string $currency Currencies displayed correctly
 */
function edd_currency_filter( $price = '', $currency = '' ) {

	// Fallback to default currency
	if ( empty( $currency ) ) {
		$currency = edd_get_currency();
	}

	// Default vars
	$position = edd_get_option( 'currency_position', 'before' );
	$negative = $price < 0;

	// Remove proceeding "-" -
	if ( true === $negative ) {
		$price = substr( $price, 1 );
	}

	$symbol = edd_currency_symbol( $currency );

	if ( 'before' === $position ):
		switch ( $currency ):
			case 'GBP' :
			case 'BRL' :
			case 'EUR' :
			case 'USD' :
			case 'AUD' :
			case 'CAD' :
			case 'HKD' :
			case 'MXN' :
			case 'NZD' :
			case 'SGD' :
			case 'JPY' :
				$formatted = $symbol . $price;
				break;
			default :
				$formatted = $currency . ' ' . $price;
				break;
		endswitch;
		$formatted = apply_filters( 'edd_' . strtolower( $currency ) . '_currency_filter_before', $formatted, $currency, $price );
	else :
		switch ( $currency ) :
			case 'GBP' :
			case 'BRL' :
			case 'EUR' :
			case 'USD' :
			case 'AUD' :
			case 'CAD' :
			case 'HKD' :
			case 'MXN' :
			case 'SGD' :
			case 'JPY' :
				$formatted = $price . $symbol;
				break;
			default :
				$formatted = $price . ' ' . $currency;
				break;
		endswitch;
		$formatted = apply_filters( 'edd_' . strtolower( $currency ) . '_currency_filter_after', $formatted, $currency, $price );
	endif;

	// Prepend the mins sign before the currency sign
	if ( true === $negative ) {
		$formatted = '-' . $formatted;
	}

	return $formatted;
}

/**
 * Set the number of decimal places per currency
 *
 * @since 1.4.2
 * @since 3.0 Updated to allow currency to be passed in.
 *
 * @param int    $decimals Number of decimal places.
 * @param string $currency Currency.
 *
 * @return int $decimals Number of decimal places for currency.
*/
function edd_currency_decimal_filter( $decimals = 2, $currency = '' ) {
	$currency = empty( $currency )
		? edd_get_currency()
		: $currency;

	switch ( $currency ) {
		case 'RIAL' :
		case 'JPY' :
		case 'TWD' :
		case 'HUF' :
			$decimals = 0;
			break;
	}

	return apply_filters( 'edd_currency_decimal_count', $decimals, $currency );
}
add_filter( 'edd_sanitize_amount_decimals', 'edd_currency_decimal_filter' );
add_filter( 'edd_format_amount_decimals',   'edd_currency_decimal_filter' );

/**
 * Sanitizes a string key for EDD Settings
 *
 * Keys are used as internal identifiers. Alphanumeric characters, dashes,
 * underscores, stops, colons and slashes are allowed.
 *
 * This differs from `sanitize_key()` in that it allows uppercase letters,
 * stops, colons, and slashes.
 *
 * @since  2.5.8
 * @param  string $key String key
 * @return string Sanitized key
 */
function edd_sanitize_key( $key = '' ) {
	$raw_key = $key;
	$key     = preg_replace( '/[^a-zA-Z0-9_\-\.\:\/]/', '', $key );

	/**
	 * Filter a sanitized key string.
	 *
	 * @since 2.5.8
	 * @param string $key     Sanitized key.
	 * @param string $raw_key The key prior to sanitization.
	 */
	return apply_filters( 'edd_sanitize_key', $key, $raw_key );
}

/**
 * Never let a numeric value be less than zero.
 *
 * Adapted from bbPress.
 *
 * @since 3.0
 *
 * @param int $number Default 0.
 * @return int.
 */
function edd_number_not_negative( $number = 0 ) {

	// Protect against formatted strings
	if ( is_string( $number ) ) {
		$number = strip_tags( $number );                    // No HTML
		$number = preg_replace( '/[^0-9-]/', '', $number ); // No number-format
		// Protect against objects, arrays, scalars, etc...
	} elseif ( ! is_numeric( $number ) ) {
		$number = 0;
	}

	// Make the number an integer
	$casted_number = is_float( $number )
		? floatval( $number )
		: intval( $number );

	$max_value = is_float( $number )
		? 0.00
		: 0;

	// Pick the maximum value, never less than zero
	$not_less_than_zero = max( $max_value, $casted_number );

	// Filter & return
	return (int) apply_filters( 'edd_number_not_negative', $not_less_than_zero, $casted_number, $number );
}

