<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

// Get org_id from query string
$org_id = intval($_GET['org_id'] ?? 0);

// Get organization details
$sql_org = "SELECT o.*, u.user_id as adviser_user_id, a.adviser_fname, a.adviser_email
            FROM organization o
            LEFT JOIN user u ON u.org_id = o.org_id AND u.user_role = 'adviser'
            LEFT JOIN adviser_details a ON a.user_id = u.user_id
            WHERE o.org_id = ?";

$stmt = $conn->prepare($sql_org);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$org = $stmt->get_result()->fetch_assoc();

if (!$org) {
    die('<div class="alert alert-danger m-5">Organization not found.</div>');
}
$adviser = $org['adviser_fname'] ? $org['adviser_fname'] : 'Unassigned';

// Get member list ------------- IMPORTANT: may student_id na -------------
$sql_members = "SELECT 
                  m.full_name,
                  m.ay,
                  m.course,
                  m.student_id,
                  u.created_at
                FROM member_details m
                JOIN user u ON m.user_id = u.user_id
                WHERE u.org_id = ? AND u.user_role = 'member'
                ORDER BY m.full_name ASC";
$stmt2 = $conn->prepare($sql_members);
$stmt2->bind_param("i", $org_id);
$stmt2->execute();
$members_result = $stmt2->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organization Details</title>
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
.edit-org-btn {
    height: 48px;
    font-size: 1.1em;
    border-radius: 12px;
    white-space: nowrap;
}
/* ADVISER STYLE */
.swks-adviser {
    border: 2px solid var(--swks-green);
    border-radius: 12px;
    padding: 10px 24px;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--swks-green-dark);
    background: #fff;
    text-transform: uppercase;
    margin-bottom: 0;
}

/* Responsive Header Row */
.swks-header-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

@media (max-width: 576px) {
    .swks-header-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.7rem !important;
    }
    .edit-org-btn {
        width: 100%;
        font-size: 1em;
    }
    .swks-adviser {
        width: 100%;
        font-size: 1em;
        text-align: center;
        padding: 12px 8px;
    }
}
.button-stack {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.w-btn {
  white-space: nowrap;
}

@media (max-width: 576px) {
  .w-btn {
    width: 100%;
    justify-content: center;
    text-align: center;
  }
}

</style>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
  <div class="container mt-4">
    <h2 class="mb-0" style="color: var(--swks-green); font-weight: bold; padding-bottom:10px;">
      <i class="bi bi-people-fill me-2"></i><?= htmlspecialchars($org['org_name']) ?> Members
    </h2>
    <div class="swks-header-row mb-4">
      <div class="swks-adviser mb-0">
        ADVISER: <?= strtoupper(htmlspecialchars($adviser)) ?>
      </div>
      <div class="button-stack">
        <button class="btn btn-success shadow-sm fw-semibold px-4 w-btn" data-bs-toggle="modal" data-bs-target="#editOrgModal">
          <i class="bi bi-pencil-square me-1"></i> Edit Organization
        </button>
         <a href="organization.php" class="btn btn-outline-secondary shadow-sm fw-semibold px-4 edit-org-btn">
          ← Back to Organizations
        </a>
      </div>
    </div>

    <div class="card border-0 shadow-lg rounded-4">
      <div class="card-body p-0">

        <!-- 🔎 Search bar (by Student ID) -->
        <div class="table-tools d-flex justify-content-end align-items-center gap-2 p-3 pb-2">
          <div class="input-group" style="max-width: 320px;">
            <span class="input-group-text bg-white border-2"><i class="bi bi-search"></i></span>
            <input type="text" id="studentSearch" class="form-control border-2"
                   placeholder="Search by student ID...">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-success rounded-4">
              <tr>
                <th style="width:4%">#</th>
                <th style="width:18%">Student ID Number</th>
                <th>Name of Student</th>
                <th>Year</th>
                <th>Course</th>
                <th>Date Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $count = 1;
              while ($member = $members_result->fetch_assoc()) {
                  $studId   = (string)($member['student_id'] ?? '');
                  $studIdDs = strtolower(str_replace(' ', '', $studId)); // for data attribute

                  echo "<tr class='member-row' data-studid='" . htmlspecialchars($studIdDs, ENT_QUOTES) . "'>";
                  echo "<td class='fw-bold'>{$count}</td>";
                  echo "<td class='fw-semibold'>" . htmlspecialchars($studId) . "</td>";
                  echo "<td class='fw-semibold'>" . htmlspecialchars($member['full_name']) . "</td>";
                  echo "<td>" . htmlspecialchars($member['ay']) . "</td>";
                  echo "<td>" . htmlspecialchars($member['course'] ?? '') . "</td>";
                  echo "<td>" . htmlspecialchars(date('F j, Y', strtotime($member['created_at']))) . "</td>";
                  echo "</tr>";
                  $count++;
              }

              if ($count === 1) {
                  // no members at all
                  echo "<tr><td colspan='6' class='text-center text-muted py-4'>No members found.</td></tr>";
              } else {
                  // row for "no matches" kapag nagsa-search
                  echo "<tr id='memberNoResults' class='d-none'>
                          <td colspan='6' class='text-center text-muted py-4'>No matching members.</td>
                        </tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Organization Modal (unchanged) -->
  <div class="modal fade" id="editOrgModal" tabindex="-1" aria-labelledby="editOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-sm">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold text-success" id="editOrgModalLabel">Edit Organization</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="update_organization.php" method="POST">
          <input type="hidden" name="org_id" value="<?= $org['org_id'] ?>">
          <div class="modal-body">
            <div class="mb-3">
              <label for="adviserName" class="form-label fw-semibold">Adviser Name</label>
              <input type="text" class="form-control" name="adviser_name" id="adviserName" value="<?= htmlspecialchars($org['adviser_fname']) ?>">
            </div>
            <div class="mb-3">
              <label for="adviserEmail" class="form-label fw-semibold">Adviser Email</label>
             <input type="email" class="form-control" name="adviser_email" id="adviserEmail" 
                value="<?= htmlspecialchars($org['adviser_email']) ?>" 
                pattern="^[a-zA-Z0-9._%+-]+@cbsua\.edu\.ph$"
                title="Please use a valid @cbsua.edu.ph email" required>
            </div>
            <div class="mb-3">
              <label for="editOrgName" class="form-label fw-semibold">Organization Name</label>
              <input type="text" class="form-control" id="editOrgName" name="org_name" value="<?= htmlspecialchars($org['org_name']) ?>" required>
            </div>
            <div class="mb-3">
              <label for="editOrgDesc" class="form-label fw-semibold">Description</label>
              <textarea class="form-control" id="editOrgDesc" name="org_desc" rows="3" required><?= htmlspecialchars($org['org_desc']) ?></textarea>
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

  <!-- Confirmation Modal (unchanged) -->
  <div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-labelledby="confirmSaveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold text-danger" id="confirmSaveLabel">
            Confirm Changes
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body fw-semibold text-center">
          Are you sure you want to apply these changes?
        </div>
        <div class="modal-footer border-0 justify-content-center">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success px-4" id="confirmSubmitBtn">Yes, Save</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Show success alert after organization update
  if (sessionStorage.getItem('orgEditSuccess')) {
    Swal.fire({
      icon: 'success',
      title: 'Organization Updated!',
      text: 'The organization information was successfully saved.',
      confirmButtonColor: '#198754'
    }).then(() => {
      sessionStorage.removeItem('orgEditSuccess');
      location.reload();
    });
  }

  const urlParams = new URLSearchParams(window.location.search);

  // Show error alert if invalid institutional email
  if (urlParams.get('invalid_email') === '1') {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Email',
      text: 'Only institutional emails ending in @cbsua.edu.ph are allowed.',
      confirmButtonColor: '#d33'
    }).then(() => {
      urlParams.delete('invalid_email');
      const query = urlParams.toString();
      const newUrl = window.location.pathname + (query ? '?' + query : '');
      window.history.replaceState({}, '', newUrl);
    });
  }

  // Show error alert if duplicate email
  if (urlParams.get('duplicate_email') === '1') {
    Swal.fire({
      icon: 'error',
      title: 'Email Already Used',
      text: 'This email is already associated with another adviser.',
      confirmButtonColor: '#d33'
    }).then(() => {
      urlParams.delete('duplicate_email');
      const query = urlParams.toString();
      const newUrl = window.location.pathname + (query ? '?' + query : '');
      window.history.replaceState({}, '', newUrl);
    });
  }
});

