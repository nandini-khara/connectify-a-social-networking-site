<?php
/**
 * load_messages.php — fixed post card layout + all previous features
 */
session_start();
require 'connect.php';
require_once 'chat_crypto.php';

if (!isset($_SESSION['user_id'], $_POST['user_id'])) exit;

/**
 * linkifyText()
 * Safely escapes HTML then wraps any http/https URLs in clickable <a> tags.
 * Links open in a new tab. Works for profile links, post links, anything.
 */
function linkifyText(string $text): string {
    // 1. Escape all HTML first so we don't break anything
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2. Replace URLs with anchor tags
    //    Pattern matches http:// and https:// URLs including query strings and fragments
    $pattern = '/(https?:\/\/[^\s<>"\']+)/i';
    $replace = '<a href="$1" target="_blank" rel="noopener noreferrer" '
             . 'style="color:inherit;text-decoration:underline;word-break:break-all;">'
             . '$1</a>';

    return preg_replace($pattern, $replace, $safe);
}

$user_id  = (int)$_SESSION['user_id'];
$other_id = (int)$_POST['user_id'];

$seen = $con->prepare("UPDATE messages SET status='seen',seen_at=NOW() WHERE receiver_id=? AND sender_id=? AND status IN ('sent','delivered')");
$seen->bind_param("ii",$user_id,$other_id);
$seen->execute();

$aesKey = getOrCreateConversationKey($con,$user_id,$other_id);

// Clear-chat cutoff
$clrStmt = $con->prepare("SELECT cleared_at FROM chat_clears WHERE clearer_id=? AND other_id=?");
$clrStmt->bind_param('ii',$user_id,$other_id);
$clrStmt->execute();
$clrRow    = $clrStmt->get_result()->fetch_assoc();
$clrStmt->close();
$clearedAt = $clrRow ? $clrRow['cleared_at'] : null;
$cutoff    = $clearedAt ? "AND m.created_at > '" . $con->real_escape_string($clearedAt) . "'" : '';

