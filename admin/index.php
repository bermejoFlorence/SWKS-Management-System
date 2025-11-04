<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------------- Defaults (avoid notices) ---------------- */
$totalOrgs = 0;
$totalMembers = 0;
$validatedRequests = 0;

/* New KPI totals for the 8 cards */
$totalInventoryItems = 0; // active SKUs in inventory_items
$totalBorrowedItems  = 0; // approved + returned quantities
$totalApprovedItems  = 0; // approved quantities (currently out)
$totalReturnedItems  = 0; // returned quantities
$totalOngoingItems   = 0; // alias of approved quantities (currently out)

/* ---------------- Basic KPIs (existing) ---------------- */

/* Total organizations */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM organization")) {
  $row = $res->fetch_assoc();
  $totalOrgs = (int)($row['c'] ?? 0);
  $res->free();
}

/* Total approved members (site-wide) */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM member_details WHERE status='approved'")) {
  $row = $res->fetch_assoc();
  $totalMembers = (int)($row['c'] ?? 0);
  $res->free();
}

/* Pending validated — bilang ng requests na 'validated' */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM borrow_requests WHERE status='validated'")) {
  $row = $res->fetch_assoc();
  $validatedRequests = (int)($row['c'] ?? 0);
  $res->free();
}

/* ---------------- Inventory + Borrow KPIs (new) ---------------- */

/* Total Inventory Items (active SKUs) */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM inventory_items WHERE status='active'")) {
  $row = $res->fetch_assoc();
  $totalInventoryItems = (int)($row['c'] ?? 0);
  $res->free();
}

/* Sum of quantities per borrow status */
$byStatusQty = [
  'pending'   => 0,
  'validated' => 0,
  'approved'  => 0,
  'rejected'  => 0,
  'returned'  => 0,
  'cancelled' => 0,
];

$sql = "
  SELECT br.status, COALESCE(SUM(bri.quantity_requested),0) AS qty
  FROM borrow_requests br
  JOIN borrow_request_items bri ON bri.request_id = br.request_id
  GROUP BY br.status
";
if ($res = $conn->query($sql)) {
  while ($row = $res->fetch_assoc()) {
    $st  = strtolower((string)$row['status']);
    $qty = (int)$row['qty'];
    if (isset($byStatusQty[$st])) $byStatusQty[$st] = $qty;
  }
  $res->free();
}

/* Map to the 4 new cards */
$totalBorrowedItems = ($byStatusQty['approved'] ?? 0) + ($byStatusQty['returned'] ?? 0);
$totalApprovedItems = ($byStatusQty['approved'] ?? 0);
$totalReturnedItems = ($byStatusQty['returned'] ?? 0);
$totalOngoingItems  = ($byStatusQty['approved'] ?? 0); // items currently out
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Aca Coordinator Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 / Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.11/index.global.min.js"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Custom styles -->
  <link rel="stylesheet" href="styles/style.css">
<style>
  /* =========================
   PAGE-SPECIFIC THEME VARS
   (admin/index.php only)
========================= */
:root{
  /* Page + calendar (na-setup na natin dati) */
  --page-bg: #eef7f0;
  --card-border: #e1efe7;

  /* Mint green system */
  --mint-50:  #f5fbf7;
  --mint-100: #eaf6ef;
  --mint-200: #dff3e6;
  --mint-300: #cfeadc;
  --mint-500: #69bf7a;
  --mint-600: #57b06a;
  --mint-700: #2e7d32;

  /* Neutrals */
  --ink-900: #263238;
  --ink-700: #455a64;
  --ink-500: #6b7a82;
  --ink-300: #cfd8dc;

  /* Pills (buttons) */
  --pill-green-1: #8bd09a;
  --pill-green-2: #69bf7a;
  --pill-grey-1: #818c96;
  --pill-grey-2: #67727c;
}
/* --- Harmonized KPI palette --- */
:root{
  --mint-300:#a5d6a7; --mint-500:#69bf7a; --mint-700:#2e7d32;
  --teal-300:#63d2c7; --teal-600:#1ea497; --teal-700:#17897f;
  --slate-300:#cfd8dc; --slate-600:#6b7a82; --slate-700:#51616a;
  --amber-400:#ffc857; --amber-600:#f0a400;
}

/* text utilities */
.text-mint  { color: var(--mint-700)  !important; }
.text-teal  { color: var(--teal-700)  !important; }
.text-slate { color: var(--slate-700) !important; }
.text-amber { color: var(--amber-600) !important; }

