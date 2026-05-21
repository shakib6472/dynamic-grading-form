# DDA Incident Report Form

A WordPress plugin that publishes the **DC Government DDA Incident Report** as a single shortcode-driven assessment. Learners fill out the form once; an admin or instructor reviews it from the WordPress dashboard, assigns a score, and the learner gets an automatic pass/fail email and an in-page result.

* **Plugin file:** `m_o_form.php`
* **Text domain:** `dda-incident-report`
* **Requires:** WordPress 6.4+, PHP 8.0+
* **License:** GPL v2 or later

---

## Installation

1. Copy the entire `m_o_form/` folder into `wp-content/plugins/`.
2. In **WP Admin → Plugins**, activate **DDA Incident Report Form**.

   On activation the plugin registers a new role: **DDA Instructor**.

3. Create or edit any page and add the shortcode:

   ```
   [dda_incident_report]
   ```

   The page that holds the shortcode becomes the *single hub* the learner returns to — to take the assessment, to see "pending review," and later to see their result.

---

## The shortcode

| Shortcode | Description |
|-----------|-------------|
| `[dda_incident_report]` | Renders the entire flow. The plugin auto-detects the viewer's state and shows the matching screen. |

The shortcode takes **no attributes** — there is nothing to configure on the page itself.

### What the learner sees

The shortcode renders one of five states automatically:

| Viewer state | Screen |
|--------------|--------|
| Not logged in | "Sign in to start the assessment" card with a **Log in** button |
| Logged in, no submission yet | The full incident-report form |
| Submitted, no score yet | "Thank you — instructor is reviewing" card with the submission date |
| Scored, ≥ 80% | Green "Congratulations — you passed!" card with score ring + instructor notes |
| Scored, < 80% | Red "Your result is ready" card with score ring + instructor notes |

**Rules:**

* Only logged-in users can submit.
* Each user can submit **only one** report. Re-submission is blocked at the handler.
* The result page is the page the shortcode is on. The plugin stores that URL on submission and uses it in the result email's "View your result" button.

---

## Admin / Instructor workflow

### Who can score

| Role | Can score? |
|------|-----------|
| Administrator | Yes |
| Editor | Yes |
| **DDA Instructor** (created on activation) | Yes |
| Author / Contributor / Subscriber | No |

You can promote a user to instructor under **Users → Edit user → Role → DDA Instructor**.

To grant scoring rights to a different role, hook the filter:

```php
add_filter( 'dda_incident_report_user_can_score', function ( $can, $user_id ) {
    if ( user_can( $user_id, 'my_custom_cap' ) ) {
        return true;
    }
    return $can;
}, 10, 2 );
```

### Reviewing a submission

1. In WP Admin, open **Incident Reports** in the left menu.
2. The list table shows: **MCIS #**, **Person**, **Incident Date**, **Reporter**, and a **Score** column with a pill — `Pending`, `Pass · 87.5`, or `Fail · 62.0`.
3. Click a report. You will see:

   * **Incident Report (Paper Form View)** — the entire submission rendered like the official paper form: section banners, two/three-column field grids with underlined values, lined-paper textareas for narrative fields, and ticked checkboxes (`✓` / `☐`) for selections.
   * **Submitter** (sidebar) — the learner's name, email, submission timestamp, and IP.
   * **Review & Score** (sidebar) — where you score the submission.

### Scoring a submission

In the **Review & Score** box:

1. Enter a **Score** between `0` and `100` (decimals allowed, e.g. `82.5`).
2. Optionally add **Review Notes**. These notes are shown to the learner on their result page and are also referenced in the email's design.
3. Leave the **Email the result to the submitter on save** checkbox ticked.
4. Click **Update** in the WordPress publish box.

What happens on save:

* Score is clamped to `0–100` and saved.
* `Last reviewed` timestamp + reviewer's name are recorded.
* An HTML email is sent to the submitter:
  * **≥ 80% (configurable):** "Congratulations — You Passed" email (green theme).
  * **< 80%:** "Your Incident Report Result" email (red theme).
