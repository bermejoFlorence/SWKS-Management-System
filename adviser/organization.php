<?php
include_once 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$org_id  = $_SESSION['org_id']  ?? 0;
$orgName = $_SESSION['org_name'] ?? 'Members';

/* Members of adviser’s org (approved only) + Date Joined from user.created_at */
$members = [];
if ($org_id) {
 $sql = "
  SELECT
    md.member_id,
    md.user_id,
    md.full_name,
    md.course,
    md.year_level,
    md.status,                 -- ← include this
    u.created_at AS joined_at
  FROM member_details md
  LEFT JOIN user u ON u.user_id = md.user_id
  WHERE md.preferred_org = ?
    AND md.status IN ('approved','deactivated','deactivate','')  -- include both spellings + blank
  ORDER BY (md.status='approved') DESC, md.full_name ASC
";



  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $members[] = $row;
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($orgName) ?> Members</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="stylesheet" href="styles/style.css">

  <style>
    /* ===== LAYOUT: main content fits beside sidebar (adjust width if your sidebar differs) ===== */
    :root { --sidebar-w: 240px; }
    html, body { width:100%; max-width:100%; overflow-x:hidden; }
    #sidebar { width: var(--sidebar-w); }

    .main-content{
      margin-left: var(--sidebar-w);
      width: calc(100% - var(--sidebar-w));
      max-width: calc(100% - var(--sidebar-w));
      padding: 24px 16px;
      padding-top: 70px;
      overflow-x: hidden;  /* stop page-level horizontal scroll */
    }
    @media (max-width: 992px){
      .main-content{ margin-left:0; width:100%; max-width:100%; padding:16px 12px; padding-top:70px; }
    }

    /* ===== Table look ===== */
    .page-title{ font-weight:800; letter-spacing:.3px; margin-bottom:.75rem; }
    .card{ border-radius:16px; transition:box-shadow .2s; }
    .card:hover{ box-shadow:0 8px 32px rgba(0,0,0,.10); }
    .table thead th{ background:#e6f0e7; color:#143d1f; font-weight:700; }

    /* Fit on desktop; wrap long text to avoid overflow */
    .table-fit { table-layout: fixed; width:100%; min-width: 720px; } /* min width so headers stay readable on mobile */
    th, td { vertical-align:middle; white-space:normal; word-break:break-word; }
    .table-sm > :not(caption) > * > *{ padding:.65rem .9rem; }

    /* compact columns */
    th.col-num    { width:60px; }
    th.col-year   { width:120px; }
    th.col-date   { width:160px; }
    th.col-action { width:150px; }

    .name-link{ color:#0f6a2e; font-weight:700; text-decoration:none; }
    .name-link:hover{ text-decoration:underline; }
    .btn-outline-deactivate{
      border:2px solid #14532d; color:#14532d; border-radius:999px; font-weight:700; padding:.35rem .9rem;
    }

  /* --- Admin-style pill buttons (with hover) --- */
.btn-pill-brand{
  display: inline-flex;          /* one line: icon + text */
  align-items: center;
  gap: .45rem;                   /* space between icon and text */
  white-space: nowrap;           /* don't wrap */
  border:2px solid #0f6a2e;
  color:#0f6a2e;
  background:#fff;
  border-radius:999px;
  padding:.45rem .95rem;
  font-weight:700;
  line-height:1;
  transition:background-color .15s ease, color .15s ease, box-shadow .15s ease, transform .08s ease;
}
.btn-pill-brand:hover,
.btn-pill-brand:focus{
  background:#0f6a2e;
  color:#fff;
  box-shadow:0 6px 16px rgba(15,106,46,.22);
  transform:translateY(-1px);
}
.btn-pill-brand:active{
  transform:translateY(0);
  box-shadow:0 3px 10px rgba(15,106,46,.25);
}

/* red version for Deactivate (optional) */
.btn-pill-danger{
  display: inline-flex;          /* one line: icon + text */
  align-items: center;
  gap: .45rem;
  white-space: nowrap;
  border:2px solid #dc3545;
  color:#dc3545;
  background:#fff;
  border-radius:999px;
  padding:.45rem .95rem;
  font-weight:700;
  line-height:1;
  transition:background-color .15s ease, color .15s ease, box-shadow .15s ease, transform .08s ease;
}
.btn-pill-danger:hover,
.btn-pill-danger:focus{
  background:#dc3545;
  color:#fff;
  box-shadow:0 6px 16px rgba(220,53,69,.22);
  transform:translateY(-1px);
}
.btn-pill-brand:disabled,
.btn-pill-danger:disabled{
  opacity:.55;
  cursor:not-allowed;
  transform:none;
  box-shadow:none;
}

/* (optional) paluwagin ang Action column para di siksik */
th.col-action { width: 180px; }   /* dati 150px */
/* status badge sa tabi ng name */
.badge-status{
  display:inline-block;
  padding:.15rem .55rem;
  border-radius:999px;
  font-size:.75rem;
  font-weight:700;
  vertical-align:middle;
  margin-left:.35rem;
}
.badge-status.approved{
  color:#0f6a2e;
  background:rgba(15,106,46,.12);
  border:1px solid rgba(15,106,46,.28);
}
.badge-status.deactivated{
  color:#6b7280;
  background:#eef2f7;
  border:1px solid #d1d5db;
}

/* gray pill for 'Deactivated' (disabled) */
.btn-pill-muted{
  display:inline-flex; align-items:center; gap:.45rem; white-space:nowrap;
  border:2px solid #9ca3af; color:#6b7280; background:#fff; border-radius:999px;
  padding:.45rem .95rem; font-weight:700; line-height:1;
}
.btn-pill-muted:disabled{ opacity:.9; cursor:not-allowed; }

  </style>
</head>
<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <h2 class="page-title"><?= htmlspecialchars($orgName) ?> Members</h2>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <!-- Keep table layout on all screens; on small screens this div provides horizontal scroll -->
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-fit">
            <thead>
              <tr>
                <th class="col-num">#</th>
                <th>Name</th>
                <th class="col-year">Year Level</th>
                <th>Course</th>
                <th class="col-date">Date Joined</th>
                <th class="text-center col-action">Action</th>
              </tr>
            </thead>
           <tbody>
<?php if (!$members): ?>
  <tr>
    <td colspan="6" class="text-center text-muted py-4">No approved members yet.</td>
  </tr>
<?php else: ?>
  <?php $i=1; foreach ($members as $m): ?>
    <?php
      // Normalize status once per row, then reuse everywhere
      $rawStatus  = trim((string)($m['status'] ?? ''));
      // Treat anything not exactly 'approved' as deactivated (covers '', 'deactivate', 'deactivated')
      $statusNorm = ($rawStatus === 'approved') ? 'approved' : 'deactivated';
      $statusText = ucfirst($statusNorm);
      $joined     = $m['joined_at'] ?? null;
    ?>
    <tr>
      <td><?= $i++ ?></td>

      <!-- Name + status badge -->
      <td>
        <a class="name-link" href="member_view.php?id=<?= (int)$m['member_id'] ?>">
          <?= htmlspecialchars($m['full_name'] ?: '—') ?>
        </a>
        <span class="badge-status <?= $statusNorm === 'approved' ? 'approved' : 'deactivated' ?>">
          <?= $statusText ?>
        </span>
      </td>

      <td><?= htmlspecialchars($m['year_level'] ?: '—') ?></td>
      <td><?= htmlspecialchars($m['course'] ?: '—') ?></td>

      <td><?= $joined ? htmlspecialchars(date('M d, Y', strtotime($joined))) : '—' ?></td>

      <!-- Action -->
      <td class="text-center">
        <?php if ($statusNorm === 'approved'): ?>
          <button
            type="button"
            class="btn btn-pill-danger btn-deactivate"
            data-member-id="<?= (int)$m['member_id'] ?>"
            data-member-name="<?= htmlspecialchars($m['full_name']) ?>"
          >
            <i class="bi bi-slash-circle"></i><span>Deactivate</span>
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-pill-muted" disabled>
            <i class="bi bi-slash-circle"></i><span>Deactivated</span>
          </button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
<?php endif; ?>
</tbody>


          </table>
        </div>
      </div>
    </div>

  </div><!-- /.main-content -->

  <script>
    // Sidebar toggle for mobile
    function toggleSidebar(){ document.getElementById('sidebar')?.classList.toggle('show'); }
    document.addEventListener('click', function(e){
      const sidebar = document.getElementById('sidebar');
      const hamburger = document.querySelector('.hamburger-btn');
      if (window.innerWidth <= 992 && sidebar?.classList.contains('show')) {
        if (!sidebar.contains(e.target) && e.target !== hamburger) sidebar.classList.remove('show');
      }
    });
    document.querySelector('.hamburger-btn')?.addEventListener('click', e => e.stopPropagation());
  </script>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-deactivate');
  if (!btn) return;

  const id   = btn.getAttribute('data-member-id');
  const name = btn.getAttribute('data-member-name') || 'this member';

  const { isConfirmed } = await Swal.fire({
    title: 'Deactivate member?',
    html: `Are you sure you want to deactivate <b>${escapeHtml(name)}</b>?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, deactivate',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc3545'
  });
  if (!isConfirmed) return;

  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('member_id', id);

    const res  = await fetch('member_deactivate.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data?.ok) {
      const tr = btn.closest('tr');

      // update badge
      let badge = tr.querySelector('.badge-status');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge-status deactivated';
        tr.querySelector('td:nth-child(2)')?.appendChild(badge);
      }
      badge.classList.remove('approved');
      badge.classList.add('deactivated');
      badge.textContent = 'Deactivated';

      // replace button with disabled pill
      btn.outerHTML = `
        <button type="button" class="btn btn-pill-muted" disabled>
          <i class="bi bi-slash-circle"></i><span>Deactivated</span>
        </button>
      `;

      Swal.fire({ icon: 'success', title: 'Deactivated', text: data.msg || 'Member has been deactivated.' });
    } else {
      throw new Error(data?.msg || 'Deactivation failed.');
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error', text: String(err.message || err) });
    btn.disabled = false;
  }

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
});
</script>


</body>
</html>
