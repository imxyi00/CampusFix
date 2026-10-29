<?php
require_once __DIR__ . "/config.php";

$records = [
    [
        'id' => '#CF-1089',
        'title' => 'Aircon Defective & Noisy',
        'location' => 'Bldg 3 - Room 402',
        'assigned' => 'A. Dela Cruz',
        'category' => 'HVAC',
        'status' => 'COMPLETED',
        'badge_class' => 'badge-completed',
        'icon' => 'fa-asterisk'
    ],
    [
        'id' => '#CF-1088',
        'title' => 'Projector Display Flicker',
        'location' => 'Bldg 2 - Lab 201',
        'assigned' => 'M. Santos (ITSO)',
        'category' => 'IT',
        'status' => 'IN PROGRESS',
        'badge_class' => 'badge-progress',
        'icon' => 'fa-desktop'
    ],
    [
        'id' => '#CF-1085',
        'title' => 'Hallway Lighting Outage',
        'location' => 'Main Bldg - 2nd Floor',
        'assigned' => 'Unassigned',
        'category' => 'Electrical',
        'status' => 'PENDING',
        'badge_class' => 'badge-pending',
        'icon' => 'fa-lightbulb'
    ],
    [
        'id' => '#CF-1081',
        'title' => 'Classroom Unlocking Request',
        'location' => 'Bldg 5 - Room 105',
        'assigned' => 'A. Mendoza',
        'category' => 'Access',
        'status' => 'COMPLETED',
        'badge_class' => 'badge-completed',
        'icon' => 'fa-key'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/cflogo.png">
  <title>CampusFix - Facility Request Records</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 
  <style>
    :root {
      --bg-yellow: #FFC01D;
      --oval-glow: #FFD54F;
      --nav-highlight: #FFE033;
      --card-yellow: #FFF59E;
      --card-white: #FFFFFF;
      --text-dark: #111111;
      --text-muted: #666666;
      --btn-black: #111111;
     
      --status-completed-bg: #E8F5E9;
      --status-completed-text: #2E7D32;
     
      --status-progress-bg: #E1F5FE;
      --status-progress-text: #0288D1;
     
      --status-pending-bg: #FFF3E0;
      --status-pending-text: #E65100;
    }


    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    html {
      overflow-x: clip;
    }


    body {
      background-color: var(--bg-yellow);
      color: var(--text-dark);
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }


    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 140vw;
      height: 380px;
      background-color: var(--oval-glow);
      border-bottom-left-radius: 50% 100%;
      border-bottom-right-radius: 50% 100%;
      z-index: 0;
      pointer-events: none;
    }


    .navbar {
      background-color: var(--nav-highlight);
      padding: 12px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      position: relative;
      z-index: 1000;
    }


    .nav-left {
      display: flex;
      align-items: center;
      gap: 14px;
    }


    .nav-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: var(--text-dark);
    }


    .brand-logo-icon {
      font-size: 26px;
      color: var(--text-dark);
    }


    .nav-brand-text {
      display: flex;
      flex-direction: column;
      line-height: 1.1;
    }


    .brand-title {
      font-weight: 900;
      font-size: 20px;
      letter-spacing: -0.3px;
    }


    .brand-subtitle {
      font-size: 9px;
      font-weight: 800;
      color: #665200;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-top: 1px;
    }


    .nav-menu {
      display: flex;
      align-items: center;
      gap: 20px;
      list-style: none;
    }


    .nav-link {
      text-decoration: none;
      color: var(--text-dark);
      font-weight: 700;
      font-size: 13px;
      padding: 6px 14px;
      border-radius: 20px;
      transition: background-color 0.2s;
    }


    .nav-link:hover {
      background-color: rgba(0,0,0,0.06);
    }


    .nav-link.active {
      background-color: rgba(0,0,0,0.12);
    }


    .nav-link.logout-btn {
      background-color: var(--card-white);
      color: var(--text-dark);
      padding: 6px 20px;
      border-radius: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }


    .app-container {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 860px;
      margin: 0 auto;
      padding: 36px 20px;
    }


    .header-section {
      margin-bottom: 24px;
    }


    .sub-heading {
      text-transform: uppercase;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1.2px;
      color: #5C4B00;
      margin-bottom: 4px;
    }


    .main-title {
      font-size: 36px;
      font-weight: 900;
      color: var(--text-dark);
      letter-spacing: -0.5px;
      margin-bottom: 6px;
    }


    .description {
      font-size: 13.5px;
      color: #332B00;
      font-weight: 500;
    }


    .filter-card {
      background-color: var(--card-yellow);
      border-radius: 20px;
      padding: 16px 20px;
      margin-bottom: 24px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }


    .filter-row {
      display: flex;
      gap: 12px;
      align-items: center;
    }


    .search-group {
      flex: 2;
      position: relative;
      display: flex;
      align-items: center;
    }


    .search-group i {
      position: absolute;
      left: 16px;
      color: #888888;
      font-size: 13px;
    }


    .input-search {
      width: 100%;
      padding: 12px 16px 12px 42px;
      border-radius: 25px;
      border: none;
      background-color: var(--card-white);
      font-size: 13.5px;
      font-weight: 500;
      color: var(--text-dark);
      outline: none;
    }


    .input-search::placeholder {
      color: #888888;
    }


    .select-filter {
      flex: 1;
      padding: 12px 18px;
      border-radius: 25px;
      border: none;
      background-color: var(--card-white);
      font-size: 13px;
      font-weight: 700;
      color: var(--text-dark);
      outline: none;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 16px center;
      background-size: 12px;
      padding-right: 36px;
    }


    .records-card {
      background-color: var(--card-yellow);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    }


    .records-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }


    .records-title {
      font-size: 17px;
      font-weight: 800;
      color: var(--text-dark);
    }


    .btn-export {
      background-color: var(--btn-black);
      color: #FFFFFF;
      border: none;
      padding: 9px 18px;
      border-radius: 12px;
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
    }


    .record-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }


    .record-item {
      background-color: var(--card-white);
      border-radius: 16px;
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      text-decoration: none;
      color: inherit;
    }


    .record-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }


    .icon-box {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background-color: var(--card-yellow);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: var(--text-dark);
    }


    .record-details {
      display: flex;
      flex-direction: column;
    }


    .record-id {
      font-size: 11.5px;
      font-weight: 700;
      color: var(--text-muted);
    }


    .record-title-text {
      font-size: 15px;
      font-weight: 800;
      color: var(--text-dark);
      margin: 2px 0;
    }


    .record-subtext {
      font-size: 12.5px;
      font-weight: 500;
      color: var(--text-muted);
    }


    .record-right {
      display: flex;
      align-items: center;
      gap: 14px;
    }


    .badge {
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }


    .badge-completed {
      background-color: var(--status-completed-bg);
      color: var(--status-completed-text);
    }


    .badge-progress {
      background-color: var(--status-progress-bg);
      color: var(--status-progress-text);
    }


    .badge-pending {
      background-color: var(--status-pending-bg);
      color: var(--status-pending-text);
    }


    .arrow-icon {
      font-size: 12px;
      color: var(--text-dark);
    }


    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        gap: 12px;
        padding: 12px clamp(12px, 4vw, 20px);
      }
      .nav-menu {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 4px;
        padding: 0;
      }
      .nav-menu li {
        flex: 0 0 auto;
      }
      .nav-menu form {
        margin: 0;
      }
      .app-container {
        padding: 28px clamp(12px, 4vw, 20px);
      }
      .main-title {
        font-size: clamp(28px, 8vw, 36px);
      }
      .filter-row {
        flex-direction: column;
      }
      .select-filter {
        width: 100%;
      }
      .records-card {
        padding: 18px;
      }
      .record-item {
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 12px;
        padding: 15px;
      }
      .record-left {
        min-width: 0;
        flex: 1 1 200px;
      }
      .record-right {
        max-width: 100%;
        flex-wrap: wrap;
      }
      .records-header {
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 12px;
      }
    }

    @media (max-width: 380px) {
      .filter-card,
      .records-card {
        padding: 14px;
      }
      .record-item {
        padding: 13px;
      }
    }
  </style>
