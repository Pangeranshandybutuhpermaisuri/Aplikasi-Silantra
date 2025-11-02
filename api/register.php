<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

function body_param($key){ return isset($_POST[$key]) ? trim($_POST[$key]) : ''; }

$nik       = body_param('nik');
$full_name = body_param('full_name');
$username  = body_param('username');
$phone     = body_param('phone');
$email     = body_param('email'); // optional
$password  = body_param('password');

if ($nik === '' || $username === '' || $password === '' || $phone === '') {
  http_response_code(422);
  echo json_encode(['success'=>false,'error'=>'required_fields','detail'=>'nik, username, phone, password wajib']);
  exit;
}

try {
  $pdo->beginTransaction();

  $password_hash = password_hash($password, PASSWORD_BCRYPT);

  // Full name bisa kosong; jika kosong dan lookup ada, gunakan nama dari DB
  if ($full_name === '') {
    try {
      $lookup = $pdo->prepare('CALL sp_lookup_nik(:nik)');
      $lookup->execute([':nik'=>$nik]);
      $row = $lookup->fetch();
      $lookup->closeCursor();
      if ($row && !empty($row['full_name'])) { $full_name = $row['full_name']; }
    } catch (Throwable $e1) {
      // Fallback: ambil dari tabel users jika ada
      $q = $pdo->prepare("SELECT * FROM users WHERE nik = :nik LIMIT 1");
      $q->execute([':nik'=>$nik]);
      $r = $q->fetch();
      if ($r) {
        foreach (['full_name','nama','name','NamaLengkap','NAMA','nama_peserta','NAMA_PESERTA','nm_peserta','fullname'] as $k) {
          if (isset($r[$k]) && trim((string)$r[$k]) !== '') { $full_name = (string)$r[$k]; break; }
        }
      }
    }
  }
  if ($full_name === '') { $full_name = $username; }

  // 1) Coba lewat Stored Procedure
  $spOk = false; $user = null; $spErr = null;
  try {
    $stmt = $pdo->prepare('CALL sp_upsert_user_by_nik(:nik,:full_name,:username,:phone,:email,:password_hash)');
    $stmt->execute([
      ':nik' => $nik,
      ':full_name' => $full_name,
      ':username' => $username,
      ':phone' => $phone,
      ':email' => ($email !== '' ? $email : null),
      ':password_hash' => $password_hash,
    ]);
    $user = $stmt->fetch();
    $stmt->closeCursor();
    $spOk = true;
  } catch (Throwable $eSp) {
    $spErr = $eSp;
  }

  // 2) Jika SP gagal, fallback ke upsert langsung ke tabel users (dengan id sebagai PK, nik UNIQUE)
  if (!$spOk) {
    // Pastikan tabel users ada
    $tblExists = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
    $tblExists->execute(); $exists = (int)$tblExists->fetch()['c'] > 0;
    if (!$exists) { throw $spErr ?: new Exception('Table users not found'); }

    // Upsert berdasarkan nik
    $ins = $pdo->prepare("INSERT INTO users (nik, full_name, username, phone, email, password_hash)
                          VALUES (:nik, :full_name, :username, :phone, :email, :password_hash)
                          ON DUPLICATE KEY UPDATE
                            full_name = VALUES(full_name),
                            username  = VALUES(username),
                            phone     = VALUES(phone),
                            email     = VALUES(email),
                            password_hash = VALUES(password_hash)");
    $ins->execute([
      ':nik'=>$nik,
      ':full_name'=>$full_name,
      ':username'=>$username,
      ':phone'=>$phone,
      ':email'=>($email !== '' ? $email : null),
      ':password_hash'=>$password_hash,
    ]);

    // Ambil kembali data user
    $sel = $pdo->prepare("SELECT * FROM users WHERE nik = :nik LIMIT 1");
    $sel->execute([':nik'=>$nik]);
    $user = $sel->fetch();
  }

  $pdo->commit();

  echo json_encode(['success'=>true,'user'=>$user]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
