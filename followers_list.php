<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];

if (!isset($_GET['user_id'])) {
    echo "User ID not provided.";
    exit();
}

$profile_user_id = intval($_GET['user_id']);

// Fetch followers ordered by name
$stmt = $con->prepare("
    SELECT u.user_id, u.full_name, u.profile_image,
           EXISTS (SELECT 1 FROM follows f2 WHERE f2.follower_id = ? AND f2.following_id = u.user_id) AS is_following
    FROM follows f
    JOIN users u ON f.follower_id = u.user_id
    WHERE f.following_id = ?
    ORDER BY u.full_name ASC
");
$stmt->bind_param("ii", $logged_in_user_id, $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();

$followers = [];
while ($row = $result->fetch_assoc()) {
    $followers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Followers</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #e6ccff, #f3e5f5);
      font-family: 'Poppins', sans-serif;
    }

    .container {
      max-width: 600px;
      margin-top: 50px;
      background: white;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    h3 {
      text-align: center;
      color: #6a1b9a;
      margin-bottom: 30px;
      font-weight: 600;
    }

    .list-group-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: none;
      border-bottom: 1px solid #eee;
      padding: 12px 16px;
    }

    .list-group-item img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 15px;
      border: 2px solid #cba1ec;
    }

    .user-info {
      display: flex;
      align-items: center;
    }

    .user-info a {
      text-decoration: none;
      color: #333;
      font-weight: 500;
    }

    .follow-btn {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      border: none;
      transition: 0.3s;
      cursor: pointer;
    }

    .follow {
      background-color: #a055c9;
      color: white;
    }

    .unfollow {
      background-color: #e0e0e0;
      color: #333;
    }

    .no-followers {
      text-align: center;
      color: #888;
      font-style: italic;
      margin-top: 40px;
    }
.back-button {
  display: inline-block;
  margin-bottom: 15px;
  font-size: 0.95rem;
  font-weight: 500;
  border-radius: 20px;
  padding: 6px 14px;
  color: #6a1b9a;
  background-color: #f3e5f5;
  text-decoration: none;
  border: 1px solid #d9a7f5;
  transition: 0.3s;
}

.back-button:hover {
  background-color: #e0b3ff;
  color: white;
}

  </style>
</head>
<body>

<div class="container">
  <h3>Followers</h3>
<div style="text-align: left; margin-bottom: 15px;">
  <a class="back-button" href="<?php echo ($profile_user_id == $logged_in_user_id) ? 'myprofile_frontend.php' : 'public_profile.php?user_id=' . $profile_user_id; ?>">← Back to Profile</a>

</div>

  <?php if (count($followers) === 0): ?>
    <p class="no-followers">No followers yet.</p>
  <?php else: ?>
    <ul class="list-group">
      <?php foreach ($followers as $row): ?>
        <li class="list-group-item">
          <div class="user-info">
            <img src="<?php echo htmlspecialchars(!empty($row['profile_image']) ? $row['profile_image'] : 'uploads/default-profile.png'); ?>" alt="Profile Image">
            <a href="public_profile.php?user_id=<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['full_name']); ?></a>
          </div>
          <?php if ($row['user_id'] !== $logged_in_user_id): ?>
            <button 
              class="follow-btn <?php echo $row['is_following'] ? 'unfollow' : 'follow'; ?>" 
              onclick="toggleFollow(this, <?php echo $row['user_id']; ?>, '<?php echo $row['is_following'] ? 'unfollow' : 'follow'; ?>')"
            >
              <?php echo $row['is_following'] ? 'Unfollow' : 'Follow'; ?>
            </button>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<script>
  function toggleFollow(button, userId, currentAction) {
    const newAction = currentAction === 'follow' ? 'unfollow' : 'follow';

    fetch('follow_action.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `target_user_id=${userId}&action=${currentAction}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        // Toggle button text and classes
        button.textContent = newAction.charAt(0).toUpperCase() + newAction.slice(1);
        button.classList.toggle('follow');
        button.classList.toggle('unfollow');
        button.setAttribute('onclick', `toggleFollow(this, ${userId}, '${newAction}')`);
      } else {
        alert(data.message);
      }
    })
    .catch(error => {
      console.error("Follow action error:", error);
      alert("Something went wrong!");
    });
  }
</script>

</body>
</html>
