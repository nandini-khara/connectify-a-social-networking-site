<?php
/**
 * admin_reports.php — Unified Report Management
 * Shows: Chat reports (user-vs-user) + Settings bug reports side by side
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'connect.php';
require_once 'chat_crypto.php';

// Uncomment when admin auth is ready:
// if (empty($_SESSION['is_admin'])) { header('Location: index.php'); exit(); }

/* ═══════════════════════════════════════════
   AJAX HANDLERS
═══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action    = $_POST['action']    ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);
    $note      = mb_substr(trim($_POST['note'] ?? ''), 0, 1000);
    $admin_id  = (int)($_SESSION['user_id'] ?? 0);

    /* Load chat conversation */
    if ($action === 'load_chat') {
        $uid_a = (int)($_POST['user_a'] ?? 0);
        $uid_b = (int)($_POST['user_b'] ?? 0);
        if (!$uid_a || !$uid_b) { echo json_encode(['status'=>'error','msg'=>'Missing user IDs']); exit; }
        $lo = min($uid_a,$uid_b); $hi = max($uid_a,$uid_b);
        $ks = $con->prepare("SELECT aes_key_enc FROM conversation_keys WHERE user_a=? AND user_b=?");
        $ks->bind_param('ii',$lo,$hi); $ks->execute();
        $keyRow = $ks->get_result()->fetch_assoc();
        $messages = []; $encrypted = false;
        if ($keyRow) {
            $encrypted = true;
            $aesKey = _decryptAesKey($keyRow['aes_key_enc']);
            $ms = $con->prepare("SELECT m.*,u.user_name,u.profile_image FROM messages m JOIN users u ON u.user_id=m.sender_id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at ASC");
            $ms->bind_param('iiii',$uid_a,$uid_b,$uid_b,$uid_a); $ms->execute();
            foreach ($ms->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $text = !empty($r['message_enc']) ? decryptMessage($r['message_enc'],$aesKey) : ($r['message_text'] ?: (!empty($r['shared_post_id']) ? '[Shared Post #'.$r['shared_post_id'].']' : '[Media]'));
                $messages[] = ['id'=>$r['message_id'],'sender'=>$r['user_name'],'avatar'=>$r['profile_image']?:'default_profile.png','mine'=>($r['sender_id']==$uid_a),'text'=>$text,'type'=>$r['message_type']??'text','media'=>$r['media_path']??'','status'=>$r['status'],'time'=>date('M j, Y · H:i',strtotime($r['created_at']))];
            }
        } else {
            $ms = $con->prepare("SELECT m.*,u.user_name,u.profile_image FROM messages m JOIN users u ON u.user_id=m.sender_id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at ASC");
            $ms->bind_param('iiii',$uid_a,$uid_b,$uid_b,$uid_a); $ms->execute();
            foreach ($ms->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $text = $r['message_text'] ?: (!empty($r['shared_post_id']) ? '[Shared Post]' : '[Media]');
                $messages[] = ['id'=>$r['message_id'],'sender'=>$r['user_name'],'avatar'=>$r['profile_image']?:'default_profile.png','mine'=>($r['sender_id']==$uid_a),'text'=>$text,'type'=>$r['message_type']??'text','media'=>$r['media_path']??'','status'=>$r['status'],'time'=>date('M j, Y · H:i',strtotime($r['created_at']))];
            }
        }
        echo json_encode(['status'=>'ok','messages'=>$messages,'count'=>count($messages),'encrypted'=>$encrypted]);
        exit;
    }

    /* Send email */
    if ($action === 'send_email') {
        $to=$_POST['to_email']??''; $subject=$_POST['subject']??''; $body=$_POST['body']??'';
        if (!$to||!$subject||!$body){echo json_encode(['status'=>'error','msg'=>'Missing fields']);exit;}
        $headers="From: Connectify Admin <no-reply@connectify.com>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        echo mail($to,$subject,$body,$headers)?json_encode(['status'=>'sent']):json_encode(['status'=>'error','msg'=>'mail() failed']);
        exit;
    }

    /* Warn user */
    if ($action === 'warn_user') {
        $target_id=(int)($_POST['target_id']??0); $warning=mb_substr(trim($_POST['warning']??''),0,500);
        if(!$target_id){echo json_encode(['status'=>'error','msg'=>'No target']);exit;}
        $con->query("CREATE TABLE IF NOT EXISTS user_warnings(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,warning TEXT,warned_by INT,created_at DATETIME DEFAULT NOW(),INDEX(user_id))");
        $w=$con->prepare("INSERT INTO user_warnings(user_id,warning,warned_by)VALUES(?,?,?)");
        $w->bind_param('isi',$target_id,$warning,$admin_id);
        echo $w->execute()?json_encode(['status'=>'ok']):json_encode(['status'=>'error']);
        exit;
    }

    /* Suspend / unsuspend */
    if ($action==='suspend_user'||$action==='unsuspend_user') {
        $target_id=(int)($_POST['target_id']??0); $s=($action==='suspend_user')?1:0;
        $u=$con->prepare("UPDATE users SET is_suspended=? WHERE user_id=?");
        $u->bind_param('ii',$s,$target_id);
        echo $u->execute()?json_encode(['status'=>'ok']):json_encode(['status'=>'error','msg'=>$con->error]);
        exit;
    }

    /* Delete user */
    if ($action==='delete_user') {
        $target_id=(int)($_POST['target_id']??0);
        $d=$con->prepare("DELETE FROM users WHERE user_id=?"); $d->bind_param('i',$target_id);
        echo $d->execute()?json_encode(['status'=>'ok']):json_encode(['status'=>'error','msg'=>$con->error]);
        exit;
    }

    /* Status update */
    if (in_array($action,['reviewed','dismissed','actioned','escalated'])) {
        $upd=$con->prepare("UPDATE user_reports SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE report_id=?");
        $upd->bind_param('ssii',$action,$note,$admin_id,$report_id);
        echo $upd->execute()?json_encode(['status'=>'success']):json_encode(['status'=>'error','message'=>$con->error]);
        exit;
    }

    echo json_encode(['status'=>'error','msg'=>'Unknown action']);
    exit;
}

