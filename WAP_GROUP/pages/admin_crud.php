<?php
/**
 * AgroSecure – admin_crud.php
 * Admin CRUD Manager — loads real data from MySQL, all operations
 * talk to admin_handler.php via fetch().
 * SDG 2: Zero Hunger | MUBS Group Project
 */
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.html?status=error&message=" . urlencode("Access denied. Admin login required."));
    exit;
}

require_once '../php/db_connect.php';
$conn = getDBConnection();

// ── Load all data from DB ──────────────────────────────────────────────────
$result = $conn->query("SELECT id, first_name, last_name, email, phone, district, role, is_active, DATE(created_at) AS created_at FROM users ORDER BY created_at DESC");
if (!$result) die("Query failed: " . $conn->error);
$db_users = $result->fetch_all(MYSQLI_ASSOC);

$result2 = $conn->query("SELECT id, reporter_name, reporter_phone, district, village, crisis_type, severity, households_affected, description, email, status, DATE(reported_at) AS reported_at FROM crisis_reports ORDER BY reported_at DESC");
if (!$result2) die("Query failed: " . $conn->error);
$db_reports = $result2->fetch_all(MYSQLI_ASSOC);

$result3 = $conn->query("SELECT id, first_name, last_name, email, phone, subject, message, read_status, DATE(submitted_at) AS submitted_at FROM contact_messages ORDER BY submitted_at DESC");
if (!$result3) die("Query failed: " . $conn->error);
$db_contacts = $result3->fetch_all(MYSQLI_ASSOC);

$conn->close();

$admin_name  = htmlspecialchars($_SESSION['user_full_name'] ?? 'Admin');
$admin_id    = (int)$_SESSION['user_id'];

