<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Email Verification</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #f5f5f5;
    }

    .container {
      background: white;
      padding: 2rem 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
      text-align: center;
    }

    h2 {
      color: #6a1b9a;
      margin-bottom: 1rem;
    }

    p {
      color: #555;
      margin-bottom: 1.5rem;
    }

    .otp-input {
      width: 100%;
      height: 50px;
      font-size: 1.2rem;
      text-align: center;
      border: 1px solid #ccc;
      border-radius: 8px;
      outline-color: #6a1b9a;
      margin-bottom: 1.5rem;
    }

    .verify-btn {
      background: #6a1b9a;
      color: white;
      border: none;
      padding: 0.6rem 1.5rem;
      font-size: 1rem;
      border-radius: 8px;
      cursor: pointer;
    }

    .resend {
      margin-top: 1rem;
      color: #6a1b9a;
      cursor: pointer;
      font-size: 0.9rem;
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
  <h2>Email Verification</h2>
  <!-- Error Message -->
  <?php if (isset($_GET['msg'])): ?>
    <div class="error"><?php echo htmlspecialchars($_GET['msg']); ?></div>
  <?php endif; ?>
  <p>Enter the 6-digit code sent to <span><b>
    <?php
    session_start();
if (isset($_SESSION['signup_data']['email_id'])) {
    echo $_SESSION['signup_data']['email_id'];
} else {
    echo 'Email not found in session!';
}


   // echo $_SESSION['email'];
    ?></b></span></p>
  <form action="verify_otp1.php" method="POST">
    <input type="text" name="otp" class="otp-input" maxlength="6" pattern="\d{6}" placeholder="Enter OTP" required />
    <button type="submit" class="verify-btn">Verify</button>
  </form>
  <div class="resend">Didn't receive code? <a href="resend_code.php">Resend</a></div>
</div>

<script>
  // Remove ?msg=... from URL after showing error once
  if (window.location.search.includes('msg=')) {
    const url = new URL(window.location);
    url.searchParams.delete('msg');
    window.history.replaceState({}, document.title, url.pathname);
  }
</script>

</body>
</html>
