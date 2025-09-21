<?php
include 'includes/auth_adviser.php'; // Access control (adviser only)
include_once '../database/db_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$org_id  = $_SESSION['org_id']  ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

/* ---- TOTAL MEMBERS: approved per adviser's org ---- */
$totalMembers = 0;
if ($org_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM member_details
        WHERE status = 'approved'
          AND preferred_org = ?
    ");
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $stmt->bind_result($totalMembers);
    $stmt->fetch();
    $stmt->close();
}

/* ---- PENDING BORROW REQUESTS: for this org ---- */
$pendingBorrow = 0;
if ($org_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT br.request_id)
        FROM borrow_requests br
        WHERE br.org_id = ?
          AND br.status = 'pending'
    ");
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $stmt->bind_result($pendingBorrow);
    $stmt->fetch();
    $stmt->close();
}

/* ---- UNREAD NOTIFICATIONS: for this adviser ---- */
$unreadNotifs = 0;
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM notification
        WHERE user_id = ?
          AND is_seen = 0
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($unreadNotifs);
    $stmt->fetch();
    $stmt->close();
}

/* ---- ORG NAME for calendar labels (safe fetch) ---- */

/* ---- ORG NAME for calendar labels ---- */
$orgName = $_SESSION['org_name'] ?? null;

if (!$orgName && $org_id) {
    $stmt = $conn->prepare("SELECT org_name FROM organization WHERE org_id = ?");
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $stmt->bind_result($orgName);
    $stmt->fetch();
    $stmt->close();
}

$orgName = $orgName ?: 'Organization';


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Adviser Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.11/index.global.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Pass org name to JS (fallback kung wala sa session) -->
<script>
  window.ORG_NAME = <?= json_encode($orgName) ?>;
</script>


  <!-- Your custom style -->
  <link rel="stylesheet" href="styles/style.css">
</head>
<style>
  /* =========================
   PAGE-SPECIFIC THEME (ADVISER)
   ========================= */
:root{
  /* Mint system */
  --mint-50:#f5fbf7; --mint-100:#eaf6ef; --mint-200:#dff3e6;
  --mint-500:#69bf7a; --mint-600:#57b06a; --mint-700:#2e7d32;
  --card-border:#e1efe7; --page-bg:#eef7f0;

  /* neutrals */
  --ink-900:#263238; --ink-700:#455a64; --ink-500:#6b7a82; --ink-300:#cfd8dc;

  /* pills */
  --pill-green-1:#8bd09a; --pill-green-2:#69bf7a;
  --pill-grey-1:#818c96; --pill-grey-2:#67727c;
}

/* ---------- Layout + Cards ---------- */
.main-content{ background:var(--page-bg); padding-top:70px; }
.main-content .card{
  background:#fff; border:1px solid var(--card-border); border-radius:22px;
  box-shadow:0 12px 30px rgba(0,0,0,.06); transition:.2s;
}
.main-content .card:hover{ box-shadow:0 18px 44px rgba(0,0,0,.10); transform:translateY(-2px); }

