<?php
/**
 * admin_reports.php  — Upgraded
 * Full report management + decrypt chat + email user + expanded actions
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'connect.php';
require_once 'chat_crypto.php';   // needed for chat decryption

/* ── ADMIN GUARD ── */
// Uncomment when your admin auth is ready:
// if (empty($_SESSION['is_admin'])) { header('Location: index.php'); exit(); }

/* ══════════════════════════════════════════════════════════════
   AJAX HANDLERS
══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action    = $_POST['action']    ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);
    $note      = mb_substr(trim($_POST['note'] ?? ''), 0, 1000);
    $admin_id  = (int)($_SESSION['user_id'] ?? 0);

    /* ── Load full chat conversation ── */
    if ($action === 'load_chat') {
        $uid_a = (int)($_POST['user_a'] ?? 0);
        $uid_b = (int)($_POST['user_b'] ?? 0);
        if (!$uid_a || !$uid_b) { echo json_encode(['status'=>'error','msg'=>'Missing user IDs']); exit; }

        // Get AES key
        $lo = min($uid_a, $uid_b);
        $hi = max($uid_a, $uid_b);
        $ks = $con->prepare("SELECT aes_key_enc FROM conversation_keys WHERE user_a=? AND user_b=?");
        $ks->bind_param('ii', $lo, $hi);
        $ks->execute();
        $keyRow = $ks->get_result()->fetch_assoc();

        $messages = [];
        if ($keyRow) {
            $aesKey = _decryptAesKey($keyRow['aes_key_enc']);

            $ms = $con->prepare("
                SELECT m.message_id, m.sender_id, m.message_text, m.message_enc,
                       m.media_path, m.message_type, m.shared_post_id,
                       m.status, m.created_at, u.user_name, u.profile_image
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                WHERE (m.sender_id=? AND m.receiver_id=?)
                   OR (m.sender_id=? AND m.receiver_id=?)
                ORDER BY m.created_at ASC
            ");
            $ms->bind_param('iiii', $uid_a, $uid_b, $uid_b, $uid_a);
            $ms->execute();
            $rows = $ms->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($rows as $r) {
                $text = '';
                if (!empty($r['message_enc'])) {
                    $text = decryptMessage($r['message_enc'], $aesKey);
                } elseif (!empty($r['message_text'])) {
                    $text = $r['message_text'];
                } elseif (!empty($r['shared_post_id'])) {
                    $text = '[Shared Post #' . $r['shared_post_id'] . ']';
                } elseif (!empty($r['media_path'])) {
                    $text = '[Media: ' . basename($r['media_path']) . ']';
                }
                $messages[] = [
                    'id'      => $r['message_id'],
                    'sender'  => $r['user_name'],
                    'avatar'  => $r['profile_image'] ?: 'default_profile.png',
                    'mine'    => ($r['sender_id'] == $uid_a),
                    'text'    => $text,
                    'type'    => $r['message_type'] ?? 'text',
                    'media'   => $r['media_path'] ?? '',
                    'status'  => $r['status'],
                    'time'    => date('M j, Y · H:i', strtotime($r['created_at'])),
                ];
            }
            echo json_encode(['status' => 'ok', 'messages' => $messages, 'count' => count($messages)]);
        } else {
            // No encryption key — try loading plain messages (pre-encryption)
            $ms = $con->prepare("
                SELECT m.message_id, m.sender_id, m.message_text,
                       m.media_path, m.message_type, m.shared_post_id,
                       m.status, m.created_at, u.user_name, u.profile_image
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                WHERE (m.sender_id=? AND m.receiver_id=?)
                   OR (m.sender_id=? AND m.receiver_id=?)
                ORDER BY m.created_at ASC
            ");
            $ms->bind_param('iiii', $uid_a, $uid_b, $uid_b, $uid_a);
            $ms->execute();
            $rows = $ms->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $r) {
                $text = $r['message_text'] ?: ($r['shared_post_id'] ? '[Shared Post]' : '[Media]');
                $messages[] = [
                    'id'     => $r['message_id'],
                    'sender' => $r['user_name'],
                    'avatar' => $r['profile_image'] ?: 'default_profile.png',
                    'mine'   => ($r['sender_id'] == $uid_a),
                    'text'   => $text,
                    'type'   => $r['message_type'] ?? 'text',
                    'media'  => $r['media_path'] ?? '',
                    'status' => $r['status'],
                    'time'   => date('M j, Y · H:i', strtotime($r['created_at'])),
                ];
            }
            echo json_encode(['status' => 'ok', 'messages' => $messages, 'count' => count($messages), 'note' => 'no_encryption_key']);
        }
        exit;
    }

    /* ── Send email to user ── */
    if ($action === 'send_email') {
        $to      = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject']  ?? '');
        $body    = trim($_POST['body']     ?? '');
        if (!$to || !$subject || !$body) { echo json_encode(['status'=>'error','msg'=>'Missing fields']); exit; }
        $headers  = "From: Connectify Admin <no-reply@connectify.com>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = mail($to, $subject, $body, $headers);
        echo json_encode($sent ? ['status'=>'sent'] : ['status'=>'error','msg'=>'mail() failed — check server mail config']);
        exit;
    }

    /* ── Warn user (store warning in DB) ── */
    if ($action === 'warn_user') {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $warning   = mb_substr(trim($_POST['warning'] ?? ''), 0, 500);
        if (!$target_id) { echo json_encode(['status'=>'error','msg'=>'No target']); exit; }
        // Store in a simple warnings table (create if needed)
        $con->query("CREATE TABLE IF NOT EXISTS user_warnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            warning TEXT,
            warned_by INT,
            created_at DATETIME DEFAULT NOW(),
            INDEX(user_id)
        )");
        $w = $con->prepare("INSERT INTO user_warnings (user_id, warning, warned_by) VALUES (?,?,?)");
        $w->bind_param('isi', $target_id, $warning, $admin_id);
        echo $w->execute() ? json_encode(['status'=>'ok']) : json_encode(['status'=>'error']);
        exit;
    }

    /* ── Suspend / unsuspend user ── */
    if ($action === 'suspend_user' || $action === 'unsuspend_user') {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $suspend   = ($action === 'suspend_user') ? 1 : 0;
        $u = $con->prepare("UPDATE users SET is_suspended=? WHERE user_id=?");
        $u->bind_param('ii', $suspend, $target_id);
        echo $u->execute() ? json_encode(['status'=>'ok']) : json_encode(['status'=>'error','msg'=>$con->error]);
        exit;
    }

    /* ── Delete user account ── */
    if ($action === 'delete_user') {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $d = $con->prepare("DELETE FROM users WHERE user_id=?");
        $d->bind_param('i', $target_id);
        echo $d->execute() ? json_encode(['status'=>'ok']) : json_encode(['status'=>'error','msg'=>$con->error]);
        exit;
    }

    /* ── Standard report status updates ── */
    if (in_array($action, ['reviewed', 'dismissed', 'actioned', 'escalated'])) {
        $upd = $con->prepare("
            UPDATE user_reports
            SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE report_id = ?
        ");
        $upd->bind_param('ssii', $action, $note, $admin_id, $report_id);
        echo $upd->execute()
            ? json_encode(['status' => 'success'])
            : json_encode(['status' => 'error', 'message' => $con->error]);
        exit;
    }

    echo json_encode(['status'=>'error','msg'=>'Unknown action']);
    exit;
}

/* ── Filters ── */
$filter_status = in_array($_GET['status'] ?? '', ['pending','reviewed','dismissed','actioned','escalated'])
    ? $_GET['status'] : 'pending';
$search = trim($_GET['search'] ?? '');

/* ── Fetch reports with emails ── */
$where  = "WHERE r.status = ?";
$params = [$filter_status];
$types  = 's';

if ($search !== '') {
    $where  .= " AND (rep.user_name LIKE ? OR rpd.user_name LIKE ? OR r.reason LIKE ? OR rep.email_id LIKE ? OR rpd.email_id LIKE ?)";
    $like    = '%' . $search . '%';
    $params  = array_merge($params, [$like, $like, $like, $like, $like]);
    $types  .= 'sssss';
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
        rep.user_id    AS reporter_id,
        rep.user_name  AS reporter_name,
        rep.email_id   AS reporter_email,
        rep.profile_image AS reporter_img,
        rpd.user_id    AS reported_id,
        rpd.user_name  AS reported_name,
        rpd.email_id   AS reported_email,
        rpd.profile_image AS reported_img,
        adm.user_name  AS admin_name
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
$counts = ['pending'=>0,'reviewed'=>0,'dismissed'=>0,'actioned'=>0,'escalated'=>0];
$cq = $con->query("SELECT status, COUNT(*) AS c FROM user_reports GROUP BY status");
while ($row = $cq->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Report Management — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ═══════════════════ RESET ═══════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#0b0c0f;--bg2:#111318;--bg3:#181b22;--bg4:#1e2128;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.12);
  --txt:#e8eaf0;--sub:rgba(232,234,240,.45);
  --acc:#ff3b5c;--acc2:#ff7a5c;
  --green:#2de07e;--yellow:#f5c843;--blue:#5b8fff;--purple:#9b5de5;
  --font:'Syne',sans-serif;--mono:'JetBrains Mono',monospace;
  --r:14px;--sh:0 8px 32px rgba(0,0,0,.5);
}
html{font-size:15px;}
body{background:var(--bg);color:var(--txt);font-family:var(--font);min-height:100vh;line-height:1.5;}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.028'/%3E%3C/svg%3E");}

/* ═══════════════════ LAYOUT ═══════════════════ */
.wrap{position:relative;z-index:1;max-width:1400px;margin:0 auto;padding:0 24px 80px;}

/* ═══════════════════ HEADER ═══════════════════ */
.hdr{display:flex;align-items:center;gap:16px;padding:28px 0 24px;border-bottom:1px solid var(--border2);margin-bottom:32px;}
.hdr-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,var(--acc),var(--acc2));display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 20px rgba(255,59,92,.35);}
.hdr h1{font-size:1.55rem;font-weight:800;letter-spacing:-.5px;background:linear-gradient(90deg,var(--txt) 0%,rgba(232,234,240,.6) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.hdr small{font-size:.73rem;color:var(--sub);font-family:var(--mono);display:block;margin-top:2px;}
.hdr-back{margin-left:auto;background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:8px 16px;border-radius:10px;font-family:var(--font);font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:background .18s,transform .15s;display:inline-flex;align-items:center;gap:6px;}
.hdr-back:hover{background:var(--border2);transform:translateY(-1px);}

/* ═══════════════════ STATS ═══════════════════ */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:28px;}
.stat{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;cursor:pointer;transition:border-color .2s,transform .15s;text-decoration:none;display:block;}
.stat:hover{transform:translateY(-2px);border-color:var(--border2);}
.stat.active{border-color:var(--acc);background:rgba(255,59,92,.07);}
.stat-n{font-size:1.8rem;font-weight:800;font-family:var(--mono);line-height:1;}
.stat-lbl{font-size:.72rem;color:var(--sub);margin-top:4px;text-transform:uppercase;letter-spacing:.8px;}
.stat[data-s=pending]   .stat-n{color:var(--yellow);}
.stat[data-s=reviewed]  .stat-n{color:var(--blue);}
.stat[data-s=dismissed] .stat-n{color:var(--sub);}
.stat[data-s=actioned]  .stat-n{color:var(--green);}
.stat[data-s=escalated] .stat-n{color:var(--acc);}

/* ═══════════════════ TOOLBAR ═══════════════════ */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.srch-wrap{flex:1;min-width:200px;position:relative;}
.srch-wrap input{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:10px;color:var(--txt);padding:9px 14px 9px 36px;font-family:var(--font);font-size:.85rem;outline:none;transition:border-color .2s;}
.srch-wrap input:focus{border-color:var(--acc);}
.srch-wrap .srch-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;color:var(--sub);pointer-events:none;}
.toolbar form{display:contents;}
.btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:9px 18px;border-radius:10px;font-family:var(--font);font-size:.83rem;font-weight:600;cursor:pointer;transition:background .18s,transform .15s;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn:hover{background:var(--border2);transform:translateY(-1px);}
.btn.acc{background:var(--acc);border-color:var(--acc);color:#fff;}
.btn.acc:hover{background:#e02a4e;}

/* ═══════════════════ TABLE ═══════════════════ */
.tbl-wrap{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh);}
.tbl-head{display:grid;grid-template-columns:44px 1.4fr 1.4fr 2fr 110px 1.8fr;padding:10px 18px;background:var(--bg3);border-bottom:1px solid var(--border2);font-size:.71rem;text-transform:uppercase;letter-spacing:.9px;color:var(--sub);font-family:var(--mono);}
.rrow{display:grid;grid-template-columns:44px 1.4fr 1.4fr 2fr 110px 1.8fr;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border);transition:background .15s;gap:4px;}
.rrow:last-child{border-bottom:none;}
.rrow:hover{background:rgba(255,255,255,.022);}
.rrow-id{font-family:var(--mono);font-size:.72rem;color:var(--sub);}

