<?php
session_start();
include_once '../database/db_connection.php';
include_once 'includes/auth_adviser.php';

// Mark notification as seen when opened via notif link
if (isset($_GET['notif_id']) && isset($_SESSION['user_id'])) {
    $notif_id = (int) $_GET['notif_id'];
    $user_id  = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notification SET is_seen = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

$org_id = $_SESSION['org_id'] ?? 0;

// Borrow Requests table (per request) with custom ordering
$reqListQ = $conn->prepare("
    SELECT 
        br.request_id,
        br.status,
        br.created_at,
        bri.item_id,
        bri.quantity_requested AS qty,
        ii.name  AS item_name,
        ii.image AS item_image,
        COALESCE(md.full_name, u.user_email) AS member_name,
        md.course,                 -- ✅ add
        md.year_level              -- ✅ add
    FROM borrow_requests br
    JOIN borrow_request_items bri ON bri.request_id = br.request_id
    JOIN inventory_items ii ON ii.item_id = bri.item_id
    JOIN user u ON u.user_id = br.user_id
    LEFT JOIN member_details md ON md.user_id = u.user_id
    WHERE br.org_id = ?
    ORDER BY FIELD(br.status,'pending','validated','approved','rejected'), br.created_at DESC
");

$reqListQ->bind_param("i", $org_id);
$reqListQ->execute();
$reqList = $reqListQ->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Borrow Requests – Adviser</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css">
  <style>
    .card { border-radius: 16px; }
    .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
    .item-thumb { width:38px; height:42px; object-fit:cover; border:1px solid #ddd; border-radius:6px; }
    /* View Details button styles */
.btn-view-details{
  display:inline-flex; align-items:center; gap:6px;
  font-weight:600; border-radius:50px; padding:8px 16px;
  transition:all .2s ease-in-out; border-width:2px;
}
.btn-view-details i{ font-size:1rem; }

/* Outline (default) */
.btn-view-details-outline{
  background:transparent; color:#064d00; border:2px solid #064d00;
}
.btn-view-details-outline:hover{
  background:#064d00; color:#fff;
}

/* Filled (optional variant you can use if you want it solid) */
.btn-view-details-filled{
  background:#064d00; color:#fff; border:2px solid #064d00;
}
.btn-view-details-filled:hover{
  background:#053a00; border-color:#053a00;
}
/* Header layout */
.header-bar { row-gap: 8px; }
.filter-status { max-width: 240px; }
.search-wrap { min-width: 240px; max-width: 340px; }

/* Responsive */
@media (max-width: 992px){
  .filter-status { max-width: 220px; }
  .search-wrap { max-width: 300px; flex: 1 1 auto; }
}
@media (max-width: 576px){
  .header-bar { flex-direction: column; align-items: stretch; }
  .filter-status, .search-wrap { max-width: none; width: 100%; }
}

  </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content" style="padding-top:70px;">
  <div class="container mt-4">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 header-bar">
  <h2 class="mb-0 text-success fw-bold me-auto">
    <i class="bi bi-list-check me-2"></i>Borrow Requests
  </h2>

  <div class="input-group filter-status">
    <label class="input-group-text" for="filterStatus"><i class="bi bi-funnel"></i></label>
    <select id="filterStatus" class="form-select">
      <option value="">All statuses</option>
      <option value="pending">Pending</option>
      <option value="validated">Validated</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>

  <div class="search-wrap">
    <input id="searchInput" class="form-control" placeholder="Search (item / member / date)…">
  </div>
</div>



    <div class="card border-0 shadow-lg rounded-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-success">
              <tr>
                <th style="width:7%; text-align:center;">#</th>
                <th>Item</th>
                <th>Member</th>
                <th style="width:10%; text-align:center;">Qty</th>
                <th style="width:18%;">Date</th>
                <th style="width:14%; text-align:center;">Status</th>
                <th style="width:24%; text-align:center;">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php $i=1; while($r = $reqList->fetch_assoc()): 
              $s = $r['status'];
              $badge = ($s==='pending')?'warning':(($s==='validated')?'info':(($s==='approved')?'success':'secondary'));
            ?>
              <tr>
                <td class="text-center fw-semibold"><?= $i++ ?></td>
                <td class="fw-semibold">
                  <?php if (!empty($r['item_image'])): ?>
                    <img src="/swks/<?= htmlspecialchars($r['item_image']) ?>" class="item-thumb me-2" alt="">
                  <?php endif; ?>
                  <?= htmlspecialchars($r['item_name']) ?>
                </td>
                <td><?= htmlspecialchars($r['member_name']) ?></td>
                <td class="text-center"><?= (int)$r['qty'] ?></td>
                <td><?= date('F j, Y g:i A', strtotime($r['created_at'])) ?></td>
                <td class="text-center">
                  <span class="badge bg-<?= $badge ?> px-3 py-2 text-uppercase"><?= htmlspecialchars($s) ?></span>
                </td>
                  <td class="text-center">
                    <?php if ($s === 'pending'): ?>
                      <button class="btn btn-success me-2"
                              onclick="openRowModal(this,'validate')"
                              data-request-id="<?= (int)$r['request_id'] ?>"
                              data-item-id="<?= (int)$r['item_id'] ?>"
                              data-item-name="<?= htmlspecialchars($r['item_name'], ENT_QUOTES) ?>"
                              data-item-image="<?= htmlspecialchars($r['item_image'] ?? '', ENT_QUOTES) ?>"
                              data-member="<?= htmlspecialchars($r['member_name'], ENT_QUOTES) ?>"
                              data-course="<?= htmlspecialchars($r['course'] ?? '', ENT_QUOTES) ?>"
                              data-year="<?= htmlspecialchars($r['year_level'] ?? '', ENT_QUOTES) ?>"
                              data-qty="<?= (int)$r['qty'] ?>"
                              data-created="<?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?>">
                        Validate
                      </button>

                      <button class="btn btn-danger"
                              onclick="openRowModal(this,'reject')"
                              data-request-id="<?= (int)$r['request_id'] ?>"
                              data-item-id="<?= (int)$r['item_id'] ?>"
                              data-item-name="<?= htmlspecialchars($r['item_name'], ENT_QUOTES) ?>"
                              data-item-image="<?= htmlspecialchars($r['item_image'] ?? '', ENT_QUOTES) ?>"
                              data-member="<?= htmlspecialchars($r['member_name'], ENT_QUOTES) ?>"
                              data-course="<?= htmlspecialchars($r['course'] ?? '', ENT_QUOTES) ?>"
                              data-year="<?= htmlspecialchars($r['year_level'] ?? '', ENT_QUOTES) ?>"
                              data-qty="<?= (int)$r['qty'] ?>"
                              data-created="<?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?>">
                        Reject
                      </button>
                    <?php else: ?>
                      <button class="btn-view-details btn-view-details-outline"
                              onclick="openRowModal(this,'view')"
                              data-request-id="<?= (int)$r['request_id'] ?>"
                              data-item-id="<?= (int)$r['item_id'] ?>"
                              data-item-name="<?= htmlspecialchars($r['item_name'], ENT_QUOTES) ?>"
                              data-item-image="<?= htmlspecialchars($r['item_image'] ?? '', ENT_QUOTES) ?>"
                              data-member="<?= htmlspecialchars($r['member_name'], ENT_QUOTES) ?>"
                              data-course="<?= htmlspecialchars($r['course'] ?? '', ENT_QUOTES) ?>"
                              data-year="<?= htmlspecialchars($r['year_level'] ?? '', ENT_QUOTES) ?>"
                              data-qty="<?= (int)$r['qty'] ?>"
                              data-created="<?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?>">
                        View details
                      </button>
                    <?php endif; ?>
                  </td>

              </tr>
            <?php endwhile; ?>

            <tr id="noDataRow" class="d-none">
  <td colspan="7" class="text-center text-muted py-4">
    No data found.
  </td>
</tr>

            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
<!-- Per-request Modal -->
<div class="modal fade" id="rowRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success">
          <i class="bi bi-box-seam-fill me-2"></i>Borrow Request
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="text-center mb-3" id="rowItemImgWrap" style="display:none;">
          <img id="rowItemImg" src="" alt="Item Image"
               class="rounded" style="width:120px;height:130px;object-fit:cover;border:1px solid #ccc;">
        </div>

        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Item :</div><div class="col-7" id="rowItemName"></div></div>
        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Name :</div><div class="col-7" id="rowMember"></div></div>
        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Year :</div><div class="col-7" id="rowYear"></div></div>
        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Course :</div><div class="col-7" id="rowCourse"></div></div>
        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Quantity :</div><div class="col-7" id="rowQty"></div></div>
        <div class="row mb-1"><div class="col-5 text-end fw-semibold">Date :</div><div class="col-7" id="rowDate"></div></div>

        <div class="text-center mt-3">
          <button class="btn btn-success me-2" id="rowBtnValidate">Validate</button>
          <button class="btn btn-danger me-2" id="rowBtnReject">Reject</button>
          <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</div>


<script>
// Sidebar small UX
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');}
document.addEventListener('click',function(e){
  const s=document.getElementById('sidebar'),h=document.querySelector('.hamburger-btn');
  if(window.innerWidth<=992 && s.classList.contains('show')){
    if(!s.contains(e.target) && e.target!==h){s.classList.remove('show');}
  }
});
document.querySelector('.hamburger-btn').addEventListener('click',e=>e.stopPropagation());

// Validate/Reject handler (same backend)
function handleRequestAction(btn, action) {
  const requestId = btn.getAttribute('data-request-id');
  const itemId = btn.getAttribute('data-item-id');
  fetch('validate_borrow_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `request_id=${encodeURIComponent(requestId)}&item_id=${encodeURIComponent(itemId)}&action=${encodeURIComponent(action)}`
  })
  .then(res => res.json())
  .then(data => {
    Swal.fire({
      icon: data.success ? 'success' : 'error',
      title: data.success ? 'Success' : 'Error',
      text: data.message
    }).then(() => { if (data.success) window.location.reload(); });
  })
  .catch(() => Swal.fire({icon:'error',title:'Error',text:'Request failed. Try again.'}));
}

const rowModalEl = document.getElementById('rowRequestModal');
const rowModal = new bootstrap.Modal(rowModalEl);
const rowItemImgWrap = document.getElementById('rowItemImgWrap');
const rowItemImg = document.getElementById('rowItemImg');
const rowItemName = document.getElementById('rowItemName');
const rowMember = document.getElementById('rowMember');
const rowYear = document.getElementById('rowYear');
const rowCourse = document.getElementById('rowCourse');
const rowQty = document.getElementById('rowQty');
const rowDate = document.getElementById('rowDate');
const rowBtnValidate = document.getElementById('rowBtnValidate');
const rowBtnReject = document.getElementById('rowBtnReject');

let currentReq = null; // {requestId, itemId}

function openRowModal(btn, action) {
  const d = {
    requestId: btn.getAttribute('data-request-id'),
    itemId:    btn.getAttribute('data-item-id'),
    itemName:  btn.getAttribute('data-item-name') || '',
    itemImg:   btn.getAttribute('data-item-image') || '',
    member:    btn.getAttribute('data-member') || '',
    course:    btn.getAttribute('data-course') || '',
    year:      btn.getAttribute('data-year') || '',
    qty:       btn.getAttribute('data-qty') || '',
    created:   btn.getAttribute('data-created') || ''
  };

  // fill fields
  rowItemName.textContent = d.itemName;
  rowMember.textContent   = d.member;
  rowYear.textContent     = d.year;
  rowCourse.textContent   = d.course;
  rowQty.textContent      = d.qty;
  rowDate.textContent     = new Date(d.created).toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'});

  if (d.itemImg) {
    rowItemImg.src = '/swks/' + d.itemImg.replace(/^\/?/, '');
    rowItemImgWrap.style.display = 'block';
  } else {
    rowItemImgWrap.style.display = 'none';
  }

  // toggle buttons based on mode
  if (action === 'view') {
    rowBtnValidate.classList.add('d-none');
    rowBtnReject.classList.add('d-none');
  } else {
    rowBtnValidate.classList.remove('d-none');
    rowBtnReject.classList.remove('d-none');
  }

  // wire actions when not view
  currentReq = { requestId: d.requestId, itemId: d.itemId };
  rowBtnValidate.onclick = () => handleRequestActionDirect(currentReq.requestId, currentReq.itemId, 'validate');
  rowBtnReject.onclick   = () => handleRequestActionDirect(currentReq.requestId, currentReq.itemId, 'reject');

  rowModal.show();
}

// Reuse backend call
function handleRequestActionDirect(requestId, itemId, action) {
  fetch('validate_borrow_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `request_id=${encodeURIComponent(requestId)}&item_id=${encodeURIComponent(itemId)}&action=${encodeURIComponent(action)}`
  })
  .then(res => res.json())
  .then(data => {
    rowModal.hide();
    Swal.fire({
      icon: data.success ? 'success' : 'error',
      title: data.success ? 'Success' : 'Error',
      text: data.message
    }).then(() => { if (data.success) window.location.reload(); });
  })
  .catch(() => {
    rowModal.hide();
    Swal.fire({icon:'error',title:'Error',text:'Request failed. Try again.'});
  });
}
// === Status + Search filter (forum-style) ===
const tableEl   = document.querySelector('table.table');
const getRows   = () => Array.from(tableEl.querySelectorAll('tbody tr')).filter(r => r.id !== 'noDataRow');
const selStatus = document.getElementById('filterStatus');
const txtSearch = document.getElementById('searchInput');
const noDataRow = document.getElementById('noDataRow');

// Normalize helper (lowercase, alisin accents, ayusin spaces)
const norm = s => (s || '')
  .toString()
  .toLowerCase()
  .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
  .replace(/\s+/g, ' ')
  .trim();

function filterRows() {
  const q  = norm(txtSearch.value);
  const st = selStatus.value; // '' = All

  let visibleCount = 0;

  getRows().forEach(r => {
    // 1) Basahin buong text ng row — gaya ng forum.php
    const rowText = norm(r.innerText);

    // 2) Gamitin pa rin ang data-status kung meron (mas eksakto)
    const rowStatus = r.dataset.status || '';

    const okStatus = !st || rowStatus === st;   // AND logic tulad ng dati
    const okText   = !q || rowText.includes(q); // forum-style text search

    const show = okStatus && okText;
    r.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  // Toggle "No data found"
  if (noDataRow) noDataRow.classList.toggle('d-none', visibleCount !== 0);
}

// debounce para smooth habang nagta-type
let searchTid;
txtSearch.addEventListener('input', () => {
  clearTimeout(searchTid);
  searchTid = setTimeout(filterRows, 120);
});
selStatus.addEventListener('change', filterRows);

// initial run
filterRows();


</script>
</body>
</html>
