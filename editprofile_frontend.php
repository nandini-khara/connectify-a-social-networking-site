<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require 'connect.php';
$user_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: die("User not found");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile | Connectify</title>

<!-- Bootstrap Icons (for the tiny × glyphs) -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
/* ----------- base layout ----------- */
*{box-sizing:border-box;font-family:'Inter',sans-serif;margin:0;padding:0;}
body{
    background:linear-gradient(135deg,#a18cd1,#fbc2eb);
    display:flex;
    justify-content:center;
    align-items:flex-start;          /* no vertical‑centering => top never cuts off */
    min-height:100vh;
    padding-top:60px;                /* space for the heading */
}
.edit-profile-container{
    background:#fff;
    padding:30px 40px 40px;
    border-radius:20px;
    width:100%;max-width:480px;
    box-shadow:0 20px 40px rgba(0,0,0,.1);
    position:relative;
    margin-bottom:60px;              /* so last buttons don’t stick to bottom edge */
}
.header{text-align:center;margin-bottom:20px;}
h2{font-size:26px;color:#6a1b9a;margin-bottom:20px;}

/* ----------- image area ----------- */
.image-wrapper{
    position:relative;               /* lets us absolutely‑position delete buttons */
    text-align:center;
    margin-bottom:20px;
    min-height:190px;                /* enough room for bg + avatar overlap */
}
.bg-pic-preview{
    width:100%;height:150px;
    object-fit:cover;border-radius:15px;cursor:pointer;
}
.profile-pic-preview{
    width:120px;height:120px;border-radius:50%;
    object-fit:cover;border:4px solid #9b59b6;background:#fff;
    position:absolute;
    bottom:-40px;left:50%;transform:translateX(-50%);
    box-shadow:0 4px 15px rgba(0,0,0,.1);cursor:pointer;
}

/* ----------- delete icons ----------- */
.delete-icon{
    position:absolute;               /* sit ON TOP of the image */
    background:#fff;border-radius:50%;
    padding:2px;font-size:22px;line-height:1;
    color:#6a1b9a;cursor:pointer;transition:color .2s;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
}
.delete-icon:hover{color:#4a1369;}

/* specific positions */
#delBg   {bottom:10px;right:10px;}                   /* bg image */
#delProf {bottom:-12px;left:50%;transform:translate(38px, 0);} /* avatar */

/* ----------- buttons + fields ----------- */
.change-pic-btn{
    display:inline-block;margin-top:40px;padding:6px 14px;
    background:#9b59b6;color:#fff;border:none;border-radius:8px;
    font-size:14px;cursor:pointer;transition:background .3s;
}
.change-pic-btn:hover{background:#8e44ad;}

input[type=file]{display:none;}
form{margin-top:30px;}
label{font-weight:600;display:block;margin-bottom:5px;margin-top:15px;color:#5e3584;}
input[type=text],textarea{
    width:100%;padding:12px;border:1px solid #d3bce9;border-radius:10px;
    margin-top:4px;background:#f9f5ff;transition:border-color .3s;
}
input[type=text]:focus,textarea:focus{border-color:#9b59b6;outline:none;background:#fff;}
textarea{resize:none;}
.buttons{display:flex;justify-content:space-between;gap:10px;margin-top:30px;}
button{flex:1;padding:12px;font-size:16px;border:none;border-radius:10px;cursor:pointer;transition:background .3s;}
.save-btn{background:linear-gradient(to right,#a18cd1,#6a1b9a);color:#fff;}
.save-btn:hover{background:linear-gradient(to right,#6a1b9a,#8e44ad);}
.cancel-btn{background:#f2e9fb;color:#6a1b9a;}
.cancel-btn:hover{background:#e0d3f3;}

/* ----------- lightbox modal ----------- */
.modal{display:none;position:fixed;z-index:1000;
       left:0;top:0;width:100%;height:100%;overflow:auto;
       background:rgba(0,0,0,.6);}
.modal-content{margin:5% auto;display:block;max-width:90%;max-height:80vh;
               border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.close{position:absolute;top:20px;right:35px;color:#fff;font-size:30px;
       font-weight:bold;cursor:pointer;z-index:1001;}
</style>
</head>
<body>

<div class="edit-profile-container">
  <div class="header"><h2>Edit Profile</h2></div>

  <form action="update_profile.php" method="POST" enctype="multipart/form-data">
    <!-- ---------- IMAGES & DELETE ICONS ---------- -->
    <div class="image-wrapper">
      <!-- background -->
      <img src="<?= !empty($user['background_image']) ? htmlspecialchars($user['background_image']) : ''; ?>"
           alt="Background Preview" class="bg-pic-preview" id="bgPreview"
           style="<?= !empty($user['background_image']) ? 'display:block' : 'display:none'; ?>"
           onclick="openModal(this.src)">

      <!-- profile picture -->
      <img src="<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default-profile.png'; ?>"
           alt="Profile Picture" class="profile-pic-preview" id="previewImg"
           onclick="openModal(this.src)">
      
      <!-- delete buttons (overlay) -->
      <i class="bi bi-x-circle delete-icon" id="delBg"
         title="Remove background image"
         onclick="deleteImage('bgPreview','delete_background_image','')"></i>
      
      <i class="bi bi-x-circle delete-icon" id="delProf"
         title="Remove profile picture"
         onclick="deleteImage('previewImg','delete_profile_image','default-profile.png')"></i>
    </div>

    <!-- upload triggers -->
    <label class="change-pic-btn" for="profile-pic">Change Profile Picture</label>
    <input type="file" id="profile-pic" name="profile_image" accept="image/*"
           onchange="previewImage(event, 'previewImg')">
    <input type="hidden" name="delete_profile_image" id="delete_profile_image" value="0">

    <label class="change-pic-btn" for="bg-pic">Change Background Image</label>
    <input type="file" id="bg-pic" name="background_image" accept="image/*"
           onchange="previewImage(event, 'bgPreview')">
    <input type="hidden" name="delete_background_image" id="delete_background_image" value="0">

    <!-- ---------- TEXT FIELDS ---------- -->
    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name"
           value="<?= htmlspecialchars($user['full_name'] ?? ''); ?>" required>

    <label for="user_name">Username</label>
    <input type="text" id="user_name" name="user_name"
           value="<?= htmlspecialchars($user['user_name'] ?? ''); ?>" required>

    <label for="phone_number">Phone Number</label>
    <input type="text" id="phone_number" name="phone_number"
           value="<?= htmlspecialchars($user['phone_number'] ?? ''); ?>" required>

    <label for="bio">Bio</label>
    <textarea id="bio" name="Bio" rows="4"><?= htmlspecialchars($user['Bio'] ?? ''); ?></textarea>

    <div class="buttons">
      <button type="submit" class="save-btn">Save Changes</button>
      <button type="button" class="cancel-btn"
              onclick="window.location.href='myprofile_frontend.php';">Cancel</button>
    </div>
  </form>
</div>

<!-- ---------- Image modal ---------- -->
<div id="imgModal" class="modal" onclick="closeModal()">
  <span class="close" onclick="closeModal()">&times;</span>
  <img class="modal-content" id="modalImage">
</div>

<script>
/* preview chosen file */
function previewImage(event, imgId){
  const reader = new FileReader();
  reader.onload = () => {
      const img = document.getElementById(imgId);
      img.src = reader.result;
      /* show bg preview if hidden */
      if(imgId === 'bgPreview') img.style.display = 'block';
  };
  if(event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
}

/* simple lightbox */
function openModal(src){
  document.getElementById("imgModal").style.display = "block";
  document.getElementById("modalImage").src = src;
}
function closeModal(){document.getElementById("imgModal").style.display = "none";}

/* delete image handler */
function deleteImage(imgId, hiddenFieldId, fallbackSrc){
  const img = document.getElementById(imgId);
  if(imgId === 'bgPreview'){ img.style.display='none'; img.src=''; }
  else{ img.src = fallbackSrc; }
  document.getElementById(hiddenFieldId).value = "1";
  /* clear the file input too */
  if(imgId==='previewImg')  document.getElementById('profile-pic').value='';
  if(imgId==='bgPreview')   document.getElementById('bg-pic').value='';
}
</script>
</body>
</html>