/* user cell */
.ucel{display:flex;align-items:center;gap:8px;min-width:0;}
.ucel img{width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1.5px solid var(--border2);}
.ucel-name{font-size:.84rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ucel-id{font-size:.69rem;color:var(--sub);font-family:var(--mono);}
.ucel-email{font-size:.68rem;color:var(--blue);font-family:var(--mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;}

/* reason */
.reason-cel{min-width:0;}
.reason-text{font-size:.82rem;color:var(--txt);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;cursor:pointer;transition:color .15s;}
.reason-text:hover{color:#fff;}
.reason-meta{font-size:.68rem;color:var(--sub);margin-top:3px;font-family:var(--mono);}

/* badge */
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:8px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;font-family:var(--mono);white-space:nowrap;}
.badge.pending  {background:rgba(245,200,67,.12);color:var(--yellow);border:1px solid rgba(245,200,67,.3);}
.badge.reviewed {background:rgba(91,143,255,.12);color:var(--blue);border:1px solid rgba(91,143,255,.3);}
.badge.dismissed{background:rgba(255,255,255,.05);color:var(--sub);border:1px solid var(--border);}
.badge.actioned {background:rgba(45,224,126,.1);color:var(--green);border:1px solid rgba(45,224,126,.3);}
.badge.escalated{background:rgba(255,59,92,.1);color:var(--acc);border:1px solid rgba(255,59,92,.3);}
.badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;}

/* action buttons */
.acts{display:flex;gap:5px;flex-wrap:wrap;}
.act-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:5px 10px;border-radius:8px;font-family:var(--font);font-size:.73rem;font-weight:600;cursor:pointer;transition:background .15s,transform .12s;white-space:nowrap;}
.act-btn:hover{transform:scale(1.04);}
.act-btn.rev{border-color:rgba(91,143,255,.4);color:var(--blue);}
.act-btn.rev:hover{background:rgba(91,143,255,.15);}
.act-btn.dis{border-color:rgba(255,255,255,.15);color:var(--sub);}
.act-btn.dis:hover{background:rgba(255,255,255,.07);}
.act-btn.act{border-color:rgba(45,224,126,.4);color:var(--green);}
.act-btn.act:hover{background:rgba(45,224,126,.12);}
.act-btn.esc{border-color:rgba(255,59,92,.4);color:var(--acc);}
.act-btn.esc:hover{background:rgba(255,59,92,.12);}
.act-btn.chat{border-color:rgba(155,93,229,.4);color:var(--purple);}
.act-btn.chat:hover{background:rgba(155,93,229,.15);}
.act-btn.email{border-color:rgba(91,143,255,.4);color:var(--blue);}
.act-btn.email:hover{background:rgba(91,143,255,.15);}