const editForm = document.querySelector('#editOrgModal form');
const confirmModal = new bootstrap.Modal(document.getElementById('confirmSaveModal'));
const editModalEl = document.getElementById('editOrgModal');
const editModal = new bootstrap.Modal(editModalEl);

editForm.addEventListener('submit', function (e) {
  e.preventDefault(); // Stop auto-submit
  editModal.hide();   // Close edit modal
  setTimeout(() => confirmModal.show(), 300); // Delay to smooth transition
});

document.getElementById('confirmSubmitBtn').addEventListener('click', () => {
  if (editForm.checkValidity()) {
    editForm.submit(); // Valid → proceed
  } else {
    editForm.reportValidity(); // Invalid → show built-in warning
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
        if(window.innerWidth <= 992 && sidebar?.classList.contains('show')) {
            if (!sidebar.contains(event.target) && event.target !== hamburger) {
                sidebar.classList.remove('show');
            }
        }
    });
    // Prevent closing on hamburger click
    document.querySelector('.hamburger-btn')?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
</script>

<!-- 🔎 JS filter by student ID -->
<script>
(function(){
  const input = document.getElementById('studentSearch');
  const getRows = () => Array.from(document.querySelectorAll('tr.member-row'));
  const noRow  = document.getElementById('memberNoResults');

  const norm = s => (s || '')
      .toString()
      .toLowerCase()
      .replace(/\s+/g, '')    // tanggal spaces
      .trim();

  function applyFilter() {
    const q = norm(input.value);
    let shown = 0;

    getRows().forEach(tr => {
      const sid = norm(tr.dataset.studid || '');
      const hit = !q || sid.includes(q);
      tr.style.display = hit ? '' : 'none';
      if (hit) shown++;
    });

    if (noRow) {
      noRow.classList.toggle('d-none', shown !== 0);
    }
  }

  input?.addEventListener('input', applyFilter);
})();
</script>

</body>
</html>
