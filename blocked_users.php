<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php"); exit();
}
require 'connect.php';
$uid = $_SESSION['user_id'];

/* all users I have blocked */
$sql = "
  SELECT u.user_id, u.full_name, u.profile_image
  FROM   blocks b
  JOIN   users  u ON u.user_id = b.blocked_id
  WHERE  b.blocker_id = ?
  ORDER  BY u.full_name
";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$blocked = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php include 'getdark_mode.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blocked Users | Connectify</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,sans-serif;background:#f9f9f9;padding:1.5rem}
h2{color:#6a1b9a;margin-bottom:1rem;font-weight:600}
.back-btn{display:inline-block;margin-bottom:1rem;background:#6a1b9a;color:#fff;
          padding:6px 14px;border-radius:22px;font-weight:500;text-decoration:none}
.back-btn:hover{transform:scale(1.05);}
.list{max-width:600px;margin:auto;display:flex;flex-direction:column;gap:12px}
.user-card{display:flex;align-items:center;gap:14px;background:#fff;padding:10px 14px;
           border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
.user-card img{width:46px;height:46px;border-radius:50%;object-fit:cover}
.user-card span{flex:1;font-weight:500;color:#333}
.unblock{background:#e53935;color:#fff;border:none;padding:6px 14px;border-radius:8px;
         cursor:pointer;font-weight:500}
.unblock:hover{opacity:.9}
</style>
</head>
<body>
<a href="settings_frontend.php" class="back-btn">← Settings</a>
<h2>Blocked users</h2>

<?php if (!$blocked): ?>
  <p>You haven’t blocked anyone.</p>
<?php else: ?>
  <div class="list">
    <?php foreach ($blocked as $u):
          $img = $u['profile_image'] ?: 'uploads/default-profile.png'; ?>
      <div class="user-card" id="u<?= $u['user_id'] ?>">
        <img src="<?= htmlspecialchars($img) ?>" alt="">
        <span><?= htmlspecialchars($u['full_name']) ?></span>
        <button class="unblock" data-id="<?= $u['user_id'] ?>">Unblock</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
document.querySelectorAll('.unblock').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const id = btn.dataset.id;
    fetch('block_action.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`target_user_id=${id}&action=unblock`
    })
    .then(r=>r.json())
    .then(data=>{
       if(data.status==='success'){
         document.getElementById('u'+id).remove();
         if(!document.querySelector('.user-card')) location.reload(); // list empty → show msg
       }else alert(data.message||'Failed');
    })
    .catch(()=>alert('Network error'));
  });
});
</script>
</body>
</html>
