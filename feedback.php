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
  <title>Feedback - Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
    body { background: linear-gradient(to right, #e1bee7, #f3e5f5); padding: 2rem; }
    .container { max-width: 700px; margin: 0 auto; background-color: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h2 { margin-bottom: 1rem; color: #6a1b9a; font-size: 1.8rem; }
    form { display: flex; flex-direction: column; gap: 1rem; }
    label { font-weight: 600; color: #6a1b9a; }
    textarea, input[type="text"] { padding: 0.75rem; border: 1px solid #ce93d8; border-radius: 8px; background-color: #f3e5f5; }
    button { background-color: #6a1b9a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
    button:hover { background-color: #4a148c; }
    .back-link { display: inline-block; margin-top: 1rem; text-decoration: none; color: #6a1b9a; font-weight: 500; }
  </style>
</head>
<body>
  <div class="container">
    <h2>We’d love your Feedback</h2>
    <form action="feedback_backend.php" method="POST">
      <label for="subject">Subject</label>
      <input type="text" name="subject" id="subject" required>
      
      <label for="message">Your Feedback</label>
      <textarea name="message" id="message" rows="6" required></textarea>
      
      <button type="submit">Submit Feedback</button>
    </form>
    <a class="back-link" href="settings_frontend.php">← Back to Settings</a>
  </div>
</body>
</html>
