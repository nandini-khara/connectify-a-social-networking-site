<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify - Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
    }

    body {
      background: linear-gradient(to right, #a18cd1, #fbc2eb);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      background: white;
      width: 360px;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
      text-align: center;
    }

    .app-name {
      font-size: 2rem;
      font-weight: 700;
      color: #6a1b9a;
      margin-bottom: 0.5rem;
    }

    .container h2 {
      margin-bottom: 1.5rem;
      color: #444;
    }

    .input-group {
      margin-bottom: 1.2rem;
      text-align: left;
    }

    .input-group label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: 600;
      color: #555;
    }

    .input-group input[type="text"],
    .input-group input[type="password"],
    .input-group input[type="email"] {
      width: 100%;
      padding: 0.6rem 0.8rem;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 1rem;
    }

    .password-wrapper {
      position: relative;
    }

    .password-wrapper input[type="checkbox"] {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
    }

    .show-password {
      text-align: right;
      font-size: 0.75rem;
      margin-top: 0.3rem;
    }

    .forgot-password {
      text-align: right;
      margin-top: -0.5rem;
      margin-bottom: 1rem;
      font-size: 0.85rem;
    }

    .forgot-password a {
      color: #6a1b9a;
      text-decoration: none;
      font-weight: 600;
    }

    .forgot-password a:hover {
      text-decoration: underline;
    }

    .btn {
      background: linear-gradient(to right, #9D50BB, #6E48AA);
      color: white;
      border: none;
      padding: 0.75rem;
      border-radius: 10px;
      width: 100%;
      cursor: pointer;
      font-weight: bold;
      font-size: 1rem;
      transition: background 0.3s ease;
    }

    .btn:hover {
      background: linear-gradient(to right, #6E48AA, #9D50BB);
    }

    .toggle-link {
      margin-top: 1.5rem;
      font-size: 0.9rem;
    }

    .toggle-link a {
      color: #6a1b9a;
      text-decoration: none;
      font-weight: 600;
    }

    .toggle-link a:hover {
      text-decoration: underline;
    }

    .error {
      color: red;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="app-name">Connectify</div>
    <h2>Welcome</h2>

    <!-- Error Message -->
    <?php if (isset($_GET['error'])): ?>
      <div class="error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <form id="auth-form" action="login.php" method="POST">
      <div class="input-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required />
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Enter your password" required />
          <input type="checkbox" id="togglePassword" />
        </div>
        <div class="show-password">Show Password</div>
      </div>

      <div class="forgot-password">
        <a href="forgetpassword.php">Forgot Password?</a>
      </div>

      <button type="submit" class="btn">Login</button>
    </form>

    <div class="toggle-link">
      Don't have an account? <a href="sign_uppage.php">Sign Up</a>
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('change', () => {
      passwordInput.type = togglePassword.checked ? 'text' : 'password';
    });

    // Remove the error query parameter after displaying it
    if (window.location.search.includes('error=')) {
      const url = new URL(window.location.href);
      url.searchParams.delete('error');
      window.history.replaceState({}, document.title, url.pathname);
    }
  </script>
</body>
</html>