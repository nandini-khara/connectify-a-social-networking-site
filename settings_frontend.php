<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'connect.php';

$user_id = $_SESSION['user_id'];

$dark_mode = $is_private = $hide_last_seen = 0;
$dm_stmt = $con->prepare("SELECT dark_mode, is_private, hide_last_seen FROM users WHERE user_id = ?");
$dm_stmt->bind_param("i", $user_id);
$dm_stmt->execute();
$dm_row = $dm_stmt->get_result()->fetch_assoc();
$dm_stmt->close();
if ($dm_row) {
    $dark_mode      = (int)$dm_row['dark_mode'];
    $is_private     = (int)$dm_row['is_private'];
    $hide_last_seen = (int)$dm_row['hide_last_seen'];
}

$user_stmt = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
if (!$user) die("User not found");

$fb_stmt = $con->prepare("SELECT feedback_id FROM user_feedback WHERE user_id = ?");
$fb_stmt->bind_param("i", $user_id);
$fb_stmt->execute();
$fb_row = $fb_stmt->get_result()->fetch_assoc();
$fb_stmt->close();
$already_submitted = $fb_row ? true : false;

$dm = $dark_mode;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connectify Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;font-family:'Inter',sans-serif;margin:0;padding:0;}
    body{background-color:<?=$dm?'#111':'#f9f9f9'?>;padding:2rem;color:<?=$dm?'#eee':'#333'?>;}
    .settings-container{max-width:900px;margin:0 auto;background-color:<?=$dm?'#1e1e1e':'#fff'?>;padding:2rem;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,<?=$dm?'0.4':'0.05'?>);border:1px solid <?=$dm?'#2e2e2e':'transparent'?>;}
    h2{margin-bottom:1.5rem;color:<?=$dm?'#bb86fc':'#6a1b9a'?>;border-bottom:2px solid <?=$dm?'#2e2e2e':'#eee'?>;padding-bottom:.5rem;}
    .section{margin-bottom:2rem;}
    .section h3{color:<?=$dm?'#ddd':'#333'?>;margin-bottom:1rem;}
    .field{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid <?=$dm?'#2e2e2e':'#eee'?>;}
    .field label{color:<?=$dm?'#bbb':'#555'?>;font-weight:500;}
    .toggle{display:flex;align-items:center;gap:.5rem;}
    .action-btn{background-color:#6a1b9a;color:white;border:none;padding:.5rem 1rem;border-radius:8px;cursor:pointer;transition:background .2s;}
    .action-btn:hover{background-color:#5e1690;}
    .danger-btn{background-color:#e53935;}
    .danger-btn:hover{background-color:#c62828;}
    .info-box{background:<?=$dm?'#2a2a2a':'#f1f1f1'?>;padding:.75rem;border-radius:8px;margin-top:.5rem;font-size:.9rem;color:<?=$dm?'#aaa':'#444'?>;border:1px solid <?=$dm?'#3a3a3a':'transparent'?>;}
    .link-btn{text-decoration:none;background-color:#6a1b9a;color:white;padding:.5rem 1rem;border-radius:8px;font-weight:500;transition:background .2s;display:inline-block;}
    .link-btn:hover{background-color:#5e1690;}
    .switch{position:relative;display:inline-block;width:46px;height:24px;}
    .switch input{opacity:0;width:0;height:0;}
    .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:<?=$dm?'#555':'#ccc'?>;transition:.4s;border-radius:34px;}
    .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background-color:white;transition:.4s;border-radius:50%;}
    .switch input:checked+.slider{background-color:#6a1b9a;}
    .switch input:checked+.slider:before{transform:translateX(22px);}
    .privacy-badge{display:inline-block;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px;vertical-align:middle;}
    .privacy-badge.public{background:rgba(45,200,100,.15);color:#2dc864;border:1px solid rgba(45,200,100,.4);}
    .privacy-badge.private{background:rgba(155,93,229,.15);color:#9b5de5;border:1px solid rgba(155,93,229,.4);}
    .field-sub{font-size:.78rem;color:<?=$dm?'#777':'#999'?>;margin-top:3px;}
    .saving-indicator{font-size:.78rem;color:#9b5de5;display:none;margin-left:8px;}

    /* ── Modal ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.active{display:flex;}
    .modal{background:<?=$dm?'#1e1e1e':'#fff'?>;border:1px solid <?=$dm?'#333':'#e0e0e0'?>;border-radius:16px;padding:2rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:slideUp .25s ease;position:relative;}
    @keyframes slideUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
    .modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.3rem;cursor:pointer;color:<?=$dm?'#777':'#999'?>;transition:color .2s;}
    .modal-close:hover{color:<?=$dm?'#eee':'#333'?>;}
    .modal h3{color:<?=$dm?'#bb86fc':'#6a1b9a'?>;margin-bottom:.35rem;font-size:1.1rem;}
    .modal p.modal-sub{font-size:.83rem;color:#888;margin-bottom:1.4rem;}
    .star-row{display:flex;gap:6px;margin-bottom:1.2rem;}
    .star{font-size:1.8rem;cursor:pointer;color:<?=$dm?'#444':'#ddd'?>;transition:color .15s,transform .15s;user-select:none;}
    .star.lit{color:#f5c518;}
    .star:hover{transform:scale(1.15);}
    .reaction-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;}
    .reaction-chip{background:<?=$dm?'#2a2a2a':'#f5f5f5'?>;border:1px solid <?=$dm?'#3a3a3a':'#e0e0e0'?>;border-radius:20px;padding:5px 14px;font-size:.82rem;cursor:pointer;transition:all .15s;color:<?=$dm?'#bbb':'#555'?>;}
    .reaction-chip:hover,.reaction-chip.selected{background:rgba(106,27,154,.15);border-color:#6a1b9a;color:#9b5de5;font-weight:600;}
    .modal-form-group{margin-bottom:1rem;}
    .modal-form-group label{display:block;font-size:.8rem;font-weight:600;color:<?=$dm?'#bbb':'#555'?>;margin-bottom:.4rem;}
    .modal-form-group textarea,.modal-form-group input,.modal-form-group select{width:100%;background:<?=$dm?'#2a2a2a':'#f9f9f9'?>;border:1px solid <?=$dm?'#3a3a3a':'#ddd'?>;border-radius:8px;padding:.6rem .8rem;font-size:.88rem;font-family:'Inter',sans-serif;color:<?=$dm?'#eee':'#333'?>;outline:none;resize:vertical;transition:border-color .2s;}
    .modal-form-group textarea:focus,.modal-form-group input:focus,.modal-form-group select:focus{border-color:#6a1b9a;}
    .modal-form-group textarea{min-height:90px;}
    .modal-form-group select option{background:<?=$dm?'#2a2a2a':'#fff'?>;}
    .char-counter{font-size:.72rem;color:<?=$dm?'#666':'#bbb'?>;text-align:right;margin-top:3px;}
    .modal-submit{width:100%;padding:.7rem;background:linear-gradient(135deg,#6a1b9a,#9b5de5);color:white;border:none;border-radius:10px;font-size:.95rem;font-weight:600;cursor:pointer;transition:opacity .2s,transform .1s;margin-top:.5rem;}
    .modal-submit:hover{opacity:.9;}
    .modal-submit:active{transform:scale(.98);}
    .modal-submit:disabled{opacity:.5;cursor:not-allowed;}
    .modal-success{display:none;text-align:center;padding:1.5rem 0 .5rem;}
    .modal-success .success-icon{font-size:3rem;margin-bottom:.75rem;animation:pop .4s cubic-bezier(.34,1.56,.64,1) both;}
    @keyframes pop{from{transform:scale(0);}to{transform:scale(1);}}
    .modal-success h4{color:<?=$dm?'#bb86fc':'#6a1b9a'?>;font-size:1.1rem;margin-bottom:.4rem;}
    .modal-success p{font-size:.85rem;color:#888;}
    .already-notice{background:rgba(106,27,154,.1);border:1px solid rgba(106,27,154,.3);border-radius:8px;padding:.7rem 1rem;font-size:.83rem;color:#9b5de5;text-align:center;}
    .modal-divider{border:none;border-top:1px solid <?=$dm?'#2e2e2e':'#eee'?>;margin:1.1rem 0;}
  </style>
</head>
<body>
<div class="settings-container">
  <h2>Settings</h2>

  <!-- Profile -->
  <div class="section">
    <h3>👤 Profile Settings</h3>
    <div class="field">
      <label>Edit Profile</label>
      <a href="editprofile_frontend.php" class="link-btn">Edit</a>
    </div>
  </div>

  <!-- Activity -->
  <div class="section">
    <h3>📜 User Activity</h3>
    <div class="field">
      <label>Saved Posts</label>
      <button class="action-btn" onclick="location.href='saved_posts.php'">View</button>
    </div>
    <div class="field">
      <label>Your Comments</label>
      <button class="action-btn" onclick="location.href='my_comments.php'">View</button>
    </div>
    <div class="field">
      <label>Liked Posts</label>
      <button class="action-btn" onclick="location.href='liked_posts.php'">View</button>
    </div>
  </div>

  <!-- Privacy & Security -->
  <div class="section">
    <h3>🔐 Privacy &amp; Security</h3>
    <div class="field">
      <label>Block/Unblock Users</label>
      <button class="action-btn" onclick="location.href='blocked_users.php'">Manage</button>
    </div>
    <div class="field">
      <label>Change Password</label>
      <a href="changepassword.php" class="link-btn">Change</a>
    </div>
  </div>

  <!-- Theme -->
  <div class="section">
    <h3>🎨 Theme Preferences</h3>
    <div class="field toggle">
      <label for="themeToggle">Dark Mode</label>
      <label class="switch">
        <input type="checkbox" id="themeToggle" <?=$dm?'checked':''?>>
        <span class="slider"></span>
      </label>
    </div>
  </div>

  <!-- Account Privacy -->
  <div class="section">
    <h3>🔒 Account Privacy</h3>
    <div class="field toggle" style="flex-wrap:wrap;gap:6px;">
      <div>
        <label for="privacyToggle">
          Private Account
          <span class="privacy-badge <?=$is_private?'private':'public'?>" id="privacyBadge">
            <?=$is_private?'🔒 Private':'🌐 Public'?>
          </span>
          <span class="saving-indicator" id="privacySaving">saving…</span>
        </label>
        <div class="field-sub" id="privacyDesc">
          <?=$is_private?'Only your mutual followers can see your profile and posts.':'Everyone can see your profile and posts.'?>
        </div>
      </div>
      <label class="switch">
        <input type="checkbox" id="privacyToggle" <?=$is_private?'checked':''?>>
        <span class="slider"></span>
      </label>
    </div>
    <div class="info-box" style="margin-top:10px;">
      <strong>🌐 Public:</strong> Anyone on Connectify can view your profile, posts, followers and following list.<br><br>
      <strong>🔒 Private:</strong> Only mutual followers can see your profile and posts.
    </div>
  </div>

  <!-- Last Seen -->
  <div class="section">
    <h3>👁️ Last Seen &amp; Online Status</h3>
    <div class="field toggle" style="flex-wrap:wrap;gap:6px;">
      <div>
        <label for="lastSeenToggle">
          Hide Last Seen
          <span class="saving-indicator" id="lastSeenSaving">saving…</span>
        </label>
        <div class="field-sub" id="lastSeenDesc">
          <?=$hide_last_seen?'Nobody can see when you were last online.':'Mutual followers can see your last seen time.'?>
        </div>
      </div>
      <label class="switch">
        <input type="checkbox" id="lastSeenToggle" <?=$hide_last_seen?'checked':''?>>
        <span class="slider"></span>
      </label>
    </div>
    <div class="info-box" style="margin-top:10px;">
      When enabled, nobody can see your last seen or online status.
    </div>
  </div>

  <!-- ════ FEEDBACK & REPORT ════ -->
  <div class="section">
    <h3>💬 Feedback &amp; Support</h3>

    <div class="field">
      <div>
        <label>Rate Connectify</label>
        <div class="field-sub">
          <?=$already_submitted?'You\'ve already shared your feedback — thank you!':'Star rating, quick reaction or written review (one per account).'?>
        </div>
      </div>
      <button class="action-btn" onclick="openFeedback()" <?=$already_submitted?'disabled title="Already submitted"':''?>>
        <?=$already_submitted?'✓ Done':'⭐ Rate'?>
      </button>
    </div>

    <div class="field">
      <div>
        <label>Report a Problem</label>
        <div class="field-sub">Found a bug or something broken? Let us know.</div>
      </div>
      <button class="action-btn" onclick="openReport()">🐛 Report</button>
    </div>

    <div class="info-box">
      Our team reviews all reports. For urgent issues please use the Report option above.
    </div>
  </div>

  <!-- Account Control -->
  <div class="section">
    <h3>⚙️ Account Control</h3>
    <div class="field">
      <label>Delete Account</label>
      <button class="action-btn danger-btn" onclick="location.href='delete_frontend.php'">Delete</button>
    </div>
    <div class="field">
      <label>Logout</label>
      <button class="action-btn" onclick="location.href='logout_fe.php'">Logout</button>
    </div>
    <div class="info-box">Deleting your account is permanent and cannot be undone.</div>
  </div>
</div>

<!-- ═══ MODAL: FEEDBACK ═══ -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('feedbackModal')">✕</button>
    <?php if ($already_submitted): ?>
      <h3>⭐ Rate Connectify</h3>
      <div class="already-notice" style="margin-top:.5rem;">
        You've already submitted your feedback. Thank you for helping us improve Connectify! 💜
      </div>
    <?php else: ?>
      <h3>⭐ Rate Connectify</h3>
      <p class="modal-sub">One submission per account. Your honest feedback shapes what we build.</p>
      <div id="feedbackFormArea">
        <div class="modal-form-group">
          <label>Overall rating</label>
          <div class="star-row" id="starRow">
            <span class="star" data-v="1">★</span>
            <span class="star" data-v="2">★</span>
            <span class="star" data-v="3">★</span>
            <span class="star" data-v="4">★</span>
            <span class="star" data-v="5">★</span>
          </div>
          <input type="hidden" id="starValue" value="0">
        </div>
        <div class="modal-form-group">
          <label>Quick reaction <span style="font-weight:400;opacity:.7">(pick any)</span></label>
          <div class="reaction-row">
            <span class="reaction-chip" data-r="🔥 Love it">🔥 Love it</span>
            <span class="reaction-chip" data-r="⚡ Fast">⚡ Fast</span>
            <span class="reaction-chip" data-r="🎨 Great design">🎨 Great design</span>
            <span class="reaction-chip" data-r="🐌 Too slow">🐌 Too slow</span>
            <span class="reaction-chip" data-r="🤯 Needs work">🤯 Needs work</span>
            <span class="reaction-chip" data-r="💡 Needs features">💡 Needs features</span>
          </div>
          <input type="hidden" id="reactionValue" value="">
        </div>
        <div class="modal-form-group">
          <label>Write a review <span style="font-weight:400;opacity:.7">(optional)</span></label>
          <textarea id="feedbackText" maxlength="500" placeholder="What do you love? What would you change?"></textarea>
          <div class="char-counter"><span id="fbCharCount">0</span> / 500</div>
        </div>
        <button class="modal-submit" id="feedbackSubmitBtn" onclick="submitFeedback()">Submit feedback</button>
      </div>
      <div class="modal-success" id="feedbackSuccess">
        <div class="success-icon">🎉</div>
        <h4>Thank you!</h4>
        <p>Your feedback has been received and will help shape Connectify.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ MODAL: REPORT A PROBLEM ═══ -->
<div class="modal-overlay" id="reportModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('reportModal')">✕</button>
    <h3>🐛 Report a Problem</h3>
    <p class="modal-sub">Describe what went wrong and we'll look into it quickly.</p>
    <div id="reportFormArea">
      <div class="modal-form-group">
        <label>Problem category</label>
        <select id="reportCategory">
          <option value="" disabled selected>Select a category…</option>
          <option>Bug / error</option>
          <option>Performance issue</option>
          <option>Login / account access</option>
          <option>Messages not sending</option>
          <option>Notifications broken</option>
          <option>Content not loading</option>
          <option>Other</option>
        </select>
      </div>
      <div class="modal-form-group">
        <label>Describe the problem</label>
        <textarea id="reportText" maxlength="800" placeholder="What happened? What were you doing when it occurred?"></textarea>
        <div class="char-counter"><span id="rpCharCount">0</span> / 800</div>
      </div>
      <div class="modal-form-group">
        <label>Steps to reproduce <span style="font-weight:400;opacity:.7">(optional)</span></label>
        <textarea id="reportSteps" maxlength="400" style="min-height:60px;" placeholder="1. Go to…  2. Click…  3. See error…"></textarea>
      </div>
      <button class="modal-submit" id="reportSubmitBtn" onclick="submitReport()">Send report</button>
    </div>
    <div class="modal-success" id="reportSuccess">
      <div class="success-icon">✅</div>
      <h4>Report received!</h4>
      <p>We'll investigate and fix it as soon as possible.</p>
    </div>
  </div>
</div>

<script>
  /* Dark mode */
  document.getElementById('themeToggle').addEventListener('change', function(){
    fetch('darkmode_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'dark_mode='+(this.checked?1:0)}).then(()=>location.reload());
  });

  /* Last seen */
  document.getElementById('lastSeenToggle').addEventListener('change', function(){
    const hide=this.checked?1:0, desc=document.getElementById('lastSeenDesc'), saving=document.getElementById('lastSeenSaving');
    saving.style.display='inline';
    fetch('privacy_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'hide_last_seen='+hide})
    .then(r=>r.json()).then(d=>{saving.style.display='none';if(d.status==='success'){desc.textContent=hide?'Nobody can see when you were last online.':'Mutual followers can see your last seen time.';}else{alert('Could not save.');this.checked=!hide;}})
    .catch(()=>{saving.style.display='none';alert('Network error.');this.checked=!hide;});
  });

  /* Privacy */
  document.getElementById('privacyToggle').addEventListener('change', function(){
    const p=this.checked?1:0, badge=document.getElementById('privacyBadge'), desc=document.getElementById('privacyDesc'), saving=document.getElementById('privacySaving');
    saving.style.display='inline';
    fetch('privacy_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'is_private='+p})
    .then(r=>r.json()).then(d=>{saving.style.display='none';if(d.status==='success'){if(p){badge.textContent='🔒 Private';badge.className='privacy-badge private';desc.textContent='Only your mutual followers can see your profile and posts.';}else{badge.textContent='🌐 Public';badge.className='privacy-badge public';desc.textContent='Everyone can see your profile and posts.';}}else{alert('Could not save.');this.checked=!this.checked;}})
    .catch(()=>{saving.style.display='none';alert('Network error.');this.checked=!p;});
  });

  /* Modals */
  function openFeedback(){ document.getElementById('feedbackModal').classList.add('active'); }
  function openReport()  { document.getElementById('reportModal').classList.add('active');   }
  function closeModal(id){ document.getElementById(id).classList.remove('active'); }
  document.querySelectorAll('.modal-overlay').forEach(ov=>{
    ov.addEventListener('click',e=>{ if(e.target===ov) ov.classList.remove('active'); });
  });

  /* Stars */
  let rating=0;
  document.querySelectorAll('.star').forEach(s=>{
    s.addEventListener('mouseenter',()=>{ const v=+s.dataset.v; document.querySelectorAll('.star').forEach(x=>x.classList.toggle('lit',+x.dataset.v<=v)); });
    s.addEventListener('click',()=>{ rating=+s.dataset.v; document.getElementById('starValue').value=rating; });
    s.addEventListener('mouseleave',()=>{ document.querySelectorAll('.star').forEach(x=>x.classList.toggle('lit',+x.dataset.v<=rating)); });
  });

  /* Reaction chips */
  document.querySelectorAll('.reaction-chip').forEach(c=>{
    c.addEventListener('click',()=>{
      c.classList.toggle('selected');
      document.getElementById('reactionValue').value=[...document.querySelectorAll('.reaction-chip.selected')].map(x=>x.dataset.r).join(', ');
    });
  });

  /* Char counters */
  ['feedbackText:fbCharCount','reportText:rpCharCount'].forEach(pair=>{
    const [ta,cnt]=pair.split(':');
    const el=document.getElementById(ta); if(!el) return;
    el.addEventListener('input',()=>document.getElementById(cnt).textContent=el.value.length);
  });

  /* Submit feedback */
  function submitFeedback(){
    const rating=document.getElementById('starValue').value;
    const reaction=document.getElementById('reactionValue').value;
    const text=document.getElementById('feedbackText').value.trim();
    if(rating==='0'&&!reaction&&!text){ alert('Please give a rating, pick a reaction, or write something!'); return; }
    const btn=document.getElementById('feedbackSubmitBtn');
    btn.disabled=true; btn.textContent='Sending…';
    fetch('feedback_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'rating='+encodeURIComponent(rating)+'&reaction='+encodeURIComponent(reaction)+'&text='+encodeURIComponent(text)})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){document.getElementById('feedbackFormArea').style.display='none';document.getElementById('feedbackSuccess').style.display='block';}
      else{alert(d.message||'Could not submit. Please try again.');btn.disabled=false;btn.textContent='Submit feedback';}
    }).catch(()=>{alert('Network error.');btn.disabled=false;btn.textContent='Submit feedback';});
  }

  /* Submit report */
  function submitReport(){
    const category=document.getElementById('reportCategory').value;
    const text=document.getElementById('reportText').value.trim();
    const steps=document.getElementById('reportSteps').value.trim();
    if(!category){ alert('Please select a category.'); return; }
    if(!text){ alert('Please describe the problem.'); return; }
    const btn=document.getElementById('reportSubmitBtn');
    btn.disabled=true; btn.textContent='Sending…';
    fetch('report_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'category='+encodeURIComponent(category)+'&text='+encodeURIComponent(text)+'&steps='+encodeURIComponent(steps)})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){document.getElementById('reportFormArea').style.display='none';document.getElementById('reportSuccess').style.display='block';}
      else{alert(d.message||'Could not send. Please try again.');btn.disabled=false;btn.textContent='Send report';}
    }).catch(()=>{alert('Network error.');btn.disabled=false;btn.textContent='Send report';});
  }
</script>
</body>
</html>