/* empty */
.empty{padding:60px 20px;text-align:center;color:var(--sub);}
.empty-icon{font-size:3rem;display:block;margin-bottom:12px;}
.empty h3{font-size:1.1rem;color:var(--txt);margin-bottom:6px;}

/* ═══════════════════ OVERLAY ═══════════════════ */
.overlay{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;}
.overlay.on{opacity:1;pointer-events:all;}

/* ═══════════════════ MODAL (report details) ═══════════════════ */
.modal{background:var(--bg2);border:1px solid var(--border2);border-radius:20px;width:100%;max-width:520px;padding:28px;box-shadow:0 32px 80px rgba(0,0,0,.7);transform:translateY(18px) scale(.97);transition:transform .3s cubic-bezier(.34,1.56,.64,1);position:relative;}
.overlay.on .modal{transform:translateY(0) scale(1);}
.modal h2{font-size:1.15rem;font-weight:800;margin-bottom:6px;}
.modal-meta{font-size:.78rem;color:var(--sub);margin-bottom:18px;font-family:var(--mono);}
.modal-reason{background:var(--bg3);border:1px solid var(--border2);border-radius:12px;padding:14px;font-size:.85rem;color:var(--txt);line-height:1.6;margin-bottom:18px;max-height:180px;overflow-y:auto;}
.modal label{font-size:.78rem;color:var(--sub);display:block;margin-bottom:5px;font-family:var(--mono);}
.modal textarea,
.modal input[type=text],
.modal input[type=email],
.modal select{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:12px;color:var(--txt);padding:10px 14px;font-family:var(--font);font-size:.85rem;outline:none;margin-bottom:14px;transition:border-color .2s;}
.modal textarea{resize:vertical;min-height:80px;}
.modal textarea:focus,
.modal input:focus,
.modal select:focus{border-color:var(--acc);}
.modal select option{background:var(--bg3);}
.modal-btns{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:4px;}
.modal-close{position:absolute;top:16px;right:16px;background:var(--bg3);border:1px solid var(--border2);color:var(--sub);width:30px;height:30px;border-radius:8px;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,color .15s;}
.modal-close:hover{background:var(--border2);color:var(--txt);}
.modal-section{border-top:1px solid var(--border2);padding-top:14px;margin-top:4px;}
.modal-section-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.8px;color:var(--sub);font-family:var(--mono);margin-bottom:10px;}

