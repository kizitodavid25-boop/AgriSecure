<?php
/**
 * AgroSecure – dashboard.php
 * Farmer dashboard — shows real data from the database.
 */
session_start();
require_once '../php/db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?status=error&message=" . urlencode('Please log in to access your dashboard.'));
    exit;
}

$conn = getDBConnection();
$user_id       = (int) $_SESSION['user_id'];
$user_name     = htmlspecialchars($_SESSION['user_full_name'] ?? 'Farmer');
$user_district = htmlspecialchars($_SESSION['user_district'] ?? '');

// 1. Get user phone for matching reports
$phone_res = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
$phone_res->bind_param('i', $user_id);
$phone_res->execute();
$user_phone = $phone_res->get_result()->fetch_assoc()['phone'] ?? '';
$phone_res->close();

// 2. Fetch this user's own reports (matched by phone)
$my_reports = [];
if ($user_phone) {
    $stmt = $conn->prepare(
        "SELECT crisis_type, severity, district, status, reported_at
         FROM crisis_reports WHERE reporter_phone = ?
         ORDER BY reported_at DESC LIMIT 5"
    );
    $stmt->bind_param('s', $user_phone);
    $stmt->execute();
    $my_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 3. Count all reports in user's district
$report_count = 0;
if ($user_district) {
    $r = $conn->prepare("SELECT COUNT(*) as total FROM crisis_reports WHERE district = ?");
    $r->bind_param('s', $user_district);
    $r->execute();
    $report_count = $r->get_result()->fetch_assoc()['total'] ?? 0;
    $r->close();
}

// 4. Count saved resources
$s = $conn->prepare("SELECT COUNT(*) as total FROM user_resources WHERE user_id = ? AND action = 'saved'");
$s->bind_param('i', $user_id);
$s->execute();
$saved_count = $s->get_result()->fetch_assoc()['total'] ?? 0;
$s->close();

// 5. Active severe alerts in user's district
$district_alerts = [];
if ($user_district) {
    $a = $conn->prepare(
        "SELECT crisis_type, severity, reported_at FROM crisis_reports
         WHERE district = ? AND severity = 'high' AND status != 'resolved'
         ORDER BY reported_at DESC LIMIT 3"
    );
    $a->bind_param('s', $user_district);
    $a->execute();
    $district_alerts = $a->get_result()->fetch_all(MYSQLI_ASSOC);
    $a->close();
}

$conn->close();

function severityBadge($s) {
    $map = ['high'=>'#e63946','medium'=>'#e9a81d','low'=>'#2d6a4f'];
    $c = $map[$s] ?? '#aaa';
    return "<span style='background:{$c};color:#fff;padding:2px 10px;border-radius:50px;font-size:0.75rem;font-weight:700;text-transform:uppercase;'>{$s}</span>";
}
function statusBadge($s) {
    $map = ['pending'=>'#e9a81d','verified'=>'#1a6fa3','resolved'=>'#2d6a4f','dismissed'=>'#aaa'];
    $c = $map[$s] ?? '#aaa';
    return "<span style='background:{$c};color:#fff;padding:2px 10px;border-radius:50px;font-size:0.75rem;font-weight:700;text-transform:uppercase;'>{$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AgroSecure – Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    .dash-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:36px; }
    .dash-stat { background:var(--white); border-radius:var(--radius); padding:24px 28px; box-shadow:var(--shadow-sm); border:1px solid rgba(0,0,0,0.05); }
    .dash-stat .icon { font-size:1.8rem; margin-bottom:8px; }
    .dash-stat .num { font-family:var(--font-display); font-size:2.4rem; font-weight:900; color:var(--green-dark); line-height:1; }
    .dash-stat .label { font-size:0.83rem; color:var(--text-light); margin-top:4px; }
    .dash-card { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-sm); border:1px solid rgba(0,0,0,0.05); margin-bottom:28px; }
    .dash-card-header { padding:18px 24px; border-bottom:1px solid rgba(0,0,0,0.06); font-family:var(--font-display); font-size:1.05rem; color:var(--green-dark); font-weight:700; }
    .dash-card-body { padding:20px 24px; }
    .report-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid rgba(0,0,0,0.05); font-size:0.88rem; flex-wrap:wrap; gap:8px; }
    .report-row:last-child { border-bottom:none; }
    .alert-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
    .alert-item:last-child { border-bottom:none; }
    .alert-dot { width:10px; height:10px; border-radius:50%; background:#e63946; margin-top:5px; flex-shrink:0; }
    .empty-msg { text-align:center; padding:28px; color:var(--text-light); font-size:0.9rem; }
    .welcome-banner { background:linear-gradient(135deg,var(--green-dark),var(--green-mid)); color:white; border-radius:var(--radius); padding:32px 36px; margin-bottom:32px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
    .welcome-banner h2 { font-family:var(--font-display); font-size:1.6rem; margin-bottom:4px; }
    .welcome-banner p { opacity:0.8; font-size:0.9rem; }
    .btn-light { background:rgba(255,255,255,0.15); color:white; border:1.5px solid rgba(255,255,255,0.4); padding:10px 22px; border-radius:var(--radius-sm); font-family:var(--font-body); font-weight:600; font-size:0.88rem; cursor:pointer; text-decoration:none; transition:0.2s; display:inline-block; }
    .btn-light:hover { background:rgba(255,255,255,0.25); }
  </style>
</head>
<body>

  <nav class="navbar" id="navbar">
    <div class="nav-logo">
      <span class="logo-icon">🌾</span>
      <span class="logo-text">AgroSecure</span>
    </div>
    <ul class="nav-links" id="navLinks">
      <li><a href="dashboard.php" class="active">Dashboard</a></li>
      <li><a href="report.html">Report Crisis</a></li>
      <li><a href="resources.html">Resources</a></li>
      <li><a href="contact.html">Contact</a></li>
      <li><a href="../php/connect_handler.php?action=logout" class="btn-nav">Logout</a></li>
    </ul>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </nav>

  <main class="page-content" style="margin-top:100px;">

    <div class="welcome-banner">
      <div>
        <h2>Welcome back, <?php echo $user_name; ?>! 👋</h2>
        <p>District: <?php echo $user_district ?: 'Not set'; ?> &nbsp;|&nbsp; Last login: <?php echo date('d M Y, H:i', $_SESSION['last_login'] ?? time()); ?></p>
      </div>
      <a href="report.html" class="btn-light">+ Report a Crisis</a>
    </div>

    <!-- STATS -->
    <div class="dash-grid">
      <div class="dash-stat">
        <div class="icon">🚨</div>
        <div class="num"><?php echo count($my_reports); ?></div>
        <div class="label">Your Crisis Reports</div>
      </div>
      <div class="dash-stat">
        <div class="icon">📍</div>
        <div class="num"><?php echo $report_count; ?></div>
        <div class="label">Reports in Your District</div>
      </div>
      <div class="dash-stat">
        <div class="icon">💾</div>
        <div class="num"><?php echo $saved_count; ?></div>
        <div class="label">Saved Resources</div>
      </div>
      <div class="dash-stat">
        <div class="icon">⚠️</div>
        <div class="num"><?php echo count($district_alerts); ?></div>
        <div class="label">Active Alerts Nearby</div>
      </div>
    </div>

    <!-- MY REPORTS -->
    <div class="dash-card">
      <div class="dash-card-header">🚨 Your Recent Crisis Reports</div>
      <div class="dash-card-body">
        <?php if (empty($my_reports)): ?>
          <div class="empty-msg">
            You haven't submitted any crisis reports yet.<br>
            <a href="report.html" class="feat-link" style="display:inline-block;margin-top:10px;">Submit your first report →</a>
          </div>
        <?php else: ?>
          <?php foreach ($my_reports as $rep): ?>
            <div class="report-row">
              <div>
                <strong><?php echo htmlspecialchars($rep['crisis_type']); ?></strong>
                <div style="color:var(--text-light);font-size:0.8rem;margin-top:2px;">
                  <?php echo htmlspecialchars($rep['district']); ?> · <?php echo date('d M Y', strtotime($rep['reported_at'])); ?>
                </div>
              </div>
              <div style="display:flex;gap:8px;align-items:center;">
                <?php echo severityBadge($rep['severity']); ?>
                <?php echo statusBadge($rep['status']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- DISTRICT ALERTS -->
    <div class="dash-card">
      <div class="dash-card-header">⚠️ Active Alerts in <?php echo $user_district ?: 'Your District'; ?></div>
      <div class="dash-card-body">
        <?php if (empty($district_alerts)): ?>
          <div class="empty-msg">No active severe alerts in your district. Stay safe! ✅</div>
        <?php else: ?>
          <?php foreach ($district_alerts as $alert): ?>
            <div class="alert-item">
              <div class="alert-dot"></div>
              <div>
                <strong style="font-size:0.9rem;"><?php echo htmlspecialchars($alert['crisis_type']); ?></strong>
                <div style="color:var(--text-light);font-size:0.8rem;margin-top:2px;">
                  Reported <?php echo date('d M Y', strtotime($alert['reported_at'])); ?>
                  &nbsp;· <?php echo severityBadge($alert['severity']); ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="features-grid">
      <div class="feature-card">
        <h3>📚 Resources</h3>
        <p>Access crop guides, irrigation tips, nutrition guides and market directories.</p>
        <a href="resources.html" class="feat-link">Browse resources →</a>
      </div>
      <div class="feature-card">
        <h3>🚨 Report Crisis</h3>
        <p>Submit a new food security or agricultural crisis report for your area.</p>
        <a href="report.html" class="feat-link">Submit a report →</a>
      </div>
      <div class="feature-card">
        <h3>📞 Contact Us</h3>
        <p>Reach out to our team for support, partnerships or technical help.</p>
        <a href="contact.html" class="feat-link">Get in touch →</a>
      </div>
    </div>

  </main>

  <script src="../js/main.js"></script>
  <script>
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') === 'login_success') {
      const note = document.createElement('div');
      note.style.cssText = 'position:fixed;top:90px;left:50%;transform:translateX(-50%);padding:14px 28px;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:10px;font-weight:600;font-size:0.92rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.12);';
      note.textContent = '✅ Login successful! Welcome back, <?php echo $user_name; ?>.';
      document.body.appendChild(note);
      setTimeout(() => note.remove(), 4000);
      window.history.replaceState({}, '', window.location.pathname);
    }
  </script>
</body>
</html>