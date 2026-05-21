<?php
/**
 * Sends pass / fail result emails to the submitter.
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Emailer {

	public function send_result( $post_id, $score ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$user = get_userdata( $post->post_author );
		if ( ! $user || empty( $user->user_email ) ) {
			return false;
		}

		$score      = (float) $score;
		$threshold  = DDA_Incident_Report_User_State::passing_score();
		$passed     = $score >= $threshold;
		$result_url = get_post_meta( $post_id, DDA_Incident_Report_User_State::META_RESULT_URL, true );
		if ( empty( $result_url ) ) {
			$result_url = home_url( '/' );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( $passed ) {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Congratulations — You Passed', 'dda-incident-report' ),
				$site_name
			);
		} else {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Your Incident Report Result', 'dda-incident-report' ),
				$site_name
			);
		}

		$message = $this->build_html( array(
			'user'       => $user,
			'score'      => $score,
			'threshold'  => $threshold,
			'passed'     => $passed,
			'result_url' => $result_url,
			'site_name'  => $site_name,
			'post'       => $post,
		) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filters the result email arguments before send.
		 *
		 * @param array $args     wp_mail args: to, subject, message, headers.
		 * @param int   $post_id  Report ID.
		 * @param float $score    The score.
		 * @param bool  $passed   Whether the submitter passed.
		 */
		$args = apply_filters(
			'dda_incident_report_result_email',
			array(
				'to'      => $user->user_email,
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			),
			$post_id,
			$score,
			$passed
		);

		return wp_mail( $args['to'], $args['subject'], $args['message'], $args['headers'] );
	}

	private function build_html( $args ) {
		$user       = $args['user'];
		$score      = $args['score'];
		$threshold  = $args['threshold'];
		$passed     = $args['passed'];
		$result_url = $args['result_url'];
		$site_name  = $args['site_name'];

		$accent      = $passed ? '#059669' : '#DC2626';
		$accent_soft = $passed ? '#D1FAE5' : '#FEE2E2';
		$headline    = $passed
			? __( 'Congratulations — You Passed!', 'dda-incident-report' )
			: __( 'Your Result Is Ready', 'dda-incident-report' );
		$lead        = $passed
			? __( 'Your incident report has been reviewed and you met the required standard. Great work.', 'dda-incident-report' )
			: __( 'Your incident report has been reviewed. Unfortunately, the score did not meet the passing threshold.', 'dda-incident-report' );

		$score_display     = number_format_i18n( $score, 1 );
		$threshold_display = number_format_i18n( $threshold );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $headline ); ?></title>
</head>
<body style="margin:0;padding:0;background:#F1F5F9;font-family:'Inter',Arial,sans-serif;color:#0F172A;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.06);">
					<tr>
						<td style="background:#0B5FA3;padding:28px 32px;color:#FFFFFF;">
							<div style="font-family:'Urbanist','Inter',Arial,sans-serif;font-size:20px;font-weight:700;letter-spacing:0.2px;">
								<?php echo esc_html( $site_name ); ?>
							</div>
							<div style="font-size:13px;opacity:0.85;margin-top:4px;">
								<?php esc_html_e( 'DDA Incident Report — Review Notification', 'dda-incident-report' ); ?>
							</div>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<div style="display:inline-block;background:<?php echo esc_attr( $accent_soft ); ?>;color:<?php echo esc_attr( $accent ); ?>;font-weight:600;font-size:12px;padding:6px 12px;border-radius:999px;letter-spacing:0.6px;text-transform:uppercase;">
								<?php echo esc_html( $passed ? __( 'Passed', 'dda-incident-report' ) : __( 'Not Passed', 'dda-incident-report' ) ); ?>
							</div>
							<h1 style="font-family:'Urbanist','Inter',Arial,sans-serif;font-size:26px;line-height:1.25;margin:18px 0 10px;color:#0F172A;">
								<?php echo esc_html( $headline ); ?>
							</h1>
							<p style="font-size:15px;line-height:1.6;color:#475569;margin:0 0 22px;">
								<?php echo esc_html( $lead ); ?>
							</p>

							<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:18px;margin-bottom:24px;">
								<tr>
									<td>
										<div style="font-size:12px;color:#64748B;text-transform:uppercase;letter-spacing:0.6px;">
											<?php esc_html_e( 'Your Score', 'dda-incident-report' ); ?>
										</div>
										<div style="font-family:'Urbanist','Inter',Arial,sans-serif;font-size:42px;font-weight:700;color:<?php echo esc_attr( $accent ); ?>;line-height:1;margin-top:6px;">
											<?php echo esc_html( $score_display ); ?><span style="font-size:18px;color:#94A3B8;font-weight:600;">/100</span>
										</div>
										<div style="font-size:12px;color:#64748B;margin-top:8px;">
											<?php
											/* translators: %s: passing score */
											printf( esc_html__( 'Passing threshold: %s%%', 'dda-incident-report' ), esc_html( $threshold_display ) );
											?>
										</div>
									</td>
								</tr>
							</table>

							<p style="font-size:14px;line-height:1.6;color:#475569;margin:0 0 24px;">
								<?php esc_html_e( 'You can view the full result, including any notes from the reviewer, on the course page below.', 'dda-incident-report' ); ?>
							</p>

							<p style="text-align:center;margin:0 0 8px;">
								<a href="<?php echo esc_url( $result_url ); ?>" style="display:inline-block;background:#0B5FA3;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:8px;">
									<?php esc_html_e( 'View Your Result', 'dda-incident-report' ); ?>
								</a>
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:20px 32px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;text-align:center;">
							<?php
							/* translators: 1: user display name, 2: site name */
							printf( esc_html__( 'Sent to %1$s from %2$s.', 'dda-incident-report' ), esc_html( $user->display_name ), esc_html( $site_name ) );
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