/* ═══════════════════ CHAT VIEWER MODAL ═══════════════════ */
.chat-modal{background:var(--bg2);border:1px solid var(--border2);border-radius:20px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 32px 80px rgba(0,0,0,.7);transform:translateY(18px) scale(.97);transition:transform .3s cubic-bezier(.34,1.56,.64,1);overflow:hidden;}
.overlay.on .chat-modal{transform:translateY(0) scale(1);}
.chat-modal-hdr{padding:20px 22px 16px;border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:12px;flex-shrink:0;}
.chat-modal-hdr h2{font-size:1rem;font-weight:800;flex:1;}
.chat-modal-hdr .chat-count{font-size:.75rem;color:var(--sub);font-family:var(--mono);}
.chat-modal-body{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:10px;}
.chat-modal-body::-webkit-scrollbar{width:4px;}
.chat-modal-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}
/* chat bubbles in admin view */
.adm-msg{display:flex;flex-direction:column;max-width:72%;}
.adm-msg.left{align-self:flex-start;align-items:flex-start;}
.adm-msg.right{align-self:flex-end;align-items:flex-end;}
.adm-msg-who{font-size:.7rem;color:var(--sub);margin-bottom:3px;font-family:var(--mono);display:flex;align-items:center;gap:6px;}
.adm-msg-who img{width:18px;height:18px;border-radius:50%;object-fit:cover;}
.adm-bub{padding:9px 13px;border-radius:16px;font-size:.85rem;line-height:1.45;word-break:break-word;max-width:100%;}
.adm-msg.left  .adm-bub{background:rgba(255,255,255,.08);border-bottom-left-radius:4px;}
.adm-msg.right .adm-bub{background:linear-gradient(135deg,#9b5de5,#f15bb5);color:#fff;border-bottom-right-radius:4px;}
.adm-msg-time{font-size:.67rem;color:var(--sub);margin-top:3px;font-family:var(--mono);}
.adm-loading{text-align:center;padding:40px;color:var(--sub);font-size:.85rem;}
.adm-empty{text-align:center;padding:40px;color:var(--sub);}
.adm-empty-icon{font-size:2.5rem;display:block;margin-bottom:8px;}
.adm-enc-note{background:rgba(155,93,229,.1);border:1px solid rgba(155,93,229,.25);border-radius:10px;padding:10px 14px;font-size:.78rem;color:var(--purple);margin-bottom:12px;flex-shrink:0;}
.chat-modal-footer{padding:14px 20px;border-top:1px solid var(--border2);display:flex;gap:8px;flex-shrink:0;}

/* ═══════════════════ EMAIL MODAL ═══════════════════ */
.email-modal{background:var(--bg2);border:1px solid var(--border2);border-radius:20px;width:100%;max-width:520px;padding:28px;box-shadow:0 32px 80px rgba(0,0,0,.7);transform:translateY(18px) scale(.97);transition:transform .3s cubic-bezier(.34,1.56,.64,1);position:relative;}
.overlay.on .email-modal{transform:translateY(0) scale(1);}
.email-modal h2{font-size:1.1rem;font-weight:800;margin-bottom:16px;}
.email-tmpl-row{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
.tmpl-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--sub);padding:5px 12px;border-radius:8px;font-size:.75rem;cursor:pointer;transition:all .15s;font-family:var(--font);}
.tmpl-btn:hover{border-color:var(--blue);color:var(--blue);background:rgba(91,143,255,.1);}

/* ═══════════════════ TOAST ═══════════════════ */
#toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(12px);background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:10px 22px;border-radius:12px;font-size:.83rem;font-family:var(--font);font-weight:600;z-index:99999;opacity:0;pointer-events:none;transition:opacity .3s,transform .3s;box-shadow:var(--sh);white-space:nowrap;}
#toast.on{opacity:1;transform:translateX(-50%) translateY(0);}

/* ═══════════════════ RESPONSIVE ═══════════════════ */
@media(max-width:1000px){
  .stats{grid-template-columns:repeat(3,1fr);}
  .tbl-head,.rrow{grid-template-columns:44px 1fr 1fr;}
  .tbl-head>*:nth-child(n+4),.rrow>*:nth-child(n+4){display:none;}
}
@media(max-width:560px){
  .tbl-head{display:none;}
  .rrow{grid-template-columns:1fr;gap:10px;padding:14px;}
  .rrow>*{display:block!important;}
  .stats{grid-template-columns:repeat(2,1fr);}
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
      <small>User safety dashboard · Connectify Admin</small>
    </div>
    <a href="index.php" class="hdr-back">← Back to site</a>
  </div>

  <!-- STATS -->
  <div class="stats">
    <?php
    $statLabels = ['pending'=>'⏳ Pending','reviewed'=>'🔍 Reviewed','dismissed'=>'✗ Dismissed','actioned'=>'✅ Actioned','escalated'=>'🔺 Escalated'];
    foreach ($statLabels as $s => $lbl): ?>
      <a href="?status=<?=$s?>&search=<?=urlencode($search)?>"
         class="stat <?=$filter_status===$s?'active':''?>" data-s="<?=$s?>">
        <div class="stat-n"><?=$counts[$s]?></div>
        <div class="stat-lbl"><?=$lbl?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <form method="get" style="display:contents">
      <input type="hidden" name="status" value="<?=htmlspecialchars($filter_status)?>">
      <div class="srch-wrap">
        <span class="srch-icon">🔎</span>
        <input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search reporter, reported, reason, email…">
      </div>
      <button type="submit" class="btn acc">Search</button>
      <?php if ($search): ?><a href="?status=<?=$filter_status?>" class="btn">Clear</a><?php endif; ?>
    </form>
  </div>

  <!-- TABLE -->
  <div class="tbl-wrap">
    <div class="tbl-head">
      <div>#</div><div>Reporter</div><div>Reported</div>
      <div>Reason</div><div>Status</div><div>Actions</div>
    </div>

    <?php if (empty($reports)): ?>
      <div class="empty">
        <span class="empty-icon">🎉</span>
        <h3>No <?=$filter_status?> reports</h3>
        <p>Nothing to review here.</p>
      </div>
    <?php else: foreach ($reports as $r):
      $repImg = !empty($r['reporter_img']) ? htmlspecialchars($r['reporter_img']) : 'default_profile.png';
      $rpdImg = !empty($r['reported_img']) ? htmlspecialchars($r['reported_img']) : 'default_profile.png';
      $rData  = htmlspecialchars(json_encode($r), ENT_QUOTES);
    ?>
      <div class="rrow" data-id="<?=$r['report_id']?>">

        <div class="rrow-id">#<?=$r['report_id']?></div>

        <!-- Reporter -->
        <div class="ucel">
          <img src="<?=$repImg?>" alt="">
          <div>
            <div class="ucel-name"><?=htmlspecialchars($r['reporter_name'])?></div>
            <div class="ucel-id">ID <?=$r['reporter_id']?></div>
            <div class="ucel-email" title="<?=htmlspecialchars($r['reporter_email']??'')?>"><?=htmlspecialchars($r['reporter_email']??'—')?></div>
          </div>
        </div>

        <!-- Reported -->
        <div class="ucel">
          <img src="<?=$rpdImg?>" alt="">
          <div>
            <div class="ucel-name"><?=htmlspecialchars($r['reported_name'])?></div>
            <div class="ucel-id">ID <?=$r['reported_id']?></div>
            <div class="ucel-email" title="<?=htmlspecialchars($r['reported_email']??'')?>"><?=htmlspecialchars($r['reported_email']??'—')?></div>
          </div>
        </div>

        <!-- Reason -->
        <div class="reason-cel">
          <div class="reason-text" onclick='openDetailModal(<?=$rData?>)'>
            <?=htmlspecialchars($r['reason'])?>
          </div>
          <div class="reason-meta">
            <?=date('M j, Y · H:i', strtotime($r['created_at']))?>
            <?php if ($r['ip_address']): ?> · <?=htmlspecialchars($r['ip_address'])?><?php endif; ?>
            <?php if ($r['admin_name']): ?> · by <?=htmlspecialchars($r['admin_name'])?><?php endif; ?>
          </div>
        </div>

        <!-- Status -->
        <div><span class="badge <?=$r['status']?>"><?=$r['status']?></span></div>

        <!-- Actions -->
        <div class="acts">
          <button class="act-btn chat"
                  onclick='openChat(<?=$r['reporter_id']?>,<?=$r['reported_id']?>,
                    <?=htmlspecialchars(json_encode($r['reporter_name']),ENT_QUOTES)?>,
                    <?=htmlspecialchars(json_encode($r['reported_name']),ENT_QUOTES)?>)'>
            💬 Chat
          </button>
          <button class="act-btn email"
                  onclick='openEmail(
                    <?=htmlspecialchars(json_encode($r['reporter_email']??''),ENT_QUOTES)?>,
                    <?=htmlspecialchars(json_encode($r['reported_email']??''),ENT_QUOTES)?>,
                    <?=htmlspecialchars(json_encode($r['reporter_name']),ENT_QUOTES)?>,
                    <?=htmlspecialchars(json_encode($r['reported_name']),ENT_QUOTES)?>)'>
            📧 Email
          </button>
          <button class="act-btn rev"  onclick='openDetailModal(<?=$rData?>, "reviewed")'>Review</button>
          <button class="act-btn act"  onclick='openDetailModal(<?=$rData?>, "actioned")'>Action</button>
          <button class="act-btn esc"  onclick='openDetailModal(<?=$rData?>, "escalated")'>Escalate</button>
          <button class="act-btn dis"  onclick='act(<?=$r['report_id']?>,"dismissed","")'>Dismiss</button>
        </div>

      </div>
    <?php endforeach; endif; ?>
  </div>

