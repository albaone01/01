<?php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
$payload = json_encode([
  'cmd'      => 'test_print',
  'text'     => "TEST PRINT\nToko: ".($_SESSION['toko_id'] ?? '')."\n".date('Y-m-d H:i:s'),
  'printer'  => $_POST['alamat'] ?? '',
  'driver'   => $_POST['driver'] ?? 'escpos',
  'width'    => (int)($_POST['lebar'] ?? 80),
]);
$ch = curl_init("http://localhost:19100/print"); // endpoint agent lokal
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload]);
$resp = curl_exec($ch); $err = curl_error($ch); $http = curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
if($err || $http>=400) exit(json_encode(['ok'=>false,'msg'=>'Agent error: '.($err ?: $resp)]));
echo $resp ?: json_encode(['ok'=>true]);
