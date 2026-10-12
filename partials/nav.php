<nav class="navbar">
    <div class="nav-inner">
        <a class="brand" href="dashboard.php" aria-label="CampusFix Dashboard">
            <img src="partials/logo.png" alt="T.I.P." class="nav-logo">
            <span><strong>CampusFix</strong><small class="brand-school">T.I.P. Quezon City</small></span>
        </a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <?php if ($_SESSION["role"] === "requester"): ?>
                <a href="submit_request.php">Submit Request</a><a href="my_requests.php">My Requests</a>
            <?php elseif (in_array($_SESSION["role"], ["admin_staff", "system_admin"], true)): ?>
                <a href="manage_requests.php">Manage Requests</a>
                <?php if ($_SESSION["role"] === "system_admin"): ?><a href="manage_users.php">Manage Users</a><?php endif; ?>
            <?php elseif (in_array($_SESSION["role"], ["maintenance", "itso"], true)): ?>
                <a href="assigned_requests.php">Assigned Requests</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION["role"], ["admin_staff", "system_admin"], true)): ?>
                <a href="reports.php">Reports</a>
            <?php endif; ?>
            <a href="help_support.php">Help &amp; Support</a>
            <a class="nav-logout" href="logout.php">Logout</a>
        </div>
    </div>
</nav>
