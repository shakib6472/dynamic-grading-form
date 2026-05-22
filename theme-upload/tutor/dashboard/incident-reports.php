<?php
/**
 * Loader for Tutor LMS Instructor Dashboard: Incident Reports
 *
 * UPLOAD THIS FILE TO:
 *   <your-active-theme>/tutor/dashboard/incident-reports.php
 *
 * For this site that means:
 *   /wp-content/themes/edubin/tutor/dashboard/incident-reports.php
 *
 * It is a thin loader: Tutor LMS looks for dashboard sub-page templates
 * inside the active theme's `tutor/dashboard/` folder first. We forward
 * the render to the DDA Incident Report Form plugin, so updating the
 * plugin keeps the page in sync automatically and you never need to
 * edit this file again.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'DDA_INCIDENT_REPORT_DIR' ) ) {
	$template = trailingslashit( DDA_INCIDENT_REPORT_DIR ) . 'tutor-templates/dashboard/incident-reports.php';

	if ( file_exists( $template ) ) {
		include $template;
		return;
	}
}

echo '<div class="tutor-alert tutor-alert-danger tutor-mt-24">';
echo '<p><strong>Incident Reports</strong> page cannot be loaded.</p>';
echo '<p>Please ensure the <em>DDA Incident Report Form</em> plugin is active. Expected template file:</p>';
echo '<p><code>' . esc_html(
	defined( 'DDA_INCIDENT_REPORT_DIR' )
		? trailingslashit( DDA_INCIDENT_REPORT_DIR ) . 'tutor-templates/dashboard/incident-reports.php'
		: 'DDA Incident Report Form plugin is NOT active.'
) . '</code></p>';
echo '</div>';