/* ═══════════════════════════════════════════
   PAGE VARS
═══════════════════════════════════════════ */
$filter_status = in_array($_GET['status']??'',['pending','reviewed','dismissed','actioned','escalated']) ? $_GET['status'] : 'pending';
$active_source = in_array($_GET['source']??'',['all','chat','settings']) ? $_GET['source'] : 'all';
$search        = trim($_GET['search']??'');

/* Build WHERE */
$where   = "WHERE r.status = ?";
$params  = [$filter_status];
$types   = 's';

if ($active_source === 'chat')     { $where .= " AND r.source = 'chat'";     }
if ($active_source === 'settings') { $where .= " AND r.source = 'settings'"; }

if ($search !== '') {
    $where  .= " AND (rep.user_name LIKE ? OR rpd.user_name LIKE ? OR r.reason LIKE ? OR rep.email_id LIKE ?)";
    $like    = '%'.$search.'%';
    $params  = array_merge($params,[$like,$like,$like,$like]);
    $types  .= 'ssss';
}

/* Main query — LEFT JOIN reported user (settings reports have no reported_id) */
$sql = "
    SELECT
        r.report_id, r.reason, r.extra_info, r.ip_address,
        r.report_type, r.source, r.status, r.admin_note,
        r.created_at, r.reviewed_at,
        rep.user_id   AS reporter_id,
        rep.user_name AS reporter_name,
        rep.email_id  AS reporter_email,
        rep.profile_image AS reporter_img,
        rpd.user_id   AS reported_id,
        rpd.user_name AS reported_name,
        rpd.email_id  AS reported_email,
        rpd.profile_image AS reported_img,
        adm.user_name AS admin_name
    FROM user_reports r
    JOIN  users rep ON rep.user_id = r.reporter_id
    LEFT JOIN users rpd ON rpd.user_id  = r.reported_id
    LEFT JOIN users adm ON adm.user_id  = r.reviewed_by
    $where
    ORDER BY r.created_at DESC
    LIMIT 300
";
$st = $con->prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$reports = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* Status counts (all sources) */
$counts = ['pending'=>0,'reviewed'=>0,'dismissed'=>0,'actioned'=>0,'escalated'=>0];
$cq = $con->query("SELECT status,COUNT(*) AS c FROM user_reports GROUP BY status");
while ($row=$cq->fetch_assoc()) { if(isset($counts[$row['status']])) $counts[$row['status']]=(int)$row['c']; }

/* Source counts */
$src_counts = ['all'=>0,'chat'=>0,'settings'=>0];
$sq = $con->query("SELECT source,COUNT(*) AS c FROM user_reports WHERE status='$filter_status' GROUP BY source");
while ($row=$sq->fetch_assoc()) { $src_counts[$row['source']]=(int)$row['c']; }
$src_counts['all'] = array_sum([$src_counts['chat'],$src_counts['settings']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Report Management — Connectify Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#0b0c0f;--bg2:#111318;--bg3:#181b22;--bg4:#1e2128;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.13);
  --txt:#e8eaf0;--sub:rgba(232,234,240,.42);
  --acc:#7b6ef6;--acc2:#4fc3a1;
  --green:#2de07e;--yellow:#f5c843;--blue:#5b8fff;--red:#ff3b5c;--purple:#9b5de5;
  --font:'Syne',sans-serif;--mono:'JetBrains Mono',monospace;
  --r:14px;
}
body{background:var(--bg);color:var(--txt);font-family:var(--font);min-height:100vh;line-height:1.5;}

/* ── Layout ── */
.wrap{max-width:1480px;margin:0 auto;padding:0 24px 80px;}

/* ── Header ── */
.hdr{display:flex;align-items:center;gap:14px;padding:26px 0 22px;border-bottom:1px solid var(--border2);margin-bottom:28px;}
.hdr-icon{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,var(--acc),var(--acc2));display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.hdr h1{font-size:1.45rem;font-weight:800;letter-spacing:-.4px;}
.hdr small{font-size:.72rem;color:var(--sub);font-family:var(--mono);display:block;margin-top:1px;}
.hdr-back{margin-left:auto;background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:7px 15px;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;transition:.18s;}
.hdr-back:hover{background:var(--border2);}

/* ── Status stats ── */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;}
.stat{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;cursor:pointer;transition:border-color .2s,transform .15s;text-decoration:none;display:block;}
.stat:hover{transform:translateY(-2px);border-color:var(--border2);}
.stat.active{border-color:var(--acc);}
.stat-n{font-size:1.65rem;font-weight:800;font-family:var(--mono);line-height:1;}
.stat-lbl{font-size:.7rem;color:var(--sub);margin-top:3px;text-transform:uppercase;letter-spacing:.8px;}
.stat[data-s=pending]   .stat-n{color:var(--yellow);}
.stat[data-s=reviewed]  .stat-n{color:var(--blue);}
.stat[data-s=dismissed] .stat-n{color:var(--sub);}
.stat[data-s=actioned]  .stat-n{color:var(--green);}
.stat[data-s=escalated] .stat-n{color:var(--red);}