$stmt = $con->prepare("
    SELECT m.message_id, m.sender_id,
           m.message_text, m.message_enc,
           m.media_path, m.message_type,
           m.status, m.created_at,
           m.shared_post_id,
           m.reply_to_id,
           m.is_edited, m.edited_at,
           m.deleted_for_me, m.deleted_for_me_by, m.deleted_for_me_at,
           m.deleted_everyone, m.deleted_everyone_at,
           p.post_text, p.post_img, p.post_video,
           u.user_name     AS post_author,
           u.user_id       AS post_owner_id,
           u.profile_image AS post_owner_img
    FROM messages m
    LEFT JOIN post  p ON p.id      = m.shared_post_id
    LEFT JOIN users u ON u.user_id = p.user_id
    WHERE (
        (m.sender_id=? AND m.receiver_id=?)
        OR (m.sender_id=? AND m.receiver_id=?)
    )
    $cutoff
    AND NOT EXISTS (
        SELECT 1 FROM message_deletions md
        WHERE md.message_id=m.message_id AND md.user_id=?
    )
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii",$user_id,$other_id,$other_id,$user_id,$user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Reactions
$msgIds = array_column($rows,'message_id');
$reactMap = []; $myReactMap = [];
if ($msgIds) {
    $inList = implode(',',array_map('intval',$msgIds));
    $rr = $con->query("SELECT message_id,emoji,COUNT(*) AS cnt FROM message_reactions WHERE message_id IN ($inList) GROUP BY message_id,emoji");
    while ($r=$rr->fetch_assoc()) $reactMap[(int)$r['message_id']][] = $r;
    $mr = $con->query("SELECT message_id,emoji FROM message_reactions WHERE message_id IN ($inList) AND user_id=$user_id");
    while ($r=$mr->fetch_assoc()) $myReactMap[(int)$r['message_id']] = $r['emoji'];
}

// Reply previews
$replyIds = array_filter(array_column($rows,'reply_to_id'));
$replyMap = [];
if ($replyIds) {
    $inList2 = implode(',',array_map('intval',$replyIds));
    $rp = $con->query("SELECT m.message_id,m.message_text,m.message_enc,u.user_name FROM messages m JOIN users u ON u.user_id=m.sender_id WHERE m.message_id IN ($inList2)");
    while ($r=$rp->fetch_assoc()) {
        $prev = !empty($r['message_enc']) ? decryptMessage($r['message_enc'],$aesKey) : ($r['message_text']??'');
        $replyMap[(int)$r['message_id']] = ['text'=>mb_substr($prev,0,60),'name'=>$r['user_name']];
    }
}

$now = new DateTime();

foreach ($rows as $row):
    $mid    = (int)$row['message_id'];
    $isMe   = ((int)$row['sender_id']===$user_id);
    $side   = $isMe ? 'me' : 'them';
    $time   = date('h:i A',strtotime($row['created_at']));
    $sentAt = new DateTime($row['created_at']);
    $ageMin = ($now->getTimestamp()-$sentAt->getTimestamp())/60;

    $displayText = !empty($row['message_enc'])
        ? decryptMessage($row['message_enc'],$aesKey)
        : ($row['message_text']??'');

    $delForMe  = (bool)$row['deleted_for_me'] && (int)$row['deleted_for_me_by']===$user_id;
    $delForAll = (bool)$row['deleted_everyone'];

    $canUndoMe  = $delForMe  && $row['deleted_for_me_at']  && (($now->getTimestamp()-strtotime($row['deleted_for_me_at']))/60)<5;
    $canUndoAll = $delForAll && $row['deleted_everyone_at'] && (($now->getTimestamp()-strtotime($row['deleted_everyone_at']))/60)<5;
    $canDelAll  = $isMe && $ageMin<=10 && !$delForAll;
    $canEdit    = $isMe && $ageMin<=10 && !$delForAll && !$delForMe;

    $statusIcon='';
    if ($isMe) {
        if     ($row['status']==='seen')      $statusIcon='<span class="cp-tick seen">✔✔</span>';
        elseif ($row['status']==='delivered') $statusIcon='<span class="cp-tick">✔✔</span>';
        else                                  $statusIcon='<span class="cp-tick">✔</span>';
    }

    $da  = "data-message-id='$mid'";
    $da .= " data-mine='".($isMe?'1':'0')."'";
    $da .= " data-can-del-all='".($canDelAll?'1':'0')."'";
    $da .= " data-can-edit='".($canEdit?'1':'0')."'";

    echo "<div class='chat-message $side' $da>";

    if ($delForAll) {
        echo "<div class='message-bubble cp-del-bub'><span class='cp-del-icon'>🗑️</span>"
           . ($isMe?'You deleted this message for everyone':'This message was deleted')."</div>";
        if ($isMe&&$canUndoAll) echo "<button class='cp-undo-btn' data-action='undo_delete_everyone' data-id='$mid'>↩ Undo</button>";
        echo "<div class='meta'><span class='time'>$time</span></div></div>";
        continue;
    }

    if ($delForMe) {
        echo "<div class='message-bubble cp-del-bub'><span class='cp-del-icon'>🗑️</span>You deleted this message</div>";
        if ($canUndoMe) echo "<button class='cp-undo-btn' data-action='undo_delete_me' data-id='$mid'>↩ Undo</button>";
        echo "<div class='meta'><span class='time'>$time</span></div></div>";
        continue;
    }

    echo "<div class='message-bubble'>";

    // Reply snippet
    if (!empty($row['reply_to_id']) && isset($replyMap[(int)$row['reply_to_id']])) {
        $rep=$replyMap[(int)$row['reply_to_id']];
        echo "<div class='cp-reply-snip' data-jump='{$row['reply_to_id']}'>
                <span class='cp-reply-who'>".htmlspecialchars($rep['name'])."</span>
                <span class='cp-reply-txt'>".htmlspecialchars($rep['text'])."</span>
              </div>";
    }

    // ── SHARED POST CARD — fixed layout ──────────────────────────
    if (!empty($row['shared_post_id'])) {
        $pid      = (int)$row['shared_post_id'];
        $ownerId  = (int)$row['post_owner_id'];
        $ownerImg = htmlspecialchars($row['post_owner_img'] ?? 'default_profile.png');
        $pName    = htmlspecialchars($row['post_author'] ?? '');
        $pText    = htmlspecialchars(mb_substr($row['post_text'] ?? '', 0, 100));
        $dots     = strlen($row['post_text'] ?? '') > 100 ? '…' : '';

        echo "
        <div class='cp-postcard' data-post-id='$pid'>

          <!-- Clickable author row -->
          <div class='cp-post-author' data-owner-id='$ownerId'>
            <img class='cp-post-avatar' src='$ownerImg' alt=''>
            <span class='cp-post-uname'>@$pName</span>
          </div>";

        // Post image
        if (!empty($row['post_img'])) {
            $src = htmlspecialchars($row['post_img']);
            echo "<img class='cp-post-img' src='$src' alt='post image'>";
        }
        // Post video
        if (!empty($row['post_video'])) {
            $src = htmlspecialchars($row['post_video']);
            echo "<video class='cp-post-vid' src='$src' controls></video>";
        }
        // Caption
        if ($pText !== '') {
            echo "<p class='cp-post-caption'>$pText$dots</p>";
        }

        echo "
          <div class='cp-view-post' data-post-id='$pid'>👁 View original post</div>
        </div>";
    }

    // Media attachment
    if (!empty($row['media_path'])) {
        $fp  = htmlspecialchars($row['media_path']);
        $ft  = $row['message_type'] ?? '';
        $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
        if ($ft==='image' || in_array($ext,['jpg','jpeg','png','gif','webp']))
            echo "<img src='$fp' class='cp-media-img' data-src='$fp'>";
        elseif (in_array($ft,['audio','voice']))
            echo "<audio controls src='$fp'></audio>";
        elseif (in_array($ext,['mp4','webm','mov']))
            echo "<video controls src='$fp' class='cp-media-vid' data-src='$fp'></video>";
        else
            echo "<a href='$fp' target='_blank' class='cp-file-link'>📎 ".basename($fp)."</a>";
    }

    // Text — URLs are made clickable
    if ($displayText !== '')
        echo "<div class='text'>".nl2br(linkifyText($displayText))."</div>";

    // Edited label
    if ($row['is_edited'])
        echo "<span class='cp-edited-lbl'>edited ".date('h:i A',strtotime($row['edited_at']))."</span>";

    echo "<div class='meta'><span class='time'>$time</span>$statusIcon</div>";
    echo "</div>"; // .message-bubble

    // Reactions
    if (!empty($reactMap[$mid])) {
        echo "<div class='cp-reactions' data-mid='$mid'>";
        foreach ($reactMap[$mid] as $rc) {
            $mine = (isset($myReactMap[$mid])&&$myReactMap[$mid]===$rc['emoji'])?' my-react':'';
            echo "<span class='cp-react-pill$mine' data-emoji='{$rc['emoji']}' data-mid='$mid'>"
               . htmlspecialchars($rc['emoji'])
               . ($rc['cnt']>1?" {$rc['cnt']}":'')."</span>";
        }
        echo "</div>";
    }

    echo "</div>"; // .chat-message
endforeach;
?>
<style>
/* ── deleted ── */
.cp-del-bub{display:flex!important;align-items:center;gap:6px;font-style:italic;font-size:.78rem;color:rgba(237,233,246,.45)!important;background:rgba(255,255,255,.04)!important;border:1px dashed rgba(255,255,255,.12)!important;border-radius:14px;padding:7px 12px;}
.cp-del-icon{opacity:.6;}
.cp-undo-btn{background:rgba(155,93,229,.2);border:1px solid rgba(155,93,229,.5);color:#ede9f6;border-radius:8px;padding:3px 10px;font-size:.72rem;cursor:pointer;margin-top:3px;transition:background .15s;display:inline-block;}
.cp-undo-btn:hover{background:rgba(155,93,229,.4);}
/* ── ticks ── */
.cp-tick{font-size:.72rem;color:rgba(237,233,246,.4);letter-spacing:-1px;margin-left:3px;}
.cp-tick.seen{color:#4fc3f7;}
/* ── reply snippet ── */
.cp-reply-snip{background:rgba(255,255,255,.1);border-left:3px solid #9b5de5;border-radius:6px;padding:4px 8px;margin-bottom:6px;cursor:pointer;font-size:.75rem;transition:background .15s;}
.cp-reply-snip:hover{background:rgba(255,255,255,.18);}
.cp-reply-who{font-weight:700;color:#9b5de5;display:block;margin-bottom:1px;}
.cp-reply-txt{color:rgba(237,233,246,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:200px;}
/* ── reactions ── */
.cp-reactions{display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;}
.cp-react-pill{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:2px 7px;font-size:.76rem;cursor:pointer;transition:background .14s,transform .12s;user-select:none;}
.cp-react-pill:hover{background:rgba(255,255,255,.22);transform:scale(1.1);}
.cp-react-pill.my-react{background:rgba(155,93,229,.35);border-color:#9b5de5;}
/* ── edited label ── */
.cp-edited-lbl{font-size:.68rem;color:rgba(237,233,246,.4);font-style:italic;display:block;margin-top:1px;}
/* ── media ── */
.cp-media-img{max-width:200px;border-radius:10px;margin-top:4px;display:block;cursor:pointer;}
.cp-media-vid{max-width:200px;border-radius:10px;margin-top:4px;display:block;}
.cp-file-link{display:inline-block;margin-top:4px;background:rgba(255,255,255,.12);padding:4px 10px;border-radius:8px;font-size:.76rem;color:inherit;text-decoration:none;}

/* ═══════════════════════════════════════════
   POST CARD — fixed layout
═══════════════════════════════════════════ */
.cp-postcard{
  background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.13);
  border-radius:12px;
  padding:10px;
  margin-top:6px;
  /* CRITICAL: prevent children from overflowing */
  overflow:hidden;
  width:100%;
  max-width:220px;       /* matches bubble max-width */
  box-sizing:border-box;
}
.cp-postcard:hover{background:rgba(255,255,255,.12);}

/* Author row */
.cp-post-author{
  display:flex;
  align-items:center;
  gap:6px;
  margin-bottom:7px;
  cursor:pointer;
  border-radius:6px;
  padding:2px 4px;
  transition:background .15s;
  /* prevent overflow */
  min-width:0;
  overflow:hidden;
}
.cp-post-author:hover{background:rgba(255,255,255,.1);}
.cp-post-avatar{
  width:22px;height:22px;
  border-radius:50%;
  object-fit:cover;
  border:1.5px solid rgba(155,93,229,.5);
  flex-shrink:0;
}
.cp-post-uname{
  font-size:.75rem;
  font-weight:700;
  color:#bb86fc;
  /* stop text from pushing card wider */
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  min-width:0;
}
.cp-post-author:hover .cp-post-uname{text-decoration:underline;}

/* Post image / video */
.cp-post-img,
.cp-post-vid{
  width:100%;
  border-radius:8px;
  display:block;
  margin-bottom:6px;
  object-fit:cover;
  max-height:160px;
}

/* Caption */
.cp-post-caption{
  font-size:.76rem;
  color:rgba(237,233,246,.8);
  margin:0 0 6px;
  /* wrap long text, never overflow */
  word-break:break-word;
  overflow-wrap:break-word;
}

/* View post link */
.cp-view-post{
  font-size:.72rem;
  color:#9b5de5;
  cursor:pointer;
  text-decoration:underline;
  display:inline-block;
}
.cp-view-post:hover{color:#bb86fc;}
</style>