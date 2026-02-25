<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Account Deleted - Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body {
      background: linear-gradient(to right, #ce93d8, #f3e5f5);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      padding: 1rem;
    }
    .message-box {
      background-color: white;
      padding: 2.5rem;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      text-align: center;
      max-width: 520px;
    }
    h1 {
      color: #6a1b9a;
      margin-bottom: 1rem;
      font-size: 2rem;
    }
    p {
      color: #5d4037;
      font-size: 1rem;
      margin-bottom: 2rem;
    }
    .button-group {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    a.button {
      text-decoration: none;
      color: white;
      background-color: #ab47bc;
      padding: 0.8rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      transition: background-color 0.3s ease;
    }
    a.button:hover {
      background-color: #8e24aa;
    }
  </style>
</head>
<body>
  <div class="message-box">
    <h1>Your account has been deleted</h1>
    <p>We're sorry to see you go. If you have feedback or need support, feel free to reach out.</p>
    <div class="button-group">
      <a href="sign_uppage.php" class="button">Create New Account</a>
      
    </div>
  </div>
</body>
</html>
