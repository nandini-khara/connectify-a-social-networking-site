<?php
// Example: delete_frontend.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Delete Account - Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
    body { background: linear-gradient(to right, #e1bee7, #f3e5f5); padding: 2rem; }
    .settings-container { max-width: 600px; margin: 0 auto; background-color: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h2 { margin-bottom: 1rem; color: #d32f2f; font-size: 1.8rem; border-bottom: 2px solid #f8bbd0; padding-bottom: 0.5rem; }
    p { margin-bottom: 1.5rem; color: #5d4037; font-size: 1rem; }
    form { display: flex; flex-direction: column; gap: 1rem; }
    .input-group { display: flex; flex-direction: column; }
    label { font-weight: 600; margin-bottom: 0.5rem; color: #6a1b9a; }
    input[type="password"] { padding: 0.5rem; border: 1px solid #ce93d8; border-radius: 8px; background-color: #f3e5f5; }
    .show-password { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.9rem; color: #555; }
    .btn-danger { background-color: #e53935; color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; transition: background-color 0.3s ease; }
    .btn-danger:hover { background-color: #c62828; }
    .btn-cancel { background-color: #ab47bc; color: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; text-align: center; text-decoration: none; display: inline-block; transition: background-color 0.3s ease; }
    .btn-cancel:hover { background-color: #8e24aa; }
    .btn-group { display: flex; justify-content: space-between; gap: 1rem; }
  </style>
</head>
<body>
  <div class="settings-container">
    <h2>Delete Your Account</h2>
    <p>Are you sure you want to delete your account? This action cannot be undone. Please confirm your password to proceed.</p>
    <form action="delete_backend.php" method="POST">
      <div class="input-group">
        <label for="password">Confirm Password</label>
        <input type="password" id="password" name="password" required>
        <div class="show-password">
          <input type="checkbox" id="togglePassword">
          <label for="togglePassword">Show Password</label>
        </div>
      </div>
      <div class="btn-group">
        <button type="submit" class="btn-danger">Delete My Account</button>
        <a href="settings_frontend.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    togglePassword.addEventListener('change', function () {
      passwordField.type = this.checked ? 'text' : 'password';
    });
  </script>
</body>
</html>
