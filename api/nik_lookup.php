<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$nik = isset($_GET['nik']) ? trim($_GET['nik']) : '';
if ($nik === '') { echo json_encode(['found'=>false, 'error'=>'nik_required']); exit; }

// Helper: normalize possible name fields into full_name
function normalize_user_row(array $row): array {
  $name = '';
  foreach (['full_name','nama','name','NAMA','NamaLengkap','nama_peserta','NAMA_PESERTA','nm_peserta','fullname'] as $k) {
    if (isset($row[$k]) && trim((string)$row[$k]) !== '') { $name = (string)$row[$k]; break; }
  }
  if ($name !== '' && !isset($row['full_name'])) { $row['full_name'] = $name; }
  return $row;
}

try {
  // 1) Coba via Stored Procedure
  try {
    $stmt = $pdo->prepare('CALL sp_lookup_nik(:nik)');
    $stmt->execute([':nik'=>$nik]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    if ($row) {
      echo json_encode(['found'=>true, 'user'=> normalize_user_row($row) ]);
      exit;
    }
  } catch (Throwable $spErr) {
    // Lanjut ke fallback SELECT langsung
  }

  // 2) Fallback: cek apakah tabel users ada
  $tblExists = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
  $tblExists->execute();
  $exists = (int)$tblExists->fetch()['c'] > 0;
  if ($exists) {
    // Hindari error "Unknown column" dengan memilih seluruh kolom lalu normalisasi di PHP
    $sql = "SELECT * FROM users WHERE nik = :nik LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute([':nik'=>$nik]);
    $r = $q->fetch();
    if ($r) {
      echo json_encode(['found'=>true, 'user'=> normalize_user_row($r) ]);
      exit;
    }
  }

  // 3) Tidak ditemukan
  echo json_encode(['found'=>false]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['found'=>false, 'error'=>$e->getMessage()]);
}