// Compute safe next IDs
$max_uid = $db_users    ? max(array_column($db_users,    'id')) + 1 : 1;
$max_rid = $db_reports  ? max(array_column($db_reports,  'id')) + 1 : 1;
$max_cid = $db_contacts ? max(array_column($db_contacts, 'id')) + 1 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AgroSecure Admin – CRUD Manager</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --green-dark: #1a3d2b; --green-mid: #2d6a4f; --green-light: #52b788;
      --green-pale: #d8f3dc; --gold: #e9a81d; --cream: #fdf6ec;
      --white: #ffffff; --text-dark: #1a1a1a; --text-mid: #4a4a4a;
      --text-light: #7a7a7a; --red: #c0392b; --red-pale: #fdecea;
      --blue: #1a6fa3; --blue-pale: #e8f4fc;
      --shadow-sm: 0 2px 12px rgba(0,0,0,0.08);
      --shadow-md: 0 8px 32px rgba(0,0,0,0.12);
      --radius: 16px; --radius-sm: 8px;
      --font-display: 'Playfair Display', Georgia, serif;
      --font-body: 'DM Sans', sans-serif;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: var(--font-body); background: #f2f6f3; color: var(--text-dark); min-height: 100vh; }

    /* ── SIDEBAR ── */
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
      width: 240px; background: var(--green-dark); color: var(--white);
      display: flex; flex-direction: column; position: fixed;
      top: 0; bottom: 0; left: 0; z-index: 100; padding-bottom: 24px;
    }
    .sidebar-logo {
      padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
      display: flex; align-items: center; gap: 10px;
    }
    .sidebar-logo span:first-child { font-size: 1.6rem; }
    .sidebar-logo .brand { font-family: var(--font-display); font-size: 1.1rem; font-weight: 700; }
    .sidebar-logo .badge {
      font-size: 0.65rem; background: var(--gold); color: var(--green-dark);
      padding: 2px 7px; border-radius: 50px; font-weight: 700;
      display: block; margin-top: 2px; width: fit-content;
    }
    .sidebar-section {
      padding: 16px 12px 4px; font-size: 0.68rem; letter-spacing: 0.1em;
      text-transform: uppercase; color: rgba(255,255,255,0.4); font-weight: 600;
    }
    .sidebar-nav { flex: 1; padding: 0 12px; }
    .sidebar-nav a {
      display: flex; align-items: center; gap: 10px; padding: 10px 12px;
      border-radius: var(--radius-sm); color: rgba(255,255,255,0.75);
      font-size: 0.88rem; font-weight: 500; text-decoration: none;
      transition: background 0.2s, color 0.2s; margin-bottom: 2px;
    }
    .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.12); color: var(--white); }
    .sidebar-nav a.active { background: rgba(82,183,136,0.25); color: var(--green-light); }
    .sidebar-icon { font-size: 1rem; width: 20px; text-align: center; }
    .sidebar-footer { padding: 16px 20px 0; border-top: 1px solid rgba(255,255,255,0.1); }
    .sidebar-footer a {
      display: block; padding: 9px 12px; border-radius: var(--radius-sm);
      color: rgba(255,255,255,0.6); font-size: 0.85rem; text-decoration: none; transition: 0.2s;
    }
    .sidebar-footer a:hover { color: #ff7675; background: rgba(255,100,100,0.1); }

    /* ── MAIN ── */
    .main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; }
    .topbar {
      background: var(--white); border-bottom: 1px solid rgba(0,0,0,0.07);
      padding: 0 32px; height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50; box-shadow: var(--shadow-sm);
    }
    .topbar-title { font-family: var(--font-display); font-size: 1.3rem; color: var(--green-dark); font-weight: 700; }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-user {
      display: flex; align-items: center; gap: 10px; background: var(--green-pale);
      padding: 6px 14px; border-radius: 50px; font-size: 0.83rem;
      font-weight: 600; color: var(--green-dark);
    }
    .content { padding: 32px; }

    /* ── TABS / PANELS ── */
    .panel { display: none; }
    .panel.active { display: block; }

    /* ── CRUD CARD ── */
    .crud-card {
      background: var(--white); border-radius: var(--radius);
      box-shadow: var(--shadow-sm); border: 1px solid rgba(0,0,0,0.05); margin-bottom: 28px;
    }
    .crud-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 20px 24px; border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .crud-card-title {
      font-family: var(--font-display); font-size: 1.1rem;
      color: var(--green-dark); font-weight: 700; display: flex; align-items: center; gap: 8px;
    }
    .crud-card-body { padding: 24px; }

    /* ── FORM ── */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label {
      font-size: 0.82rem; font-weight: 600; color: var(--text-mid);
      text-transform: uppercase; letter-spacing: 0.04em;
    }
    .form-group input, .form-group select, .form-group textarea {
      padding: 10px 14px; border: 1.5px solid rgba(0,0,0,0.12);
      border-radius: var(--radius-sm); font-family: var(--font-body);
      font-size: 0.9rem; color: var(--text-dark); background: #fafafa;
      transition: border 0.2s, box-shadow 0.2s; outline: none;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      border-color: var(--green-mid); box-shadow: 0 0 0 3px rgba(45,106,79,0.12); background: var(--white);
    }
    .form-group textarea { resize: vertical; min-height: 80px; }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex; align-items: center; gap: 7px; padding: 10px 22px;
      border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 0.88rem;
      font-weight: 600; cursor: pointer; border: none; transition: 0.2s;
    }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .btn-green { background: var(--green-mid); color: var(--white); }
    .btn-green:hover:not(:disabled) { background: var(--green-dark); transform: translateY(-1px); }
    .btn-red { background: var(--red); color: var(--white); }
    .btn-red:hover:not(:disabled) { background: #a93226; transform: translateY(-1px); }
    .btn-blue { background: var(--blue); color: var(--white); }
    .btn-blue:hover:not(:disabled) { background: #155e8b; transform: translateY(-1px); }
    .btn-outline { background: transparent; border: 1.5px solid rgba(0,0,0,0.15); color: var(--text-mid); }
    .btn-outline:hover { background: #f5f5f5; }
    .btn-gold { background: var(--gold); color: var(--green-dark); }
    .btn-gold:hover { background: #d4960f; }
    .btn-sm { padding: 6px 14px; font-size: 0.78rem; }

    /* ── TABLE ── */
    .table-wrap { overflow-x: auto; border-radius: var(--radius-sm); }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    thead th {
      background: var(--green-dark); color: var(--white); padding: 12px 16px;
      text-align: left; font-size: 0.78rem; text-transform: uppercase;
      letter-spacing: 0.05em; font-weight: 600;
    }
    thead th:first-child { border-radius: 8px 0 0 0; }
    thead th:last-child  { border-radius: 0 8px 0 0; }
    tbody tr { border-bottom: 1px solid rgba(0,0,0,0.05); transition: background 0.15s; }
    tbody tr:hover { background: #f7fdf9; }
    tbody td { padding: 12px 16px; color: var(--text-mid); vertical-align: middle; }
    tbody td:first-child { color: var(--text-dark); font-weight: 500; }

    .badge {
      display: inline-block; padding: 3px 10px; border-radius: 50px;
      font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    }
    .badge-green  { background: var(--green-pale); color: var(--green-dark); }
    .badge-red    { background: var(--red-pale);   color: var(--red); }
    .badge-gold   { background: #fff3cc;            color: #7a5a00; }
    .badge-blue   { background: var(--blue-pale);  color: var(--blue); }
    .badge-grey   { background: #eee;              color: #555; }
    .action-btns  { display: flex; gap: 6px; }

    /* ── TOAST ── */
    #toast {
      position: fixed; top: 80px; right: 24px; z-index: 9999;
      padding: 14px 22px; border-radius: var(--radius-sm); font-size: 0.88rem;
      font-weight: 600; box-shadow: var(--shadow-md);
      transform: translateX(120%); transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
      max-width: 360px;
    }
    #toast.show { transform: translateX(0); }
    #toast.success { background: var(--green-pale); color: var(--green-dark); border-left: 4px solid var(--green-mid); }
    #toast.error   { background: var(--red-pale);   color: var(--red);        border-left: 4px solid var(--red); }
    #toast.info    { background: var(--blue-pale);  color: var(--blue);       border-left: 4px solid var(--blue); }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 500;
      background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: var(--white); border-radius: var(--radius);
      padding: 36px 32px; width: 90%; max-width: 580px;
      box-shadow: var(--shadow-md); animation: scalePop 0.25s ease;
      max-height: 90vh; overflow-y: auto;
    }
    @keyframes scalePop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .modal h3 { font-family: var(--font-display); color: var(--green-dark); font-size: 1.2rem; margin-bottom: 6px; }
    .modal p.sub { color: var(--text-light); font-size: 0.87rem; margin-bottom: 24px; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; }

    /* ── CONFIRM ── */
    .confirm-overlay {
      display: none; position: fixed; inset: 0; z-index: 600;
      background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
    }
    .confirm-overlay.open { display: flex; }
    .confirm-box {
      background: var(--white); border-radius: var(--radius);
      padding: 32px; max-width: 400px; width: 90%;
      text-align: center; box-shadow: var(--shadow-md); animation: scalePop 0.2s ease;
    }
    .confirm-icon { font-size: 3rem; margin-bottom: 12px; }
    .confirm-box h3 { font-family: var(--font-display); color: var(--red); margin-bottom: 8px; }
    .confirm-box p  { color: var(--text-mid); font-size: 0.88rem; margin-bottom: 24px; }
    .confirm-btns   { display: flex; gap: 12px; justify-content: center; }

    /* ── SEARCH / TOOLBAR ── */
    .table-toolbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 24px; border-bottom: 1px solid rgba(0,0,0,0.05);
      flex-wrap: wrap; gap: 12px;
    }
    .search-wrap { position: relative; }
    .search-wrap input {
      padding: 8px 14px 8px 36px; border: 1.5px solid rgba(0,0,0,0.1);
      border-radius: var(--radius-sm); font-family: var(--font-body);
      font-size: 0.85rem; outline: none; transition: 0.2s; width: 220px; background: #fafafa;
    }
    .search-wrap input:focus { border-color: var(--green-mid); background: var(--white); }
    .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-light); font-size: 0.9rem; pointer-events: none; }

    /* ── STATS ── */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .stat-card {
      background: var(--white); border-radius: var(--radius); padding: 20px 22px;
      box-shadow: var(--shadow-sm); border: 1px solid rgba(0,0,0,0.05);
    }
    .stat-card .num { font-family: var(--font-display); font-size: 2rem; font-weight: 900; color: var(--green-dark); line-height: 1; }
    .stat-card .label { font-size: 0.8rem; color: var(--text-light); margin-top: 4px; }
    .stat-card .icon  { font-size: 1.5rem; margin-bottom: 8px; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align: center; padding: 48px 24px; color: var(--text-light); }
    .empty-state .e-icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-state p { font-size: 0.9rem; }

    /* ── LOADING SPINNER ── */
    .spinner {
      display: inline-block; width: 14px; height: 14px;
      border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff;
      border-radius: 50%; animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── RESPONSIVE ── */
    @media(max-width:900px) {
      .sidebar { transform: translateX(-100%); transition: 0.3s; }
      .sidebar.open { transform: translateX(0); }
      .main { margin-left: 0; }
      .form-grid { grid-template-columns: 1fr; }
      .form-group.full { grid-column: 1; }
    }
  </style>
</head>
<body>

<!-- TOAST -->
<div id="toast"></div>

<!-- DELETE CONFIRM -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon">🗑️</div>
    <h3>Confirm Delete</h3>
    <p id="confirmMsg">Are you sure? This cannot be undone.</p>
    <div class="confirm-btns">
      <button class="btn btn-outline" onclick="closeConfirm()">Cancel</button>
      <button class="btn btn-red" id="confirmYes">Yes, Delete</button>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="updateModal">
  <div class="modal">
    <h3 id="modalTitle">Edit Record</h3>
    <p class="sub" id="modalSub">Update the fields below and save.</p>
    <div id="modalBody"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
      <button class="btn btn-blue" id="modalSaveBtn" onclick="saveUpdate()">💾 Save Changes</button>
    </div>
  </div>
</div>

<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <span>🌾</span>
      <div>
        <div class="brand">AgroSecure</div>
        <span class="badge">ADMIN</span>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Management</div>
      <a href="#" class="active" onclick="switchPanel('users', this); return false;">
        <span class="sidebar-icon">👤</span> User Management
      </a>
      <a href="#" onclick="switchPanel('reports', this); return false;">
        <span class="sidebar-icon">🚨</span> Crisis Reports
      </a>
      <a href="#" onclick="switchPanel('contacts', this); return false;">
        <span class="sidebar-icon">✉️</span> Contact Messages
      </a>
      <div class="sidebar-section" style="margin-top:8px;">Navigation</div>
      <a href="../index.html">
        <span class="sidebar-icon">🏠</span> Back to Site
      </a>
      <a href="dashboard.php">
        <span class="sidebar-icon">📊</span> Dashboard
      </a>
    </div>
    <div class="sidebar-footer">
      <a href="../php/connect_handler.php?action=logout">🔒 Logout</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="topbar-title" id="pageTitle">👤 User Management</div>
      <div class="topbar-right">
        <div class="topbar-user">🛡️ <?php echo $admin_name; ?></div>
      </div>
    </div>

    <div class="content">

      <!-- ══════════════════════
           PANEL: USERS
      ══════════════════════ -->
      <div class="panel active" id="panel-users">
        <div class="stats-row">
          <div class="stat-card"><div class="icon">👥</div><div class="num" id="stat-totalUsers">0</div><div class="label">Total Users</div></div>
          <div class="stat-card"><div class="icon">🌾</div><div class="num" id="stat-farmers">0</div><div class="label">Farmers</div></div>
          <div class="stat-card"><div class="icon">✅</div><div class="num" id="stat-activeUsers">0</div><div class="label">Active Users</div></div>
          <div class="stat-card"><div class="icon">🏢</div><div class="num" id="stat-orgs">0</div><div class="label">Organizations</div></div>
        </div>

        <!-- INSERT USER FORM -->
        <div class="crud-card">
          <div class="crud-card-header">
            <div class="crud-card-title">➕ Insert New User</div>
            <button class="btn btn-outline btn-sm" onclick="toggleSection('insertUserForm')">Toggle Form</button>
          </div>
          <div class="crud-card-body" id="insertUserForm">
            <form id="insertUserFormEl">
              <div class="form-grid">
                <div class="form-group"><label>First Name *</label><input type="text" id="u_first" placeholder="e.g. Okello" required /></div>
                <div class="form-group"><label>Last Name *</label><input type="text" id="u_last" placeholder="e.g. James" required /></div>
                <div class="form-group"><label>Email Address *</label><input type="email" id="u_email" placeholder="okello@example.com" required /></div>
                <div class="form-group"><label>Phone Number *</label><input type="tel" id="u_phone" placeholder="+256 7XX XXX XXX" required /></div>
                <div class="form-group">
                  <label>District *</label>
                  <select id="u_district" required>
                    <option value="">-- Select District --</option>
                    <option>Kampala</option><option>Wakiso</option><option>Mukono</option>
                    <option>Jinja</option><option>Mbale</option><option>Gulu</option>
                    <option>Lira</option><option>Arua</option><option>Mbarara</option>
                    <option>Kabale</option><option>Fort Portal</option><option>Masaka</option>
                    <option>Soroti</option><option>Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Role *</label>
                  <select id="u_role" required>
                    <option value="">-- Select Role --</option>
                    <option>Smallholder Farmer</option><option>Commercial Farmer</option>
                    <option>Agricultural Extension Worker</option><option>NGO / Aid Organization</option>
                    <option>Researcher / Student</option><option>Buyer / Trader</option><option>Other</option>
                  </select>
                </div>
                <div class="form-group"><label>Password *</label><input type="password" id="u_pass" placeholder="Min. 8 characters" minlength="8" required /></div>
                <div class="form-group">
                  <label>Status</label>
                  <select id="u_status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
              </div>
              <div style="margin-top:20px;display:flex;gap:10px;">
                <button type="button" class="btn btn-green" onclick="insertUser()">✅ Insert User</button>
                <button type="reset" class="btn btn-outline">↺ Clear</button>
              </div>
            </form>
          </div>
        </div>

        <!-- USERS TABLE -->
        <div class="crud-card">
          <div class="table-toolbar">
            <div class="crud-card-title">👥 All Users</div>
            <div class="search-wrap">
              <span class="search-icon">🔍</span>
              <input type="text" placeholder="Search users…" oninput="searchTable('usersTable', this.value)" />
            </div>
          </div>
          <div class="table-wrap">
            <table id="usersTable">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Email</th><th>Phone</th>
                  <th>District</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr>
              </thead>
              <tbody id="usersTbody"></tbody>
            </table>
            <div class="empty-state" id="usersEmpty" style="display:none;">
              <div class="e-icon">👥</div><p>No users found.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════
           PANEL: REPORTS
      ══════════════════════ -->
      <div class="panel" id="panel-reports">
        <div class="stats-row">
          <div class="stat-card"><div class="icon">🚨</div><div class="num" id="stat-totalReports">0</div><div class="label">Total Reports</div></div>
          <div class="stat-card"><div class="icon">🔴</div><div class="num" id="stat-severeReports">0</div><div class="label">Severe / Emergency</div></div>
          <div class="stat-card"><div class="icon">⏳</div><div class="num" id="stat-pendingReports">0</div><div class="label">Pending Review</div></div>
          <div class="stat-card"><div class="icon">✔️</div><div class="num" id="stat-resolvedReports">0</div><div class="label">Resolved</div></div>
        </div>

        <!-- INSERT REPORT FORM -->
        <div class="crud-card">
          <div class="crud-card-header">
            <div class="crud-card-title">➕ Insert Crisis Report</div>
            <button class="btn btn-outline btn-sm" onclick="toggleSection('insertReportForm')">Toggle Form</button>
          </div>
          <div class="crud-card-body" id="insertReportForm">
            <form id="insertReportFormEl">
              <div class="form-grid">
                <div class="form-group"><label>Reporter Name *</label><input type="text" id="r_name" placeholder="e.g. Nakato Rose" required /></div>
                <div class="form-group"><label>Phone Number *</label><input type="tel" id="r_phone" placeholder="+256 7XX XXX XXX" required /></div>
                <div class="form-group">
                  <label>District *</label>
                  <select id="r_district" required>
                    <option value="">Select District</option>
                    <option>Kampala</option><option>Wakiso</option><option>Mukono</option>
                    <option>Jinja</option><option>Mbale</option><option>Gulu</option>
                    <option>Lira</option><option>Arua</option><option>Mbarara</option>
                    <option>Kabale</option><option>Fort Portal</option><option>Masaka</option>
                    <option>Soroti</option><option>Moroto</option><option>Other</option>
                  </select>
                </div>
                <div class="form-group"><label>Village / Sub-County</label><input type="text" id="r_village" placeholder="e.g. Bweyogerere" /></div>
                <div class="form-group">
                  <label>Crisis Type *</label>
                  <select id="r_type" required>
                    <option value="">Select Crisis Type</option>
                    <option>Drought / Dry Spell</option><option>Flooding / Waterlogging</option>
                    <option>Pest Outbreak (e.g. Armyworm)</option><option>Plant Disease</option>
                    <option>Food Supply Shortage</option><option>Livestock Disease</option>
                    <option>Post-Harvest Loss</option><option>Market Price Spike</option><option>Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Severity *</label>
                  <select id="r_severity" required>
                    <option value="">Select Severity</option>
                    <option value="low">Low</option>
                    <option value="medium">Moderate</option>
                    <option value="high">Severe / Emergency</option>
                  </select>
                </div>
                <div class="form-group"><label>Households Affected</label><input type="number" id="r_households" placeholder="e.g. 150" min="0" /></div>
                <div class="form-group">
                  <label>Status</label>
                  <select id="r_status">
                    <option value="pending">Pending</option><option value="verified">Verified</option>
                    <option value="resolved">Resolved</option><option value="dismissed">Dismissed</option>
                  </select>
                </div>
                <div class="form-group full"><label>Description *</label><textarea id="r_desc" placeholder="Describe the crisis situation…" required></textarea></div>
                <div class="form-group full"><label>Reporter Email (optional)</label><input type="email" id="r_email" placeholder="reporter@email.com" /></div>
              </div>
              <div style="margin-top:20px;display:flex;gap:10px;">
                <button type="button" class="btn btn-red" onclick="insertReport()">🚨 Insert Report</button>
                <button type="reset" class="btn btn-outline">↺ Clear</button>
              </div>
            </form>
          </div>
        </div>

        <!-- REPORTS TABLE -->
        <div class="crud-card">
          <div class="table-toolbar">
            <div class="crud-card-title">🚨 All Crisis Reports</div>
            <div class="search-wrap">
              <span class="search-icon">🔍</span>
              <input type="text" placeholder="Search reports…" oninput="searchTable('reportsTable', this.value)" />
            </div>
          </div>
          <div class="table-wrap">
            <table id="reportsTable">
              <thead>
                <tr>
                  <th>ID</th><th>Reporter</th><th>District</th><th>Crisis Type</th>
                  <th>Severity</th><th>Households</th><th>Status</th><th>Date</th><th>Actions</th>
                </tr>
              </thead>
              <tbody id="reportsTbody"></tbody>
            </table>
            <div class="empty-state" id="reportsEmpty" style="display:none;">
              <div class="e-icon">🚨</div><p>No reports found.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════
           PANEL: CONTACTS
      ══════════════════════ -->
      <div class="panel" id="panel-contacts">
        <div class="stats-row">
          <div class="stat-card"><div class="icon">✉️</div><div class="num" id="stat-totalContacts">0</div><div class="label">Total Messages</div></div>
          <div class="stat-card"><div class="icon">📬</div><div class="num" id="stat-unreadContacts">0</div><div class="label">Unread</div></div>
          <div class="stat-card"><div class="icon">🤝</div><div class="num" id="stat-partnerContacts">0</div><div class="label">Partnership Inquiries</div></div>
        </div>

        <!-- INSERT CONTACT FORM -->
        <div class="crud-card">
          <div class="crud-card-header">
            <div class="crud-card-title">➕ Insert Contact Message</div>
            <button class="btn btn-outline btn-sm" onclick="toggleSection('insertContactForm')">Toggle Form</button>
          </div>
          <div class="crud-card-body" id="insertContactForm">
            <form id="insertContactFormEl">
              <div class="form-grid">
                <div class="form-group"><label>First Name *</label><input type="text" id="c_first" placeholder="e.g. Amumpaire" required /></div>
                <div class="form-group"><label>Last Name *</label><input type="text" id="c_last" placeholder="e.g. Grace" required /></div>
                <div class="form-group"><label>Email *</label><input type="email" id="c_email" placeholder="grace@example.com" required /></div>
                <div class="form-group"><label>Phone</label><input type="tel" id="c_phone" placeholder="+256 7XX XXX XXX" /></div>
                <div class="form-group">
                  <label>Subject *</label>
                  <select id="c_subject" required>
                    <option value="">-- Select Topic --</option>
                    <option>General Inquiry</option><option>Partnership Opportunity</option>
                    <option>Farmer Registration Help</option><option>Technical Support</option>
                    <option>Donate / Sponsor</option><option>Media / Press</option><option>Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Read Status</label>
                  <select id="c_read"><option value="unread">Unread</option><option value="read">Read</option></select>
                </div>
                <div class="form-group full"><label>Message *</label><textarea id="c_message" placeholder="Enter the contact message…" required></textarea></div>
              </div>
              <div style="margin-top:20px;display:flex;gap:10px;">
                <button type="button" class="btn btn-blue" onclick="insertContact()">✉️ Insert Message</button>
                <button type="reset" class="btn btn-outline">↺ Clear</button>
              </div>
            </form>
          </div>
        </div>

        <!-- CONTACTS TABLE -->
        <div class="crud-card">
          <div class="table-toolbar">
            <div class="crud-card-title">✉️ All Messages</div>
            <div class="search-wrap">
              <span class="search-icon">🔍</span>
              <input type="text" placeholder="Search messages…" oninput="searchTable('contactsTable', this.value)" />
            </div>
          </div>
          <div class="table-wrap">
            <table id="contactsTable">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Email</th><th>Subject</th>
                  <th>Preview</th><th>Status</th><th>Received</th><th>Actions</th>
                </tr>
              </thead>
              <tbody id="contactsTbody"></tbody>
            </table>
            <div class="empty-state" id="contactsEmpty" style="display:none;">
              <div class="e-icon">✉️</div><p>No messages found.</p>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
/* ═══════════════════════════════════════════════════════════════════
   AgroSecure Admin CRUD Manager
   Real data injected from PHP. All mutations go to admin_handler.php
═══════════════════════════════════════════════════════════════════ */

// ── DATA (injected by PHP) ────────────────────────────────────────
let db = {
  users:    <?php echo json_encode(array_values($db_users),    JSON_HEX_TAG | JSON_HEX_APOS); ?>,
  reports:  <?php echo json_encode(array_values($db_reports),  JSON_HEX_TAG | JSON_HEX_APOS); ?>,
  contacts: <?php echo json_encode(array_values($db_contacts), JSON_HEX_TAG | JSON_HEX_APOS); ?>,
  nextUserId:    <?php echo $max_uid; ?>,
  nextReportId:  <?php echo $max_rid; ?>,
  nextContactId: <?php echo $max_cid; ?>,
};

const ADMIN_ID = <?php echo $admin_id; ?>;
const API      = '../php/admin_handler.php';

// ── UTILITIES ────────────────────────────────────────────────────
let pendingDeleteFn   = null;
let currentEditType   = null;
let currentEditId     = null;

function toast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = type + ' show';
  setTimeout(() => t.className = '', 3400);
}

function toggleSection(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? '' : 'none';
}

function searchTable(tableId, query) {
  const q = query.toLowerCase();
  document.querySelectorAll(`#${tableId} tbody tr`).forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function confirmDelete(msg, fn) {
  document.getElementById('confirmMsg').textContent = msg;
  pendingDeleteFn = fn;
  document.getElementById('confirmOverlay').classList.add('open');
}
function closeConfirm() {
  document.getElementById('confirmOverlay').classList.remove('open');
  pendingDeleteFn = null;
}
document.getElementById('confirmYes').onclick = () => {
  if (pendingDeleteFn) pendingDeleteFn();
  closeConfirm();
};

function openModal(title, sub, bodyHtml, editType, editId) {
  document.getElementById('modalTitle').textContent   = title;
  document.getElementById('modalSub').textContent     = sub;
  document.getElementById('modalBody').innerHTML      = bodyHtml;
  currentEditType = editType;
  currentEditId   = editId;
  document.getElementById('updateModal').classList.add('open');
}
function closeModal() {
  document.getElementById('updateModal').classList.remove('open');
  currentEditType = null;
  currentEditId   = null;
}

function setBtn(id, loading, label) {
  const b = document.getElementById(id);
  if (!b) return;
  b.disabled   = loading;
  b.innerHTML  = loading ? '<span class="spinner"></span> Saving…' : label;
}

function formatDate(d) { return d || '—'; }

function badgeSeverity(s) {
  if (s === 'high')   return `<span class="badge badge-red">🔴 Severe</span>`;
  if (s === 'medium') return `<span class="badge badge-gold">🟡 Moderate</span>`;
  return `<span class="badge badge-green">🟢 Low</span>`;
}
function badgeStatus(s) {
  const map = {
    pending:'badge-gold', verified:'badge-blue', resolved:'badge-green', dismissed:'badge-grey',
    active:'badge-green', inactive:'badge-red', read:'badge-grey', unread:'badge-blue'
  };
  return `<span class="badge ${map[s] || 'badge-grey'}">${s}</span>`;
}

// ── PANEL SWITCHING ──────────────────────────────────────────────
function switchPanel(name, el) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
  if (el) el.classList.add('active');
  const titles = { users:'👤 User Management', reports:'🚨 Crisis Reports', contacts:'✉️ Contact Messages' };
  document.getElementById('pageTitle').textContent = titles[name] || '';
  updateStats();
}

// ── STATS ────────────────────────────────────────────────────────
function updateStats() {
  document.getElementById('stat-totalUsers').textContent   = db.users.length;
  document.getElementById('stat-farmers').textContent      = db.users.filter(u => u.role && u.role.includes('Farmer')).length;
  document.getElementById('stat-activeUsers').textContent  = db.users.filter(u => u.is_active == 1).length;
  document.getElementById('stat-orgs').textContent         = db.users.filter(u => u.role && (u.role.includes('NGO') || u.role.includes('Organization'))).length;

  document.getElementById('stat-totalReports').textContent    = db.reports.length;
  document.getElementById('stat-severeReports').textContent   = db.reports.filter(r => r.severity === 'high').length;
  document.getElementById('stat-pendingReports').textContent  = db.reports.filter(r => r.status === 'pending').length;
  document.getElementById('stat-resolvedReports').textContent = db.reports.filter(r => r.status === 'resolved').length;

  document.getElementById('stat-totalContacts').textContent   = db.contacts.length;
  document.getElementById('stat-unreadContacts').textContent  = db.contacts.filter(c => c.read_status === 'unread').length;
  document.getElementById('stat-partnerContacts').textContent = db.contacts.filter(c => c.subject === 'Partnership Opportunity').length;
}

// ── GENERIC API CALL ─────────────────────────────────────────────
async function apiCall(action, data) {
  const body = new URLSearchParams({ ...data });
  const res  = await fetch(`${API}?action=${action}`, { method: 'POST', body });
  return res.json();
}

// ═════════════════════════════
// USERS CRUD
// ═════════════════════════════

function renderUsers() {
  const tbody = document.getElementById('usersTbody');
  const empty = document.getElementById('usersEmpty');
  if (!db.users.length) { tbody.innerHTML = ''; empty.style.display = ''; updateStats(); return; }
  empty.style.display = 'none';
  tbody.innerHTML = db.users.map(u => `
    <tr id="user-row-${u.id}">
      <td>#${u.id}</td>
      <td>${escHtml(u.first_name)} ${escHtml(u.last_name)}</td>
      <td>${escHtml(u.email)}</td>
      <td>${escHtml(u.phone)}</td>
      <td>${escHtml(u.district)}</td>
      <td style="font-size:0.82rem">${escHtml(u.role)}</td>
      <td>${badgeStatus(u.is_active == 1 ? 'active' : 'inactive')}</td>
      <td>${formatDate(u.created_at)}</td>
      <td>
        <div class="action-btns">
          <button class="btn btn-blue btn-sm" onclick="editUser(${u.id})">✏️ Edit</button>
          <button class="btn btn-red btn-sm" onclick="deleteUser(${u.id})"
            ${u.id == ADMIN_ID ? 'disabled title="Cannot delete your own account"' : ''}>🗑️ Del</button>
        </div>
      </td>
    </tr>
  `).join('');
  updateStats();
}

async function insertUser() {
  const first_name = document.getElementById('u_first').value.trim();
  const last_name  = document.getElementById('u_last').value.trim();
  const email      = document.getElementById('u_email').value.trim();
  const phone      = document.getElementById('u_phone').value.trim();
  const district   = document.getElementById('u_district').value;
  const role       = document.getElementById('u_role').value;
  const password   = document.getElementById('u_pass').value;
  const is_active  = document.getElementById('u_status').value;

  if (!first_name || !last_name || !email || !phone || !district || !role || !password) {
    toast('Please fill in all required fields.', 'error'); return;
  }

  const btn = document.querySelector('#insertUserFormEl .btn-green');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Inserting…';

  try {
    const data = await apiCall('insert_user', { first_name, last_name, email, phone, district, role, password, is_active });
    if (data.success) {
      db.users.unshift({ id: data.id, first_name, last_name, email, phone, district, role, is_active: parseInt(is_active), created_at: data.created_at });
      db.nextUserId = data.id + 1;
      renderUsers();
      document.getElementById('insertUserFormEl').reset();
      toast(data.message);
    } else {
      toast(data.message, 'error');
    }
  } catch(e) {
    toast('Network error. Please try again.', 'error');
  }
  btn.disabled = false; btn.innerHTML = '✅ Insert User';
}

function deleteUser(id) {
  if (id == ADMIN_ID) { toast('You cannot delete your own account.', 'error'); return; }
  const u = db.users.find(x => x.id == id);
  confirmDelete(`Delete user "${u.first_name} ${u.last_name}"? This cannot be undone.`, async () => {
    try {
      const data = await apiCall('delete_user', { id });
      if (data.success) {
        db.users = db.users.filter(x => x.id != id);
        renderUsers();
        toast(data.message, 'info');
      } else {
        toast(data.message, 'error');
      }
    } catch(e) {
      toast('Network error. Please try again.', 'error');
    }
  });
}

function editUser(id) {
  const u = db.users.find(x => x.id == id);
  const districts = ['Kampala','Wakiso','Mukono','Jinja','Mbale','Gulu','Lira','Arua','Mbarara','Kabale','Fort Portal','Masaka','Soroti','Other'];
  const roles     = ['Smallholder Farmer','Commercial Farmer','Agricultural Extension Worker','NGO / Aid Organization','Researcher / Student','Buyer / Trader','Other'];
  const html = `
    <div class="form-grid">
      <div class="form-group"><label>First Name</label><input id="eu_first" value="${escAttr(u.first_name)}" /></div>
      <div class="form-group"><label>Last Name</label><input id="eu_last" value="${escAttr(u.last_name)}" /></div>
      <div class="form-group"><label>Email</label><input type="email" id="eu_email" value="${escAttr(u.email)}" /></div>
      <div class="form-group"><label>Phone</label><input id="eu_phone" value="${escAttr(u.phone)}" /></div>
      <div class="form-group"><label>District</label>
        <select id="eu_district">${districts.map(d=>`<option ${d===u.district?'selected':''}>${d}</option>`).join('')}</select>
      </div>
      <div class="form-group"><label>Role</label>
        <select id="eu_role">${roles.map(r=>`<option ${r===u.role?'selected':''}>${r}</option>`).join('')}</select>
      </div>
      <div class="form-group"><label>Status</label>
        <select id="eu_status">
          <option value="1" ${u.is_active==1?'selected':''}>Active</option>
          <option value="0" ${u.is_active==0?'selected':''}>Inactive</option>
        </select>
      </div>
    </div>`;
  openModal('✏️ Edit User', `Updating: ${u.first_name} ${u.last_name}`, html, 'user', id);
}

// ═════════════════════════════
// REPORTS CRUD
// ═════════════════════════════

function renderReports() {
  const tbody = document.getElementById('reportsTbody');
  const empty = document.getElementById('reportsEmpty');
  if (!db.reports.length) { tbody.innerHTML = ''; empty.style.display = ''; updateStats(); return; }
  empty.style.display = 'none';
  tbody.innerHTML = db.reports.map(r => `
    <tr id="report-row-${r.id}">
      <td>#${r.id}</td>
      <td>${escHtml(r.reporter_name)}<br><small style="color:var(--text-light)">${escHtml(r.reporter_phone)}</small></td>
      <td>${escHtml(r.district)}${r.village ? `<br><small style="color:var(--text-light)">${escHtml(r.village)}</small>` : ''}</td>
      <td style="font-size:0.82rem">${escHtml(r.crisis_type)}</td>
      <td>${badgeSeverity(r.severity)}</td>
      <td>${r.households_affected || '—'}</td>
      <td>${badgeStatus(r.status)}</td>
      <td>${formatDate(r.reported_at)}</td>
      <td>
        <div class="action-btns">
          <button class="btn btn-blue btn-sm" onclick="editReport(${r.id})">✏️ Edit</button>
          <button class="btn btn-red btn-sm" onclick="deleteReport(${r.id})">🗑️ Del</button>
        </div>
      </td>
    </tr>
  `).join('');
  updateStats();
}

async function insertReport() {
  const reporter_name       = document.getElementById('r_name').value.trim();
  const reporter_phone      = document.getElementById('r_phone').value.trim();
  const district            = document.getElementById('r_district').value;
  const village             = document.getElementById('r_village').value.trim();
  const crisis_type         = document.getElementById('r_type').value;
  const severity            = document.getElementById('r_severity').value;
  const households_affected = document.getElementById('r_households').value || 0;
  const description         = document.getElementById('r_desc').value.trim();
  const email               = document.getElementById('r_email').value.trim();
  const status              = document.getElementById('r_status').value;

  if (!reporter_name || !reporter_phone || !district || !crisis_type || !severity || !description) {
    toast('Please fill in all required fields.', 'error'); return;
  }

  const btn = document.querySelector('#insertReportFormEl .btn-red');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Inserting…';

  try {
    const data = await apiCall('insert_report', { reporter_name, reporter_phone, district, village, crisis_type, severity, households_affected, description, email, status });
    if (data.success) {
      db.reports.unshift({ id: data.id, reporter_name, reporter_phone, district, village, crisis_type, severity, households_affected: parseInt(households_affected), description, email, status, reported_at: data.reported_at });
      db.nextReportId = data.id + 1;
      renderReports();
      document.getElementById('insertReportFormEl').reset();
      toast(data.message);
    } else {
      toast(data.message, 'error');
    }
  } catch(e) {
    toast('Network error. Please try again.', 'error');
  }
  btn.disabled = false; btn.innerHTML = '🚨 Insert Report';
}

function deleteReport(id) {
  const r = db.reports.find(x => x.id == id);
  confirmDelete(`Delete report #${r.id} (${r.crisis_type} in ${r.district})?`, async () => {
    try {
      const data = await apiCall('delete_report', { id });
      if (data.success) {
        db.reports = db.reports.filter(x => x.id != id);
        renderReports();
        toast(data.message, 'info');
      } else {
        toast(data.message, 'error');
      }
    } catch(e) {
      toast('Network error. Please try again.', 'error');
    }
  });
}

function editReport(id) {
  const r = db.reports.find(x => x.id == id);
  const districts = ['Kampala','Wakiso','Mukono','Jinja','Mbale','Gulu','Lira','Arua','Mbarara','Kabale','Fort Portal','Masaka','Soroti','Moroto','Other'];
  const types     = ['Drought / Dry Spell','Flooding / Waterlogging','Pest Outbreak (e.g. Armyworm)','Plant Disease','Food Supply Shortage','Livestock Disease','Post-Harvest Loss','Market Price Spike','Other'];
  const html = `
    <div class="form-grid">
      <div class="form-group"><label>Reporter Name</label><input id="er_name" value="${escAttr(r.reporter_name)}" /></div>
      <div class="form-group"><label>Phone</label><input id="er_phone" value="${escAttr(r.reporter_phone)}" /></div>
      <div class="form-group"><label>District</label>
        <select id="er_district">${districts.map(d=>`<option ${d===r.district?'selected':''}>${d}</option>`).join('')}</select>
      </div>
      <div class="form-group"><label>Village</label><input id="er_village" value="${escAttr(r.village||'')}" /></div>
      <div class="form-group"><label>Crisis Type</label>
        <select id="er_type">${types.map(t=>`<option ${t===r.crisis_type?'selected':''}>${t}</option>`).join('')}</select>
      </div>
      <div class="form-group"><label>Severity</label>
        <select id="er_severity">
          <option value="low" ${r.severity==='low'?'selected':''}>Low</option>
          <option value="medium" ${r.severity==='medium'?'selected':''}>Moderate</option>
          <option value="high" ${r.severity==='high'?'selected':''}>Severe / Emergency</option>
        </select>
      </div>
      <div class="form-group"><label>Households</label><input type="number" id="er_households" value="${r.households_affected||0}" /></div>
      <div class="form-group"><label>Status</label>
        <select id="er_status">
          ${['pending','verified','resolved','dismissed'].map(s=>`<option value="${s}" ${s===r.status?'selected':''}>${s}</option>`).join('')}
        </select>
      </div>
      <div class="form-group full"><label>Description</label><textarea id="er_desc">${escHtml(r.description)}</textarea></div>
    </div>`;
  openModal('✏️ Edit Crisis Report', `Report #${r.id} — ${r.crisis_type} in ${r.district}`, html, 'report', id);
}

// ═════════════════════════════
// CONTACTS CRUD
// ═════════════════════════════

function renderContacts() {
  const tbody = document.getElementById('contactsTbody');
  const empty = document.getElementById('contactsEmpty');
  if (!db.contacts.length) { tbody.innerHTML = ''; empty.style.display = ''; updateStats(); return; }
  empty.style.display = 'none';
  tbody.innerHTML = db.contacts.map(c => `
    <tr id="contact-row-${c.id}">
      <td>#${c.id}</td>
      <td>${escHtml(c.first_name)} ${escHtml(c.last_name)}</td>
      <td>${escHtml(c.email)}</td>
      <td style="font-size:0.82rem">${escHtml(c.subject)}</td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;color:var(--text-mid)">
        ${escHtml((c.message||'').substring(0, 60))}…
      </td>
      <td>${badgeStatus(c.read_status)}</td>
      <td>${formatDate(c.submitted_at)}</td>
      <td>
        <div class="action-btns">
          <button class="btn btn-blue btn-sm" onclick="editContact(${c.id})">✏️ Edit</button>
          <button class="btn btn-red btn-sm" onclick="deleteContact(${c.id})">🗑️ Del</button>
        </div>
      </td>
    </tr>
  `).join('');
  updateStats();
}

async function insertContact() {
  const first_name  = document.getElementById('c_first').value.trim();
  const last_name   = document.getElementById('c_last').value.trim();
  const email       = document.getElementById('c_email').value.trim();
  const phone       = document.getElementById('c_phone').value.trim();
  const subject     = document.getElementById('c_subject').value;
  const message     = document.getElementById('c_message').value.trim();
  const read_status = document.getElementById('c_read').value;

  if (!first_name || !last_name || !email || !subject || !message) {
    toast('Please fill in all required fields.', 'error'); return;
  }

  const btn = document.querySelector('#insertContactFormEl .btn-blue');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Inserting…';

  try {
    const data = await apiCall('insert_contact', { first_name, last_name, email, phone, subject, message, read_status });
    if (data.success) {
      db.contacts.unshift({ id: data.id, first_name, last_name, email, phone, subject, message, read_status, submitted_at: data.submitted_at });
      db.nextContactId = data.id + 1;
      renderContacts();
      document.getElementById('insertContactFormEl').reset();
      toast(data.message);
    } else {
      toast(data.message, 'error');
    }
  } catch(e) {
    toast('Network error. Please try again.', 'error');
  }
  btn.disabled = false; btn.innerHTML = '✉️ Insert Message';
}

function deleteContact(id) {
  const c = db.contacts.find(x => x.id == id);
  confirmDelete(`Delete message from "${c.first_name} ${c.last_name}" (${c.subject})?`, async () => {
    try {
      const data = await apiCall('delete_contact', { id });
      if (data.success) {
        db.contacts = db.contacts.filter(x => x.id != id);
        renderContacts();
        toast(data.message, 'info');
      } else {
        toast(data.message, 'error');
      }
    } catch(e) {
      toast('Network error. Please try again.', 'error');
    }
  });
}

function editContact(id) {
  const c = db.contacts.find(x => x.id == id);
  const subjects = ['General Inquiry','Partnership Opportunity','Farmer Registration Help','Technical Support','Donate / Sponsor','Media / Press','Other'];
  const html = `
    <div class="form-grid">
      <div class="form-group"><label>First Name</label><input id="ec_first" value="${escAttr(c.first_name)}" /></div>
      <div class="form-group"><label>Last Name</label><input id="ec_last" value="${escAttr(c.last_name)}" /></div>
      <div class="form-group"><label>Email</label><input type="email" id="ec_email" value="${escAttr(c.email)}" /></div>
      <div class="form-group"><label>Phone</label><input id="ec_phone" value="${escAttr(c.phone||'')}" /></div>
      <div class="form-group"><label>Subject</label>
        <select id="ec_subject">${subjects.map(s=>`<option ${s===c.subject?'selected':''}>${s}</option>`).join('')}</select>
      </div>
      <div class="form-group"><label>Read Status</label>
        <select id="ec_read">
          <option value="unread" ${c.read_status==='unread'?'selected':''}>Unread</option>
          <option value="read"   ${c.read_status==='read'  ?'selected':''}>Read</option>
        </select>
      </div>
      <div class="form-group full"><label>Message</label><textarea id="ec_message">${escHtml(c.message)}</textarea></div>
    </div>`;
  openModal('✏️ Edit Message', `From: ${c.first_name} ${c.last_name} — ${c.subject}`, html, 'contact', id);
}

// ── SAVE UPDATE (modal save button) ─────────────────────────────
async function saveUpdate() {
  setBtn('modalSaveBtn', true, '💾 Save Changes');
  let payload = { id: currentEditId };
  let localUpdate = null;

  if (currentEditType === 'user') {
    payload = {
      ...payload,
      first_name: document.getElementById('eu_first').value.trim(),
      last_name:  document.getElementById('eu_last').value.trim(),
      email:      document.getElementById('eu_email').value.trim(),
      phone:      document.getElementById('eu_phone').value.trim(),
      district:   document.getElementById('eu_district').value,
      role:       document.getElementById('eu_role').value,
      is_active:  document.getElementById('eu_status').value,
    };
    localUpdate = () => {
      const u = db.users.find(x => x.id == currentEditId);
      Object.assign(u, payload);
      renderUsers();
    };
  }
  else if (currentEditType === 'report') {
    payload = {
      ...payload,
      reporter_name:       document.getElementById('er_name').value.trim(),
      reporter_phone:      document.getElementById('er_phone').value.trim(),
      district:            document.getElementById('er_district').value,
      village:             document.getElementById('er_village').value.trim(),
      crisis_type:         document.getElementById('er_type').value,
      severity:            document.getElementById('er_severity').value,
      households_affected: parseInt(document.getElementById('er_households').value) || 0,
      status:              document.getElementById('er_status').value,
      description:         document.getElementById('er_desc').value.trim(),
    };
    localUpdate = () => {
      const r = db.reports.find(x => x.id == currentEditId);
      Object.assign(r, payload);
      renderReports();
    };
  }
  else if (currentEditType === 'contact') {
    payload = {
      ...payload,
      first_name:  document.getElementById('ec_first').value.trim(),
      last_name:   document.getElementById('ec_last').value.trim(),
      email:       document.getElementById('ec_email').value.trim(),
      phone:       document.getElementById('ec_phone').value.trim(),
      subject:     document.getElementById('ec_subject').value,
      read_status: document.getElementById('ec_read').value,
      message:     document.getElementById('ec_message').value.trim(),
    };
    localUpdate = () => {
      const c = db.contacts.find(x => x.id == currentEditId);
      Object.assign(c, payload);
      renderContacts();
    };
  }

  try {
    const data = await apiCall('update_' + currentEditType, payload);
    if (data.success) {
      localUpdate && localUpdate();
      closeModal();
      toast(data.message);
    } else {
      toast(data.message, 'error');
    }
  } catch(e) {
    toast('Network error. Please try again.', 'error');
  }
  setBtn('modalSaveBtn', false, '💾 Save Changes');
}

// ── XSS HELPERS ──────────────────────────────────────────────────
function escHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
  if (s == null) return '';
  return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ── INIT ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderUsers();
  renderReports();
  renderContacts();
  updateStats();

  const params = new URLSearchParams(window.location.search);
  if (params.get('status') === 'login_success') {
    toast('✅ Welcome back, <?php echo $admin_name; ?>! Logged in as Admin.', 'success');
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});
</script>
</body>
</html>