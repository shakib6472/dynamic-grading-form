<?php
/**
 * Renders the [dda_incident_report] shortcode with state-aware output:
 *
 *  guest          → login prompt
 *  eligible       → form
 *  awaiting       → "Thank you, instructor is reviewing"
 *  passed / failed → result card with score
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Shortcode {

	public function __construct() {
		add_shortcode( 'dda_incident_report', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$state = DDA_Incident_Report_User_State::get_state();

		ob_start();
		echo '<div class="dda-incident-app">';

		$this->maybe_render_flash_messages( $state );

		switch ( $state ) {
			case DDA_Incident_Report_User_State::STATE_GUEST:
				$this->render_login_prompt();
				break;
			case DDA_Incident_Report_User_State::STATE_ELIGIBLE:
				$this->render_form();
				break;
			case DDA_Incident_Report_User_State::STATE_AWAITING_REVIEW:
				$this->render_awaiting();
				break;
			case DDA_Incident_Report_User_State::STATE_PASSED:
			case DDA_Incident_Report_User_State::STATE_FAILED:
				$this->render_result( $state );
				break;
		}

		echo '</div>';
		return ob_get_clean();
	}

	private function maybe_render_flash_messages( $state ) {
		if ( isset( $_GET['dda_submitted'] ) && '1' === $_GET['dda_submitted']
			&& DDA_Incident_Report_User_State::STATE_AWAITING_REVIEW === $state ) {
			echo '<div class="dda-flash dda-flash-success">';
			echo '<strong>' . esc_html__( 'Thank you!', 'dda-incident-report' ) . '</strong> ';
			echo esc_html__( 'Your incident report has been submitted successfully.', 'dda-incident-report' );
			echo '</div>';
		}

		if ( isset( $_GET['dda_error'] ) ) {
			$messages = array(
				'missing_required'  => __( 'Please complete all required fields and try again.', 'dda-incident-report' ),
				'login_required'    => __( 'You must be logged in to submit the form.', 'dda-incident-report' ),
				'already_submitted' => __( 'You have already submitted a report. Only one submission per account is allowed.', 'dda-incident-report' ),
				'insert_failed'     => __( 'Something went wrong while saving your report. Please try again.', 'dda-incident-report' ),
			);
			$code = sanitize_key( $_GET['dda_error'] );
			$msg  = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'There was a problem submitting your report.', 'dda-incident-report' );
			echo '<div class="dda-flash dda-flash-error">' . esc_html( $msg ) . '</div>';
		}
	}

	private function render_login_prompt() {
		$login_url = wp_login_url( get_permalink() );
		?>
		<div class="dda-card dda-card-center">
			<div class="dda-card-icon dda-icon-lock">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 10V8a6 6 0 1 1 12 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="4" y="10" width="16" height="11" rx="2" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="15.5" r="1.5" fill="currentColor"/></svg>
			</div>
			<h2 class="dda-heading"><?php esc_html_e( 'Sign in to start the assessment', 'dda-incident-report' ); ?></h2>
			<p class="dda-lead"><?php esc_html_e( 'The DDA incident report assessment is available to enrolled learners. Please log in to your account to begin.', 'dda-incident-report' ); ?></p>
			<a class="dda-btn dda-btn-primary" href="<?php echo esc_url( $login_url ); ?>">
				<?php esc_html_e( 'Log in to continue', 'dda-incident-report' ); ?>
			</a>
		</div>
		<?php
	}

	private function render_awaiting() {
		$user      = wp_get_current_user();
		$report_id = DDA_Incident_Report_User_State::get_report_id_for_user( $user->ID );
		$submitted = $report_id ? get_post_meta( $report_id, DDA_Incident_Report_Plugin::META_PREFIX . 'submitted_at', true ) : '';
		?>
		<div class="dda-card dda-card-center">
			<div class="dda-card-icon dda-icon-hourglass">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 2h12M6 22h12M8 2v4a4 4 0 0 0 8 0V2M8 22v-4a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</div>
			<span class="dda-pill dda-pill-info"><?php esc_html_e( 'Pending review', 'dda-incident-report' ); ?></span>
			<h2 class="dda-heading"><?php esc_html_e( 'Thank you — your report has been received', 'dda-incident-report' ); ?></h2>
			<p class="dda-lead">
				<?php esc_html_e( 'An instructor is currently reviewing your submission. You will receive an email with your result, and your score will also appear on this page once the review is complete.', 'dda-incident-report' ); ?>
			</p>
			<?php if ( $submitted ) : ?>
				<div class="dda-meta-line">
					<?php
					/* translators: %s: human-readable date */
					printf( esc_html__( 'Submitted on %s', 'dda-incident-report' ), esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submitted ) ) );
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_result( $state ) {
		$user      = wp_get_current_user();
		$report_id = DDA_Incident_Report_User_State::get_report_id_for_user( $user->ID );
		$score     = DDA_Incident_Report_User_State::get_score( $report_id );
		$threshold = DDA_Incident_Report_User_State::passing_score();
		$passed    = DDA_Incident_Report_User_State::STATE_PASSED === $state;
		$notes     = get_post_meta( $report_id, DDA_Incident_Report_User_State::META_REVIEW_NOTES, true );
		$reviewed  = get_post_meta( $report_id, DDA_Incident_Report_User_State::META_REVIEWED_AT, true );
		$percent   = max( 0, min( 100, (float) $score ) );

		$status_class = $passed ? 'dda-status-pass' : 'dda-status-fail';
		$pill_text    = $passed ? __( 'Passed', 'dda-incident-report' ) : __( 'Not Passed', 'dda-incident-report' );
		$headline     = $passed
			? __( 'Congratulations — you passed!', 'dda-incident-report' )
			: __( 'Your result is ready', 'dda-incident-report' );
		$lead         = $passed
			? __( 'Great work! You met the required standard on the DDA incident report assessment.', 'dda-incident-report' )
			: __( 'Unfortunately, the score on your incident report assessment did not meet the passing threshold.', 'dda-incident-report' );
		?>
		<div class="dda-result-card <?php echo esc_attr( $status_class ); ?>">
			<div class="dda-result-header">
				<span class="dda-pill"><?php echo esc_html( $pill_text ); ?></span>
				<h2 class="dda-heading"><?php echo esc_html( $headline ); ?></h2>
				<p class="dda-lead"><?php echo esc_html( $lead ); ?></p>
			</div>

			<div class="dda-score-display">
				<div class="dda-score-ring" style="--dda-progress: <?php echo esc_attr( $percent ); ?>;">
					<div class="dda-score-ring-inner">
						<span class="dda-score-number"><?php echo esc_html( number_format_i18n( (float) $score, 1 ) ); ?></span>
						<span class="dda-score-suffix">/ 100</span>
					</div>
				</div>
				<div class="dda-score-detail">
					<div class="dda-score-row">
						<span class="dda-score-label"><?php esc_html_e( 'Passing threshold', 'dda-incident-report' ); ?></span>
						<span class="dda-score-value"><?php echo esc_html( $threshold ); ?>%</span>
					</div>
					<?php if ( $reviewed ) : ?>
					<div class="dda-score-row">
						<span class="dda-score-label"><?php esc_html_e( 'Reviewed', 'dda-incident-report' ); ?></span>
						<span class="dda-score-value"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $reviewed ) ); ?></span>
					</div>
					<?php endif; ?>
					<div class="dda-score-row">
						<span class="dda-score-label"><?php esc_html_e( 'Candidate', 'dda-incident-report' ); ?></span>
						<span class="dda-score-value"><?php echo esc_html( $user->display_name ); ?></span>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $notes ) ) : ?>
				<div class="dda-feedback">
					<h3 class="dda-feedback-title"><?php esc_html_e( 'Instructor Feedback', 'dda-incident-report' ); ?></h3>
					<p><?php echo nl2br( esc_html( $notes ) ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_form() {
		$submit_url = esc_url( admin_url( 'admin-post.php' ) );
		$user       = wp_get_current_user();
		?>
		<div class="dda-form-intro">
			<span class="dda-pill dda-pill-info"><?php esc_html_e( 'Assessment', 'dda-incident-report' ); ?></span>
			<h2 class="dda-heading"><?php esc_html_e( 'DDA Incident Report', 'dda-incident-report' ); ?></h2>
			<p class="dda-lead"><?php esc_html_e( 'Read the scenario below, then complete every required field. You can only submit this form once — please review your answers carefully before submitting.', 'dda-incident-report' ); ?></p>
			<div class="dda-meta-line">
				<?php
				/* translators: %s: user display name */
				printf( esc_html__( 'Logged in as %s', 'dda-incident-report' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' );
				?>
			</div>
		</div>

		<?php $this->render_scenario(); ?>

		<form method="post" action="<?php echo $submit_url; ?>" class="dda-incident-form" novalidate>
			<input type="hidden" name="action" value="dda_incident_submit">
			<?php wp_nonce_field( DDA_Incident_Report_Plugin::NONCE_ACTION, DDA_Incident_Report_Plugin::NONCE_NAME ); ?>

			<?php $this->render_form_sections(); ?>

			<div class="dda-submit-bar">
				<p class="dda-submit-note"><?php esc_html_e( 'By submitting, you confirm that the information above is accurate to the best of your knowledge.', 'dda-incident-report' ); ?></p>
				<button type="submit" class="dda-btn dda-btn-primary dda-btn-lg">
					<?php esc_html_e( 'Submit Incident Report', 'dda-incident-report' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	private function render_scenario() {
		$scenario      = DDA_Incident_Report_Fields::scenario();
		$narrative     = is_array( $scenario['narrative'] ) ? $scenario['narrative'] : array( (string) $scenario['narrative'] );
		?>
		<details class="dda-scenario" open>
			<summary class="dda-scenario-summary">
				<span class="dda-scenario-pill"><?php esc_html_e( 'Scenario', 'dda-incident-report' ); ?></span>
				<span class="dda-scenario-title"><?php echo esc_html( (string) $scenario['title'] ); ?></span>
				<span class="dda-scenario-toggle" aria-hidden="true"></span>
			</summary>
			<div class="dda-scenario-body">
				<div class="dda-scenario-block">
					<h4 class="dda-scenario-h"><?php esc_html_e( 'Instructions', 'dda-incident-report' ); ?></h4>
					<p><?php echo esc_html( (string) $scenario['instructions'] ); ?></p>
				</div>

				<div class="dda-scenario-block">
					<h4 class="dda-scenario-h"><?php esc_html_e( 'Scenario', 'dda-incident-report' ); ?></h4>
					<?php foreach ( $narrative as $para ) : ?>
						<p><?php echo esc_html( (string) $para ); ?></p>
					<?php endforeach; ?>
				</div>

				<div class="dda-scenario-callout">
					<strong><?php esc_html_e( 'Additional Information:', 'dda-incident-report' ); ?></strong>
					<?php echo esc_html( (string) $scenario['additional'] ); ?>
				</div>

				<div class="dda-scenario-block">
					<h4 class="dda-scenario-h"><?php esc_html_e( 'Ms. Brown’s Circle of Support', 'dda-incident-report' ); ?></h4>
					<ul class="dda-scenario-circle">
						<?php foreach ( (array) $scenario['circle'] as $line ) : ?>
							<li><?php echo esc_html( (string) $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</details>
		<?php
	}

	private function render_form_sections() {
		?>
		<!-- Header -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Person & Provider Information', 'dda-incident-report' ); ?></h3>

			<div class="dda-row">
				<div class="dda-col">
					<label>MCIS Report Number</label>
					<input type="text" name="mcis_report_number">
				</div>
				<div class="dda-col">
					<label>Date of Incident <span class="dda-required">*</span></label>
					<input type="date" name="date_of_incident" required>
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col">
					<label>Name of Primary Person Involved <span class="dda-required">*</span></label>
					<input type="text" name="primary_person_name" required>
				</div>
				<div class="dda-col">
					<label>Date of Birth</label>
					<input type="date" name="primary_person_dob">
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col">
					<label>Evans Class Member</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="evans_class_member" value="yes"> Yes</label>
						<label><input type="radio" name="evans_class_member" value="no"> No</label>
					</div>
				</div>
				<div class="dda-col">
					<label>Waiver</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="waiver" value="yes"> Yes</label>
						<label><input type="radio" name="waiver" value="no"> No</label>
					</div>
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Person Served by DDA's Address</label>
					<input type="text" name="person_address">
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col">
					<label>Provider's Name (Residential)</label>
					<input type="text" name="residential_provider_name">
				</div>
				<div class="dda-col">
					<label>Provider Phone</label>
					<input type="tel" name="residential_provider_phone">
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Residential Provider Type</label>
					<div class="dda-check-group dda-check-group-3">
						<?php foreach ( DDA_Incident_Report_Fields::residential_provider_types() as $key => $label ) : ?>
							<label><input type="radio" name="residential_provider_type" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<label><input type="radio" name="residential_provider_type" value="other"> Other</label>
					</div>
					<input type="text" name="residential_provider_type_other" placeholder="If other, please specify" class="dda-inline-other">
				</div>
			</div>
		</div>

		<!-- Location of Incident -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Location of Incident', 'dda-incident-report' ); ?></h3>
			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Address of Incident (if different from above)</label>
					<input type="text" name="incident_address">
				</div>
			</div>
			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Provider Name where Incident Occurred (if different from above)</label>
					<input type="text" name="incident_provider_name">
				</div>
			</div>
		</div>

		<!-- Other Persons Involved -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Other Persons Supported by DDA Involved', 'dda-incident-report' ); ?></h3>
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
				<div class="dda-row">
					<div class="dda-col">
						<label>Name</label>
						<input type="text" name="other_person_<?php echo $i; ?>_name">
					</div>
					<div class="dda-col">
						<label>Date of Birth</label>
						<input type="date" name="other_person_<?php echo $i; ?>_dob">
					</div>
				</div>
			<?php endfor; ?>
		</div>

		<!-- Staff Involved -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Staff Involved', 'dda-incident-report' ); ?></h3>
			<?php for ( $i = 1; $i <= 2; $i++ ) : ?>
				<div class="dda-row">
					<div class="dda-col">
						<label>Name</label>
						<input type="text" name="staff_<?php echo $i; ?>_name">
					</div>
					<div class="dda-col">
						<label>Phone</label>
						<input type="tel" name="staff_<?php echo $i; ?>_phone">
					</div>
				</div>
			<?php endfor; ?>
		</div>

		<!-- Reporter -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Reporter Information', 'dda-incident-report' ); ?></h3>
			<div class="dda-row">
				<div class="dda-col">
					<label>Name of Person Reporting <span class="dda-required">*</span></label>
					<input type="text" name="reporter_name" required>
				</div>
				<div class="dda-col">
					<label>Title</label>
					<input type="text" name="reporter_title">
				</div>
				<div class="dda-col">
					<label>Phone</label>
					<input type="tel" name="reporter_phone">
				</div>
			</div>
		</div>

		<!-- Section 1: Incident Categorization -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Section 1: Incident Categorization', 'dda-incident-report' ); ?></h3>

			<h4 class="dda-subsection-title">Serious Reportable</h4>
			<p class="dda-helper">Report to be submitted via MCIS the next business day by 5:00 p.m.</p>
			<div class="dda-check-group dda-check-group-2">
				<?php foreach ( DDA_Incident_Report_Fields::serious_reportable_options() as $key => $label ) : ?>
					<label><input type="checkbox" name="serious_reportable[]" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				<label><input type="checkbox" name="serious_reportable[]" value="other"> Other</label>
			</div>
			<input type="text" name="serious_reportable_other" placeholder="If other, please specify" class="dda-inline-other">

			<h4 class="dda-subsection-title">Abuse and Neglect Categories</h4>
			<div class="dda-check-group dda-check-group-3">
				<?php foreach ( DDA_Incident_Report_Fields::abuse_neglect_categories() as $key => $label ) : ?>
					<label><input type="checkbox" name="abuse_neglect_categories[]" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>

			<div class="dda-callout">
				<strong>For abuse, neglect, and exploitation allegations,</strong> staff must be removed from <u>all</u> customer contact immediately. Indicate below that this action has been taken.
			</div>
			<div class="dda-row">
				<div class="dda-col">
					<label>Supervisor Name (Certifying)</label>
					<input type="text" name="supervisor_cert_name">
				</div>
				<div class="dda-col">
					<label>Supervisor Title</label>
					<input type="text" name="supervisor_cert_title">
				</div>
				<div class="dda-col">
					<label>Signature (Typed)</label>
					<input type="text" name="supervisor_cert_signature">
				</div>
			</div>

			<h4 class="dda-subsection-title">Reportable</h4>
			<p class="dda-helper">Report written and maintained in-house for internal investigation and trending/tracking.</p>
			<div class="dda-check-group dda-check-group-2">
				<?php foreach ( DDA_Incident_Report_Fields::reportable_options() as $key => $label ) : ?>
					<label><input type="checkbox" name="reportable[]" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				<label><input type="checkbox" name="reportable[]" value="other"> Other</label>
			</div>
			<input type="text" name="reportable_other" placeholder="If other, please specify" class="dda-inline-other">

			<h4 class="dda-subsection-title">Incident Location Type</h4>
			<div class="dda-check-group dda-check-group-3">
				<?php foreach ( DDA_Incident_Report_Fields::location_types() as $key => $label ) : ?>
					<label><input type="radio" name="location_type" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				<label><input type="radio" name="location_type" value="other"> Other</label>
			</div>
			<input type="text" name="location_type_other" placeholder="If other, please specify" class="dda-inline-other">
		</div>

		<!-- Section 2: Description -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Section 2: Description of Incident', 'dda-incident-report' ); ?></h3>

			<div class="dda-row">
				<div class="dda-col">
					<label>Date of Incident <span class="dda-required">*</span></label>
					<input type="date" name="section2_date" required>
				</div>
				<div class="dda-col">
					<label>Time</label>
					<input type="time" name="section2_time">
				</div>
				<div class="dda-col">
					<label>AM / PM</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="section2_ampm" value="am"> A.M.</label>
						<label><input type="radio" name="section2_ampm" value="pm"> P.M.</label>
					</div>
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col">
					<label>Incident was</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="incident_source" value="informed"> Informed</label>
						<label><input type="radio" name="incident_source" value="witnessed"> Witnessed</label>
						<label><input type="radio" name="incident_source" value="discovered"> Discovered</label>
					</div>
				</div>
				<div class="dda-col">
					<label>Reporter Type</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="reporter_type" value="person_supported"> Person Supported</label>
						<label><input type="radio" name="reporter_type" value="employee"> Employee</label>
						<label><input type="radio" name="reporter_type" value="family_member"> Family Member</label>
						<label><input type="radio" name="reporter_type" value="visitor"> Visitor</label>
						<label><input type="radio" name="reporter_type" value="other"> Other</label>
					</div>
					<input type="text" name="reporter_type_other" placeholder="If other, please specify" class="dda-inline-other">
				</div>
			</div>

			<?php for ( $i = 1; $i <= 2; $i++ ) : ?>
				<div class="dda-row">
					<div class="dda-col">
						<label>Witness <?php echo $i; ?> Name</label>
						<input type="text" name="witness_<?php echo $i; ?>_name">
					</div>
					<div class="dda-col">
						<label>Witness <?php echo $i; ?> Phone</label>
						<input type="tel" name="witness_<?php echo $i; ?>_phone">
					</div>
				</div>
			<?php endfor; ?>

			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Description of the Incident: Who? What? When? Where? How? <span class="dda-required">*</span></label>
					<textarea name="incident_description" required></textarea>
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col dda-col-full">
					<label>Immediate Actions Taken</label>
					<textarea name="immediate_actions"></textarea>
				</div>
			</div>

			<div class="dda-row">
				<div class="dda-col">
					<label>Signature of Reporter (Typed) <span class="dda-required">*</span></label>
					<input type="text" name="reporter_signature" required>
				</div>
				<div class="dda-col">
					<label>Date</label>
					<input type="date" name="reporter_signature_date">
				</div>
				<div class="dda-col">
					<label>Time</label>
					<input type="time" name="reporter_signature_time">
				</div>
				<div class="dda-col">
					<label>AM / PM</label>
					<div class="dda-inline-radios">
						<label><input type="radio" name="reporter_signature_ampm" value="am"> A.M.</label>
						<label><input type="radio" name="reporter_signature_ampm" value="pm"> P.M.</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Verbal Notifications -->
		<div class="dda-section">
			<h3 class="dda-section-title"><?php esc_html_e( 'Verbal Notifications', 'dda-incident-report' ); ?></h3>
			<p class="dda-helper">Check all that apply and complete the person notified, date, and time.</p>

			<?php foreach ( DDA_Incident_Report_Fields::notification_recipients() as $key => $label ) : ?>
				<div class="dda-notification-row">
					<div class="dda-grid-3">
						<label class="dda-check">
							<input type="checkbox" name="notifications[<?php echo esc_attr( $key ); ?>][checked]" value="1">
							<?php echo esc_html( $label ); ?>
						</label>
						<input type="text" name="notifications[<?php echo esc_attr( $key ); ?>][person]" placeholder="Person notified">
						<div class="dda-grid-datetime">
							<input type="date" name="notifications[<?php echo esc_attr( $key ); ?>][date]">
							<input type="time" name="notifications[<?php echo esc_attr( $key ); ?>][time]">
						</div>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="dda-notification-row">
				<div class="dda-grid-3">
					<label class="dda-check">
						<input type="checkbox" name="notifications[other][checked]" value="1">
						Other:
						<input type="text" name="notifications[other][label]" placeholder="Specify" class="dda-other-inline">
					</label>
					<input type="text" name="notifications[other][person]" placeholder="Person notified">
					<div class="dda-grid-datetime">
						<input type="date" name="notifications[other][date]">
						<input type="time" name="notifications[other][time]">
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
