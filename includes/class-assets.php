<?php
/**
 * Enqueues front-end styles and Google Fonts.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function enqueue_styles() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'dda_incident_report' ) ) {
			return;
		}

		wp_enqueue_style(
			'dda-incident-report-fonts',
			'https://fonts.googleapis.com/css2?family=Urbanist:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'dda-incident-report',
			DDA_INCIDENT_REPORT_URL . 'assets/css/dda-incident-report.css',
			array( 'dda-incident-report-fonts' ),
			DDA_INCIDENT_REPORT_VERSION
		);
	}
}
