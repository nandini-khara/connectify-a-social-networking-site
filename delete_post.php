<?php
session_start();
require 'connect.php';

if(!isset($_SESSION['user_id'])){
  echo json_encode(['status'=>'error','msg'=>'login']); exit;
}

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id'] ?? 0);
if(!$post_id){ echo json_encode(['status'=>'error','msg'=>'bad id']); exit; }

$stmt = $con->prepare("DELETE FROM post WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $post_id, $user_id);
$stmt->execute();

echo json_encode([
  'status' => $stmt->affected_rows ? 'success' : 'error',
  'msg'    => $stmt->affected_rows ? '' : 'not yours'
]);
