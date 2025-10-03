<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* --------------------
 * INVENTORY LIST (for tab 1)
 * -------------------- */
$inventoryQ = $conn->query("SELECT * FROM inventory_items ORDER BY name ASC");

/* --------------------
 * BORROW REQUESTS (for tab 2)
 * Only requests validated by advisers (ready for admin action)
 * -------------------- */
$reqSql = "
  SELECT
    br.request_id,
    br.org_id,
    o.org_name,
    br.user_id,
    u.user_email,
    COALESCE(md.full_name, ad.adviser_fname, u.user_email) AS requester_name,
    br.purpose,
    br.status,
    br.created_at,
    COUNT(bri.item_id) AS item_count,
    COALESCE(SUM(bri.quantity_requested), 0) AS total_qty
  FROM borrow_requests br
  JOIN user u ON u.user_id = br.user_id
  LEFT JOIN member_details md ON md.user_id = u.user_id
  LEFT JOIN adviser_details ad ON ad.user_id = u.user_id
  LEFT JOIN organization o ON o.org_id = br.org_id
  LEFT JOIN borrow_request_items bri ON bri.request_id = br.request_id
  WHERE br.status IN ('validated','approved','returned')   -- ⬅️ include approved & returned
  GROUP BY br.request_id
  ORDER BY br.created_at DESC
";

$requestsQ = $conn->query($reqSql);

