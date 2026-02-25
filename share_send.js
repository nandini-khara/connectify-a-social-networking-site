document.addEventListener('click', function(e) {
  const btn = e.target.closest('.share-send-btn');
  if (!btn) return;

  if (!selectedPostId) {
    alert('No post selected.');
    return;
  }

  const receiverId = btn.dataset.userId;
  const name = btn.dataset.name;

  fetch('send_message.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `receiver_id=${receiverId}&message=&shared_post_id=${selectedPostId}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'sent') {
      alert(`Post shared with ${name}!`);
      document.getElementById('shareSidebar').classList.remove('open');
    } else if (data.status === 'blocked') {
      alert('Cannot share with this user.');
    } else {
      alert('Failed to share.');
    }
  })
  .catch(() => alert('Network error'));
});