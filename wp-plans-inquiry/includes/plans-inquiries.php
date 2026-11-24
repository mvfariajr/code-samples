<?php
/**
 * Plan inquiries: every Plans form submission is saved as a private post type
 * and listed under "Plan Inquiries" in wp-admin, for administrators only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FD_INQUIRY_TYPE', 'fd_plan_inquiry' );

add_action( 'init', 'fd_inquiries_register' );
function fd_inquiries_register() {
	$cap = 'manage_options';

	register_post_type(
		FD_INQUIRY_TYPE,
		array(
			'labels'              => array(
				'name'               => 'Plan Inquiries',
				'singular_name'      => 'Plan Inquiry',
				'menu_name'          => 'Plan Inquiries',
				'all_items'          => 'All Inquiries',
				'edit_item'          => 'Plan Inquiry',
				'search_items'       => 'Search Inquiries',
				'not_found'          => 'No inquiries yet.',
				'not_found_in_trash' => 'No inquiries in the trash.',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'menu_position'       => 31,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => false,
			'rewrite'             => false,
			'query_var'           => false,
			// Admins only, and inquiries can only be created by the form.
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'create_posts'           => 'do_not_allow',
				'edit_post'              => $cap,
				'read_post'              => $cap,
				'delete_post'            => $cap,
				'edit_posts'             => $cap,
				'edit_others_posts'      => $cap,
				'edit_published_posts'   => $cap,
				'edit_private_posts'     => $cap,
				'publish_posts'          => $cap,
				'read_private_posts'     => $cap,
				'delete_posts'           => $cap,
				'delete_others_posts'    => $cap,
				'delete_published_posts' => $cap,
				'delete_private_posts'   => $cap,
			),
		)
	);
}

/**
 * Saves a validated submission. Returns the new post ID, or 0 on failure.
 */
function fd_inquiries_save( $values, $sites, $options ) {
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => FD_INQUIRY_TYPE,
				'post_status'    => 'publish',
				'post_title'     => fd_plans_summary( $values, $options ),
				'post_content'   => fd_plans_message( $values, $sites, $options ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		),
		true
	);

	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}

	$meta = array(
		'plan'            => $values['plan'],
		'care'            => $values['care'] ? '1' : '',
		'sites'           => implode( "\n", $sites ),
		'name'            => $values['name'],
		'company'         => $values['company'],
		'email'           => $values['email'],
		'needs'           => $values['needs'],
		'start'           => $values['start'],
		'payment'         => $values['payment'],
		'invoice_details' => 'invoice' === $values['payment'] ? $values['invoice_details'] : '',
		'notes'           => $values['notes'],
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, '_fd_' . $key, wp_slash( $value ) );
	}

	return $id;
}

function fd_inquiries_meta( $id, $key ) {
	return (string) get_post_meta( $id, '_fd_' . $key, true );
}

/**
 * Rebuilds the submitted values from a saved inquiry: array( values, sites ).
 */
function fd_inquiries_values( $id ) {
	$values = array();
	foreach ( array( 'plan', 'sites', 'name', 'company', 'email', 'needs', 'start', 'payment', 'invoice_details', 'notes' ) as $key ) {
		$values[ $key ] = fd_inquiries_meta( $id, $key );
	}
	$values['care'] = '1' === fd_inquiries_meta( $id, 'care' );

	return array( $values, array_filter( explode( "\n", $values['sites'] ) ) );
}

/* List screen */
add_filter( 'manage_' . FD_INQUIRY_TYPE . '_posts_columns', 'fd_inquiries_columns' );
function fd_inquiries_columns( $columns ) {
	return array(
		'cb'          => $columns['cb'],
		'title'       => 'Inquiry',
		'fd_name'     => 'Name',
		'fd_email'    => 'Email',
		'fd_start'    => 'Preferred start',
		'fd_notified' => 'Notification',
		'fd_received' => 'Received',
	);
}

add_filter( 'manage_edit-' . FD_INQUIRY_TYPE . '_sortable_columns', 'fd_inquiries_sortable_columns' );
function fd_inquiries_sortable_columns( $columns ) {
	$columns['fd_received'] = 'date';
	return $columns;
}

add_action( 'manage_' . FD_INQUIRY_TYPE . '_posts_custom_column', 'fd_inquiries_column', 10, 2 );
function fd_inquiries_column( $column, $id ) {
	switch ( $column ) {
		case 'fd_name':
			echo esc_html( fd_inquiries_meta( $id, 'name' ) );
			break;
		case 'fd_email':
			$email = fd_inquiries_meta( $id, 'email' );
			printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
			break;
		case 'fd_start':
			$start = fd_plans_parse_date( fd_inquiries_meta( $id, 'start' ) );
			echo $start ? esc_html( wp_date( 'M j, Y', $start->getTimestamp() ) ) : '';
			break;
		case 'fd_notified':
			echo esc_html( fd_inquiries_notified_label( $id ) );
			break;
		case 'fd_received':
			echo esc_html( get_the_date( 'M j, Y g:i a', $id ) );
			break;
	}
}

function fd_inquiries_notified_label( $id ) {
	$notified = fd_inquiries_meta( $id, 'notified' );
	if ( 'yes' === $notified ) {
		return 'Sent';
	}
	return 'no' === $notified ? 'Failed' : '';
}