/* ── Source tabs ── */
.src-tabs{display:flex;gap:8px;margin-bottom:20px;align-items:center;}
.src-tab{background:var(--bg2);border:1px solid var(--border);border-radius:9px;padding:7px 16px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;color:var(--sub);transition:.18s;display:inline-flex;align-items:center;gap:7px;}
.src-tab:hover{border-color:var(--border2);color:var(--txt);}
.src-tab.active{border-color:var(--acc);color:var(--txt);background:rgba(123,110,246,.1);}
.src-badge{background:var(--bg3);border-radius:6px;padding:1px 7px;font-size:.7rem;font-family:var(--mono);}
.src-tab.active .src-badge{background:rgba(123,110,246,.25);color:var(--acc);}
.src-chat-badge{background:rgba(79,195,161,.12);color:var(--acc2);border:1px solid rgba(79,195,161,.3);border-radius:6px;padding:1px 7px;font-size:.7rem;}
.src-set-badge{background:rgba(91,143,255,.1);color:var(--blue);border:1px solid rgba(91,143,255,.3);border-radius:6px;padding:1px 7px;font-size:.7rem;}

/* ── Toolbar ── */
.toolbar{display:flex;gap:9px;align-items:center;margin-bottom:18px;flex-wrap:wrap;}
.srch-wrap{flex:1;min-width:180px;position:relative;}
.srch-wrap input{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:9px;color:var(--txt);padding:8px 12px 8px 34px;font-family:var(--font);font-size:.84rem;outline:none;transition:border-color .2s;}
.srch-wrap input:focus{border-color:var(--acc);}
.srch-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--sub);pointer-events:none;}
.btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:8px 16px;border-radius:9px;font-family:var(--font);font-size:.81rem;font-weight:600;cursor:pointer;transition:.18s;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn:hover{background:var(--border2);}
.btn.acc{background:var(--acc);border-color:var(--acc);color:#fff;}
.btn.acc:hover{filter:brightness(1.1);}

/* ── Table ── */
.tbl-wrap{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
.tbl-head{display:grid;grid-template-columns:42px 90px 1.3fr 1.3fr 2.2fr 105px 1.9fr;padding:9px 16px;background:var(--bg3);border-bottom:1px solid var(--border2);font-size:.69rem;text-transform:uppercase;letter-spacing:.9px;color:var(--sub);font-family:var(--mono);}
.rrow{display:grid;grid-template-columns:42px 90px 1.3fr 1.3fr 2.2fr 105px 1.9fr;align-items:center;padding:13px 16px;border-bottom:1px solid var(--border);transition:background .14s;gap:4px;}
.rrow:last-child{border-bottom:none;}
.rrow:hover{background:rgba(255,255,255,.02);}
.rrow-id{font-family:var(--mono);font-size:.7rem;color:var(--sub);}

/* source pill */
.src-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:.68rem;font-weight:700;font-family:var(--mono);white-space:nowrap;}
.src-pill.chat{background:rgba(79,195,161,.1);color:var(--acc2);border:1px solid rgba(79,195,161,.25);}
.src-pill.settings{background:rgba(91,143,255,.1);color:var(--blue);border:1px solid rgba(91,143,255,.25);}

/* user cell */
.ucel{display:flex;align-items:center;gap:7px;min-width:0;}
.ucel img{width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1.5px solid var(--border2);}
.ucel-name{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ucel-id{font-size:.67rem;color:var(--sub);font-family:var(--mono);}
.ucel-email{font-size:.66rem;color:var(--blue);font-family:var(--mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
.ucel-na{font-size:.78rem;color:var(--sub);font-style:italic;}

/* reason */
.reason-cel{min-width:0;}
.reason-text{font-size:.81rem;color:var(--txt);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;cursor:pointer;transition:color .15s;}
.reason-text:hover{color:#fff;}
.reason-meta{font-size:.67rem;color:var(--sub);margin-top:3px;font-family:var(--mono);}
.reason-extra{font-size:.68rem;color:var(--sub);margin-top:2px;font-style:italic;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}
.type-tag{display:inline-block;font-size:.63rem;font-family:var(--mono);padding:2px 6px;border-radius:4px;margin-right:5px;background:rgba(255,255,255,.06);color:var(--sub);}

/* badge */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:7px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;font-family:var(--mono);white-space:nowrap;}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0;}
.badge.pending  {background:rgba(245,200,67,.12);color:var(--yellow);border:1px solid rgba(245,200,67,.3);}
.badge.reviewed {background:rgba(91,143,255,.12);color:var(--blue);border:1px solid rgba(91,143,255,.3);}
.badge.dismissed{background:rgba(255,255,255,.05);color:var(--sub);border:1px solid var(--border);}
.badge.actioned {background:rgba(45,224,126,.1);color:var(--green);border:1px solid rgba(45,224,126,.3);}
.badge.escalated{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.3);}

/* action buttons */
.acts{display:flex;gap:4px;flex-wrap:wrap;}
.act-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:4px 9px;border-radius:7px;font-family:var(--font);font-size:.71rem;font-weight:600;cursor:pointer;transition:background .14s,transform .1s;white-space:nowrap;}
.act-btn:hover{transform:scale(1.04);}
.act-btn.rev{border-color:rgba(91,143,255,.4);color:var(--blue);}  .act-btn.rev:hover{background:rgba(91,143,255,.14);}
.act-btn.dis{border-color:rgba(255,255,255,.14);color:var(--sub);} .act-btn.dis:hover{background:rgba(255,255,255,.06);}
.act-btn.act{border-color:rgba(45,224,126,.4);color:var(--green);} .act-btn.act:hover{background:rgba(45,224,126,.1);}
.act-btn.esc{border-color:rgba(255,59,92,.4);color:var(--red);}    .act-btn.esc:hover{background:rgba(255,59,92,.1);}
.act-btn.chat{border-color:rgba(155,93,229,.4);color:var(--purple);}  .act-btn.chat:hover{background:rgba(155,93,229,.14);}
.act-btn.email{border-color:rgba(91,143,255,.4);color:var(--blue);}   .act-btn.email:hover{background:rgba(91,143,255,.14);}

.empty{padding:55px 20px;text-align:center;color:var(--sub);}
.empty-icon{font-size:2.8rem;display:block;margin-bottom:10px;}
.empty h3{font-size:1rem;color:var(--txt);margin-bottom:5px;}

/* ── Overlays ── */
.overlay{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.78);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;}
.overlay.on{opacity:1;pointer-events:all;}

