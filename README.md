# CampusFix
# CampusFix

CampusFix is a PHP and MySQL facility maintenance and service request management system for campus operations. Requesters can submit requests and follow their status from **My Requests → View**. Administrators and assigned personnel update statuses through the existing request management pages.

## Request tracking database setup

For a new installation, create the database using `database.sql`; it includes the progress-history table.

For an existing installation, run `tracking_migration.sql` once against the `campusfix` database. It adds the progress-history table without deleting request records and backfills the stages that can be inferred from each request's current status. Since older status-change timestamps were not retained, those backfilled stages use each request's `updated_at` value. New stage timestamps are recorded as statuses are updated.

## Help and reports

Signed-in users can open **Help & Support** from the navigation bar for FAQs and links to the request workflow. Administrative Staff and System Administrators also have **Reports**, with inclusive start/end date filters, request volume and category charts, personnel performance, and a filtered CSV export. The current schema has no satisfaction ratings, so reports show ratings as unavailable. Resolution duration uses the resolved-stage timestamp when available and falls back to `updated_at` for older requests without stage history.

## Firebase Real-time Live Tracking & Push Alerts

CampusFix uses a hybrid architecture with Firebase Cloud Firestore for real-time tracking without full page reloads:
- **Primary source of truth**: MySQL database manages all relational records and permissions.
- **Real-time broadcast**: When a request is created, updated, or deleted (`submit_request.php`, `update_request.php`, `delete_request.php`), `firebase.php` automatically syncs the state to Cloud Firestore via service-account-authenticated REST API.
- **Client listeners**: `tracking.php`, `my_requests.php`, and `dashboard.php` use `assets/firebase-tracking.js` to listen for snapshot updates in real time, displaying live timeline updates, in-app toast alerts, and optional desktop push notifications.
