<?php
/**
 * Renders an incident report in a clean on-screen "paper form" layout.
 *
 * Used by:
 *   - the WP admin meta box,
 *   - the TutorLMS instructor dashboard detail page.
 *
 * Note: for the *print* layout (which must match the official PDF
 * exactly), see DDA_Incident_Report_Print_View.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Paper_View {

	public static function render( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( (int) $post );
		}
		if ( ! $post ) {
			return;
		}

		$pid = (int) $post->ID;
		$get = function ( $key ) use ( $pid ) {
			return get_post_meta( $pid, DDA_Incident_Report_Plugin::META_PREFIX . $key, true );
		};

		echo '<div class="dda-paper-form">';
		echo '<div class="dda-paper-header">';
		echo '<div class="dda-paper-title">DDA Incident Report</div>';
		echo '<div class="dda-paper-sub">' . esc_html__( 'DC Government — Department on Disability Services', 'dda-incident-report' ) . '</div>';
		echo '</div>';

		self::section_open( __( 'Person & Provider Information', 'dda-incident-report' ) );
		self::field_grid( array(
			array( 'MCIS Report Number', $get( 'mcis_report_number' ) ),
			array( 'Date of Incident', $get( 'date_of_incident' ) ),
			array( 'Name of Primary Person Involved', $get( 'primary_person_name' ) ),
			array( 'Date of Birth', $get( 'primary_person_dob' ) ),
			array( 'Evans Class Member', self::yes_no( $get( 'evans_class_member' ) ) ),
			array( 'Waiver', self::yes_no( $get( 'waiver' ) ) ),
		), 2 );
		self::field_grid( array(
			array( 'Person Served by DDA\'s Address', $get( 'person_address' ) ),
		), 1 );
		self::field_grid( array(
			array( 'Provider\'s Name (Residential)', $get( 'residential_provider_name' ) ),
			array( 'Provider Phone', $get( 'residential_provider_phone' ) ),
		), 2 );

		$rpt_key   = $get( 'residential_provider_type' );
		$rpt_list  = DDA_Incident_Report_Fields::residential_provider_types();
		$rpt_label = 'other' === $rpt_key ? 'Other: ' . $get( 'residential_provider_type_other' ) : ( isset( $rpt_list[ $rpt_key ] ) ? $rpt_list[ $rpt_key ] : '' );
		self::field_grid( array(
			array( 'Residential Provider Type', $rpt_label ),
		), 1 );
		self::section_close();

		self::section_open( __( 'Location of Incident', 'dda-incident-report' ) );
		self::field_grid( array(
			array( 'Address of Incident', $get( 'incident_address' ) ),
			array( 'Provider Name (Incident Location)', $get( 'incident_provider_name' ) ),
		), 1 );
		self::section_close();

		self::section_open( __( 'Other Persons Supported by DDA Involved', 'dda-incident-report' ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			self::field_grid( array(
				array( "Person {$i} Name", $get( "other_person_{$i}_name" ) ),
				array( "Person {$i} Date of Birth", $get( "other_person_{$i}_dob" ) ),
			), 2 );
		}
		self::section_close();

		self::section_open( __( 'Staff Involved', 'dda-incident-report' ) );
		for ( $i = 1; $i <= 2; $i++ ) {
			self::field_grid( array(
				array( "Staff {$i} Name", $get( "staff_{$i}_name" ) ),
				array( "Staff {$i} Phone", $get( "staff_{$i}_phone" ) ),
			), 2 );
		}
		self::section_close();

		self::section_open( __( 'Reporter Information', 'dda-incident-report' ) );
		self::field_grid( array(
			array( 'Reporter Name', $get( 'reporter_name' ) ),
			array( 'Reporter Title', $get( 'reporter_title' ) ),
			array( 'Reporter Phone', $get( 'reporter_phone' ) ),
		), 3 );
		self::section_close();

		self::section_open( __( 'Section 1: Incident Categorization', 'dda-incident-report' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Serious Reportable', 'dda-incident-report' ) . '</div>';
		self::checkbox_list( DDA_Incident_Report_Fields::serious_reportable_options(), $get( 'serious_reportable' ), $get( 'serious_reportable_other' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Abuse and Neglect Categories', 'dda-incident-report' ) . '</div>';
		self::checkbox_list( DDA_Incident_Report_Fields::abuse_neglect_categories(), $get( 'abuse_neglect_categories' ), '', false );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Supervisor Certification', 'dda-incident-report' ) . '</div>';
		self::field_grid( array(
			array( 'Supervisor Name', $get( 'supervisor_cert_name' ) ),
			array( 'Supervisor Title', $get( 'supervisor_cert_title' ) ),
			array( 'Signature (Typed)', $get( 'supervisor_cert_signature' ) ),
		), 3 );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Reportable', 'dda-incident-report' ) . '</div>';
		self::checkbox_list( DDA_Incident_Report_Fields::reportable_options(), $get( 'reportable' ), $get( 'reportable_other' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Incident Location Type', 'dda-incident-report' ) . '</div>';
		$lt_key   = $get( 'location_type' );
		$lt_other = $get( 'location_type_other' );
		self::radio_list( DDA_Incident_Report_Fields::location_types(), $lt_key, $lt_other );

		self::section_close();

		self::section_open( __( 'Section 2: Description of Incident', 'dda-incident-report' ) );
		self::field_grid( array(
			array( 'Date', $get( 'section2_date' ) ),
			array( 'Time', trim( $get( 'section2_time' ) . ' ' . strtoupper( $get( 'section2_ampm' ) ) ) ),
			array( 'Source', ucfirst( (string) $get( 'incident_source' ) ) ),
		), 3 );

		$reporter_type  = $get( 'reporter_type' );
		$reporter_label = 'other' === $reporter_type ? 'Other: ' . $get( 'reporter_type_other' ) : ucwords( str_replace( '_', ' ', (string) $reporter_type ) );
		self::field_grid( array(
			array( 'Reporter Type', $reporter_label ),
		), 1 );

		for ( $i = 1; $i <= 2; $i++ ) {
			self::field_grid( array(
				array( "Witness {$i} Name", $get( "witness_{$i}_name" ) ),
				array( "Witness {$i} Phone", $get( "witness_{$i}_phone" ) ),
			), 2 );
		}

		echo '<div class="dda-paper-block">';
		echo '<div class="dda-paper-label">' . esc_html__( 'Description of the Incident (Who, What, When, Where, How)', 'dda-incident-report' ) . '</div>';
		echo '<div class="dda-paper-textbox">' . nl2br( esc_html( (string) $get( 'incident_description' ) ) ) . '</div>';
		echo '</div>';

		echo '<div class="dda-paper-block">';
		echo '<div class="dda-paper-label">' . esc_html__( 'Immediate Actions Taken', 'dda-incident-report' ) . '</div>';
		echo '<div class="dda-paper-textbox">' . nl2br( esc_html( (string) $get( 'immediate_actions' ) ) ) . '</div>';
		echo '</div>';

		self::field_grid( array(
			array( 'Signature of Reporter (Typed)', $get( 'reporter_signature' ) ),
			array( 'Date', $get( 'reporter_signature_date' ) ),
			array( 'Time', trim( $get( 'reporter_signature_time' ) . ' ' . strtoupper( $get( 'reporter_signature_ampm' ) ) ) ),
		), 3 );
		self::section_close();

		self::section_open( __( 'Verbal Notifications', 'dda-incident-report' ) );
		$notes      = $get( 'notifications' );
		$recipients = DDA_Incident_Report_Fields::notification_recipients();

		echo '<table class="dda-paper-notifications">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Recipient', 'dda-incident-report' ) . '</th>';
		echo '<th>' . esc_html__( 'Person Notified', 'dda-incident-report' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'dda-incident-report' ) . '</th>';
		echo '<th>' . esc_html__( 'Time', 'dda-incident-report' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $recipients as $key => $label ) {
			$row     = isset( $notes[ $key ] ) ? $notes[ $key ] : array();
			$checked = ! empty( $row['checked'] );
			echo '<tr class="' . ( $checked ? 'is-checked' : '' ) . '">';
			echo '<td><span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> ' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( isset( $row['person'] ) ? $row['person'] : '' ) . '</td>';
			echo '<td>' . esc_html( isset( $row['date'] ) ? $row['date'] : '' ) . '</td>';
			echo '<td>' . esc_html( isset( $row['time'] ) ? $row['time'] : '' ) . '</td>';
			echo '</tr>';
		}

		if ( isset( $notes['other'] ) ) {
			$other   = $notes['other'];
			$checked = ! empty( $other['checked'] );
			echo '<tr class="' . ( $checked ? 'is-checked' : '' ) . '">';
			echo '<td><span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> Other: ' . esc_html( isset( $other['label'] ) ? $other['label'] : '' ) . '</td>';
			echo '<td>' . esc_html( isset( $other['person'] ) ? $other['person'] : '' ) . '</td>';
			echo '<td>' . esc_html( isset( $other['date'] ) ? $other['date'] : '' ) . '</td>';
			echo '<td>' . esc_html( isset( $other['time'] ) ? $other['time'] : '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		self::section_close();

		echo '</div>'; // .dda-paper-form
	}

	private static function section_open( $title ) {
		echo '<div class="dda-paper-section">';
		echo '<h3 class="dda-paper-section-title">' . esc_html( $title ) . '</h3>';
	}

	private static function section_close() {
		echo '</div>';
	}

	private static function field_grid( $rows, $cols = 2 ) {
		echo '<div class="dda-paper-grid dda-paper-grid-' . (int) $cols . '">';
		foreach ( $rows as $row ) {
			list( $label, $value ) = $row;
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value ) );
			}
			$is_empty = ( '' === (string) $value );
			echo '<div class="dda-paper-field">';
			echo '<div class="dda-paper-label">' . esc_html( $label ) . '</div>';
			echo '<div class="dda-paper-value' . ( $is_empty ? ' is-empty' : '' ) . '">';
			echo $is_empty ? '&nbsp;' : esc_html( $value );
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private static function checkbox_list( $options, $selected, $other_value = '', $allow_other = true ) {
		$selected = is_array( $selected ) ? $selected : array();
		echo '<div class="dda-paper-checklist">';
		foreach ( $options as $key => $label ) {
			$checked = in_array( $key, $selected, true );
			echo '<div class="dda-paper-check' . ( $checked ? ' is-checked' : '' ) . '">';
			echo '<span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> ' . esc_html( $label );
			echo '</div>';
		}
		if ( $allow_other ) {
			$checked = in_array( 'other', $selected, true );
			echo '<div class="dda-paper-check' . ( $checked ? ' is-checked' : '' ) . '">';
			echo '<span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> Other';
			if ( $checked && $other_value ) {
				echo ': <em>' . esc_html( $other_value ) . '</em>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private static function radio_list( $options, $selected_key, $other_value = '' ) {
		echo '<div class="dda-paper-checklist">';
		foreach ( $options as $key => $label ) {
			$checked = ( (string) $key === (string) $selected_key );
			echo '<div class="dda-paper-check' . ( $checked ? ' is-checked' : '' ) . '">';
			echo '<span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> ' . esc_html( $label );
			echo '</div>';
		}
		$checked = ( 'other' === (string) $selected_key );
		echo '<div class="dda-paper-check' . ( $checked ? ' is-checked' : '' ) . '">';
		echo '<span class="dda-tick">' . ( $checked ? '&#10003;' : '&#9633;' ) . '</span> Other';
		if ( $checked && $other_value ) {
			echo ': <em>' . esc_html( $other_value ) . '</em>';
		}
		echo '</div>';
		echo '</div>';
	}

	private static function yes_no( $val ) {
		if ( 'yes' === $val ) {
			return 'Yes';
		}
		if ( 'no' === $val ) {
			return 'No';
		}
		return '';
	}
}