/* pill buttons (tone-on-tone) */
.btn-mint{
  background: linear-gradient(180deg,#8bd09a,var(--mint-500)) !important;
  color:#fff !important; border:0 !important; border-radius:999px !important; font-weight:700 !important;
}
.btn-teal{
  background: linear-gradient(180deg,var(--teal-300),var(--teal-600)) !important;
  color:#fff !important; border:0 !important; border-radius:999px !important; font-weight:700 !important;
}
.btn-slate{
  background: linear-gradient(180deg,#d7e1e5,var(--slate-600)) !important;
  color:#fff !important; border:0 !important; border-radius:999px !important; font-weight:700 !important;
}
.btn-amber{
  background: linear-gradient(180deg,var(--amber-400),var(--amber-600)) !important;
  color:#fff !important; border:0 !important; border-radius:999px !important; font-weight:700 !important;
}

/* subtle top bars (replaces border-*) */
.kpi-wrapper .card.border-mint::before,
.kpi-wrapper .card.border-teal::before,
.kpi-wrapper .card.border-slate::before,
.kpi-wrapper .card.border-amber::before{
  content:""; position:absolute; top:-1px; left:14px; right:14px; height:7px; border-radius:12px; opacity:.95;
}
.kpi-wrapper .card.border-mint::before  { background:linear-gradient(90deg,#c9eccc,var(--mint-500)); }
.kpi-wrapper .card.border-teal::before  { background:linear-gradient(90deg,#b8efe9,var(--teal-600)); }
.kpi-wrapper .card.border-slate::before { background:linear-gradient(90deg,#e6eff3,var(--slate-600)); }
.kpi-wrapper .card.border-amber::before { background:linear-gradient(90deg,#ffe9a6,var(--amber-600)); }

/* optional: lighter subtext tone */
.kpi-sub.text-mint  { color: rgba(46,125,50,.85) !important; }
.kpi-sub.text-teal  { color: rgba(23,137,127,.85) !important; }
.kpi-sub.text-slate { color: rgba(81,97,106,.85) !important; }
.kpi-sub.text-amber { color: rgba(240,164,0,.95) !important; }

/* =========================
   CARDS (kept from prev)
========================= */
.main-content{ background: var(--page-bg); padding-top: 70px; }
.main-content .card{
  background:#fff; border-radius:22px; border:1px solid var(--card-border);
  box-shadow:0 12px 30px rgba(0,0,0,.06);
  transition: box-shadow .2s, transform .2s;
}
.main-content .card:hover{ box-shadow:0 18px 44px rgba(0,0,0,.10); transform: translateY(-2px); }

/* top highlight bars (kept) */
.main-content .card.border-success::before,
.main-content .card.border-primary::before,
.main-content .card.border-warning::before{
  content:""; position:absolute; top:-1px; left:14px; right:14px; height:8px; border-radius:12px; opacity:.95;
}
.main-content .card.border-success::before{ background: linear-gradient(90deg,#a5d6a7,#66bb6a); }
.main-content .card.border-primary::before{ background: linear-gradient(90deg,#90caf9,#42a5f5); }
.main-content .card.border-warning::before{ background: linear-gradient(90deg,#ffe082,#ffb300); }

/* Buttons inside cards */
.main-content .card .btn{ border-radius:999px; font-weight:700; padding:.7rem 1.3rem; border:0; box-shadow:0 8px 22px rgba(0,0,0,.10); }
.main-content .card .btn.btn-success{ background:linear-gradient(180deg,#8bd09a,#5fbe73); }
.main-content .card .btn.btn-primary{ background:linear-gradient(180deg,#2ea3ff,#1b8fff); }
.main-content .card .btn.btn-warning{ background:linear-gradient(180deg,#ffb83a,#ffa000); color:#fff; }

/* =========================
   CALENDAR (kept from prev)
========================= */
.calendar-card{ border-radius:24px; background:#fff; border:1px solid #e4efe7; box-shadow:0 10px 30px rgba(0,0,0,.06); overflow:hidden; }
.calendar-card .card-body{ padding:24px; }
.calendar-card .fc .fc-toolbar .fc-button{
  border-radius:28px !important; font-weight:700 !important; padding:.55rem 1.1rem !important; border:0 !important;
  box-shadow:0 6px 18px rgba(124,198,138,.25) !important; transition: transform .12s ease !important;
}
.calendar-card .fc .fc-prev-button,
.calendar-card .fc .fc-next-button,
.calendar-card .fc .fc-today-button,
.calendar-card .fc .fc-button-group .fc-button{
  background: linear-gradient(180deg,#8bd09a,#69bf7a) !important; color:#fff !important;
}
.calendar-card .fc .fc-toolbar-title{ font-weight:800; color: var(--mint-700); }
.calendar-card .fc .fc-col-header{ background: var(--mint-100); }
.calendar-card .fc-theme-standard .fc-scrollgrid,
.calendar-card .fc-theme-standard td,
.calendar-card .fc-theme-standard th{ border-color:#e9efe8; }
.calendar-card .fc .fc-daygrid-day-number{ color:#0f1b14; font-weight:700; }
.calendar-card .fc .fc-day-past .fc-daygrid-day-number{ color:#cdd8cf; }
.calendar-card .fc .fc-day-today{ background:#fff7e6 !important; }
.calendar-card .fc .fc-day-today .fc-daygrid-day-number{
  background: var(--mint-200); color: var(--mint-700); border-radius:999px; padding:.25rem .6rem;
}
.calendar-card .fc .fc-event{
  background:#7cc68a !important; border:none !important; border-radius:16px !important; padding:.6rem .8rem !important;
  box-shadow:0 6px 16px rgba(124,198,138,.35);
}
.calendar-card .fc .fc-event .fc-event-main,
.calendar-card .fc .fc-event .fc-event-time{ color:#fff !important; font-weight:700; }

/* =========================
   MODAL: Day list & Add/Edit
========================= */

/* Backdrop subtle blur */
.modal-backdrop.show{ backdrop-filter: blur(2px); }

/* Shell */
#eventModal .modal-content{
  border-radius: 22px;
  border: 1px solid var(--card-border);
  box-shadow: 0 24px 70px rgba(0,0,0,.18);
}

/* Header: mint divider + title color */
#eventModal .modal-header{
  border-bottom: 1px solid #cfe7d2;
  background: linear-gradient(180deg, var(--mint-50), #ffffff);
  padding: 1.2rem 1.6rem;
}
#eventModal .modal-title{
  font-weight: 800; color: var(--mint-700);
}

/* Footer: mint divider */
#eventModal .modal-footer{
  border-top: 1px solid #cfe7d2;
  background: linear-gradient(0deg, var(--mint-50), #ffffff);
  padding: 1.1rem 1.6rem;
}

/* Buttons: Add / Save (green), Close (gray), Back (ghost) */
#eventModal .btn{
  border-radius: 999px; font-weight: 700; padding: .7rem 1.2rem; border: 0;
  box-shadow: 0 8px 22px rgba(0,0,0,.10);
}
#eventModal #addEventBtn,
#eventModal #saveBtn{
  background: linear-gradient(180deg, var(--pill-green-1), var(--pill-green-2));
  color:#fff;
}
#eventModal #addEventBtn:hover,
#eventModal #saveBtn:hover{ filter: brightness(.96); }

#eventModal .btn-secondary{
  background: linear-gradient(180deg, var(--pill-grey-1), var(--pill-grey-2));
  color:#fff;
}
#eventModal .btn-secondary:hover{ filter: brightness(.96); }

#eventModal #backToListBtn{
  border:1px solid var(--ink-300);
  color: var(--ink-700);
  background:#fff;
  box-shadow:none;
}
#eventModal #backToListBtn:hover{
  background: var(--mint-50);
  border-color: var(--mint-300);
  color: var(--mint-700);
}

/* ---------- Day list mode ---------- */
#dayLabel{ color: var(--ink-900); font-weight: 700; }
#dayEmpty{
  background: #fff;
  border: 2px dashed #cfe7d2;
  color: var(--ink-500);
  border-radius: 18px;
}

/* Items list (if meron) */
#dayEventsList .list-group-item{
  background: #198754; color:#fff; border:none; border-radius:12px; margin-bottom:.6rem;
  box-shadow: 0 8px 20px rgba(25,135,84,.25);
}
#dayEventsList .list-group-item .text-muted{ color: rgba(255,255,255,.85) !important; }
#dayEventsList .btn-outline-primary,
#dayEventsList .btn-outline-danger{
  color:#fff; border-color:#fff;
}
#dayEventsList .btn-outline-primary:hover{ background:#0d6efd; border-color:#0d6efd; }
#dayEventsList .btn-outline-danger:hover{ background:#dc3545; border-color:#dc3545; }

/* ---------- Form mode ---------- */
#eventModal .form-label{ font-weight:700; color: var(--ink-900); }
#eventModal .form-control{
  border-radius: 12px; border: 1.5px solid #e3ede6; padding: .75rem 1rem;
}
#eventModal .form-control:focus{
  border-color: var(--mint-500);
  box-shadow: 0 0 0 4px rgba(105,191,122,.18);
}
#eventModal .form-control-color{
  width: 48px; height: 36px; padding: 4px; border-radius: 10px;
  border: 1.5px solid #e3ede6; background:#fff;
}

/* Close icon color */
#eventModal .btn-close{
  filter: invert(40%) sepia(12%) saturate(400%) hue-rotate(90deg) brightness(90%);
  opacity: .8;
}
#eventModal .btn-close:hover{ opacity: 1; }
/* ==== FORCE GREEN FULLCALENDAR BUTTONS (month/week/day/list + prev/next/today) ==== */
.calendar-card .fc .fc-toolbar .fc-button,
.calendar-card .fc .btn,
.calendar-card .fc .btn-primary {
  background: linear-gradient(180deg, #8bd09a, #69bf7a) !important; /* mint green */
  border: 0 !important;
  color: #fff !important;
  border-radius: 28px !important;
  font-weight: 700 !important;
  padding: .55rem 1.1rem !important;
  box-shadow: 0 6px 18px rgba(124,198,138,.25) !important;
}

/* hover/active states */
.calendar-card .fc .fc-toolbar .fc-button:hover,
.calendar-card .fc .fc-toolbar .fc-button.fc-button-active {
  background: linear-gradient(180deg, #7fcb90, #5fb873) !important;
  filter: brightness(.98);
}

/* today button kapag disabled (same green family, just lighter) */
.calendar-card .fc .fc-today-button:disabled {
  background: #a9dcb3 !important;
  color: #ffffff !important;
  opacity: .85 !important;
}

/* focus ring (green glow) */
.calendar-card .fc .fc-toolbar .fc-button:focus {
  box-shadow: 0 0 0 .2rem rgba(105,191,122,.25) !important;
}
/* KPI sizing tweaks so 4 fit comfortably per row */
.kpi-wrapper .kpi-card {
  border-radius: 20px;
  border: 1px solid var(--card-border);
  box-shadow: 0 8px 22px rgba(0,0,0,.06);
  padding-top: .25rem;
}
.kpi-wrapper .kpi-card .card-body {
  padding: 1.25rem 1rem;
}
.kpi-wrapper .kpi-title {
  font-weight: 800;
  margin-top: .25rem;
}
.kpi-wrapper .kpi-sub {
  font-weight: 600;
  opacity: .95;
}

/* Slightly smaller numbers than the old display-4 */
.kpi-wrapper .display-6 {
  font-size: 2.25rem;
  line-height: 1.1;
}

/* keep the nice top gradient bars on bordered cards */
.kpi-wrapper .card.border-success::before,
.kpi-wrapper .card.border-primary::before,
.kpi-wrapper .card.border-warning::before,
.kpi-wrapper .card.border-info::before,
.kpi-wrapper .card.border-secondary::before{
  content:""; position:absolute; top:-1px; left:14px; right:14px; height:7px; border-radius:12px; opacity:.95;
}
.kpi-wrapper .card.border-success::before{ background: linear-gradient(90deg,#a5d6a7,#66bb6a); }
.kpi-wrapper .card.border-primary::before{ background: linear-gradient(90deg,#90caf9,#42a5f5); }
.kpi-wrapper .card.border-warning::before{ background: linear-gradient(90deg,#ffe082,#ffb300); }
.kpi-wrapper .card.border-info::before{    background: linear-gradient(90deg,#a5e4f5,#29b6f6); }
.kpi-wrapper .card.border-secondary::before{background: linear-gradient(90deg,#cfd8dc,#90a4ae); }

</style>
  <script>
    // Label for renderers when org name is absent
    window.ORG_NAME = 'All Organizations';
  </script>
</head>
<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- Top cards: 3 centered -->
<!-- Top cards: 3 centered -->
<!-- Top cards: 3 centered -->
<div class="row g-4 justify-content-center text-center mt-2">
  <!-- Total Orgs -->
  <!-- ==== KPI CARDS (8 total: 4 per row) ==== -->
<div class="container-fluid kpi-wrapper mt-2">
  <div class="row g-4 text-center justify-content-center">

<!-- Row 1 (4 cards) -->
<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-mint h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-mint"><?= $totalOrgs ?></div>
      <div class="kpi-title">Total Organizations</div>
      <div class="kpi-sub text-mint mb-3">All registered orgs</div>
      <a href="organization.php" class="btn btn-mint btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-teal h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-teal"><?= $totalMembers ?></div>
      <div class="kpi-title">Total Members</div>
      <div class="kpi-sub text-teal mb-3">Approved members</div>
      <a href="organization.php" class="btn btn-teal btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-amber h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-amber"><?= $validatedRequests ?></div>
      <div class="kpi-title">Pending Validated Borrow Items</div>
      <div class="kpi-sub text-amber mb-3">Awaiting admin action</div>
      <a href="inventory.php" class="btn btn-amber btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<!-- Total Inventory Items -->
<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-slate h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-slate"><?= $totalInventoryItems ?></div>
      <div class="kpi-title">Total Inventory Items</div>
      <div class="kpi-sub text-slate mb-3">All items in stock list</div>
      <a href="inventory.php" class="btn btn-slate btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<!-- Row 2 (4 cards) -->
<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-slate h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-slate"><?= $totalBorrowedItems ?></div>
      <div class="kpi-title">Total Borrowed Items</div>
      <div class="kpi-sub text-slate mb-3">All borrow entries</div>
      <a href="inventory.php" class="btn btn-slate btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-mint h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-mint"><?= $totalApprovedItems ?></div>
      <div class="kpi-title">Total Approved Items</div>
      <div class="kpi-sub text-mint mb-3">Approved borrow requests</div>
      <a href="inventory.php" class="btn btn-mint btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-teal h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-teal"><?= $totalReturnedItems ?></div>
      <div class="kpi-title">Total Returned Items</div>
      <div class="kpi-sub text-teal mb-3">Completed returns</div>
      <a href="inventory.php" class="btn btn-teal btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

<div class="col-12 col-sm-6 col-lg-3">
  <div class="card kpi-card border-slate h-100 position-relative">
    <div class="card-body d-flex flex-column align-items-center">
      <div class="display-6 fw-bold text-slate"><?= $totalOngoingItems ?></div>
      <div class="kpi-title">Total Ongoing Items</div>
      <div class="kpi-sub text-slate mb-3">Out/issued right now</div>
      <a href="inventory.php" class="btn btn-slate btn-sm mt-auto">View details</a>
    </div>
  </div>
</div>

  </div>
</div>
<!-- ==== /KPI CARDS ==== -->

</div>
    <!-- Calendar -->
    <div class="row g-4 mt-1">
      <div class="col-12">
        <div class="card shadow-sm calendar-card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="fw-bold mb-3">Calendar (Asia/Manila)</h5>
            </div>
            <div id="calendar"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Day List / Add-Edit Form -->
  <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form id="eventForm">
          <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Events</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <!-- Hidden keys -->
            <input type="hidden" name="event_id" id="event_id">
            <input type="hidden" name="start" id="start">
            <input type="hidden" name="end" id="end">
            <input type="hidden" name="allDay" id="allDayHidden" value="1">

            <!-- Day list mode -->
            <div id="dayListMode">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0" id="dayLabel">—</h6>
                <button type="button" class="btn btn-primary btn-sm" id="addEventBtn">
                  <i class="bi bi-plus-lg me-1"></i>Add event
                </button>
              </div>
              <div id="dayEventsList" class="list-group small"></div>
              <div id="dayEmpty" class="text-muted text-center py-4 d-none">
                No events for this day yet
              </div>
            </div>

            <!-- Form mode -->
            <div id="formMode" class="d-none">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Title <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="title" id="title" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Color</label>
                  <input type="color" class="form-control form-control-color" name="color" id="color" value="#198754">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label class="form-label">Start time (optional)</label>
                  <input type="time" class="form-control" id="startTime">
                </div>
                <div class="col-md-6 col-lg-3">
                  <label class="form-label">End time (optional)</label>
                  <input type="time" class="form-control" id="endTime">
                </div>
                <div class="col-12">
                  <label class="form-label">Description</label>
                  <textarea class="form-control" rows="3" name="description" id="description"></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer justify-content-between">
            <div>
              <button type="button" class="btn btn-outline-secondary d-none" id="backToListBtn">
                <i class="bi bi-arrow-left-short me-1"></i>Back
              </button>
            </div>
            <div>
              <button type="button" class="btn btn-outline-danger d-none" id="deleteBtn">Delete</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary d-none" id="saveBtn">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar behavior -->
  <script>
    function toggleSidebar(){ const s=document.getElementById('sidebar'); s?.classList.toggle('show'); }
    document.addEventListener('click', function(e){
      const s=document.getElementById('sidebar'); const h=document.querySelector('.hamburger-btn');
      if (window.innerWidth<=992 && s?.classList.contains('show')){
        if (!s.contains(e.target) && e.target!==h){ s.classList.remove('show'); }
      }
    });
    const hamburger=document.querySelector('.hamburger-btn');
    hamburger?.addEventListener('click', e=> e.stopPropagation());
  </script>

  <!-- Calendar logic (mirrors adviser behavior) -->
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const el = document.getElementById('calendar');
      if (!el) return;

      // PH time helpers
      function manilaTodayStart(){
        const nowPH = new Date(new Date().toLocaleString('en-US', { timeZone:'Asia/Manila' }));
        nowPH.setHours(0,0,0,0);
        return nowPH;
      }
      function isPast(date, startOfDay = true){
        const d = new Date(date);
        if (startOfDay) d.setHours(0,0,0,0);
        return d < manilaTodayStart();
      }
      const startOfDay = d=>{ const x=new Date(d); x.setHours(0,0,0,0); return x; };
      const endOfDay   = d=>{ const x=new Date(d); x.setHours(23,59,59,999); return x; };
      const toYMD      = d=>{ const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`; };
      const toLocalHM  = d=>{ const p=n=>String(n).padStart(2,'0'); return `${p(d.getHours())}:${p(d.getMinutes())}`; };
      + function formatTimeRange(s,e){
   const fmt = new Intl.DateTimeFormat('en-PH',{
     hour:'numeric', minute:'2-digit', timeZone:'Asia/Manila'
   });
   const st = s ? fmt.format(s) : '';
   const et = e ? fmt.format(e) : '';
   return (st && et) ? `${st} – ${et}` : st;
 }

      function formatDayHeader(d){
        return d.toLocaleDateString('en-PH',{weekday:'long',month:'short',day:'numeric',year:'numeric'});
      }
      const escapeHtml = str => (str||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

      // Modal + state
      const modalEl=document.getElementById('eventModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl,{backdrop:true,keyboard:true});
      let modalOpen=false, selectedDay=null, calendar;

      // UI refs
      const dayListMode   = document.getElementById('dayListMode');
      const formMode      = document.getElementById('formMode');
      const backToListBtn = document.getElementById('backToListBtn');
      const saveBtn       = document.getElementById('saveBtn');
      const deleteBtn     = document.getElementById('deleteBtn');
      const addEventBtn   = document.getElementById('addEventBtn');
      const dayLabel      = document.getElementById('dayLabel');
      const dayEventsList = document.getElementById('dayEventsList');
      const dayEmpty      = document.getElementById('dayEmpty');
      const modalTitle    = document.getElementById('modalTitle');

      // Form refs
      const startHidden = document.getElementById('start');
      const endHidden   = document.getElementById('end');
      const allDayHid   = document.getElementById('allDayHidden');
      const titleInp    = document.getElementById('title');
      const colorInp    = document.getElementById('color');
      const descInp     = document.getElementById('description');
      const startTime   = document.getElementById('startTime');
      const endTime     = document.getElementById('endTime');

      modalEl.addEventListener('shown.bs.modal', ()=> modalOpen=true);
      modalEl.addEventListener('hidden.bs.modal', ()=>{
        modalOpen=false; calendar?.unselect?.();
        document.querySelectorAll('.modal-backdrop').forEach(b=>b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('paddingRight');
      });

      const isMobile = ()=> window.innerWidth < 576;

      calendar = new FullCalendar.Calendar(el,{
        themeSystem:'bootstrap5',
        timeZone:'Asia/Manila',
        initialView: isMobile()? 'listWeek':'dayGridMonth',
        headerToolbar:{ left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        expandRows:true, contentHeight:'auto', stickyHeaderDates:true,
        navLinks:false, dayMaxEvents:true, nowIndicator:true, handleWindowResize:true,
        eventTimeFormat:{ hour:'numeric', minute:'2-digit', meridiem:'short' },
        selectAllow: info => !isPast(info.start),

        // Admin feed (should return ALL orgs visible to admin)
        events: 'events_feed.php',

        eventContent: (arg)=>{
          const container=document.createElement('div');
          container.className='swks-event';
          const timeLine = arg.timeText || '';
          const org = (arg.event.extendedProps && arg.event.extendedProps.org_name) || window.ORG_NAME || 'All Organizations';
          container.innerHTML = `
            <div class="small fw-semibold">${escapeHtml(org)}</div>
            <div class="fw-bold">${escapeHtml(arg.event.title || '')}</div>
            <div class="small">${escapeHtml(timeLine)}</div>
          `;
          return { domNodes:[container] };
        },

        dateClick: (info)=>{
          if (isPast(info.date)) return;
          if (modalOpen) return;
          openDayModal(new Date(info.dateStr));
        },

        selectable:true, selectMirror:false,
        select: (info)=>{
          if (isPast(info.start)){ calendar.unselect(); return; }
          if (modalOpen) return;
          selectedDay = startOfDay(info.start);
          switchToFormMode(false, 'Add Event');
          setDateFromDay(selectedDay);
          if (!info.allDay){
            startTime.value = toPH_HM(info.start);
            if (info.end) endTime.value = toPH_HM(info.end);
            allDayHid.value='0';
          } else {
            startTime.value=''; endTime.value=''; allDayHid.value='1';
          }
          modal.show(); calendar.unselect();
        },

eventClick: async (info) => {
  if (modalOpen) return;
  const e = info.event;

  // 🔒 View-only kapag hindi ikaw ang gumawa
  if (!e.extendedProps?.owned) {
    Swal.fire({
      icon: 'info',
      title: 'View only',
      text: 'You can only edit events you created.',
      timer: 1400,
      showConfirmButton: false
    });
    return;
  }

  try {
    // ✅ snapshot mula DB (admin/events_get.php)
    const res  = await fetch('events_get.php?id=' + encodeURIComponent(e.id));
    const data = await res.json();
    if (!data?.ok) throw new Error(data?.msg || 'Fetch failed');

    switchToFormMode(true, 'Edit Event');

    // keys + basics
    document.getElementById('event_id').value = data.id;
    titleInp.value = data.title || '';
    colorInp.value = data.color || '#198754';
    descInp.value  = data.description || '';

    // hidden datetime fields
    startHidden.value = data.start || '';
    endHidden.value   = data.end   || '';
    allDayHid.value   = data.allDay ? '1' : '0';

    // time inputs (Asia/Manila)
    if (data.allDay) {
      startTime.value = '';
      endTime.value   = '';
      selectedDay     = startOfDay(new Date((data.start || '').slice(0,10)));
    } else {
      startTime.value = toPH_HM(data.start);
      endTime.value   = data.end ? toPH_HM(data.end) : '';
      selectedDay     = startOfDay(new Date(data.start));
    }

    modal.show();

  } catch (err) {
    // fallback gamit ang event object ng calendar
    selectedDay = startOfDay(e.start || new Date());
    switchToFormMode(true, 'Edit Event');

    document.getElementById('event_id').value = e.id;
    titleInp.value = e.title;
    colorInp.value = e.backgroundColor || '#198754';
    descInp.value  = e.extendedProps?.description || '';

    startHidden.value = e.startStr || '';
    endHidden.value   = e.endStr   || '';
    allDayHid.value   = e.allDay ? '1' : '0';

    startTime.value = (!e.allDay && e.start) ? toPH_HM(e.start) : '';
    endTime.value   = (!e.allDay && e.end)   ? toPH_HM(e.end)   : '';

    modal.show();
  }
},


        editable:true,
        eventDrop:  async info => { await quickSaveTimes(info.event); },
        eventResize: async info => { await quickSaveTimes(info.event); }
      });

      calendar.render();

      let last = isMobile();
      window.addEventListener('resize', ()=>{
        const m = isMobile();
        if (m !== last){ calendar.changeView(m? 'listWeek':'dayGridMonth'); last=m; }
      });

      // Day modal logic
      function openDayModal(day){
        selectedDay = startOfDay(day);
        renderDayList(selectedDay);
        switchToListMode(formatDayHeader(selectedDay));
        modal.show();
      }

  function renderDayList(dayDate) {
  dayEventsList.innerHTML = '';
  const dayStart = startOfDay(dayDate).getTime();
  const dayEnd   = endOfDay(dayDate).getTime();

  const events = calendar.getEvents().filter(e=>{
    const s  = e.start ? e.start.getTime() : 0;
    const ee = e.end   ? e.end.getTime()   : s;
    return (s < dayEnd) && (ee >= dayStart);
  }).sort((a,b)=>{
    if (a.allDay !== b.allDay) return a.allDay ? -1 : 1;
    const as = a.start ? a.start.getTime() : 0;
    const bs = b.start ? b.start.getTime() : 0;
    return as - bs;
  });

  if (!events.length) { 
    dayEmpty.classList.remove('d-none'); 
    return; 
  }
  dayEmpty.classList.add('d-none');

  for (const ev of events) {
    const item = document.createElement('div');
    item.className = 'list-group-item';
    const timeText = ev.allDay ? '' : formatTimeRange(ev.start, ev.end);
    const org = ev.extendedProps?.org_name || 'ACA Coordinator';

    const isOwned = !!ev.extendedProps?.owned;

    item.innerHTML = `
      <div class="d-flex w-100 justify-content-between align-items-start">
        <div class="me-3">
          <div class="small fw-semibold">${escapeHtml(org)}</div>
          <div class="fw-bold">${escapeHtml(ev.title || '')}</div>
          <div class="small text-muted">${escapeHtml(timeText)}</div>
        </div>
        <div class="ms-auto">
          ${isOwned ? `
            <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-ev"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger del-ev"><i class="bi bi-trash"></i></button>
          ` : ``}
        </div>
      </div>
    `;

    // Kung owned, tsaka lang magdagdag ng listeners
    if (isOwned) {
      item.querySelector('.edit-ev')?.addEventListener('click', async () => {
  try {
    const res  = await fetch('events_get.php?id=' + encodeURIComponent(ev.id));
    const data = await res.json();
    if (!data?.ok) throw new Error(data?.msg || 'Fetch failed');

    switchToFormMode(true, 'Edit Event');

    document.getElementById('event_id').value = data.id;
    titleInp.value = data.title || '';
    colorInp.value = data.color || '#198754';
    descInp.value  = data.description || '';

    startHidden.value = data.start || '';
    endHidden.value   = data.end   || '';
    allDayHid.value   = data.allDay ? '1' : '0';

    if (data.allDay) {
      startTime.value = '';
      endTime.value   = '';
      selectedDay     = startOfDay(new Date((data.start || '').slice(0,10)));
    } else {
      startTime.value = toPH_HM(data.start);
      endTime.value   = data.end ? toPH_HM(data.end) : '';
      selectedDay     = startOfDay(new Date(data.start));
    }

  } catch (err) {
    // fallback kung may error
    switchToFormMode(true, 'Edit Event');
    document.getElementById('event_id').value = ev.id;
    titleInp.value = ev.title;
    colorInp.value = ev.backgroundColor || '#198754';
    descInp.value  = ev.extendedProps?.description || '';
    startHidden.value = ev.startStr || '';
    endHidden.value   = ev.endStr   || '';
    allDayHid.value   = ev.allDay ? '1' : '0';
    startTime.value   = (!ev.allDay && ev.start) ? toPH_HM(ev.start) : '';
    endTime.value     = (!ev.allDay && ev.end)   ? toPH_HM(ev.end)   : '';
  }
});

      item.querySelector('.del-ev').addEventListener('click', async ()=> {
  const { isConfirmed } = await Swal.fire({
    icon:'warning', title:'Delete this event?', showCancelButton:true,
    confirmButtonText:'Delete', confirmButtonColor:'#dc3545'
  });
  if (!isConfirmed) return;

  const fd=new FormData(); 
  fd.append('event_id', ev.id);

  const res=await fetch('events_delete.php',{ method:'POST', body:fd });
  const data=await res.json();

  if (data?.ok){
    calendar.refetchEvents();
    renderDayList(selectedDay);
    Swal.fire({ 
      icon:'success', 
      title:'Event deleted', 
      timer:1200, 
      showConfirmButton:false 
    }).then(() => {
      // ✅ Close the modal after success
      const modalEl = document.getElementById('eventModal');
      if (modalEl){
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
      }
    });
  } else {
    Swal.fire({ icon:'error', title:'Error', text: data?.msg || 'Delete failed' });
  }
});

    }

    dayEventsList.appendChild(item);
  }
}
// Delete from EDIT modal
deleteBtn.addEventListener('click', async () => {
  const id = document.getElementById('event_id').value;
  if (!id) {
    Swal.fire({ icon: 'warning', title: 'No event selected' });
    return;
  }

  const { isConfirmed } = await Swal.fire({
    icon: 'warning',
    title: 'Delete this event?',
    text: 'This cannot be undone.',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc3545'
  });
  if (!isConfirmed) return;

  try {
    const fd = new FormData();
    fd.append('event_id', id);

    const res  = await fetch('events_delete.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data?.ok) throw new Error(data?.msg || 'Delete failed');

    await Swal.fire({ icon: 'success', title: 'Event deleted', timer: 900, showConfirmButton: false });

    modal.hide();
    calendar.refetchEvents();
    if (selectedDay) renderDayList(selectedDay);

  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error', text: String(err.message || err) });
  }
});

      function switchToListMode(label){
        modalTitle.textContent='Events';
        dayLabel.textContent = label || '—';
        dayListMode.classList.remove('d-none');
        formMode.classList.add('d-none');
        backToListBtn.classList.add('d-none');
        saveBtn.classList.add('d-none');
        deleteBtn.classList.add('d-none');
      }
      function switchToFormMode(isEdit, titleText){
        modalTitle.textContent = titleText || (isEdit? 'Edit Event':'Add Event');
        dayListMode.classList.add('d-none');
        formMode.classList.remove('d-none');
        backToListBtn.classList.remove('d-none');
        saveBtn.classList.remove('d-none');
        deleteBtn.classList.toggle('d-none', !isEdit);
      }

      backToListBtn.addEventListener('click', ()=>{
        renderDayList(selectedDay);
        switchToListMode(formatDayHeader(selectedDay));
      });
      addEventBtn.addEventListener('click', ()=>{
        switchToFormMode(false,'Add Event');
        clearFormOnly();
        setDateFromDay(selectedDay);
      });

      function setDateFromDay(day){
        const d = toYMD(day);
        startHidden.value = `${d}`;
        endHidden.value   = '';
        allDayHid.value   = '1';
        startTime.value   = '';
        endTime.value     = '';
      }
      function clearFormOnly(){
        document.getElementById('eventForm').reset();
        document.getElementById('event_id').value='';
        colorInp.value='#198754';
      }

      // Save create/update
      document.getElementById('eventForm').addEventListener('submit', async (e)=>{
        e.preventDefault();
        const startISOHidden = startHidden.value;
        const endISOHidden   = endHidden.value;
        const startDatePart  = (startISOHidden||'').slice(0,10);
        const endDatePart    = (endISOHidden||startISOHidden||'').slice(0,10);

        let allDay = allDayHid.value === '1';
        if (startTime.value || endTime.value) allDay=false;

        let startOut = startISOHidden;
        if (!allDay && startDatePart && startTime.value){ startOut = `${startDatePart}T${startTime.value}`; }
        let endOut = endISOHidden;
        if (!allDay && endDatePart && endTime.value){ endOut = `${endDatePart}T${endTime.value}`; }

        const fd=new FormData(e.target);
        fd.set('start', startOut || '');
        if (endOut) fd.set('end', endOut); else fd.delete('end');
        fd.set('allDay', allDay ? '1':'0');

        const isEdit = !!document.getElementById('event_id').value;

        try{
          const res = await fetch('events_save.php',{ method:'POST', body:fd });
          const data= await res.json();
          if (data?.ok){
            calendar.refetchEvents();
            renderDayList(selectedDay);
            switchToListMode(formatDayHeader(selectedDay));
            Swal.fire({ icon:'success', title: isEdit? 'Event updated':'Event added', timer:1300, showConfirmButton:false });
          } else {
            throw new Error(data?.msg || 'Save failed');
          }
        }catch(err){
          Swal.fire({ icon:'error', title:'Error', text:String(err.message || err) });
        }
      });

      // Quick-save on drag/resize
      async function quickSaveTimes(e){
        try{
          const fd=new FormData();
          fd.append('event_id', e.id);
          fd.append('title', e.title);
          fd.append('start', e.startStr);
          if (e.endStr) fd.append('end', e.endStr);
          fd.append('allDay', e.allDay ? '1':'0');
          const res=await fetch('events_save.php',{ method:'POST', body:fd });
          const data=await res.json();
          if (!data?.ok) throw new Error(data?.msg || 'Update failed');
        }catch(err){
          Swal.fire({ icon:'error', title:'Error', text:'Could not update event. Reverting.' });
          calendar.refetchEvents();
        }
      }
      function toPH_HM(dateOrIso){
  if (!dateOrIso) return '';
  if (typeof dateOrIso === 'string'){            // "YYYY-MM-DD HH:MM:SS"
    const s = dateOrIso.replace(' ', 'T');
    const m = s.match(/T(\d{2}:\d{2})/);
    if (m) return m[1];                          // HH:MM (24h)
  }
  const fmt = new Intl.DateTimeFormat('en-GB',{
    hour:'2-digit', minute:'2-digit', hour12:false, timeZone:'Asia/Manila'
  });
  return fmt.format(dateOrIso);
}

    });

  </script>
</body>
</html>
