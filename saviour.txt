<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deepfake Detection</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ---------- GLOBAL ---------- */
* {
  box-sizing: border-box;
  font-family: "Inter", sans-serif;
}
body {
  margin: 0;
  height: 100vh;
  background: radial-gradient(circle at top, #1b3b44, #0b1c22 70%);
  color: #fff;
  overflow-x: hidden;
}

/* ---------- HERO ---------- */
.hero {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 140px 80px 80px; /* top padding increased */
  height: 100vh;
}

}

.hero-text {
  max-width: 45%;
}

.hero-text h1 {
  font-size: 48px;
  margin-bottom: 15px;
}

.hero-text p {
  font-size: 16px;
  opacity: 0.85;
  line-height: 1.6;
}

.buttons {
  margin-top: 25px;
}

.btn-primary {
  background: #00d4ff;
  color: #000;
  padding: 12px 22px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  margin-right: 10px;
}

.btn-secondary {
  background: transparent;
  color: #00d4ff;
  border: 1px solid #00d4ff;
  padding: 12px 22px;
  border-radius: 8px;
  cursor: pointer;
}

/* ---------- SLIDER ---------- */
.slider {
  width: 40%;
  overflow: hidden;
  border-radius: 12px;
}

.slider-track {
  display: flex;
  animation: slide 8s infinite alternate;
}

.slider img {
  width: 100%;
  cursor: pointer;
  border-radius: 12px;
}

@keyframes slide {
  0% { transform: translateX(0); }
  100% { transform: translateX(-100%); }
}

/* ---------- PRIVACY ---------- */
.privacy {
  margin-top: 30px;
  display: flex;
  gap: 20px;
  font-size: 14px;
  opacity: 0.85;
}

/* ---------- OPTIONS ---------- */
.options {
  display: none;
  padding: 60px;
  text-align: center;
}

.option-box {
  display: inline-block;
  background: rgba(0,0,0,0.35);
  padding: 25px;
  border-radius: 12px;
  margin: 10px;
  width: 220px;
  cursor: pointer;
}

/* ---------- UPLOAD ---------- */
.upload-box {
  display: none;
  margin-top: 30px;
  border: 2px dashed #00d4ff;
  padding: 30px;
  border-radius: 12px;
}

progress {
  width: 100%;
  margin-top: 15px;
}

/* ---------- MODAL ---------- */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.85);
  justify-content: center;
  align-items: center;
}

.modal img {
  max-width: 80%;
  border-radius: 12px;
}
/* ---------- NAVBAR ---------- */
.navbar {
  position: fixed;
  top: 0;
  width: 100%;
  height: 70px;
  padding: 0 60px;
  display: flex;
  justify-content: space-between;
  align-items: center;

  background: rgba(15, 32, 39, 0.85); /* contrast + glass effect */
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  z-index: 1000;
}

.nav-left {
  display: flex;
  align-items: center;
  gap: 40px;
}

.logo {
  font-size: 20px;
  font-weight: 700;
  color: #00d4ff;
  letter-spacing: 0.5px;
}

.nav-links a {
  text-decoration: none;
  color: #ffffff;
  font-size: 14px;
  opacity: 0.85;
  margin-right: 20px;
  transition: opacity 0.2s ease;
}

.nav-links a:hover {
  opacity: 1;
}

.nav-right {
  display: flex;
  gap: 12px;
}

.btn-login {
  background: transparent;
  color: #00d4ff;
  border: 1px solid #00d4ff;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}

.btn-signup {
  background: #00d4ff;
  color: #000;
  border: none;
  padding: 8px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}

</style>
</head>

<body>
<!-- NAVBAR -->
<header class="navbar">
  <div class="nav-left">
    <span class="logo">DeepFakeAI</span>

    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="#">How It Works</a>
      <a href="detect.php">Detection</a>
      <a href="#">About</a>
      <a href="#">Contact</a>
    </nav>
  </div>

  <div class="nav-right">
    <button class="btn-login">Login</button>
    <button class="btn-signup">Sign Up</button>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-text">
    <h1>Detect Deepfakes. Protect Digital Truth.</h1>
    <p>
      AI-powered analysis for images, videos, audio, and online media
      to identify manipulated content with confidence.
    </p>

    <div class="buttons">
      <button class="btn-primary" onclick="showOptions()">Start Detection</button>
      <button class="btn-secondary" onclick="toggleHow()">Read More</button>
    </div>

    <div class="privacy">
      <span>🔒 Files auto-deleted</span>
      <span>🧾 No storage without consent</span>
      <span>🧠 Secure AI processing</span>
    </div>

    <p id="howItWorks" style="display:none; margin-top:15px; opacity:0.8;">
      Uploaded media is analyzed using AI models trained to detect
      facial, audio, and visual manipulation patterns.
    </p>
  </div>

  <!-- SLIDER -->
  <div class="slider">
    <div class="slider-track">
      <img src="assets/img1.jpg" onclick="openModal(this.src)">
      <img src="assets/img2.jpg" onclick="openModal(this.src)">
    </div>
  </div>
</section>

<!-- OPTIONS -->
<section class="options" id="options">
    <button class="btn-secondary" onclick="closeOptions()">✕ Close</button>

  <h2>Select Detection Type</h2>

  <div class="option-box" onclick="showUpload()">📷 Image Detection</div>
  <div class="option-box" onclick="showUpload()">🎥 Video Detection</div>
  <div class="option-box" onclick="showUpload()">🎙 Audio Detection</div>
  <div class="option-box" onclick="showUpload()">🌐 URL Detection</div>

  <div class="upload-box" id="uploadBox">
    <p>Drag & Drop or Click to Upload</p>
    <p style="font-size:13px;opacity:0.8;">
      Max 100MB • JPG, PNG, MP4, WAV
    </p>
    <input type="file" onchange="startProgress()">
    <progress id="progress" value="0" max="100"></progress>
  </div>
</section>

<!-- IMAGE MODAL -->
<div class="modal" id="modal" onclick="closeModal()">
  <img id="modalImg">
</div>

<script>
function toggleHow() {
  const el = document.getElementById("howItWorks");
  el.style.display = el.style.display === "none" ? "block" : "none";
}

function showOptions() {
  document.getElementById("options").style.display = "block";
  window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" });
}

function showUpload() {
  document.getElementById("uploadBox").style.display = "block";
}

function startProgress() {
  let p = document.getElementById("progress");
  p.value = 0;
  let interval = setInterval(() => {
    p.value += 10;
    if (p.value >= 100) clearInterval(interval);
  }, 200);
}

function openModal(src) {
  document.getElementById("modal").style.display = "flex";
  document.getElementById("modalImg").src = src;
}

function closeModal() {
  document.getElementById("modal").style.display = "none";
}
function closeOptions() {
  document.getElementById("options").style.display = "none";
}

</script>

</body>
</html>
