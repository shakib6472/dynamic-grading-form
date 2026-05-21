<?php
/**
 * TutorLMS Pro frontend instructor-dashboard integration.
 *
 * Adds a new dashboard tab "Incident Reports" visible to instructors.
 *  - List view:  paginated table of every submission (configurable filter).
 *  - Detail view: paper-form rendering + Review & Score panel + Print button.
 *  - Score is saved via admin-post.php and reuses
 *    DDA_Incident_Report_Scoring::apply_score().
 *
 * The integration degrades gracefully if TutorLMS is not active — it just
 * does nothing.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_TutorLMS {

	const TAB_KEY         = 'incident-reports';
	const NONCE_ACTION    = 'dda_tutor_score_save';
	const NONCE_NAME      = '_dda_tutor_score_nonce';
	const SCORE_POST_ACTION = 'dda_tutor_score_save';
	const PER_PAGE        = 20;

	/** @var self|null */
	private static $instance = null;

	public function __construct() {
		self::$instance = $this;

		// Tutor LMS uses slash-separated filter names (not underscore).
		// Hook into BOTH the general dashboard nav AND the instructor section
		// so admins (who don't have the tutor_instructor role) still see it.
		add_filter( 'tutor_dashboard/nav_items', array( $this, 'add_dashboard_tab' ) );
		add_filter( 'tutor_dashboard/instructor_nav_items', array( $this, 'add_dashboard_tab' ) );

		// Route Tutor's template loader to our plugin folder for our page.
		add_filter( 'tutor_get_template_path', array( $this, 'filter_template_path' ), 10, 2 );

		// Score submission handler.
		add_action( 'admin_post_' . self::SCORE_POST_ACTION, array( $this, 'handle_score_save' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	private function tutor_active() {
		return function_exists( 'tutor' );
	}

	/* ---------------------------------------------------------------------
	 * Dashboard menu registration
	 * ------------------------------------------------------------------ */

	public function add_dashboard_tab( $pages ) {
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}

		// Only register the tab for users who can review reports
		// (admins, editors, TutorLMS instructors, DDA instructors).
		// We gate here rather than relying on `auth_cap` because that only
		// accepts a single capability string and `tutor_instructor` is a
		// role admins don't have.
		if ( ! is_user_logged_in() || ! DDA_Incident_Report_User_State::user_can_score() ) {
			return $pages;
		}

		$pages[ self::TAB_KEY ] = array(
			'title' => __( 'Incident Reports', 'dda-incident-report' ),
			'icon'  => 'tutor-icon-clipboard',
		);

		return $pages;
	}

	/**
	 * Route Tutor's template loader to our plugin's tutor-templates/ folder
	 * when it tries to load our dashboard page.
	 *
	 * @param string $template_location Default folder Tutor will look in.
	 * @param string $template          Template slug Tutor is loading.
	 */
	public function filter_template_path( $template_location, $template ) {
		$template = is_string( $template ) ? $template : '';

		$candidates = array(
			'dashboard.' . self::TAB_KEY,
			'dashboard/' . self::TAB_KEY,
			'dashboard.' . self::TAB_KEY . '.php',
			'dashboard/' . self::TAB_KEY . '.php',
		);

		if ( in_array( $template, $candidates, true ) ) {
			return trailingslashit( DDA_INCIDENT_REPORT_DIR . 'tutor-templates' );
		}

		return $template_location;
	}

	private function dashboard_url() {
		if ( $this->tutor_active() && function_exists( 'tutor_utils' ) ) {
			return tutor_utils()->tutor_dashboard_url( self::TAB_KEY );
		}
		return home_url( '/dashboard/' . self::TAB_KEY . '/' );
	}

	private function report_detail_url( $report_id ) {
		return add_query_arg( 'report', (int) $report_id, $this->dashboard_url() );
	}

	/* ---------------------------------------------------------------------
	 * Asset enqueue
	 * ------------------------------------------------------------------ */

	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( ! $this->is_tutor_dashboard() ) {
			return;
		}

		wp_enqueue_style(
			'dda-incident-report-tutorlms',
			DDA_INCIDENT_REPORT_URL . 'assets/css/dda-incident-report-tutorlms.css',
			array(),
			DDA_INCIDENT_REPORT_VERSION
		);

		// Reuse the admin paper-form CSS so the detail page looks consistent.
		wp_enqueue_style(
			'dda-incident-report-admin',
			DDA_INCIDENT_REPORT_URL . 'assets/css/dda-incident-report-admin.css',
			array(),
			DDA_INCIDENT_REPORT_VERSION
		);
	}

	/**
	 * Robust Tutor-dashboard detection — works regardless of the dashboard's
	 * URL slug or whether the rewrite has flushed.
	 */
	private function is_tutor_dashboard() {
		// 1) Tutor utils helper.
		if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'is_tutor_dashboard' ) ) {
			if ( tutor_utils()->is_tutor_dashboard() ) {
				return true;
			}
		}

		// 2) Page contains the [tutor_dashboard] shortcode.
		global $post;
		if ( $post && ! empty( $post->post_content ) && function_exists( 'has_shortcode' ) ) {
			if ( has_shortcode( $post->post_content, 'tutor_dashboard' ) ) {
				return true;
			}
		}

		// 3) URL contains /dashboard/.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
		if ( is_string( $path ) && false !== strpos( $path, '/dashboard/' ) ) {
			return true;
		}

		return false;
	}

	/* ---------------------------------------------------------------------
	 * Page renderer
	 * ------------------------------------------------------------------ */

	/**
	 * Static entry point for the Tutor LMS template file.
	 * Reuses the already-constructed singleton instance so no hooks are
	 * registered twice.
	 */
	public static function render() {
		if ( null === self::$instance ) {
			new self();
		}
		self::$instance->render_dashboard_page();
	}

	public function render_dashboard_page() {
		if ( ! DDA_Incident_Report_User_State::user_can_score() ) {
			echo '<div class="dda-tutor-page"><p>' . esc_html__( 'You do not have permission to view incident reports.', 'dda-incident-report' ) . '</p></div>';
			return;
		}

		$report_id = isset( $_GET['report'] ) ? (int) $_GET['report'] : 0;

		echo '<div class="dda-tutor-page">';

		$this->render_flash();

		if ( $report_id ) {
			$this->render_detail( $report_id );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	private function render_flash() {
		if ( ! empty( $_GET['dda_msg'] ) ) {
			$messages = array(
				'score_saved'    => __( 'Score saved. The result email has been sent to the submitter.', 'dda-incident-report' ),
				'score_saved_no_email' => __( 'Score saved.', 'dda-incident-report' ),
				'score_cleared'  => __( 'Score cleared. The report is back to pending review.', 'dda-incident-report' ),
				'score_error'    => __( 'Could not save the score. Please try again.', 'dda-incident-report' ),
				'access_denied'  => __( 'Access denied.', 'dda-incident-report' ),
			);
			$code = sanitize_key( $_GET['dda_msg'] );
			if ( isset( $messages[ $code ] ) ) {
				$type = ( 'score_error' === $code || 'access_denied' === $code ) ? 'error' : 'success';
				echo '<div class="dda-tutor-flash dda-tutor-flash-' . esc_attr( $type ) . '">' . esc_html( $messages[ $code ] ) . '</div>';
			}
		}
	}

	private function render_list() {
		$paged   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$status  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$args = array(
			'post_type'      => DDA_Incident_Report_Plugin::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( 'pending' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'     => DDA_Incident_Report_User_State::META_SCORE,
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ( 'passed' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'     => DDA_Incident_Report_User_State::META_SCORE,
					'value'   => DDA_Incident_Report_User_State::passing_score(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		} elseif ( 'failed' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'     => DDA_Incident_Report_User_State::META_SCORE,
					'value'   => DDA_Incident_Report_User_State::passing_score(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			);
		}

		$query = new WP_Query( $args );

		$totals = $this->get_status_counts();
		?>
		<header class="dda-tutor-header">
			<div>
				<h1 class="dda-tutor-title"><?php esc_html_e( 'Incident Reports', 'dda-incident-report' ); ?></h1>
				<p class="dda-tutor-subtitle"><?php esc_html_e( 'Review submissions, assign a score, and send the learner a pass/fail email.', 'dda-incident-report' ); ?></p>
			</div>
			<div class="dda-tutor-stats">
				<div class="dda-stat"><span class="num"><?php echo esc_html( $totals['all'] ); ?></span><span class="label"><?php esc_html_e( 'Total', 'dda-incident-report' ); ?></span></div>
				<div class="dda-stat dda-stat-pending"><span class="num"><?php echo esc_html( $totals['pending'] ); ?></span><span class="label"><?php esc_html_e( 'Pending', 'dda-incident-report' ); ?></span></div>
				<div class="dda-stat dda-stat-pass"><span class="num"><?php echo esc_html( $totals['passed'] ); ?></span><span class="label"><?php esc_html_e( 'Passed', 'dda-incident-report' ); ?></span></div>
				<div class="dda-stat dda-stat-fail"><span class="num"><?php echo esc_html( $totals['failed'] ); ?></span><span class="label"><?php esc_html_e( 'Failed', 'dda-incident-report' ); ?></span></div>
			</div>
		</header>

		<form class="dda-tutor-filters" method="get">
			<?php $this->print_keep_query_vars( array( 'status', 's', 'paged', 'report' ) ); ?>
			<div class="dda-tutor-tabs">
				<?php
				$statuses = array(
					'all'     => __( 'All', 'dda-incident-report' ),
					'pending' => __( 'Pending Review', 'dda-incident-report' ),
					'passed'  => __( 'Passed', 'dda-incident-report' ),
					'failed'  => __( 'Failed', 'dda-incident-report' ),
				);
				foreach ( $statuses as $key => $label ) :
					$url = add_query_arg( array( 'status' => $key, 'paged' => 1 ), $this->dashboard_url() );
					if ( ! empty( $search ) ) {
						$url = add_query_arg( 's', $search, $url );
					}
					$is_active = ( $status === $key );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="dda-tab <?php echo $is_active ? 'is-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
						<span class="dda-tab-count"><?php echo esc_html( $totals[ $key ] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="dda-tutor-search">
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search reports…', 'dda-incident-report' ); ?>">
				<button type="submit"><?php esc_html_e( 'Search', 'dda-incident-report' ); ?></button>
			</div>
		</form>

		<?php if ( ! $query->have_posts() ) : ?>
			<div class="dda-tutor-empty">
				<p><?php esc_html_e( 'No incident reports match this view.', 'dda-incident-report' ); ?></p>
			</div>
		<?php else : ?>
			<div class="dda-tutor-table-wrap">
				<table class="dda-tutor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Submitter', 'dda-incident-report' ); ?></th>
							<th><?php esc_html_e( 'Person Involved', 'dda-incident-report' ); ?></th>
							<th><?php esc_html_e( 'Incident Date', 'dda-incident-report' ); ?></th>
							<th><?php esc_html_e( 'Submitted', 'dda-incident-report' ); ?></th>
							<th><?php esc_html_e( 'Status', 'dda-incident-report' ); ?></th>
							<th class="dda-col-action"></th>
						</tr>
					</thead>
					<tbody>
						<?php while ( $query->have_posts() ) : $query->the_post();
							$pid    = get_the_ID();
							$author = get_userdata( get_post_field( 'post_author', $pid ) );
							$person = get_post_meta( $pid, DDA_Incident_Report_Plugin::META_PREFIX . 'primary_person_name', true );
							$inc_dt = get_post_meta( $pid, DDA_Incident_Report_Plugin::META_PREFIX . 'date_of_incident', true );
							$score  = get_post_meta( $pid, DDA_Incident_Report_User_State::META_SCORE, true );
							$pass   = DDA_Incident_Report_User_State::passing_score();
							?>
							<tr>
								<td>
									<?php if ( $author ) : ?>
										<div class="dda-cell-strong"><?php echo esc_html( $author->display_name ); ?></div>
										<div class="dda-cell-faint"><?php echo esc_html( $author->user_email ); ?></div>
									<?php else : ?>
										<em><?php esc_html_e( '(deleted user)', 'dda-incident-report' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $person ); ?></td>
								<td><?php echo esc_html( $inc_dt ); ?></td>
								<td><?php echo esc_html( get_the_date( get_option( 'date_format' ) ) ); ?></td>
								<td>
									<?php if ( '' === $score ) : ?>
										<span class="dda-pill dda-pill-pending"><?php esc_html_e( 'Pending', 'dda-incident-report' ); ?></span>
									<?php else :
										$passed = (float) $score >= $pass;
										$cls    = $passed ? 'dda-pill-pass' : 'dda-pill-fail';
										$label  = $passed ? __( 'Passed', 'dda-incident-report' ) : __( 'Failed', 'dda-incident-report' );
										?>
										<span class="dda-pill <?php echo esc_attr( $cls ); ?>">
											<?php echo esc_html( $label ); ?> &middot; <?php echo esc_html( number_format_i18n( (float) $score, 1 ) ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="dda-col-action">
									<a class="dda-btn-sm dda-btn-primary" href="<?php echo esc_url( $this->report_detail_url( $pid ) ); ?>">
										<?php esc_html_e( 'Review', 'dda-incident-report' ); ?>
									</a>
									<a class="dda-btn-sm dda-btn-ghost" target="_blank" href="<?php echo esc_url( DDA_Incident_Report_Printer::url( $pid ) ); ?>">
										<?php esc_html_e( 'Print', 'dda-incident-report' ); ?>
									</a>
								</td>
							</tr>
						<?php endwhile; ?>
					</tbody>
				</table>
			</div>

			<?php $this->render_pagination( $query, $paged, $status, $search ); ?>
		<?php endif; ?>
		<?php
		wp_reset_postdata();
	}

	private function render_pagination( $query, $paged, $status, $search ) {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}

		$base = add_query_arg( array( 'status' => $status ), $this->dashboard_url() );
		if ( ! empty( $search ) ) {
			$base = add_query_arg( 's', $search, $base );
		}

		echo '<nav class="dda-tutor-pagination">';
		echo paginate_links( array(
			'base'      => add_query_arg( 'paged', '%#%', $base ),
			'format'    => '',
			'current'   => $paged,
			'total'     => $query->max_num_pages,
			'prev_text' => __( '« Previous', 'dda-incident-report' ),
			'next_text' => __( 'Next »', 'dda-incident-report' ),
		) );
		echo '</nav>';
	}

	private function render_detail( $report_id ) {
		$post = get_post( $report_id );
		if ( ! $post || DDA_Incident_Report_Plugin::POST_TYPE !== $post->post_type ) {
			echo '<p>' . esc_html__( 'Report not found.', 'dda-incident-report' ) . '</p>';
			return;
		}

		$author    = get_userdata( $post->post_author );
		$submitted = get_post_meta( $report_id, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_at', true );

		?>
		<div class="dda-tutor-detail">
			<header class="dda-tutor-detail-header">
				<a class="dda-back" href="<?php echo esc_url( $this->dashboard_url() ); ?>">&larr; <?php esc_html_e( 'Back to all reports', 'dda-incident-report' ); ?></a>
				<div class="dda-tutor-detail-meta">
					<h2 class="dda-tutor-detail-title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
					<div class="dda-tutor-detail-sub">
						<?php if ( $author ) : ?>
							<?php
							/* translators: %s: submitter name */
							printf( esc_html__( 'Submitted by %s', 'dda-incident-report' ), '<strong>' . esc_html( $author->display_name ) . '</strong>' );
							?>
							&middot;
							<a href="mailto:<?php echo esc_attr( $author->user_email ); ?>"><?php echo esc_html( $author->user_email ); ?></a>
						<?php endif; ?>
						<?php if ( $submitted ) : ?>
							&middot; <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submitted ) ); ?>
						<?php endif; ?>
					</div>
				</div>
				<div class="dda-tutor-detail-actions">
					<a class="dda-btn-sm dda-btn-ghost" target="_blank" href="<?php echo esc_url( DDA_Incident_Report_Printer::url( $report_id ) ); ?>">
						&#128424; <?php esc_html_e( 'Print PDF Form', 'dda-incident-report' ); ?>
					</a>
				</div>
			</header>

			<div class="dda-tutor-detail-grid">
				<div class="dda-tutor-paper">
					<?php DDA_Incident_Report_Paper_View::render( $post ); ?>
				</div>

				<aside class="dda-tutor-score-panel">
					<h3 class="dda-tutor-score-title"><?php esc_html_e( 'Review & Score', 'dda-incident-report' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dda-tutor-score-form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::SCORE_POST_ACTION ); ?>">
						<input type="hidden" name="report_id" value="<?php echo (int) $report_id; ?>">
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<div class="dda-score-box">
							<?php DDA_Incident_Report_Scoring::render_form_fields( $report_id ); ?>
						</div>
						<div class="dda-tutor-score-actions">
							<button type="submit" class="dda-btn-sm dda-btn-primary"><?php esc_html_e( 'Save & Send', 'dda-incident-report' ); ?></button>
							<a class="dda-btn-sm dda-btn-ghost" href="<?php echo esc_url( $this->dashboard_url() ); ?>"><?php esc_html_e( 'Cancel', 'dda-incident-report' ); ?></a>
						</div>
					</form>
				</aside>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Score submission handler (admin-post.php)
	 * ------------------------------------------------------------------ */

	public function handle_score_save() {
		$redirect = isset( $_POST['_referrer'] ) ? esc_url_raw( wp_unslash( $_POST['_referrer'] ) ) : wp_get_referer();
		if ( ! $redirect ) {
			$redirect = $this->dashboard_url();
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $redirect ) );
			exit;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'dda_msg', 'score_error', $redirect ) );
			exit;
		}

		if ( ! DDA_Incident_Report_User_State::user_can_score() ) {
			wp_safe_redirect( add_query_arg( 'dda_msg', 'access_denied', $redirect ) );
			exit;
		}

		$report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
		$post      = $report_id ? get_post( $report_id ) : null;
		if ( ! $post || DDA_Incident_Report_Plugin::POST_TYPE !== $post->post_type ) {
			wp_safe_redirect( add_query_arg( 'dda_msg', 'score_error', $redirect ) );
			exit;
		}

		$raw_score  = isset( $_POST['dda_score'] ) ? wp_unslash( $_POST['dda_score'] ) : '';
		$notes      = isset( $_POST['dda_review_notes'] ) ? wp_unslash( $_POST['dda_review_notes'] ) : null;
		$send_email = ! empty( $_POST['dda_send_email'] );

		DDA_Incident_Report_Scoring::apply_score( $report_id, $raw_score, $notes, $send_email );

		// Pick a flash code.
		$raw_score = is_string( $raw_score ) ? trim( $raw_score ) : $raw_score;
		if ( '' === $raw_score ) {
			$code = 'score_cleared';
		} elseif ( $send_email ) {
			$code = 'score_saved';
		} else {
			$code = 'score_saved_no_email';
		}

		$target = $this->report_detail_url( $report_id );
		wp_safe_redirect( add_query_arg( 'dda_msg', $code, $target ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private function get_status_counts() {
		$counts = array(
			'all'     => 0,
			'pending' => 0,
			'passed'  => 0,
			'failed'  => 0,
		);

		global $wpdb;
		$pass = DDA_Incident_Report_User_State::passing_score();
		$cpt  = DDA_Incident_Report_Plugin::POST_TYPE;
		$mk   = DDA_Incident_Report_User_State::META_SCORE;

		$counts['all'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
			$cpt
		) );

		$counts['pending'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_id IS NULL",
			$mk,
			$cpt
		) );

		$counts['passed'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			   AND pm.meta_key = %s AND CAST(pm.meta_value AS DECIMAL(6,2)) >= %f",
			$cpt,
			$mk,
			(float) $pass
		) );

		$counts['failed'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			   AND pm.meta_key = %s AND CAST(pm.meta_value AS DECIMAL(6,2)) < %f",
			$cpt,
			$mk,
			(float) $pass
		) );

		return $counts;
	}

	private function print_keep_query_vars( $skip = array() ) {
		foreach ( $_GET as $key => $value ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
	}
}
