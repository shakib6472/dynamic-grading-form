<?php
/**
 * Plugin Name:       DDA Incident Report Form
 * Plugin URI:        https://github.com/shakib6472/
 * Description:       Adds a shortcode [dda_incident_report] that displays the DC Government DDA Incident Report form. Submissions are stored as a custom post type (dda_incident) and reviewed by an admin or instructor; learners receive an automatic pass/fail email and see their result on the same page.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Shakib Shown
 * Author URI:        https://github.com/shakib6472/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dda-incident-report
 * Domain Path:       /languages
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DDA_INCIDENT_REPORT_VERSION', '1.0.0' );
define( 'DDA_INCIDENT_REPORT_FILE', __FILE__ );
define( 'DDA_INCIDENT_REPORT_BASENAME', plugin_basename( __FILE__ ) );
define( 'DDA_INCIDENT_REPORT_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDA_INCIDENT_REPORT_URL', plugin_dir_url( __FILE__ ) );

require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-fields.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-user-state.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-activator.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-post-type.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-assets.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-shortcode.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-form-handler.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-emailer.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-paper-view.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-print-view.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-printer.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-scoring.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-admin.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-tutorlms.php';
require_once DDA_INCIDENT_REPORT_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'DDA_Incident_Report_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DDA_Incident_Report_Activator', 'deactivate' ) );

add_action( 'init', function () {
	load_plugin_textdomain(
		'dda-incident-report',
		false,
		dirname( DDA_INCIDENT_REPORT_BASENAME ) . '/languages'
	);
} );

DDA_Incident_Report_Plugin::instance();
