<?php
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
  <title>Contact Us - Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
    body { background: linear-gradient(to right, #e1bee7, #f3e5f5); padding: 2rem; }
    .container { max-width: 700px; margin: 0 auto; background-color: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h2 { margin-bottom: 1rem; color: #6a1b9a; font-size: 1.8rem; }
    form { display: flex; flex-direction: column; gap: 1rem; }
    label { font-weight: 600; color: #6a1b9a; }
    input, textarea { padding: 0.75rem; border: 1px solid #ce93d8; border-radius: 8px; background-color: #f3e5f5; }
    button { background-color: #6a1b9a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
    button:hover { background-color: #4a148c; }
    .back-link { display: inline-block; margin-top: 1rem; text-decoration: none; color: #6a1b9a; font-weight: 500; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Contact Us</h2>
    <form action="contact_us_backend.php" method="POST">
      <label for="name">Your Name</label>
      <input type="text" id="name" name="name" required>

      <label for="email">Your Email</label>
      <input type="email" id="email" name="email" required>

      <label for="message">Message</label>
      <textarea id="message" name="message" rows="6" required></textarea>

      <button type="submit">Send Message</button>
    </form>
    <a class="back-link" href="settings_frontend.php">← Back to Settings</a>
  </div>
</body>
</html>
