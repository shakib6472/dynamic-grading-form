<?php
/**
 * WP-admin meta boxes and list-table columns for incident reports.
 *
 * The paper-form rendering itself lives in DDA_Incident_Report_Paper_View
 * so it can be reused by the TutorLMS dashboard and print template.
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
		add_filter( 'post_row_actions', array( $this, 'add_print_row_action' ), 10, 2 );

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
			__( 'Incident Report', 'dda-incident-report' ),
			array( $this, 'render_meta_box' ),
			DDA_Incident_Report_Plugin::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'dda_incident_submitter',
			__( 'Submitter & Actions', 'dda-incident-report' ),
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
		$print  = DDA_Incident_Report_Printer::url( $post->ID );

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

		echo '<hr style="margin:10px 0;border:0;border-top:1px solid #e0e0e0;">';
		echo '<a href="' . esc_url( $print ) . '" target="_blank" class="button button-primary" style="width:100%;text-align:center;">';
		echo '&#128424; ' . esc_html__( 'Print PDF Form', 'dda-incident-report' );
		echo '</a>';
		echo '</div>';
	}

	public function render_meta_box( $post ) {
		DDA_Incident_Report_Paper_View::render( $post );
	}

	public function add_print_row_action( $actions, $post ) {
		if ( DDA_Incident_Report_Plugin::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		$url = DDA_Incident_Report_Printer::url( $post->ID );
		$actions['dda_print'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html__( 'Print', 'dda-incident-report' )
		);
		return $actions;
	}

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
					$pass   = DDA_Incident_Report_User_State::passing_score();
					$passed = (float) $score >= $pass;
					$cls    = $passed ? 'dda-pill-pass' : 'dda-pill-fail';
					$label  = $passed ? __( 'Pass', 'dda-incident-report' ) : __( 'Fail', 'dda-incident-report' );
					echo '<span class="dda-pill ' . esc_attr( $cls ) . '">' . esc_html( $label ) . ' &middot; ' . esc_html( number_format_i18n( (float) $score, 1 ) ) . '</span>';
				}
				break;
		}
	}
}
