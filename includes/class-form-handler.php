<?php
/**
 * Handles incident report form submissions.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Form_Handler {

	public function __construct() {
		// Primary handler — fires on the shortcode page itself, so the
		// POST never hits /wp-admin/admin-post.php. This sidesteps Tutor
		// LMS Pro's "Disable admin access" feature, which would otherwise
		// block subscribers / instructors with "Access Denied!".
		add_action( 'template_redirect', array( $this, 'maybe_handle' ), 1 );

		// Legacy admin-post.php endpoints kept as a fallback for users
		// who can still reach /wp-admin/ (e.g. administrators).
		add_action( 'admin_post_nopriv_dda_incident_submit', array( $this, 'handle' ) );
		add_action( 'admin_post_dda_incident_submit', array( $this, 'handle' ) );
	}

	/**
	 * Intercepts the form POST when it's submitted to the shortcode page.
	 */
	public function maybe_handle() {
		if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}
		if ( empty( $_POST['dda_action'] ) || 'submit_report' !== $_POST['dda_action'] ) {
			return;
		}
		$this->handle();
	}

	public function handle() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'dda_error', 'login_required', $redirect ) );
			exit;
		}

		if ( ! isset( $_POST[ DDA_Incident_Report_Plugin::NONCE_NAME ] )
			|| ! wp_verify_nonce( $_POST[ DDA_Incident_Report_Plugin::NONCE_NAME ], DDA_Incident_Report_Plugin::NONCE_ACTION ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please go back and try again.', 'dda-incident-report' ),
				esc_html__( 'Security Error', 'dda-incident-report' ),
				array( 'response' => 403 )
			);
		}

		$user_id = get_current_user_id();

		// One submission per user.
		$existing = DDA_Incident_Report_User_State::get_report_id_for_user( $user_id );
		if ( $existing ) {
			wp_safe_redirect( add_query_arg( 'dda_error', 'already_submitted', $redirect ) );
			exit;
		}

		$required = array( 'date_of_incident', 'primary_person_name', 'reporter_name', 'section2_date', 'incident_description', 'reporter_signature' );
		foreach ( $required as $req ) {
			if ( empty( $_POST[ $req ] ) ) {
				wp_safe_redirect( add_query_arg( 'dda_error', 'missing_required', $redirect ) );
				exit;
			}
		}

		$person_name = sanitize_text_field( wp_unslash( $_POST['primary_person_name'] ) );
		$date        = sanitize_text_field( wp_unslash( $_POST['date_of_incident'] ) );
		$mcis        = isset( $_POST['mcis_report_number'] ) ? sanitize_text_field( wp_unslash( $_POST['mcis_report_number'] ) ) : '';
		$title       = $mcis ? sprintf( '[%s] %s - %s', $mcis, $person_name, $date ) : sprintf( '%s - %s', $person_name, $date );

		$post_id = wp_insert_post(
			array(
				'post_type'   => DDA_Incident_Report_Plugin::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( add_query_arg( 'dda_error', 'insert_failed', $redirect ) );
			exit;
		}

		$this->save_text_fields( $post_id );
		$this->save_textareas( $post_id );
		$this->save_checkbox_arrays( $post_id );
		$this->save_notifications( $post_id );
		$this->save_submission_meta( $post_id, $redirect );

		/**
		 * Fires after an incident report is saved.
		 *
		 * @param int $post_id The newly created report ID.
		 */
		do_action( 'dda_incident_report_submitted', $post_id );

		wp_safe_redirect( add_query_arg( 'dda_submitted', '1', $redirect ) );
		exit;
	}

	private function save_text_fields( $post_id ) {
		$fields = array(
			'mcis_report_number', 'date_of_incident', 'primary_person_name', 'primary_person_dob',
			'evans_class_member', 'waiver', 'person_address',
			'residential_provider_name', 'residential_provider_phone', 'residential_provider_type', 'residential_provider_type_other',
			'incident_address', 'incident_provider_name',
			'other_person_1_name', 'other_person_1_dob',
			'other_person_2_name', 'other_person_2_dob',
			'other_person_3_name', 'other_person_3_dob',
			'staff_1_name', 'staff_1_phone',
			'staff_2_name', 'staff_2_phone',
			'reporter_name', 'reporter_title', 'reporter_phone',
			'serious_reportable_other', 'reportable_other',
			'supervisor_cert_name', 'supervisor_cert_title', 'supervisor_cert_signature',
			'location_type', 'location_type_other',
			'section2_date', 'section2_time', 'section2_ampm',
			'incident_source', 'reporter_type', 'reporter_type_other',
			'witness_1_name', 'witness_1_phone',
			'witness_2_name', 'witness_2_phone',
			'reporter_signature', 'reporter_signature_date', 'reporter_signature_time', 'reporter_signature_ampm',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . $field, $value );
			}
		}
	}

	private function save_textareas( $post_id ) {
		$fields = array( 'incident_description', 'immediate_actions' );

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . $field, $value );
			}
		}
	}

	private function save_checkbox_arrays( $post_id ) {
		$fields = array( 'serious_reportable', 'abuse_neglect_categories', 'reportable' );

		foreach ( $fields as $field ) {
			$values = isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $field ] ) )
				: array();
			update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . $field, $values );
		}
	}

	private function save_notifications( $post_id ) {
		$clean = array();

		if ( isset( $_POST['notifications'] ) && is_array( $_POST['notifications'] ) ) {
			foreach ( $_POST['notifications'] as $key => $data ) {
				$key_clean           = sanitize_key( $key );
				$clean[ $key_clean ] = array(
					'checked' => ! empty( $data['checked'] ) ? 1 : 0,
					'person'  => isset( $data['person'] ) ? sanitize_text_field( wp_unslash( $data['person'] ) ) : '',
					'date'    => isset( $data['date'] ) ? sanitize_text_field( wp_unslash( $data['date'] ) ) : '',
					'time'    => isset( $data['time'] ) ? sanitize_text_field( wp_unslash( $data['time'] ) ) : '',
					'label'   => isset( $data['label'] ) ? sanitize_text_field( wp_unslash( $data['label'] ) ) : '',
				);
			}
		}

		update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'notifications', $clean );
	}

	private function save_submission_meta( $post_id, $result_url ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_ip', $ip );
		update_post_meta( $post_id, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_at', current_time( 'mysql' ) );

		// Save the URL of the page that holds the shortcode so the result email
		// can link back to it later.
		$clean_url = remove_query_arg( array( 'dda_submitted', 'dda_error' ), $result_url );
		update_post_meta( $post_id, DDA_Incident_Report_User_State::META_RESULT_URL, esc_url_raw( $clean_url ) );
	}
}
