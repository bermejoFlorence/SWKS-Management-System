<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

if ($start && $end) {
    $endPhp   = (new DateTime($end))->format('Y-m-d H:i:s');
    $startPhp = (new DateTime($start))->format('Y-m-d H:i:s');

    $sql = "SELECT e.event_id, e.title, e.start_datetime, e.end_datetime, e.all_day,
                   e.description, e.color, o.org_name
            FROM org_events e
            LEFT JOIN organization o ON o.org_id = e.org_id
            WHERE (e.start_datetime < ? AND COALESCE(e.end_datetime, e.start_datetime) >= ?)
            ORDER BY e.start_datetime ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode([]); exit; }
    $stmt->bind_param('ss', $endPhp, $startPhp);
} else {
    $sql = "SELECT e.event_id, e.title, e.start_datetime, e.end_datetime, e.all_day,
                   e.description, e.color, o.org_name
            FROM org_events e
            LEFT JOIN organization o ON o.org_id = e.org_id
            ORDER BY e.start_datetime ASC";
    $stmt = $conn->prepare($sql);
}

/* Defaults */
$totalOrgs = 0;
$totalMembers = 0;
$validatedRequests = 0;

/* Total organizations */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM organization")) {
    $row = $res->fetch_assoc();
    $totalOrgs = (int)($row['c'] ?? 0);
    $res->free();
}

/* Total approved members */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM member_details WHERE status='approved'")) {
    $row = $res->fetch_assoc();
    $totalMembers = (int)($row['c'] ?? 0);
    $res->free();
}

