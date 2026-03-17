<?php
/**
 * admin_reports.php
 * Admin dashboard — view, filter, and act on user reports.
 * Protect this page: only admins should reach it.
 * Add your own admin-auth check below.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'connect.php';

/* ── ADMIN GUARD — adapt to your auth system ── */
// Example: if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { header('Location: index.php'); exit(); }

/* ── Handle POST actions (AJAX) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action    = $_POST['action']    ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);
    $note      = mb_substr(trim($_POST['note'] ?? ''), 0, 1000);
    $admin_id  = (int)($_SESSION['user_id'] ?? 0);

    if (!in_array($action, ['reviewed', 'dismissed', 'actioned'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit();
    }

    $upd = $con->prepare("
        UPDATE user_reports
        SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE report_id = ?
    ");
    $upd->bind_param('ssii', $action, $note, $admin_id, $report_id);
    echo $upd->execute()
        ? json_encode(['status' => 'success'])
        : json_encode(['status' => 'error', 'message' => $con->error]);
    exit();
}

/* ── Filters ── */
$filter_status = in_array($_GET['status'] ?? '', ['pending','reviewed','dismissed','actioned'])
    ? $_GET['status'] : 'pending';
$search = trim($_GET['search'] ?? '');

/* ── Fetch reports ── */
$where  = "WHERE r.status = ?";
$params = [$filter_status];
$types  = 's';

if ($search !== '') {
    $where  .= " AND (rep.user_name LIKE ? OR rpd.user_name LIKE ? OR r.reason LIKE ?)";
    $like    = '%' . $con->real_escape_string($search) . '%';
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}

$sql = "
    SELECT
        r.report_id,
        r.reason,
        r.ip_address,
        r.status,
        r.admin_note,
        r.created_at,
        r.reviewed_at,
        rep.user_id   AS reporter_id,
        rep.user_name AS reporter_name,
        rep.profile_image AS reporter_img,
        rpd.user_id   AS reported_id,
        rpd.user_name AS reported_name,
        rpd.profile_image AS reported_img,
        adm.user_name AS admin_name
    FROM user_reports r
    JOIN users rep ON rep.user_id = r.reporter_id
    JOIN users rpd ON rpd.user_id = r.reported_id
    LEFT JOIN users adm ON adm.user_id = r.reviewed_by
    $where
    ORDER BY r.created_at DESC
    LIMIT 200
";

$st = $con->prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$reports = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Counts per status ── */
$counts = ['pending' => 0, 'reviewed' => 0, 'dismissed' => 0, 'actioned' => 0];
$cq = $con->query("SELECT status, COUNT(*) AS c FROM user_reports GROUP BY status");
while ($row = $cq->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Management — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   ROOT & RESET
═══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:      #0b0c0f;
  --bg2:     #111318;
  --bg3:     #181b22;
  --border:  rgba(255,255,255,.07);
  --border2: rgba(255,255,255,.12);
  --txt:     #e8eaf0;
  --sub:     rgba(232,234,240,.45);
  --acc:     #ff3b5c;
  --acc2:    #ff7a5c;
  --green:   #2de07e;
  --yellow:  #f5c843;
  --blue:    #5b8fff;
  --font:    'Syne', sans-serif;
  --mono:    'JetBrains Mono', monospace;
  --r:       14px;
  --sh:      0 8px 32px rgba(0,0,0,.5);
}

html { font-size: 15px; }
body {
  background: var(--bg);
  color: var(--txt);
  font-family: var(--font);
  min-height: 100vh;
  line-height: 1.5;
}

/* ═══════════════════════════════════════════════
   NOISE OVERLAY
═══════════════════════════════════════════════ */
body::before {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.028'/%3E%3C/svg%3E");
  opacity: 1;
}

/* ═══════════════════════════════════════════════
   LAYOUT
═══════════════════════════════════════════════ */
.wrap {
  position: relative; z-index: 1;
  max-width: 1280px; margin: 0 auto;
  padding: 0 24px 60px;
}

