<?php
// include 'includes/auth_member.php'; // kung may access control
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

// Org name for "<Org> Adviser" label
$org_id  = $_SESSION['org_id']  ?? 0;
$orgName = $_SESSION['org_name'] ?? null;
if (!$orgName && $org_id) {
  if ($stmt = $conn->prepare("SELECT org_name FROM organization WHERE org_id = ?")) {
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $stmt->bind_result($orgName);
    $stmt->fetch();
    $stmt->close();
  }
}
$orgName = $orgName ?: 'Organization';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Member Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.11/index.global.min.js"></script>

  <!-- Site CSS -->
  <link rel="stylesheet" href="styles/style.css">

  <!-- Calendar theme – same as adviser -->
  <style>
    /* Cards / layout (keep your defaults) */
    .card{border-radius:16px;transition:box-shadow .2s}
    .card:hover{box-shadow:0 8px 32px rgba(0,0,0,.11)}
    .display-4{font-size:2.8rem}
    .main-content .card{border-radius:18px;transition:box-shadow .2s}
    .main-content .card:hover{box-shadow:0 8px 32px rgba(0,0,0,.12)}
    .main-content{padding-top:70px}
    @media (max-width:575px){.display-4{font-size:2.2rem}}

    /* Calendar (scoped) */
    #calendar{min-height:520px}
    @media (max-width:575px){#calendar{min-height:420px}}

    .calendar-card .fc .btn{
      border-radius:10px;font-weight:600;box-shadow:0 2px 10px rgba(0,0,0,.06)
    }
    .calendar-card .fc .btn-primary,
    .calendar-card .btn-primary{
      --bs-btn-bg:#0f6a2e;--bs-btn-border-color:#0f6a2e;
      --bs-btn-hover-bg:#0c5625;--bs-btn-hover-border-color:#0c5625;
      --bs-btn-active-bg:#0a4a20;--bs-btn-active-border-color:#0a4a20
    }
    .calendar-card .fc .btn-outline-primary,
    .calendar-card .btn-outline-primary{
      --bs-btn-color:#0f6a2e;--bs-btn-border-color:#0f6a2e;
      --bs-btn-hover-bg:#0f6a2e;--bs-btn-hover-border-color:#0f6a2e;--bs-btn-hover-color:#fff
    }
    .calendar-card .fc .fc-toolbar-title{font-weight:700}
    .calendar-card .fc .fc-col-header-cell-cushion,
    .calendar-card .fc .fc-daygrid-day-number{font-weight:600}
    .calendar-card .fc-day-today{background:rgba(15,106,46,.08)!important}

    /* Past days: grey */
    .calendar-card .fc .fc-daygrid-day.fc-day-past,
    .calendar-card .fc .fc-timegrid-col.fc-day-past{background:#f2f4f7}
    .calendar-card .fc-day-past .fc-daygrid-day-number{color:#6b7280}
    .calendar-card .fc-day-past .fc-daygrid-day-top{cursor:not-allowed}

    /* Event chips */
    .calendar-card .fc .fc-event{background:#198754;border-color:#198754;border-radius:10px}
    /* White text ONLY on tiles (not list view) */
    .calendar-card .fc .fc-daygrid-event .fc-event-main,
    .calendar-card .fc .fc-timegrid-event .fc-event-main,
    .calendar-card .fc .fc-timegrid-event .fc-event-time{color:#fff!important}
    .calendar-card .fc .fc-daygrid-event .fc-event-time,
    .calendar-card .fc .fc-daygrid-event .fc-event-title{color:#fff!important}
    .calendar-card .fc .fc-daygrid-event .swks-event,
    .calendar-card .fc .fc-timegrid-event .swks-event,
    .calendar-card .fc .fc-daygrid-event .swks-event *,
    .calendar-card .fc .fc-timegrid-event .swks-event *{color:#fff!important}
    .calendar-card .swks-event .small{line-height:1.1}
    .calendar-card .swks-event .fw-bold{line-height:1.15}

    /* "+N more" */
    .calendar-card .fc .fc-daygrid-more-link{color:#0f6a2e;font-weight:600;text-decoration:underline}
    .calendar-card .fc .fc-daygrid-more-link:hover{color:#0c5625}

    /* Keep events clear on past cells */
    .calendar-card .fc-day-past .fc-event,
    .calendar-card .fc-day-past .fc-event .fc-event-main{opacity:1;filter:none}

    /* Day modal (list) – dark for contrast */
    #dayEventsList .list-group-item{background:#1f2937;color:#fff;border-color:#1f2937}
    #dayEventsList .list-group-item .text-muted{color:rgba(255,255,255,.75)!important}
    #dayEventsList .list-group-item:hover,#dayEventsList .list-group-item:focus{background:#111827;color:#fff}
    /* === SWKS Green Calendar Theme (FullCalendar + Bootstrap5) === */
.calendar-card {
  /* Tweak these 4 tokens if you want a slightly different green */
  --swks-green-100: #eaf7ef;
  --swks-green-400: #7bd29a;
  --swks-green-600: #45ba6f;
  --swks-green-700: #2ea85c;
  --swks-green-800: #1f7a3c;
}

/* Title (November 2025) */
.calendar-card .fc .fc-toolbar-title{
  color: var(--swks-green-800);
  font-weight: 800;
  letter-spacing: .2px;
}

/* Make grid rounded like a card */
.calendar-card .fc .fc-scrollgrid{
  border-radius: 18px;
  overflow: hidden;
  border-color: #e7efe9;
}

/* Header (Sun–Sat) */
.calendar-card .fc .fc-col-header th{
  background: var(--swks-green-100);
  color: var(--swks-green-800);
  border-color: #d9e8dd;
}
.calendar-card .fc .fc-col-header-cell-cushion{ font-weight: 700; }

/* Day cells & lines (subtle) */
.calendar-card .fc .fc-daygrid-day,
.calendar-card .fc .fc-timegrid-slot{
  border-color: #eef4ef;
}

/* Today highlight */
.calendar-card .fc .fc-day-today{
  background: rgba(69,186,111,.10) !important;
}
.calendar-card .fc .fc-day-today .fc-daygrid-day-number{
  font-weight: 800;
  color: var(--swks-green-800);
}

/* Prev/Next buttons as round green pills */
.calendar-card .fc .fc-prev-button,
.calendar-card .fc .fc-next-button{
  width: 42px; height: 42px;
  border-radius: 999px !important;
  background: var(--swks-green-600);
  border-color: var(--swks-green-600);
  color: #fff;
  display: inline-flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(69,186,111,.25);
}
.calendar-card .fc .fc-prev-button:hover,
.calendar-card .fc .fc-next-button:hover{
  background: var(--swks-green-700);
  border-color: var(--swks-green-700);
}

/* "today" button as soft green pill */
.calendar-card .fc .fc-today-button.btn{
  border-radius: 999px !important;
  background: var(--swks-green-400);
  border-color: var(--swks-green-400);
  color: #fff;
  font-weight: 700;
}
.calendar-card .fc .fc-today-button.btn:disabled{
  opacity: .65; color:#fff; background: var(--swks-green-400); border-color: var(--swks-green-400);
}

/* View switcher (month / week / day / list) – green pills */
.calendar-card .fc .btn{
  border-radius: 999px !important;
  font-weight: 700;
  box-shadow: 0 3px 12px rgba(69,186,111,.12);
}
.calendar-card .fc .btn-primary{
  background: var(--swks-green-600);
  border-color: var(--swks-green-600);
}
.calendar-card .fc .btn-primary:hover,
.calendar-card .fc .btn-primary:focus{
  background: var(--swks-green-700);
  border-color: var(--swks-green-700);
}
.calendar-card .fc .btn-primary.active,
.calendar-card .fc .btn-primary:active{
  background: var(--swks-green-800);
  border-color: var(--swks-green-800);
}

/* Event chips = green with white text */
.calendar-card .fc .fc-event{
  background: var(--swks-green-600);
  border-color: var(--swks-green-600);
  border-radius: 10px;
}
.calendar-card .fc .fc-daygrid-event .fc-event-main,
.calendar-card .fc .fc-timegrid-event .fc-event-main,
.calendar-card .fc .fc-timegrid-event .fc-event-time{
  color: #fff !important;
}

/* "+N more" link color */
.calendar-card .fc .fc-daygrid-more-link{
  color: var(--swks-green-800);
  font-weight: 700;
  text-decoration: underline;
}
.calendar-card .fc .fc-daygrid-more-link:hover{
  color: var(--swks-green-700);
}

/* Past days (light gray-green) */
.calendar-card .fc .fc-daygrid-day.fc-day-past,
.calendar-card .fc .fc-timegrid-col.fc-day-past{
  background: #f3f7f4;
}
.calendar-card .fc-day-past .fc-daygrid-day-number{ color:#8aa295; }

  </style>

  <script>window.ORG_NAME = <?= json_encode($orgName) ?>;</script>
</head>
<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- Calendar Section (same structure as adviser) -->
    <div class="row g-4 mt-1">
      <div class="col-12">
        <div class="card shadow-sm calendar-card">
          <div class="card-body">
            <h5 class="fw-bold mb-3">CALENDAR EVENTS</h5>
            <div id="calendar"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Day List Modal (read-only) -->
  <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Events</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0" id="dayLabel">—</h6>
          </div>
          <div id="dayEventsList" class="list-group small"></div>
          <div id="dayEmpty" class="text-muted text-center py-4 d-none">No events for this day.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  // Sidebar toggle (unchanged)
  function toggleSidebar(){document.getElementById('sidebar')?.classList.toggle('show')}
  document.addEventListener('click',function(e){
    const s=document.getElementById('sidebar'),h=document.querySelector('.hamburger-btn');
    if(window.innerWidth<=992 && s?.classList.contains('show')){
      if(!s.contains(e.target) && e.target!==h) s.classList.remove('show');
    }
  });
  document.querySelector('.hamburger-btn')?.addEventListener('click',e=>e.stopPropagation());
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendar');
    if (!el) return;

    // Single modal instance
    const modalEl = document.getElementById('eventModal');
    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });

    const dayLabel      = document.getElementById('dayLabel');
    const dayEventsList = document.getElementById('dayEventsList');
    const dayEmpty      = document.getElementById('dayEmpty');

    const isMobile = () => window.innerWidth < 576;

    const calendar = new FullCalendar.Calendar(el, {
      themeSystem: 'bootstrap5',
      timeZone: 'Asia/Manila',
      initialView: isMobile() ? 'listWeek' : 'dayGridMonth',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      expandRows: true,
      contentHeight: 'auto',
      stickyHeaderDates: true,
      navLinks: false,
      dayMaxEvents: true,
      nowIndicator: true,
      handleWindowResize: true,
      editable: false,    // READ-ONLY
      selectable: false,  // READ-ONLY

      // Use Day modal for "+N more"
      moreLinkClick: (arg) => { openDayModal(arg.date); return false; },

      eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },

      // Same-folder feed: member/events_feed.php
      events: 'events_feed.php',

      // Chip content (same as adviser)
      eventContent: (arg) => {
        const div = document.createElement('div');
        div.className = 'swks-event';
        const timeLine = arg.timeText || '';
        const org = (arg.event.extendedProps && arg.event.extendedProps.org_name)
                    || window.ORG_NAME || 'Organization';
        div.innerHTML = `
          <div class="small fw-semibold">${escapeHtml(org)} Adviser</div>
          <div class="fw-bold">${escapeHtml(arg.event.title || '')}</div>
          <div class="small">${escapeHtml(timeLine)}</div>
        `;
        return { domNodes: [div] };
      },

      // Click day/event -> open Day list
      dateClick: (info) => openDayModal(new Date(info.dateStr)),
      eventClick: (info) => openDayModal(info.event.start || new Date())
    });

    calendar.render();

    // Switch view on resize (mobile ↔ desktop)
    let last = isMobile();
    window.addEventListener('resize', () => {
      const m = isMobile();
      if (m !== last) { calendar.changeView(m ? 'listWeek' : 'dayGridMonth'); last = m; }
    });

    /* ===== Day list modal (read-only) ===== */
    function openDayModal(dayDate) {
      renderDayList(dayDate);
      document.getElementById('modalTitle').textContent = 'Events';
      dayLabel.textContent = formatDayHeader(dayDate);
      modal.show();
    }

    function renderDayList(dayDate) {
      dayEventsList.innerHTML = '';
      const dayStart = startOfDay(dayDate).getTime();
      const dayEnd   = endOfDay(dayDate).getTime();

      const events = calendar.getEvents()
        .filter(e => {
          const s = e.start ? e.start.getTime() : 0;
          const eEnd = e.end ? e.end.getTime() : s;
          return (s < dayEnd) && (eEnd >= dayStart);
        })
        .sort((a,b) => {
          if (a.allDay !== b.allDay) return a.allDay ? -1 : 1;
          const as = a.start ? a.start.getTime() : 0;
          const bs = b.start ? b.start.getTime() : 0;
          return as - bs;
        });

      if (events.length === 0) { dayEmpty.classList.remove('d-none'); return; }
      dayEmpty.classList.add('d-none');

      for (const ev of events) {
        const timeText = ev.allDay ? '' : formatTimeRange(ev.start, ev.end);
        const org = (ev.extendedProps && ev.extendedProps.org_name) || window.ORG_NAME || 'Organization';
        const item = document.createElement('div');
        item.className = 'list-group-item';
        item.innerHTML = `
          <div class="small fw-semibold">${escapeHtml(org)} Adviser</div>
          <div class="fw-bold">${escapeHtml(ev.title || '')}</div>
          <div class="small text-muted">${escapeHtml(timeText)}</div>
        `;
        dayEventsList.appendChild(item);
      }
    }

    // Utils
    function startOfDay(d){ const x=new Date(d); x.setHours(0,0,0,0); return x; }
    function endOfDay(d){ const x=new Date(d); x.setHours(23,59,59,999); return x; }
    function formatTimeRange(s, e){
      const opts = { hour: 'numeric', minute: '2-digit' };
      const st = s ? s.toLocaleTimeString([], opts) : '';
      const et = e ? e.toLocaleTimeString([], opts) : '';
      return (st && et) ? `${st} – ${et}` : st;
    }
    function formatDayHeader(d){
      return d.toLocaleDateString('en-PH', { weekday:'long', month:'short', day:'numeric', year:'numeric' });
    }
    function escapeHtml(str){
      return (str||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
  });
  </script>
</body>
</html>