/* Pending validated requests */
if ($res = $conn->query("SELECT COUNT(*) AS c FROM borrow_requests WHERE status='validated'")) {
    $row = $res->fetch_assoc();
    $validatedRequests = (int)($row['c'] ?? 0);
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ACA Coordinator Dashboard</title>
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

    <!-- Your Custom Styles -->
    <link rel="stylesheet" href="styles/style.css">

    <!-- Dashboard-Specific Modern Styles -->
<style>
    /* ========== GLOBAL MODERN THEME ========== */
    :root {
        --swks-primary: #4caf50;        /* Vibrant, friendly green */
        --swks-primary-dark: #388e3c;   /* Slightly deeper for hover */
        --swks-accent: #81c784;         /* Playful leaf green for highlights */
        --swks-light: #e8f5e9;          /* Soft mint background */
        --swks-pale: #f1f8e9;           /* Whisper cream for cards */
        --swks-text: #263238;           /* Soft dark gray (not black) */
        --swks-muted: #607d8b;          /* Gentle blue-gray */
        --swks-border: #c8e6c9;         /* Soft green border */
        --swks-shadow-sm: 0 2px 8px rgba(76, 175, 80, 0.08);
        --swks-shadow-md: 0 6px 20px rgba(76, 175, 80, 0.12);
        --swks-transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .main-content {
        background-color: var(--swks-pale);
        background-image: url("image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30,10 C50,5 70,20 75,40 C80,60 65,80 45,85 C25,90 5,75 0,55 C-5,35 10,15 30,10 Z' fill='%234caf50' fill-opacity='0.03'/%3E%3C/svg%3E");
        background-size: 300px;
        background-attachment: fixed;
        padding: 90px 22px 40px;
        min-height: 100vh;
        transition: margin-left 0.25s;
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    /* ========== STATS CARDS ========== */
    .stat-card {
        background: white;
        border-radius: 28px;
        box-shadow: var(--swks-shadow-sm);
        border: 1px solid var(--swks-border);
        transition: var(--swks-transition);
        height: 100%;
        overflow: hidden;
        position: relative;
        backdrop-filter: blur(8px);
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--swks-shadow-md);
    }

    /* Accent top border on hover */
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--swks-accent);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }

    .stat-card:hover::before {
        transform: scaleX(1);
    }

    .stat-card .card-body {
        padding: 2rem;
        text-align: center;
    }

    .stat-number {
        font-size: 2.8rem;
        font-weight: 800;
        color: var(--swks-primary);
        margin-bottom: 0.3rem;
        line-height: 1;
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    .stat-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--swks-text);
        margin-bottom: 0.4rem;
    }

    .stat-subtitle {
        font-size: 0.95rem;
        color: var(--swks-muted);
        margin-bottom: 1.5rem;
        line-height: 1.5;
    }

    .stat-btn {
        background: var(--swks-accent);
        color: white;
        border: none;
        border-radius: 50px;
        padding: 0.7rem 1.8rem;
        font-weight: 600;
        font-size: 1rem;
        transition: var(--swks-transition);
        box-shadow: 0 4px 12px rgba(129, 199, 132, 0.25);
        letter-spacing: 0.5px;
    }

    .stat-btn:hover {
        background: var(--swks-primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
    }

    /* Color variants */
    .stat-primary .stat-number { color: #2196f3; }
    .stat-primary .stat-btn {
        background: #2196f3;
        box-shadow: 0 4px 12px rgba(33, 150, 243, 0.25);
    }
    .stat-primary .stat-btn:hover { background: #1976d2; }

    .stat-warning .stat-number { color: #ff9800; }
    .stat-warning .stat-btn {
        background: #ff9800;
        box-shadow: 0 4px 12px rgba(255, 152, 0, 0.25);
    }
    .stat-warning .stat-btn:hover { background: #f57c00; }

    /* ========== CALENDAR CARD ========== */
    .calendar-card {
        background: white;
        border-radius: 32px;
        box-shadow: var(--swks-shadow-sm);
        border: 1px solid var(--swks-border);
        transition: var(--swks-transition);
        overflow: hidden;
    }

    .calendar-card:hover {
        box-shadow: var(--swks-shadow-md);
        transform: translateY(-4px);
    }

    .calendar-card .card-header {
        background: rgba(76, 175, 80, 0.03);
        border-bottom: 1px solid var(--swks-border);
        padding: 1.5rem 2rem;
        border-radius: 32px 32px 0 0;
    }

    .calendar-card .card-body {
        padding: 2rem;
    }

    /* ========== FULLCALENDAR ========== */
    .calendar-card .fc {
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    .calendar-card .fc-toolbar h2 {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--swks-primary);
        letter-spacing: -0.5px;
    }

    .calendar-card .fc .fc-toolbar .fc-button {
        background: white !important;
        color: var(--swks-primary) !important;
        border: 2px solid var(--swks-primary) !important;
        border-radius: 50px !important;
        padding: 0.6rem 1.4rem !important;
        font-weight: 700 !important;
        text-transform: none !important;
        box-shadow: none !important;
        transition: var(--swks-transition) !important;
        font-size: 0.95rem;
    }

    .calendar-card .fc .fc-toolbar .fc-button:hover {
        background: var(--swks-primary) !important;
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
    }

    .calendar-card .fc .fc-toolbar .fc-button.fc-button-active,
    .calendar-card .fc .fc-toolbar .fc-button:active {
        background: var(--swks-primary-dark) !important;
        color: white !important;
        border-color: var(--swks-primary-dark) !important;
    }

    /* Header Cells */
    .calendar-card .fc .fc-col-header-cell {
        background: rgba(76, 175, 80, 0.05) !important;
        border: none !important;
        padding: 1rem 0 !important;
    }

    .calendar-card .fc .fc-col-header-cell-cushion {
        font-weight: 800 !important;
        color: var(--swks-primary) !important;
        font-size: 0.95rem !important;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Day Numbers */
    .calendar-card .fc .fc-daygrid-day-number {
        font-weight: 800 !important;
        font-size: 1.1rem !important;
        padding: 0.6rem !important;
        border-radius: 50% !important;
        width: 2.4rem !important;
        height: 2.4rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 auto !important;
        background: transparent !important;
        color: var(--swks-text) !important;
        transition: var(--swks-transition) !important;
    }

    .calendar-card .fc .fc-day-today .fc-daygrid-day-number {
        background: rgba(129, 199, 132, 0.2) !important;
        color: var(--swks-primary) !important;
        font-weight: 900 !important;
        transform: scale(1.05);
    }

    .calendar-card .fc .fc-day-past .fc-daygrid-day-number {
        color: #bbb !important;
        background: rgba(0,0,0,0.01) !important;
    }

    /* Events */
    .calendar-card .fc .fc-event {
        background: var(--swks-accent) !important;
        border: none !important;
        border-radius: 14px !important;
        padding: 0.6rem 1rem !important;
        font-size: 0.9rem !important;
        font-weight: 700 !important;
        color: white !important;
        margin: 3px 5px !important;
        transition: var(--swks-transition) !important;
        box-shadow: 0 2px 8px rgba(129, 199, 132, 0.2);
    }

    .calendar-card .fc .fc-event:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 6px 16px rgba(129, 199, 132, 0.3);
    }

    .calendar-card .fc .fc-event-main {
        color: white !important;
        line-height: 1.3 !important;
        font-weight: 700 !important;
    }

    /* List View */
    .calendar-card .fc .fc-list-event {
        background: rgba(76, 175, 80, 0.03) !important;
        border-radius: 18px !important;
        margin: 0.6rem 0 !important;
        border: none !important;
        transition: var(--swks-transition) !important;
    }

    .calendar-card .fc .fc-list-event:hover {
        background: rgba(76, 175, 80, 0.08) !important;
        transform: translateX(6px);
    }

    .calendar-card .fc .fc-list-event-title {
        font-weight: 700 !important;
        color: var(--swks-text) !important;
    }

    .calendar-card .fc .fc-list-day-cushion {
        background: rgba(76, 175, 80, 0.08) !important;
        color: var(--swks-primary) !important;
        font-weight: 800 !important;
        border-radius: 14px !important;
        padding: 1rem !important;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* ========== MODAL ========== */
    .modal-content {
        border-radius: 30px !important;
        box-shadow: 0 16px 48px rgba(76, 175, 80, 0.18) !important;
        border: none !important;
        border: 1px solid rgba(76, 175, 80, 0.1) !important;
    }

    .modal-header {
        background: rgba(76, 175, 80, 0.03);
        border-bottom: 2px solid var(--swks-border) !important;
        padding: 1.8rem 2.5rem !important;
        border-radius: 30px 30px 0 0 !important;
    }

    .modal-title {
        font-weight: 800 !important;
        font-size: 1.5rem !important;
        color: var(--swks-primary) !important;
        letter-spacing: -0.5px;
    }

    .modal-body {
        padding: 2.5rem !important;
    }

    .modal-footer {
        border-top: 2px solid var(--swks-border) !important;
        padding: 1.8rem 2.5rem !important;
    }

    /* Day List Items */
    #dayEventsList .list-group-item {
        background: white !important;
        color: var(--swks-text) !important;
        border: 1px solid var(--swks-border) !important;
        border-radius: 18px !important;
        padding: 1.2rem !important;
        margin-bottom: 1rem !important;
        transition: var(--swks-transition) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }

    #dayEventsList .list-group-item:hover {
        background: rgba(76, 175, 80, 0.05) !important;
        transform: translateX(6px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }

    #dayEventsList .list-group-item .btn {
        border-radius: 50% !important;
        width: 40px !important;
        height: 40px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        transition: var(--swks-transition) !important;
        font-size: 1.1rem;
    }

    #dayEventsList .list-group-item .btn-outline-primary {
        color: var(--swks-primary) !important;
        border-color: var(--swks-primary) !important;
    }

    #dayEventsList .list-group-item .btn-outline-primary:hover {
        background: var(--swks-primary) !important;
        color: white !important;
        transform: scale(1.1);
    }

    #dayEventsList .list-group-item .btn-outline-danger {
        color: #f44336 !important;
        border-color: #f44336 !important;
    }

    #dayEventsList .list-group-item .btn-outline-danger:hover {
        background: #f44336 !important;
        color: white !important;
        transform: scale(1.1);
    }

    /* Form Inputs */
    .form-control {
        border-radius: 14px !important;
        padding: 0.9rem 1.2rem !important;
        border: 2px solid #e0e0e0 !important;
        font-size: 1.05rem !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        transition: var(--swks-transition) !important;
        background: white;
    }

    .form-control:focus {
        border-color: var(--swks-primary) !important;
        box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.15) !important;
        background: rgba(232, 245, 233, 0.5);
    }

    .form-label {
        font-weight: 700 !important;
        margin-bottom: 0.6rem !important;
        color: var(--swks-text) !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 1.05rem;
    }

    /* Buttons */
    .btn-primary {
        background: var(--swks-accent) !important;
        border: none !important;
        border-radius: 50px !important;
        padding: 0.8rem 2rem !important;
        font-weight: 700 !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        transition: var(--swks-transition) !important;
        box-shadow: 0 5px 18px rgba(129, 199, 132, 0.3) !important;
        font-size: 1.05rem;
        letter-spacing: 0.5px;
    }

    .btn-primary:hover {
        background: var(--swks-primary) !important;
        transform: translateY(-4px) scale(1.03) !important;
        box-shadow: 0 10px 28px rgba(76, 175, 80, 0.4) !important;
    }

    .btn-secondary,
    .btn-outline-secondary {
        border-radius: 50px !important;
        padding: 0.8rem 2rem !important;
        font-weight: 700 !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        transition: var(--swks-transition) !important;
        font-size: 1.05rem;
    }

    .btn-outline-danger {
        border-radius: 50px !important;
        padding: 0.8rem 2rem !important;
        font-weight: 700 !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        transition: var(--swks-transition) !important;
        font-size: 1.05rem;
    }

    /* Empty State */
    #dayEmpty {
        padding: 3rem !important;
        font-size: 1.2rem !important;
        color: var(--swks-muted) !important;
        border-radius: 20px !important;
        background: rgba(0,0,0,0.01) !important;
        text-align: center;
        font-family: 'Segoe UI', Arial, sans-serif;
        border: 2px dashed var(--swks-border);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stat-card .card-body {
            padding: 1.5rem !important;
        }
        .stat-number {
            font-size: 2.3rem !important;
        }
        .calendar-card .card-header,
        .calendar-card .card-body {
            padding: 1.2rem !important;
        }
        .modal-body {
            padding: 1.8rem !important;
        }
        .modal-header,
        .modal-footer {
            padding: 1.2rem !important;
        }
    }

    @media (max-width: 576px) {
        .stat-number {
            font-size: 2.1rem !important;
        }
        .stat-title {
            font-size: 1.05rem !important;
        }
        .btn {
            padding: 0.7rem 1.8rem !important;
            font-size: 1rem !important;
        }
        .modal-content {
            margin: 10px;
        }
    }
