<?php
/**
 * Registers the dda_incident custom post type.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Post_Type {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$labels = array(
			'name'               => __( 'Incident Reports', 'dda-incident-report' ),
			'singular_name'      => __( 'Incident Report', 'dda-incident-report' ),
			'menu_name'          => __( 'Incident Reports', 'dda-incident-report' ),
			'all_items'          => __( 'All Reports', 'dda-incident-report' ),
			'view_item'          => __( 'View Report', 'dda-incident-report' ),
			'edit_item'          => __( 'Edit Report', 'dda-incident-report' ),
			'add_new_item'       => __( 'Add New Report', 'dda-incident-report' ),
			'search_items'       => __( 'Search Reports', 'dda-incident-report' ),
			'not_found'          => __( 'No reports found', 'dda-incident-report' ),
			'not_found_in_trash' => __( 'No reports found in trash', 'dda-incident-report' ),
		);

		register_post_type(
			DDA_Incident_Report_Plugin::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-clipboard',
				'capability_type'     => 'post',
				'supports'            => array( 'title', 'author' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
	}
}