</div><!-- /wrap -->

<!-- ════════════ DETAIL / ACTION MODAL ════════════ -->
<div class="overlay" id="detailOverlay" onclick="closeOverlay('detailOverlay',event)">
  <div class="modal" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeOverlay('detailOverlay')">✕</button>
    <h2>Report #<span id="dModalId"></span></h2>
    <div class="modal-meta" id="dModalMeta"></div>
    <div class="modal-reason" id="dModalReason"></div>

    <!-- Quick user actions -->
    <div class="modal-section">
      <div class="modal-section-title">User Actions</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
        <button class="act-btn esc" id="dSuspendBtn"  onclick="suspendUser()">🚫 Suspend Reported User</button>
        <button class="act-btn rev" id="dUnsuspendBtn" onclick="unsuspendUser()">✅ Unsuspend</button>
        <button class="act-btn dis" id="dWarnBtn"     onclick="warnUser()">⚠️ Issue Warning</button>
        <button class="act-btn" style="border-color:rgba(255,59,92,.5);color:var(--acc);" id="dDeleteBtn" onclick="deleteUser()">🗑️ Delete Account</button>
      </div>
    </div>

    <!-- Admin note -->
    <label>Admin note</label>
    <textarea id="dNote" placeholder="Add a note visible only to admins…"></textarea>

    <!-- Status change -->
    <label>Change status to</label>
    <select id="dStatusSelect">
      <option value="reviewed">🔍 Reviewed</option>
      <option value="actioned">✅ Actioned</option>
      <option value="escalated">🔺 Escalated</option>
      <option value="dismissed">✗ Dismissed</option>
    </select>

    <div class="modal-btns">
      <button class="btn" onclick="closeOverlay('detailOverlay')">Cancel</button>
      <button class="btn acc" onclick="saveDetail()">Save</button>
    </div>
  </div>
