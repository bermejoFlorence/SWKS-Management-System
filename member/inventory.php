<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once 'includes/auth_member.php';
include_once '../database/db_connection.php';

// ---- Load items (active only) ----
$itemsQ = $conn->query("SELECT * FROM inventory_items WHERE status = 'active' ORDER BY name ASC");
if (!$itemsQ) {
    die("DB error loading items: " . $conn->error);
}

/**
 * Resolve a DB-stored relative path like "uploads/tools/rake.jpg" to a working web path "/swks/uploads/tools/rake.jpg".
 * Returns null if file not found.
 */
function resolve_web_image($rawPath) {
    $p = str_replace('\\', '/', trim($rawPath ?? '', " \t\n\r\0\x0B/"));
    if ($p === '') return null;

    $web = "/swks/{$p}";
    $abs = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $web;
    if (file_exists($abs)) return $web;

    if (str_starts_with($p, 'swks/')) {
        $web2 = '/' . $p;
        $abs2 = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $web2;
        if (file_exists($abs2)) return $web2;
    }
    return null;
}

// ---- Load current member borrow requests (items per row) ----
$user_id = $_SESSION['user_id'] ?? 0;
$requests = [];
if ($user_id) {
        $stmt = $conn->prepare("
        SELECT
          br.request_id,
          br.created_at,
          br.status AS request_status,
          br.purpose,
          bri.quantity_requested,
          ii.item_id,
          ii.name  AS item_name,
          ii.image AS item_image
        FROM borrow_requests br
        JOIN borrow_request_items bri ON bri.request_id = br.request_id
        JOIN inventory_items ii       ON ii.item_id = bri.item_id
        WHERE br.user_id = ?
        ORDER BY
          CASE LOWER(br.status)
            WHEN 'pending'    THEN 1
            WHEN 'validated'  THEN 2
            WHEN 'with_admin' THEN 2
            WHEN 'approved'   THEN 3
            WHEN 'cancelled'  THEN 4
            WHEN 'rejected'   THEN 5
            ELSE 6
          END,
          br.created_at DESC,
          br.request_id DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $requests[] = $row;
        $stmt->close();
    }
}