/* ═══════════════════════════════════════════════
   HEADER
═══════════════════════════════════════════════ */
.hdr {
  display: flex; align-items: center; gap: 16px;
  padding: 28px 0 24px;
  border-bottom: 1px solid var(--border2);
  margin-bottom: 32px;
}
.hdr-icon {
  width: 48px; height: 48px; border-radius: 14px;
  background: linear-gradient(135deg, var(--acc), var(--acc2));
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; flex-shrink: 0;
  box-shadow: 0 4px 20px rgba(255,59,92,.35);
}
.hdr h1 {
  font-size: 1.55rem; font-weight: 800; letter-spacing: -.5px;
  background: linear-gradient(90deg, var(--txt) 0%, rgba(232,234,240,.6) 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.hdr small {
  font-size: .73rem; color: var(--sub); font-family: var(--mono);
  display: block; margin-top: 2px;
}
.hdr-back {
  margin-left: auto;
  background: var(--bg3); border: 1px solid var(--border2);
  color: var(--txt); padding: 8px 16px; border-radius: 10px;
  font-family: var(--font); font-size: .82rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: background .18s, transform .15s;
  display: inline-flex; align-items: center; gap: 6px;
}
.hdr-back:hover { background: var(--border2); transform: translateY(-1px); }

/* ═══════════════════════════════════════════════
   STAT PILLS
═══════════════════════════════════════════════ */
.stats {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 12px; margin-bottom: 28px;
}
.stat {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--r); padding: 16px 18px;
  cursor: pointer; transition: border-color .2s, transform .15s;
  text-decoration: none; display: block;
}
.stat:hover { transform: translateY(-2px); border-color: var(--border2); }
.stat.active { border-color: var(--acc); background: rgba(255,59,92,.07); }
.stat-n {
  font-size: 1.8rem; font-weight: 800;
  font-family: var(--mono); line-height: 1;
}
.stat-lbl { font-size: .72rem; color: var(--sub); margin-top: 4px; text-transform: uppercase; letter-spacing: .8px; }
.stat[data-s=pending]   .stat-n { color: var(--yellow); }
.stat[data-s=reviewed]  .stat-n { color: var(--blue); }
.stat[data-s=dismissed] .stat-n { color: var(--sub); }
.stat[data-s=actioned]  .stat-n { color: var(--green); }

/* ═══════════════════════════════════════════════
   TOOLBAR
═══════════════════════════════════════════════ */
.toolbar {
  display: flex; gap: 10px; align-items: center;
  margin-bottom: 20px; flex-wrap: wrap;
}
.srch-wrap {
  flex: 1; min-width: 200px; position: relative;
}
.srch-wrap input {
  width: 100%; background: var(--bg2); border: 1px solid var(--border2);
  border-radius: 10px; color: var(--txt); padding: 9px 14px 9px 36px;
  font-family: var(--font); font-size: .85rem; outline: none;
  transition: border-color .2s;
}
.srch-wrap input:focus { border-color: var(--acc); }
.srch-wrap .srch-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  font-size: 14px; color: var(--sub); pointer-events: none;
}
.toolbar form { display: contents; }
.btn {
  background: var(--bg3); border: 1px solid var(--border2);
  color: var(--txt); padding: 9px 18px; border-radius: 10px;
  font-family: var(--font); font-size: .83rem; font-weight: 600;
  cursor: pointer; transition: background .18s, transform .15s;
  white-space: nowrap;
}
.btn:hover { background: var(--border2); transform: translateY(-1px); }
.btn.acc { background: var(--acc); border-color: var(--acc); color: #fff; }
.btn.acc:hover { background: #e02a4e; }

/* ═══════════════════════════════════════════════
   TABLE CONTAINER
═══════════════════════════════════════════════ */
.tbl-wrap {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--r); overflow: hidden;
  box-shadow: var(--sh);
}

.tbl-head {
  display: grid;
  grid-template-columns: 44px 1.3fr 1.3fr 2.4fr 120px 148px;
  padding: 10px 18px;
  background: var(--bg3); border-bottom: 1px solid var(--border2);
  font-size: .71rem; text-transform: uppercase; letter-spacing: .9px;
  color: var(--sub); font-family: var(--mono);
}

/* ═══════════════════════════════════════════════
   REPORT ROW
═══════════════════════════════════════════════ */
.rrow {
  display: grid;
  grid-template-columns: 44px 1.3fr 1.3fr 2.4fr 120px 148px;
  align-items: center; padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  transition: background .15s;
  gap: 4px;
}
.rrow:last-child { border-bottom: none; }
.rrow:hover { background: rgba(255,255,255,.022); }

.rrow-id {
  font-family: var(--mono); font-size: .72rem; color: var(--sub);
}

/* user cell */
.ucel {
  display: flex; align-items: center; gap: 8px; min-width: 0;
}
.ucel img {
  width: 32px; height: 32px; border-radius: 50%;
  object-fit: cover; flex-shrink: 0;
  border: 1.5px solid var(--border2);
}
.ucel-name {
  font-size: .84rem; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ucel-id {
  font-size: .69rem; color: var(--sub); font-family: var(--mono);
}

/* reason cell */
.reason-cel {
  min-width: 0;
}
.reason-text {
  font-size: .82rem; color: var(--txt);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; cursor: pointer;
  transition: color .15s;
}
.reason-text:hover { color: #fff; }
.reason-meta {
  font-size: .68rem; color: var(--sub); margin-top: 3px;
  font-family: var(--mono);
}

/* status badge */
.badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 8px;
  font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
  font-family: var(--mono); white-space: nowrap;
}
.badge.pending   { background: rgba(245,200,67,.12);  color: var(--yellow); border: 1px solid rgba(245,200,67,.3); }
.badge.reviewed  { background: rgba(91,143,255,.12);  color: var(--blue);   border: 1px solid rgba(91,143,255,.3); }
.badge.dismissed { background: rgba(255,255,255,.05); color: var(--sub);    border: 1px solid var(--border); }
.badge.actioned  { background: rgba(45,224,126,.1);   color: var(--green);  border: 1px solid rgba(45,224,126,.3); }
.badge::before {
  content: ''; width: 6px; height: 6px; border-radius: 50%;
  background: currentColor; flex-shrink: 0;
}

/* action buttons */
.acts { display: flex; gap: 6px; flex-wrap: wrap; }
.act-btn {
  background: var(--bg3); border: 1px solid var(--border2);
  color: var(--txt); padding: 5px 11px; border-radius: 8px;
  font-family: var(--font); font-size: .74rem; font-weight: 600;
  cursor: pointer; transition: background .15s, transform .12s, border-color .15s;
  white-space: nowrap;
}
.act-btn:hover { transform: scale(1.04); }
.act-btn.rev  { border-color: rgba(91,143,255,.4);  color: var(--blue); }
.act-btn.rev:hover  { background: rgba(91,143,255,.15); }
.act-btn.dis  { border-color: rgba(255,255,255,.15); color: var(--sub); }
.act-btn.dis:hover  { background: rgba(255,255,255,.07); }
.act-btn.act  { border-color: rgba(45,224,126,.4);  color: var(--green); }
.act-btn.act:hover  { background: rgba(45,224,126,.12); }

/* ═══════════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════════ */
.empty {
  padding: 60px 20px; text-align: center; color: var(--sub);
}
.empty-icon { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty h3 { font-size: 1.1rem; color: var(--txt); margin-bottom: 6px; }
.empty p  { font-size: .84rem; }

/* ═══════════════════════════════════════════════
   MODAL
═══════════════════════════════════════════════ */
.overlay {
  position: fixed; inset: 0; z-index: 9000;
  background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity .25s;
}
.overlay.on { opacity: 1; pointer-events: all; }

.modal {
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: 20px; width: 100%; max-width: 500px;
  padding: 28px; box-shadow: 0 32px 80px rgba(0,0,0,.7);
  transform: translateY(18px) scale(.97);
  transition: transform .3s cubic-bezier(.34,1.56,.64,1);
  position: relative;
}
.overlay.on .modal { transform: translateY(0) scale(1); }

.modal h2 { font-size: 1.15rem; font-weight: 800; margin-bottom: 6px; }
.modal-meta { font-size: .78rem; color: var(--sub); margin-bottom: 18px; font-family: var(--mono); }
.modal-reason {
  background: var(--bg3); border: 1px solid var(--border2);
  border-radius: 12px; padding: 14px; font-size: .85rem;
  color: var(--txt); line-height: 1.6; margin-bottom: 18px;
  max-height: 180px; overflow-y: auto;
}
.modal label { font-size: .78rem; color: var(--sub); display: block; margin-bottom: 5px; font-family: var(--mono); }
.modal textarea {
  width: 100%; background: var(--bg3); border: 1px solid var(--border2);
  border-radius: 12px; color: var(--txt); padding: 10px 14px;
  font-family: var(--font); font-size: .85rem; resize: vertical;
  min-height: 80px; outline: none; margin-bottom: 16px;
  transition: border-color .2s;
}
.modal textarea:focus { border-color: var(--acc); }
.modal-btns { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.modal-close {
  position: absolute; top: 16px; right: 16px;
  background: var(--bg3); border: 1px solid var(--border2);
  color: var(--sub); width: 30px; height: 30px; border-radius: 8px;
  font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .15s, color .15s;
}
.modal-close:hover { background: var(--border2); color: var(--txt); }

/* ═══════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════ */
#toast {
  position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(12px);
  background: var(--bg3); border: 1px solid var(--border2);
  color: var(--txt); padding: 10px 22px; border-radius: 12px;
  font-size: .83rem; font-family: var(--font); font-weight: 600;
  z-index: 99999; opacity: 0; pointer-events: none;
  transition: opacity .3s, transform .3s;
  box-shadow: var(--sh); white-space: nowrap;
}
#toast.on { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media (max-width: 900px) {
  .stats { grid-template-columns: repeat(2, 1fr); }
  .tbl-head,
  .rrow { grid-template-columns: 44px 1fr 1fr; }
  .tbl-head > *:nth-child(n+4),
  .rrow > *:nth-child(n+4) { display: none; }
}
@media (max-width: 560px) {
  .tbl-head { display: none; }
  .rrow {
    grid-template-columns: 1fr;
    gap: 10px; padding: 14px;
  }
  .rrow > * { display: block !important; }
}
</style>
</head>
<body>

<div class="wrap">

  <!-- HEADER -->
  <div class="hdr">
    <div class="hdr-icon">🚩</div>
    <div>
      <h1>Report Management</h1>
      <small>User safety dashboard</small>
    </div>
    <a href="index.php" class="hdr-back">← Back to site</a>
  </div>

  <!-- STATS -->
  <div class="stats">
    <?php foreach (['pending' => '⏳ Pending', 'reviewed' => '🔍 Reviewed', 'dismissed' => '✗ Dismissed', 'actioned' => '✅ Actioned'] as $s => $lbl): ?>
      <a href="?status=<?= $s ?>&search=<?= urlencode($search) ?>"
         class="stat <?= $filter_status === $s ? 'active' : '' ?>"
         data-s="<?= $s ?>">
        <div class="stat-n"><?= $counts[$s] ?></div>
        <div class="stat-lbl"><?= $lbl ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <form method="get" style="display:contents">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
      <div class="srch-wrap">
        <span class="srch-icon">🔎</span>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search reporter, reported, or reason…">
      </div>
      <button type="submit" class="btn acc">Search</button>
      <?php if ($search): ?>
        <a href="?status=<?= $filter_status ?>" class="btn">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- TABLE -->
  <div class="tbl-wrap">
    <div class="tbl-head">
      <div>#</div>
      <div>Reporter</div>
      <div>Reported</div>
      <div>Reason</div>
      <div>Status</div>
      <div>Actions</div>
    </div>

    <?php if (empty($reports)): ?>
      <div class="empty">
        <span class="empty-icon">🎉</span>
        <h3>No <?= $filter_status ?> reports</h3>
        <p>Nothing to review here.</p>
      </div>
    <?php else: foreach ($reports as $r):
      $repImg = !empty($r['reporter_img']) ? htmlspecialchars($r['reporter_img']) : 'default_profile.png';
      $rpdImg = !empty($r['reported_img']) ? htmlspecialchars($r['reported_img']) : 'default_profile.png';
    ?>
      <div class="rrow" data-id="<?= $r['report_id'] ?>">

        <!-- ID -->
        <div class="rrow-id">#<?= $r['report_id'] ?></div>

        <!-- Reporter -->
        <div class="ucel">
          <img src="<?= $repImg ?>" alt="">
          <div>
            <div class="ucel-name"><?= htmlspecialchars($r['reporter_name']) ?></div>
            <div class="ucel-id">ID <?= $r['reporter_id'] ?></div>
          </div>
        </div>

        <!-- Reported -->
        <div class="ucel">
          <img src="<?= $rpdImg ?>" alt="">
          <div>
            <div class="ucel-name"><?= htmlspecialchars($r['reported_name']) ?></div>
            <div class="ucel-id">ID <?= $r['reported_id'] ?></div>
          </div>
        </div>

        <!-- Reason -->
        <div class="reason-cel">
          <div class="reason-text"
               onclick="openModal(<?= $r['report_id'] ?>,
                 <?= htmlspecialchars(json_encode($r['reason']), ENT_QUOTES) ?>,
                 <?= htmlspecialchars(json_encode($r['reporter_name']), ENT_QUOTES) ?>,
                 <?= htmlspecialchars(json_encode($r['reported_name']), ENT_QUOTES) ?>,
                 <?= htmlspecialchars(json_encode($r['admin_note'] ?? ''), ENT_QUOTES) ?>)">
            <?= htmlspecialchars($r['reason']) ?>
          </div>
          <div class="reason-meta">
            <?= date('M j, Y · H:i', strtotime($r['created_at'])) ?>
            <?php if ($r['ip_address']): ?> · <?= htmlspecialchars($r['ip_address']) ?><?php endif; ?>
            <?php if ($r['admin_name']): ?> · reviewed by <?= htmlspecialchars($r['admin_name']) ?><?php endif; ?>
          </div>
        </div>

        <!-- Status -->
        <div>
          <span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span>
        </div>

        <!-- Actions -->
        <div class="acts">
          <?php if ($r['status'] !== 'reviewed'): ?>
            <button class="act-btn rev" onclick="act(<?= $r['report_id'] ?>,'reviewed')">Review</button>
          <?php endif; ?>
          <?php if ($r['status'] !== 'dismissed'): ?>
            <button class="act-btn dis" onclick="act(<?= $r['report_id'] ?>,'dismissed')">Dismiss</button>
          <?php endif; ?>
          <?php if ($r['status'] !== 'actioned'): ?>
            <button class="act-btn act" onclick="openModal(<?= $r['report_id'] ?>,
              <?= htmlspecialchars(json_encode($r['reason']), ENT_QUOTES) ?>,
              <?= htmlspecialchars(json_encode($r['reporter_name']), ENT_QUOTES) ?>,
              <?= htmlspecialchars(json_encode($r['reported_name']), ENT_QUOTES) ?>,
              <?= htmlspecialchars(json_encode($r['admin_note'] ?? ''), ENT_QUOTES) ?>, true)">Action</button>
          <?php endif; ?>
        </div>

      </div>
    <?php endforeach; endif; ?>
  </div><!-- /tbl-wrap -->

