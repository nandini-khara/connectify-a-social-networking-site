<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify - Sign Up</title>
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
  align-items: flex-start; /* Align to top instead of center */
  min-height: 100vh;       /* Better than fixed 100vh */
  padding: 2rem 0;         /* Space above and below */
  overflow-y: auto;        /* Ensure vertical scrolling */
}

.container {
  background: white;
  width: 400px;
  padding: 2rem;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.1);
  text-align: center;
  margin-top: 1rem; /* Optional extra spacing */
}


    .app-name {
      font-size: 1.8rem;
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

    .input-group input[type="text"],
    .input-group input[type="email"],
    .input-group input[type="password"],
    .input-group input[type="date"] {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 10px;
    }

    .gender-group {
      display: flex;
      gap: 1rem;
      margin-top: 0.25rem;
    }

    .gender-group label {
      font-weight: 400;
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

    .show-password {
      font-weight: normal;
      font-size: 0.9rem;
      margin-top: 0.5rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="app-name">Connectify</div>
    <h2>Create Your Account</h2>
    <form id="signup-form" action="sendcode1.php" method="POST">
      <div class="input-group">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="full_name" required />
      </div>

      <div class="input-group">
        <label>Gender</label>
        <div class="gender-group">
          <label><input type="radio" name="gender" value="male" required /> Male</label>
          <label><input type="radio" name="gender" value="female" required /> Female</label>
          <label><input type="radio" name="gender" value="other" required /> Other</label>
        </div>
      </div>

      <div class="input-group">
        <label for="dob">Date of Birth</label>
        <input type="date" id="dob" name="dob" required />
      </div>

      <div class="input-group">
        <label for="phone">Phone Number</label>
        <input type="text" id="phone" name="phone_number" placeholder="10-digit phone number" pattern="\d{10}" required />
      </div>

      <div class="input-group">
        <label for="email">Email ID</label>
        <input type="email" id="email" name="email_id" required />
      </div>

      <div class="input-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="user_name" required />
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="8–12 characters, include number & symbol"
               pattern="^(?=.*[0-9])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,12}$"
               required />
      </div>

      <div class="input-group">
        <label for="confirm-password">Confirm Password</label>
        <input type="password" id="confirm-password" name="confirm_password" placeholder="Re-enter your password" required />
        <label class="show-password">
          <input type="checkbox" id="show-password" /> Show Password
        </label>
      </div>

      <button type="submit" class="btn">Sign Up</button>
    </form>
  </div>

  <script>
    // Form validation
    document.getElementById('signup-form').addEventListener('submit', function(e) {
      const phone = document.getElementById('phone').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm-password').value;

      const phoneValid = /^\d{10}$/.test(phone);
      const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      const passwordValid = /^(?=.*[0-9])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,12}$/.test(password);
      let errors = [];

      if (!phoneValid) errors.push("Phone Number must be exactly 10 digits.");
      if (!emailValid) errors.push("Invalid Email ID format.");
      if (!passwordValid) errors.push("Password must be 8–12 characters, with at least one number and one symbol.");
      if (password !== confirmPassword) errors.push("Passwords do not match.");

      if (errors.length > 0) {
        e.preventDefault();
        alert(errors.join("\n"));
      }
    });

    // Password visibility toggle
    document.getElementById('show-password').addEventListener('change', function() {
      const pwdField = document.getElementById('password');
      const confirmPwdField = document.getElementById('confirm-password');
      const type = this.checked ? 'text' : 'password';
      pwdField.type = type;
      confirmPwdField.type = type;
    });
  </script>

</body>
</html>
