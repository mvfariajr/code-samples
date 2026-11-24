<?php
/**
 * Plans page: assets, form handler and redirect state for template-plans.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FD_PLANS_TEMPLATE', 'template-plans.php' );
define( 'FD_PLANS_RECIPIENT', 'info@fariadesign.com' );
define( 'FD_PLANS_COOKIE', 'fd_plans_result' );

require_once __DIR__ . '/plans-inquiries.php';

/**
 * Form choices, shared by the template, the validator and the email.
 */
function fd_plans_options() {
	return array(
		'plans'    => array(
			'oncall'  => array( 'label' => 'On Call', 'detail' => '10 hrs / mo, $900/month' ),
			'ongoing' => array( 'label' => 'Ongoing', 'detail' => '20 hrs / mo, $1,700/month' ),
			'partner' => array( 'label' => 'Partner', 'detail' => '40 hrs / mo, $3,200/month' ),
			'custom'  => array( 'label' => 'Custom', 'detail' => '60+ hrs / mo, priced together' ),
			'unsure'  => array( 'label' => 'Not sure yet', 'detail' => 'wants a recommendation' ),
			'none'    => array( 'label' => 'No retainer', 'detail' => 'care plan only' ),
		),
		// Retainer tiers that get the $75 care plan rate.
		'paid'     => array( 'oncall', 'ongoing', 'partner', 'custom' ),
		'payments' => array(
			'card'    => 'Card or bank transfer (automatic monthly)',
			'paypal'  => 'PayPal',
			'invoice' => "Invoice (my company's own system)",
		),
	);
}

/**
 * Result of the last submission, read once from a short-lived transient.
 * A cookie carries only a random token, never the visitor's data, so the
 * page URL stays clean after the redirect.
 */
function fd_plans_state() {
	static $state = null;

	if ( null !== $state ) {
		return $state;
	}

	$state = array();
	$token = isset( $_COOKIE[ FD_PLANS_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ FD_PLANS_COOKIE ] ) ) : '';

	if ( preg_match( '/^[a-z0-9]{20}$/', $token ) ) {
		$saved = get_transient( 'fd_plans_' . $token );
		if ( is_array( $saved ) ) {
			$state = $saved;
			delete_transient( 'fd_plans_' . $token );
		}
	}

	return $state;
}

// Read the result before any output, so the cookie can be cleared and the page kept out of caches.
add_action( 'template_redirect', 'fd_plans_consume_state' );
function fd_plans_consume_state() {
	if ( ! isset( $_COOKIE[ FD_PLANS_COOKIE ] ) || ! is_page_template( FD_PLANS_TEMPLATE ) ) {
		return;
	}
	fd_plans_state();
	fd_plans_set_cookie( '', time() - HOUR_IN_SECONDS, wp_parse_url( get_permalink(), PHP_URL_PATH ) );
	nocache_headers();
}

