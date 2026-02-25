<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password - Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      margin: 0;
      background: linear-gradient(to right, #a18cd1, #fbc2eb);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      background: #fff;
      padding: 2rem;
      width: 350px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .brand-name {
      font-size: 1.8rem;
      font-weight: 700;
      color: #6a1b9a;
      margin-bottom: 0.5rem;
    }

    h2 {
      color: #444;
      margin-bottom: 1.5rem;
    }

    .input-group {
      position: relative;
      margin-bottom: 1.5rem;
      text-align: left;
    }

    label {
      display: block;
      margin-bottom: 6px;
      color: #444;
      font-weight: 600;
    }

    input[type="password"],
    input[type="text"] {
      width: 100%;
      padding: 10px 40px 10px 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 14px;
      transition: border 0.2s ease-in-out;
    }

    .btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(to right, #9D50BB, #6E48AA);
      color: white;
      font-size: 15px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.3s;
      margin-top: 0.5rem;
    }

    .btn:hover {
      background: linear-gradient(to right, #6E48AA, #9D50BB);
    }

    .message {
      color: red;
      font-size: 14px;
      margin-bottom: 15px;
      text-align: center;
    }

    .success {
      color: green;
    }

    .show-password {
      font-weight: normal;
      font-size: 0.9rem;
      margin-top: 0.5rem;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="brand-name">Connectify</div>
    <h2>Reset Your Password</h2>

    <?php if (isset($_SESSION['reset_error'])): ?>
      <div class="message"><?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="reset_password_backend.php" onsubmit="return checkPasswordsMatch();">
      <div class="input-group">
        <label for="new_password">New Password</label>
        <input type="password" name="new_password" id="new_password" required />
      </div>

      <div class="input-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required />
        <label class="show-password">
          <input type="checkbox" id="show-password" /> Show Password
        </label>
      </div>

      <div class="message" id="password-error" style="display: none;">Passwords do not match.</div>

      <button type="submit" class="btn">Reset Password</button>
    </form>
  </div>

  <script>
    // Password visibility toggle
    document.getElementById('show-password').addEventListener('change', function() {
      const pwdField = document.getElementById('new_password');
      const confirmPwdField = document.getElementById('confirm_password');
      const type = this.checked ? 'text' : 'password';
      pwdField.type = type;
      confirmPwdField.type = type;
    });

    // Check if passwords match before submitting
    function checkPasswordsMatch() {
      const pwd = document.getElementById('new_password').value;
      const confirmPwd = document.getElementById('confirm_password').value;
      const errorDiv = document.getElementById('password-error');

      if (pwd !== confirmPwd) {
        errorDiv.style.display = 'block';
        return false; // Prevent form submission
      } else {
        errorDiv.style.display = 'none';
        return true;
      }
    }
  </script>

</body>
</html>
