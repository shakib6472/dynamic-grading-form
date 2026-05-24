<?php
/**
 * Renders a single incident report as a standalone, print-ready HTML page
 * that mirrors the official DDA Incident Report PDF, layout-for-layout.
 *
 * Output target: US Letter, two pages.
 *  Page 1 — Header band, Person info, Section 1 (4-column categorization grid)
 *  Page 2 — Section 2 (description, signature), Verbal Notifications, footer
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Print_View {

	/**
	 * Output a complete HTML document for printing a report.
	 *
	 * @param int $post_id Report ID.
	 */
	public static function render_document( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			status_header( 404 );
			echo '<p>Report not found.</p>';
			return;
		}

		$pid  = (int) $post->ID;
		$g    = function ( $key ) use ( $pid ) {
			$v = get_post_meta( $pid, DDA_Incident_Report_Plugin::META_PREFIX . $key, true );
			return is_string( $v ) ? $v : $v;
		};
		$bool = function ( $key, $expected ) use ( $g ) {
			return strtolower( (string) $g( $key ) ) === strtolower( (string) $expected );
		};
		$in   = function ( $key, $needle ) use ( $g ) {
			$arr = $g( $key );
			return is_array( $arr ) && in_array( $needle, $arr, true );
		};
		$eq   = function ( $key, $val ) use ( $g ) {
			return (string) $g( $key ) === (string) $val;
		};

		$mcis   = (string) $g( 'mcis_report_number' );
		$date   = (string) $g( 'date_of_incident' );
		$person = (string) $g( 'primary_person_name' );

		$notifications = $g( 'notifications' );
		$notifications = is_array( $notifications ) ? $notifications : array();

		// Submitter info, used to pre-fill the scenario test sheet.
		$author          = get_userdata( (int) $post->post_author );
		$submitter_name  = $author ? $author->display_name : '';
		$submitted_at    = (string) $g( 'submitted_at' );
		$submitter_date  = $submitted_at
			? mysql2date( get_option( 'date_format' ), $submitted_at )
			: $date;

		// Pull scenario from the shared source (also used by the
		// front-end shortcode). The `dda_incident_report_scenario`
		// filter lives there; this print-specific filter still works
		// as a layer to tweak the print copy without touching the form.
		$scenario = DDA_Incident_Report_Fields::scenario();

		/**
		 * Filter the scenario specifically for the print template.
		 *
		 * @param array $scenario
		 * @param int   $post_id
		 */
		$scenario = apply_filters( 'dda_incident_report_print_scenario', $scenario, $pid );

		$scen_narrative_paras = is_array( $scenario['narrative'] )
			? $scenario['narrative']
			: array( (string) $scenario['narrative'] );

		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<title>DDA Incident Report &mdash; <?php echo esc_html( $person ); ?></title>
