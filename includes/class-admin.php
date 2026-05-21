<?php
/**
 * Admin meta boxes and list-table columns for incident reports.
 *
 * Renders the submitted data in a paper-form layout (PDF-style) so
 * reviewers can read it the same way it appears on the official form.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Admin {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		$cpt = DDA_Incident_Report_Plugin::POST_TYPE;
		add_filter( "manage_{$cpt}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( $this, 'column_content' ), 10, 2 );
	}

	public function enqueue_admin_styles( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || DDA_Incident_Report_Plugin::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'dda-incident-report-admin',
			DDA_INCIDENT_REPORT_URL . 'assets/css/dda-incident-report-admin.css',
			array(),
			DDA_INCIDENT_REPORT_VERSION
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'dda_incident_details',
			__( 'Incident Report (Paper Form View)', 'dda-incident-report' ),
			array( $this, 'render_meta_box' ),
			DDA_Incident_Report_Plugin::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'dda_incident_submitter',
			__( 'Submitter', 'dda-incident-report' ),
			array( $this, 'render_submitter_box' ),
			DDA_Incident_Report_Plugin::POST_TYPE,
			'side',
			'default'
		);
	}

	public function render_submitter_box( $post ) {
		$author = get_userdata( $post->post_author );
		$ip     = get_post_meta( $post->ID, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_ip', true );
		$at     = get_post_meta( $post->ID, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_at', true );

		echo '<div class="dda-submitter">';
		if ( $author ) {
			echo '<p><strong>' . esc_html( $author->display_name ) . '</strong><br>';
			echo '<a href="mailto:' . esc_attr( $author->user_email ) . '">' . esc_html( $author->user_email ) . '</a></p>';
		} else {
			echo '<p><em>' . esc_html__( 'Author not found.', 'dda-incident-report' ) . '</em></p>';
		}
		if ( $at ) {
			echo '<p><small>' . esc_html__( 'Submitted:', 'dda-incident-report' ) . '<br>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $at ) ) . '</small></p>';
		}
		if ( $ip ) {
			echo '<p><small>' . esc_html__( 'IP:', 'dda-incident-report' ) . ' <code>' . esc_html( $ip ) . '</code></small></p>';
		}
		echo '</div>';
	}

	public function render_meta_box( $post ) {
		$pid = $post->ID;
		$get = function ( $key ) use ( $pid ) {
			return get_post_meta( $pid, DDA_Incident_Report_Plugin::META_PREFIX . $key, true );
		};

		echo '<div class="dda-paper-form">';
		echo '<div class="dda-paper-header">';
		echo '<div class="dda-paper-title">DDA Incident Report</div>';
		echo '<div class="dda-paper-sub">' . esc_html__( 'DC Government — Department on Disability Services', 'dda-incident-report' ) . '</div>';
		echo '</div>';

		// --- Section: Header (Person info) ---
		$this->section_open( __( 'Person & Provider Information', 'dda-incident-report' ) );
		$this->field_grid( array(
			array( 'MCIS Report Number', $get( 'mcis_report_number' ) ),
			array( 'Date of Incident', $get( 'date_of_incident' ) ),
			array( 'Name of Primary Person Involved', $get( 'primary_person_name' ) ),
			array( 'Date of Birth', $get( 'primary_person_dob' ) ),
			array( 'Evans Class Member', $this->yes_no( $get( 'evans_class_member' ) ) ),
			array( 'Waiver', $this->yes_no( $get( 'waiver' ) ) ),
		), 2 );
		$this->field_grid( array(
			array( 'Person Served by DDA\'s Address', $get( 'person_address' ) ),
		), 1 );
		$this->field_grid( array(
			array( 'Provider\'s Name (Residential)', $get( 'residential_provider_name' ) ),
			array( 'Provider Phone', $get( 'residential_provider_phone' ) ),
		), 2 );

		$rpt_key   = $get( 'residential_provider_type' );
		$rpt_list  = DDA_Incident_Report_Fields::residential_provider_types();
		$rpt_label = 'other' === $rpt_key ? 'Other: ' . $get( 'residential_provider_type_other' ) : ( isset( $rpt_list[ $rpt_key ] ) ? $rpt_list[ $rpt_key ] : '' );
		$this->field_grid( array(
			array( 'Residential Provider Type', $rpt_label ),
		), 1 );
		$this->section_close();

		// --- Section: Location ---
		$this->section_open( __( 'Location of Incident', 'dda-incident-report' ) );
		$this->field_grid( array(
			array( 'Address of Incident', $get( 'incident_address' ) ),
			array( 'Provider Name (Incident Location)', $get( 'incident_provider_name' ) ),
		), 1 );
		$this->section_close();

		// --- Section: Other persons ---
		$this->section_open( __( 'Other Persons Supported by DDA Involved', 'dda-incident-report' ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->field_grid( array(
				array( "Person {$i} Name", $get( "other_person_{$i}_name" ) ),
				array( "Person {$i} Date of Birth", $get( "other_person_{$i}_dob" ) ),
			), 2 );
		}
		$this->section_close();

		// --- Section: Staff ---
		$this->section_open( __( 'Staff Involved', 'dda-incident-report' ) );
		for ( $i = 1; $i <= 2; $i++ ) {
			$this->field_grid( array(
				array( "Staff {$i} Name", $get( "staff_{$i}_name" ) ),
				array( "Staff {$i} Phone", $get( "staff_{$i}_phone" ) ),
			), 2 );
		}
		$this->section_close();

		// --- Section: Reporter ---
		$this->section_open( __( 'Reporter Information', 'dda-incident-report' ) );
		$this->field_grid( array(
			array( 'Reporter Name', $get( 'reporter_name' ) ),
			array( 'Reporter Title', $get( 'reporter_title' ) ),
			array( 'Reporter Phone', $get( 'reporter_phone' ) ),
		), 3 );
		$this->section_close();

		// --- Section: Categorization ---
		$this->section_open( __( 'Section 1: Incident Categorization', 'dda-incident-report' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Serious Reportable', 'dda-incident-report' ) . '</div>';
		$this->checkbox_list( DDA_Incident_Report_Fields::serious_reportable_options(), $get( 'serious_reportable' ), $get( 'serious_reportable_other' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Abuse and Neglect Categories', 'dda-incident-report' ) . '</div>';
		$this->checkbox_list( DDA_Incident_Report_Fields::abuse_neglect_categories(), $get( 'abuse_neglect_categories' ), '', false );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Supervisor Certification', 'dda-incident-report' ) . '</div>';
		$this->field_grid( array(
			array( 'Supervisor Name', $get( 'supervisor_cert_name' ) ),
			array( 'Supervisor Title', $get( 'supervisor_cert_title' ) ),
			array( 'Signature (Typed)', $get( 'supervisor_cert_signature' ) ),
		), 3 );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Reportable', 'dda-incident-report' ) . '</div>';
		$this->checkbox_list( DDA_Incident_Report_Fields::reportable_options(), $get( 'reportable' ), $get( 'reportable_other' ) );

		echo '<div class="dda-paper-subhead">' . esc_html__( 'Incident Location Type', 'dda-incident-report' ) . '</div>';
		$lt_key   = $get( 'location_type' );
		$lt_other = $get( 'location_type_other' );
		$this->radio_list( DDA_Incident_Report_Fields::location_types(), $lt_key, $lt_other );

		$this->section_close();

		// --- Section 2: Description ---
		$this->section_open( __( 'Section 2: Description of Incident', 'dda-incident-report' ) );
		$this->field_grid( array(
			array( 'Date', $get( 'section2_date' ) ),
			array( 'Time', trim( $get( 'section2_time' ) . ' ' . strtoupper( $get( 'section2_ampm' ) ) ) ),
			array( 'Source', ucfirst( (string) $get( 'incident_source' ) ) ),
		), 3 );

		$reporter_type = $get( 'reporter_type' );
		$reporter_label = 'other' === $reporter_type ? 'Other: ' . $get( 'reporter_type_other' ) : ucwords( str_replace( '_', ' ', (string) $reporter_type ) );
		$this->field_grid( array(
			array( 'Reporter Type', $reporter_label ),
		), 1 );

		for ( $i = 1; $i <= 2; $i++ ) {
			$this->field_grid( array(
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

		$this->field_grid( array(
			array( 'Signature of Reporter (Typed)', $get( 'reporter_signature' ) ),
			array( 'Date', $get( 'reporter_signature_date' ) ),
			array( 'Time', trim( $get( 'reporter_signature_time' ) . ' ' . strtoupper( $get( 'reporter_signature_ampm' ) ) ) ),
		), 3 );
		$this->section_close();

		// --- Notifications ---
		$this->section_open( __( 'Verbal Notifications', 'dda-incident-report' ) );

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
		$this->section_close();

		echo '</div>'; // .dda-paper-form
	}

	/* ---------------------------------------------------------------------
	 * Rendering helpers for the paper-form layout
	 * ------------------------------------------------------------------ */

	private function section_open( $title ) {
		echo '<div class="dda-paper-section">';
		echo '<h3 class="dda-paper-section-title">' . esc_html( $title ) . '</h3>';
	}

	private function section_close() {
		echo '</div>';
	}

	private function field_grid( $rows, $cols = 2 ) {
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

	private function checkbox_list( $options, $selected, $other_value = '', $allow_other = true ) {
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

	private function radio_list( $options, $selected_key, $other_value = '' ) {
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

	private function yes_no( $val ) {
		if ( 'yes' === $val ) {
			return 'Yes';
		}
		if ( 'no' === $val ) {
			return 'No';
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Admin list columns
	 * ------------------------------------------------------------------ */

	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mcis']          = __( 'MCIS #', 'dda-incident-report' );
				$new['person']        = __( 'Person', 'dda-incident-report' );
				$new['incident_date'] = __( 'Incident Date', 'dda-incident-report' );
				$new['reporter']      = __( 'Reporter', 'dda-incident-report' );
				$new['dda_score']     = __( 'Score', 'dda-incident-report' );
			}
		}
		return $new;
	}

	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'mcis':
				echo esc_html( get_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'mcis_report_number', true ) );
				break;
			case 'person':
				echo esc_html( get_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'primary_person_name', true ) );
				break;
			case 'incident_date':
				echo esc_html( get_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'date_of_incident', true ) );
				break;
			case 'reporter':
				echo esc_html( get_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'reporter_name', true ) );
				break;
			case 'dda_score':
				$score = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_SCORE, true );
				if ( '' === $score ) {
					echo '<span class="dda-pill dda-pill-pending">' . esc_html__( 'Pending', 'dda-incident-report' ) . '</span>';
				} else {
					$pass     = DDA_Incident_Report_User_State::passing_score();
					$passed   = (float) $score >= $pass;
					$cls      = $passed ? 'dda-pill-pass' : 'dda-pill-fail';
					$label    = $passed ? __( 'Pass', 'dda-incident-report' ) : __( 'Fail', 'dda-incident-report' );
					echo '<span class="dda-pill ' . esc_attr( $cls ) . '">' . esc_html( $label ) . ' &middot; ' . esc_html( number_format_i18n( (float) $score, 1 ) ) . '</span>';
				}
				break;
		}
	}
}