// ---- Helpers for UI ----
function badge_for_status($status) {
    $s = strtolower((string)$status);
    switch ($s) {
        case 'approved':   return ['Approved',   'success'];
        case 'rejected':   return ['Rejected',   'danger'];
        case 'returned':   return ['Returned',   'info'];
        case 'cancelled':  return ['Cancelled',  'secondary'];
        case 'with_admin': return ['With Admin', 'primary'];
        case 'validated':  return ['Validated',  'primary'];
        case 'pending':
        default:           return ['Pending',    'secondary'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 & SweetAlert -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Your custom style -->
    <link rel="stylesheet" href="styles/style.css">
    <style>
      .inventory-card img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-radius: 12px;
      }
      .inventory-card {
        transition: transform 0.2s;
        border-radius: 16px;
        overflow: hidden;
      }
      .inventory-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
      }
      .inventory-title {
        color: var(--swks-green-dark, #046307);
        font-size: 1.1rem;
        font-weight: 600;
      }
      .tab-pane {
        padding-top: 1rem;
      }
      .req-thumb {
        width: 48px; height: 48px; object-fit: cover; border-radius: 8px;
      }
      .table thead th { white-space: nowrap; }
      @media (max-width: 768px){
        .col-purpose { display:none; } /* hide purpose on small screens */
      }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
  <div class="container py-4">

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="invTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-inventory" data-bs-toggle="tab" data-bs-target="#pane-inventory" type="button" role="tab">
          <i class="bi bi-box-seam me-1"></i> Available Inventory
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-requests" data-bs-toggle="tab" data-bs-target="#pane-requests" type="button" role="tab">
          <i class="bi bi-list-check me-1"></i> My Borrow Requests
        </button>
      </li>
    </ul>

    <div class="tab-content" id="invTabsContent">
      <!-- Pane 1: Available Inventory (existing grid) -->
      <div class="tab-pane fade show active" id="pane-inventory" role="tabpanel" aria-labelledby="tab-inventory">
        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
          <h3 class="mb-0 text-success fw-bold"><i class="bi bi-box-seam me-2"></i>Available Inventory</h3>
        </div>

        <div class="row g-4">
          <?php while ($item = $itemsQ->fetch_assoc()): ?>
            <?php
              $imgWeb = resolve_web_image($item['image'] ?? '');
              $nameEsc = htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8');
              $descEsc = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
              $avail   = (int)($item['quantity_available'] ?? 0);
              $itemId  = (int)($item['item_id'] ?? 0);
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
              <div class="card inventory-card shadow-sm border-0">
                <?php if ($imgWeb): ?>
                  <img src="<?= $imgWeb ?>" alt="<?= $nameEsc ?>">
                <?php else: ?>
                  <img src="/swks/assets/no-image.png" alt="No image">
                <?php endif; ?>
                <div class="card-body">
                  <div class="inventory-title"><?= $nameEsc ?></div>
                  <p class="mb-1 text-muted"><?= $descEsc ?></p>

                  <?php if ($avail > 0): ?>
                    <span class="badge bg-success mb-2">Available: <?= $avail ?></span>
                  <?php else: ?>
                    <span class="badge bg-danger mb-2">Out of stock</span>
                  <?php endif; ?>

                  <div class="d-grid">
                    <button
                      class="btn btn-success btn-sm fw-semibold btn-request-borrow"
                      data-item-id="<?= $itemId ?>"
                      data-item-name="<?= $nameEsc ?>"
                      data-available="<?= $avail ?>"
                      <?= $avail > 0 ? '' : 'disabled' ?>>
                      <i class="bi bi-cart-plus me-1"></i><?= $avail > 0 ? 'Request to Borrow' : 'Unavailable' ?>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <!-- Pane 2: My Borrow Requests -->
      <div class="tab-pane fade" id="pane-requests" role="tabpanel" aria-labelledby="tab-requests">
        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
          <h3 class="mb-0 text-success fw-bold"><i class="bi bi-list-check me-2"></i>My Borrow Requests</h3>
        </div>

        <?php if (empty($requests)): ?>
          <div class="alert alert-light border d-flex align-items-center" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <div>No requests yet. Browse the inventory and submit a request.</div>
            <a class="btn btn-sm btn-success ms-auto" href="#inventory" onclick="switchToInventory()">Browse Inventory</a>
          </div>
        <?php else: ?>
          <div class="table-responsive mt-3">
            <table class="table align-middle">
              <thead class="table-success">
                <tr>
                  <th style="width: 48px;">#</th>
                  <th>Item</th>
                  <th class="col-purpose">Purpose</th>
                  <th>Qty</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th style="width: 130px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                foreach ($requests as $r):
                    $thumb = resolve_web_image($r['item_image'] ?? '') ?: '/swks/assets/no-image.png';
                    $name  = htmlspecialchars($r['item_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $purpose = trim((string)($r['purpose'] ?? ''));
                    $purposeShort = htmlspecialchars(mb_strimwidth($purpose, 0, 60, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8');
                    $qty   = (int)$r['quantity_requested'];
                    [$label, $variant] = badge_for_status($r['request_status']);
                    $dt = date('Y-m-d H:i', strtotime($r['created_at'] ?? 'now'));
                    $reqId = (int)$r['request_id'];
                ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= $thumb ?>" class="req-thumb" alt="<?= $name ?>">
                      <div>
                        <div class="fw-semibold"><?= $name ?></div>
                        <div class="text-muted small">Req #<?= $reqId ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="col-purpose" title="<?= htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') ?>"><?= $purposeShort ?: '<span class="text-muted">—</span>' ?></td>
                  <td><?= $qty ?></td>
                  <td><span class="badge bg-<?= $variant ?>"><?= $label ?></span></td>
                  <td><?= $dt ?></td>
                  <td>
                    <?php if (strtolower($r['request_status']) === 'pending'): ?>
                      <button class="btn btn-outline-danger btn-sm btn-cancel" data-request-id="<?= $reqId ?>">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                      </button>
                    <?php else: ?>
                      <button class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="bi bi-dash-circle me-1"></i>Cancel
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Hidden form for cancel -->
<form id="cancelForm" action="cancel_borrow_request.php" method="POST" class="d-none">
  <input type="hidden" name="request_id" id="cancelRequestId">
</form>

<!-- Borrow Modal (unchanged except minor UX) -->
<div class="modal fade" id="borrowModal" tabindex="-1" aria-labelledby="borrowModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success" id="borrowModalLabel">
          <i class="bi bi-cart-plus me-1"></i> Borrow Item
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form action="submit_borrow_request.php" method="POST">
        <div class="modal-body px-4 pb-0">
          <input type="hidden" name="item_id" id="borrowItemId">

          <div class="mb-3">
            <label class="form-label fw-semibold">Item Name</label>
            <input type="text" class="form-control" id="borrowItemName" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Quantity to Borrow</label>
            <input type="number" class="form-control" name="quantity" id="borrowQuantity" min="1" step="1" inputmode="numeric" required>
            <div class="form-text">You can request up to the available quantity.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Purpose / Reason <small class="text-muted">(optional)</small></label>
            <textarea class="form-control" name="reason" rows="3" maxlength="500"></textarea>
          </div>
        </div>

        <div class="modal-footer border-0 px-4 pb-4">
          <button type="submit" class="btn btn-success fw-semibold px-4">
            <i class="bi bi-send-check me-1"></i> Submit Request
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (isset($_GET['request'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($_GET['request'] === 'success'): ?>
    Swal.fire({ icon: 'success', title: 'Request Submitted!', text: 'Your borrow request has been sent successfully.', confirmButtonColor: '#1e8fa2' });
  <?php elseif ($_GET['request'] === 'fail_item'): ?>
    Swal.fire({ icon: 'error', title: 'Item Submission Failed', text: 'Something went wrong while saving the requested item.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'fail_main'): ?>
    Swal.fire({ icon: 'error', title: 'Submission Failed', text: 'Failed to save your request. Please try again.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'access_denied'): ?>
    Swal.fire({ icon: 'error', title: 'Access Denied', text: 'You must be logged in as a member.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'invalid_qty'): ?>
    Swal.fire({ icon: 'error', title: 'Invalid Quantity', text: 'Please enter a valid quantity within the available range.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'out_of_stock'): ?>
    Swal.fire({ icon: 'error', title: 'Out of Stock', text: 'Sorry, this item is currently unavailable.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'cancelled'): ?>
    Swal.fire({ icon: 'success', title: 'Request Cancelled', text: 'Your request has been cancelled.', confirmButtonColor: '#1e8fa2' });
  <?php elseif ($_GET['request'] === 'cancel_forbidden'): ?>
    Swal.fire({ icon: 'error', title: 'Cannot Cancel', text: 'Only your pending requests can be cancelled.', confirmButtonColor: '#dc3545' });
  <?php elseif ($_GET['request'] === 'cancel_failed'): ?>
    Swal.fire({ icon: 'error', title: 'Cancel Failed', text: 'There was a problem cancelling your request.', confirmButtonColor: '#dc3545' });
  <?php endif; ?>

  // Remove ?request=... from URL after alert, keep hash
  setTimeout(() => {
    if (window.history.replaceState) {
      const url = new URL(window.location);
      const hash = url.hash;
      url.searchParams.delete('request');
      window.history.replaceState({}, document.title, url.pathname + (hash || ''));
    }
  }, 500);
});
</script>
<?php endif; ?>

<script>
// Sidebar toggle for mobile (unchanged)
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('show');
}
document.addEventListener('click', function(event) {
  const sidebar = document.getElementById('sidebar');
  const hamburger = document.querySelector('.hamburger-btn');
  if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
    if (!sidebar.contains(event.target) && event.target !== hamburger) {
      sidebar.classList.remove('show');
    }
  }
});
document.querySelector('.hamburger-btn')?.addEventListener('click', function(e) {
  e.stopPropagation();
});

// Switch to inventory tab via link
function switchToInventory() {
  const tabBtn = document.getElementById('tab-inventory');
  const tab = new bootstrap.Tab(tabBtn);
  tab.show();
  window.location.hash = '#inventory';
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Borrow modal logic
  const borrowButtons = document.querySelectorAll('.btn-request-borrow');
  const modalEl = document.getElementById('borrowModal');
  const qtyInput = document.getElementById('borrowQuantity');
  const form = modalEl.querySelector('form');

  let currentMax = 0;

  borrowButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const itemId = this.dataset.itemId;
      const itemName = this.dataset.itemName;
      const available = parseInt(this.dataset.available || '0', 10);

      document.getElementById('borrowItemId').value = itemId;
      document.getElementById('borrowItemName').value = itemName;

      currentMax = Math.max(0, available);
      qtyInput.value = currentMax > 0 ? 1 : 0;
      qtyInput.max = currentMax;

      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    });
  });

  qtyInput.addEventListener('input', function () {
    let v = parseInt(this.value || '0', 10);
    if (isNaN(v)) v = 1;
    if (v < 1) v = 1;
    if (currentMax && v > currentMax) v = currentMax;
    this.value = v;
  });

  form.addEventListener('submit', function (e) {
    const v = parseInt(qtyInput.value || '0', 10);
    if (!currentMax || v < 1 || v > currentMax) {
      e.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Invalid quantity',
        text: currentMax
          ? `Please enter a quantity between 1 and ${currentMax}.`
          : 'This item is currently unavailable.'
      });
    }
  });

  // Tab persistence via hash
  const hash = window.location.hash;
  const inventoryBtn = document.getElementById('tab-inventory');
  const requestsBtn  = document.getElementById('tab-requests');
  if (hash === '#requests') {
    new bootstrap.Tab(requestsBtn).show();
  } else if (hash === '#inventory') {
    new bootstrap.Tab(inventoryBtn).show();
  }

  // Clicking Cancel buttons
  document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', function() {
      const reqId = this.dataset.requestId;
      Swal.fire({
        icon: 'warning',
        title: 'Cancel this request?',
        text: 'This cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, cancel it',
      }).then(result => {
        if (result.isConfirmed) {
          document.getElementById('cancelRequestId').value = reqId;
          document.getElementById('cancelForm').submit();
        }
      });
    });
  });

  // Switch hash on tab change
  document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function (e) {
      const target = e.target.getAttribute('data-bs-target');
      if (target === '#pane-requests') window.location.hash = '#requests';
      else window.location.hash = '#inventory';
    });
  });
});
</script>

</body>
</html>
