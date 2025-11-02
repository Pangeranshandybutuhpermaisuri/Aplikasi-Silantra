<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
  // Buat tabel uploads jika belum ada
  $pdo->exec("CREATE TABLE IF NOT EXISTS uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    rel_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Validasi file
  if (!isset($_FILES['files'])) {
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>'no_files']);
    exit;
  }

  $files = $_FILES['files'];
  $count = is_array($files['name']) ? count($files['name']) : 0;
  if ($count === 0) {
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>'no_files']);
    exit;
  }

  // Pastikan folder uploads ada
  $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
      throw new RuntimeException('Failed to create upload directory');
    }
  }

  $saved = [];
  for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
      continue; // skip file bermasalah
    }
    $orig = $files['name'][$i];
    $tmp  = $files['tmp_name'][$i];
    $mime = $files['type'][$i] ?? null;
    $size = (int)($files['size'][$i] ?? 0);

    // Generate nama file aman
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $base = bin2hex(random_bytes(8));
    $stored = $base . ($ext ? ('.' . preg_replace('/[^A-Za-z0-9_.-]/','', $ext)) : '');

    $dest = $uploadDir . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
      continue;
    }

    $relPath = 'uploads/' . $stored;

    $ins = $pdo->prepare('INSERT INTO uploads(original_name, stored_name, mime_type, size_bytes, rel_path) VALUES(:o,:s,:m,:z,:p)');
    $ins->execute([
      ':o'=>$orig,
      ':s'=>$stored,
      ':m'=>$mime,
      ':z'=>$size,
      ':p'=>$relPath,
    ]);

    $saved[] = [
      'id' => (int)$pdo->lastInsertId(),
      'original_name' => $orig,
      'stored_name' => $stored,
      'mime_type' => $mime,
      'size_bytes' => $size,
      'rel_path' => $relPath,
    ];
  }

  if (empty($saved)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'upload_failed']);
    exit;
  }

  echo json_encode(['success'=>true,'files'=>$saved]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
