# DDA Incident Report Form

A WordPress plugin that publishes the **DC Government DDA Incident Report** as a single shortcode-driven assessment. Learners fill out the form once on the front end; an admin or **TutorLMS instructor** reviews it from their dashboard, assigns a score, and the learner gets an automatic pass/fail email and an in-page result. Any reviewer can also **print the submission as the official PDF form**.

* **Plugin file:** `m_o_form.php`
* **Text domain:** `dda-incident-report`
* **Requires:** WordPress 6.4+, PHP 8.0+, optional [TutorLMS](https://wordpress.org/plugins/tutor/) (free or Pro)
* **License:** GPL v2 or later

---

## Installation

1. Copy the entire `m_o_form/` folder into `wp-content/plugins/`.
2. In **WP Admin → Plugins**, activate **DDA Incident Report Form**.
   On activation the plugin registers a new role: **DDA Instructor** (used if you don't have TutorLMS instructors).
3. Create or edit any page and add the shortcode:

   ```
   [dda_incident_report]
   ```

   This page becomes the *single hub* the learner returns to — to take the assessment, to see "pending review," and later to see their result.

4. *(Optional but recommended)* Install and activate **TutorLMS** (free) or **Tutor LMS Pro**. Anyone with the `tutor_instructor` role will see a new **Incident Reports** tab on the TutorLMS frontend dashboard — they can grade reports without needing access to `/wp-admin/`.

---

## How learners use it (`[dda_incident_report]`)

The shortcode takes **no attributes**. It auto-detects who's viewing and renders one of five screens:

| Viewer state | Screen |
|--------------|--------|
| Not logged in | "Sign in to start the assessment" card with a **Log in** button |
| Logged in, no submission yet | The full incident-report form |
| Submitted, no score yet | "Thank you — instructor is reviewing" card |
| Scored, ≥ 80% | Green "Congratulations — you passed!" card with score ring + notes |
| Scored, < 80% | Red "Your result is ready" card with score ring + notes |

**Rules:**
- Only logged-in users can submit.
- Each user can submit **only one** report; the handler blocks duplicates.
- The result page is the page where the shortcode lives. The plugin stores that URL on submission and uses it in the email's "View your result" button.

---

## How instructors grade (TutorLMS frontend dashboard)

When TutorLMS is active, any user with the `tutor_instructor` role gets a new tab.

### The dashboard tab

**`/dashboard/incident-reports/`** — accessible from the TutorLMS sidebar as **Incident Reports**.

* **Header:** total / pending / passed / failed counters.
* **Filter tabs:** All · Pending Review · Passed · Failed (each with a count).
* **Search:** searches by title (auto-built from MCIS, person name, and date).
* **Table columns:** Submitter (name + email), Person Involved, Incident Date, Submitted, Status pill, and per-row actions: **Review** and **Print**.

### Reviewing a single report

Clicking **Review** opens the detail page (still inside the dashboard tab).

* **Left side:** the full submission rendered in a clean paper-form layout, identical to what an admin sees in `/wp-admin/`.
* **Right side (sticky panel):** **Review & Score** form with:
  - Current pass/fail pill if a score already exists
  - Score input (`0` – `100`, decimals allowed)
  - Review notes (visible to the learner on their result page)
  - "Email the result to the submitter on save" checkbox
* **Top right:** **Print PDF Form** button — opens a new window with the official PDF layout (see below) and auto-triggers the browser print dialog.

Clicking **Save & Send** persists the score, optionally sends the email, and reloads the detail page with a confirmation banner. Clearing the score sends the report back to "pending review."

> **Permission rule:** The dashboard tab and score-save endpoint check `DDA_Incident_Report_User_State::user_can_score()`. By default that allows: administrators, editors, `tutor_instructor`, and `dda_instructor`. Override via the `dda_incident_report_user_can_score` filter.

---

## Printing the official PDF form

Every report has a print URL:

```
https://yoursite.com/?dda_print=<REPORT_ID>
```

* The author of the report can print their own report.
* Any user who can score (admins, editors, TutorLMS instructors, DDA instructors) can print any report.
* Opens a self-contained HTML page laid out to match the official **DDA Incident Report (REV 12/13)** PDF:
  - DC government header (flag, blue typography, "Government of the District of Columbia / Department of Disability Services / Quality Management Division" titles)
  - Outer black-bordered content box
  - Red labels for `MCIS REPORT NUMBER`, `DATE OF INCIDENT`, etc.
  - 4-column **Section 1 INCIDENT CATEGORIZATION** grid (Serious Reportable · Abuse and Neglect · Reportable · Incident Location Type)
  - Section 2 description block with lined paper background, witnesses, signature line
  - Verbal Notifications table with 4 columns (Recipient · Person Notified · Date · Time)
  - DC star footer + Washington, D.C. address
  - "Page 1 of 2" / "Page 2 of 2" footers
* The browser print dialog opens automatically on page load. To suppress auto-print, append `&autoprint=0`.

Print buttons appear in:
* TutorLMS instructor detail page — top right
* TutorLMS list — per-row "Print" action
* WP admin sidebar meta box ("Print PDF Form")
* WP admin list-table row actions

To use a PDF instead of the browser print dialog, use your browser's "Save as PDF" or "Print to PDF" option, or attach the print URL to any "HTML → PDF" backend conversion service.

---

## WP-admin workflow (still works)

If you don't want to use TutorLMS, the entire workflow remains available in `/wp-admin/`:

1. Go to **Incident Reports** in the left admin menu.
2. The list shows: MCIS #, Person, Incident Date, Reporter, Score pill, and row actions (Edit · Print).
3. Open a report. You'll see:
   * **Incident Report** meta box — paper-form rendering of the submission.
   * **Submitter & Actions** (sidebar) — author info + a big **Print PDF Form** button.
   * **Review & Score** (sidebar) — same scoring panel as the TutorLMS dashboard.
4. Save the post to apply the score / send the email.

---

## Who can score (capability matrix)

| Role | Can score? | Where |
|------|-----------|-------|
| Administrator | Yes | `/wp-admin/` and TutorLMS dashboard (if also `tutor_instructor`) |
| Editor | Yes | `/wp-admin/` |
| `tutor_instructor` (TutorLMS) | **Yes** | **TutorLMS frontend dashboard tab** |
| `dda_instructor` (created by this plugin) | Yes | `/wp-admin/` (if you grant them admin access) |
| Author / Contributor / Subscriber | No | — |

To grant scoring rights to a different role, hook the filter:

```php
add_filter( 'dda_incident_report_user_can_score', function ( $can, $user_id ) {
    if ( user_can( $user_id, 'my_custom_cap' ) ) {
        return true;
    }
    return $can;
}, 10, 2 );
```

---

## Settings & filters

There is **no settings page** by design — everything is controlled with filters.

| Filter / hook | What it does | Default |
|---|---|---|
| `dda_incident_report_passing_score` | Pass threshold percent (0–100). | `80` |
| `dda_incident_report_user_can_score` | Whether a user is allowed to score. | admins, editors, `tutor_instructor`, `dda_instructor` |
| `dda_incident_report_result_email` | Mutate `to` / `subject` / `message` / `headers` before `wp_mail`. | — |
| `dda_incident_report_submitted` (action) | Fires after a learner submits. Receives `$post_id`. | — |
| `dda_incident_report_scored` (action) | Fires after a report is scored or cleared. Receives `$post_id`, `$score`, `$email_sent`. | — |

### Example: lower the passing score to 70%

```php
add_filter( 'dda_incident_report_passing_score', function () {
    return 70;
} );
```

### Example: BCC the office on every result email

```php
add_filter( 'dda_incident_report_result_email', function ( $args, $post_id, $score, $passed ) {
    $args['headers'][] = 'Bcc: office@example.com';
    return $args;
}, 10, 4 );
```

### Example: notify Slack when a learner submits

```php
add_action( 'dda_incident_report_submitted', function ( $post_id ) {
    wp_remote_post( 'https://hooks.slack.com/services/XXX', array(
        'body' => wp_json_encode( array(
            'text' => 'New DDA incident report: ' . get_the_title( $post_id ),
        ) ),
    ) );
} );
```

---

## Roles & capabilities reference

The plugin uses the standard WP post capabilities (`capability_type => 'post'`) and adds one role on activation:

**DDA Instructor (`dda_instructor`):**
* `read`, `edit_posts`, `edit_others_posts`, `edit_published_posts`, `read_private_posts`
* No `publish_posts`, no delete caps — admins handle that.

The plugin reads the **TutorLMS `tutor_instructor` role** out of the box; no extra setup is needed if TutorLMS is active.

---

## Data model

| Custom post type | `dda_incident` |
|---|---|
| Meta prefix | `_dda_` |
| Score meta | `_dda_score` (0–100, float) |
| Review notes meta | `_dda_review_notes` (shown on learner's result page) |
| Reviewed-at meta | `_dda_reviewed_at` (MySQL datetime) |
| Reviewed-by meta | `_dda_reviewed_by` (user ID) |
| Email-sent meta | `_dda_email_sent` (MySQL datetime, presence = sent at least once) |
| Result URL meta | `_dda_result_url` (page that holds the shortcode) |
| Submitted-at meta | `_dda_submitted_at` |
| Submitted-IP meta | `_dda_submitted_ip` |
| Post author | The submitting user — enforces the "one per user" rule |

All form fields are stored as `_dda_<field_name>` post meta. Checkbox arrays (e.g. `_dda_serious_reportable`) are saved as arrays of slugs. The verbal-notifications block is stored as a nested array under `_dda_notifications`.

---

## Design

* **Fonts:** Urbanist (headings) + Inter (body), loaded from Google Fonts only on pages with the shortcode.
* **Palette:** primary blue `#0B5FA3`, amber accent `#F59E0B`, emerald `#059669` for pass, red `#DC2626` for fail.
* **CSS files:**
  - `assets/css/dda-incident-report.css` — front-end (shortcode)
  - `assets/css/dda-incident-report-admin.css` — paper-form + scoring panel (WP admin)
  - `assets/css/dda-incident-report-tutorlms.css` — TutorLMS dashboard tab
  - The print HTML is fully self-contained (inline CSS) so it renders identically when opened via the print URL.

Override any of these by enqueuing your own stylesheet after the plugin's.

---

## Uninstall

Deactivating the plugin leaves your data intact (and keeps the **DDA Instructor** role).

Deleting the plugin via **Plugins → Installed Plugins → Delete** runs `uninstall.php`, which **permanently removes all incident report posts and their meta**. Take a backup first if you want to keep them.

---

## File map

```
m_o_form/
├── m_o_form.php                  ← header + bootstrap
├── uninstall.php                 ← deletes all reports on plugin removal
├── README.md
├── assets/
│   └── css/
│       ├── dda-incident-report.css         ← front-end design
│       ├── dda-incident-report-admin.css   ← admin paper-form + scoring panel
│       └── dda-incident-report-tutorlms.css ← TutorLMS dashboard tab
└── includes/
    ├── class-plugin.php          ← singleton orchestrator + constants
    ├── class-fields.php          ← option lists (single source of truth)
    ├── class-user-state.php      ← viewer state + can_score gate
    ├── class-activator.php       ← creates DDA Instructor role on activation
    ├── class-post-type.php       ← registers the dda_incident CPT
    ├── class-assets.php          ← enqueues Google Fonts + CSS (conditional)
    ├── class-shortcode.php       ← state-aware [dda_incident_report] output
    ├── class-form-handler.php    ← submission handler with one-per-user rule
    ├── class-paper-view.php      ← shared paper-form rendering (admin + TutorLMS)
    ├── class-print-view.php      ← official-PDF-matching print HTML
    ├── class-printer.php         ← print URL handler (?dda_print=ID)
    ├── class-scoring.php         ← Review & Score meta box + apply_score() helper
    ├── class-emailer.php         ← HTML pass/fail email templates
    ├── class-admin.php           ← WP admin meta boxes + list-table columns
    └── class-tutorlms.php        ← TutorLMS instructor dashboard tab
```
