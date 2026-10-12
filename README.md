# CampusFix
# CampusFix

CampusFix is a PHP and MySQL facility maintenance and service request management system for campus operations. Requesters can submit requests and follow their status from **My Requests → View**. Administrators and assigned personnel update statuses through the existing request management pages.

## Request tracking database setup

For a new installation, create the database using `database.sql`; it includes the progress-history table.

For an existing installation, run `tracking_migration.sql` once against the `campusfix` database. It adds the progress-history table without deleting request records and backfills the stages that can be inferred from each request's current status. Since older status-change timestamps were not retained, those backfilled stages use each request's `updated_at` value. New stage timestamps are recorded as statuses are updated.

## Help and reports

Signed-in users can open **Help & Support** from the navigation bar for FAQs and links to the request workflow. Administrative Staff and System Administrators also have **Reports**, with inclusive start/end date filters, request volume and category charts, personnel performance, and a filtered CSV export. The current schema has no satisfaction ratings, so reports show ratings as unavailable. Resolution duration uses the resolved-stage timestamp when available and falls back to `updated_at` for older requests without stage history.
