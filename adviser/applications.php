<?php
include 'includes/auth_adviser.php'; // Access control (adviser only)
include '../database/db_connection.php';

if (isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $updateNotif = $conn->prepare("UPDATE notification SET is_seen = 1 WHERE notification_id = ?");
    $updateNotif->bind_param("i", $notif_id);
    $updateNotif->execute();
    $updateNotif->close();
}

$org_id = $_SESSION['org_id'];
$sql = "SELECT member_id, full_name, course, year_level, date_submitted, status 
        FROM member_details 
        WHERE status = 'pending' AND preferred_org = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Adviser Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Animate.css for modal animation -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Your custom style -->
    <link rel="stylesheet" href="styles/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Main Content Padding (if you use a fixed sidebar/header) */
.main-content {
    margin-left: 210px;
    padding: 90px 22px 32px 22px;
    min-height: 100vh;
    transition: margin-left 0.25s;
}
@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding-top: 82px;
    }
}

/* Table Styles */
.table thead th {
    font-weight: 600;
    font-size: 1.08rem;
    background-color: #f6f9f7;
    color: var(--swks-green-dark);
    border-bottom: 2px solid #e3ede5;
}
.table tbody td {
    vertical-align: middle;
    font-size: 1.02rem;
    background: #fff;
}
.table {
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 0;
}
@media (max-width: 576px) {
    .main-content h2 {
        font-size: 1.25rem;
    }
    .table th, .table td {
        font-size: 0.98rem;
        padding: 0.45rem 0.5rem;
    }
}

/* Dropdown Styles in Table */
.table .dropdown-menu {
    min-width: 8rem;
    padding: 0.2rem 0;
    font-size: 1rem;
    box-shadow: 0 3px 16px rgba(0,0,0,0.10);
    border-radius: 10px;
    border: none;
}
.table .dropdown-item {
    padding: 0.5rem 1rem;
    text-align: center;
    transition: 0.1s;
}
.dropdown-menu .dropdown-item:hover {
    background-color: #e9fbe6;
    color: var(--swks-green-dark);
    font-weight: 500;
}

/* Modal Styles */
.confirm-modal .modal-dialog {
    animation-duration: 0.38s;
}
.confirm-modal .modal-content {
    border-radius: 18px;
    box-shadow: 0 8px 38px rgba(0,0,0,0.15);
}
.confirm-modal .modal-header {
    align-items: center;
    background: #e8f6ea;
    border-bottom: none;
}
.confirm-modal .modal-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px; height: 48px;
    background: var(--swks-green);
    border-radius: 50%;
    margin-right: 10px;
}
.confirm-modal .modal-title {
    margin-bottom: 0 !important;
    color: var(--swks-green);
}
.confirm-modal .modal-footer {
    background: transparent;
    border-top: none;
    padding-top: 0.7rem;
    padding-bottom: 1.2rem;
}
.confirm-modal .btn-primary, 
.confirm-modal .btn {
    background: var(--swks-green) !important;
    border: none;
    color: #fff !important;
    font-weight: 600;
}
.confirm-modal .btn-primary:hover, 
.confirm-modal .btn:hover {
    background: var(--swks-green-dark) !important;
}

