<?php 
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
require 'connect.php';

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Connectify Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #f9f9f9;
      padding: 2rem;
    }

    .settings-container {
      max-width: 900px;
      margin: 0 auto;
      background-color: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    h2 {
      margin-bottom: 1.5rem;
      color: #6a1b9a;
      border-bottom: 2px solid #eee;
      padding-bottom: 0.5rem;
    }

    .section {
      margin-bottom: 2rem;
    }

    .section h3 {
      color: #333;
      margin-bottom: 1rem;
    }

    .field {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0;
      border-bottom: 1px solid #eee;
    }

    .field label {
      color: #555;
      font-weight: 500;
    }

    .field span {
      color: #222;
    }

    .toggle {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .toggle input[type="checkbox"] {
      transform: scale(1.3);
    }

    .action-btn {
      background-color: #6a1b9a;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 0.5rem;
    }

    .danger-btn {
      background-color: #e53935;
      color: white;
    }

    .info-box {
      background: #f1f1f1;
      padding: 0.75rem;
      border-radius: 8px;
      margin-top: 0.5rem;
      font-size: 0.9rem;
      color: #444;
    }

    .link-btn {
      text-decoration: none;
      background-color: #6a1b9a;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 500;
    }

    /* Toggle Switch Style */
    .switch {
      position: relative;
      display: inline-block;
      width: 46px;
      height: 24px;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0;
      right: 0; bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    .switch input:checked + .slider {
      background-color: #6a1b9a;
    }

    .switch input:checked + .slider:before {
      transform: translateX(22px);
    }
  </style>
 <!-- Remember to include getdark_mode.php before </head> -->
<?php include 'getdark_mode.php'; ?>

</head>
<body>

  <div class="settings-container">
    <h2>Settings</h2>

    <!-- Profile Settings -->
    <div class="section">
      <h3>👤 Profile Settings</h3>
      <div class="field">
        <label>Edit Profile</label>
        <a href="editprofile_frontend.php" class="link-btn">Edit</a>
      </div>
    </div>

    <!-- User Activity -->
    <div class="section">
      <h3>📜 User Activity</h3>
      
      <div class="field"><label>Saved</label><button class="action-btn" onclick="window.location.href='saved_posts.php'">View</button></div>
      <div class="field">
  <label>Your comments</label>
  <button class="action-btn" onclick="window.location.href='my_comments.php'">
    View
  </button>
</div>

      <div class="field">
  <label>Liked</label>
  <button class="action-btn" onclick="window.location.href='liked_posts.php'">
    View
  </button>
</div>

    </div>

    <!-- Privacy & Security -->
    <div class="section">
      <h3>🔐 Privacy & Security</h3>
      <div class="field toggle">
        
      </div>
      
      <div class="field">
  <label>Block/Unblock Users</label>
  <button class="action-btn" onclick="window.location.href='blocked_users.php'">
    Manage
  </button>
</div>

      <div class="field">
        <label>Change Password</label>
        <a href="changepassword.php" class="link-btn">Change</a>
      </div>
      
    </div>

    <!-- Theme Preferences -->
    <div class="section">
      <h3>🎨 Theme Preferences</h3>
      <div class="field toggle">
        <label for="themeToggle">Dark Mode</label>
        <label class="switch">
          <input type="checkbox" id="themeToggle" <?php echo ($user['dark_mode'] == 1) ? 'checked' : ''; ?>>
          <span class="slider"></span>
        </label>
      </div>
    </div>

    

    <!-- Account Control -->
    <div class="section">
      <h3>⚙️ Account Control</h3>
      <div class="field"><label>Delete Account</label><button class="action-btn" onclick="window.location.href='delete_frontend.php'">Delete</button></div>
      <div class="field"><label>Logout</label><button class="action-btn"onclick="window.location.href='logout_fe.php'">Logout</button></div>
      <div class="info-box">Deleting your account is permanent and cannot be undone.</div>
    </div>
  </div>

  <script>
  document.getElementById('themeToggle').addEventListener('change', function () {
    const isDarkMode = this.checked ? 1 : 0;

    fetch('darkmode_backend.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'dark_mode=' + isDarkMode
    }).then(() => {
      location.reload(); // reload to apply the stylesheet change
    });
  });
</script>

</body>
</html>