</div><!-- /wrap -->

<!-- MODAL -->
<div class="overlay" id="overlay" onclick="closeModal(event)">
  <div class="modal" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h2 id="modalTitle">Report #<span id="modalId"></span></h2>
    <div class="modal-meta" id="modalMeta"></div>
    <div class="modal-reason" id="modalReason"></div>
    <label>Admin note (optional)</label>
    <textarea id="modalNote" placeholder="Add a note…"></textarea>
    <div class="modal-btns">
      <button class="btn"     onclick="closeModal()">Cancel</button>
      <button class="act-btn rev" onclick="modalAct('reviewed')">Mark Reviewed</button>
      <button class="act-btn dis" onclick="modalAct('dismissed')">Dismiss</button>
      <button class="act-btn act" id="modalActBtn" onclick="modalAct('actioned')">Take Action</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
let curId = null;

function openModal(id, reason, reporter, reported, note, actionMode) {
  curId = id;
  document.getElementById('modalId').textContent      = id;
  document.getElementById('modalMeta').textContent    = reporter + ' reported ' + reported;
  document.getElementById('modalReason').textContent  = reason;
  document.getElementById('modalNote').value          = note || '';
  document.getElementById('modalActBtn').style.display = actionMode ? '' : 'none';
  document.getElementById('overlay').classList.add('on');
}