function fd_plans_set_cookie( $value, $expires, $path ) {
	setcookie(
		FD_PLANS_COOKIE,
		$value,
		array(
			'expires'  => $expires,
			'path'     => $path ? $path : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/* Assets, only on the Plans template */
add_action( 'wp_enqueue_scripts', 'fd_plans_enqueue_assets', 30 );
function fd_plans_enqueue_assets() {
	if ( ! is_page_template( FD_PLANS_TEMPLATE ) ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style( 'fd-plans', $uri . '/assets/css/plans.css', array( 'child-theme' ), filemtime( $dir . '/assets/css/plans.css' ) );
	wp_enqueue_script( 'fd-plans', $uri . '/assets/js/plans.js', array(), filemtime( $dir . '/assets/js/plans.js' ), true );

	// GA4 through GTM: push the event once, on the page load right after a successful send.
	$state = fd_plans_state();
	if ( isset( $state['status'], $state['ga'] ) && 'sent' === $state['status'] ) {
		$event = array(
			'event'     => 'plan_inquiry',
			'plan'      => $state['ga']['plan'],
			'care_plan' => (bool) $state['ga']['care_plan'],
		);
		wp_add_inline_script( 'fd-plans', 'window.dataLayer = window.dataLayer || [];window.dataLayer.push(' . wp_json_encode( $event ) . ');', 'before' );
	}
}

/* Form submission through admin-post.php */
add_action( 'admin_post_nopriv_fd_plans_inquiry', 'fd_plans_handle_submission' );
add_action( 'admin_post_fd_plans_inquiry', 'fd_plans_handle_submission' );
function fd_plans_handle_submission() {
	$options = fd_plans_options();
	$page_id = isset( $_POST['fd_page'] ) ? absint( $_POST['fd_page'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$return  = fd_plans_return_url( $page_id );

	// Honeypot: bots get the normal success page and nothing is sent.
	if ( ! empty( $_POST['fd_hp_field'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		fd_plans_redirect( $return, array( 'status' => 'sent' ) );
	}

	$values = fd_plans_sanitize( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$nonce  = isset( $_POST['fd_plans_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fd_plans_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'fd_plans_submit' ) ) {
		$errors = array( 'form' => 'Your session expired. Please send the form again.' );
	} else {
		list( $errors, $sites ) = fd_plans_validate( $values, $options );
	}

	if ( $errors ) {
		fd_plans_redirect( $return, array( 'status' => 'error', 'errors' => $errors, 'values' => $values ) );
	}

	// Save first, so an inquiry is never lost if the notification email fails.
	$inquiry_id = fd_inquiries_save( $values, $sites, $options );
	$notified   = fd_plans_send( $values, $sites, $options );

	if ( $inquiry_id ) {
		update_post_meta( $inquiry_id, '_fd_notified', $notified ? 'yes' : 'no' );
	}

	if ( ! $inquiry_id && ! $notified ) {
		fd_plans_redirect(
			$return,
			array(
				'status' => 'error',
				'errors' => array( 'form' => "Sorry, the message couldn't be sent. Please try again or email info@fariadesign.com." ),
				'values' => $values,
			)
		);
	}

	fd_plans_redirect(
		$return,
		array(
			'status' => 'sent',
			'ga'     => array( 'plan' => $values['plan'] ? $values['plan'] : 'none', 'care_plan' => $values['care'] ),
		)
	);
}

function fd_plans_sanitize( $post ) {
	$options = fd_plans_options();
	$text    = function ( $key, $max ) use ( $post ) {
		return isset( $post[ $key ] ) && is_string( $post[ $key ] ) ? mb_substr( sanitize_text_field( $post[ $key ] ), 0, $max ) : '';
	};
	$area    = function ( $key, $max ) use ( $post ) {
		return isset( $post[ $key ] ) && is_string( $post[ $key ] ) ? mb_substr( sanitize_textarea_field( $post[ $key ] ), 0, $max ) : '';
	};

	$plan    = sanitize_key( $text( 'plan', 20 ) );
	$payment = sanitize_key( $text( 'payment', 20 ) );

	return array(
		'plan'            => isset( $options['plans'][ $plan ] ) ? $plan : '',
		'care'            => ! empty( $post['care'] ),
		'sites'           => $area( 'sites', 5000 ),
		'name'            => $text( 'name', 200 ),
		'company'         => $text( 'company', 200 ),
		'email'           => $text( 'email', 254 ),
		'needs'           => $area( 'needs', 5000 ),
		'start'           => $text( 'start', 10 ),
		'payment'         => isset( $options['payments'][ $payment ] ) ? $payment : '',
		'invoice_details' => $text( 'invoice_details', 300 ),
		'notes'           => $area( 'notes', 5000 ),
	);
}

/**
 * Returns array( errors keyed by field, normalized site URLs ).
 */
function fd_plans_validate( $values, $options ) {
	$errors = array();
	$sites  = array();

	// A retainer choice (including "Not sure yet") or the care plan switch.
	$has_plan = '' !== $values['plan'] && 'none' !== $values['plan'];
	if ( ! $has_plan && ! $values['care'] ) {
		$errors['plan'] = "Pick a retainer plan, \"Not sure yet\", or add a care plan so I know what you're interested in.";
	}

	if ( $values['care'] ) {
		list( $sites, $bad ) = fd_plans_parse_sites( $values['sites'] );
		if ( $bad ) {
			$errors['sites'] = 'Check the website URLs. Each line should be one address, like https://example.com.';
		} elseif ( ! $sites ) {
			$errors['sites'] = 'Add the URL of each site you want covered, one per line.';
		}
	}

	if ( '' === $values['name'] ) {
		$errors['name'] = 'Enter your name.';
	}

	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = 'Enter a valid email address.';
	}

	$start = fd_plans_parse_date( $values['start'] );
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	if ( ! $start || $start <= $today ) {
		$errors['start'] = 'Choose a start date after today.';
	}

	if ( '' === $values['payment'] ) {
		$errors['payment'] = 'Choose a payment method.';
	}

	return array( $errors, $sites );
}

function fd_plans_parse_date( $value ) {
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $date : null;
}

/**
 * Returns array( valid URLs, whether any line was not a valid URL ).
 */
function fd_plans_parse_sites( $text ) {
	$urls = array();
	$bad  = false;

	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		$url  = preg_match( '#^https?://#i', $line ) ? $line : 'https://' . $line;
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || ! $host || false === strpos( $host, '.' ) ) {
			$bad = true;
			continue;
		}

		$urls[] = esc_url_raw( $url );
	}

	return array( $urls, $bad );
}

/**
 * Short label for an inquiry, e.g. "Ongoing + Care plan (Example Co.)".
 * Used for the email subject and the saved inquiry's title.
 */
function fd_plans_summary( $values, $options ) {
	$plan_label = $values['plan'] ? $options['plans'][ $values['plan'] ]['label'] : 'No plan selected';

	if ( $values['care'] && in_array( $values['plan'], array( '', 'none' ), true ) ) {
		$summary = 'Care plan';
	} elseif ( $values['care'] ) {
		$summary = $plan_label . ' + Care plan';
	} else {
		$summary = $plan_label;
	}

	$who = '' !== $values['company'] ? $values['company'] : $values['name'];
	return sprintf( '%s (%s)', $summary, $who );
}

/**
 * Plain-text version of an inquiry. The 'email' context (notification and admin
 * search) adds a header and footer; 'reply' is just the submitted fields.
 */
function fd_plans_message( $values, $sites, $options, $context = 'email', $time = null ) {
	$plan_label = $values['plan'] ? $options['plans'][ $values['plan'] ]['label'] : '';
	$start      = fd_plans_parse_date( $values['start'] );

	$lines = array();
	if ( 'email' === $context ) {
		$lines[] = 'New plan inquiry from ' . home_url( '/plans/' );
		$lines[] = '';
	}
	$lines[] = 'Retainer plan: ' . ( $values['plan'] ? $plan_label . ' (' . $options['plans'][ $values['plan'] ]['detail'] . ')' : 'None selected' );
	$lines[] = 'Care plan: ' . ( $values['care'] ? 'Yes' : 'No' );
	if ( $values['care'] ) {
		$lines[] = 'Website URLs:';
		foreach ( $sites as $site ) {
			$lines[] = '  ' . $site;
		}
	}
	$lines[] = '';
	$lines[] = 'Name: ' . $values['name'];
	$lines[] = 'Company: ' . ( '' !== $values['company'] ? $values['company'] : '(not given)' );
	$lines[] = 'Email: ' . $values['email'];
	$lines[] = 'Preferred start date: ' . wp_date( 'F j, Y', $start->getTimestamp() ) . ' (' . $values['start'] . ')';
	$lines[] = 'Preferred payment method: ' . $options['payments'][ $values['payment'] ];
	if ( 'invoice' === $values['payment'] ) {
		$lines[] = 'How does your company pay?: ' . ( '' !== $values['invoice_details'] ? $values['invoice_details'] : '(not given)' );
	}
	$lines[] = '';
	$lines[] = 'What do you usually need help with?';
	$lines[] = '' !== $values['needs'] ? $values['needs'] : '(not given)';
	$lines[] = '';
	$lines[] = 'Anything else I should know?';
	$lines[] = '' !== $values['notes'] ? $values['notes'] : '(not given)';
	if ( 'email' === $context ) {
		$lines[] = '';
		$lines[] = '--';
		$lines[] = 'Sent ' . wp_date( 'F j, Y g:i a T', $time );
	}

	return implode( "\n", $lines );
}

function fd_plans_send( $values, $sites, $options ) {
	$subject = 'New plan inquiry: ' . fd_plans_summary( $values, $options );

	// Strip characters that would break the Reply-To header.
	$reply_name = trim( str_replace( array( '"', '<', '>', ',', ';', ':', '\\' ), '', $values['name'] ) );
	$headers    = array( 'Reply-To: ' . ( '' !== $reply_name ? $reply_name . ' <' . $values['email'] . '>' : $values['email'] ) );

	$admin      = admin_url( 'edit.php?post_type=' . FD_INQUIRY_TYPE );
	$body       = fd_plans_message( $values, $sites, $options ) . "\n\nAll inquiries: " . $admin;

	return wp_mail( FD_PLANS_RECIPIENT, $subject, $body, $headers );
}

function fd_plans_return_url( $page_id ) {
	if ( $page_id && 'page' === get_post_type( $page_id ) && FD_PLANS_TEMPLATE === get_page_template_slug( $page_id ) ) {
		return get_permalink( $page_id );
	}
	return home_url( '/plans/' );
}

function fd_plans_redirect( $url, $state ) {
	$token = strtolower( wp_generate_password( 20, false, false ) );
	set_transient( 'fd_plans_' . $token, $state, 15 * MINUTE_IN_SECONDS );
	fd_plans_set_cookie( $token, time() + 15 * MINUTE_IN_SECONDS, wp_parse_url( $url, PHP_URL_PATH ) );
	wp_safe_redirect( $url, 303 );
	exit;
}
