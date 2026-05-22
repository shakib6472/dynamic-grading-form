<?php
/**
 * Resolves the current viewer's state in the incident report flow.
 *
 * States:
 *  - guest            (not logged in)
 *  - eligible         (logged in, no submission yet)
 *  - awaiting_review  (submitted, no score yet)
 *  - passed           (scored, score >= passing threshold)
 *  - failed           (scored, score < passing threshold)
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_User_State {

	const STATE_GUEST           = 'guest';
	const STATE_ELIGIBLE        = 'eligible';
	const STATE_AWAITING_REVIEW = 'awaiting_review';
	const STATE_PASSED          = 'passed';
	const STATE_FAILED          = 'failed';

	const META_SCORE        = '_dda_score';
	const META_REVIEW_NOTES = '_dda_review_notes';
	const META_REVIEWED_AT  = '_dda_reviewed_at';
	const META_REVIEWED_BY  = '_dda_reviewed_by';
	const META_EMAIL_SENT   = '_dda_email_sent';
	const META_RESULT_URL   = '_dda_result_url';

	public static function passing_score() {
		/**
		 * Filter the passing score percentage (0-100).
		 *
		 * @param int $threshold Default 80.
		 */
		return (int) apply_filters( 'dda_incident_report_passing_score', 80 );
	}

	public static function get_report_id_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}

		$reports = get_posts(
			array(
				'post_type'      => DDA_Incident_Report_Plugin::POST_TYPE,
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $reports ) ? (int) $reports[0] : 0;
	}

	public static function get_score( $post_id ) {
		$score = get_post_meta( $post_id, self::META_SCORE, true );
		if ( '' === $score || null === $score ) {
			return null;
		}
		return (float) $score;
	}

	public static function get_state() {
		if ( ! is_user_logged_in() ) {
			return self::STATE_GUEST;
		}

		$user_id   = get_current_user_id();
		$report_id = self::get_report_id_for_user( $user_id );

		if ( ! $report_id ) {
			return self::STATE_ELIGIBLE;
		}

		$score = self::get_score( $report_id );

		if ( null === $score ) {
			return self::STATE_AWAITING_REVIEW;
		}

		return $score >= self::passing_score() ? self::STATE_PASSED : self::STATE_FAILED;
	}

	public static function user_can_score( $user_id = null ) {
		$check_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $check_id <= 0 ) {
			return (bool) apply_filters( 'dda_incident_report_user_can_score', false, $user_id );
		}

		$user = get_userdata( $check_id );
		$can  = false;

		if ( $user ) {
			$roles = (array) $user->roles;

			// Standard WP capabilities — admins and editors pass via these.
			if ( user_can( $check_id, 'manage_options' )      // Administrator.
				|| user_can( $check_id, 'edit_others_posts' ) // Editor.
			) {
				$can = true;
			}

			if ( ! $can ) {
				// Common instructor / reviewer role keys.
				$reviewer_roles = array(
					'administrator',
					'editor',
					'tutor_instructor',
					'dda_instructor',
				);

				// Honor whatever role key TutorLMS reports at runtime
				// (Pro and custom builds occasionally vary).
				if ( function_exists( 'tutor' ) ) {
					$tutor = tutor();
					if ( is_object( $tutor ) && isset( $tutor->instructor_role ) && $tutor->instructor_role ) {
						$reviewer_roles[] = (string) $tutor->instructor_role;
					}
				}

				foreach ( $reviewer_roles as $role ) {
					if ( in_array( $role, $roles, true ) ) {
						$can = true;
						break;
					}
				}
			}

			// Final fallback: ask TutorLMS itself if this user is an instructor.
			if ( ! $can && function_exists( 'tutor_utils' ) ) {
				$utils = tutor_utils();
				if ( $utils && method_exists( $utils, 'is_instructor' ) ) {
					if ( $utils->is_instructor( $check_id ) ) {
						$can = true;
					}
				}
			}
		}

		/**
		 * Filter whether a user is allowed to score incident reports.
		 *
		 * @param bool     $can     Permission flag.
		 * @param int|null $user_id User being checked (null = current user).
		 */
		return (bool) apply_filters( 'dda_incident_report_user_can_score', $can, $user_id );
	}
}
