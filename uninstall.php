<?php
/**
 * Cleanup on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'atep_settings' );

$posts = get_posts(
	array(
		'post_type'      => 'atep_price',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