// Inquiries are read-only: no Quick Edit or bulk edit, and "Edit" reads "View".
add_filter( 'post_row_actions', 'fd_inquiries_row_actions', 10, 2 );
function fd_inquiries_row_actions( $actions, $post ) {
	if ( FD_INQUIRY_TYPE !== $post->post_type ) {
		return $actions;
	}
	unset( $actions['inline hide-if-no-js'] );
	if ( isset( $actions['edit'] ) ) {
		$actions['edit'] = sprintf( '<a href="%s">View</a>', esc_url( get_edit_post_link( $post->ID ) ) );
	}
	return $actions;
}

add_filter( 'bulk_actions-edit-' . FD_INQUIRY_TYPE, 'fd_inquiries_bulk_actions' );
function fd_inquiries_bulk_actions( $actions ) {
	unset( $actions['edit'] );
	return $actions;
}

/* Detail screen */
add_action( 'add_meta_boxes_' . FD_INQUIRY_TYPE, 'fd_inquiries_meta_boxes' );
function fd_inquiries_meta_boxes() {
	remove_meta_box( 'submitdiv', FD_INQUIRY_TYPE, 'side' );
	remove_meta_box( 'slugdiv', FD_INQUIRY_TYPE, 'normal' );
	add_meta_box( 'fd-inquiry-details', 'Inquiry details', 'fd_inquiries_render_details', FD_INQUIRY_TYPE, 'normal', 'high' );
	add_meta_box( 'fd-inquiry-actions', 'Actions', 'fd_inquiries_render_actions', FD_INQUIRY_TYPE, 'side', 'high' );
}

function fd_inquiries_render_details( $post ) {
	$options = fd_plans_options();
	$id      = $post->ID;
	$plan    = fd_inquiries_meta( $id, 'plan' );
	$payment = fd_inquiries_meta( $id, 'payment' );
	$start   = fd_plans_parse_date( fd_inquiries_meta( $id, 'start' ) );
	$email   = fd_inquiries_meta( $id, 'email' );

	$sites = array_filter( explode( "\n", fd_inquiries_meta( $id, 'sites' ) ) );
	$links = array_map(
		function ( $url ) {
			return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $url ), esc_html( $url ) );
		},
		$sites
	);

	$text = function ( $value ) {
		return '' !== $value ? nl2br( esc_html( $value ) ) : '<span class="description">Not given</span>';
	};

	$rows = array(
		'Received'                 => esc_html( get_the_date( 'F j, Y g:i a', $post ) ),
		'Retainer plan'            => isset( $options['plans'][ $plan ] ) ? esc_html( $options['plans'][ $plan ]['label'] . ' (' . $options['plans'][ $plan ]['detail'] . ')' ) : 'None selected',
		'Care plan'                => fd_inquiries_meta( $id, 'care' ) ? 'Yes' : 'No',
		'Website URLs'             => $links ? implode( '<br>', $links ) : '<span class="description">None</span>',
		'Name'                     => $text( fd_inquiries_meta( $id, 'name' ) ),
		'Company'                  => $text( fd_inquiries_meta( $id, 'company' ) ),
		'Email'                    => sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) ),
		'Preferred start date'     => $start ? esc_html( wp_date( 'F j, Y', $start->getTimestamp() ) ) : '',
		'Payment method'           => isset( $options['payments'][ $payment ] ) ? esc_html( $options['payments'][ $payment ] ) : '',
		'How does your company pay?' => 'invoice' === $payment ? $text( fd_inquiries_meta( $id, 'invoice_details' ) ) : null,
		'What do you usually need help with?' => $text( fd_inquiries_meta( $id, 'needs' ) ),
		'Anything else'            => $text( fd_inquiries_meta( $id, 'notes' ) ),
		'Notification email'       => esc_html( fd_inquiries_notified_label( $id ) ),
	);

	echo '<h2 style="padding:0;margin:4px 0 8px;font-size:1.3em">' . esc_html( get_the_title( $post ) ) . '</h2>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $label => $value ) {
		if ( null === $value ) {
			continue;
		}
		printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</tbody></table>';
}

function fd_inquiries_render_actions( $post ) {
	list( $values, $sites ) = fd_inquiries_values( $post->ID );

	$subject = 'Re: ' . get_the_title( $post );
	$body    = "\n\n\n" . sprintf( '----- %s sent this inquiry on %s -----', $values['name'], get_the_date( 'F j, Y', $post ) ) . "\n\n"
		. fd_plans_message( $values, $sites, fd_plans_options(), 'reply' );

	// Encode line breaks as CRLF, which mail apps expect in a mailto body.
	$mailto = 'mailto:' . str_replace( array( '%40', '%2B' ), array( '@', '+' ), rawurlencode( $values['email'] ) )
		. '?subject=' . rawurlencode( $subject )
		. '&body=' . rawurlencode( str_replace( "\n", "\r\n", $body ) );
	?>
	<p><a class="button button-primary" href="<?php echo esc_attr( $mailto ); ?>">Reply by email</a></p>
	<p><a class="submitdelete" style="color:#b32d2e" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>">Move to Trash</a></p>
	<?php
}
