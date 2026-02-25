<!-- logout_confirm.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Confirm Logout</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .confirm-box {
      background: white;
      padding: 2rem 2.5rem;
      border-radius: 20px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .confirm-box h1 {
      color: #6a1b9a;
      font-size: 1.8rem;
      margin-bottom: 1rem;
    }

    .confirm-box p {
      color: #555;
      font-size: 1rem;
      margin-bottom: 1.5rem;
    }

    .btn {
      padding: 0.7rem 1.4rem;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
      margin: 0 0.5rem;
    }

    .yes {
      background: linear-gradient(to right, #9D50BB, #6E48AA);
      color: white;
    }

    .yes:hover {
      background: linear-gradient(to right, #6E48AA, #9D50BB);
    }

    .no {
      background: #ccc;
      color: #333;
    }

    .no:hover {
      background: #bbb;
    }
  </style>
</head>
<body>
  <div class="confirm-box">
    <h1>Confirm Logout</h1>
    <p>Are you sure you want to log out?</p>
    <form action="log_out.php" method="post" style="display: inline;">
      <button type="submit" class="btn yes">Yes, Log Me Out</button>
    </form>
    <a href="home.php"><button class="btn no">Cancel</button></a>
  </div>
</body>
</html>
