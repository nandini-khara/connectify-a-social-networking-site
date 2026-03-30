<?php
/**
 * chat_panel.php  — FIXED VERSION
 * FIX 1: Online status shows ONLY the green dot on avatar. 
 *         The sub-text "Online" label is removed — dot is the sole indicator.
 *         Offline users show a grey dot.
 * FIX 2: viewPost() passes &chat_uid=X back URL so conversation auto-reopens.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();
require_once 'connect.php';
$cp_uid = (int)$_SESSION['user_id'];

$blk_stmt = $con->prepare("SELECT blocked_id FROM blocks WHERE blocker_id = ?");
$blk_stmt->bind_param('i', $cp_uid);
$blk_stmt->execute();
$cp_blocked_ids = [];
while ($br = $blk_stmt->get_result()->fetch_assoc()) {
    $cp_blocked_ids[] = (int)$br['blocked_id'];
}

$cp_sql = "
  SELECT u.user_id, u.user_name, u.profile_image,
         MAX(m.created_at) AS last_msg_time,
         SUM(CASE WHEN m.receiver_id=? AND m.sender_id=u.user_id AND (m.seen=0 OR m.seen IS NULL) THEN 1 ELSE 0 END) AS unread_count
  FROM users u
  JOIN messages m ON (m.sender_id=u.user_id AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=u.user_id)
  WHERE u.user_id != ?
  GROUP BY u.user_id,u.user_name,u.profile_image
  UNION
  SELECT u.user_id,u.user_name,u.profile_image,NULL,0
  FROM users u
  WHERE u.user_id != ?
    AND u.user_id IN (SELECT following_id FROM follows WHERE follower_id=?)
    AND u.user_id NOT IN (SELECT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END FROM messages WHERE sender_id=? OR receiver_id=?)
  ORDER BY last_msg_time DESC, user_name ASC LIMIT 50";
$cp_st = $con->prepare($cp_sql);
$cp_st->bind_param("iiiiiiiii",$cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid);
$cp_st->execute();
$cp_users = $cp_st->get_result();

$fwQ = $con->prepare("SELECT u.user_id,u.full_name,u.user_name,u.profile_image FROM follows f JOIN users u ON u.user_id=f.following_id WHERE f.follower_id=?");
$fwQ->bind_param('i',$cp_uid);
$fwQ->execute();
$cp_following = $fwQ->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap');
:root{
  --cp-bg:#1a1025;--cp-surf:rgba(255,255,255,.07);--cp-bdr:rgba(255,255,255,.11);
  --cp-acc:#9b5de5;--cp-acc2:#f15bb5;--cp-txt:#ede9f6;--cp-sub:rgba(237,233,246,.5);
  --cp-me:linear-gradient(135deg,#9b5de5,#f15bb5);--cp-them:rgba(255,255,255,.09);
  --cp-inp:rgba(255,255,255,.07);--cp-r:18px;--cp-sh:0 24px 70px rgba(0,0,0,.6);
}
.cpt-ocean{--cp-bg:#0a1628;--cp-acc:#00c4ff;--cp-me:linear-gradient(135deg,#00c4ff,#0077ff);}
.cpt-forest{--cp-bg:#0d1f15;--cp-acc:#39d353;--cp-me:linear-gradient(135deg,#39d353,#00bb77);}
.cpt-rose{--cp-bg:#1f0d16;--cp-acc:#ff6b9d;--cp-me:linear-gradient(135deg,#ff6b9d,#ff3371);}
.cpt-gold{--cp-bg:#1a1408;--cp-acc:#f5c518;--cp-me:linear-gradient(135deg,#f5c518,#e07b00);}
.cpt-ice{--cp-bg:#0d1926;--cp-acc:#a8edea;--cp-me:linear-gradient(135deg,#a8edea,#7fdbff);}

#cpPanel{position:fixed;bottom:24px;right:24px;width:400px;height:600px;max-width:calc(100vw - 32px);max-height:calc(100dvh - 32px);background:var(--cp-bg);border-radius:var(--cp-r);border:1px solid var(--cp-bdr);box-shadow:var(--cp-sh);display:flex;flex-direction:column;overflow:hidden;z-index:9999;font-family:'DM Sans',sans-serif;color:var(--cp-txt);backdrop-filter:blur(20px);transform:translateY(calc(100% + 30px)) scale(.93);opacity:0;pointer-events:none;transition:transform .38s cubic-bezier(.34,1.56,.64,1),opacity .25s ease;}
#cpPanel.cp-open{transform:translateY(0) scale(1);opacity:1;pointer-events:all;}
#cpPanel::after{content:'';position:absolute;inset:0;pointer-events:none;z-index:0;border-radius:var(--cp-r);background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");}
#cpPanel>*{position:relative;z-index:1;}
.cp-hdr{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--cp-bdr);flex-shrink:0;background:var(--cp-bg);position:sticky;top:0;z-index:10;}
.cp-title{font-weight:700;font-size:.95rem;flex:1;letter-spacing:-.3px;}
.cp-hbtn{background:var(--cp-surf);border:1px solid var(--cp-bdr);color:var(--cp-txt);width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;flex-shrink:0;transition:background .2s,transform .15s;}
.cp-hbtn:hover{background:var(--cp-acc);transform:scale(1.12);}
.cp-dots{display:flex;gap:5px;flex-shrink:0;}
.cp-dot{width:15px;height:15px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .2s;}
.cp-dot:hover,.cp-dot.on{border-color:var(--cp-txt);transform:scale(1.25);}
.cp-dot[data-t=default]{background:linear-gradient(135deg,#9b5de5,#f15bb5);}
.cp-dot[data-t=ocean]{background:linear-gradient(135deg,#00c4ff,#0077ff);}
.cp-dot[data-t=forest]{background:linear-gradient(135deg,#39d353,#00bb77);}
.cp-dot[data-t=rose]{background:linear-gradient(135deg,#ff6b9d,#ff3371);}
.cp-dot[data-t=gold]{background:linear-gradient(135deg,#f5c518,#e07b00);}
.cp-dot[data-t=ice]{background:linear-gradient(135deg,#a8edea,#7fdbff);}
.cp-au{display:none;align-items:center;gap:8px;flex:1;min-width:0;}
.cp-au img{width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--cp-acc);flex-shrink:0;}
.cp-au strong{font-size:.87rem;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
/* ── FIX: Header status shows only short label like "Active now" or "Last seen …" ── */
.cp-au small{font-size:.7rem;color:var(--cp-sub);}
#cpKebab,#cpCtx{position:fixed;background:#111 !important;border:1px solid rgba(255,255,255,.15);overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.95);z-index:999999;display:none;}
#cpKebab{min-width:175px;border-radius:14px;}
#cpCtx{min-width:185px;border-radius:12px;}
#cpKebab.on,#cpCtx.on{display:block;animation:cpPop .14s ease;}
.cp-km,.cp-ci{padding:11px 16px;font-size:.84rem;cursor:pointer;display:flex;align-items:center;gap:9px;transition:background .14s;color:#ede9f6;background:#111 !important;white-space:nowrap;}
.cp-km:hover,.cp-ci:hover{background:#222 !important;}
.cp-km.red,.cp-ci.red{color:#ff6b6b;}
.cp-km.green{color:#39d353;}
.cp-ctx-divider{height:1px;background:rgba(255,255,255,.15);margin:2px 0;}
.cp-srch{padding:10px 14px;flex-shrink:0;position:relative;background:var(--cp-bg);}
.cp-srch input{width:100%;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:12px;color:var(--cp-txt);padding:8px 14px 8px 34px;font-size:.84rem;outline:none;transition:border-color .2s;}
.cp-srch input:focus{border-color:var(--cp-acc);}
.cp-srch i{position:absolute;left:26px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--cp-sub);}
.cp-ulist{flex:1;overflow-y:auto;padding:4px 0;background:var(--cp-bg);}
.cp-ulist::-webkit-scrollbar{width:3px;}
.cp-ulist::-webkit-scrollbar-thumb{background:var(--cp-acc);border-radius:3px;}
.cp-urow{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-radius:12px;margin:2px 8px;transition:background .18s;position:relative;}
.cp-urow:hover{background:var(--cp-surf);}
.cp-urow .cp-avatar-wrap{position:relative;flex-shrink:0;}
.cp-urow img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--cp-acc);display:block;}

/* ═══════════════════════════════════════════════════
   FIX: ONE dot only — positioned bottom-right of avatar.
   NO separate "Online" text. The dot alone = online.
   Grey dot = offline / recently left.
   ═══════════════════════════════════════════════════ */
.cp-online-dot{
  position:absolute;
  bottom:1px;right:1px;
  width:11px;height:11px;
  border-radius:50%;
  background:#39d353;          /* green = online */
  border:2px solid var(--cp-bg);
  z-index:2;
  transition:background .4s ease;
}
.cp-online-dot.offline{
  background:#666;             /* grey = offline */
}
/* hide dot completely until we get status data */
.cp-online-dot.hidden{ display:none; }

.cp-urow.is-blocked img{border-color:#ff6b6b !important;opacity:.65;filter:grayscale(50%);}
.cp-urow.is-blocked .cp-uname{color:#ff8a8a !important;}
.cp-urow.is-blocked .cp-usub{color:rgba(255,138,138,.55) !important;}
.cp-urow.is-blocked .cp-uname::after{content:'Blocked';display:inline-block;margin-left:6px;font-size:9px;font-weight:700;background:rgba(255,107,107,.18);border:1px solid rgba(255,107,107,.45);color:#ff8a8a;border-radius:6px;padding:1px 5px;vertical-align:middle;}
.cp-unread-dot{position:absolute;top:-2px;right:-2px;min-width:18px;height:18px;padding:0 4px;background:linear-gradient(135deg,#f15bb5,#9b5de5);color:#fff;border-radius:9px;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--cp-bg);animation:cpPulse 2s ease-in-out infinite;}
@keyframes cpPulse{0%,100%{box-shadow:0 0 0 0 rgba(241,91,181,.5);}50%{box-shadow:0 0 0 5px rgba(241,91,181,0);}}
.cp-uname{font-size:.87rem;font-weight:600;}
/* ── FIX: sub-text shows "Tap to chat" or last-seen label, NEVER "Online" text ── */
.cp-usub{font-size:.73rem;color:var(--cp-sub);}
.cp-urow.has-unread .cp-uname{color:#fff;}
.cp-urow.has-unread .cp-usub{color:var(--cp-txt);}
.cp-win{flex:1;display:none;flex-direction:column;overflow:hidden;min-height:0;}
.cp-win.on{display:flex;}
.cp-msgs{flex:1;overflow-y:auto;padding:12px 12px 6px;display:flex;flex-direction:column;gap:7px;min-height:0;}
.cp-msgs::-webkit-scrollbar{width:3px;}
.cp-msgs::-webkit-scrollbar-thumb{background:var(--cp-acc);border-radius:3px;}
.cp-msg,.chat-message{display:flex;flex-direction:column;max-width:78%;position:relative;}
.cp-msg.me,.chat-message.me{align-self:flex-end;align-items:flex-end;}
.cp-msg.them,.chat-message.them{align-self:flex-start;align-items:flex-start;}
@keyframes cpIn{from{opacity:0;transform:translateY(7px);}}
.cp-msg,.chat-message{animation:cpIn .22s ease;}
.cp-bub,.message-bubble{padding:9px 13px;border-radius:18px;font-size:.87rem;line-height:1.45;word-break:break-word;}
.cp-msg.me .cp-bub,.chat-message.me .message-bubble{background:var(--cp-me);border-bottom-right-radius:4px;color:#fff;}
.cp-msg.them .cp-bub,.chat-message.them .message-bubble{background:var(--cp-them);border-bottom-left-radius:4px;}
.cp-bub img,.cp-bub video,.message-bubble img,.message-bubble video{max-width:200px;border-radius:10px;margin-top:4px;display:block;}
.cp-bub audio,.message-bubble audio{margin-top:4px;width:200px;}
.cp-meta,.meta{font-size:10px;color:var(--cp-sub);margin-top:3px;display:flex;gap:4px;align-items:center;}
.cp-deleted-notice{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:14px;font-size:.78rem;font-style:italic;color:var(--cp-sub);background:rgba(255,255,255,.04);border:1px dashed rgba(255,255,255,.12);}
.cp-block-banner{margin:8px 12px;background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.35);border-radius:14px;padding:14px 16px;display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;flex-shrink:0;}
.cp-block-banner .cp-bb-icon{font-size:28px;}
.cp-block-banner .cp-bb-title{font-size:.88rem;font-weight:600;color:#ff8a8a;}
.cp-block-banner .cp-bb-sub{font-size:.76rem;color:var(--cp-sub);}
.cp-block-banner .cp-unblock-btn{background:rgba(57,211,83,.15);border:1px solid rgba(57,211,83,.4);color:#39d353;border-radius:10px;padding:7px 18px;font-size:.82rem;font-weight:600;cursor:pointer;transition:background .2s,transform .15s;}
.cp-block-banner .cp-unblock-btn:hover{background:rgba(57,211,83,.3);transform:scale(1.04);}
.cp-strip{display:none;flex-wrap:wrap;gap:6px;padding:6px 12px;border-top:1px solid var(--cp-bdr);background:var(--cp-surf);flex-shrink:0;}
.cp-strip.on{display:flex;}
.cp-ath{position:relative;width:54px;height:54px;border-radius:8px;overflow:hidden;border:1px solid var(--cp-bdr);}
.cp-ath img,.cp-ath video{width:100%;height:100%;object-fit:cover;}
.cp-ath .cp-rm{position:absolute;top:2px;right:2px;background:rgba(0,0,0,.65);color:#fff;border:none;border-radius:50%;width:16px;height:16px;font-size:9px;cursor:pointer;line-height:16px;text-align:center;padding:0;}
.cp-afile{display:flex;align-items:center;gap:4px;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:8px;padding:4px 8px;font-size:.74rem;max-width:110px;overflow:hidden;white-space:nowrap;position:relative;}
#cpReplyBar{display:none;align-items:center;gap:8px;padding:6px 12px;background:rgba(155,93,229,.12);border-top:1px solid rgba(155,93,229,.3);flex-shrink:0;}
#cpReplyBar.on{display:flex;}
.cp-rb-preview{flex:1;font-size:.76rem;color:var(--cp-sub);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cp-rb-name{color:var(--cp-acc);font-weight:600;}
.cp-rb-close{background:none;border:none;color:var(--cp-sub);font-size:16px;cursor:pointer;line-height:1;padding:0;flex-shrink:0;}
#cpEditBar{display:none;align-items:center;gap:8px;padding:5px 12px;background:rgba(245,197,24,.1);border-top:1px solid rgba(245,197,24,.25);flex-shrink:0;}
#cpEditBar.on{display:flex;}
.cp-eb-label{font-size:.76rem;color:#f5c518;flex:1;}
.cp-eb-close{background:none;border:none;color:var(--cp-sub);font-size:16px;cursor:pointer;line-height:1;padding:0;flex-shrink:0;}
.cp-bar{display:flex;align-items:center;gap:6px;padding:8px 10px;border-top:1px solid var(--cp-bdr);flex-shrink:0;position:relative;background:var(--cp-bg);}
.cp-plus{width:34px;height:34px;border-radius:50%;background:var(--cp-me);border:none;color:#fff;font-size:20px;font-weight:300;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .2s,box-shadow .2s;}
.cp-plus:hover{transform:scale(1.1);box-shadow:0 4px 16px rgba(155,93,229,.5);}
.cp-inpwrap{flex:1;display:flex;align-items:center;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:13px;padding:6px 11px;transition:border-color .2s;min-width:0;}
.cp-inpwrap:focus-within{border-color:var(--cp-acc);}
.cp-inpwrap input{flex:1;background:none;border:none;outline:none;color:var(--cp-txt);font-size:.87rem;font-family:'DM Sans',sans-serif;min-width:0;}
.cp-inpwrap input::placeholder{color:var(--cp-sub);}
.cp-send{width:36px;height:36px;border-radius:50%;background:var(--cp-me);border:none;color:#fff;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .15s,box-shadow .15s;}
.cp-send:hover{transform:scale(1.08);box-shadow:0 4px 16px rgba(155,93,229,.5);}
.cp-mpop{position:absolute;bottom:58px;left:10px;background:var(--cp-bg);border:1px solid var(--cp-bdr);border-radius:16px;padding:12px;display:none;flex-direction:column;gap:8px;box-shadow:var(--cp-sh);z-index:500;width:280px;}
.cp-mpop.on{display:flex;animation:cpPop .15s ease;}
.cp-mprow{display:flex;gap:8px;flex-wrap:wrap;}
.cp-mpbtn{display:flex;flex-direction:column;align-items:center;gap:3px;background:var(--cp-surf);border:1px solid var(--cp-bdr);border-radius:12px;padding:8px;cursor:pointer;font-size:10px;color:var(--cp-sub);transition:background .15s,color .15s;min-width:52px;flex:1;}
.cp-mpbtn .ico{font-size:20px;line-height:1;}
.cp-mpbtn.gif-btn .ico{font-size:13px;font-weight:700;}
.cp-mpbtn:hover{background:var(--cp-acc);color:#fff;border-color:var(--cp-acc);}
.cp-subpick{display:none;}
.cp-subpick.on{display:block;}
.cp-etabs{display:flex;gap:4px;margin-bottom:6px;}
.cp-etab{font-size:17px;cursor:pointer;padding:3px 7px;border-radius:7px;transition:background .14s;}
.cp-etab.on,.cp-etab:hover{background:var(--cp-surf);}
.cp-egrid{display:flex;flex-wrap:wrap;gap:3px;max-height:150px;overflow-y:auto;}
.cp-egrid span{font-size:20px;cursor:pointer;padding:3px;border-radius:7px;}
.cp-egrid span:hover{background:var(--cp-surf);}
.cp-gif-srch{width:100%;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:10px;color:var(--cp-txt);padding:6px 10px;margin-bottom:6px;outline:none;font-size:.81rem;}
.cp-ggrid{display:grid;grid-template-columns:1fr 1fr;gap:4px;max-height:150px;overflow-y:auto;}
.cp-ggrid img{width:100%;border-radius:8px;cursor:pointer;transition:transform .14s;}
.cp-ggrid img:hover{transform:scale(1.04);}
#cpEmojiBar{position:fixed;background:#111;border:1px solid rgba(255,255,255,.15);border-radius:30px;padding:6px 10px;display:none;gap:6px;box-shadow:0 8px 24px rgba(0,0,0,.7);z-index:999999;}
#cpEmojiBar.on{display:flex;animation:cpPop .12s ease;}
#cpEmojiBar span{font-size:20px;cursor:pointer;border-radius:50%;padding:3px;transition:transform .12s;}
#cpEmojiBar span:hover{transform:scale(1.3);}
#cpFwdDrawer{position:fixed;top:0;right:-300px;width:280px;height:100%;background:#1a1025;border-left:1px solid rgba(255,255,255,.11);box-shadow:-4px 0 20px rgba(0,0,0,.5);padding:20px;transition:right .3s ease;z-index:999998;overflow-y:auto;font-family:'DM Sans',sans-serif;color:#ede9f6;}
#cpFwdDrawer.on{right:0;}
#cpFwdDrawer h5{font-weight:700;margin-bottom:12px;font-size:.9rem;}
#cpFwdSearch{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.11);border-radius:10px;color:#ede9f6;padding:7px 12px;margin-bottom:10px;outline:none;font-size:.82rem;}
.cp-fwd-user{display:flex;align-items:center;gap:10px;padding:7px 4px;border-bottom:1px solid rgba(255,255,255,.07);}
.cp-fwd-user img{width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;}
.cp-fwd-user span{flex:1;font-size:.84rem;}
.cp-fwd-send{background:linear-gradient(135deg,#9b5de5,#f15bb5);border:none;color:#fff;border-radius:8px;padding:5px 12px;font-size:.78rem;cursor:pointer;transition:opacity .15s;}
.cp-fwd-send:hover{opacity:.85;}
#cpToast{position:fixed;bottom:88px;right:28px;background:#222;color:#fff;padding:10px 18px;border-radius:12px;font-size:.83rem;font-family:'DM Sans',sans-serif;z-index:99999;opacity:0;pointer-events:none;transition:opacity .3s;}
@keyframes cpPop{from{opacity:0;transform:scale(.9);}}
@media(max-width:480px){#cpPanel{width:100vw;height:100dvh;bottom:0;right:0;border-radius:0;}}
.chat-panel{display:none !important;}
#cpFI,#cpII,#cpVI,#cpAI{display:none;}
</style>

<div id="cpPanel">
  <div class="cp-hdr">
    <button class="cp-hbtn" id="cpBack" style="display:none">←</button>
    <span class="cp-title" id="cpTitle">Messages</span>
    <div class="cp-au" id="cpAU">
      <img id="cpAUImg" src="" alt="">
      <div style="min-width:0">
        <strong id="cpAUName"></strong>
        <!-- Shows "Active now" or "Last seen X ago" — NOT "Online" duplicated -->
        <small id="cpAUStatus" style="color:var(--cp-sub);font-size:.7rem;display:block;"></small>
      </div>
    </div>
    <div class="cp-dots">
      <div class="cp-dot on" data-t="default"></div>
      <div class="cp-dot" data-t="ocean"></div>
      <div class="cp-dot" data-t="forest"></div>
      <div class="cp-dot" data-t="rose"></div>
      <div class="cp-dot" data-t="gold"></div>
      <div class="cp-dot" data-t="ice"></div>
    </div>
    <button class="cp-hbtn" id="cpKebabBtn" style="display:none">⋮</button>
    <button class="cp-hbtn" id="cpClose">✕</button>
  </div>
  <div class="cp-srch" id="cpSrch">
    <i class="fas fa-search"></i>
    <input id="cpSrchInp" placeholder="Search conversations…">
  </div>
  <div class="cp-ulist" id="cpUList">
    <?php if($cp_users->num_rows===0): ?>
      <p style="padding:16px;color:var(--cp-sub);font-size:.85rem;">No conversations yet</p>
    <?php else: while($cpu=$cp_users->fetch_assoc()):
      $cpImg=!empty($cpu['profile_image'])?htmlspecialchars($cpu['profile_image']):'default_profile.png';
      $cpName=htmlspecialchars($cpu['user_name']);
      $cpUnread=(int)$cpu['unread_count'];
      $cpBlocked=in_array((int)$cpu['user_id'],$cp_blocked_ids);
    ?>
      <div class="cp-urow <?=$cpUnread>0?'has-unread':''?> <?=$cpBlocked?'is-blocked':''?>"
           data-uid="<?=$cpu['user_id']?>" data-name="<?=$cpName?>" data-img="<?=$cpImg?>"
           data-unread="<?=$cpUnread?>" data-blocked="<?=$cpBlocked?'1':'0'?>">
        <div class="cp-avatar-wrap">
          <img src="<?=$cpImg?>" alt="<?=$cpName?>">
          <?php if($cpUnread>0):?>
            <span class="cp-unread-dot" id="cpBadge_<?=$cpu['user_id']?>"><?=$cpUnread?></span>
          <?php endif;?>
          <!-- FIX: single status dot, hidden until JS populates it -->
          <span class="cp-online-dot hidden" id="cpDot_<?=$cpu['user_id']?>"></span>
        </div>
        <div>
          <div class="cp-uname"><?=$cpName?></div>
          <!-- FIX: sub-text = "Tap to chat" or unread count — never "Online" text -->
          <div class="cp-usub" id="cpSub_<?=$cpu['user_id']?>">
            <?php
              if($cpBlocked) echo 'Blocked';
              elseif($cpUnread>0) echo $cpUnread.' new message'.($cpUnread>1?'s':'');
              else echo 'Tap to chat';
            ?>
          </div>
        </div>
      </div>
    <?php endwhile;endif;?>
  </div>
  <div class="cp-win" id="cpWin">
    <div class="cp-msgs" id="cpMsgs"></div>
    <div id="cpReplyBar">
      <span style="font-size:13px;color:var(--cp-acc);">↩</span>
      <div class="cp-rb-preview"><span class="cp-rb-name" id="cpRbName"></span> <span id="cpRbText"></span></div>
      <button class="cp-rb-close" id="cpRbClose">✕</button>
    </div>
    <div id="cpEditBar">
      <span class="cp-eb-label">✏️ Editing message…</span>
      <button class="cp-eb-close" id="cpEbClose">✕</button>
    </div>
    <div class="cp-strip" id="cpStrip"></div>
    <div class="cp-bar">
      <button class="cp-plus" id="cpPlusBtn">+</button>
      <div class="cp-mpop" id="cpMPop">
        <div class="cp-mprow">
          <div class="cp-mpbtn" id="cpMPEmoji"><span class="ico">😊</span><span>Emoji</span></div>
          <div class="cp-mpbtn gif-btn" id="cpMPGif"><span class="ico">GIF</span><span>GIF</span></div>
          <div class="cp-mpbtn" id="cpMPFile"><span class="ico">📎</span><span>File</span></div>
          <div class="cp-mpbtn" id="cpMPImg"><span class="ico">🖼️</span><span>Image</span></div>
          <div class="cp-mpbtn" id="cpMPVid"><span class="ico">🎬</span><span>Video</span></div>
          <div class="cp-mpbtn" id="cpMPAud"><span class="ico">🎵</span><span>Audio</span></div>
        </div>
        <div class="cp-subpick" id="cpEmojiPick">
          <div class="cp-etabs">
            <span class="cp-etab on" data-cat="smileys">😊</span>
            <span class="cp-etab" data-cat="hands">👋</span>
            <span class="cp-etab" data-cat="objects">🎁</span>
            <span class="cp-etab" data-cat="nature">🌸</span>
            <span class="cp-etab" data-cat="food">🍕</span>
            <span class="cp-etab" data-cat="symbols">❤️</span>
          </div>
          <div class="cp-egrid" id="cpEGrid"></div>
        </div>
        <div class="cp-subpick" id="cpGifPick">
          <input class="cp-gif-srch" id="cpGifSrch" placeholder="Search GIFs…">
          <div class="cp-ggrid" id="cpGGrid"><p style="grid-column:span 2;color:var(--cp-sub);font-size:.78rem;">Type to search…</p></div>
        </div>
      </div>
      <div class="cp-inpwrap"><input id="cpTxtInp" placeholder="Message…" autocomplete="off"></div>
      <button class="cp-send" id="cpSend">➤</button>
    </div>
  </div>
</div>

<div id="cpKebab">
  <div class="cp-km" id="cpKMProfile">👤 View Profile</div>
  <div class="cp-km cp-kreport">🚩 Report User</div>
  <div class="cp-km red" id="cpKMBlock">🚫 Block User</div>
  <div class="cp-ctx-divider"></div>
  <div class="cp-km red" id="cpKMClear">🧹 Clear Chat</div>
</div>

<div id="cpCtx">
  <div class="cp-ci" id="cpCtxReact">😊 React</div>
  <div class="cp-ctx-divider"></div>
  <div class="cp-ci" id="cpCtxCopy">📋 Copy Text</div>
  <div class="cp-ci" id="cpCtxReply">↩️ Reply</div>
  <div class="cp-ci" id="cpCtxForward">↗️ Forward</div>
  <div class="cp-ci" id="cpCtxEdit" style="display:none">✏️ Edit</div>
  <div class="cp-ci" id="cpCtxDownload" style="display:none">⬇️ Download</div>
  <div class="cp-ctx-divider"></div>
  <div class="cp-ci red" id="cpCtxDelMe">🗑️ Delete for Me</div>
  <div class="cp-ci red" id="cpCtxDelAll" style="display:none">❌ Delete for Everyone</div>
</div>

<div id="cpEmojiBar">
  <span data-emoji="❤️">❤️</span><span data-emoji="🔥">🔥</span>
  <span data-emoji="😂">😂</span><span data-emoji="😮">😮</span>
  <span data-emoji="😢">😢</span><span data-emoji="👍">👍</span>
</div>

<div id="cpFwdDrawer">
  <h5>↗️ Forward to…</h5>
  <input type="text" id="cpFwdSearch" placeholder="Search people…">
  <div id="cpFwdResults"></div>
  <div id="cpFwdList">
    <?php foreach($cp_following as $fu):
      $fi=!empty($fu['profile_image'])?htmlspecialchars($fu['profile_image']):'default_profile.png';
      $fn=htmlspecialchars($fu['full_name']?:$fu['user_name']);
    ?>
      <div class="cp-fwd-user">
        <img src="<?=$fi?>" alt="">
        <span><?=$fn?></span>
        <button class="cp-fwd-send" data-uid="<?=$fu['user_id']?>">Send</button>
      </div>
    <?php endforeach;?>
  </div>
</div>

<div id="cpToast"></div>
<input type="file" id="cpFI" accept="*/*" multiple>
<input type="file" id="cpII" accept="image/*" multiple>
<input type="file" id="cpVI" accept="video/*">
<input type="file" id="cpAI" accept="audio/*">

<script>
(function(){
'use strict';
const TENOR='YOUR_TENOR_API_KEY';

const panel=document.getElementById('cpPanel'),closeBtn=document.getElementById('cpClose'),backBtn=document.getElementById('cpBack');
const titleEl=document.getElementById('cpTitle'),auDiv=document.getElementById('cpAU'),auImg=document.getElementById('cpAUImg'),auName=document.getElementById('cpAUName');
const uList=document.getElementById('cpUList'),srchWrap=document.getElementById('cpSrch'),srchInp=document.getElementById('cpSrchInp');
const win=document.getElementById('cpWin'),msgs=document.getElementById('cpMsgs'),txtInp=document.getElementById('cpTxtInp'),sendBtn=document.getElementById('cpSend');
const strip=document.getElementById('cpStrip'),plusBtn=document.getElementById('cpPlusBtn'),mpop=document.getElementById('cpMPop');
const emojiPick=document.getElementById('cpEmojiPick'),gifPick=document.getElementById('cpGifPick'),gifSrch=document.getElementById('cpGifSrch'),gGrid=document.getElementById('cpGGrid');
const ctx=document.getElementById('cpCtx'),kebabBtn=document.getElementById('cpKebabBtn'),kebab=document.getElementById('cpKebab');
const toast=document.getElementById('cpToast'),blockBtn=document.getElementById('cpKMBlock');
const replyBar=document.getElementById('cpReplyBar'),editBar=document.getElementById('cpEditBar');
const emojiBar=document.getElementById('cpEmojiBar'),fwdDrawer=document.getElementById('cpFwdDrawer');
const fwdSearch=document.getElementById('cpFwdSearch'),fwdResults=document.getElementById('cpFwdResults');

let uid=null,ctxMsgId=null,ctxMsgEl=null,replyToId=null,editMsgId=null,fwdMsgId=null;
let files=[],pollT=null,listPollT=null;
let fileDialogOpen=false,confirmOpen=false,isBlocked=false;
let userScrolled=false,lastMsgHash='';

/* ── Auto-reopen if returning from view_post / profile with ?chat_open=1 ── */
(function autoReopen(){
  const params=new URLSearchParams(window.location.search);
  if(params.get('chat_open')!=='1')return;
  const chatUid=params.get('chat_uid');
  const chatName=params.get('chat_name')||'Chat';
  const chatImg=params.get('chat_img')||'default_profile.png';
  const clean=new URL(window.location.href);
  clean.searchParams.delete('chat_open');clean.searchParams.delete('chat_uid');
  clean.searchParams.delete('chat_name');clean.searchParams.delete('chat_img');
  window.history.replaceState({},'',clean.toString());
  if(!chatUid)return;
  panel.classList.add('cp-open');startListPoll();startStatusPoll();
  uid=chatUid;
  titleEl.style.display='none';auDiv.style.display='flex';
  auImg.src=decodeURIComponent(chatImg);auName.textContent=decodeURIComponent(chatName);
  backBtn.style.display='flex';kebabBtn.style.display='flex';
  uList.style.display='none';srchWrap.style.display='none';
  win.classList.add('on');files=[];lastMsgHash='';userScrolled=false;
  checkBlockStatus(uid,blocked=>{updateBlockBtn(blocked);renderBlockBanner(blocked);loadMsgs(uid,true);if(!blocked)startPoll(uid);});
})();

msgs.addEventListener('scroll',()=>{userScrolled=(msgs.scrollHeight-msgs.scrollTop-msgs.clientHeight)>=80;});

const openBtn=document.getElementById('openChat');
if(openBtn){openBtn.addEventListener('click',e=>{e.stopPropagation();panel.classList.toggle('cp-open');if(panel.classList.contains('cp-open')){startListPoll();startStatusPoll();}else{stopListPoll();stopStatusPoll();}});}

function closePanel(){panel.classList.remove('cp-open');kebab.classList.remove('on');stopPoll();stopListPoll();stopStatusPoll();}
closeBtn.addEventListener('click',closePanel);

document.addEventListener('click',e=>{
  if(fileDialogOpen||confirmOpen)return;
  if(panel.classList.contains('cp-open')&&!panel.contains(e.target)&&!ctx.contains(e.target)&&!kebab.contains(e.target)&&!emojiBar.contains(e.target)&&!fwdDrawer.contains(e.target)&&e.target!==openBtn&&!openBtn?.contains(e.target))closePanel();
  if(!kebab.contains(e.target)&&e.target!==kebabBtn)kebab.classList.remove('on');
  if(!ctx.contains(e.target)&&!emojiBar.contains(e.target))ctx.classList.remove('on');
  if(!mpop.contains(e.target)&&e.target!==plusBtn)closeMpop();
  if(!emojiBar.contains(e.target))emojiBar.classList.remove('on');
  if(!fwdDrawer.contains(e.target)&&!e.target.closest('#cpCtxForward'))fwdDrawer.classList.remove('on');
});

document.querySelectorAll('.cp-dot').forEach(d=>{d.addEventListener('click',()=>{panel.classList.forEach(c=>{if(c.startsWith('cpt-'))panel.classList.remove(c);});if(d.dataset.t!=='default')panel.classList.add('cpt-'+d.dataset.t);document.querySelectorAll('.cp-dot').forEach(x=>x.classList.remove('on'));d.classList.add('on');});});

backBtn.addEventListener('click',showList);
function showList(){
  uid=null;stopPoll();isBlocked=false;
  win.classList.remove('on');uList.style.display='';srchWrap.style.display='';
  backBtn.style.display='none';kebabBtn.style.display='none';
  titleEl.style.display='';auDiv.style.display='none';
  kebab.classList.remove('on');closeMpop();files=[];renderStrip();
  lastMsgHash='';userScrolled=false;clearReply();clearEdit();
  const bb=document.getElementById('cpBlockBanner');if(bb)bb.remove();
  document.querySelector('.cp-bar').style.display='';
}

function checkBlockStatus(t,cb){fetch('check_block.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'target_id='+t}).then(r=>r.json()).then(d=>cb(d.blocked==1)).catch(()=>cb(false));}
function updateBlockBtn(b){isBlocked=b;if(b){blockBtn.innerHTML='✅ Unblock User';blockBtn.classList.remove('red');blockBtn.classList.add('green');}else{blockBtn.innerHTML='🚫 Block User';blockBtn.classList.remove('green');blockBtn.classList.add('red');}}
function renderBlockBanner(b){
  const ex=document.getElementById('cpBlockBanner');if(ex)ex.remove();
  const bar=document.querySelector('.cp-bar');
  if(b){bar.style.display='none';const bn=document.createElement('div');bn.className='cp-block-banner';bn.id='cpBlockBanner';bn.innerHTML=`<span class="cp-bb-icon">🚫</span><span class="cp-bb-title">You've blocked this user</span><span class="cp-bb-sub">They cannot message you while blocked.</span><button class="cp-unblock-btn" id="cpUnblockInChat">Unblock</button>`;win.insertBefore(bn,bar);document.getElementById('cpUnblockInChat').addEventListener('click',doUnblock);}
  else bar.style.display='';
}
function markRowBlocked(t,b){const row=uList.querySelector(`.cp-urow[data-uid="${t}"]`);if(!row)return;const sub=row.querySelector('.cp-usub');if(b){row.classList.add('is-blocked');row.dataset.blocked='1';if(sub)sub.textContent='Blocked';uList.appendChild(row);}else{row.classList.remove('is-blocked');row.dataset.blocked='0';if(sub)sub.textContent='Tap to chat';uList.insertBefore(row,uList.firstElementChild);}}

uList.addEventListener('click',e=>{
  const row=e.target.closest('.cp-urow');if(!row)return;
  uid=row.dataset.uid;if(!uid)return;
  const name=row.dataset.name||'User',img=row.dataset.img||'';
  titleEl.style.display='none';auDiv.style.display='flex';auImg.src=img;auName.textContent=name;
  backBtn.style.display='flex';kebabBtn.style.display='flex';
  uList.style.display='none';srchWrap.style.display='none';
  win.classList.add('on');files=[];renderStrip();lastMsgHash='';userScrolled=false;
  clearBadge(uid);clearReply();clearEdit();
  const preBlocked=row.dataset.blocked==='1';
  updateBlockBtn(preBlocked);renderBlockBanner(preBlocked);
  loadMsgs(uid,true);
  if(!preBlocked)startPoll(uid);
  checkBlockStatus(uid,blocked=>{if(blocked!==preBlocked){updateBlockBtn(blocked);renderBlockBanner(blocked);if(!blocked)startPoll(uid);else stopPoll();}});
});

function clearBadge(t){const row=uList.querySelector(`.cp-urow[data-uid="${t}"]`);const b=document.getElementById('cpBadge_'+t);if(b)b.remove();if(row){row.classList.remove('has-unread');const s=document.getElementById('cpSub_'+t);if(s&&row.dataset.blocked!=='1')s.textContent='Tap to chat';}}
function updateBadge(t,count){const row=uList.querySelector(`.cp-urow[data-uid="${t}"]`);if(!row)return;let b=document.getElementById('cpBadge_'+t);const s=document.getElementById('cpSub_'+t);if(count>0){row.classList.add('has-unread');if(s&&row.dataset.blocked!=='1')s.textContent=count+' new message'+(count>1?'s':'');if(!b){const w=row.querySelector('.cp-avatar-wrap');b=document.createElement('span');b.className='cp-unread-dot';b.id='cpBadge_'+t;w.appendChild(b);}b.textContent=count;}else clearBadge(t);}

srchInp.addEventListener('input',()=>{const q=srchInp.value.toLowerCase();uList.querySelectorAll('.cp-urow').forEach(r=>{r.style.display=(r.dataset.name||'').toLowerCase().includes(q)?'':'none';});});

function loadMsgs(u,force){
  fetch('load_messages.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'user_id='+u})
  .then(r=>r.text()).then(html=>{
    if(html===lastMsgHash&&!force)return;
    lastMsgHash=html;
    const wasAtBottom=msgs.scrollHeight-msgs.scrollTop-msgs.clientHeight<80;
    msgs.innerHTML=html;bindMsgEvents();markSeen(u);
    if(force||wasAtBottom){msgs.scrollTop=msgs.scrollHeight;userScrolled=false;}
  }).catch(()=>{msgs.innerHTML='<p style="padding:12px;color:#f66;">Load failed</p>';});
}

function bindMsgEvents(){
  msgs.querySelectorAll('.chat-message').forEach(el=>{el.classList.add('cp-msg');const b=el.querySelector('.message-bubble');if(b)b.classList.add('cp-bub');});
  msgs.querySelectorAll('.cp-reply-snip').forEach(el=>{el.addEventListener('click',()=>{const t=msgs.querySelector(`[data-message-id="${el.dataset.jump}"]`);if(t){t.scrollIntoView({behavior:'smooth',block:'center'});t.style.outline='2px solid var(--cp-acc)';setTimeout(()=>t.style.outline='',1200);}});});
  msgs.querySelectorAll('.cp-undo-btn').forEach(btn=>{btn.addEventListener('click',()=>msgAction(btn.dataset.action,btn.dataset.id,{},()=>loadMsgs(uid,true)));});
  msgs.querySelectorAll('.cp-view-post').forEach(lnk=>{lnk.addEventListener('click',e=>{e.stopPropagation();viewPost(lnk.dataset.postId);});});
  msgs.querySelectorAll('.cp-post-author').forEach(el=>{el.addEventListener('click',e=>{e.stopPropagation();const ownerId=el.dataset.ownerId;if(!ownerId)return;const back=buildBackUrl();window.location.href='public_profile.php?user_id='+ownerId+'&back='+encodeURIComponent(back);});});
  msgs.querySelectorAll('.cp-react-pill').forEach(pill=>{pill.addEventListener('click',e=>{e.stopPropagation();msgAction('react',pill.dataset.mid,{emoji:pill.dataset.emoji},()=>loadMsgs(uid,false));});});
}

function buildBackUrl(){const base=window.location.href.split('?')[0];const name=encodeURIComponent(auName.textContent||'');const img=encodeURIComponent(auImg.src||'');return base+'?chat_open=1&chat_uid='+uid+'&chat_name='+name+'&chat_img='+img;}
function viewPost(pid){window.location.href='view_post.php?post_id='+pid+'&back='+encodeURIComponent(buildBackUrl());}

function startPoll(u){stopPoll();pollT=setInterval(()=>{if(u===uid)loadMsgs(u,false);else pollUnread();},5000);}
function stopPoll(){clearInterval(pollT);pollT=null;}
function markSeen(u){fetch('mark_seen.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'sender_id='+u}).catch(()=>{});}
function pollUnread(){fetch('get_unread_counts.php',{method:'POST'}).then(r=>r.json()).then(data=>{if(!data||typeof data!=='object')return;uList.querySelectorAll('.cp-urow').forEach(row=>updateBadge(row.dataset.uid,data[row.dataset.uid]||0));}).catch(()=>{});}
function startListPoll(){stopListPoll();listPollT=setInterval(()=>{if(!uid)pollUnread();},8000);}
function stopListPoll(){clearInterval(listPollT);listPollT=null;}

/* ══════════════════════════════════════════════════════════════
   ONLINE STATUS  — FIX
   • Only the green/grey dot on the avatar indicates status.
   • The sub-text "Online" label is NEVER set — only "Tap to chat"
     or unread count is shown there.
   • The conversation header shows a tiny human-readable label
     ("Active now" / "Last seen 3 min ago") but NOT "Online".
   ══════════════════════════════════════════════════════════════ */
let statusPollT=null;
function startStatusPoll(){stopStatusPoll();fetchOnlineStatus();statusPollT=setInterval(fetchOnlineStatus,30000);}
function stopStatusPoll(){clearInterval(statusPollT);statusPollT=null;}

function fetchOnlineStatus(){
  const rows=uList.querySelectorAll('.cp-urow');
  if(!rows.length)return;
  const idList=[...rows].map(r=>String(r.dataset.uid)).filter(Boolean);
  if(!idList.length)return;
  fetch('update_last_seen.php',{method:'POST'}).catch(()=>{});
  fetch('get_online_status.php',{method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'user_ids='+encodeURIComponent(idList.join(','))})
  .then(r=>r.json())
  .then(data=>{
    const status={};
    Object.keys(data).forEach(k=>{status[String(k)]=data[k];});
    rows.forEach(row=>{
      const u=String(row.dataset.uid);
      if(!status[u])return;
      const info=status[u];

      /* ── Update the single dot on the avatar ── */
      const dot=document.getElementById('cpDot_'+u);
      if(dot){
        dot.classList.remove('hidden','offline');
        if(info.online){
          dot.title='Online';
        } else {
          dot.classList.add('offline');
          dot.title=info.label||'Offline';
        }
      }

      /* ── Update conversation-header status label (NOT "Online" — use "Active now") ── */
      if(u===String(uid)){
        const statusEl=document.getElementById('cpAUStatus');
        if(statusEl){
          statusEl.textContent=info.online?'Active now':(info.label||'');
          statusEl.style.color=info.online?'#39d353':'var(--cp-sub)';
        }
      }

      /* ── Sub-text: NEVER set to "Online". Keep "Tap to chat" or unread count ── */
      // We intentionally do NOT update cp-usub with online text here.
      // The green dot is the sole visual indicator of online status.
    });
  }).catch(()=>{});
}

function setReply(msgId,name,preview){replyToId=msgId;document.getElementById('cpRbName').textContent=name;document.getElementById('cpRbText').textContent=preview;replyBar.classList.add('on');txtInp.focus();}
function clearReply(){replyToId=null;replyBar.classList.remove('on');document.getElementById('cpRbName').textContent='';document.getElementById('cpRbText').textContent='';}
document.getElementById('cpRbClose').addEventListener('click',clearReply);
function startEdit(msgId,currentText){editMsgId=msgId;txtInp.value=currentText;editBar.classList.add('on');txtInp.focus();}
function clearEdit(){editMsgId=null;editBar.classList.remove('on');}
document.getElementById('cpEbClose').addEventListener('click',()=>{clearEdit();txtInp.value='';});

function doSend(){
  if(!uid||isBlocked)return;
  const text=txtInp.value.trim();
  if(editMsgId){if(!text)return;msgAction('edit',editMsgId,{new_text:text},()=>{clearEdit();txtInp.value='';loadMsgs(uid,true);});return;}
  if(!text&&files.length===0)return;
  if(files.length>0){
    const sends=files.map(pf=>{const fd=new FormData();fd.append('receiver_id',uid);fd.append('file',pf.file);let ft='media';if(pf.type.startsWith('audio/'))ft='audio';else if(pf.type==='application/pdf'||pf.type.includes('document')||pf.type.startsWith('text/'))ft='document';fd.append('file_type',ft);if(replyToId)fd.append('reply_to_id',replyToId);return fetch('send_message_file.php',{method:'POST',body:fd}).then(r=>r.text()).then(raw=>{try{return JSON.parse(raw);}catch(e){return{status:'error',msg:raw};}});});
    Promise.all(sends).then(results=>{if(results.find(d=>d.status==='blocked')){toast_('Cannot send — user blocked');return;}files=[];renderStrip();clearReply();if(text){const fd2=new FormData();fd2.append('receiver_id',uid);fd2.append('message',text);if(replyToId)fd2.append('reply_to_id',replyToId);fetch('send_message.php',{method:'POST',body:fd2}).then(()=>{txtInp.value='';userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}).catch(()=>loadMsgs(uid,true));}else{userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}});
  }else{
    const fd=new FormData();fd.append('receiver_id',uid);fd.append('message',text);if(replyToId)fd.append('reply_to_id',replyToId);
    fetch('send_message.php',{method:'POST',body:fd}).then(r=>r.text()).then(raw=>{try{const d=JSON.parse(raw);if(d.status==='sent'){txtInp.value='';userScrolled=false;clearReply();moveRowToTop(uid);loadMsgs(uid,true);}else if(d.status==='blocked')toast_('Cannot send — user blocked');else toast_('Failed: '+(d.msg||'unknown'));}catch(e){toast_('Server error');}});
  }
}
sendBtn.addEventListener('click',doSend);
txtInp.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();}});
function moveRowToTop(t){const row=uList.querySelector(`.cp-urow[data-uid="${t}"]`);if(row&&row.dataset.blocked!=='1')uList.insertBefore(row,uList.firstElementChild);}

plusBtn.addEventListener('click',e=>{e.stopPropagation();mpop.classList.toggle('on');if(!mpop.classList.contains('on'))closeSubPicks();});
mpop.addEventListener('click',e=>e.stopPropagation());
function closeMpop(){mpop.classList.remove('on');closeSubPicks();}
function closeSubPicks(){emojiPick.classList.remove('on');gifPick.classList.remove('on');}
document.getElementById('cpMPEmoji').addEventListener('click',e=>{e.stopPropagation();const was=emojiPick.classList.contains('on');closeSubPicks();if(!was){emojiPick.classList.add('on');renderEmojis('smileys');}});
document.getElementById('cpMPGif').addEventListener('click',e=>{e.stopPropagation();const was=gifPick.classList.contains('on');closeSubPicks();if(!was){gifPick.classList.add('on');if(!gGrid.dataset.loaded)searchGifs('trending');}});
function wireFile(btnId,inputId){const btn=document.getElementById(btnId),inp=document.getElementById(inputId);btn.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();fileDialogOpen=true;inp.click();setTimeout(()=>{fileDialogOpen=false;},10000);});inp.addEventListener('change',function(e){fileDialogOpen=false;e.stopPropagation();Array.from(this.files).forEach(f=>{files.push({file:f,type:f.type,url:URL.createObjectURL(f)});});this.value='';renderStrip();closeMpop();txtInp.focus();});window.addEventListener('focus',()=>{if(fileDialogOpen)setTimeout(()=>{fileDialogOpen=false;},300);},{once:false});}
wireFile('cpMPFile','cpFI');wireFile('cpMPImg','cpII');wireFile('cpMPVid','cpVI');wireFile('cpMPAud','cpAI');
function renderStrip(){if(files.length===0){strip.classList.remove('on');return;}strip.classList.add('on');strip.innerHTML='';files.forEach((pf,i)=>{let w;if(pf.type.startsWith('image/')){w=document.createElement('div');w.className='cp-ath';w.innerHTML=`<img src="${pf.url}">`;}else if(pf.type.startsWith('video/')){w=document.createElement('div');w.className='cp-ath';w.innerHTML=`<video src="${pf.url}" muted></video>`;}else if(pf.type.startsWith('audio/')){w=document.createElement('div');w.className='cp-ath';w.style.cssText='display:flex;align-items:center;justify-content:center;font-size:22px;';w.textContent='🎵';}else{w=document.createElement('div');w.className='cp-afile';w.textContent='📎 '+pf.file.name.slice(0,14);}const rm=document.createElement('button');rm.className='cp-rm';rm.textContent='✕';rm.addEventListener('click',e=>{e.stopPropagation();files.splice(i,1);renderStrip();});w.appendChild(rm);strip.appendChild(w);});}

const EMOJI={smileys:['😀','😁','😂','🤣','😊','😍','🥰','😎','🤩','😜','😏','😢','😭','😤','😡','🤔','🫡','😴','🥳','🤯','😱','🥺','🫶','😇','😆','😅','🙂','🙃','🤐','🥴'],hands:['👋','🤚','🖐','✋','🤙','👈','👉','👆','👇','☝','👍','👎','✌','🤞','🤟','🤘','👌','🤌','🤏','🖖','🫵','🫰','🤜','🤛','👊','✊','🙌','🤲','🙏','💅'],objects:['🎁','🎈','🎉','🎊','🎀','🏆','🥇','🎮','🕹','🧩','🎲','🎨','🖼','🧸','🎯','🔮','💎','💍','👑','🔑','🗝','🔭','🔬','💊','🩹','📱','💻','📷','🎸','🎹'],nature:['🌸','🌺','🌻','🌹','🌷','🍀','🌿','🌱','🪴','🌳','🌲','🍃','🍂','🍁','🌾','🌵','🌴','🌊','🔥','⭐','🌙','☀️','🌈','❄️','☃️','🌤','⛅','🪨','🦋','🌎'],food:['🍕','🍔','🌮','🍣','🍜','🍩','🍪','🎂','🍫','☕','🧋','🍵','🍺','🍹','🥤','🍓','🍎','🍌','🍇','🥝','🫐','🍑','🥑','🌽','🥕','🍄','🥦','🧄','🥐','🍳'],symbols:['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','☮️','✝️','🕉','☯️','✡️','🛐','⚛️','🆘','♾️','✨','💫','⚡']};
function renderEmojis(cat){const g=document.getElementById('cpEGrid');g.innerHTML='';(EMOJI[cat]||[]).forEach(em=>{const s=document.createElement('span');s.textContent=em;s.addEventListener('click',e=>{e.stopPropagation();txtInp.value+=em;txtInp.focus();});g.appendChild(s);});}
document.querySelectorAll('.cp-etab').forEach(t=>{t.addEventListener('click',e=>{e.stopPropagation();document.querySelectorAll('.cp-etab').forEach(x=>x.classList.remove('on'));t.classList.add('on');renderEmojis(t.dataset.cat);});});

let gifT;gifSrch.addEventListener('input',()=>{clearTimeout(gifT);gifT=setTimeout(()=>searchGifs(gifSrch.value||'trending'),500);});
function searchGifs(q){if(TENOR==='YOUR_TENOR_API_KEY'){gGrid.innerHTML='<p style="grid-column:span 2;color:var(--cp-sub);font-size:.78rem;">Add Tenor API key</p>';return;}fetch(`https://tenor.googleapis.com/v2/search?q=${encodeURIComponent(q)}&key=${TENOR}&limit=8&media_filter=gif`).then(r=>r.json()).then(data=>{gGrid.innerHTML='';gGrid.dataset.loaded='1';(data.results||[]).forEach(g=>{const img=document.createElement('img');img.src=g.media_formats?.tinygif?.url||'';img.loading='lazy';img.addEventListener('click',e=>{e.stopPropagation();sendGif(img.src);});gGrid.appendChild(img);});}).catch(()=>{gGrid.innerHTML='<p style="color:#f66;grid-column:span 2;font-size:.78rem;">GIF failed</p>';});}
function sendGif(url){if(!uid)return;const fd=new FormData();fd.append('receiver_id',uid);fd.append('gif_url',url);fetch('send_message.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='sent'){userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}});closeMpop();}

kebabBtn.addEventListener('click',e=>{e.stopPropagation();if(kebab.classList.contains('on')){kebab.classList.remove('on');return;}const r=kebabBtn.getBoundingClientRect();kebab.style.top=(r.bottom+6)+'px';kebab.style.right=(window.innerWidth-r.right)+'px';kebab.classList.add('on');});
document.getElementById('cpKMProfile').addEventListener('click',e=>{e.stopPropagation();kebab.classList.remove('on');if(uid)window.location.href='public_profile.php?user_id='+uid;});
document.getElementById('cpKMClear').addEventListener('click',e=>{e.stopPropagation();kebab.classList.remove('on');if(!uid)return;confirmOpen=true;const yes=confirm('Clear this conversation from your view?');setTimeout(()=>{confirmOpen=false;},300);if(!yes)return;msgAction('clear_chat',0,{other_id:uid},()=>{loadMsgs(uid,true);toast_('🧹 Chat cleared');});});
blockBtn.addEventListener('click',e=>{e.stopPropagation();kebab.classList.remove('on');if(!uid)return;if(isBlocked){doUnblock();return;}confirmOpen=true;const yes=confirm('Block this user?');setTimeout(()=>{confirmOpen=false;},300);if(!yes)return;doBlock();});
function doBlock(){fetch('block_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`target_user_id=${uid}&action=block`}).then(r=>r.json()).then(d=>{if(d.status==='success'){toast_('User blocked');updateBlockBtn(true);renderBlockBanner(true);markRowBlocked(uid,true);stopPoll();}else toast_('Could not block: '+(d.message||'unknown'));}).catch(()=>toast_('Network error'));}
function doUnblock(){fetch('block_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`target_user_id=${uid}&action=unblock`}).then(r=>r.json()).then(d=>{if(d.status==='success'){toast_('User unblocked');updateBlockBtn(false);renderBlockBanner(false);markRowBlocked(uid,false);loadMsgs(uid,true);startPoll(uid);}else toast_('Could not unblock: '+(d.message||'unknown'));}).catch(()=>toast_('Network error'));}
document.querySelector('.cp-kreport').addEventListener('click',e=>{e.stopPropagation();kebab.classList.remove('on');if(!uid)return;confirmOpen=true;const reason=prompt('Why are you reporting this user?');setTimeout(()=>{confirmOpen=false;},300);if(!reason||!reason.trim())return;fetch('report_user.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`reported_id=${uid}&reason=${encodeURIComponent(reason.trim())}`}).then(r=>r.json()).then(d=>{if(d.status==='reported')toast_('✅ Report submitted');else if(d.status==='already_reported')toast_("⚠️ Already reported");else toast_('❌ '+(d.message||'Could not report'));}).catch(()=>toast_('❌ Network error'));});

msgs.addEventListener('contextmenu',e=>{
  const msgEl=e.target.closest('.cp-msg,.chat-message');if(!msgEl){ctx.classList.remove('on');return;}
  e.preventDefault();
  ctxMsgId=msgEl.dataset.messageId||msgEl.dataset.msgId||null;ctxMsgEl=msgEl;
  const isMine=msgEl.dataset.mine==='1',canDelAll=msgEl.dataset.canDelAll==='1',canEdit=msgEl.dataset.canEdit==='1';
  const hasMedia=!!msgEl.querySelector('.cp-media-img,.cp-media-vid,audio');
  const isDeleted=!!msgEl.querySelector('.cp-del-bub');
  document.getElementById('cpCtxReact').style.display=!isDeleted?'flex':'none';
  document.getElementById('cpCtxCopy').style.display=!isDeleted?'flex':'none';
  document.getElementById('cpCtxReply').style.display=!isDeleted?'flex':'none';
  document.getElementById('cpCtxForward').style.display=!isDeleted?'flex':'none';
  document.getElementById('cpCtxEdit').style.display=(isMine&&canEdit&&!isDeleted)?'flex':'none';
  document.getElementById('cpCtxDownload').style.display=(hasMedia&&!isDeleted)?'flex':'none';
  document.getElementById('cpCtxDelMe').style.display=!isDeleted?'flex':'none';
  document.getElementById('cpCtxDelAll').style.display=(isMine&&canDelAll)?'flex':'none';
  const mw=185,mh=260;let x=e.clientX,y=e.clientY;
  const pr=panel.getBoundingClientRect();
  const maxX=Math.min(window.innerWidth,pr.right)-mw-8;
  const maxY=Math.min(window.innerHeight,pr.bottom)-mh-8;
  if(x>maxX)x=maxX;if(x<pr.left+4)x=pr.left+4;
  if(y>maxY)y=maxY;if(y<pr.top+4)y=pr.top+4;
  ctx.style.left=x+'px';ctx.style.top=y+'px';ctx.classList.add('on');
});

document.getElementById('cpCtxReact').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgEl)return;const r=ctxMsgEl.getBoundingClientRect();let x=r.left,y=r.top-52;if(x+230>window.innerWidth)x=window.innerWidth-238;if(y<4)y=r.bottom+6;emojiBar.style.left=x+'px';emojiBar.style.top=y+'px';emojiBar.classList.add('on');});
emojiBar.querySelectorAll('span').forEach(s=>{s.addEventListener('click',e=>{e.stopPropagation();emojiBar.classList.remove('on');if(ctxMsgId)msgAction('react',ctxMsgId,{emoji:s.dataset.emoji},()=>loadMsgs(uid,false));});});
document.getElementById('cpCtxCopy').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgEl)return;const bub=ctxMsgEl.querySelector('.cp-bub,.message-bubble');if(!bub)return;const clone=bub.cloneNode(true);clone.querySelectorAll('.cp-meta,.meta,.cp-tick,.cp-reply-snip,.cp-reactions,.cp-react-pill').forEach(n=>n.remove());const txt=clone.querySelector('.text');navigator.clipboard.writeText((txt?txt.innerText:clone.innerText).trim()).then(()=>toast_('✅ Copied')).catch(()=>toast_('Copy failed'));});
document.getElementById('cpCtxReply').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgEl||!ctxMsgId)return;const bub=ctxMsgEl.querySelector('.cp-bub,.message-bubble');const clone=bub?bub.cloneNode(true):null;if(clone)clone.querySelectorAll('.cp-meta,.meta,.cp-tick,.cp-reply-snip,.cp-reactions').forEach(n=>n.remove());const preview=clone?(clone.querySelector('.text')?.innerText||clone.innerText).trim().slice(0,60):'message';const senderName=ctxMsgEl.dataset.mine==='1'?'You':(auName?.textContent||'Them');setReply(ctxMsgId,senderName,preview);});
document.getElementById('cpCtxForward').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');fwdMsgId=ctxMsgId;fwdDrawer.classList.add('on');fwdSearch.value='';fwdResults.innerHTML='';fwdSearch.focus();});
document.getElementById('cpCtxEdit').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgEl)return;const txt=ctxMsgEl.querySelector('.text');startEdit(ctxMsgId,txt?txt.innerText.trim():'');});
document.getElementById('cpCtxDownload').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgEl)return;const media=ctxMsgEl.querySelector('.cp-media-img,.cp-media-vid');if(!media)return;const src=media.dataset.src||media.src||media.currentSrc;const a=document.createElement('a');a.href=src;a.download='';a.target='_blank';a.click();});
document.getElementById('cpCtxDelMe').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgId)return;msgAction('delete_me',ctxMsgId,{},()=>loadMsgs(uid,true));});
document.getElementById('cpCtxDelAll').addEventListener('click',e=>{e.stopPropagation();ctx.classList.remove('on');if(!ctxMsgId)return;confirmOpen=true;const yes=confirm('Delete for everyone?');setTimeout(()=>{confirmOpen=false;},300);if(!yes)return;msgAction('delete_everyone',ctxMsgId,{},()=>loadMsgs(uid,true));});

fwdSearch.addEventListener('input',()=>{const q=fwdSearch.value.trim();if(!q){fwdResults.innerHTML='';return;}fetch('search_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`query=${encodeURIComponent(q)}`}).then(r=>r.text()).then(html=>{fwdResults.innerHTML=html;fwdResults.querySelectorAll('[data-user-id]').forEach(el=>{const btn=document.createElement('button');btn.className='cp-fwd-send';btn.textContent='Send';btn.addEventListener('click',e2=>{e2.stopPropagation();doForward(el.dataset.userId);});el.appendChild(btn);});}).catch(()=>{fwdResults.innerHTML='<p style="color:#f66;font-size:.78rem;">Search failed</p>';});});
document.querySelectorAll('.cp-fwd-send').forEach(btn=>{btn.addEventListener('click',e=>{e.stopPropagation();doForward(btn.dataset.uid);});});
function doForward(targetUid){if(!fwdMsgId)return;fetch('forward_message.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`message_id=${fwdMsgId}&receiver_id=${targetUid}`}).then(r=>r.json()).then(d=>{fwdDrawer.classList.remove('on');toast_(d.status==='sent'?'✅ Forwarded':'❌ Forward failed');}).catch(()=>toast_('❌ Network error'));}

function msgAction(action,msgId,extra,onSuccess){
  let body=`action=${encodeURIComponent(action)}`;
  if(msgId)body+=`&message_id=${encodeURIComponent(msgId)}`;
  body+=Object.entries(extra).map(([k,v])=>`&${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('');
  fetch('message_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
  .then(r=>r.json()).then(d=>{if(d.status==='success'){if(onSuccess)onSuccess(d);}else toast_('❌ '+(d.msg||d.message||'Action failed'));}).catch(()=>toast_('Network error'));
}

let toastT;
function toast_(msg){toast.textContent=msg;toast.style.opacity='1';clearTimeout(toastT);toastT=setTimeout(()=>{toast.style.opacity='0';},2600);}

})();
</script>