/* Detail modal */
.modal{background:var(--bg2);border:1px solid var(--border2);border-radius:18px;width:100%;max-width:520px;padding:26px;box-shadow:0 28px 70px rgba(0,0,0,.7);transform:translateY(16px) scale(.97);transition:transform .28s cubic-bezier(.34,1.56,.64,1);position:relative;}
.overlay.on .modal{transform:none;}
.modal h2{font-size:1.1rem;font-weight:800;margin-bottom:5px;}
.modal-meta{font-size:.76rem;color:var(--sub);margin-bottom:16px;font-family:var(--mono);}
.modal-reason{background:var(--bg3);border:1px solid var(--border2);border-radius:11px;padding:13px;font-size:.83rem;color:var(--txt);line-height:1.6;margin-bottom:16px;max-height:160px;overflow-y:auto;}
.modal label{font-size:.76rem;color:var(--sub);display:block;margin-bottom:4px;font-family:var(--mono);}
.modal textarea,.modal input[type=text],.modal input[type=email],.modal select{width:100%;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;color:var(--txt);padding:9px 12px;font-family:var(--font);font-size:.83rem;outline:none;margin-bottom:12px;transition:border-color .2s;}
.modal textarea{resize:vertical;min-height:75px;}
.modal textarea:focus,.modal input:focus,.modal select:focus{border-color:var(--acc);}
.modal select option{background:var(--bg3);}
.modal-btns{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end;margin-top:2px;}
.modal-close{position:absolute;top:14px;right:14px;background:var(--bg3);border:1px solid var(--border2);color:var(--sub);width:28px;height:28px;border-radius:7px;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s;}
.modal-close:hover{background:var(--border2);color:var(--txt);}
.modal-section{border-top:1px solid var(--border2);padding-top:12px;margin-top:2px;}
.modal-section-title{font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:var(--sub);font-family:var(--mono);margin-bottom:8px;}

