<?php
// This file now only shows the form. The actual handling happens in newpost_backend.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create New Post</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f3f3f3;
      margin: 0;
      padding: 2rem;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .post-box {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      width: 100%;
      max-width: 600px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .post-box h2 {
      margin-top: 0;
      color: #6a1b9a;
    }

    textarea {
      width: 100%;
      height: 120px;
      padding: 1rem;
      border-radius: 8px;
      border: 1px solid #ccc;
      resize: none;
      font-size: 1rem;
      margin-bottom: 1rem;
    }

    input[type="file"] {
      margin-bottom: 1rem;
    }

    .preview {
      margin-top: 1rem;
      border: 1px dashed #aaa;
      padding: 10px;
      border-radius: 8px;
      background: #fafafa;
    }

    .preview p {
      margin: 0 0 10px;
      color: #333;
    }

    .preview img, .preview video {
      max-width: 100%;
      border-radius: 8px;
    }

    button {
      background: #6a1b9a;
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
    }

    button:hover {
      background: #5e1789;
    }

    a.back-link {
      display: inline-block;
      margin-bottom: 1rem;
      text-decoration: none;
      color: #6a1b9a;
      font-weight: 600;
    }

    a.back-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="post-box">
    <a class="back-link" href="home.php"><i class="fas fa-arrow-left"></i> Back to Feed</a>
    <h2>Create New Post</h2>

    <form action="newpost_backend.php" method="POST" enctype="multipart/form-data">
      <textarea name="post_text" id="postText" placeholder="What's on your mind?" oninput="updatePreview()" ></textarea>
      
      <input type="file" name="post_img" accept="image/*,video/*" onchange="showMediaPreview(event)">
      
      <div class="preview" id="previewContainer" style="display: none;">
        <p><strong>Preview:</strong></p>
        <p id="textPreview"></p>
        <img id="imagePreview" style="display:none;" />
        <video id="videoPreview" controls style="display:none;"></video>
      </div>
      
      <br>
      <button type="submit">Post</button>
    </form>
  </div>

  <script>
    function updatePreview() {
      const text = document.getElementById('postText').value;
      const previewText = document.getElementById('textPreview');
      const previewContainer = document.getElementById('previewContainer');

      previewText.textContent = text;
      if (text.trim() !== "") {
        previewContainer.style.display = 'block';
      }
    }

    function showMediaPreview(event) {
      const file = event.target.files[0];
      const previewContainer = document.getElementById('previewContainer');
      const imagePreview = document.getElementById('imagePreview');
      const videoPreview = document.getElementById('videoPreview');

      imagePreview.style.display = 'none';
      videoPreview.style.display = 'none';

      if (file) {
        const fileURL = URL.createObjectURL(file);
        const fileType = file.type;

        if (fileType.startsWith('image/')) {
          imagePreview.src = fileURL;
          imagePreview.style.display = 'block';
        } else if (fileType.startsWith('video/')) {
          videoPreview.src = fileURL;
          videoPreview.style.display = 'block';
        }

        previewContainer.style.display = 'block';
      }
    }
  </script>

</body>
</html>
