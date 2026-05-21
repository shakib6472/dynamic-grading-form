<?php
/**
 * Handles the standalone print URL: ?dda_print=<post_id>
 *
 * Access:
 *   - The report's author (the learner) can print their own report.
 *   - Any user who passes user_can_score() (admins, editors, TutorLMS
 *     instructors, DDA instructors) can print any report.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Printer {

	const QUERY_VAR = 'dda_print';

	public function __construct() {
		add_action( 'init', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 1 );
	}

	public function register_query_var() {
		global $wp;
		$wp->add_query_var( self::QUERY_VAR );
	}

	public function maybe_render() {
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$post_id = (int) $_GET[ self::QUERY_VAR ];
		if ( $post_id <= 0 ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || DDA_Incident_Report_Plugin::POST_TYPE !== $post->post_type ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		if ( ! $this->can_view( $post ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( $this->build_url( $post_id ) ) );
				exit;
			}
			wp_die(
				esc_html__( 'You do not have permission to view this report.', 'dda-incident-report' ),
				esc_html__( 'Access denied', 'dda-incident-report' ),
				array( 'response' => 403 )
			);
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		DDA_Incident_Report_Print_View::render_document( $post_id );
		exit;
	}

	/**
	 * Build the public print URL for a given report.
	 *
	 * @param int  $post_id     Report ID.
	 * @param bool $auto_print  Whether the browser should auto-trigger the
	 *                           print dialog on load (default true).
	 * @return string
	 */
	public static function url( $post_id, $auto_print = true ) {
		$args = array( self::QUERY_VAR => (int) $post_id );
		if ( ! $auto_print ) {
			$args['autoprint'] = '0';
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	private function can_view( $post ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$current_user_id = get_current_user_id();

		// Author can view own report.
		if ( (int) $post->post_author === $current_user_id ) {
			return true;
		}

		// Anyone who can score can also print.
		if ( DDA_Incident_Report_User_State::user_can_score( $current_user_id ) ) {
			return true;
		}

		return false;
	}
}
