<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

function body($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }

 $service_code = body('service_code'); // 'A' | 'B' | 'C' | 'BU' (lainnya akan dimapping)
 $nik          = body('nik'); // optional
 $full_name    = body('full_name'); // optional
 $username     = body('username');  // optional
 $phone        = body('phone');
 $email        = body('email');     // optional
 $counter_no   = body('counter_no'); // optional
 $desc         = body('desc');      // optional; disimpan sebagai notes tiket

 if ($phone === '') {
   http_response_code(422);
   echo json_encode(['success'=>false,'error'=>'required_fields','detail'=>'phone wajib']);
   exit;
 }

 // Map service_code yang tidak tersedia ke 'A'
 $svc = strtoupper(preg_replace('/[^A-Z]/','', $service_code));
 if (!in_array($svc, ['A','B','C','BU'], true)) { $svc = 'A'; }

 try {
   $pdo->beginTransaction();

   // Upsert user
   $user_id = null;
   if ($nik !== '') {
     // Gunakan prosedur resmi upsert by NIK
     $stmt = $pdo->prepare('CALL sp_upsert_user_by_nik(:nik,:full_name,:username,:phone,:email,:password_hash)');
     $stmt->execute([
       ':nik'=>$nik,
       ':full_name'=>($full_name !== '' ? $full_name : $username),
       ':username'=>($username !== '' ? $username : null),
       ':phone'=>($phone !== '' ? $phone : null),
       ':email'=>($email !== '' ? $email : null),
       ':password_hash'=>null,
     ]);
     $row = $stmt->fetch();
     $stmt->closeCursor();
     if ($row && isset($row['user_id'])) { $user_id = (int)$row['user_id']; }
   } else {
     // Upsert manual berdasarkan phone
     $sel = $pdo->prepare('SELECT id FROM users WHERE phone = :p LIMIT 1');
     $sel->execute([':p'=>$phone]);
     $row = $sel->fetch();
     if ($row) {
       $user_id = (int)$row['id'];
       $upd = $pdo->prepare('UPDATE users SET full_name=COALESCE(:fn, full_name), username=COALESCE(:un, username), email=COALESCE(:em, email) WHERE id=:id');
       $upd->execute([':fn'=>($full_name!==''?$full_name:null), ':un'=>($username!==''?$username:null), ':em'=>($email!==''?$email:null), ':id'=>$user_id]);
     } else {
       $ins = $pdo->prepare('INSERT INTO users(full_name, username, phone, email) VALUES(:fn,:un,:ph,:em)');
       $ins->execute([':fn'=>($full_name!==''?$full_name:null), ':un'=>($username!==''?$username:null), ':ph'=>$phone, ':em'=>($email!==''?$email:null)]);
       $user_id = (int)$pdo->lastInsertId();
     }
   }

   // Buat tiket melalui prosedur resmi
   $call = $pdo->prepare('CALL sp_create_queue_ticket(:svc, :uid, :counter)');
   $call->execute([':svc'=>$svc, ':uid'=>($user_id?:null), ':counter'=>($counter_no!==''?$counter_no:null)]);
   $ticket = $call->fetch();
   $call->closeCursor();

   if (!$ticket) { throw new RuntimeException('Gagal membuat tiket'); }

   // Simpan notes jika ada
   if ($desc !== '' && isset($ticket['id'])) {
     $updT = $pdo->prepare('UPDATE queue_tickets SET notes = :n WHERE id = :id');
     $updT->execute([':n'=>$desc, ':id'=>(int)$ticket['id']]);
   }

   $pdo->commit();
   echo json_encode(['success'=>true, 'ticket'=>$ticket]);
 } catch (Throwable $e) {
   if ($pdo->inTransaction()) $pdo->rollBack();
   http_response_code(500);
   echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
 }