* The learner sees their pass/fail result the next time they load the shortcode page.

### Re-sending the email or changing the score

* Edit the score, tick **Email the result to the submitter on save** again, and click **Update**. A note below the checkbox indicates whether an email has already been sent.
* Clear the score field entirely to reset back to "pending review" — the learner will see the "Pending review" card again.

---

## Settings & filters

There is **no settings page** by design. Everything is controlled via these filters, which you can add to your theme's `functions.php` or to a small site-specific plugin.

| Filter / hook | What it does | Default |
|---|---|---|
| `dda_incident_report_passing_score` | Pass threshold percent (0–100). | `80` |
| `dda_incident_report_user_can_score` | Whether a user is allowed to score. | `current_user_can( 'edit_others_posts' )` |
| `dda_incident_report_result_email` | Mutate `to` / `subject` / `message` / `headers` before `wp_mail`. | — |
| `dda_incident_report_submitted` (action) | Fires after a learner submits a report. Receives `$post_id`. | — |

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

The plugin uses the standard WordPress post capabilities (the CPT uses `capability_type => 'post'`) and ships one extra role:

**DDA Instructor** — granted on activation:
* `read`
* `edit_posts`
* `edit_others_posts`
* `edit_published_posts`
* `read_private_posts`

Instructors do **not** get `publish_posts` or any delete caps — they cannot delete reports. Admins handle that.

---

## Data model

| Custom post type | `dda_incident` |
|---|---|
| Meta prefix | `_dda_` |
| Score meta | `_dda_score` (0–100, float) |
| Review notes meta | `_dda_review_notes` |
| Reviewed-at meta | `_dda_reviewed_at` (MySQL datetime) |
| Reviewed-by meta | `_dda_reviewed_by` (user ID) |
| Email-sent meta | `_dda_email_sent` (MySQL datetime, presence = sent at least once) |
| Result URL meta | `_dda_result_url` (page that holds the shortcode) |
| Submitted-at meta | `_dda_submitted_at` |
| Submitted-IP meta | `_dda_submitted_ip` |
| Post author | The submitting user — this is how the "one per user" rule is enforced |

All form fields are stored as `_dda_<field_name>` post meta. Checkbox arrays (e.g. `_dda_serious_reportable`) are saved as arrays of slugs. The verbal-notifications block is stored as a nested array under `_dda_notifications`.

---

## Design

* **Fonts:** Urbanist (headings) + Inter (body), loaded from Google Fonts only on pages that contain the shortcode.
* **Palette:** primary blue `#0B5FA3`, amber accent `#F59E0B`, emerald `#059669` for pass, red `#DC2626` for fail.
* **Front-end CSS:** `assets/css/dda-incident-report.css`
* **Admin (paper-form view) CSS:** `assets/css/dda-incident-report-admin.css`

Override either by enqueuing your own stylesheet after `dda-incident-report` / `dda-incident-report-admin`.

---

## Uninstall

Deactivating the plugin leaves your data intact (and keeps the **DDA Instructor** role around).

Deleting the plugin from **Plugins → Installed Plugins → Delete** runs `uninstall.php`, which **permanently removes all incident report posts and their meta**. Take a backup first if you want to keep them.

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
│       └── dda-incident-report-admin.css   ← paper-form + scoring panel
└── includes/
    ├── class-plugin.php          ← singleton orchestrator + constants
    ├── class-fields.php          ← option lists (single source of truth)
    ├── class-user-state.php      ← resolves viewer state, exposes score helpers
    ├── class-activator.php       ← creates DDA Instructor role on activation
    ├── class-post-type.php       ← registers the dda_incident CPT
    ├── class-assets.php          ← enqueues Google Fonts + CSS (conditional)
    ├── class-shortcode.php       ← state-aware [dda_incident_report] output
    ├── class-form-handler.php    ← submission handler with one-per-user rule
    ├── class-scoring.php         ← Review & Score meta box + save logic
    ├── class-emailer.php         ← HTML pass/fail email templates
    └── class-admin.php           ← paper-form view + list-table columns
```