</style>

    <script>
        window.ORG_NAME = 'All Organizations';
    </script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Top Stats Cards -->
        <div class="row g-4 justify-content-center text-center mt-2">
            <!-- Total Orgs -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card h-100">
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="stat-number"><?= $totalOrgs ?></div>
                        <div class="stat-title">Total Organizations</div>
                        <div class="stat-subtitle">All registered orgs</div>
                        <a href="organization.php" class="stat-btn mt-auto">
                            View details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Total Members -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card stat-primary h-100">
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="stat-number"><?= $totalMembers ?></div>
                        <div class="stat-title">Total Members</div>
                        <div class="stat-subtitle">Approved members</div>
                        <a href="organization.php" class="stat-btn mt-auto">
                            View details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Validated Borrow Items -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card stat-warning h-100">
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="stat-number"><?= $validatedRequests ?></div>
                        <div class="stat-title">Pending Validated Borrow Items</div>
                        <div class="stat-subtitle">Awaiting admin action</div>
                        <a href="inventory.php" class="stat-btn mt-auto">
                            View details
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="calendar-card">
                    <div class="card-header">
                        <h5 class="fw-bold mb-0">Calendar (Asia/Manila)</h5>
                    </div>
                    <div class="card-body">
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
                                    <input type="color" class="form-control form-control-color" name="color" id="color" value="#043c00">
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
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('show');
        }
        document.addEventListener('click', function(e) {
            const s = document.getElementById('sidebar');
            const h = document.querySelector('.hamburger-btn');
            if (window.innerWidth <= 992 && s?.classList.contains('show')) {
                if (!s.contains(e.target) && e.target !== h) {
                    s.classList.remove('show');
                }
            }
        });
        const hamburger = document.querySelector('.hamburger-btn');
        hamburger?.addEventListener('click', e => e.stopPropagation());
    </script>

    <!-- Calendar logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('calendar');
            if (!el) return;

            // PH time helpers
            function manilaTodayStart() {
                const nowPH = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                nowPH.setHours(0, 0, 0, 0);
                return nowPH;
            }
            function isPast(date, startOfDay = true) {
                const d = new Date(date);
                if (startOfDay) d.setHours(0, 0, 0, 0);
                return d < manilaTodayStart();
            }
            const startOfDay = d => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; };
            const endOfDay = d => { const x = new Date(d); x.setHours(23, 59, 59, 999); return x; };
            const toYMD = d => { const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; };
            const toLocalHM = d => { const p = n => String(n).padStart(2, '0'); return `${p(d.getHours())}:${p(d.getMinutes())}`; };
            function formatTimeRange(s, e) {
                const opt = { hour: 'numeric', minute: '2-digit' };
                const st = s ? s.toLocaleTimeString([], opt) : '';
                const et = e ? e.toLocaleTimeString([], opt) : '';
                return (st && et) ? `${st} – ${et}` : st;
            }
            function formatDayHeader(d) {
                return d.toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
            }
            const escapeHtml = str => (str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '<', '>': '>', '"': '&quot;', "'": '&#39;' }[m]));

            // Modal + state
            const modalEl = document.getElementById('eventModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
            let modalOpen = false, selectedDay = null, calendar;

            // UI refs
            const dayListMode = document.getElementById('dayListMode');
            const formMode = document.getElementById('formMode');
            const backToListBtn = document.getElementById('backToListBtn');
            const saveBtn = document.getElementById('saveBtn');
            const deleteBtn = document.getElementById('deleteBtn');
            const addEventBtn = document.getElementById('addEventBtn');
            const dayLabel = document.getElementById('dayLabel');
            const dayEventsList = document.getElementById('dayEventsList');
            const dayEmpty = document.getElementById('dayEmpty');
            const modalTitle = document.getElementById('modalTitle');

            // Form refs
            const startHidden = document.getElementById('start');
            const endHidden = document.getElementById('end');
            const allDayHid = document.getElementById('allDayHidden');
            const titleInp = document.getElementById('title');
            const colorInp = document.getElementById('color');
            const descInp = document.getElementById('description');
            const startTime = document.getElementById('startTime');
            const endTime = document.getElementById('endTime');

            modalEl.addEventListener('shown.bs.modal', () => modalOpen = true);
            modalEl.addEventListener('hidden.bs.modal', () => {
                modalOpen = false;
                calendar?.unselect?.();
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
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                expandRows: true,
                contentHeight: 'auto',
                stickyHeaderDates: true,
                navLinks: false,
                dayMaxEvents: true,
                nowIndicator: true,
                handleWindowResize: true,
                eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
                selectAllow: info => !isPast(info.start),
                events: 'events_feed.php',

                eventContent: (arg) => {
                    const container = document.createElement('div');
                    container.className = 'swks-event';
                    const timeLine = arg.timeText || '';
                    const org = (arg.event.extendedProps && arg.event.extendedProps.org_name) || window.ORG_NAME || 'All Organizations';
                    container.innerHTML = `
                        <div class="small fw-semibold">${escapeHtml(org)}</div>
                        <div class="fw-bold">${escapeHtml(arg.event.title || '')}</div>
                        <div class="small">${escapeHtml(timeLine)}</div>
                    `;
                    return { domNodes: [container] };
                },

                dateClick: (info) => {
                    if (isPast(info.date)) return;
                    if (modalOpen) return;
                    openDayModal(new Date(info.dateStr));
                },

                selectable: true,
                selectMirror: false,
                select: (info) => {
                    if (isPast(info.start)) { calendar.unselect(); return; }
                    if (modalOpen) return;
                    selectedDay = startOfDay(info.start);
                    switchToFormMode(false, 'Add Event');
                    setDateFromDay(selectedDay);
                    if (!info.allDay) {
                        startTime.value = toLocalHM(info.start);
                        if (info.end) endTime.value = toLocalHM(info.end);
                        allDayHid.value = '0';
                    } else {
                        startTime.value = '';
                        endTime.value = '';
                        allDayHid.value = '1';
                    }
                    modal.show();
                    calendar.unselect();
                },

                eventClick: (info) => {
                    if (modalOpen) return;
                    const e = info.event;

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
                    colorInp.value = e.backgroundColor || '#043c00';
                    descInp.value = e.extendedProps?.description || '';
                    startHidden.value = e.startStr || '';
                    endHidden.value = e.endStr || '';
                    allDayHid.value = e.allDay ? '1' : '0';
                    startTime.value = (!e.allDay && e.start) ? toLocalHM(e.start) : '';
                    endTime.value = (!e.allDay && e.end) ? toLocalHM(e.end) : '';

                    modal.show();
                },

                editable: true,
                eventDrop: async info => { await quickSaveTimes(info.event); },
                eventResize: async info => { await quickSaveTimes(info.event); }
            });

            calendar.render();

            let last = isMobile();
            window.addEventListener('resize', () => {
                const m = isMobile();
                if (m !== last) {
                    calendar.changeView(m ? 'listWeek' : 'dayGridMonth');
                    last = m;
                }
            });

            // Day modal logic
            function openDayModal(day) {
                selectedDay = startOfDay(day);
                renderDayList(selectedDay);
                switchToListMode(formatDayHeader(selectedDay));
                modal.show();
            }

            function renderDayList(dayDate) {
                dayEventsList.innerHTML = '';
                const dayStart = startOfDay(dayDate).getTime();
                const dayEnd = endOfDay(dayDate).getTime();

                const events = calendar.getEvents().filter(e => {
                    const s = e.start ? e.start.getTime() : 0;
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

                    if (isOwned) {
                        item.querySelector('.edit-ev').addEventListener('click', () => {
                            switchToFormMode(true, 'Edit Event');
                            document.getElementById('event_id').value = ev.id;
                            titleInp.value = ev.title;
                            colorInp.value = ev.backgroundColor || '#043c00';
                            descInp.value = ev.extendedProps?.description || '';
                            startHidden.value = ev.startStr || '';
                            endHidden.value = ev.endStr || '';
                            allDayHid.value = ev.allDay ? '1' : '0';
                            startTime.value = (!ev.allDay && ev.start) ? toLocalHM(ev.start) : '';
                            endTime.value = (!ev.allDay && ev.end) ? toLocalHM(ev.end) : '';
                        });

                        item.querySelector('.del-ev').addEventListener('click', async () => {
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

                            const res = await fetch('events_delete.php', { method: 'POST', body: fd });
                            const data = await res.json();

                            if (data?.ok) {
                                calendar.refetchEvents();
                                renderDayList(selectedDay);
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Event deleted',
                                    timer: 1200,
                                    showConfirmButton: false
                                }).then(() => {
                                    const modalEl = document.getElementById('eventModal');
                                    if (modalEl) {
                                        const modal = bootstrap.Modal.getInstance(modalEl);
                                        modal.hide();
                                    }
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data?.msg || 'Delete failed' });
                            }
                        });
                    }

                    dayEventsList.appendChild(item);
                }
            }

            function switchToListMode(label) {
                modalTitle.textContent = 'Events';
                dayLabel.textContent = label || '—';
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

            backToListBtn.addEventListener('click', () => {
                renderDayList(selectedDay);
                switchToListMode(formatDayHeader(selectedDay));
            });

            addEventBtn.addEventListener('click', () => {
                switchToFormMode(false, 'Add Event');
                clearFormOnly();
                setDateFromDay(selectedDay);
            });

            function setDateFromDay(day) {
                const d = toYMD(day);
                startHidden.value = `${d}`;
                endHidden.value = '';
                allDayHid.value = '1';
                startTime.value = '';
                endTime.value = '';
            }

            function clearFormOnly() {
                document.getElementById('eventForm').reset();
                document.getElementById('event_id').value = '';
                colorInp.value = '#043c00';
            }

            document.getElementById('eventForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const startISOHidden = startHidden.value;
                const endISOHidden = endHidden.value;
                const startDatePart = (startISOHidden || '').slice(0, 10);
                const endDatePart = (endISOHidden || startISOHidden || '').slice(0, 10);

                let allDay = allDayHid.value === '1';
                if (startTime.value || endTime.value) allDay = false;

                let startOut = startISOHidden;
                if (!allDay && startDatePart && startTime.value) { startOut = `${startDatePart}T${startTime.value}`; }
                let endOut = endISOHidden;
                if (!allDay && endDatePart && endTime.value) { endOut = `${endDatePart}T${endTime.value}`; }

                const fd = new FormData(e.target);
                fd.set('start', startOut || '');
                if (endOut) fd.set('end', endOut); else fd.delete('end');
                fd.set('allDay', allDay ? '1' : '0');

                const isEdit = !!document.getElementById('event_id').value;

                try {
                    const res = await fetch('events_save.php', { method: 'POST', body: fd });
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
                    if (!data?.ok) throw new Error(data?.msg || 'Update failed');
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not update event. Reverting.' });
                    calendar.refetchEvents();
                }
            }
        });
    </script>
</body>
</html>