</div>

<!-- ════════════ CHAT VIEWER MODAL ════════════ -->
<div class="overlay" id="chatOverlay" onclick="closeOverlay('chatOverlay',event)">
  <div class="chat-modal" onclick="event.stopPropagation()">
    <div class="chat-modal-hdr">
      <div>
        <h2 id="chatTitle">Conversation</h2>
        <span class="chat-count" id="chatCount"></span>
      </div>
      <button class="modal-close" style="position:static;margin-left:auto;" onclick="closeOverlay('chatOverlay')">✕</button>
    </div>
    <div id="chatEncNote" class="adm-enc-note" style="display:none;margin:12px 20px 0;">
      🔐 Messages are end-to-end encrypted and have been decrypted for this admin view.
    </div>
    <div class="chat-modal-body" id="chatBody">
      <div class="adm-loading">Loading conversation…</div>
    </div>
    <div class="chat-modal-footer">
      <button class="btn" onclick="closeOverlay('chatOverlay')" style="margin-left:auto;">Close</button>
    </div>
  </div>
</div>

<!-- ════════════ EMAIL MODAL ════════════ -->
<div class="overlay" id="emailOverlay" onclick="closeOverlay('emailOverlay',event)">
  <div class="email-modal" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeOverlay('emailOverlay')">✕</button>
    <h2>📧 Send Email</h2>

    <!-- Quick templates -->
    <div class="modal-section-title" style="margin-bottom:8px;">Quick Templates</div>
    <div class="email-tmpl-row">
      <button class="tmpl-btn" onclick="applyTemplate('warning')">⚠️ Warning</button>
      <button class="tmpl-btn" onclick="applyTemplate('resolved')">✅ Resolved</button>
      <button class="tmpl-btn" onclick="applyTemplate('suspended')">🚫 Suspended</button>
      <button class="tmpl-btn" onclick="applyTemplate('info')">ℹ️ More Info Needed</button>
      <button class="tmpl-btn" onclick="applyTemplate('cleared')">🎉 Cleared</button>
    </div>

    <label>To</label>
    <select id="emailTo">
      <option id="emailOptReporter" value="">Reporter — </option>
      <option id="emailOptReported" value="">Reported — </option>
    </select>

    <label>Subject</label>
    <input type="text" id="emailSubject" placeholder="Email subject…">

    <label>Message</label>
    <textarea id="emailBody" style="min-height:140px;" placeholder="Write your message…"></textarea>

    <div class="modal-btns">
      <button class="btn" onclick="closeOverlay('emailOverlay')">Cancel</button>
      <button class="btn acc" onclick="sendEmail()">Send Email</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
// ── State ──────────────────────────────────────────────────────
let curReport       = null;  // current report object in detail modal
let emailReporter   = {email:'', name:''};
let emailReported   = {email:'', name:''};

