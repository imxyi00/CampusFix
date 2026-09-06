CampusFix - Secured Prototype

Stack: HTML/CSS/JavaScript + PHP + MySQL (XAMPP)

Main roles:
- Requester: submit and track own requests.
- Administrative Staff: review, search, filter, assign, and update requests.
- Maintenance Personnel: view and update only requests assigned to their account.
- ITSO Personnel: view and update only requests assigned to their account.
- System Administrator: all administrative request functions plus user-account management and permanent request deletion.

Major revisions included:
- Requester dashboard with summary cards, recent requests, request progress, notifications, and a submit-request call to action.
- Admin operations dashboard with category analytics, recent requests, needs-attention list, and personnel workload.
- Search, filter, and sorting tools for request management.
- Inline request review panel with photo, description, assignment, and status controls.
- System Administrator-only delete confirmation for requests.
- Requester request history filters and detailed request progress view.
- Public registration remains requester-only.
- Privileged accounts are created through Manage Users by a System Administrator.
- Role-based access checks are enforced on protected pages.
- Uploaded images are validated and PHP execution is blocked inside uploads.

Setup:
1. Extract this folder into XAMPP/htdocs/.
2. Start Apache and MySQL.
3. Open phpMyAdmin and import database.sql.
4. Visit http://localhost/campusfix/.
5. Register a normal account. For the first local admin, change that account manually in phpMyAdmin:
   UPDATE users SET role = 'system_admin' WHERE email = 'qwbambalan@tip.edu.ph';
6. Log in as System Administrator and create the required staff accounts from Manage Users.

Note: SMS notifications are represented in the UI as request updates in this prototype; an external SMS provider still needs to be connected for actual SMS delivery.
