<?php
/**
 * Main plugin orchestrator.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DDA_Incident_Report_Plugin {

	const POST_TYPE    = 'dda_incident';
	const NONCE_ACTION = 'dda_incident_submit_action';
	const NONCE_NAME   = 'dda_incident_nonce';
	const META_PREFIX  = '_dda_';

	/**
	 * @var DDA_Incident_Report_Plugin|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new DDA_Incident_Report_Post_Type();
		new DDA_Incident_Report_Assets();
		new DDA_Incident_Report_Shortcode();
		new DDA_Incident_Report_Form_Handler();
		new DDA_Incident_Report_Admin();
		new DDA_Incident_Report_Scoring();
	}

	private function __clone() {}
	public function __wakeup() {}
}
