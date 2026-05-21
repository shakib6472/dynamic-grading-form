<?php
/**
 * Activation / deactivation handlers.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Activator {

	const INSTRUCTOR_ROLE = 'dda_instructor';

	public static function activate() {
		self::register_instructor_role();
		self::grant_admin_caps();
	}

	public static function deactivate() {
		// Role intentionally preserved on deactivation; removed only on uninstall.
	}

	private static function register_instructor_role() {
		$existing = get_role( self::INSTRUCTOR_ROLE );
		if ( $existing ) {
			return;
		}

		add_role(
			self::INSTRUCTOR_ROLE,
			__( 'DDA Instructor', 'dda-incident-report' ),
			array(
				'read'                   => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'read_private_posts'     => true,
				'publish_posts'          => false,
				'delete_posts'           => false,
				'delete_others_posts'    => false,
			)
		);
	}

	private static function grant_admin_caps() {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		// Standard administrators already hold these; this is defensive.
		foreach ( array( 'edit_others_posts', 'edit_posts' ) as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}
}
