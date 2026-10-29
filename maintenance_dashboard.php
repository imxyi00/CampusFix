



<?php
require_once __DIR__ . "/config.php";

$metrics = [
    'pending' => 12,
    'in_progress' => 8,
    'completed_today' => 15,
    'critical_alerts' => 2
];


// Dynamic Priority Maintenance Tasks
$tasks = [
    [
        'id' => '#CF-1088',
        'priority' => 'Priority High',
        'title' => 'Projector Display Flicker',
        'location' => 'Bldg 2 - Lab 201',
        'tech' => 'M. Santos (ITSO)',
        'status' => 'IN PROGRESS',
        'badge_class' => 'badge-progress',
        'icon' => 'fa-desktop',
        'is_urgent' => false
    ],
    [
        'id' => '#CF-1092',
        'priority' => 'Priority Emergency',
        'title' => 'Main Breaker Tripped',
        'location' => 'Bldg 3 - 3rd Floor East',
        'tech' => 'Unassigned',
        'status' => 'URGENT',
        'badge_class' => 'badge-urgent',
        'icon' => 'fa-bolt',
        'is_urgent' => true
    ],
    [
        'id' => '#CF-1085',
        'priority' => 'Priority Medium',
        'title' => 'Hallway Lighting Outage',
        'location' => 'Main Bldg - 2nd Floor',
        'tech' => 'R. Cruz',
        'status' => 'PENDING ASSIGNMENT',
        'badge_class' => 'badge-pending',
        'icon' => 'fa-lightbulb',
        'is_urgent' => false
    ]
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/cflogo.png">
  <title>CampusFix - Maintenance Dashboard</title>
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
     
      --status-progress-bg: #E1F5FE;
      --status-progress-text: #0288D1;
     
      --status-urgent-bg: #FFEBEE;
      --status-urgent-text: #D32F2F;
     
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


    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }


    .metric-card {
      background-color: var(--card-white);
      border-radius: 16px;
      padding: 18px 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }


    .metric-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }


    .metric-label {
      font-size: 12px;
      font-weight: 700;
      color: var(--text-muted);
    }


    .metric-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background-color: var(--card-yellow);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      color: var(--text-dark);
    }


    .metric-icon.urgent {
      background-color: var(--status-urgent-bg);
      color: var(--status-urgent-text);
    }


    .metric-value {
      font-size: 28px;
      font-weight: 900;
      color: var(--text-dark);
      line-height: 1;
    }


    .dashboard-card {
      background-color: var(--card-yellow);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    }


    .card-title {
      font-size: 17px;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 18px;
    }


    .task-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }


    .task-item {
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


    .task-left {
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


    .icon-box.urgent {
      background-color: var(--status-urgent-bg);
      color: var(--status-urgent-text);
    }


    .task-details {
      display: flex;
      flex-direction: column;
    }


    .task-id {
      font-size: 11.5px;
      font-weight: 700;
      color: var(--text-muted);
    }


    .task-title-text {
      font-size: 15px;
      font-weight: 800;
      color: var(--text-dark);
      margin: 2px 0;
    }


    .task-subtext {
      font-size: 12.5px;
      font-weight: 500;
      color: var(--text-muted);
    }


    .badge {
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }


    .badge-progress {
      background-color: var(--status-progress-bg);
      color: var(--status-progress-text);
    }


    .badge-urgent {
      background-color: var(--status-urgent-bg);
      color: var(--status-urgent-text);
    }


    .badge-pending {
      background-color: var(--status-pending-bg);
      color: var(--status-pending-text);
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
      .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .task-item {
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 12px;
      }
      .task-left {
        min-width: 0;
        flex: 1 1 200px;
      }
    }

    @media (max-width: 380px) {
      .metrics-grid {
        grid-template-columns: 1fr;
      }
      .metric-card {
        padding: 16px;
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
      <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
      <li><a href="manage_requests.php" class="nav-link">Manage Requests</a></li>
      <li><a href="manage_users.php" class="nav-link">Manage Users</a></li>
      <li><a href="records.php" class="nav-link">Records</a></li>
      <li><form action="logout.php" method="POST" onsubmit="return confirm('Are you sure you want to log out of CampusFix?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>"><button type="submit" class="nav-link logout-btn">Logout</button></form></li>
    </ul>
  </nav>


  <div class="app-container">
   
    <header class="header-section">
      <div class="sub-heading">OPERATIONS OVERVIEW</div>
      <h1 class="main-title">Maintenance Dashboard</h1>
      <p class="description">Live snapshot of campus facility health, active work orders, and emergency alerts.</p>
    </header>


    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-top">
          <span class="metric-label">Pending</span>
          <div class="metric-icon"><i class="fa-regular fa-clock"></i></div>
        </div>
        <div class="metric-value"><?= sprintf('%02d', $metrics['pending']); ?></div>
      </div>


      <div class="metric-card">
        <div class="metric-top">
          <span class="metric-label">In Progress</span>
          <div class="metric-icon"><i class="fa-solid fa-gears"></i></div>
        </div>
        <div class="metric-value"><?= sprintf('%02d', $metrics['in_progress']); ?></div>
      </div>


      <div class="metric-card">
        <div class="metric-top">
          <span class="metric-label">Completed Today</span>
          <div class="metric-icon"><i class="fa-regular fa-circle-check"></i></div>
        </div>
        <div class="metric-value"><?= sprintf('%02d', $metrics['completed_today']); ?></div>
      </div>


      <div class="metric-card">
        <div class="metric-top">
          <span class="metric-label">Critical Alerts</span>
          <div class="metric-icon urgent"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="metric-value"><?= sprintf('%02d', $metrics['critical_alerts']); ?></div>
      </div>
    </div>


    <div class="dashboard-card">
      <div class="card-title">Priority Maintenance Tasks</div>


      <div class="task-list">
        <?php foreach ($tasks as $task): ?>
          <a href="request_details.php?id=<?= urlencode($task['id']); ?>" class="task-item">
            <div class="task-left">
              <div class="icon-box <?= $task['is_urgent'] ? 'urgent' : ''; ?>">
                <i class="fa-solid <?= htmlspecialchars($task['icon']); ?>"></i>
              </div>
              <div class="task-details">
                <span class="task-id"><?= htmlspecialchars($task['id']); ?> · <?= htmlspecialchars($task['priority']); ?></span>
                <div class="task-title-text"><?= htmlspecialchars($task['title']); ?></div>
                <div class="task-subtext"><?= htmlspecialchars($task['location']); ?> · Tech: <?= htmlspecialchars($task['tech']); ?></div>
              </div>
            </div>
            <span class="badge <?= htmlspecialchars($task['badge_class']); ?>"><?= htmlspecialchars($task['status']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>


  </div>


</body>
</html>
