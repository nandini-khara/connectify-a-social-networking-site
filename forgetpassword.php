<?php
session_start();
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']); // clear error after showing it
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify - Forgot Password</title>
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
      text-align: center;
    }

    .container {
      background: white;
      width: 400px;
      padding: 2rem;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }

    .app-name {
      font-size: 2rem;
      font-weight: 700;
      color: #6a1b9a;
      margin-bottom: 1rem;
    }

    .container h2 {
      margin-bottom: 1.5rem;
      color: #444;
    }

    .input-group {
      margin-bottom: 1rem;
      text-align: left;
    }

    .input-group label {
      display: block;
      margin-bottom: 0.25rem;
      font-weight: 600;
    }

    .input-group input[type="text"] {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 10px;
    }

    .btn {
      background-color: #6a1b9a;
      color: white;
      border: none;
      padding: 0.75rem;
      border-radius: 10px;
      width: 100%;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s ease;
      margin-top: 1rem;
    }

    .btn:hover {
      background-color: #4a0072;
    }

    .error {
      color: red;
      font-size: 0.9rem;
      margin-top: 1rem;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="app-name">Connectify</div>
    <h2>Forgot Your Password?</h2>
    <form method="POST" action="sendcode.php">
      <div class="input-group">
        <label for="identifier">Email</label>
        <input type="text" name="identifier" id="identifier" placeholder="Enter your email" required />
      </div>

      <button type="submit" class="btn">Send OTP</button>
    </form>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
  </div>

</body>
</html>