// ── Overlay helpers ────────────────────────────────────────────
function closeOverlay(id, e) {
  if (e && e.target !== document.getElementById(id)) return;
  document.getElementById(id).classList.remove('on');
}
function openOverlay(id) {
  document.getElementById(id).classList.add('on');
}

/* ════════════════════════════════════════════
   DETAIL / ACTION MODAL
════════════════════════════════════════════ */
function openDetailModal(r, defaultAction) {
  curReport = r;
  document.getElementById('dModalId').textContent   = r.report_id;
  document.getElementById('dModalMeta').textContent =
    r.reporter_name + ' → ' + r.reported_name + '  ·  ' +
    new Date(r.created_at).toLocaleString();
  document.getElementById('dModalReason').textContent = r.reason;
  document.getElementById('dNote').value              = r.admin_note || '';

  if (defaultAction) {
    document.getElementById('dStatusSelect').value = defaultAction;
  }

  // Store IDs on buttons for user actions
  document.getElementById('dSuspendBtn').dataset.uid   = r.reported_id;
  document.getElementById('dUnsuspendBtn').dataset.uid = r.reported_id;
  document.getElementById('dWarnBtn').dataset.uid      = r.reported_id;
  document.getElementById('dDeleteBtn').dataset.uid    = r.reported_id;

  openOverlay('detailOverlay');
}

function saveDetail() {
  if (!curReport) return;
  const status = document.getElementById('dStatusSelect').value;
  const note   = document.getElementById('dNote').value;
  act(curReport.report_id, status, note, () => {
    closeOverlay('detailOverlay');
  });
}

/* ════════════════════════════════════════════
   REPORT STATUS ACTIONS
════════════════════════════════════════════ */
function act(reportId, action, note, cb) {
  const row = document.querySelector(`.rrow[data-id="${reportId}"]`);
  const fd  = new FormData();
  fd.append('ajax','1'); fd.append('action',action);
  fd.append('report_id',reportId); fd.append('note',note||'');

  fetch('', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        toast_('✅ Marked as ' + action);
        if (row) {
          row.style.transition = 'opacity .4s,transform .4s';
          row.style.opacity    = '0';
          row.style.transform  = 'translateX(12px)';
          setTimeout(() => row.remove(), 420);
        }
        updateStatCount();
        if (cb) cb();
      } else {
        toast_('❌ ' + (d.message || 'Error'));
      }
    })
    .catch(() => toast_('❌ Network error'));
}

function updateStatCount() {
  const pill = document.querySelector('.stat.active .stat-n');
  if (pill) {
    const cur = parseInt(pill.textContent) || 0;
    pill.textContent = Math.max(0, cur - 1);
  }
}

/* ════════════════════════════════════════════
   USER ACTIONS (suspend / warn / delete)
════════════════════════════════════════════ */
function suspendUser() {
  const uid = document.getElementById('dSuspendBtn').dataset.uid;
  if (!uid || !confirm('Suspend this user? They will not be able to log in.')) return;
  userAction('suspend_user', uid, () => toast_('🚫 User suspended'));
}
function unsuspendUser() {
  const uid = document.getElementById('dUnsuspendBtn').dataset.uid;
  if (!uid) return;
  userAction('unsuspend_user', uid, () => toast_('✅ User unsuspended'));
}
function warnUser() {
  const uid = document.getElementById('dWarnBtn').dataset.uid;
  const warning = prompt('Enter the warning message to issue to this user:');
  if (!warning || !warning.trim()) return;
  const fd = new FormData();
  fd.append('ajax','1'); fd.append('action','warn_user');
  fd.append('target_id',uid); fd.append('warning',warning.trim());
  fetch('', {method:'POST',body:fd}).then(r=>r.json()).then(d => {
    toast_(d.status==='ok' ? '⚠️ Warning recorded' : '❌ Could not save warning');
  });
}
function deleteUser() {
  const uid = document.getElementById('dDeleteBtn').dataset.uid;
  if (!uid || !confirm('⚠️ PERMANENTLY delete this user account? This cannot be undone.')) return;
  if (!confirm('Are you absolutely sure? All their data will be removed.')) return;
  userAction('delete_user', uid, () => {
    toast_('🗑️ Account deleted');
    closeOverlay('detailOverlay');
  });
}
function userAction(action, uid, cb) {
  const fd = new FormData();
  fd.append('ajax','1'); fd.append('action',action); fd.append('target_id',uid);
  fetch('', {method:'POST',body:fd}).then(r=>r.json()).then(d => {
    if (d.status==='ok') { if(cb) cb(); }
    else toast_('❌ '+(d.msg||'Error'));
  }).catch(()=>toast_('❌ Network error'));
}

