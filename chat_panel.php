<?php
/**
 * chat_panel.php  — include at bottom of feed_frontend.php before </body>
 * <?php include 'chat_panel.php'; ?>
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();
require_once 'connect.php';
$cp_uid = (int)$_SESSION['user_id'];

/*
 * ── FIX 1: Load blocked user IDs so PHP can mark rows on page load ──
 * This ensures the "blocked" styling survives page reloads.
 */
$blk_stmt = $con->prepare("SELECT blocked_id FROM blocks WHERE blocker_id = ?");
$blk_stmt->bind_param('i', $cp_uid);
$blk_stmt->execute();
$blk_res = $blk_stmt->get_result();
$cp_blocked_ids = [];
while ($br = $blk_res->fetch_assoc()) {
    $cp_blocked_ids[] = (int)$br['blocked_id'];
}

$cp_sql = "
  SELECT
    u.user_id,
    u.user_name,
    u.profile_image,
    MAX(m.created_at) AS last_msg_time,
    SUM(
      CASE WHEN m.receiver_id = ? AND m.sender_id = u.user_id AND (m.seen = 0 OR m.seen IS NULL) THEN 1 ELSE 0 END
    ) AS unread_count
  FROM users u
  JOIN messages m
    ON (m.sender_id = u.user_id AND m.receiver_id = ?)
    OR (m.sender_id = ? AND m.receiver_id = u.user_id)
  WHERE u.user_id != ?
  GROUP BY u.user_id, u.user_name, u.profile_image

  UNION

  SELECT
    u.user_id,
    u.user_name,
    u.profile_image,
    NULL AS last_msg_time,
    0    AS unread_count
  FROM users u
  WHERE u.user_id != ?
    AND u.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?)
    AND u.user_id NOT IN (
      SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
      FROM messages WHERE sender_id = ? OR receiver_id = ?
    )

  ORDER BY last_msg_time DESC, user_name ASC
  LIMIT 50
";
$cp_st = $con->prepare($cp_sql);
$cp_st->bind_param(
    "iiiiiiiii",
    $cp_uid,$cp_uid,$cp_uid,$cp_uid,
    $cp_uid,$cp_uid,$cp_uid,$cp_uid,$cp_uid
);
$cp_st->execute();
$cp_users = $cp_st->get_result();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap');

