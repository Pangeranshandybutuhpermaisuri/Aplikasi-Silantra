<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$new_pass = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';

if ($user_id <= 0 || $new_pass === '') {
  http_response_code(422);
  echo json_encode(['success'=>false,'error'=>'required']);
  exit;
}

try{
  $hash = password_hash($new_pass, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
  $stmt->execute([':h'=>$hash, ':id'=>$user_id]);
  if($stmt->rowCount() < 1){
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'user_not_found']);
    exit;
  }
  echo json_encode(['success'=>true]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