.confirm-modal .btn-cancel {
    background: #e8f6ea !important;     /* Light green background */
    color: #043c00 !important;          /* Main green text */
    border: none;
    font-weight: 600;
    border-radius: 8px;
    min-width: 120px;
    transition: background 0.2s;
}
.confirm-modal .btn-cancel:hover {
    background: #c2efd1 !important;     /* Slightly darker on hover */
    color: #186a1a !important;
}
.confirm-modal .btn-continue {
    background: #043c00 !important;     /* Main dark green */
    color: #fff !important;
    border: none;
    font-weight: 600;
    border-radius: 8px;
    min-width: 140px;
    transition: background 0.2s;
}
.confirm-modal .btn-continue:hover {
    background: #186a1a !important;     /* Slightly lighter on hover */
}

    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
        <h2 class="mb-4 fw-bold" style="color: #186a1a;">New Applicants</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 shadow-sm rounded" style="background:#fff;">
                <thead class="table-light">
                    <tr class="align-middle text-center">
                        <th class="text-nowrap">#</th>
                        <th class="text-nowrap">Full Name</th>
                        <th class="text-nowrap">Course</th>
                        <th class="text-nowrap">Year Level</th>
                        <th class="text-nowrap">Date Applied</th>
                        <th class="text-nowrap">Status</th>
                    </tr>
                </thead>
                    <tbody>
                    <?php
                    $num = 1;
                    foreach ($result as $row):
                    ?>
                    <tr class="text-center">
                        <td><?= $num++ ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['course']) ?></td>
                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                        <td>
                            <?= htmlspecialchars(date('F d, Y', strtotime($row['date_submitted']))) ?>
                        </td>
                        <td>
                            <div class="dropdown dropend">
                                <button class="btn btn-sm 
                                    <?= $row['status'] == 'pending' ? 'btn-warning' : ($row['status']=='approved' ? 'btn-success' : 'btn-danger') ?> 
                                    dropdown-toggle"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= ucfirst($row['status']) ?>
                                </button>
                                <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item status-action" href="#"
                                    data-member="<?= $row['member_id'] ?>"
                                    data-status="approved">
                                        <i class="bi bi-check-circle me-1 text-success"></i> Approve
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item status-action" href="#"
                                    data-member="<?= $row['member_id'] ?>"
                                    data-status="rejected">
                                        <i class="bi bi-x-circle me-1 text-danger"></i> Reject
                                    </a>
                                </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                <!-- Confirmation Modal -->
                <div class="modal fade confirm-modal" id="confirmStatusModal" tabindex="-1" aria-labelledby="confirmStatusLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered animate__animated animate__zoomIn">
                    <div class="modal-content">
                    <div class="modal-header border-0 pb-0" style="background: #e8f6ea;">
                        <div class="modal-icon-wrap me-2" style="background: #043c00;">
                        <i class="bi bi-question-circle-fill" style="font-size: 2.3rem; color: #fff;"></i>
                        </div>
                        <h5 class="modal-title fw-bold" id="confirmStatusLabel" style="color: #043c00; font-size: 1.22rem;">
                        Confirm Action
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3" id="confirmModalBody" style="font-size:1.13rem;">
                        <!-- JS will insert text here -->
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-continue" id="confirmStatusBtn">Yes, Continue</button>
                    </div>
                    </div>
                </div>
                </div>
        </div>
    </div>
    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger-btn');
            if(window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && event.target !== hamburger) {
                    sidebar.classList.remove('show');
                }
            }
        });
        document.querySelector('.hamburger-btn').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        let selectedMemberId = null;
        let selectedStatus = null;

        document.querySelectorAll('.status-action').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                selectedMemberId = this.getAttribute('data-member');
                selectedStatus = this.getAttribute('data-status');
                let actionText = selectedStatus === 'approved' ? 'approve' : 'reject';
                document.getElementById('confirmModalBody').textContent = 
                    `Are you sure you want to ${actionText} this applicant?`;
                let confirmModal = new bootstrap.Modal(document.getElementById('confirmStatusModal'));
                confirmModal.show();
            });
        });
        document.getElementById('confirmStatusBtn').addEventListener('click', function() {
            if (selectedMemberId && selectedStatus) {
                fetch('update_applicant_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `member_id=${selectedMemberId}&status=${selectedStatus}`
                })
                .then(response => response.text())
                .then(data => {
                    var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmStatusModal'));
                    if (confirmModal) confirmModal.hide();
                    Swal.fire({
                        icon: selectedStatus === 'approved' ? 'success' : 'info',
                        title: selectedStatus === 'approved' ? 'Approved!' : 'Rejected!',
                        text: selectedStatus === 'approved'
                            ? 'The applicant has been approved.'
                            : 'The applicant has been rejected.',
                        confirmButtonColor: '#043c00'
                    }).then(() => {
                        location.reload();
                    });
                });
            }
        });
    </script>
</body>
</html>