:root{
  --cp-bg:#1a1025; --cp-surf:rgba(255,255,255,.07);
  --cp-bdr:rgba(255,255,255,.11); --cp-acc:#9b5de5; --cp-acc2:#f15bb5;
  --cp-txt:#ede9f6; --cp-sub:rgba(237,233,246,.5);
  --cp-me:linear-gradient(135deg,#9b5de5,#f15bb5);
  --cp-them:rgba(255,255,255,.09); --cp-inp:rgba(255,255,255,.07);
  --cp-r:18px; --cp-sh:0 24px 70px rgba(0,0,0,.6);
}
.cpt-ocean  {--cp-bg:#0a1628;--cp-acc:#00c4ff;--cp-me:linear-gradient(135deg,#00c4ff,#0077ff);}
.cpt-forest {--cp-bg:#0d1f15;--cp-acc:#39d353;--cp-me:linear-gradient(135deg,#39d353,#00bb77);}
.cpt-rose   {--cp-bg:#1f0d16;--cp-acc:#ff6b9d;--cp-me:linear-gradient(135deg,#ff6b9d,#ff3371);}
.cpt-gold   {--cp-bg:#1a1408;--cp-acc:#f5c518;--cp-me:linear-gradient(135deg,#f5c518,#e07b00);}
.cpt-ice    {--cp-bg:#0d1926;--cp-acc:#a8edea;--cp-me:linear-gradient(135deg,#a8edea,#7fdbff);}

#cpPanel{
  position:fixed; bottom:24px; right:24px;
  width:400px; height:600px;
  background:var(--cp-bg); border-radius:var(--cp-r);
  border:1px solid var(--cp-bdr); box-shadow:var(--cp-sh);
  display:flex; flex-direction:column; overflow:hidden;
  z-index:9999; font-family:'DM Sans',sans-serif; color:var(--cp-txt);
  backdrop-filter:blur(20px);
  transform:translateY(calc(100% + 30px)) scale(.93);
  opacity:0; pointer-events:none;
  transition:transform .38s cubic-bezier(.34,1.56,.64,1), opacity .25s ease;
}
#cpPanel.cp-open{transform:translateY(0) scale(1);opacity:1;pointer-events:all;}
#cpPanel::after{
  content:'';position:absolute;inset:0;pointer-events:none;z-index:0;border-radius:var(--cp-r);
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
}
#cpPanel>*{position:relative;z-index:1;}

.cp-hdr{
  display:flex; align-items:center; gap:8px;
  padding:12px 14px; border-bottom:1px solid var(--cp-bdr);
  flex-shrink:0; background:var(--cp-bg); position:sticky; top:0; z-index:10;
}
.cp-title{font-weight:700;font-size:.95rem;flex:1;letter-spacing:-.3px;}
.cp-hbtn{
  background:var(--cp-surf);border:1px solid var(--cp-bdr);
  color:var(--cp-txt);width:30px;height:30px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:13px;flex-shrink:0;transition:background .2s,transform .15s;
}
.cp-hbtn:hover{background:var(--cp-acc);transform:scale(1.12);}

.cp-dots{display:flex;gap:5px;flex-shrink:0;}
.cp-dot{width:15px;height:15px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .2s;}
.cp-dot:hover,.cp-dot.on{border-color:var(--cp-txt);transform:scale(1.25);}
.cp-dot[data-t=default]{background:linear-gradient(135deg,#9b5de5,#f15bb5);}
.cp-dot[data-t=ocean]  {background:linear-gradient(135deg,#00c4ff,#0077ff);}
.cp-dot[data-t=forest] {background:linear-gradient(135deg,#39d353,#00bb77);}
.cp-dot[data-t=rose]   {background:linear-gradient(135deg,#ff6b9d,#ff3371);}
.cp-dot[data-t=gold]   {background:linear-gradient(135deg,#f5c518,#e07b00);}
.cp-dot[data-t=ice]    {background:linear-gradient(135deg,#a8edea,#7fdbff);}

.cp-au{display:none;align-items:center;gap:8px;flex:1;min-width:0;}
.cp-au img{width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--cp-acc);flex-shrink:0;}
.cp-au strong{font-size:.87rem;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cp-au small{font-size:.7rem;color:var(--cp-sub);}

#cpKebab{
  position:fixed; background:var(--cp-bg); border:1px solid var(--cp-bdr);
  border-radius:14px; overflow:hidden; min-width:175px;
  box-shadow:0 12px 40px rgba(0,0,0,.55); z-index:99999; display:none;
}
#cpKebab.on{display:block;animation:cpPop .15s ease;}
.cp-km{
  padding:12px 16px;font-size:.85rem;cursor:pointer;
  display:flex;align-items:center;gap:9px;transition:background .14s;
  color:var(--cp-txt);background:var(--cp-bg);white-space:nowrap;
}
.cp-km:hover{background:var(--cp-surf);}
.cp-km.red{color:#ff6b6b;}
.cp-km.green{color:#39d353;}

.cp-srch{padding:10px 14px;flex-shrink:0;position:relative;background:var(--cp-bg);}
.cp-srch input{
  width:100%;background:var(--cp-inp);border:1px solid var(--cp-bdr);
  border-radius:12px;color:var(--cp-txt);padding:8px 14px 8px 34px;
  font-size:.84rem;outline:none;transition:border-color .2s;
}
.cp-srch input:focus{border-color:var(--cp-acc);}
.cp-srch i{position:absolute;left:26px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--cp-sub);}

.cp-ulist{flex:1;overflow-y:auto;padding:4px 0;background:var(--cp-bg);}
.cp-ulist::-webkit-scrollbar{width:3px;}
.cp-ulist::-webkit-scrollbar-thumb{background:var(--cp-acc);border-radius:3px;}
.cp-urow{
  display:flex;align-items:center;gap:10px;
  padding:9px 14px;cursor:pointer;border-radius:12px;margin:2px 8px;
  transition:background .18s;position:relative;
}
.cp-urow:hover{background:var(--cp-surf);}
.cp-urow .cp-avatar-wrap{position:relative;flex-shrink:0;}
.cp-urow img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--cp-acc);display:block;}

/* ── FIX 1: blocked row styles — applied both by PHP (on load) and JS (after action) ── */
.cp-urow.is-blocked img{
  border-color:#ff6b6b !important;
  opacity:.65;
  filter:grayscale(50%);
}
.cp-urow.is-blocked .cp-uname{color:#ff8a8a !important;}
.cp-urow.is-blocked .cp-usub{color:rgba(255,138,138,.55) !important;}
/* red "Blocked" badge pill on the row */
.cp-urow.is-blocked .cp-uname::after{
  content:'Blocked';
  display:inline-block;
  margin-left:6px;
  font-size:9px;
  font-weight:700;
  letter-spacing:.4px;
  background:rgba(255,107,107,.18);
  border:1px solid rgba(255,107,107,.45);
  color:#ff8a8a;
  border-radius:6px;
  padding:1px 5px;
  vertical-align:middle;
  line-height:1.6;
}

.cp-unread-dot{
  position:absolute;top:-2px;right:-2px;
  min-width:18px;height:18px;padding:0 4px;
  background:linear-gradient(135deg,#f15bb5,#9b5de5);
  color:#fff;border-radius:9px;font-size:10px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  border:2px solid var(--cp-bg);animation:cpPulse 2s ease-in-out infinite;
}
@keyframes cpPulse{
  0%,100%{box-shadow:0 0 0 0 rgba(241,91,181,.5);}
  50%{box-shadow:0 0 0 5px rgba(241,91,181,0);}
}
.cp-uname{font-size:.87rem;font-weight:600;}
.cp-usub{font-size:.73rem;color:var(--cp-sub);}
.cp-urow.has-unread .cp-uname{color:#fff;}
.cp-urow.has-unread .cp-usub{color:var(--cp-txt);}

.cp-win{flex:1;display:none;flex-direction:column;overflow:hidden;min-height:0;}
.cp-win.on{display:flex;}
.cp-msgs{flex:1;overflow-y:auto;padding:12px 12px 6px;display:flex;flex-direction:column;gap:7px;min-height:0;}
.cp-msgs::-webkit-scrollbar{width:3px;}
.cp-msgs::-webkit-scrollbar-thumb{background:var(--cp-acc);border-radius:3px;}

.cp-msg{display:flex;flex-direction:column;max-width:78%;}
.cp-msg.me  {align-self:flex-end;align-items:flex-end;}
.cp-msg.them{align-self:flex-start;align-items:flex-start;}
@keyframes cpIn{from{opacity:0;transform:translateY(7px);}}
.cp-msg{animation:cpIn .22s ease;}
.cp-bub{padding:9px 13px;border-radius:18px;font-size:.87rem;line-height:1.45;word-break:break-word;}
.cp-msg.me   .cp-bub{background:var(--cp-me);border-bottom-right-radius:4px;color:#fff;}
.cp-msg.them .cp-bub{background:var(--cp-them);border-bottom-left-radius:4px;}
.cp-bub img,.cp-bub video{max-width:200px;border-radius:10px;margin-top:4px;display:block;}
.cp-bub audio{margin-top:4px;width:200px;}
.cp-meta{font-size:10px;color:var(--cp-sub);margin-top:3px;display:flex;gap:4px;}
.cp-postcard{
  background:rgba(255,255,255,.07);border:1px solid var(--cp-bdr);
  border-radius:12px;padding:9px;margin-top:4px;font-size:.8rem;cursor:pointer;transition:background .2s;
}
.cp-postcard:hover{background:rgba(255,255,255,.14);}
.cp-postcard img{width:100%;border-radius:8px;margin-bottom:4px;}

/* ── FIX 2: deleted message placeholder ── */
.cp-deleted-notice{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:7px 12px;
  border-radius:14px;
  font-size:.78rem;
  font-style:italic;
  color:var(--cp-sub);
  background:rgba(255,255,255,.04);
  border:1px dashed rgba(255,255,255,.12);
  animation:cpIn .2s ease;
}
.cp-deleted-notice .cp-del-icon{font-size:13px;opacity:.7;}

/* ── BLOCK BANNER ── */
.cp-block-banner{
  margin:8px 12px;
  background:rgba(255,107,107,.12);
  border:1px solid rgba(255,107,107,.35);
  border-radius:14px;padding:14px 16px;
  display:flex;flex-direction:column;align-items:center;gap:10px;
  text-align:center;flex-shrink:0;
}
.cp-block-banner .cp-bb-icon{font-size:28px;}
.cp-block-banner .cp-bb-title{font-size:.88rem;font-weight:600;color:#ff8a8a;}
.cp-block-banner .cp-bb-sub{font-size:.76rem;color:var(--cp-sub);}
.cp-block-banner .cp-unblock-btn{
  background:rgba(57,211,83,.15);border:1px solid rgba(57,211,83,.4);
  color:#39d353;border-radius:10px;padding:7px 18px;
  font-size:.82rem;font-weight:600;cursor:pointer;
  transition:background .2s,transform .15s;
}
.cp-block-banner .cp-unblock-btn:hover{background:rgba(57,211,83,.3);transform:scale(1.04);}

.cp-strip{display:none;flex-wrap:wrap;gap:6px;padding:6px 12px;border-top:1px solid var(--cp-bdr);background:var(--cp-surf);flex-shrink:0;}
.cp-strip.on{display:flex;}
.cp-ath{position:relative;width:54px;height:54px;border-radius:8px;overflow:hidden;border:1px solid var(--cp-bdr);}
.cp-ath img,.cp-ath video{width:100%;height:100%;object-fit:cover;}
.cp-ath .cp-rm{position:absolute;top:2px;right:2px;background:rgba(0,0,0,.65);color:#fff;border:none;border-radius:50%;width:16px;height:16px;font-size:9px;cursor:pointer;line-height:16px;text-align:center;padding:0;}
.cp-afile{display:flex;align-items:center;gap:4px;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:8px;padding:4px 8px;font-size:.74rem;max-width:110px;overflow:hidden;white-space:nowrap;position:relative;}

.cp-bar{
  display:flex;align-items:center;gap:6px;
  padding:8px 10px;border-top:1px solid var(--cp-bdr);
  flex-shrink:0;position:relative;background:var(--cp-bg);
}
.cp-plus{
  width:34px;height:34px;border-radius:50%;
  background:var(--cp-me);border:none;color:#fff;
  font-size:20px;font-weight:300;line-height:1;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:transform .2s,box-shadow .2s;
}
.cp-plus:hover{transform:scale(1.1);box-shadow:0 4px 16px rgba(155,93,229,.5);}

.cp-mpop{
  position:absolute;bottom:58px;left:10px;
  background:var(--cp-bg);border:1px solid var(--cp-bdr);
  border-radius:16px;padding:12px;display:none;flex-direction:column;gap:8px;
  box-shadow:var(--cp-sh);z-index:500;width:280px;
}
.cp-mpop.on{display:flex;animation:cpPop .15s ease;}
.cp-mprow{display:flex;gap:8px;flex-wrap:wrap;}
.cp-mpbtn{
  display:flex;flex-direction:column;align-items:center;gap:3px;
  background:var(--cp-surf);border:1px solid var(--cp-bdr);
  border-radius:12px;padding:8px;cursor:pointer;
  font-size:10px;color:var(--cp-sub);transition:background .15s,color .15s;
  min-width:52px;flex:1;
}
.cp-mpbtn .ico{font-size:20px;line-height:1;}
.cp-mpbtn.gif-btn .ico{font-size:13px;font-weight:700;}
.cp-mpbtn:hover{background:var(--cp-acc);color:#fff;border-color:var(--cp-acc);}

.cp-subpick{display:none;}
.cp-subpick.on{display:block;}
.cp-etabs{display:flex;gap:4px;margin-bottom:6px;}
.cp-etab{font-size:17px;cursor:pointer;padding:3px 7px;border-radius:7px;transition:background .14s;}
.cp-etab.on,.cp-etab:hover{background:var(--cp-surf);}
.cp-egrid{display:flex;flex-wrap:wrap;gap:3px;max-height:150px;overflow-y:auto;}
.cp-egrid span{font-size:20px;cursor:pointer;padding:3px;border-radius:7px;transition:background .14px;}
.cp-egrid span:hover{background:var(--cp-surf);}
.cp-gif-srch{width:100%;background:var(--cp-inp);border:1px solid var(--cp-bdr);border-radius:10px;color:var(--cp-txt);padding:6px 10px;margin-bottom:6px;outline:none;font-size:.81rem;}
.cp-ggrid{display:grid;grid-template-columns:1fr 1fr;gap:4px;max-height:150px;overflow-y:auto;}
.cp-ggrid img{width:100%;border-radius:8px;cursor:pointer;transition:transform .14s;}
.cp-ggrid img:hover{transform:scale(1.04);}

.cp-inpwrap{
  flex:1;display:flex;align-items:center;
  background:var(--cp-inp);border:1px solid var(--cp-bdr);
  border-radius:13px;padding:6px 11px;transition:border-color .2s;min-width:0;
}
.cp-inpwrap:focus-within{border-color:var(--cp-acc);}
.cp-inpwrap input{flex:1;background:none;border:none;outline:none;color:var(--cp-txt);font-size:.87rem;font-family:'DM Sans',sans-serif;min-width:0;}
.cp-inpwrap input::placeholder{color:var(--cp-sub);}
.cp-send{
  width:36px;height:36px;border-radius:50%;
  background:var(--cp-me);border:none;color:#fff;font-size:15px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:transform .15s,box-shadow .15s;
}
.cp-send:hover{transform:scale(1.08);box-shadow:0 4px 16px rgba(155,93,229,.5);}

#cpCtx{
  position:fixed;background:var(--cp-bg);border:1px solid var(--cp-bdr);
  border-radius:12px;overflow:hidden;z-index:99999;min-width:185px;
  box-shadow:0 8px 30px rgba(0,0,0,.5);display:none;
}
#cpCtx.on{display:block;animation:cpPop .14s ease;}
.cp-ci{padding:10px 15px;font-size:.83rem;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .14s;color:var(--cp-txt);}
.cp-ci:hover{background:var(--cp-surf);}
.cp-ci.red{color:#ff6b6b;}
.cp-ctx-divider{height:1px;background:var(--cp-bdr);margin:2px 0;}

@keyframes cpPop{from{opacity:0;transform:scale(.9);}}

#cpToast{
  position:fixed;bottom:88px;right:28px;
  background:#222;color:#fff;padding:10px 18px;
  border-radius:12px;font-size:.83rem;font-family:'DM Sans',sans-serif;
  z-index:99999;opacity:0;pointer-events:none;transition:opacity .3s;
}
@media(max-width:480px){#cpPanel{width:100vw;height:100dvh;bottom:0;right:0;border-radius:0;}}
.chat-panel{display:none!important;}
#cpFI,#cpII,#cpVI,#cpAI{display:none;}
</style>

<!-- HTML -->
<div id="cpPanel">
  <div class="cp-hdr">
    <button class="cp-hbtn" id="cpBack" style="display:none">←</button>
    <span class="cp-title" id="cpTitle">Messages</span>
    <div class="cp-au" id="cpAU">
      <img id="cpAUImg" src="" alt="">
      <div style="min-width:0">
        <strong id="cpAUName"></strong>
        <small>tap for info</small>
      </div>
    </div>
    <div class="cp-dots">
      <div class="cp-dot on" data-t="default" title="Purple"></div>
      <div class="cp-dot"    data-t="ocean"   title="Ocean"></div>
      <div class="cp-dot"    data-t="forest"  title="Forest"></div>
      <div class="cp-dot"    data-t="rose"    title="Rose"></div>
      <div class="cp-dot"    data-t="gold"    title="Gold"></div>
      <div class="cp-dot"    data-t="ice"     title="Ice"></div>
    </div>
    <button class="cp-hbtn" id="cpKebabBtn" style="display:none" title="Options">⋮</button>
    <button class="cp-hbtn" id="cpClose" title="Close">✕</button>
  </div>

  <div class="cp-srch" id="cpSrch">
    <i class="fas fa-search"></i>
    <input id="cpSrchInp" placeholder="Search conversations…">
  </div>

  <div class="cp-ulist" id="cpUList">
    <?php if($cp_users->num_rows===0): ?>
      <p style="padding:16px;color:var(--cp-sub);font-size:.85rem;">No conversations yet</p>
    <?php else: while($cpu=$cp_users->fetch_assoc()):
      $cpImg      = !empty($cpu['profile_image']) ? htmlspecialchars($cpu['profile_image']) : 'default_profile.png';
      $cpName     = htmlspecialchars($cpu['user_name']);
      $cpUnread   = (int)$cpu['unread_count'];
      /* FIX 1: mark blocked users server-side so style survives reload */
      $cpIsBlocked = in_array((int)$cpu['user_id'], $cp_blocked_ids);
    ?>
      <div class="cp-urow
                  <?= $cpUnread > 0   ? 'has-unread' : '' ?>
                  <?= $cpIsBlocked    ? 'is-blocked'  : '' ?>"
           data-uid="<?=$cpu['user_id']?>"
           data-name="<?=$cpName?>"
           data-img="<?=$cpImg?>"
           data-unread="<?=$cpUnread?>"
           data-blocked="<?= $cpIsBlocked ? '1' : '0' ?>">
        <div class="cp-avatar-wrap">
          <img src="<?=$cpImg?>" alt="<?=$cpName?>">
          <?php if($cpUnread > 0): ?>
            <span class="cp-unread-dot" id="cpBadge_<?=$cpu['user_id']?>"><?=$cpUnread?></span>
          <?php endif; ?>
        </div>
        <div>
          <div class="cp-uname"><?=$cpName?></div>
          <div class="cp-usub">
            <?php if($cpIsBlocked):   ?>Blocked
            <?php elseif($cpUnread>0):?><?=$cpUnread?> new message<?=$cpUnread>1?'s':''?>
            <?php else:               ?>Tap to chat
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

  <div class="cp-win" id="cpWin">
    <div class="cp-msgs" id="cpMsgs"></div>
    <div class="cp-strip" id="cpStrip"></div>

    <div class="cp-bar">
      <button class="cp-plus" id="cpPlusBtn" title="Media & more">+</button>

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
          <div class="cp-ggrid" id="cpGGrid">
            <p style="grid-column:span 2;color:var(--cp-sub);font-size:.78rem;">Type to search…</p>
          </div>
        </div>
      </div>

      <div class="cp-inpwrap">
        <input id="cpTxtInp" placeholder="Message…" autocomplete="off">
      </div>
      <button class="cp-send" id="cpSend" title="Send">➤</button>
    </div>
  </div>
</div>

<div id="cpKebab">
  <div class="cp-km" id="cpKMProfile">👤 View Profile</div>
  <div class="cp-km cp-kreport">🚩 Report User</div>
  <div class="cp-km red" id="cpKMBlock">🚫 Block User</div>
</div>

<!-- FIX 2: Context menu with Copy + Forward + divider + Delete options -->
<div id="cpCtx">
  <div class="cp-ci" id="cpCtxCopy">📋 Copy Text</div>
  <div class="cp-ci" id="cpCtxForward">↗️ Forward</div>
  <div class="cp-ctx-divider"></div>
  <div class="cp-ci red" id="cpCtxDelMe">🗑️ Delete for Me</div>
  <div class="cp-ci red" id="cpCtxDelAll">❌ Delete for Everyone</div>
</div>

<div id="cpToast"></div>

<input type="file" id="cpFI" accept="*/*"     multiple>
<input type="file" id="cpII" accept="image/*" multiple>
<input type="file" id="cpVI" accept="video/*">
<input type="file" id="cpAI" accept="audio/*">

<script>
(function(){
'use strict';
const TENOR='YOUR_TENOR_API_KEY';

const panel    = document.getElementById('cpPanel');
const closeBtn = document.getElementById('cpClose');
const backBtn  = document.getElementById('cpBack');
const titleEl  = document.getElementById('cpTitle');
const auDiv    = document.getElementById('cpAU');
const auImg    = document.getElementById('cpAUImg');
const auName   = document.getElementById('cpAUName');
const uList    = document.getElementById('cpUList');
const srchWrap = document.getElementById('cpSrch');
const srchInp  = document.getElementById('cpSrchInp');
const win      = document.getElementById('cpWin');
const msgs     = document.getElementById('cpMsgs');
const txtInp   = document.getElementById('cpTxtInp');
const sendBtn  = document.getElementById('cpSend');
const strip    = document.getElementById('cpStrip');
const plusBtn  = document.getElementById('cpPlusBtn');
const mpop     = document.getElementById('cpMPop');
const emojiPick= document.getElementById('cpEmojiPick');
const gifPick  = document.getElementById('cpGifPick');
const gifSrch  = document.getElementById('cpGifSrch');
const gGrid    = document.getElementById('cpGGrid');
const ctx      = document.getElementById('cpCtx');
const kebabBtn = document.getElementById('cpKebabBtn');
const kebab    = document.getElementById('cpKebab');
const toast    = document.getElementById('cpToast');
const blockBtn = document.getElementById('cpKMBlock');

let uid            = null;
let ctxMsgId       = null;
let ctxMsgIsMine   = false;  // FIX 2: track ownership for context menu
let files          = [];
let pollT          = null;
let fileDialogOpen = false;
let confirmOpen    = false;
let isBlocked      = false;

let userScrolled = false;
let lastMsgHash  = '';

msgs.addEventListener('scroll', () => {
  userScrolled = (msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight) >= 80;
});

/* ── Open panel ── */
const openBtn = document.getElementById('openChat');
if (openBtn) {
  openBtn.addEventListener('click', e => {
    e.stopPropagation();
    panel.classList.toggle('cp-open');
    if (panel.classList.contains('cp-open')) startListPoll(); else stopListPoll();
  });
}

/* ── Close panel ── */
function closePanel() {
  panel.classList.remove('cp-open');
  kebab.classList.remove('on');
  stopPoll(); stopListPoll();
}
closeBtn.addEventListener('click', closePanel);

document.addEventListener('click', e => {
  if (fileDialogOpen || confirmOpen) return;
  if (panel.classList.contains('cp-open') && !panel.contains(e.target) &&
      e.target !== openBtn && !openBtn?.contains(e.target)) {
    closePanel();
  }
  if (!kebab.contains(e.target) && e.target !== kebabBtn) kebab.classList.remove('on');
  if (!ctx.contains(e.target)) ctx.classList.remove('on');
  if (!mpop.contains(e.target) && e.target !== plusBtn) closeMpop();
});

/* ── Theme ── */
document.querySelectorAll('.cp-dot').forEach(d => {
  d.addEventListener('click', () => {
    panel.classList.forEach(c => { if (c.startsWith('cpt-')) panel.classList.remove(c); });
    if (d.dataset.t !== 'default') panel.classList.add('cpt-' + d.dataset.t);
    document.querySelectorAll('.cp-dot').forEach(x => x.classList.remove('on'));
    d.classList.add('on');
  });
});

/* ── Back to list ── */
backBtn.addEventListener('click', showList);
function showList() {
  uid = null; stopPoll(); isBlocked = false;
  win.classList.remove('on');
  uList.style.display = ''; srchWrap.style.display = '';
  backBtn.style.display = 'none'; kebabBtn.style.display = 'none';
  titleEl.style.display = ''; auDiv.style.display = 'none';
  kebab.classList.remove('on');
  closeMpop(); files = []; renderStrip();
  lastMsgHash = ''; userScrolled = false;
  const bb = document.getElementById('cpBlockBanner');
  if (bb) bb.remove();
  document.querySelector('.cp-bar').style.display = '';
}

/* ── Check block status via AJAX ── */
function checkBlockStatus(targetUid, callback) {
  fetch('check_block.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'target_id=' + targetUid
  })
  .then(r => r.json())
  .then(d => callback(d.blocked == 1))
  .catch(() => callback(false));
}

/* ── Toggle kebab block button label ── */
function updateBlockBtn(blocked) {
  isBlocked = blocked;
  if (blocked) {
    blockBtn.innerHTML = '✅ Unblock User';
    blockBtn.classList.remove('red'); blockBtn.classList.add('green');
  } else {
    blockBtn.innerHTML = '🚫 Block User';
    blockBtn.classList.remove('green'); blockBtn.classList.add('red');
  }
}

/* ── Show/hide block banner ── */
function renderBlockBanner(blocked) {
  const existing = document.getElementById('cpBlockBanner');
  if (existing) existing.remove();
  const bar = document.querySelector('.cp-bar');
  if (blocked) {
    bar.style.display = 'none';
    const banner = document.createElement('div');
    banner.className = 'cp-block-banner';
    banner.id = 'cpBlockBanner';
    banner.innerHTML = `
      <span class="cp-bb-icon">🚫</span>
      <span class="cp-bb-title">You've blocked this user</span>
      <span class="cp-bb-sub">They cannot send you messages while blocked.</span>
      <button class="cp-unblock-btn" id="cpUnblockInChat">Unblock User</button>
    `;
    win.insertBefore(banner, bar);
    document.getElementById('cpUnblockInChat').addEventListener('click', doUnblock);
  } else {
    bar.style.display = '';
  }
}

/* ── FIX 1: Mark row blocked/unblocked in sidebar list ── */
function markRowBlocked(targetUid, blocked) {
  const row = uList.querySelector(`.cp-urow[data-uid="${targetUid}"]`);
  if (!row) return;
  const sub = row.querySelector('.cp-usub');
  if (blocked) {
    row.classList.add('is-blocked');
    row.dataset.blocked = '1';
    if (sub) sub.textContent = 'Blocked';
    // Move to bottom of list so unblocked users stay on top
    uList.appendChild(row);
  } else {
    row.classList.remove('is-blocked');
    row.dataset.blocked = '0';
    if (sub) sub.textContent = 'Tap to chat';
    // Move back to top (recent)
    uList.insertBefore(row, uList.firstElementChild);
  }
}

/* ── User row click ── */
uList.addEventListener('click', e => {
  const row = e.target.closest('.cp-urow');
  if (!row) return;
  uid = row.dataset.uid; if (!uid) return;
  const name = row.dataset.name || 'User';
  const img  = row.dataset.img  || '';
  titleEl.style.display = 'none';
  auDiv.style.display = 'flex';
  auImg.src = img; auName.textContent = name;
  backBtn.style.display = 'flex'; kebabBtn.style.display = 'flex';
  uList.style.display = 'none'; srchWrap.style.display = 'none';
  win.classList.add('on');
  files = []; renderStrip();
  lastMsgHash = ''; userScrolled = false;
  clearBadge(uid);

  /* FIX 1: use data-blocked from PHP-rendered HTML first,
     then confirm with a fresh AJAX call */
  const preBlocked = row.dataset.blocked === '1';
  updateBlockBtn(preBlocked);
  renderBlockBanner(preBlocked);
  loadMsgs(uid, true);
  if (!preBlocked) startPoll(uid);

  // Async confirmation to keep UI snappy
  checkBlockStatus(uid, (blocked) => {
    if (blocked !== preBlocked) {
      updateBlockBtn(blocked);
      renderBlockBanner(blocked);
      if (!blocked) startPoll(uid); else stopPoll();
    }
  });
});

/* ── Badge helpers ── */
function clearBadge(targetUid) {
  const row   = uList.querySelector(`.cp-urow[data-uid="${targetUid}"]`);
  const badge = document.getElementById('cpBadge_' + targetUid);
  if (badge) badge.remove();
  if (row) {
    row.classList.remove('has-unread');
    const sub = row.querySelector('.cp-usub');
    if (sub && row.dataset.blocked !== '1') sub.textContent = 'Tap to chat';
  }
}
function updateBadge(targetUid, count) {
  const row = uList.querySelector(`.cp-urow[data-uid="${targetUid}"]`);
  if (!row) return;
  let badge = document.getElementById('cpBadge_' + targetUid);
  if (count > 0) {
    row.classList.add('has-unread');
    const sub = row.querySelector('.cp-usub');
    if (sub && row.dataset.blocked !== '1') sub.textContent = count + ' new message' + (count > 1 ? 's' : '');
    if (!badge) {
      const wrap = row.querySelector('.cp-avatar-wrap');
      badge = document.createElement('span');
      badge.className = 'cp-unread-dot';
      badge.id = 'cpBadge_' + targetUid;
      wrap.appendChild(badge);
    }
    badge.textContent = count;
  } else {
    clearBadge(targetUid);
  }
}

/* ── Search filter ── */
srchInp.addEventListener('input', () => {
  const q = srchInp.value.toLowerCase();
  uList.querySelectorAll('.cp-urow').forEach(r => {
    r.style.display = (r.dataset.name || '').toLowerCase().includes(q) ? '' : 'none';
  });
});

/* ── Load messages ── */
function loadMsgs(u, force) {
  fetch('load_messages.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'user_id=' + u
  })
  .then(r => r.text())
  .then(html => {
    if (html === lastMsgHash && !force) return;
    lastMsgHash = html;
    const wasAtBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 80;
    msgs.innerHTML = html;
    bridgeBubbles();
    markSeen(u);
    if (force || wasAtBottom) { msgs.scrollTop = msgs.scrollHeight; userScrolled = false; }
  })
  .catch(() => { msgs.innerHTML = '<p style="padding:12px;color:#f66;">Load failed</p>'; });
}
function bridgeBubbles() {
  msgs.querySelectorAll('.chat-message').forEach(el => {
    el.classList.add('cp-msg');
    const b = el.querySelector('.message-bubble');
    if (b) b.classList.add('cp-bub');
  });
}
function startPoll(u) {
  stopPoll();
  pollT = setInterval(() => { if (u === uid) loadMsgs(u, false); else pollUnread(); }, 5000);
}
function stopPoll() { clearInterval(pollT); pollT = null; }
function markSeen(u) {
  fetch('mark_seen.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'sender_id='+u}).catch(()=>{});
}
function pollUnread() {
  fetch('get_unread_counts.php', {method:'POST'})
  .then(r => r.json())
  .then(data => {
    if (!data || typeof data !== 'object') return;
    uList.querySelectorAll('.cp-urow').forEach(row => {
      updateBadge(row.dataset.uid, data[row.dataset.uid] || 0);
    });
  }).catch(()=>{});
}
let listPollT = null;
function startListPoll() { stopListPoll(); listPollT = setInterval(() => { if (!uid) pollUnread(); }, 8000); }
function stopListPoll()  { clearInterval(listPollT); listPollT = null; }

/* ── Send ── */
function doSend() {
  if (!uid || isBlocked) return;
  const text = txtInp.value.trim();
  if (!text && files.length === 0) return;
  if (files.length > 0) {
    const sends = files.map(pf => {
      const fd = new FormData();
      fd.append('receiver_id', uid); fd.append('file', pf.file);
      let ftype = 'media';
      if (pf.type.startsWith('audio/')) ftype = 'audio';
      else if (pf.type==='application/pdf'||pf.type.includes('document')||pf.type.startsWith('text/')) ftype='document';
      fd.append('file_type', ftype);
      return fetch('send_message_file.php',{method:'POST',body:fd}).then(r=>r.text()).then(raw=>{
        try{return JSON.parse(raw);}catch(e){toast_('Server error');return{status:'error',msg:raw};}
      });
    });
    Promise.all(sends).then(results => {
      if (results.find(d=>d.status==='blocked')){toast_('Cannot send — user blocked');return;}
      const err=results.find(d=>d.status==='error');
      if(err) toast_('File error: '+(err.msg||'unknown'));
      files=[]; renderStrip();
      if(text){
        const fd2=new FormData();fd2.append('receiver_id',uid);fd2.append('message',text);
        fetch('send_message.php',{method:'POST',body:fd2}).then(r=>r.json())
          .then(()=>{txtInp.value='';userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}).catch(()=>loadMsgs(uid,true));
      } else {userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}
    });
  } else {
    const fd=new FormData();fd.append('receiver_id',uid);fd.append('message',text);
    fetch('send_message.php',{method:'POST',body:fd}).then(r=>r.text()).then(raw=>{
      try{
        const d=JSON.parse(raw);
        if(d.status==='sent'){txtInp.value='';userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}
        else if(d.status==='blocked') toast_('Cannot send — user blocked');
        else toast_('Failed: '+(d.msg||'unknown'));
      }catch(e){toast_('Server error');}
    });
  }
}
sendBtn.addEventListener('click', doSend);
txtInp.addEventListener('keydown', e => { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();} });

function moveRowToTop(targetUid) {
  const row = uList.querySelector(`.cp-urow[data-uid="${targetUid}"]`);
  if (row && row.dataset.blocked !== '1') uList.insertBefore(row, uList.firstElementChild);
}

/* ── Media popup ── */
plusBtn.addEventListener('click',e=>{e.stopPropagation();mpop.classList.toggle('on');if(!mpop.classList.contains('on'))closeSubPicks();});
mpop.addEventListener('click',e=>e.stopPropagation());
function closeMpop(){mpop.classList.remove('on');closeSubPicks();}
function closeSubPicks(){emojiPick.classList.remove('on');gifPick.classList.remove('on');}
document.getElementById('cpMPEmoji').addEventListener('click',e=>{e.stopPropagation();const was=emojiPick.classList.contains('on');closeSubPicks();if(!was){emojiPick.classList.add('on');renderEmojis('smileys');}});
document.getElementById('cpMPGif').addEventListener('click',e=>{e.stopPropagation();const was=gifPick.classList.contains('on');closeSubPicks();if(!was){gifPick.classList.add('on');if(!gGrid.dataset.loaded)searchGifs('trending');}});

/* ── File buttons ── */
function wireFile(btnId,inputId){
  const btn=document.getElementById(btnId),inp=document.getElementById(inputId);
  btn.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();fileDialogOpen=true;inp.click();setTimeout(()=>{fileDialogOpen=false;},10000);});
  inp.addEventListener('change',function(e){fileDialogOpen=false;e.stopPropagation();Array.from(this.files).forEach(f=>{files.push({file:f,type:f.type,url:URL.createObjectURL(f)});});this.value='';renderStrip();closeMpop();txtInp.focus();});
  window.addEventListener('focus',()=>{if(fileDialogOpen)setTimeout(()=>{fileDialogOpen=false;},300);},{once:false});
}
wireFile('cpMPFile','cpFI');wireFile('cpMPImg','cpII');wireFile('cpMPVid','cpVI');wireFile('cpMPAud','cpAI');

/* ── Attachment strip ── */
function renderStrip(){
  if(files.length===0){strip.classList.remove('on');return;}
  strip.classList.add('on');strip.innerHTML='';
  files.forEach((pf,i)=>{
    let w;
    if(pf.type.startsWith('image/')){w=document.createElement('div');w.className='cp-ath';w.innerHTML=`<img src="${pf.url}">`;}
    else if(pf.type.startsWith('video/')){w=document.createElement('div');w.className='cp-ath';w.innerHTML=`<video src="${pf.url}" muted></video>`;}
    else if(pf.type.startsWith('audio/')){w=document.createElement('div');w.className='cp-ath';w.style.cssText='display:flex;align-items:center;justify-content:center;font-size:22px;';w.textContent='🎵';}
    else{w=document.createElement('div');w.className='cp-afile';w.textContent='📎 '+pf.file.name.slice(0,14);}
    const rm=document.createElement('button');rm.className='cp-rm';rm.textContent='✕';
    rm.addEventListener('click',e=>{e.stopPropagation();files.splice(i,1);renderStrip();});
    w.appendChild(rm);strip.appendChild(w);
  });
}

/* ── Emojis ── */
const EMOJI={
  smileys:['😀','😁','😂','🤣','😊','😍','🥰','😎','🤩','😜','😏','😢','😭','😤','😡','🤔','🫡','😴','🥳','🤯','😱','🥺','🫶','😇','😆','😅','🙂','🙃','🤐','🥴'],
  hands:  ['👋','🤚','🖐','✋','🤙','👈','👉','👆','👇','☝','👍','👎','✌','🤞','🤟','🤘','👌','🤌','🤏','🖖','🫵','🫰','🤜','🤛','👊','✊','🙌','🤲','🙏','💅'],
  objects:['🎁','🎈','🎉','🎊','🎀','🏆','🥇','🎮','🕹','🧩','🎲','🎨','🖼','🧸','🎯','🔮','💎','💍','👑','🔑','🗝','🔭','🔬','💊','🩹','📱','💻','📷','🎸','🎹'],
  nature: ['🌸','🌺','🌻','🌹','🌷','🍀','🌿','🌱','🪴','🌳','🌲','🍃','🍂','🍁','🌾','🌵','🌴','🌊','🔥','⭐','🌙','☀️','🌈','❄️','☃️','🌤','⛅','🪨','🦋','🌎'],
  food:   ['🍕','🍔','🌮','🍣','🍜','🍩','🍪','🎂','🍫','☕','🧋','🍵','🍺','🍹','🥤','🍓','🍎','🍌','🍇','🥝','🫐','🍑','🥑','🌽','🥕','🍄','🥦','🧄','🥐','🍳'],
  symbols:['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','☮️','✝️','🕉','☯️','✡️','🛐','⚛️','🆘','♾️','✨','💫','⚡'],
};
function renderEmojis(cat){const g=document.getElementById('cpEGrid');g.innerHTML='';(EMOJI[cat]||[]).forEach(em=>{const s=document.createElement('span');s.textContent=em;s.addEventListener('click',e=>{e.stopPropagation();txtInp.value+=em;txtInp.focus();});g.appendChild(s);});}
document.querySelectorAll('.cp-etab').forEach(t=>{t.addEventListener('click',e=>{e.stopPropagation();document.querySelectorAll('.cp-etab').forEach(x=>x.classList.remove('on'));t.classList.add('on');renderEmojis(t.dataset.cat);});});

/* ── GIFs ── */
let gifT;
gifSrch.addEventListener('input',()=>{clearTimeout(gifT);gifT=setTimeout(()=>searchGifs(gifSrch.value||'trending'),500);});
function searchGifs(q){
  if(TENOR==='YOUR_TENOR_API_KEY'){gGrid.innerHTML='<p style="grid-column:span 2;color:var(--cp-sub);font-size:.78rem;">Add Tenor API key to enable GIFs</p>';return;}
  fetch(`https://tenor.googleapis.com/v2/search?q=${encodeURIComponent(q)}&key=${TENOR}&limit=8&media_filter=gif`)
  .then(r=>r.json()).then(data=>{gGrid.innerHTML='';gGrid.dataset.loaded='1';(data.results||[]).forEach(g=>{const img=document.createElement('img');img.src=g.media_formats?.tinygif?.url||'';img.loading='lazy';img.addEventListener('click',e=>{e.stopPropagation();sendGif(img.src);});gGrid.appendChild(img);});}).catch(()=>{gGrid.innerHTML='<p style="color:#f66;grid-column:span 2;font-size:.78rem;">GIF failed</p>';});
}
function sendGif(url){if(!uid)return;const fd=new FormData();fd.append('receiver_id',uid);fd.append('message','');fd.append('gif_url',url);fetch('send_message.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='sent'){userScrolled=false;moveRowToTop(uid);loadMsgs(uid,true);}});closeMpop();}

/* ── Kebab ── */
kebabBtn.addEventListener('click', e => {
  e.stopPropagation();
  if (kebab.classList.contains('on')) { kebab.classList.remove('on'); return; }
  const r = kebabBtn.getBoundingClientRect();
  kebab.style.top   = (r.bottom + 6) + 'px';
  kebab.style.right = (window.innerWidth - r.right) + 'px';
  kebab.classList.add('on');
});

document.getElementById('cpKMProfile').addEventListener('click', () => {
  kebab.classList.remove('on');
  if (uid) window.location.href = 'public_profile.php?user_id=' + uid;
});

/* ── Block / Unblock ── */
blockBtn.addEventListener('click', () => {
  kebab.classList.remove('on');
  if (!uid) return;
  if (isBlocked) {
    doUnblock();
  } else {
    confirmOpen = true;
    const yes = confirm('Block this user?');
    setTimeout(() => { confirmOpen = false; }, 300);
    if (!yes) return;
    doBlock();
  }
});

function doBlock() {
  fetch('block_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `target_user_id=${uid}&action=block`
  })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'success') {
      toast_('User blocked');
      updateBlockBtn(true);
      renderBlockBanner(true);
      markRowBlocked(uid, true);
      stopPoll();
    } else {
      toast_('Could not block: ' + (d.message || 'unknown'));
    }
  })
  .catch(() => toast_('Network error'));
}

function doUnblock() {
  fetch('block_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `target_user_id=${uid}&action=unblock`
  })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'success') {
      toast_('User unblocked');
      updateBlockBtn(false);
      renderBlockBanner(false);
      markRowBlocked(uid, false);
      loadMsgs(uid, true);
      startPoll(uid);
    } else {
      toast_('Could not unblock: ' + (d.message || 'unknown'));
    }
  })
  .catch(() => toast_('Network error'));
}

/* ── Report ── 
document.querySelector('.cp-kreport').addEventListener('click', () => {
  kebab.classList.remove('on');
  if (!uid) return;
  confirmOpen = true;
  const reason = prompt('Why are you reporting this user?');
  setTimeout(() => { confirmOpen = false; }, 300);
  if (!reason) return;
  fetch('report_user.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`reported_id=${uid}&reason=${encodeURIComponent(reason)}`})
  .then(r=>r.json()).then(d=>toast_(d.status==='reported'?'User reported':'Could not report')).catch(()=>toast_('Network error'));
});
*/
/* ── Report ── */
document.querySelector('.cp-kreport').addEventListener('click', () => {
  kebab.classList.remove('on');
  if (!uid) return;
  confirmOpen = true;
  const reason = prompt('Why are you reporting this user?');
  setTimeout(() => { confirmOpen = false; }, 300);
  if (!reason || !reason.trim()) return;
  fetch('report_user.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `reported_id=${uid}&reason=${encodeURIComponent(reason.trim())}`
  })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'reported')
      toast_('✅ Report submitted — our team will review it');
    else if (d.status === 'already_reported')
      toast_('⚠️ You already reported this account. We\'re reviewing it.');
    else
      toast_('❌ ' + (d.message || 'Could not submit report'));
  })
  .catch(() => toast_('❌ Network error'));
});
/* ══════════════════════════════════════════════════
   FIX 2: RIGHT-CLICK CONTEXT MENU — full delete fix
══════════════════════════════════════════════════ */
msgs.addEventListener('contextmenu', e => {
  const msgEl = e.target.closest('.cp-msg, .chat-message');
  if (!msgEl) { ctx.classList.remove('on'); return; }
  e.preventDefault();

  ctxMsgId   = msgEl.dataset.msgId || msgEl.dataset.messageId || null;
  ctxMsgIsMine = msgEl.classList.contains('me');

  // Show "Delete for Everyone" only if it's the user's own message
  document.getElementById('cpCtxDelAll').style.display = ctxMsgIsMine ? 'flex' : 'none';

  // Position safely within viewport
  const mw = 185, mh = 160;
  let x = e.clientX, y = e.clientY;
  if (x + mw > window.innerWidth)  x = window.innerWidth  - mw - 8;
  if (y + mh > window.innerHeight) y = window.innerHeight - mh - 8;
  ctx.style.left = x + 'px';
  ctx.style.top  = y + 'px';
  ctx.classList.add('on');
});

/* Copy */
document.getElementById('cpCtxCopy').addEventListener('click', () => {
  ctx.classList.remove('on');
  if (!ctxMsgId) return;
  const el  = msgs.querySelector(`[data-msg-id="${ctxMsgId}"], [data-message-id="${ctxMsgId}"]`);
  const bub = el?.querySelector('.cp-bub, .message-bubble');
  if (!bub) return;
  // Clone and strip .cp-meta / time spans before reading text
  const clone = bub.cloneNode(true);
  clone.querySelectorAll('.cp-meta, .time, time').forEach(n => n.remove());
  navigator.clipboard.writeText(clone.innerText.trim())
    .then(() => toast_('✅ Copied to clipboard'))
    .catch(()  => toast_('Copy failed'));
});

/* Forward */
document.getElementById('cpCtxForward').addEventListener('click', () => {
  ctx.classList.remove('on');
  if (ctxMsgId) window.location.href = 'forward.php?message_id=' + ctxMsgId;
});

/* Delete for Me */
document.getElementById('cpCtxDelMe').addEventListener('click', () => {
  ctx.classList.remove('on');
  if (!ctxMsgId) return;
  performDelete(ctxMsgId, 'me');
});

/* Delete for Everyone */
document.getElementById('cpCtxDelAll').addEventListener('click', () => {
  ctx.classList.remove('on');
  if (!ctxMsgId) return;
  confirmOpen = true;
  const yes = confirm('Delete this message for everyone? This cannot be undone.');
  setTimeout(() => { confirmOpen = false; }, 300);
  if (!yes) return;
  performDelete(ctxMsgId, 'all');
});

/*
 * ── FIX 2 CORE: performDelete ──
 * 1. Calls delete_message.php on the server (hard or soft delete)
 * 2. On success: replaces the bubble with a styled "deleted" notice
 *    instead of fully removing the element (keeps the row visible)
 * 3. Shows a toast confirming the action
 */
function performDelete(msgId, scope) {
  fetch('delete_message.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `message_id=${encodeURIComponent(msgId)}&scope=${encodeURIComponent(scope)}`
  })
  .then(r => r.text())
  .then(raw => {
    let d;
    try { d = JSON.parse(raw); }
    catch(e) { toast_('Server error'); console.error('delete_message.php returned non-JSON:', raw); return; }

    if (d.status === 'success') {
      // Replace bubble content with a deleted notice
      const el  = msgs.querySelector(`[data-msg-id="${msgId}"], [data-message-id="${msgId}"]`);
      const bub = el?.querySelector('.cp-bub, .message-bubble');
      if (bub) {
        const label = scope === 'all'
          ? '🗑️ You deleted this message for everyone'
          : '🗑️ You deleted this message';
        bub.innerHTML = `<span class="cp-deleted-notice"><span class="cp-del-icon">🗑️</span>${scope === 'all' ? 'You deleted this message for everyone' : 'You deleted this message'}</span>`;
        bub.style.background = 'transparent';
        bub.style.padding    = '0';
      }
      // Remove any context-menu click target data so it can't be re-deleted
      if (el) {
        el.removeAttribute('data-msg-id');
        el.removeAttribute('data-message-id');
      }
      const toastMsg = scope === 'all'
        ? '✅ Message deleted for everyone'
        : '✅ Message deleted for you';
      toast_(toastMsg);
    } else {
      toast_('❌ ' + (d.msg || d.message || 'Could not delete message'));
    }
  })
  .catch(() => toast_('Network error'));
}

/* ── Post card ── */
msgs.addEventListener('click',e=>{const c=e.target.closest('.cp-postcard');if(c)window.location.href='view_post.php?post_id='+c.dataset.postId;});

/* ── Toast ── */
let toastT;
function toast_(msg){toast.textContent=msg;toast.style.opacity='1';clearTimeout(toastT);toastT=setTimeout(()=>{toast.style.opacity='0';},2600);}

})();
</script>