/* Chat modal */
.chat-modal{background:var(--bg2);border:1px solid var(--border2);border-radius:18px;width:100%;max-width:660px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 28px 70px rgba(0,0,0,.7);transform:translateY(16px) scale(.97);transition:transform .28s cubic-bezier(.34,1.56,.64,1);overflow:hidden;}
.overlay.on .chat-modal{transform:none;}
.chat-modal-hdr{padding:18px 20px 14px;border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.chat-modal-hdr h2{font-size:.95rem;font-weight:800;flex:1;}
.chat-count{font-size:.73rem;color:var(--sub);font-family:var(--mono);}
.chat-modal-body{flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:9px;}
.chat-modal-body::-webkit-scrollbar{width:3px;}
.chat-modal-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px;}
.enc-note{background:rgba(155,93,229,.1);border:1px solid rgba(155,93,229,.22);border-radius:9px;padding:9px 13px;font-size:.76rem;color:var(--purple);margin:10px 18px 0;flex-shrink:0;}
.adm-msg{display:flex;flex-direction:column;max-width:72%;}
.adm-msg.left{align-self:flex-start;align-items:flex-start;}
.adm-msg.right{align-self:flex-end;align-items:flex-end;}
.adm-msg-who{font-size:.68rem;color:var(--sub);margin-bottom:2px;font-family:var(--mono);display:flex;align-items:center;gap:5px;}
.adm-msg-who img{width:16px;height:16px;border-radius:50%;object-fit:cover;}
.adm-bub{padding:8px 12px;border-radius:14px;font-size:.83rem;line-height:1.45;word-break:break-word;}
.adm-msg.left  .adm-bub{background:rgba(255,255,255,.08);border-bottom-left-radius:3px;}
.adm-msg.right .adm-bub{background:linear-gradient(135deg,#7b6ef6,#4fc3a1);color:#fff;border-bottom-right-radius:3px;}
.adm-msg-time{font-size:.65rem;color:var(--sub);margin-top:2px;font-family:var(--mono);}
.adm-loading,.adm-empty{text-align:center;padding:36px;color:var(--sub);font-size:.83rem;}
.adm-empty-icon{font-size:2.2rem;display:block;margin-bottom:7px;}
.chat-modal-footer{padding:12px 18px;border-top:1px solid var(--border2);display:flex;gap:7px;flex-shrink:0;}

/* Email modal */
.email-modal{background:var(--bg2);border:1px solid var(--border2);border-radius:18px;width:100%;max-width:500px;padding:26px;box-shadow:0 28px 70px rgba(0,0,0,.7);transform:translateY(16px) scale(.97);transition:transform .28s cubic-bezier(.34,1.56,.64,1);position:relative;}
.overlay.on .email-modal{transform:none;}
.email-modal h2{font-size:1.05rem;font-weight:800;margin-bottom:14px;}
.tmpl-row{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:12px;}
.tmpl-btn{background:var(--bg3);border:1px solid var(--border2);color:var(--sub);padding:4px 11px;border-radius:7px;font-size:.73rem;cursor:pointer;transition:.15s;font-family:var(--font);}
.tmpl-btn:hover{border-color:var(--blue);color:var(--blue);background:rgba(91,143,255,.08);}

/* Toast */
#toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(10px);background:var(--bg3);border:1px solid var(--border2);color:var(--txt);padding:9px 20px;border-radius:11px;font-size:.81rem;font-weight:600;z-index:99999;opacity:0;pointer-events:none;transition:opacity .28s,transform .28s;white-space:nowrap;}
#toast.on{opacity:1;transform:translateX(-50%) translateY(0);}

@media(max-width:1100px){
  .stats{grid-template-columns:repeat(3,1fr);}
  .tbl-head,.rrow{grid-template-columns:42px 90px 1fr 1fr;}
  .tbl-head>*:nth-child(n+5),.rrow>*:nth-child(n+5){display:none;}
}
@media(max-width:620px){
  .tbl-head{display:none;}
  .rrow{grid-template-columns:1fr;padding:12px;}
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
    <small>Unified dashboard — chat reports &amp; user reports · Connectify Admin</small>
  </div>
  <a href="index.php" class="hdr-back">← Back to site</a>
</div>

<!-- STATUS STATS -->
<div class="stats">
  <?php $statLabels=['pending'=>'⏳ Pending','reviewed'=>'🔍 Reviewed','dismissed'=>'✗ Dismissed','actioned'=>'✅ Actioned','escalated'=>'🔺 Escalated'];
  foreach ($statLabels as $s=>$lbl): ?>
    <a href="?status=<?=$s?>&source=<?=$active_source?>&search=<?=urlencode($search)?>"
       class="stat <?=$filter_status===$s?'active':''?>" data-s="<?=$s?>">
      <div class="stat-n"><?=$counts[$s]?></div>
      <div class="stat-lbl"><?=$lbl?></div>
    </a>
  <?php endforeach; ?>
</div>

<!-- SOURCE TABS -->
<div class="src-tabs">
  <span style="font-size:.78rem;color:var(--sub);margin-right:4px;">Source:</span>
  <?php foreach(['all'=>'📋 All Reports','chat'=>'💬 Chat Reports','settings'=>'⚙️ Bug Reports'] as $src=>$lbl): ?>
    <a href="?status=<?=$filter_status?>&source=<?=$src?>&search=<?=urlencode($search)?>"
       class="src-tab <?=$active_source===$src?'active':''?>">
      <?=$lbl?>
      <span class="src-badge"><?=$src_counts[$src]?></span>
    </a>
  <?php endforeach; ?>
  <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
    <span class="src-chat-badge">💬 Chat = user-reported other users</span>
    <span class="src-set-badge">⚙️ Bug = reported via Settings</span>
  </div>
</div>

<!-- TOOLBAR -->
<div class="toolbar">
  <form method="get" style="display:contents">
    <input type="hidden" name="status" value="<?=htmlspecialchars($filter_status)?>">
    <input type="hidden" name="source" value="<?=htmlspecialchars($active_source)?>">
    <div class="srch-wrap">
      <span class="srch-icon">🔎</span>
      <input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search by name, reason, email…">
    </div>
    <button type="submit" class="btn acc">Search</button>
    <?php if($search): ?><a href="?status=<?=$filter_status?>&source=<?=$active_source?>" class="btn">Clear</a><?php endif; ?>
  </form>
</div>

<!-- TABLE -->
<div class="tbl-wrap">
  <div class="tbl-head">
    <div>#</div><div>Source</div><div>Reporter</div><div>Reported</div>
    <div>Reason / Details</div><div>Status</div><div>Actions</div>
  </div>

  <?php if(empty($reports)): ?>
    <div class="empty">
      <span class="empty-icon">🎉</span>
      <h3>No <?=$filter_status?> reports<?=$active_source!=='all'?' ('.$active_source.')':''?></h3>
      <p>Nothing here right now.</p>
    </div>
  <?php else: foreach($reports as $r):
    $repImg = htmlspecialchars(!empty($r['reporter_img'])?$r['reporter_img']:'default_profile.png');
    $rpdImg = !empty($r['reported_img']) ? htmlspecialchars($r['reported_img']) : '';
    $rData  = htmlspecialchars(json_encode($r),ENT_QUOTES);
    $isChatReport    = ($r['source']==='chat');
    $isSettingsReport= ($r['source']==='settings');
  ?>
  <div class="rrow" data-id="<?=$r['report_id']?>">

    <div class="rrow-id">#<?=$r['report_id']?></div>

    <!-- Source pill -->
    <div>
      <span class="src-pill <?=$r['source']?>">
        <?=$isChatReport?'💬 Chat':'⚙️ Bug'?>
      </span>
      <?php if($r['report_type']): ?>
        <div style="margin-top:4px;" class="type-tag"><?=htmlspecialchars($r['report_type'])?></div>
      <?php endif; ?>
    </div>

    <!-- Reporter -->
    <div class="ucel">
      <img src="<?=$repImg?>" alt="">
      <div>
        <div class="ucel-name"><?=htmlspecialchars($r['reporter_name'])?></div>
        <div class="ucel-id">ID <?=$r['reporter_id']?></div>
        <div class="ucel-email" title="<?=htmlspecialchars($r['reporter_email']??'')?>"><?=htmlspecialchars($r['reporter_email']??'—')?></div>
      </div>
    </div>

    <!-- Reported user (null for settings/bug reports) -->
    <div>
      <?php if(!empty($r['reported_id'])): ?>
        <div class="ucel">
          <?php if($rpdImg): ?><img src="<?=$rpdImg?>" alt=""><?php endif; ?>
          <div>
            <div class="ucel-name"><?=htmlspecialchars($r['reported_name']??'')?></div>
            <div class="ucel-id">ID <?=$r['reported_id']?></div>
            <div class="ucel-email"><?=htmlspecialchars($r['reported_email']??'—')?></div>
          </div>
        </div>
      <?php else: ?>
        <span class="ucel-na">— system report —</span>
      <?php endif; ?>
    </div>

    <!-- Reason + extra info -->
    <div class="reason-cel">
      <div class="reason-text" onclick='openDetailModal(<?=$rData?>)'>
        <?=htmlspecialchars($r['reason'])?>
      </div>
      <?php if(!empty($r['extra_info'])): ?>
        <div class="reason-extra"><?=htmlspecialchars($r['extra_info'])?></div>
      <?php endif; ?>
      <div class="reason-meta">
        <?=date('M j, Y · H:i',strtotime($r['created_at']))?>
        <?php if($r['ip_address']): ?> · <?=htmlspecialchars($r['ip_address'])?><?php endif; ?>
        <?php if($r['admin_name']): ?> · reviewed by <?=htmlspecialchars($r['admin_name'])?><?php endif; ?>
      </div>
    </div>

    <!-- Status -->
    <div><span class="badge <?=$r['status']?>"><?=$r['status']?></span></div>

    <!-- Actions -->
    <div class="acts">
      <?php if($isChatReport && !empty($r['reporter_id']) && !empty($r['reported_id'])): ?>
        <button class="act-btn chat"
          onclick='openChat(<?=$r['reporter_id']?>,<?=$r['reported_id']?>,
            <?=json_encode($r['reporter_name'])?>,<?=json_encode($r['reported_name'])?>)'>
          💬 Chat
        </button>
      <?php endif; ?>
      <?php if(!empty($r['reporter_email'])||!empty($r['reported_email'])): ?>
        <button class="act-btn email"
          onclick='openEmail(
            <?=json_encode($r['reporter_email']??'')?>,
            <?=json_encode($r['reported_email']??'')?>,
            <?=json_encode($r['reporter_name'])?>,
            <?=json_encode($r['reported_name']??'')?>)'>
          📧 Email
        </button>
      <?php endif; ?>
      <button class="act-btn rev" onclick='openDetailModal(<?=$rData?>,"reviewed")'>Review</button>
      <button class="act-btn act" onclick='openDetailModal(<?=$rData?>,"actioned")'>Action</button>
      <button class="act-btn esc" onclick='openDetailModal(<?=$rData?>,"escalated")'>Escalate</button>
      <button class="act-btn dis" onclick='act(<?=$r['report_id']?>,"dismissed","")'>Dismiss</button>
    </div>

  </div>
  <?php endforeach; endif; ?>
</div>
</div><!-- /wrap -->

<!-- ════ DETAIL MODAL ════ -->
<div class="overlay" id="detailOverlay" onclick="closeOverlay('detailOverlay',event)">
  <div class="modal" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeOverlay('detailOverlay')">✕</button>
    <h2>Report #<span id="dModalId"></span></h2>
    <div class="modal-meta" id="dModalMeta"></div>
    <div class="modal-reason" id="dModalReason"></div>
    <div id="dModalExtra" style="display:none;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;padding:10px 12px;font-size:.78rem;color:var(--sub);margin-bottom:14px;font-style:italic;"></div>

    <div class="modal-section">
      <div class="modal-section-title">User Actions (on Reported User)</div>
      <div id="dUserActions" style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px;">
        <button class="act-btn esc" id="dSuspendBtn"   onclick="suspendUser()">🚫 Suspend</button>
        <button class="act-btn rev" id="dUnsuspendBtn" onclick="unsuspendUser()">✅ Unsuspend</button>
        <button class="act-btn dis" id="dWarnBtn"      onclick="warnUser()">⚠️ Warn</button>
        <button class="act-btn" style="border-color:rgba(255,59,92,.5);color:var(--red);" id="dDeleteBtn" onclick="deleteUser()">🗑️ Delete Account</button>
      </div>
    </div>

    <label>Admin note</label>
    <textarea id="dNote" placeholder="Visible to admins only…"></textarea>
    <label>Change status</label>
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

<!-- ════ CHAT MODAL ════ -->
<div class="overlay" id="chatOverlay" onclick="closeOverlay('chatOverlay',event)">
  <div class="chat-modal" onclick="event.stopPropagation()">
    <div class="chat-modal-hdr">
      <div><h2 id="chatTitle">Conversation</h2><span class="chat-count" id="chatCount"></span></div>
      <button class="modal-close" style="position:static;margin-left:auto;" onclick="closeOverlay('chatOverlay')">✕</button>
    </div>
    <div id="chatEncNote" class="enc-note" style="display:none;">🔐 End-to-end encrypted — decrypted for this admin view.</div>
    <div class="chat-modal-body" id="chatBody"><div class="adm-loading">Loading…</div></div>
    <div class="chat-modal-footer">
      <button class="btn" onclick="closeOverlay('chatOverlay')" style="margin-left:auto;">Close</button>
    </div>
  </div>
</div>

<!-- ════ EMAIL MODAL ════ -->
<div class="overlay" id="emailOverlay" onclick="closeOverlay('emailOverlay',event)">
  <div class="email-modal" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeOverlay('emailOverlay')">✕</button>
    <h2>📧 Send Email</h2>
    <div class="modal-section-title" style="margin-bottom:7px;">Quick Templates</div>
    <div class="tmpl-row">
      <button class="tmpl-btn" onclick="applyTemplate('warning')">⚠️ Warning</button>
      <button class="tmpl-btn" onclick="applyTemplate('resolved')">✅ Resolved</button>
      <button class="tmpl-btn" onclick="applyTemplate('suspended')">🚫 Suspended</button>
      <button class="tmpl-btn" onclick="applyTemplate('info')">ℹ️ Need More Info</button>
      <button class="tmpl-btn" onclick="applyTemplate('cleared')">🎉 Cleared</button>
    </div>
    <label>To</label>
    <select id="emailTo">
      <option id="emailOptReporter" value="">Reporter — </option>
      <option id="emailOptReported" value="">Reported — </option>
    </select>
    <label>Subject</label>
    <input type="text" id="emailSubject" placeholder="Subject…">
    <label>Message</label>
    <textarea id="emailBody" style="min-height:130px;" placeholder="Write your message…"></textarea>
    <div class="modal-btns">
      <button class="btn" onclick="closeOverlay('emailOverlay')">Cancel</button>
      <button class="btn acc" onclick="sendEmail()">Send Email</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
let curReport = null;
let emailReporter = {email:'',name:''};
let emailReported = {email:'',name:''};

function closeOverlay(id,e){if(e&&e.target!==document.getElementById(id))return;document.getElementById(id).classList.remove('on');}
function openOverlay(id){document.getElementById(id).classList.add('on');}

/* ── Detail modal ── */
function openDetailModal(r, defaultAction){
  curReport=r;
  document.getElementById('dModalId').textContent=r.report_id;
  document.getElementById('dModalMeta').textContent=
    (r.source==='chat'?'💬 Chat report: ':'⚙️ Bug report: ')+
    r.reporter_name+(r.reported_name?' → '+r.reported_name:'')+
    '  ·  '+new Date(r.created_at).toLocaleString();
  document.getElementById('dModalReason').textContent=r.reason;

  const extraEl=document.getElementById('dModalExtra');
  if(r.extra_info){extraEl.style.display='block';extraEl.textContent='Steps / context: '+r.extra_info;}
  else{extraEl.style.display='none';}

  // Hide user action buttons for settings (bug) reports — no reported_id
  const ua=document.getElementById('dUserActions');
  ua.style.display=r.reported_id?'flex':'none';
  if(r.reported_id){
    ['dSuspendBtn','dUnsuspendBtn','dWarnBtn','dDeleteBtn'].forEach(id=>document.getElementById(id).dataset.uid=r.reported_id);
  }

  document.getElementById('dNote').value=r.admin_note||'';
  if(defaultAction) document.getElementById('dStatusSelect').value=defaultAction;
  openOverlay('detailOverlay');
}

function saveDetail(){
  if(!curReport)return;
  act(curReport.report_id,document.getElementById('dStatusSelect').value,document.getElementById('dNote').value,()=>closeOverlay('detailOverlay'));
}

/* ── Status action ── */
function act(reportId,action,note,cb){
  const row=document.querySelector(`.rrow[data-id="${reportId}"]`);
  const fd=new FormData();
  fd.append('ajax','1');fd.append('action',action);fd.append('report_id',reportId);fd.append('note',note||'');
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.status==='success'){
      toast_('✅ Marked as '+action);
      if(row){row.style.transition='opacity .35s,transform .35s';row.style.opacity='0';row.style.transform='translateX(10px)';setTimeout(()=>row.remove(),360);}
      const pill=document.querySelector('.stat.active .stat-n');
      if(pill) pill.textContent=Math.max(0,(parseInt(pill.textContent)||0)-1);
      if(cb)cb();
    } else toast_('❌ '+(d.message||d.msg||'Error'));
  }).catch(()=>toast_('❌ Network error'));
}

