<?php
/**
 * Adds the WP-admin scoring meta box and exposes apply_score()
 * so the TutorLMS frontend dashboard can grade reports the same way.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Scoring {

	const NONCE_ACTION = 'dda_incident_score_save';
	const NONCE_NAME   = 'dda_incident_score_nonce';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . DDA_Incident_Report_Plugin::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	public function add_meta_box() {
		if ( ! DDA_Incident_Report_User_State::user_can_score() ) {
			return;
		}

		add_meta_box(
			'dda_incident_score',
			__( 'Review & Score', 'dda-incident-report' ),
			array( $this, 'render' ),
			DDA_Incident_Report_Plugin::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<div class="dda-score-box">';
		self::render_form_fields( $post->ID );
		echo '</div>';
	}

	/**
	 * Render the score / notes / send-email inputs.
	 * Reused by the admin meta box and the TutorLMS dashboard form.
	 */
	public static function render_form_fields( $post_id ) {
		$score     = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_SCORE, true );
		$notes     = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEW_NOTES, true );
		$reviewed  = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_AT, true );
		$sent      = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_EMAIL_SENT, true );
		$by        = (int) get_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_BY, true );
		$pass      = DDA_Incident_Report_User_State::passing_score();
		$has_score = ( '' !== $score && null !== $score );

		if ( $has_score ) {
			$passed       = (float) $score >= $pass;
			$status_class = $passed ? 'pass' : 'fail';
			$status_text  = $passed ? __( 'Passed', 'dda-incident-report' ) : __( 'Failed', 'dda-incident-report' );
			echo '<div class="dda-score-status dda-' . esc_attr( $status_class ) . '">';
			echo '<span class="dda-score-pill">' . esc_html( $status_text ) . '</span>';
			echo '<span class="dda-score-value">' . esc_html( number_format_i18n( (float) $score, 1 ) ) . '<small>/100</small></span>';
			echo '</div>';
		} else {
			echo '<p class="dda-score-pending">' . esc_html__( 'Awaiting your review.', 'dda-incident-report' ) . '</p>';
		}

		echo '<p><label for="dda_score_input"><strong>' . esc_html__( 'Score (0 – 100)', 'dda-incident-report' ) . '</strong></label>';
		echo '<input id="dda_score_input" type="number" step="0.1" min="0" max="100" name="dda_score" value="' . esc_attr( $score ) . '" class="widefat"></p>';

		echo '<p><label for="dda_review_notes"><strong>' . esc_html__( 'Review Notes (shown to learner)', 'dda-incident-report' ) . '</strong></label>';
		echo '<textarea id="dda_review_notes" name="dda_review_notes" rows="5" class="widefat">' . esc_textarea( $notes ) . '</textarea></p>';

		echo '<p class="dda-score-meta">';
		echo '<small>' . esc_html__( 'Passing threshold:', 'dda-incident-report' ) . ' <strong>' . esc_html( $pass ) . '%</strong></small>';
		if ( $reviewed ) {
			$reviewer      = $by ? get_userdata( $by ) : null;
			$reviewer_name = $reviewer ? $reviewer->display_name : __( 'Unknown', 'dda-incident-report' );
			echo '<br><small>' . esc_html__( 'Last reviewed:', 'dda-incident-report' ) . ' ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reviewed ) ) . ' &mdash; ' . esc_html( $reviewer_name ) . '</small>';
		}
		echo '</p>';

		echo '<p class="dda-score-email">';
		echo '<label><input type="checkbox" name="dda_send_email" value="1" ' . checked( ! $sent, true, false ) . '> ';
		echo esc_html__( 'Email the result to the submitter on save', 'dda-incident-report' );
		echo '</label>';
		if ( $sent ) {
			echo '<br><small class="description">' . esc_html__( 'An email has already been sent. Check the box to send again.', 'dda-incident-report' ) . '</small>';
		}
		echo '</p>';
	}

	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! DDA_Incident_Report_User_State::user_can_score() ) {
			return;
		}

		$raw_score  = isset( $_POST['dda_score'] ) ? wp_unslash( $_POST['dda_score'] ) : '';
		$notes      = isset( $_POST['dda_review_notes'] ) ? wp_unslash( $_POST['dda_review_notes'] ) : null;
		$send_email = ! empty( $_POST['dda_send_email'] );

		self::apply_score( $post_id, $raw_score, $notes, $send_email );
	}

	/**
	 * Apply a score + notes to a report. Used by both the admin save_post
	 * handler and the TutorLMS frontend score handler.
	 *
	 * @param int         $post_id    Report ID.
	 * @param string      $raw_score  Score string ('' to clear, otherwise 0–100).
	 * @param string|null $notes      Review notes (null = don't touch).
	 * @param bool        $send_email Whether to send the result email.
	 * @return bool                    True if processing completed.
	 */
	public static function apply_score( $post_id, $raw_score, $notes = null, $send_email = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		$raw_score = is_string( $raw_score ) ? trim( $raw_score ) : $raw_score;
		$score_set = false;
		$score     = null;

		if ( '' === $raw_score || null === $raw_score ) {
			delete_post_meta( $post_id, DDA_Incident_Report_User_State::META_SCORE );
			delete_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_AT );
			delete_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_BY );
		} else {
			$score = (float) $raw_score;
			$score = max( 0, min( 100, $score ) );
			update_post_meta( $post_id, DDA_Incident_Report_User_State::META_SCORE, $score );
			update_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_AT, current_time( 'mysql' ) );
			update_post_meta( $post_id, DDA_Incident_Report_User_State::META_REVIEWED_BY, get_current_user_id() );
			$score_set = true;
		}

		if ( null !== $notes ) {
			update_post_meta(
				$post_id,
				DDA_Incident_Report_User_State::META_REVIEW_NOTES,
				sanitize_textarea_field( $notes )
			);
		}

		$email_sent = false;
		if ( $score_set && $send_email ) {
			$emailer    = new DDA_Incident_Report_Emailer();
			$email_sent = (bool) $emailer->send_result( $post_id, $score );
			if ( $email_sent ) {
				update_post_meta( $post_id, DDA_Incident_Report_User_State::META_EMAIL_SENT, current_time( 'mysql' ) );
			}
		}

		/**
		 * Fires after a report has been scored (or cleared).
		 *
		 * @param int        $post_id Report ID.
		 * @param float|null $score   Applied score, or null if cleared.
		 * @param bool       $sent    Whether an email was sent.
		 */
		do_action( 'dda_incident_report_scored', $post_id, $score, $email_sent );

		return true;
	}
}