function closeModal(e) {
  if (e && e.target !== document.getElementById('overlay')) return;
  document.getElementById('overlay').classList.remove('on');
  curId = null;
}

function modalAct(action) {
  if (!curId) return;
  act(curId, action, document.getElementById('modalNote').value);
  document.getElementById('overlay').classList.remove('on');
}

function act(reportId, action, note) {
  const row = document.querySelector(`.rrow[data-id="${reportId}"]`);
  const fd  = new FormData();
  fd.append('ajax', '1');
  fd.append('action', action);
  fd.append('report_id', reportId);
  fd.append('note', note || '');

  fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        toast_('✅ Report marked as ' + action);
        // Fade and remove the row
        if (row) {
          row.style.transition = 'opacity .4s, transform .4s';
          row.style.opacity    = '0';
          row.style.transform  = 'translateX(12px)';
          setTimeout(() => row.remove(), 420);
        }
        // Decrement pending count display
        const pill = document.querySelector('.stat[data-s="<?= $filter_status ?>"] .stat-n');
        if (pill) {
          const cur = parseInt(pill.textContent) || 0;
          pill.textContent = Math.max(0, cur - 1);
        }
      } else {
        toast_('❌ ' + (d.message || 'Error'));
      }
    })
    .catch(() => toast_('❌ Network error'));
}

let toastT;
function toast_(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('on');
  clearTimeout(toastT);
  toastT = setTimeout(() => t.classList.remove('on'), 2800);
}
</script>
</body>
</html>