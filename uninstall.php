<?php
/**
 * Uninstall handler — removes all incident report posts and meta.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$post_type = 'dda_incident';

$post_ids = $wpdb->get_col(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $post_type )
);

if ( ! empty( $post_ids ) ) {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}