/* top highlight bars (same feel as admin) */
.main-content .card.border-success::before,
.main-content .card.border-primary::before,
.main-content .card.border-warning::before{
  content:""; position:absolute; left:14px; right:14px; top:-1px; height:8px; border-radius:12px;
}
.main-content .card.border-success::before{ background:linear-gradient(90deg,#a5d6a7,#66bb6a); }
.main-content .card.border-primary::before{ background:linear-gradient(90deg,#90caf9,#42a5f5); }
.main-content .card.border-warning::before{ background:linear-gradient(90deg,#ffe082,#ffb300); }

/* buttons inside cards */
.main-content .card .btn{ border:0; border-radius:999px; font-weight:700; padding:.7rem 1.3rem; box-shadow:0 8px 22px rgba(0,0,0,.10); }

/* =========================
   CALENDAR (mint/green)
   ========================= */
.calendar-card{ border:1px solid #e4efe7; border-radius:24px; box-shadow:0 10px 30px rgba(0,0,0,.06); overflow:hidden; }
.calendar-card .card-body{ padding:24px; }
#calendar{ min-height:520px; } @media (max-width:575px){ #calendar{min-height:420px;} }

/* toolbar title + header row */
.calendar-card .fc .fc-toolbar-title{ font-weight:800; color:var(--mint-700); }
.calendar-card .fc .fc-col-header{ background:var(--mint-100); }
.calendar-card .fc-theme-standard .fc-scrollgrid,
.calendar-card .fc-theme-standard td, .calendar-card .fc-theme-standard th{ border-color:#e9efe8; }
.calendar-card .fc .fc-daygrid-day-number{ color:#0f1b14; font-weight:700; }
.calendar-card .fc .fc-day-past .fc-daygrid-day-number{ color:#cdd8cf; }

/* today cell */
.calendar-card .fc .fc-day-today{ background:#fff7e6 !important; }
.calendar-card .fc .fc-day-today .fc-daygrid-day-number{
  background:var(--mint-200); color:var(--mint-700); border-radius:999px; padding:.25rem .6rem;
}

/* event chips */
.calendar-card .fc .fc-event{
  background:#7cc68a !important; border:none !important; border-radius:16px !important; padding:.6rem .8rem !important;
  box-shadow:0 6px 16px rgba(124,198,138,.35);
}
.calendar-card .fc .fc-event .fc-event-main,
.calendar-card .fc .fc-event .fc-event-time{ color:#fff !important; font-weight:700; }

/* "+N more" link */
.calendar-card .fc .fc-daygrid-more-link{ color:var(--mint-700) !important; font-weight:700 !important; text-decoration:underline; }

/* list view look */
.calendar-card .fc .fc-list{ border:1px solid #e9efe8; border-radius:16px; }
.calendar-card .fc .fc-list-event td{ background:#fff; color:#132016; border-color:#e9efe8; }
.calendar-card .fc .fc-list-event:hover td{ background:#f6fbf8; }
.calendar-card .fc .fc-list-day-cushion{ background:#f5fbf7; color:var(--mint-700); font-weight:800; }

/* GREEN buttons (prev/next/today + month/week/day/list) */
.calendar-card .fc .fc-toolbar .fc-button,
.calendar-card .fc .btn,
.calendar-card .fc .btn-primary{
  background:linear-gradient(180deg,var(--pill-green-1),var(--pill-green-2)) !important;
  border:0 !important; color:#fff !important; border-radius:28px !important; font-weight:700 !important;
  padding:.55rem 1.1rem !important; box-shadow:0 6px 18px rgba(124,198,138,.25) !important;
}
.calendar-card .fc .fc-toolbar .fc-button:hover,
.calendar-card .fc .fc-toolbar .fc-button.fc-button-active{ background:linear-gradient(180deg,#7fcb90,#5fb873) !important; }
.calendar-card .fc .fc-today-button:disabled{ background:#a9dcb3 !important; color:#fff !important; opacity:.85 !important; }
.calendar-card .fc .fc-toolbar .fc-button:focus{ box-shadow:0 0 0 .2rem rgba(105,191,122,.25) !important; }

/* =========================
   MODAL (Day list + Add/Edit) – same look as admin
   ========================= */
.modal-backdrop.show{ backdrop-filter: blur(2px); }
#eventModal .modal-content{ border-radius:22px; border:1px solid var(--card-border); box-shadow:0 24px 70px rgba(0,0,0,.18); }
#eventModal .modal-header{ border-bottom:1px solid #cfe7d2; background:linear-gradient(180deg,var(--mint-50),#fff); padding:1.2rem 1.6rem; }
#eventModal .modal-title{ font-weight:800; color:var(--mint-700); }
#eventModal .modal-footer{ border-top:1px solid #cfe7d2; background:linear-gradient(0deg,var(--mint-50),#fff); padding:1.1rem 1.6rem; }

/* modal buttons */
#eventModal .btn{ border-radius:999px; font-weight:700; padding:.7rem 1.2rem; border:0; box-shadow:0 8px 22px rgba(0,0,0,.10); }
#eventModal #addEventBtn,#eventModal #saveBtn{ background:linear-gradient(180deg,var(--pill-green-1),var(--pill-green-2)); color:#fff; }
#eventModal #addEventBtn:hover,#eventModal #saveBtn:hover{ filter:brightness(.96); }
#eventModal .btn-secondary{ background:linear-gradient(180deg,var(--pill-grey-1),var(--pill-grey-2)); color:#fff; }
#eventModal .btn-secondary:hover{ filter:brightness(.96); }
#eventModal #backToListBtn{ border:1px solid var(--ink-300); color:var(--ink-700); background:#fff; box-shadow:none; }
#eventModal #backToListBtn:hover{ background:var(--mint-50); border-color:var(--mint-300); color:var(--mint-700); }

/* Day list (empty + items) */
#dayLabel{ color:var(--ink-900); font-weight:700; }
#dayEmpty{ background:#fff; border:2px dashed #cfe7d2; color:var(--ink-500); border-radius:18px; }
#dayEventsList .list-group-item{
  background:#198754; color:#fff; border:none; border-radius:12px; margin-bottom:.6rem;
  box-shadow:0 8px 20px rgba(25,135,84,.25);
}
#dayEventsList .list-group-item .text-muted{ color:rgba(255,255,255,.85)!important; }
#dayEventsList .btn-outline-primary,#dayEventsList .btn-outline-danger{ color:#fff; border-color:#fff; }
#dayEventsList .btn-outline-primary:hover{ background:#0d6efd; border-color:#0d6efd; }
#dayEventsList .btn-outline-danger:hover{ background:#dc3545; border-color:#dc3545; }

/* Form fields */
#eventModal .form-label{ font-weight:700; color:var(--ink-900); }
#eventModal .form-control{ border-radius:12px; border:1.5px solid #e3ede6; padding:.75rem 1rem; }
#eventModal .form-control:focus{ border-color:var(--mint-500); box-shadow:0 0 0 4px rgba(105,191,122,.18); }
#eventModal .form-control-color{ width:48px; height:36px; padding:4px; border-radius:10px; border:1.5px solid #e3ede6; background:#fff; }
#eventModal .btn-close{ filter: invert(40%) sepia(12%) saturate(400%) hue-rotate(90deg) brightness(90%); opacity:.8; }
#eventModal .btn-close:hover{ opacity:1; }

</style>
<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
<!-- Stats Cards (centered) -->
<div class="row g-4 justify-content-center">

  <!-- Card 1: New Applicants -->
  <div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="card flex-fill border-success shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-4 fw-bold text-success">
          <span id="newApplicantsCount">0</span>
        </div>
        <div class="h5 fw-bold mb-2">New Applicants</div>
        <a href="applications.php" class="fw-semibold text-success text-decoration-underline">View details</a>
      </div>
    </div>
  </div>

  <!-- Card 2: Total Members (approved) -->
  <div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="card flex-fill border-primary shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-4 fw-bold text-primary"><?= (int)$totalMembers ?></div>
        <div class="h5 fw-bold mb-2">Total Members</div>
        <a href="members.php?status=approved" class="fw-semibold text-primary text-decoration-underline">View details</a>
      </div>
    </div>
  </div>

  <!-- Card 3: Pending Borrow Requests -->
  <div class="col-12 col-sm-6 col-lg-3 d-flex">
    <div class="card flex-fill border-warning shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-4 fw-bold text-warning"><?= (int)$pendingBorrow ?></div>
        <div class="h5 fw-bold mb-2">Pending Borrow Requests</div>
        <a href="inventory.php" class="fw-semibold text-warning text-decoration-underline">Review requests</a>
      </div>
    </div>
  </div>

</div>


    <!-- Calendar Section -->
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

<!-- Add/Edit + Day List Modal -->
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

          <!-- ========== DAY LIST MODE ========== -->
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

          <!-- ========== FORM MODE (hidden by default) ========== -->
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
              <!-- Optional time overrides (date locked to selected day) -->
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


  <script>
  // Sidebar toggle for mobile
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar?.classList.toggle('show');
  }
  // Sidebar auto-close on outside click (mobile)
  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger-btn');
    if (window.innerWidth <= 992 && sidebar?.classList.contains('show')) {
      if (!sidebar.contains(event.target) && event.target !== hamburger) {
        sidebar.classList.remove('show');
      }
    }
  });
  // Guard against missing .hamburger-btn
  const hamburger = document.querySelector('.hamburger-btn');
  if (hamburger) {
    hamburger.addEventListener('click', function(e) { e.stopPropagation(); });
  }
  </script>

  <script>
  // Live count for New Applicants
  function fetchNewApplicantsCount() {
    fetch('get_new_applicants_count.php')
      .then(r => r.text())
      .then(count => { document.getElementById('newApplicantsCount').innerText = count; })
      .catch(() => {});
  }
  fetchNewApplicantsCount();
  setInterval(fetchNewApplicantsCount, 5000);
  </script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('calendar');
  if (!el) return;
  

  // ---- PH time helpers ----
function manilaTodayStart() {
  const nowPH = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
  nowPH.setHours(0, 0, 0, 0);
  return nowPH;
}
function isPast(date, compareStartOfDay = true) {
  const d = new Date(date);
  if (compareStartOfDay) d.setHours(0, 0, 0, 0);
  return d < manilaTodayStart();
}


  // Modal single instance + state
  const modalEl = document.getElementById('eventModal');
  const modal   = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
  let modalOpen = false;
  let calendar;
  let selectedDay = null; // JS Date of the day being viewed

  // UI elems (list vs form)
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

  // Form fields
  const startHidden = document.getElementById('start');
  const endHidden   = document.getElementById('end');
  const allDayHid   = document.getElementById('allDayHidden');
  const titleInp    = document.getElementById('title');
  const colorInp    = document.getElementById('color');
  const descInp     = document.getElementById('description');
  const startTime   = document.getElementById('startTime');
  const endTime     = document.getElementById('endTime');

  modalEl.addEventListener('shown.bs.modal', () => { modalOpen = true; });
  modalEl.addEventListener('hidden.bs.modal', () => {
    modalOpen = false;
    calendar?.unselect?.();
    // safety cleanup
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('paddingRight');
  });

  const isMobile = () => window.innerWidth < 576;

  calendar = new FullCalendar.Calendar(el, {
    themeSystem: 'bootstrap5',
    timeZone: 'Asia/Manila',
    initialView: isMobile() ? 'listWeek' : 'dayGridMonth',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    expandRows: true,
    contentHeight: 'auto',
    stickyHeaderDates: true,
    navLinks: false,
    dayMaxEvents: true,
    nowIndicator: true,
    handleWindowResize: true,
    eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
    selectAllow: (info) => !isPast(info.start),

    // Load org events
    events: 'events_feed.php',

    // Custom event card (calendar cells)
   eventContent: (arg) => {
  const container = document.createElement('div');
  container.className = 'swks-event';
  const timeLine = arg.timeText || '';

  // label na mismo mula sa feed: "ACA Coordinator" o "<OrgName> Adviser"
  const org = (arg.event.extendedProps && arg.event.extendedProps.org_name)
              || (window.ORG_NAME ? window.ORG_NAME + ' Adviser' : 'Adviser');

  container.innerHTML = `
    <div class="small fw-semibold">${escapeHtml(org)}</div>
    <div class="fw-bold">${escapeHtml(arg.event.title || '')}</div>
    <div class="small">${escapeHtml(timeLine)}</div>
  `;
  return { domNodes: [container] };
},

    // Click date => open Day List modal
    dateClick: (info) => {
    if (isPast(info.date)) return; // ⛔ past dates are unclickable
      if (modalOpen) return;
      openDayModal(new Date(info.dateStr));
      
    },

    // Drag-select => go straight to form with time range
    selectable: true,
    selectMirror: false,
    select: (info) => {
          if (isPast(info.start)) { calendar.unselect(); return; }
      if (modalOpen) return;
      // Use the start day for list context, but open form mode
      selectedDay = startOfDay(info.start);
      switchToFormMode(false, 'Add Event');
      setDateFromDay(selectedDay);             // set hidden date
      if (!info.allDay) {                      // prefill times if timed
        startTime.value = toLocalHM(info.start);
        if (info.end) endTime.value = toLocalHM(info.end);
        allDayHid.value = '0';
      } else {
        startTime.value = ''; endTime.value = '';
        allDayHid.value = '1';
      }
      modal.show();
      calendar.unselect();
    },

    // Click event => edit (form mode)
   eventClick: (info) => {
  if (modalOpen) return;
  const e = info.event;

  // 🔒 view-only kapag hindi ikaw ang gumawa
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

  selectedDay = startOfDay(e.start || new Date());
  switchToFormMode(true, 'Edit Event');

  document.getElementById('event_id').value = e.id;
  titleInp.value = e.title;
  colorInp.value = e.backgroundColor || '#198754';
  descInp.value  = e.extendedProps?.description || '';

  startHidden.value = e.startStr || '';
  endHidden.value   = e.endStr   || '';
  allDayHid.value   = e.allDay ? '1' : '0';

  startTime.value = (!e.allDay && e.start) ? toLocalHM(e.start) : '';
  endTime.value   = (!e.allDay && e.end)   ? toLocalHM(e.end)   : '';

  modal.show();
},


    // Drag/resize quick save
    editable: true,
    eventDrop:  async (info) => { await quickSaveTimes(info.event); },
    eventResize: async (info) => { await quickSaveTimes(info.event); }
  });

  calendar.render();

  // Resize view
  let last = isMobile();
  window.addEventListener('resize', () => {
    const m = isMobile();
    if (m !== last) { calendar.changeView(m ? 'listWeek' : 'dayGridMonth'); last = m; }
  });

  /* ====== Day Modal logic ====== */
  function openDayModal(dayDate) {
    selectedDay = startOfDay(dayDate);
    renderDayList(selectedDay);
    switchToListMode(formatDayHeader(selectedDay));
    modal.show();
  }

 function renderDayList(dayDate) {
  // Clear list
  dayEventsList.innerHTML = '';
  const dayStart = startOfDay(dayDate).getTime();
  const dayEnd   = endOfDay(dayDate).getTime();

  // Get events intersecting this day
  const events = calendar.getEvents().filter(e => {
    const s  = e.start ? e.start.getTime() : 0;
    const ee = e.end ? e.end.getTime() : s;
    return (s < dayEnd) && (ee >= dayStart);
  }).sort((a, b) => {
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
    const org = ev.extendedProps?.org_name
                || (window.ORG_NAME ? window.ORG_NAME + ' Adviser' : 'Adviser');
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
            <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-ev">
              <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger del-ev">
              <i class="bi bi-trash"></i>
            </button>
          ` : ``}
        </div>
      </div>
    `;

    // === Handlers only if owned ===
    item.querySelector('.edit-ev')?.addEventListener('click', () => {
      switchToFormMode(true, 'Edit Event');
      document.getElementById('event_id').value = ev.id;
      titleInp.value = ev.title;
      colorInp.value = ev.backgroundColor || '#198754';
      descInp.value  = ev.extendedProps?.description || '';
      startHidden.value = ev.startStr || '';
      endHidden.value   = ev.endStr   || '';
      allDayHid.value   = ev.allDay ? '1' : '0';
      startTime.value   = (!ev.allDay && ev.start) ? toLocalHM(ev.start) : '';
      endTime.value     = (!ev.allDay && ev.end)   ? toLocalHM(ev.end)   : '';
    });

    item.querySelector('.del-ev')?.addEventListener('click', async () => {
      const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: 'Delete this event?',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc3545'
      });
      if (!isConfirmed) return;

      const fd = new FormData();
      fd.append('event_id', ev.id);

      const res  = await fetch('events_delete.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data?.ok) {
        await Swal.fire({ icon: 'success', title: 'Event deleted', timer: 900, showConfirmButton: false });
        calendar.refetchEvents();
        // Close modal para consistent sa request mo
        modal?.hide?.();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data?.msg || 'Delete failed' });
      }
    });

    dayEventsList.appendChild(item);
  }
}

  // List/Form mode toggles
  function switchToListMode(labelText) {
    modalTitle.textContent = 'Events';
    dayLabel.textContent   = labelText || '—';
    dayListMode.classList.remove('d-none');
    formMode.classList.add('d-none');
    backToListBtn.classList.add('d-none');
    saveBtn.classList.add('d-none');
    deleteBtn.classList.add('d-none');
  }
  function switchToFormMode(isEdit, titleText) {
    modalTitle.textContent = titleText || (isEdit ? 'Edit Event' : 'Add Event');
    dayListMode.classList.add('d-none');
    formMode.classList.remove('d-none');
    backToListBtn.classList.remove('d-none');
    saveBtn.classList.remove('d-none');
    deleteBtn.classList.toggle('d-none', !isEdit);
  }

  // Header buttons
  backToListBtn.addEventListener('click', () => {
    renderDayList(selectedDay);
    switchToListMode(formatDayHeader(selectedDay));
  });
  addEventBtn.addEventListener('click', () => {
    switchToFormMode(false, 'Add Event');
    clearFormOnly();
    setDateFromDay(selectedDay);
  });

  // Helpers
  function setDateFromDay(dayDate) {
    const d = toYMD(dayDate);
    startHidden.value = `${d}`; // all-day by default (no time)
    endHidden.value   = '';     // optional
    allDayHid.value   = '1';
    startTime.value   = '';
    endTime.value     = '';
  }
  function clearFormOnly() {
    document.getElementById('eventForm').reset();
    document.getElementById('event_id').value = '';
    colorInp.value = '#198754';
  }

  // Save (create/update)
  document.getElementById('eventForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const startISOHidden = startHidden.value;
    const endISOHidden   = endHidden.value;
    const startDatePart  = (startISOHidden || '').slice(0,10);
    const endDatePart    = (endISOHidden || startISOHidden || '').slice(0,10);

    let allDay = allDayHid.value === '1';
    if (startTime.value || endTime.value) allDay = false;

    let startOut = startISOHidden;
    if (!allDay && startDatePart && startTime.value) {
      startOut = `${startDatePart}T${startTime.value}`;
    }
    let endOut = endISOHidden;
    if (!allDay && endDatePart && endTime.value) {
      endOut = `${endDatePart}T${endTime.value}`;
    }

    const fd = new FormData(e.target);
    fd.set('start', startOut || '');
    if (endOut) fd.set('end', endOut); else fd.delete('end');
    fd.set('allDay', allDay ? '1' : '0');

    const isEdit = !!document.getElementById('event_id').value;

    try {
      const res  = await fetch('events_save.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data?.ok) {
        calendar.refetchEvents();
        renderDayList(selectedDay);
        switchToListMode(formatDayHeader(selectedDay));
        Swal.fire({ icon: 'success', title: isEdit ? 'Event updated' : 'Event added', timer: 1300, showConfirmButton: false });
      } else {
        throw new Error(data?.msg || 'Save failed');
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Error', text: String(err.message || err) });
    }
  });

  // Quick save for drag/resize
  async function quickSaveTimes(e) {
    try {
      const fd = new FormData();
      fd.append('event_id', e.id);
      fd.append('title', e.title);
      fd.append('start', e.startStr);
      if (e.endStr) fd.append('end', e.endStr);
      fd.append('allDay', e.allDay ? '1' : '0');
      const res = await fetch('events_save.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data?.ok) throw new Error('Update failed');
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Could not update event. Reverting.' });
      calendar.refetchEvents();
    }
  }

  // Utils
  function startOfDay(d){ const x=new Date(d); x.setHours(0,0,0,0); return x; }
  function endOfDay(d){ const x=new Date(d); x.setHours(23,59,59,999); return x; }
  function toYMD(d){ const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`; }
  function toLocalHM(dateObj){ const p=n=>String(n).padStart(2,'0'); return `${p(dateObj.getHours())}:${p(dateObj.getMinutes())}`; }
  function formatTimeRange(s, e){
    const opts = { hour: 'numeric', minute: '2-digit' };
    const st = s ? s.toLocaleTimeString([], opts) : '';
    const et = e ? e.toLocaleTimeString([], opts) : '';
    return (st && et) ? `${st} – ${et}` : st;
  }
  function formatDayHeader(d){
    return d.toLocaleDateString('en-PH', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
  }
  function escapeHtml(str){ return (str||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
});
</script>


</body>
</html>