/* ── User actions ── */
function suspendUser(){const uid=document.getElementById('dSuspendBtn').dataset.uid;if(!uid||!confirm('Suspend this user?'))return;userAction('suspend_user',uid,()=>toast_('🚫 User suspended'));}
function unsuspendUser(){const uid=document.getElementById('dUnsuspendBtn').dataset.uid;if(!uid)return;userAction('unsuspend_user',uid,()=>toast_('✅ User unsuspended'));}
function warnUser(){const uid=document.getElementById('dWarnBtn').dataset.uid;const w=prompt('Warning message:');if(!w||!w.trim())return;const fd=new FormData();fd.append('ajax','1');fd.append('action','warn_user');fd.append('target_id',uid);fd.append('warning',w.trim());fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>toast_(d.status==='ok'?'⚠️ Warning recorded':'❌ Error'));}
function deleteUser(){const uid=document.getElementById('dDeleteBtn').dataset.uid;if(!uid||!confirm('PERMANENTLY delete this account?')||!confirm('Are you 100% sure?'))return;userAction('delete_user',uid,()=>{toast_('🗑️ Account deleted');closeOverlay('detailOverlay');});}
function userAction(action,uid,cb){const fd=new FormData();fd.append('ajax','1');fd.append('action',action);fd.append('target_id',uid);fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='ok'){if(cb)cb();}else toast_('❌ '+(d.msg||'Error'));}).catch(()=>toast_('❌ Network error'));}