/* Per-request items (for collapsible details) */
$itemsByReq = [];
if ($requestsQ && $requestsQ->num_rows) {
  $ids = [];
  $requestsQ->data_seek(0);
  while ($r = $requestsQ->fetch_assoc()) { $ids[] = (int)$r['request_id']; }
  if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $itSql = "
      SELECT bri.request_id, bri.item_id, ii.name AS item_name, bri.quantity_requested
      FROM borrow_request_items bri
      JOIN inventory_items ii ON ii.item_id = bri.item_id
      WHERE bri.request_id IN ($in)
      ORDER BY ii.name ASC
    ";
    if ($itRes = $conn->query($itSql)) {
      while ($it = $itRes->fetch_assoc()) {
        $rid = (int)$it['request_id'];
        $itemsByReq[$rid][] = $it;
      }
      $itRes->free();
    }
  }
  $requestsQ->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Aca Coordinator Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="styles/style.css">
  <style>
    .card { border-radius: 16px; transition: box-shadow .2s; }
    .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.11); }
    .display-4 { font-size: 2.8rem; }
    .main-content .card { border-radius: 18px; transition: box-shadow .2s; }
    .main-content .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.12); }
    .main-content { padding-top: 70px; }
    @media (max-width:575px){ .display-4{font-size:2.2rem;} }

    /* Borrow Requests collapsible row look */
    .req-details { background:#f8fafc; }
    .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .purpose-cell {
  display: -webkit-box;
  -webkit-line-clamp: 1;   /* or 2 kung gusto mo hanggang dalawang linya */
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px; /* para hindi masyadong humaba */
  white-space: normal;
}

  </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
  <div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 swks-header-row">
      <h2 class="mb-0" style="color: var(--swks-green); font-weight: bold; padding-bottom:10px;">
        <i class="bi bi-box-seam-fill me-2"></i>Inventory Management
      </h2>
      <button class="btn btn-success shadow-sm fw-semibold px-4 add-org-btn"
              data-bs-toggle="modal" data-bs-target="#addItemModal">
        <i class="bi bi-plus-circle me-1"></i> Add Inventory Item
      </button>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link active" id="tab-inventory" data-bs-toggle="tab" data-bs-target="#pane-inventory" type="button">
          <i class="bi bi-box-seam me-1"></i> Available Inventory
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="tab-requests" data-bs-toggle="tab" data-bs-target="#pane-requests" type="button">
          <i class="bi bi-card-checklist me-1"></i> Borrow Requests
        </button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- ========== Tab 1: INVENTORY (your existing table, unchanged) ========== -->
      <div class="tab-pane fade show active" id="pane-inventory">
        <div class="card border-0 shadow-lg rounded-4">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-success rounded-4">
                  <tr>
                    <th style="width:4%; text-align:center;">#</th>
                    <th style="width:30%; text-align:center;">Item Name</th>
                    <th style="width:30%; text-align:center;">Description</th>
                    <th style="width:10%; text-align:center;">Qty</th>
                    <th style="width:10%; text-align:center;">Status</th>
                    <th style="width:16%; text-align:center;">Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                while ($row = $inventoryQ->fetch_assoc()):
                ?>
                  <tr>
                    <td class="align-middle text-center fw-bold"><?= $count++ ?></td>
                    <td class="align-middle text-center">
                      <div class="fw-semibold" style="color: var(--swks-green-dark); font-size: 1.05rem;">
                        <?= htmlspecialchars($row['name']) ?>
                      </div>
                      <?php if (!empty($row['image'])): ?>
                        <img src="/swks/<?= htmlspecialchars($row['image']) ?>" alt="Item Image"
                             class="rounded mt-2" style="width: 100px; height: 120px; object-fit: cover; border: 1px solid #ccc;">
                      <?php else: ?>
                        <div class="text-muted small">No image</div>
                      <?php endif; ?>
                    </td>
                    <td class="align-middle text-center"><?= htmlspecialchars($row['description']) ?></td>
                    <td class="align-middle text-center"><?= (int)$row['quantity_available'] ?></td>
                    <td class="align-middle text-center">
                      <?php if ($row['status'] === 'active'): ?>
                        <span class="badge bg-success fw-semibold px-3 py-2">Active</span>
                      <?php else: ?>
                        <span class="badge bg-secondary fw-semibold px-3 py-2">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td class="align-middle text-center">
                      <div class="d-flex justify-content-center gap-2">
                        <a href="#"
                           class="btn btn-sm btn-swks-outline rounded-pill px-3 fw-semibold"
                           data-bs-toggle="modal"
                           data-bs-target="#editItemModal"
                           data-id="<?= $row['item_id'] ?>"
                           data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
                           data-desc="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                           data-qty="<?= (int)$row['quantity_available'] ?>"
                           data-image="/swks/<?= htmlspecialchars($row['image']) ?>">
                          <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ========== Tab 2: BORROW REQUESTS (validated) ========== -->
      <div class="tab-pane fade" id="pane-requests">
        <div class="card border-0 shadow-lg rounded-4">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-success">
                  <tr>
                    <th style="width:8%">Request #</th>
                    <th style="width:18%">Organization</th>
                    <th style="width:20%">Requester</th>
                    <th style="width:22%">Purpose</th>
                    <th style="width:12%" class="text-center">Items</th>
                    <th style="width:10%" class="text-center">Status</th>
                    <th style="width:10%" class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php if ($requestsQ && $requestsQ->num_rows): ?>
                  <?php while ($req = $requestsQ->fetch_assoc()):
                    $rid   = (int)$req['request_id'];
                    $items = $itemsByReq[$rid] ?? [];
                  ?>
                  <tr>
                    <td class="fw-semibold">#<?= $rid ?></td>
                    <td><?= htmlspecialchars($req['org_name'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($req['requester_name'] ?: $req['user_email']) ?></td>
                    <td class="small line-clamp-2"><?= htmlspecialchars($req['purpose'] ?: '—') ?></td>
                    <td class="text-center">
                      <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <?= (int)$req['item_count'] ?> item(s) / <?= (int)$req['total_qty'] ?> qty
                      </span>
                    </td>
                    <td class="text-center">
  <?php
    $st = strtolower($req['status']);
    if ($st === 'validated') {
      echo '<span class="badge bg-warning text-dark">validated</span>';
    } elseif ($st === 'approved') {
      echo '<span class="badge bg-primary">approved</span>';
    } elseif ($st === 'returned') {
      echo '<span class="badge bg-success">returned</span>';
    } else {
      echo '<span class="badge bg-secondary">'.htmlspecialchars($req['status']).'</span>';
    }
  ?>
</td>

<td class="text-center">
  <div class="btn-group">
    <!-- always available: show items -->
    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#reqItems<?= $rid ?>">
      <i class="bi bi-list-ul"></i>
    </button>

    <?php if ($st === 'validated'): ?>
      <button class="btn btn-success btn-sm admin-approve" data-rid="<?= $rid ?>">
        <i class="bi bi-check2-circle me-1"></i>Approve
      </button>
      <button class="btn btn-danger btn-sm admin-reject" data-rid="<?= $rid ?>">
        <i class="bi bi-x-circle me-1"></i>Reject
      </button>

    <?php elseif ($st === 'approved'): ?>
      <button class="btn btn-outline-success btn-sm admin-return" data-rid="<?= $rid ?>">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Return
      </button>

    <?php elseif ($st === 'returned'): ?>
      <button class="btn btn-outline-secondary btn-sm" disabled>
        <i class="bi bi-check2 me-1"></i>Returned
      </button>
    <?php endif; ?>
  </div>
</td>

                  </tr>
                  <tr class="collapse req-details" id="reqItems<?= $rid ?>">
                    <td colspan="7">
                      <?php if ($items): ?>
                        <ul class="mb-2 small">
                          <?php foreach ($items as $it): ?>
                            <li><?= htmlspecialchars($it['item_name']) ?> — x<?= (int)$it['quantity_requested'] ?></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <em class="text-muted">No items found for this request.</em>
                      <?php endif; ?>
                      <div class="text-muted small">Created: <?= htmlspecialchars($req['created_at']) ?></div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No validated requests at the moment.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div> <!-- /.tab-content -->
  </div>

  <!-- Add Inventory Modal -->
  <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-sm">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold text-success" id="addItemModalLabel">
            <i class="bi bi-plus-circle me-1"></i> Add New Inventory Item
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="add_inventory_item.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body px-4 pb-0">
            <div class="mb-3">
              <label for="itemName" class="form-label fw-semibold">Item Name</label>
              <input type="text" class="form-control" id="itemName" name="name" required>
            </div>
            <div class="mb-3">
              <label for="itemDesc" class="form-label fw-semibold">Description</label>
              <textarea class="form-control" id="itemDesc" name="description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label for="itemQty" class="form-label fw-semibold">Quantity</label>
              <input type="number" class="form-control" id="itemQty" name="quantity" min="1" required>
            </div>
            <div class="mb-3">
              <label for="itemImage" class="form-label fw-semibold">Upload Item Image <small class="text-muted">(optional)</small></label>
              <input type="file" class="form-control" id="itemImage" name="image" accept="image/*">
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4">
            <button type="submit" class="btn btn-success fw-semibold px-4">
              <i class="bi bi-save me-1"></i> Save
            </button>
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Inventory Modal -->
  <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-sm">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold text-success" id="editItemModalLabel">
            <i class="bi bi-pencil-square me-1"></i> Edit Inventory Item
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="update_inventory_item.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="item_id" id="editItemId">
          <div class="modal-body px-4 pb-0">
            <div class="mb-3">
              <label for="editItemName" class="form-label fw-semibold">Item Name</label>
              <input type="text" class="form-control" id="editItemName" name="name" required>
            </div>
            <div class="mb-3">
              <label for="editItemDesc" class="form-label fw-semibold">Description</label>
              <textarea class="form-control" id="editItemDesc" name="description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label for="editItemQty" class="form-label fw-semibold">Quantity</label>
              <input type="number" class="form-control" id="editItemQty" name="quantity" min="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Current Image</label><br>
              <img id="editPreviewImage" src="#" alt="Preview" class="rounded shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
            </div>
            <div class="mb-3">
              <label for="editItemImage" class="form-label fw-semibold">Change Image <small class="text-muted">(optional)</small></label>
              <input type="file" class="form-control" id="editItemImage" name="image" accept="image/*">
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4">
            <button type="submit" class="btn btn-success fw-semibold px-4">
              <i class="bi bi-save me-1"></i> Update
            </button>
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div> <!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Show success after Add
  document.addEventListener('DOMContentLoaded', function () {
    if (sessionStorage.getItem('inventoryAddSuccess')) {
      Swal.fire({
        icon: 'success',
        title: 'Item Added!',
        text: 'The inventory item has been successfully saved.',
        confirmButtonColor: '#198754'
      }).then(() => {
        sessionStorage.removeItem('inventoryAddSuccess');
        location.reload();
      });
    }
  });

  // Sidebar toggle for mobile
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
  }
  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger-btn');
    if (window.innerWidth <= 992 && sidebar?.classList.contains('show')) {
      if (!sidebar.contains(event.target) && event.target !== hamburger) {
        sidebar.classList.remove('show');
      }
    }
  });
  document.querySelector('.hamburger-btn')?.addEventListener('click', function(e){ e.stopPropagation(); });

  // Populate Edit Modal
  const editModal = document.getElementById('editItemModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('editItemId').value   = button.getAttribute('data-id');
    document.getElementById('editItemName').value = button.getAttribute('data-name');
    document.getElementById('editItemDesc').value = button.getAttribute('data-desc');
    document.getElementById('editItemQty').value  = button.getAttribute('data-qty');

    const imgSrc = button.getAttribute('data-image');
    const imgElement = document.getElementById('editPreviewImage');
    if (imgSrc) { imgElement.src = imgSrc; imgElement.style.display = 'block'; }
    else { imgElement.style.display = 'none'; }
  });

  // Show success after Update (from sessionStorage flag)
  if (sessionStorage.getItem('inventoryUpdateSuccess')) {
    Swal.fire({
      icon: 'success',
      title: 'Updated!',
      text: 'The item has been updated successfully.',
      confirmButtonColor: '#198754'
    }).then(() => { sessionStorage.removeItem('inventoryUpdateSuccess'); });
  }