<meta name="robots" content="noindex,nofollow">
<style>
	/* Use zero @page margins and put all padding on .sheet so the
	   margin shows up reliably regardless of browser print settings
	   ("Save as PDF" tends to strip @page margins). */
	/* @page margins are the ONLY CSS mechanism that gives per-printed-
	   page margins (including the top/bottom of every page where a
	   natural break happens). Body padding only applies to the start
	   of the document and the end — middle-page breaks have no margin.
	   For best results, the user's browser print dialog should keep
	   "Margins" on its Default setting (which honors this @page rule).
	   If they pick "None", margins go to zero — that's a browser-level
	   override CSS can't fight. */
	@page { size: Letter; margin: 0.5in 0.6in; }
	@media print {
		.no-print,
		.print-only-hide,
		.scen-pgnum,
		.page1-pgnum,
		.page2-pgnum { display: none !important; }

		html, body { background: #fff !important; }
		body {
			margin: 0 !important;
			padding: 0 !important;
		}

		/* Every container in the document must allow itself to break across
		   pages — otherwise the browser dumps short sections onto fresh
		   pages and creates the huge gaps we're trying to remove. */
		.sheet,
		.box,
		.scen,
		.cat-grid,
		.cat-grid > tr > td,
		table,
		div { break-inside: auto !important; page-break-inside: auto !important; }

		.sheet {
			width: 100% !important;
			max-width: 100% !important;
			min-height: 0 !important;
			height: auto !important;
			margin: 0 !important;
			padding: 0 !important;
			box-shadow: none !important;
			background: #fff !important;
			page-break-before: auto !important;
			page-break-after: auto !important;
			break-before: auto !important;
			break-after: auto !important;
		}

		/* The form's outer black box is the main reason content gets
		   pushed onto a new page (browsers try to keep bordered boxes
		   on one page). Drop the border and padding on print so
		   content can flow across pages naturally. */
		.box {
			border: 0 !important;
			padding: 0 !important;
		}

		/* The runner line at the top of the second sheet repeats data
		   that's already on the first page. In a continuous flow it's
		   redundant — hide it on print. */
		.runner { display: none !important; }

		/* Reset top margins on the first child of every sheet so we
		   don't accumulate space when sheets stack. */
		.sheet > *:first-child { margin-top: 0 !important; }

		/* Tighten the DC header (sits at the top of the form section). */
		.hdr h1, .hdr h2, .hdr h3 { margin: 0 !important; line-height: 1.1 !important; }
		.hdr { padding-bottom: 3pt !important; }
		.hdr .title { margin-top: 2pt !important; }

		/* Tighten form sections. */
		.note { padding-bottom: 2pt !important; margin-bottom: 4pt !important; }
		.row  { margin: 1.5pt 0 !important; }
		.blk-h { margin: 3pt 0 1pt !important; }
		.sec-bar { margin: 4pt -8pt !important; padding: 2pt 8pt !important; }
		.cat-grid > tr > td { padding: 3pt 5pt !important; }
		.cat-grid ol li, .cat-grid .it { margin-bottom: 0.5pt !important; }
		.desc-box { min-height: 0.9in !important; }
		.desc-box.short { min-height: 0.55in !important; }
		.notif-row { padding: 1pt 0 !important; }
		.footer { margin-top: 6pt !important; }
		.footer .dc-star { width: 20pt !important; height: 18pt !important; }
		.footer .addr { font-size: 8pt !important; }

		/* --- Group cohesion -----------------------------------------
		   The earlier "auto" inside-break setting is the right default
		   (it stops the browser from dumping whole sections onto fresh
		   pages and creating gaps). But individual logical groups still
		   shouldn't be split mid-row, mid-block, or mid-checkbox. The
		   selectors below override `auto` JUST for those small groups,
		   so a row stays whole, a header stays attached to its first
		   row, etc. */
		.row,
		.notif-row,
		.cat-grid ol li,
		.cat-grid .it,
		.scen-additional,
		.scen-meta,
		.footer { break-inside: avoid !important; page-break-inside: avoid !important; }

		/* Keep a section bar / block-heading attached to whatever
		   immediately follows it — no orphan headers at the page
		   bottom. */
		.sec-bar,
		.blk-h,
		.desc-h,
		.notif-h,
		.scen-banner,
		.scen-title,
		.scen-section .scen-lead { break-after: avoid !important; page-break-after: avoid !important; }

		/* Keep grouped rows together (Other Persons, Staff, header
		   identification block, etc.). The .keep-together class is
		   applied to <div> wrappers in the HTML. */
		.keep-together { break-inside: avoid !important; page-break-inside: avoid !important; }
	}
	* { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; }
	body {
		background: #ECECEC;
		font-family: 'Times New Roman', Times, serif;
		font-size: 9.5pt;
		color: #000;
		line-height: 1.25;
	}
	.toolbar {
		position: sticky;
		top: 0;
		z-index: 10;
		background: #0B5FA3;
		color: #fff;
		padding: 10px 16px;
		display: flex;
		gap: 10px;
		align-items: center;
		justify-content: space-between;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	}
	.toolbar .t-meta { font-size: 13px; opacity: .9; }
	.toolbar button {
		background: #fff;
		color: #0B5FA3;
		border: 0;
		padding: 8px 18px;
		font-weight: 600;
		font-size: 13px;
		border-radius: 6px;
		cursor: pointer;
	}
	.toolbar button.secondary {
		background: transparent;
		color: #fff;
		border: 1px solid rgba(255,255,255,.5);
	}
	/* On-screen preview matches the printed output: one continuous
	   document with no forced page breaks and tight spacing. */
	.sheet {
		width: 8.5in;
		margin: 0 auto;
		background: #fff;
		padding: 0 0.5in;
	}
	.sheet:first-of-type {
		margin-top: 16px;
		padding-top: 0.4in;
		box-shadow: 0 4px 16px rgba(0,0,0,.08);
		border-top-left-radius: 6px;
		border-top-right-radius: 6px;
	}
	.sheet:last-of-type {
		padding-bottom: 0.4in;
		margin-bottom: 24px;
		box-shadow: 0 4px 16px rgba(0,0,0,.08);
		border-bottom-left-radius: 6px;
		border-bottom-right-radius: 6px;
	}
	.sheet:only-of-type {
		padding: 0.4in 0.5in;
	}

	/* ----- Header band ----- */
	.hdr {
		text-align: center;
		padding-bottom: 6pt;
	}
	.hdr .flag {
		display: inline-block;
		line-height: 0;
	}
	.hdr-stars, .hdr-bars {
		display: block;
		margin: 0 auto;
	}
	.hdr-stars { width: 60pt; height: 12pt; }
	.hdr-bars  { width: 60pt; height: 8pt; margin-top: 2pt; }
	.hdr h1, .hdr h2, .hdr h3 {
		font-family: Arial, Helvetica, sans-serif;
		color: #1F4E79;
		margin: 0;
		font-weight: bold;
	}
	.hdr h1 { font-size: 12pt; margin-top: 4pt; }
	.hdr h2 { font-size: 13pt; }
	.hdr h3 {
		font-size: 11pt;
		font-weight: normal;
		font-style: normal;
		color: #2E75B6;
	}
	.hdr .title {
		font-family: Arial, Helvetica, sans-serif;
		color: #1F4E79;
		font-size: 12pt;
		font-weight: bold;
		margin-top: 3pt;
	}

	/* ----- Black-bordered content box ----- */
	.box {
		border: 1.25pt solid #000;
		padding: 6pt 8pt;
	}

	/* Top note line */
	.note {
		font-weight: bold;
		font-size: 9.5pt;
		padding-bottom: 4pt;
		border-bottom: 1pt solid #000;
		margin-bottom: 6pt;
	}

	/* Page-2 runner */
	.runner {
		font-size: 9.5pt;
		font-weight: bold;
		padding-bottom: 4pt;
		border-bottom: 1pt solid #000;
		margin-bottom: 0;
	}
	.runner .fill { font-weight: normal; }

	/* Field rows */
	.row { margin: 3pt 0; line-height: 1.35; }
	.lbl { font-weight: normal; }
	.lbl-italic { font-style: italic; }
	.red { color: #C00000; font-weight: bold; text-transform: uppercase; }
	.red-bold { color: #C00000; font-weight: bold; }
	.fill {
		display: inline-block;
		border-bottom: 1pt solid #000;
		min-width: 1.2in;
		padding: 0 3pt;
		font-weight: normal;
	}
	.fill.long  { min-width: 2.6in; }
	.fill.xlong { min-width: 4.4in; }
	.fill.full  { min-width: 5.6in; }

	/* Bold black section heading like LOCATION OF INCIDENT, OTHER PERSONS, STAFF */
	.blk-h {
		font-weight: bold;
		margin: 4pt 0 2pt;
	}

	/* Inline checkbox glyph */
	.cb {
		display: inline-block;
		width: 9pt;
		height: 9pt;
		border: 0.75pt solid #000;
		vertical-align: middle;
		margin: 0 1pt 2pt 2pt;
		text-align: center;
		line-height: 7pt;
		font-size: 9pt;
		font-weight: bold;
		color: #000;
	}
	.cb.checked::before { content: "✕"; font-family: Arial, sans-serif; }

	/* Section bar (gray) */
	.sec-bar {
		background: #D9D9D9;
		border-top: 1pt solid #000;
		border-bottom: 1pt solid #000;
		margin: 6pt -8pt;
		padding: 3pt 8pt;
		font-size: 10pt;
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: 12pt;
	}
	.sec-bar .sec-num { font-weight: bold; font-family: Arial, sans-serif; }
	.sec-bar .sec-name { font-weight: bold; color: #000; flex: 1; text-align: center; }
	.sec-bar .sec-note { color: #C00000; font-style: normal; font-size: 9pt; }

	/* 4-column categorization grid */
	.cat-grid {
		width: 100%;
		border-collapse: collapse;
		margin-top: 4pt;
	}
	.cat-grid > tr > td {
		vertical-align: top;
		padding: 4pt 6pt;
		border-right: 1pt solid #000;
		font-size: 9pt;
	}
	.cat-grid > tr > td:last-child { border-right: 0; }
	.cat-h {
		color: #C00000;
		font-weight: bold;
		text-transform: uppercase;
		font-size: 9.5pt;
	}
	.cat-h-it {
		font-style: italic;
		font-weight: normal;
		color: #000;
		font-size: 9.5pt;
	}
	.cat-sub {
		font-size: 8.5pt;
		margin-bottom: 3pt;
	}
	.cat-grid ol {
		margin: 4pt 0 0;
		padding-left: 16pt;
	}
	.cat-grid ol li, .cat-grid .it {
		margin-bottom: 1.5pt;
		font-size: 9pt;
	}
	.cat-grid .it { list-style: none; }
	.cat-grid .li-checked { font-weight: bold; }
	.cat-warn {
		font-weight: bold;
		font-size: 9pt;
		margin-top: 6pt;
	}
	.cat-warn u { text-decoration: underline; }
	.cat-sig {
		margin-top: 4pt;
		font-size: 9pt;
	}
	.cat-sig .fill { min-width: 1.4in; }

	/* Page-2 description area */
	.desc-h {
		font-weight: bold;
		margin-top: 6pt;
		font-size: 10pt;
	}
	.desc-h .muted {
		font-weight: normal;
		font-size: 9pt;
	}
	.desc-box {
		border-top: 1pt solid #000;
		min-height: 1.4in;
		padding: 4pt 2pt;
		background:
			repeating-linear-gradient(
				to bottom,
				transparent 0,
				transparent 13pt,
				#000 13pt,
				#000 13.7pt
			);
		font-size: 10pt;
		line-height: 14pt;
		white-space: pre-wrap;
	}
	.desc-box.short { min-height: 0.85in; }

	/* Notifications table */
	.notif-h {
		display: flex;
		justify-content: space-between;
		margin-top: 6pt;
		border-top: 1pt solid #000;
		padding-top: 4pt;
		font-weight: bold;
		color: #C00000;
		text-transform: uppercase;
		font-size: 9.5pt;
	}
	.notif-h .col-recipient { flex: 2.4; }
	.notif-h .col-person    { flex: 1.6; color: #C00000; }
	.notif-h .col-date      { flex: 0.9; color: #C00000; }
	.notif-h .col-time      { flex: 0.9; color: #C00000; }
	.notif-h .lc { text-transform: none; font-style: italic; font-size: 8.5pt; color: #C00000; font-weight: normal; }

	.notif-row {
		display: flex;
		align-items: baseline;
		padding: 1.8pt 0;
		font-size: 9pt;
	}
	.notif-row .col-recipient { flex: 2.4; }
	.notif-row .col-person { flex: 1.6; }
	.notif-row .col-date   { flex: 0.9; }
	.notif-row .col-time   { flex: 0.9; }
	.notif-row .fill { min-width: 1.1in; }
	.notif-row .fill.person { min-width: 1.7in; }

	/* Footer */
	.footer {
		text-align: center;
		margin-top: 12pt;
	}
	.footer .dc-star {
		width: 32pt;
		height: 30pt;
		margin: 0 auto;
		display: block;
	}
	.footer .addr {
		color: #1F4E79;
		font-family: Arial, Helvetica, sans-serif;
		font-size: 9pt;
		line-height: 1.35;
		margin-top: 2pt;
	}
	.footer .page-num {
		margin-top: 6pt;
		font-size: 9pt;
	}

	/* Page-number labels are kept in the DOM but hidden in both screen
	   and print views — the user requested no per-page footers and no
	   forced page breaks. */
	.page1-pgnum, .page2-pgnum, .scen-pgnum {
		display: none;
	}

	/* ----- Scenario block (flows directly into the form, no page break) ----- */
	.scen {
		font-family: 'Times New Roman', Times, serif;
		color: #000;
		font-size: 9.5pt;
		line-height: 1.28;
	}
	.scen-banner {
		background: linear-gradient(180deg, #1F4E79 0%, #2E75B6 100%);
		color: #fff;
		padding: 4pt 10pt;
		text-align: center;
		font-family: Arial, Helvetica, sans-serif;
		margin-bottom: 5pt;
	}
	.scen-banner .b1 {
		font-size: 7.5pt;
		font-weight: 600;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		opacity: 0.85;
	}
	.scen-banner .b2 {
		font-size: 11pt;
		font-weight: 700;
		margin-top: 0;
	}
	.scen-title {
		font-family: Arial, Helvetica, sans-serif;
		color: #1F4E79;
		font-weight: bold;
		font-size: 11pt;
		margin: 0 0 3pt;
	}
	.scen-meta {
		display: flex;
		gap: 22pt;
		margin: 2pt 0 4pt;
		font-size: 9pt;
	}
	.scen-meta .lbl { font-weight: bold; }
	.scen-meta .fill {
		border-bottom: 1pt solid #000;
		display: inline-block;
		min-width: 2.3in;
		padding: 0 4pt;
	}
	.scen-meta .fill.short { min-width: 1.3in; }
	.scen-section {
		margin: 3pt 0;
	}
	.scen-section .scen-lead {
		font-weight: bold;
		color: #1F4E79;
		font-family: Arial, Helvetica, sans-serif;
		font-size: 9.5pt;
		margin-bottom: 1pt;
	}
	.scen-narrative p {
		margin: 0 0 2pt;
		text-align: justify;
		font-size: 9pt;
		line-height: 1.28;
	}
	.scen-additional {
		border-left: 3pt solid #1F4E79;
		background: #F3F8FC;
		padding: 3pt 8pt;
		margin: 4pt 0;
	}
	.scen-additional .scen-lead { margin-bottom: 0; }
	.scen-additional p {
		margin: 0;
		font-size: 9pt;
	}
	.scen-circle {
		margin-top: 3pt;
		margin-bottom: 6pt;
	}
	.scen-circle ul {
		margin: 2pt 0 0 0;
		padding: 0;
		columns: 2;
		column-gap: 16pt;
		list-style: none;
	}
	.scen-circle li {
		margin: 0;
		font-size: 9pt;
		line-height: 1.25;
		break-inside: avoid;
		-webkit-column-break-inside: avoid;
		page-break-inside: avoid;
		padding-left: 7pt;
		text-indent: -7pt;
	}
	.scen-circle li::before {
		content: "• ";
		color: #1F4E79;
		font-weight: bold;
	}
</style>
</head>
<body>
<div class="toolbar no-print">
	<div>
		<button onclick="window.print()">&#128424; Print</button>
		<button class="secondary" onclick="window.close()">Close</button>
	</div>
	<div class="t-meta">
		DDA Incident Report &middot; <?php echo esc_html( $person ); ?>
		<?php if ( $mcis ) : ?> &middot; MCIS <?php echo esc_html( $mcis ); ?><?php endif; ?>
		<?php if ( $date ) : ?> &middot; <?php echo esc_html( $date ); ?><?php endif; ?>
	</div>
</div>

<!-- =================== SCENARIO PAGE (single page) =================== -->
<div class="sheet">
	<div class="scen">
		<div class="scen-banner">
			<div class="b1">DDA Phase I Training Test</div>
			<div class="b2">Incident Management — Scenario</div>
		</div>

		<h1 class="scen-title"><?php echo esc_html( (string) $scenario['title'] ); ?></h1>

		<div class="scen-meta">
			<div><span class="lbl">Name:</span> <span class="fill"><?php echo esc_html( $submitter_name ); ?></span></div>
			<div><span class="lbl">Date:</span> <span class="fill short"><?php echo esc_html( $submitter_date ); ?></span></div>
		</div>

		<div class="scen-section">
			<div class="scen-lead">Instructions</div>
			<p><?php echo esc_html( (string) $scenario['instructions'] ); ?></p>
		</div>

		<div class="scen-section scen-narrative">
			<div class="scen-lead">Scenario</div>
			<?php foreach ( $scen_narrative_paras as $para ) : ?>
				<p><?php echo esc_html( (string) $para ); ?></p>
			<?php endforeach; ?>
		</div>

		<div class="scen-additional">
			<div class="scen-lead">Additional Information</div>
			<p><?php echo esc_html( (string) $scenario['additional'] ); ?></p>
		</div>

		<div class="scen-section scen-circle">
			<div class="scen-lead">Ms. Brown&rsquo;s Circle of Support Includes</div>
			<ul>
				<?php foreach ( (array) $scenario['circle'] as $line ) : ?>
					<li><?php echo esc_html( (string) $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="scen-pgnum">Scenario</div>
</div>

<!-- =================== FORM PAGE 1 =================== -->
<div class="sheet">
	<div class="hdr">
		<span class="flag">
			<!-- DC flag: 3 red stars over 2 red bars -->
			<svg class="hdr-stars" viewBox="0 0 60 12" xmlns="http://www.w3.org/2000/svg">
				<g fill="#C00000">
					<polygon points="14,1 15.2,4.6 19,4.6 15.9,6.8 17,10.4 14,8.2 11,10.4 12.1,6.8 9,4.6 12.8,4.6"/>
					<polygon points="30,1 31.2,4.6 35,4.6 31.9,6.8 33,10.4 30,8.2 27,10.4 28.1,6.8 25,4.6 28.8,4.6"/>
					<polygon points="46,1 47.2,4.6 51,4.6 47.9,6.8 49,10.4 46,8.2 43,10.4 44.1,6.8 41,4.6 44.8,4.6"/>
				</g>
			</svg>
			<svg class="hdr-bars" viewBox="0 0 60 8" xmlns="http://www.w3.org/2000/svg">
				<rect x="0" y="0" width="60" height="2.5" fill="#C00000"/>
				<rect x="0" y="4.5" width="60" height="2.5" fill="#C00000"/>
			</svg>
		</span>
		<h1>GOVERNMENT OF THE DISTRICT OF COLUMBIA</h1>
		<h2>Department of Disability Services</h2>
		<h2>Quality Management Division</h2>
		<h3>Incident Management and Enforcement Unit</h3>
		<div class="title">Incident Report</div>
	</div>

	<div class="box">
		<div class="note">To be completed by Person served by DEVELOPMENTAL DISABILITIES ADMINISTRATION (DDA) (REV 12/13)</div>

		<div class="row">
			<span class="red">MCIS REPORT NUMBER</span>
			<span class="fill"><?php echo esc_html( $mcis ); ?></span>
			&nbsp;&nbsp;
			<span class="red">DATE OF INCIDENT</span>
			<span class="fill"><?php echo esc_html( $date ); ?></span>
		</div>

		<div class="row">
			<strong>Name of Primary Person involved in this Incident:</strong>
			<span class="fill xlong"><?php echo esc_html( $person ); ?></span>
		</div>

		<div class="row">
			Date of Birth:
			<span class="fill"><?php echo esc_html( (string) $g( 'primary_person_dob' ) ); ?></span>
		</div>

		<div class="row">
			<span class="lbl-italic">Evans Class Member:</span>
			Yes <span class="cb<?php echo $bool( 'evans_class_member', 'yes' ) ? ' checked' : ''; ?>"></span>,
			No <span class="cb<?php echo $bool( 'evans_class_member', 'no' ) ? ' checked' : ''; ?>"></span>
			&nbsp;&nbsp;&nbsp;
			<span class="lbl-italic">Waiver:</span>
			Yes <span class="cb<?php echo $bool( 'waiver', 'yes' ) ? ' checked' : ''; ?>"></span>,
			No <span class="cb<?php echo $bool( 'waiver', 'no' ) ? ' checked' : ''; ?>"></span>
		</div>

		<div class="row">
			Person served by DDA&rsquo;s Address:
			<span class="fill full"><?php echo esc_html( (string) $g( 'person_address' ) ); ?></span>
		</div>

		<div class="row">
			Provider&rsquo;s Name (Residential):
			<span class="fill long"><?php echo esc_html( (string) $g( 'residential_provider_name' ) ); ?></span>
			&nbsp;Phone:
			<span class="fill"><?php echo esc_html( (string) $g( 'residential_provider_phone' ) ); ?></span>
		</div>

		<div class="row">
			<span class="lbl-italic">Residential Provider Type:</span>
			Residential Habilitation <span class="cb<?php echo $eq( 'residential_provider_type', 'residential_habilitation' ) ? ' checked' : ''; ?>"></span>,
			Natural Home <span class="cb<?php echo $eq( 'residential_provider_type', 'natural_home' ) ? ' checked' : ''; ?>"></span>,
			Supported Living <span class="cb<?php echo $eq( 'residential_provider_type', 'supported_living' ) ? ' checked' : ''; ?>"></span>,
			ICF <span class="cb<?php echo $eq( 'residential_provider_type', 'icf' ) ? ' checked' : ''; ?>"></span>,
			Respite <span class="cb<?php echo $eq( 'residential_provider_type', 'respite' ) ? ' checked' : ''; ?>"></span>,
			Host Home <span class="cb<?php echo $eq( 'residential_provider_type', 'host_home' ) ? ' checked' : ''; ?>"></span>,<br>
			Other <span class="cb<?php echo $eq( 'residential_provider_type', 'other' ) ? ' checked' : ''; ?>"></span>
			<span class="fill long"><?php echo esc_html( (string) $g( 'residential_provider_type_other' ) ); ?></span>
		</div>

		<div class="blk-h">LOCATION OF INCIDENT</div>
		<div class="row">
			Address of Incident <span class="red-bold">(If different from above)</span>:
			<span class="fill full"><?php echo esc_html( (string) $g( 'incident_address' ) ); ?></span>
		</div>
		<div class="row">
			Provider Name where Incident Occurred <span class="red-bold">(If different from above)</span>:
			<span class="fill xlong"><?php echo esc_html( (string) $g( 'incident_provider_name' ) ); ?></span>
		</div>

		<div class="keep-together">
			<div class="blk-h">OTHER PERSON&rsquo;S Supported by DDA Involved:</div>
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
			<div class="row" style="padding-left: 18pt;">
				Name: <span class="fill long"><?php echo esc_html( (string) $g( "other_person_{$i}_name" ) ); ?></span>
				&nbsp;Date of Birth: <span class="fill"><?php echo esc_html( (string) $g( "other_person_{$i}_dob" ) ); ?></span>
			</div>
			<?php endfor; ?>
		</div>

		<div class="keep-together">
			<div class="blk-h" style="display: inline;">STAFF INVOLVED:</div>
			<?php for ( $i = 1; $i <= 2; $i++ ) : ?>
			<div class="row" style="padding-left: <?php echo 1 === $i ? '0' : '18pt'; ?>;">
				<?php if ( 1 === $i ) : ?>&nbsp;<?php endif; ?>
				Name: <span class="fill long"><?php echo esc_html( (string) $g( "staff_{$i}_name" ) ); ?></span>
				&nbsp;Phone: <span class="fill"><?php echo esc_html( (string) $g( "staff_{$i}_phone" ) ); ?></span>
			</div>
			<?php endfor; ?>
		</div>

		<div class="row">
			Name of Person Reporting this Incident:
			<span class="fill long"><?php echo esc_html( (string) $g( 'reporter_name' ) ); ?></span>
			&nbsp;Title: <span class="fill"><?php echo esc_html( (string) $g( 'reporter_title' ) ); ?></span>
			&nbsp;Phone: <span class="fill"><?php echo esc_html( (string) $g( 'reporter_phone' ) ); ?></span>
		</div>

		<!-- Section 1 bar -->
		<div class="sec-bar">
			<span class="sec-num">Section 1</span>
			<span class="sec-name">INCIDENT CATEGORIZATION</span>
			<span class="sec-note">(Circle Appropriate Category and Location)</span>
		</div>

		<table class="cat-grid">
			<tr>
				<!-- Col 1: Serious Reportable -->
				<td style="width: 26%;">
					<div class="cat-h">SERIOUS REPORTABLE</div>
					<div class="cat-sub">(Report to be submitted via MCIS the next business day by 5:00p.m.)</div>
					<?php
					$sr_options = array(
						'abuse'                           => 'Abuse',
						'death'                           => 'Death',
						'exploitation'                    => 'Exploitation',
						'inappropriate_restraints_injury' => 'Inappropriate use of approved restraints which results in serious injury.',
						'missing_person'                  => 'Missing person',
						'neglect'                         => 'Neglect',
						'repeated_restrictive_controls'   => 'Repeated emergency use of restrictive controls',
						'serious_medication_error'        => 'Serious medication error',
						'serious_physical_injury'         => 'Serious physical injury',
						'suicide_attempt'                 => 'Suicide attempt',
						'unapproved_restraints'           => 'Use of unapproved restraints',
						'emergency_hospitalization'       => 'Unplanned or emergency inpatient hospitalization',
					);
					?>
					<ol>
						<?php foreach ( $sr_options as $key => $label ) : $checked = $in( 'serious_reportable', $key ); ?>
							<li class="<?php echo $checked ? 'li-checked' : ''; ?>"><?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
						<li class="<?php echo $in( 'serious_reportable', 'other' ) ? 'li-checked' : ''; ?>">
							Other <span class="fill"><?php echo esc_html( (string) $g( 'serious_reportable_other' ) ); ?></span>
						</li>
					</ol>
				</td>

				<!-- Col 2: Abuse and Neglect Categories + supervisor cert -->
				<td style="width: 26%;">
					<div class="cat-h-it">Abuse and Neglect Categories</div>
					<?php
					$an_options = array(
						'physical'      => 'Physical',
						'verbal'        => 'Verbal',
						'sexual'        => 'Sexual',
						'psychological' => 'Psychological',
						'mistreatment'  => 'Mistreatment',
					);
					?>
					<ol style="list-style-type: lower-alpha;">
						<?php foreach ( $an_options as $key => $label ) : $checked = $in( 'abuse_neglect_categories', $key ); ?>
							<li class="<?php echo $checked ? 'li-checked' : ''; ?>"><?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
					</ol>

					<div class="cat-warn">
						For abuse, neglect, and exploitation allegations, staff must be removed from <u>all</u> customer contact immediately. Please indicate below that this action has been taken.
					</div>
					<div class="cat-sig">
						Name of supervisor certifying that action has been taken. (Print)<br>
						Name: <span class="fill"><?php echo esc_html( (string) $g( 'supervisor_cert_name' ) ); ?></span><br>
						Title: <span class="fill"><?php echo esc_html( (string) $g( 'supervisor_cert_title' ) ); ?></span><br>
						Signature: <span class="fill"><?php echo esc_html( (string) $g( 'supervisor_cert_signature' ) ); ?></span>
					</div>
				</td>

				<!-- Col 3: Reportable -->
				<td style="width: 26%;">
					<div class="cat-h">REPORTABLE</div>
					<div class="cat-sub">(Report written and maintained in-house for internal investigation and trending/tracking report)</div>
					<?php
					$rp_options = array(
						'emergency_relocation'      => 'Emergency relocation',
						'er_urgent_care'            => 'Emergency room or urgent care visit',
						'emergency_unauth_controls' => 'Emergency unauthorized use of restrictive controls (that are in a category typically approved by DDS, but that have not been approved with use with this person).',
						'fire'                      => 'Fire',
						'inappropriate_restraints'  => 'Inappropriate use of approved restraints (no injury)',
						'police_involvement'        => 'Incidents involving the police',
						'medication_error'          => 'Medication error',
						'physical_injury'           => 'Physical injury',
						'property_destruction'      => 'Property destruction',
						'suicide_threat'            => 'Suicide threat',
						'vehicle_accident'          => 'Vehicle accident',
					);
					?>
					<ol>
						<?php foreach ( $rp_options as $key => $label ) : $checked = $in( 'reportable', $key ); ?>
							<li class="<?php echo $checked ? 'li-checked' : ''; ?>"><?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
						<li class="<?php echo $in( 'reportable', 'other' ) ? 'li-checked' : ''; ?>">
							Other
							<?php if ( $in( 'reportable', 'other' ) && $g( 'reportable_other' ) ) : ?>
								<span class="fill"><?php echo esc_html( (string) $g( 'reportable_other' ) ); ?></span>
							<?php endif; ?>
						</li>
					</ol>
				</td>

				<!-- Col 4: Incident Location Type -->
				<td style="width: 22%;">
					<div class="cat-h">INCIDENT LOCATION TYPE:</div>
					<?php
					$lt_options = array(
						'facility_home'           => 'Facility/Home',
						'apartment_home'          => 'Apartment Home',
						'natural_home'            => 'Natural Home',
						'supported_employment'    => 'Supported Employment',
						'day_program'             => 'Day Program',
						'vacation'                => 'Vacation',
						'provider_transportation' => 'Provider&rsquo;s transportation',
						'mtm_transportation'      => 'MTM transportation vehicle.',
						'public_transportation'   => 'Public transportation',
						'hospital'                => 'Hospital',
						'nursing_home'            => 'Nursing Home',
					);
					$lt_key = (string) $g( 'location_type' );
					?>
					<ol>
						<?php foreach ( $lt_options as $key => $label ) : $checked = ( $lt_key === $key ); ?>
							<li class="<?php echo $checked ? 'li-checked' : ''; ?>"><?php echo $label; ?></li>
						<?php endforeach; ?>
						<li class="<?php echo 'other' === $lt_key ? 'li-checked' : ''; ?>">
							Other:
							<?php if ( 'other' === $lt_key ) : ?>
								<span class="fill"><?php echo esc_html( (string) $g( 'location_type_other' ) ); ?></span>
							<?php else : ?>
								<span class="fill">&nbsp;</span>
							<?php endif; ?>
						</li>
					</ol>
				</td>
			</tr>
		</table>
	</div>

	<div class="page1-pgnum">Page 1 of 2</div>
</div>

<!-- =================== FORM PAGE 2 =================== -->
<div class="sheet">
	<div class="box">
		<div class="runner">
			<span class="red-bold">MCIS REPORT #:</span><span class="fill"><?php echo esc_html( $mcis ); ?></span>,
			<span class="red-bold">DATE OF INCIDENT:</span> <span class="fill"><?php echo esc_html( $date ); ?></span>
			<span class="red-bold">PERSON:</span> <span class="fill xlong"><?php echo esc_html( $person ); ?></span>
		</div>

		<div class="sec-bar">
			<span class="sec-num">Section 2</span>
			<span class="sec-name">DESCRIPTION OF INCIDENT</span>
			<span class="sec-note">(Check or complete as appropriate)</span>
		</div>

		<div class="row" style="margin-top: 6pt;">
			Date of the Incident:
			<span class="fill"><?php echo esc_html( (string) $g( 'section2_date' ) ); ?></span>,
			&nbsp;Time:
			<span class="fill"><?php echo esc_html( (string) $g( 'section2_time' ) ); ?></span>
			A.M. <span class="cb<?php echo $bool( 'section2_ampm', 'am' ) ? ' checked' : ''; ?>"></span>,
			P.M. <span class="cb<?php echo $bool( 'section2_ampm', 'pm' ) ? ' checked' : ''; ?>"></span>,
			Informed <span class="cb<?php echo $bool( 'incident_source', 'informed' ) ? ' checked' : ''; ?>"></span>,
			Witnessed <span class="cb<?php echo $bool( 'incident_source', 'witnessed' ) ? ' checked' : ''; ?>"></span>,
			Discovered <span class="cb<?php echo $bool( 'incident_source', 'discovered' ) ? ' checked' : ''; ?>"></span>
		</div>

		<div class="row">
			Reporter Type:
			<span class="cb<?php echo $bool( 'reporter_type', 'person_supported' ) ? ' checked' : ''; ?>"></span> Person Supported by DDA,
			<span class="cb<?php echo $bool( 'reporter_type', 'employee' ) ? ' checked' : ''; ?>"></span> Employee,
			<span class="cb<?php echo $bool( 'reporter_type', 'family_member' ) ? ' checked' : ''; ?>"></span> Family Member,
			<span class="cb<?php echo $bool( 'reporter_type', 'visitor' ) ? ' checked' : ''; ?>"></span> Visitor,
			<span class="cb<?php echo $bool( 'reporter_type', 'other' ) ? ' checked' : ''; ?>"></span> Other:
			<span class="fill long"><?php echo esc_html( (string) $g( 'reporter_type_other' ) ); ?></span>
		</div>

		<div class="keep-together">
			<?php for ( $i = 1; $i <= 2; $i++ ) : ?>
			<div class="row">
				Witness Name: <span class="fill long"><?php echo esc_html( (string) $g( "witness_{$i}_name" ) ); ?></span>
				&nbsp;Witness Telephone Number: <span class="fill long"><?php echo esc_html( (string) $g( "witness_{$i}_phone" ) ); ?></span>
			</div>
			<?php endfor; ?>
		</div>

		<div class="keep-together">
			<div class="desc-h">
				Description of the Incident: Who? What? When? Where? How?
				<span class="muted">(Please provide all information in a clear and concise manner)</span>
			</div>
			<div class="desc-box"><?php echo nl2br( esc_html( (string) $g( 'incident_description' ) ) ); ?></div>
		</div>

		<div class="keep-together">
			<div class="desc-h">
				Immediate Actions Taken
				<span class="muted">(Please provide information on all action taken)</span>
			</div>
			<div class="desc-box short"><?php echo nl2br( esc_html( (string) $g( 'immediate_actions' ) ) ); ?></div>
		</div>

		<div class="row" style="border-top: 1pt solid #000; padding-top: 4pt;">
			Signature of Reporter
			<span class="fill long"><?php echo esc_html( (string) $g( 'reporter_signature' ) ); ?></span>
			Date: <span class="fill"><?php echo esc_html( (string) $g( 'reporter_signature_date' ) ); ?></span>
			Time: <span class="fill"><?php echo esc_html( (string) $g( 'reporter_signature_time' ) ); ?></span>
			A.M. <span class="cb<?php echo $bool( 'reporter_signature_ampm', 'am' ) ? ' checked' : ''; ?>"></span>,
			P.M. <span class="cb<?php echo $bool( 'reporter_signature_ampm', 'pm' ) ? ' checked' : ''; ?>"></span>
		</div>

		<!-- Verbal Notifications -->
		<div class="notif-h">
			<span class="col-recipient">VERBAL NOTIFICATIONS: <span class="lc">(Check All That Apply)</span></span>
			<span class="col-person">PERSON NOTIFIED</span>
			<span class="col-date">DATE</span>
			<span class="col-time">TIME</span>
		</div>

		<?php
		$notif_rows = array(
			'dds_service_coordinator' => 'DDS Service Coordinator',
			'dds_duty_officer'        => 'DDS Duty Officer',
			'police_911'              => '911 &ndash; Police Department',
			'ems_911'                 => '911 &ndash; Emergency Medical Services (EMS)',
			'medical_examiner'        => 'Chief Medical Examiner (ALL DEATHS) at (202) 698-9000',
			'pcp'                     => 'Person&rsquo;s Primary Care Physician (PCP)',
			'family_guardian'         => 'Person&rsquo;s Family/Guardian',
			'legal_rep'               => 'Person&rsquo;s Legal Representative/Attorney',
			'aps'                     => 'Adult Protective Services (APS)',
			'doh_hrla'                => 'DOH/Health Regulations and Licensing Administration',
			'don_rn_lpn'              => 'Provider&rsquo;s Director of Nursing/Registered Nurse/LPN',
			'supervisor_qiddp'        => 'Provider&rsquo;s Supervisor/Manager/QIDDP for Incident Location',
			'provider_ceo'            => 'Provider&rsquo;s CEO/Administration/Program Manager',
			'imc'                     => 'Provider&rsquo;s Incident Management Coordinator (IMC)',
		);
		?>
		<?php foreach ( $notif_rows as $key => $label ) :
			$row     = isset( $notifications[ $key ] ) ? $notifications[ $key ] : array();
			$checked = ! empty( $row['checked'] );
			?>
			<div class="notif-row">
				<span class="col-recipient">
					<span class="cb<?php echo $checked ? ' checked' : ''; ?>"></span> <?php echo $label; ?>
				</span>
				<span class="col-person"><span class="fill person"><?php echo esc_html( isset( $row['person'] ) ? $row['person'] : '' ); ?></span></span>
				<span class="col-date"><span class="fill"><?php echo esc_html( isset( $row['date'] ) ? $row['date'] : '' ); ?></span></span>
				<span class="col-time"><span class="fill"><?php echo esc_html( isset( $row['time'] ) ? $row['time'] : '' ); ?></span></span>
			</div>
		<?php endforeach; ?>

		<?php
		$other_row     = isset( $notifications['other'] ) ? $notifications['other'] : array();
		$other_checked = ! empty( $other_row['checked'] );
		?>
		<div class="notif-row">
			<span class="col-recipient">
				<span class="cb<?php echo $other_checked ? ' checked' : ''; ?>"></span>
				Other:
				<span class="fill"><?php echo esc_html( isset( $other_row['label'] ) ? $other_row['label'] : '' ); ?></span>
			</span>
			<span class="col-person"><span class="fill person"><?php echo esc_html( isset( $other_row['person'] ) ? $other_row['person'] : '' ); ?></span></span>
			<span class="col-date"><span class="fill"><?php echo esc_html( isset( $other_row['date'] ) ? $other_row['date'] : '' ); ?></span></span>
			<span class="col-time"><span class="fill"><?php echo esc_html( isset( $other_row['time'] ) ? $other_row['time'] : '' ); ?></span></span>
		</div>

		<!-- Footer -->
		<div class="footer">
			<svg class="dc-star" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
				<polygon points="25,4 28.4,17.3 42,17.6 31.3,25.6 35.2,38.8 25,30.8 14.8,38.8 18.7,25.6 8,17.6 21.6,17.3" fill="#C00000" stroke="#1F4E79" stroke-width="0.6"/>
				<polygon points="10,42 13,40 16,42 14.8,38.5 17.8,36.5 14.2,36.5 13,33 11.8,36.5 8.2,36.5 11.2,38.5" fill="#1F4E79"/>
				<polygon points="40,42 43,40 46,42 44.8,38.5 47.8,36.5 44.2,36.5 43,33 41.8,36.5 38.2,36.5 41.2,38.5" fill="#1F4E79"/>
			</svg>
			<div class="addr">
				1125 15<sup>th</sup> Street N.W. Washington, D.C. 2005<br>
				202-730-1700 www.dds.dc
			</div>
		</div>
	</div>

	<div class="page2-pgnum">Page 2 of 2</div>
</div>

<script>
(function () {
	var params = new URLSearchParams(window.location.search);
	if (params.get('autoprint') !== '0') {
		window.addEventListener('load', function () {
			setTimeout(function () { window.print(); }, 300);
		});
	}
})();
</script>
</body>
</html>
		<?php
	}
}