/* ── Chat viewer ── */
function openChat(uidA,uidB,nameA,nameB){
  document.getElementById('chatTitle').textContent=nameA+' ↔ '+nameB;
  document.getElementById('chatCount').textContent='';
  document.getElementById('chatEncNote').style.display='none';
  document.getElementById('chatBody').innerHTML='<div class="adm-loading">🔓 Decrypting…</div>';
  openOverlay('chatOverlay');
  const fd=new FormData();fd.append('ajax','1');fd.append('action','load_chat');fd.append('user_a',uidA);fd.append('user_b',uidB);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.status!=='ok'){document.getElementById('chatBody').innerHTML='<div class="adm-empty"><span class="adm-empty-icon">❌</span>'+esc(d.msg||'Error')+'</div>';return;}
    document.getElementById('chatCount').textContent=d.count+' messages';
    if(d.encrypted) document.getElementById('chatEncNote').style.display='block';
    if(!d.messages.length){document.getElementById('chatBody').innerHTML='<div class="adm-empty"><span class="adm-empty-icon">💬</span>No messages found.</div>';return;}
    let html='';
    d.messages.forEach(m=>{
      const side=m.mine?'right':'left';
      const media=m.media?`<div style="margin-top:5px;"><a href="${esc(m.media)}" target="_blank" style="color:var(--blue);font-size:.75rem;">📎 ${esc(m.media.split('/').pop())}</a></div>`:'';
      html+=`<div class="adm-msg ${side}"><div class="adm-msg-who"><img src="${esc(m.avatar)}" alt="">${esc(m.sender)}</div><div class="adm-bub">${esc(m.text)}${media}</div><div class="adm-msg-time">${esc(m.time)} · ${esc(m.status)}</div></div>`;
    });
    document.getElementById('chatBody').innerHTML=html;
    const body=document.getElementById('chatBody');body.scrollTop=body.scrollHeight;
  }).catch(()=>{document.getElementById('chatBody').innerHTML='<div class="adm-empty"><span class="adm-empty-icon">❌</span>Network error.</div>';});
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

