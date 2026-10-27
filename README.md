# CampusFix
# CampusFix

CampusFix is a PHP and MySQL facility maintenance and service request management system for campus operations. Requesters can submit requests and follow their status from **My Requests → View**. Administrators and assigned personnel update statuses through the existing request management pages.

## Request tracking database setup

For a new installation, create the database using `database.sql`; it includes the progress-history table.

For an existing installation, run `tracking_migration.sql` once against the `campusfix` database. It adds the progress-history table without deleting request records and backfills the stages that can be inferred from each request's current status. Since older status-change timestamps were not retained, those backfilled stages use each request's `updated_at` value. New stage timestamps are recorded as statuses are updated.

For an existing installation, also run `completion_photo_migration.sql` once. It adds an optional image reference for maintenance/ITSO completion evidence without changing existing requests. Maintenance and ITSO must attach a valid JPG, PNG, or WEBP photo (up to 5 MB) before marking assigned work complete. Requesters can edit or delete only their own pending requests; administrators manage assignments/status, and system administrators retain request-deletion access.

## Server configuration

The local XAMPP defaults are `localhost`, user `root`, an empty password, and database `campusfix`. Configure other environments using `CAMPUSFIX_DB_HOST`, `CAMPUSFIX_DB_USER`, `CAMPUSFIX_DB_PASSWORD`, and `CAMPUSFIX_DB_NAME`.

Optional Gmail delivery uses `CAMPUSFIX_SMTP_EMAIL` and `CAMPUSFIX_SMTP_APP_PASSWORD`. Do not put SMTP credentials in source files; configure them in the server environment. If email is not configured, password reset and notification email cannot be delivered.

## Help and reports

Signed-in users can open **Help & Support** from the navigation bar for FAQs and links to the request workflow. Administrative Staff and System Administrators also have **Reports**, with inclusive start/end date filters, request volume and category charts, personnel performance, and a filtered CSV export. The current schema has no satisfaction ratings, so reports show ratings as unavailable. Resolution duration uses the resolved-stage timestamp when available and falls back to `updated_at` for older requests without stage history.

## Live request updates

MySQL is the source of truth for request data and permissions. Request lists, dashboards, and tracking pages poll the authenticated `request_updates.php` endpoint for changes, so live status updates continue to work when Firebase credentials are unavailable. When a Firebase service account is configured locally (the credential file is excluded from version control), the optional Firestore sync and listeners can be enabled.

All state-changing forms use session-bound CSRF tokens. Confirmations are shown before destructive or consequential actions; successful and failed actions return visible feedback.
