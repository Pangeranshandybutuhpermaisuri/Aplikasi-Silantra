<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$u = isset($_POST['username']) ? trim($_POST['username']) : '';
$p = isset($_POST['password']) ? (string)$_POST['password'] : '';
if ($u === '' || $p === '') { http_response_code(422); echo json_encode(['success'=>false,'error'=>'required']); exit; }

try {
  $uLower = mb_strtolower($u);
  $uDigits = preg_replace('/\D+/', '', $u);

  // Ambil semua kolom agar kolom opsional seperti 'bpjs' ikut terbaca jika ada
  $sql = "SELECT *
          FROM users
          WHERE LOWER(username) = :ul_user OR LOWER(email) = :ul_email OR LOWER(full_name) = :ul_full";
  $params = [
    ':ul_user'  => $uLower,
    ':ul_email' => $uLower,
    ':ul_full'  => $uLower,
  ];
  if ($uDigits !== '') {
    // Tambahkan pencocokan numerik untuk NIK/phone jika input berisi angka
    $sql .= " OR nik = :ud_nik OR phone = :ud_phone";
    $params[':ud_nik'] = $uDigits;
    $params[':ud_phone'] = $uDigits;
  }
  $sql .= " LIMIT 1";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $row = $stmt->fetch();
  if(!$row){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'no_user']); exit; }
  if(empty($row['password_hash'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'no_password_hash']); exit; }
  if(!password_verify($p, $row['password_hash'])){ http_response_code(401); echo json_encode(['success'=>false,'error'=>'bad_password']); exit; }
  $bpjs = isset($row['bpjs']) ? (string)$row['bpjs'] : '';
  echo json_encode(['success'=>true,'user'=>[
    'user_id'=>(int)$row['id'],
    'full_name'=>$row['full_name'] ?? '',
    'username'=>$row['username'] ?? $u,
    'email'=>$row['email'] ?? '',
    'phone'=>$row['phone'] ?? '',
    'nik'=>$row['nik'] ?? '',
    'bpjs'=>$bpjs,
  ]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