// --- Admin Approve / Reject / Return actions ---
document.addEventListener('click', async (e) => {
  const btnApprove = e.target.closest('.admin-approve');
  const btnReject  = e.target.closest('.admin-reject');
  const btnReturn  = e.target.closest('.admin-return');
  if (!btnApprove && !btnReject && !btnReturn) return;

  const el   = btnApprove || btnReject || btnReturn;
  const rid  = el.dataset.rid;
  const action = btnApprove ? 'approve' : btnReject ? 'reject' : 'return';

  const titles = { approve:'Approve request?', reject:'Reject request?', return:'Mark as returned?' };
  const labels = { approve:'Approve',           reject:'Reject',           return:'Return' };
  const colors = { approve:'#198754',          reject:'#dc3545',          return:'#198754' };

  const res = await Swal.fire({
    icon: 'question',
    title: titles[action],
    showCancelButton: true,
    confirmButtonText: labels[action],
    confirmButtonColor: colors[action]
  });
  if (!res.isConfirmed) return;

  try {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('request_id', rid);

    const r = await fetch('admin_request_action.php', { method:'POST', body:fd });
    const data = await r.json();

    if (data?.ok) {
      Swal.fire({ icon:'success', title:'Done', timer:1100, showConfirmButton:false })
        .then(()=> location.reload());
    } else {
      throw new Error(data?.msg || 'Action failed');
    }
  } catch(err) {
    Swal.fire({ icon:'error', title:'Error', text:String(err.message||err) });
  }
});

  // Optional: open Requests tab via ?tab=requests
  (function(){
    const params = new URLSearchParams(location.search);
    if (params.get('tab') === 'requests') {
      const btn = document.querySelector('#tab-requests');
      if (btn) new bootstrap.Tab(btn).show();
    }
  })();
</script>
</body>
</html>
