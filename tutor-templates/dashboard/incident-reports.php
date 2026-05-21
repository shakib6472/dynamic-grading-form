<?php
/**
 * Tutor LMS dashboard template for the "Incident Reports" tab.
 *
 * Tutor LMS locates dashboard sub-pages by template name
 * (e.g. `dashboard.incident-reports`). The `tutor_get_template_path`
 * filter in DDA_Incident_Report_TutorLMS routes Tutor to this file.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'DDA_Incident_Report_TutorLMS' ) ) {
	DDA_Incident_Report_TutorLMS::render();
}