/* ════════════════════════════════════════════
   CHAT VIEWER
════════════════════════════════════════════ */
function openChat(uidA, uidB, nameA, nameB) {
  document.getElementById('chatTitle').textContent  = nameA + ' ↔ ' + nameB;
  document.getElementById('chatCount').textContent  = '';
  document.getElementById('chatEncNote').style.display = 'none';
  document.getElementById('chatBody').innerHTML     = '<div class="adm-loading">🔓 Decrypting conversation…</div>';
  openOverlay('chatOverlay');

  const fd = new FormData();
  fd.append('ajax','1'); fd.append('action','load_chat');
  fd.append('user_a', uidA); fd.append('user_b', uidB);

  fetch('', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.status !== 'ok') {
        document.getElementById('chatBody').innerHTML =
          '<div class="adm-empty"><span class="adm-empty-icon">❌</span>Could not load: ' + (d.msg||'unknown error') + '</div>';
        return;
      }

      document.getElementById('chatCount').textContent = d.count + ' messages';

      if (!d.note) {
        // Encryption key exists — show decryption notice
        document.getElementById('chatEncNote').style.display = 'block';
      }

      if (d.messages.length === 0) {
        document.getElementById('chatBody').innerHTML =
          '<div class="adm-empty"><span class="adm-empty-icon">💬</span>No messages found between these users.</div>';
        return;
      }

      let html = '';
      d.messages.forEach(m => {
        const side = m.mine ? 'right' : 'left';
        const mediaHtml = m.media ? `<div style="margin-top:6px;"><a href="${escHtml(m.media)}" target="_blank" style="color:var(--blue);font-size:.78rem;">📎 ${escHtml(m.media.split('/').pop())}</a></div>` : '';
        html += `
          <div class="adm-msg ${side}">
            <div class="adm-msg-who">
              <img src="${escHtml(m.avatar)}" alt="">
              ${escHtml(m.sender)}
            </div>
            <div class="adm-bub">${escHtml(m.text)}${mediaHtml}</div>
            <div class="adm-msg-time">${escHtml(m.time)} · ${escHtml(m.status)}</div>
          </div>`;
      });
      document.getElementById('chatBody').innerHTML = html;
      // Scroll to bottom
      const body = document.getElementById('chatBody');
      body.scrollTop = body.scrollHeight;
    })
    .catch(() => {
      document.getElementById('chatBody').innerHTML =
        '<div class="adm-empty"><span class="adm-empty-icon">❌</span>Network error loading chat.</div>';
    });
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ════════════════════════════════════════════
   EMAIL MODAL
════════════════════════════════════════════ */
function openEmail(reporterEmail, reportedEmail, reporterName, reportedName) {
  emailReporter = { email: reporterEmail, name: reporterName };
  emailReported = { email: reportedEmail, name: reportedName };

  const selTo = document.getElementById('emailTo');
  document.getElementById('emailOptReporter').value       = reporterEmail;
  document.getElementById('emailOptReporter').textContent = 'Reporter — ' + reporterName + ' <' + reporterEmail + '>';
  document.getElementById('emailOptReported').value       = reportedEmail;
  document.getElementById('emailOptReported').textContent = 'Reported — ' + reportedName + ' <' + reportedEmail + '>';
  selTo.selectedIndex = 0;

  document.getElementById('emailSubject').value = '';
  document.getElementById('emailBody').value    = '';
  openOverlay('emailOverlay');
}

const TEMPLATES = {
  warning: {
    subject: 'Important Notice Regarding Your Account — Connectify',
    body: `Hi {name},

We have received a report regarding activity on your Connectify account. After reviewing the report, we are issuing this formal warning.

Please ensure your activity on our platform complies with our Community Guidelines. Repeated violations may result in suspension or permanent removal of your account.

If you believe this warning was issued in error, you may reply to this email to appeal.

Regards,
Connectify Safety Team`
  },
  resolved: {
    subject: 'Your Report Has Been Resolved — Connectify',
    body: `Hi {name},

Thank you for bringing this matter to our attention. We have reviewed the report you submitted and have taken the appropriate action.

We take all reports seriously and are committed to keeping Connectify a safe and respectful community.

Thank you for helping us maintain community standards.

Regards,
Connectify Safety Team`
  },
  suspended: {
    subject: 'Account Suspended — Connectify',
    body: `Hi {name},

Your Connectify account has been suspended due to a violation of our Community Guidelines.

If you believe this was a mistake, please reply to this email within 14 days to submit an appeal. Include any relevant context that may help us review your case.

Regards,
Connectify Safety Team`
  },
  info: {
    subject: 'More Information Required — Connectify Report',
    body: `Hi {name},

Thank you for submitting a report. We are currently reviewing your case, and we may need additional information to proceed.

Could you please reply to this email with any additional context, screenshots, or details that may help us investigate?

Regards,
Connectify Safety Team`
  },
  cleared: {
    subject: 'Report Reviewed — No Action Required — Connectify',
    body: `Hi {name},

We have completed our review of the recent report involving your account. After investigation, we found no violation of our Community Guidelines.

Your account remains in good standing. Thank you for your patience.

Regards,
Connectify Safety Team`
  }
};

function applyTemplate(key) {
  const t    = TEMPLATES[key];
  const sel  = document.getElementById('emailTo');
  const name = sel.value === emailReporter.email ? emailReporter.name : emailReported.name;
  document.getElementById('emailSubject').value = t.subject;
  document.getElementById('emailBody').value    = t.body.replace('{name}', name);
}

function sendEmail() {
  const to      = document.getElementById('emailTo').value;
  const subject = document.getElementById('emailSubject').value.trim();
  const body    = document.getElementById('emailBody').value.trim();
  if (!to || !subject || !body) { toast_('⚠️ Fill in all email fields'); return; }

  const fd = new FormData();
  fd.append('ajax','1'); fd.append('action','send_email');
  fd.append('to_email',to); fd.append('subject',subject); fd.append('body',body);

  fetch('', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (d.status==='sent') {
      toast_('📧 Email sent to ' + to);
      closeOverlay('emailOverlay');
    } else {
      toast_('❌ '+(d.msg||'Email failed — check server mail config'));
    }
  }).catch(()=>toast_('❌ Network error'));
}

/* ════════════════════════════════════════════
   TOAST
════════════════════════════════════════════ */
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