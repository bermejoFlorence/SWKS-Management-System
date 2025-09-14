<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

// Get aca coordinator info (example lang, adjust as needed)
$coorQ = $conn->query("SELECT * FROM aca_coordinator_details LIMIT 1");
$coor = $coorQ->fetch_assoc();

// Get organization + adviser + member count
$sql = "SELECT 
            o.org_id, 
            o.org_name, 
            o.org_desc,
            a.adviser_fname,
            a.adviser_email,
            (SELECT COUNT(*) FROM user u2
                WHERE u2.org_id = o.org_id AND u2.user_role = 'member') as total_members
        FROM organization o
        LEFT JOIN user u ON u.org_id = o.org_id AND u.user_role = 'adviser'
        LEFT JOIN adviser_details a ON a.user_id = u.user_id
        ORDER BY o.org_name ASC";
$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}
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
    <!-- Your custom style -->
    <link rel="stylesheet" href="styles/style.css">
</head>
<style>

/* Table Header & Cells */
.table-success, .table-success > th, .table-success > td {
    background-color: var(--swks-green-light) !important;
}
.main-content {
    margin-top: -20px; /* adjust if needed */
}
/* Unassigned Adviser Badge */
.swks-badge-unassigned {
    background: #bbb;
    color: #fff;
    font-weight: 600;
    font-size: 0.9em;
    border-radius: 12px;
    padding: 0.4em 1em;
    display: inline-block;
}

/* Members Badge */
.swks-badge-members {
    background: var(--swks-green-light);
    color: var(--swks-green);
    font-weight: 700;
    font-size: 1.1em;
    border-radius: 18px;
    padding: 0.5em 1.4em;
    box-shadow: 0 1px 4px #0001;
    display: inline-flex;
    align-items: center;
    min-width: 52px;
    justify-content: center;
}

/* SWKS Custom Outline Button */
.btn-swks-outline {
    border: 2px solid var(--swks-green);
    color: var(--swks-green);
    background: #fff;
    font-weight: 600;
    letter-spacing: 1px;
    transition: all 0.15s;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3em;
    padding: 0.4em 1.1em;
}
.btn-swks-outline:hover, .btn-swks-outline:focus {
    background: var(--swks-green);
    color: #fff;
    text-decoration: none;
}
.add-org-btn {
    height: 48px;
    font-size: 1.1em;
    border-radius: 12px;
    white-space: nowrap;
}

/* ACA COORDINATOR STYLE */
.swks-acacoord {
    border: 2px solid var(--swks-green);
    border-radius: 12px;
    padding: 10px 24px;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--swks-green-dark);
    display: inline-block;
    background: #fff;
    margin-bottom: 24px;
    text-transform: uppercase;
}

/* RESPONSIVE: Make badges & buttons smaller, table cells tighter on mobile */
@media (max-width: 576px) {
    .swks-badge-members {
        font-size: 1em;
        padding: 0.28em 0.8em;
        min-width: 44px;
    }
    .btn-swks-outline {
        font-size: 0.92em;
        padding: 0.34em 0.9em;
        border-width: 1.5px;
        min-width: 42px;
    }
    td, th {
        font-size: 0.97em !important;
        padding-left: 0.3em !important;
        padding-right: 0.3em !important;
    }
       .swks-header-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.7rem !important;
    }
        .add-org-btn {
        width: 100%;
        font-size: 1em;
    }
        .swks-acacoord {
        width: 100%;
        font-size: 1em;
        text-align: center;
        padding: 12px 8px;
    }
}

</style>

<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>


 <div class="main-content">
  <div class="container mt-4">
    <h2 class="mb-0" style="color: var(--swks-green); font-weight: bold; padding-bottom:10px;">
      <i class="bi bi-diagram-3-fill me-2"></i>Organization Management
    </h2>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 swks-header-row">
      <div class="swks-acacoord mb-0">
        ACA COORDINATOR: <?= strtoupper(htmlspecialchars($coor['coor_name'])) ?>
      </div>
      <button class="btn btn-success shadow-sm fw-semibold px-4 add-org-btn" data-bs-toggle="modal" data-bs-target="#addOrgModal">
        <i class="bi bi-plus-circle me-1"></i> Add Organization
      </button>
    </div>
    <div class="card border-0 shadow-lg rounded-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-success rounded-4">
              <tr>
                <th style="width:4%">#</th>
                <th style="width:35%">Organization Name</th>
                <th style="width:22%">Adviser</th>
                <th style="width:16%">Total Members</th>
                <th style="width:23%">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $count = 1;
              while ($row = $result->fetch_assoc()) {
                  // Remove 'SWKS' prefix if present
                  $orgName = trim(preg_replace('/^SWKS\s*/i', '', $row['org_name']));

                  if (empty($orgName)) {
                      continue;
                  }

                  $adviserName = $row['adviser_fname'] 
                      ? '<span class="fw-semibold" style="color:var(--swks-green)">' . htmlspecialchars($row['adviser_fname']) . '</span>' 
                      : '<span class="swks-badge-unassigned">Unassigned</span>';

                  $members = $row['total_members'] ? $row['total_members'] : 0;

                  echo "<tr>";
                  echo "<td class='fw-bold'>" . $count . "</td>";
                  echo "<td class='fw-semibold' style='color:var(--swks-green-dark)'>" . htmlspecialchars($orgName) . "</td>";
                  echo "<td>{$adviserName}</td>";
                  echo "<td>
                          <span class='swks-badge-members'>
                              <i class='bi bi-people-fill me-1'></i>{$members}
                          </span>
                        </td>";
                  echo "<td class='text-center'>
                      <a href='org_details.php?org_id={$row['org_id']}' class='btn btn-swks-outline btn-sm rounded-pill d-inline-flex align-items-center justify-content-center px-3 fw-semibold'>
                          <i class='bi bi-eye me-1'></i>
                          View Details
                      </a>
                    </td>";
                  echo "</tr>";

                  $count++;
              }

              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!-- Add Organization Modal -->
<div class="modal fade" id="addOrgModal" tabindex="-1" aria-labelledby="addOrgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success" id="addOrgModalLabel">Add New Organization</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="add_organization.php" method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label for="orgName" class="form-label fw-semibold">Organization Name</label>
            <input type="text" class="form-control" id="orgName" name="org_name" required>
          </div>
          <div class="mb-3">
            <label for="orgDesc" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="orgDesc" name="org_desc" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" class="btn btn-success fw-semibold px-4">Save</button>
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (sessionStorage.getItem('orgAddSuccess')) {
            Swal.fire({
                icon: 'success',
                title: 'Organization Added!',
                text: 'The new organization has been saved successfully.',
                confirmButtonColor: '#198754'
            }).then(() => {
                // Clear the flag so it doesn’t show on refresh
                sessionStorage.removeItem('orgAddSuccess');
                location.reload(); // Reload to show new data
            });
        }
    });
</script>

<script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Sidebar auto-close on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger-btn');
            if(window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && event.target !== hamburger) {
                    sidebar.classList.remove('show');
                }
            }
        });
        // Prevent closing on hamburger click
        document.querySelector('.hamburger-btn').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>