/* ── Email modal ── */
function openEmail(rEmail,rpdEmail,rName,rpdName){
  emailReporter={email:rEmail,name:rName};
  emailReported={email:rpdEmail,name:rpdName};
  const or=document.getElementById('emailOptReporter'),orp=document.getElementById('emailOptReported');
  or.value=rEmail; or.textContent='Reporter — '+rName+(rEmail?' <'+rEmail+'>':'');
  orp.value=rpdEmail; orp.textContent=rpdName?(rpdName+(rpdEmail?' <'+rpdEmail+'>':'')):'(no reported user)';
  document.getElementById('emailTo').selectedIndex=0;
  document.getElementById('emailSubject').value='';
  document.getElementById('emailBody').value='';
  openOverlay('emailOverlay');
}
const TEMPLATES={
  warning:{subject:'Important Notice — Connectify',body:'Hi {name},\n\nWe have received a report regarding your Connectify account. This is a formal warning.\n\nPlease ensure your activity complies with our Community Guidelines. Repeated violations may lead to suspension.\n\nIf you believe this was issued in error, reply to appeal.\n\nConnectify Safety Team'},
  resolved:{subject:'Your Report Has Been Resolved — Connectify',body:'Hi {name},\n\nThank you for your report. We have reviewed it and taken appropriate action.\n\nConnectify Safety Team'},
  suspended:{subject:'Account Suspended — Connectify',body:'Hi {name},\n\nYour account has been suspended due to a Community Guidelines violation. Reply within 14 days to appeal.\n\nConnectify Safety Team'},
  info:{subject:'More Information Needed — Connectify',body:'Hi {name},\n\nWe are reviewing your report and need additional details. Please reply with any context, screenshots, or steps to help our investigation.\n\nConnectify Safety Team'},
  cleared:{subject:'Report Reviewed — No Action Required — Connectify',body:'Hi {name},\n\nWe completed our review. No Community Guidelines violation was found. Your account remains in good standing.\n\nConnectify Safety Team'}
};
function applyTemplate(key){
  const t=TEMPLATES[key];
  const sel=document.getElementById('emailTo');
  const name=sel.value===emailReporter.email?emailReporter.name:emailReported.name;
  document.getElementById('emailSubject').value=t.subject;
  document.getElementById('emailBody').value=t.body.replace('{name}',name);
}
function sendEmail(){
  const to=document.getElementById('emailTo').value,subject=document.getElementById('emailSubject').value.trim(),body=document.getElementById('emailBody').value.trim();
  if(!to||!subject||!body){toast_('⚠️ Fill in all fields');return;}
  const fd=new FormData();fd.append('ajax','1');fd.append('action','send_email');fd.append('to_email',to);fd.append('subject',subject);fd.append('body',body);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.status==='sent'){toast_('📧 Sent to '+to);closeOverlay('emailOverlay');}
    else toast_('❌ '+(d.msg||'Email failed'));
  }).catch(()=>toast_('❌ Network error'));
}

/* ── Toast ── */
let tt;
function toast_(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('on');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('on'),2800);}
</script>
</body>
</html>