</head>
<body>


  <nav class="navbar">
    <div class="nav-left">
      <a href="dashboard.php" class="nav-link nav-back" data-history-back>← Back</a>
      <a href="dashboard.php" class="nav-brand">
        <i class="fa-solid fa-chess-knight brand-logo-icon"></i>
        <div class="nav-brand-text">
          <span class="brand-title">CampusFix</span>
          <span class="brand-subtitle">T.I.P. QUEZON CITY</span>
        </div>
      </a>
    </div>
   
    <ul class="nav-menu">
      <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
      <li><a href="manage_requests.php" class="nav-link">Manage Requests</a></li>
      <li><a href="manage_users.php" class="nav-link">Manage Users</a></li>
      <li><a href="records.php" class="nav-link active">Records</a></li>
      <li><form action="logout.php" method="POST" onsubmit="return confirm('Are you sure you want to log out of CampusFix?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>"><button type="submit" class="nav-link logout-btn">Logout</button></form></li>
    </ul>
  </nav>


  <div class="app-container">
   
    <header class="header-section">
      <div class="sub-heading">RECORDS MANAGEMENT</div>
      <h1 class="main-title">Facility Request Records</h1>
      <p class="description">Search, filter, review, assign, and update submitted requests.</p>
    </header>


    <div class="filter-card">
      <div class="filter-row">
        <div class="search-group">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" class="input-search" placeholder="Search by ID, facility, or location...">
        </div>
        <select class="select-filter" id="statusFilter">
          <option value="ALL">All Statuses</option>
          <option value="COMPLETED">Completed</option>
          <option value="IN PROGRESS">In Progress</option>
          <option value="PENDING">Pending</option>
        </select>
        <select class="select-filter" id="categoryFilter">
          <option value="ALL">All Categories</option>
          <option value="HVAC">HVAC / Aircon</option>
          <option value="IT">IT / Display</option>
          <option value="Electrical">Electrical</option>
          <option value="Access">Access Control</option>
        </select>
      </div>
    </div>


    <div class="records-card">
      <div class="records-header">
        <div class="records-title">Facility Request Records</div>
        <button class="btn-export" onclick="alert('Exporting Report...')">
          <i class="fa-solid fa-download"></i> Export Report
        </button>
      </div>


      <div class="record-list" id="recordList">
        <?php foreach ($records as $row): ?>
          <a href="request_details.php?id=<?= urlencode($row['id']); ?>"
             class="record-item"
             data-status="<?= htmlspecialchars($row['status']); ?>"
             data-category="<?= htmlspecialchars($row['category']); ?>">
            <div class="record-left">
              <div class="icon-box">
                <i class="fa-solid <?= htmlspecialchars($row['icon']); ?>"></i>
              </div>
              <div class="record-details">
                <span class="record-id"><?= htmlspecialchars($row['id']); ?></span>
                <div class="record-title-text"><?= htmlspecialchars($row['title']); ?></div>
                <div class="record-subtext"><?= htmlspecialchars($row['location']); ?> · Assigned: <?= htmlspecialchars($row['assigned']); ?></div>
              </div>
            </div>
            <div class="record-right">
              <span class="badge <?= htmlspecialchars($row['badge_class']); ?>"><?= htmlspecialchars($row['status']); ?></span>
              <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>


  </div>


  <script>
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const recordItems = document.querySelectorAll('.record-item');


    function filterRecords() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedStatus = statusFilter.value;
      const selectedCategory = categoryFilter.value;


      recordItems.forEach(item => {
        const text = item.innerText.toLowerCase();
        const status = item.getAttribute('data-status');
        const category = item.getAttribute('data-category');


        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = (selectedStatus === 'ALL' || status === selectedStatus);
        const matchesCategory = (selectedCategory === 'ALL' || category === selectedCategory);


        if (matchesSearch && matchesStatus && matchesCategory) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }


    searchInput.addEventListener('input', filterRecords);
    statusFilter.addEventListener('change', filterRecords);
    categoryFilter.addEventListener('change', filterRecords);
  </script>


</body>
</html>
