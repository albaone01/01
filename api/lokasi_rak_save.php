<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/lokasi_rak.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));

$id = (int)($_POST['lokasi_id'] ?? 0);
$gudangId = (int)($_POST['gudang_id'] ?? 0);
$kode = strtoupper(trim((string)($_POST['kode_lokasi'] ?? '')));
$nama = trim((string)($_POST['nama_lokasi'] ?? ''));
$zona = strtoupper(trim((string)($_POST['zona'] ?? '')));
$lorong = strtoupper(trim((string)($_POST['lorong'] ?? '')));
$levelRak = strtoupper(trim((string)($_POST['level_rak'] ?? '')));
$bin = strtoupper(trim((string)($_POST['bin'] ?? '')));
$kapasitas = (int)($_POST['kapasitas'] ?? 0);
$aktif = isset($_POST['aktif']) && (int)$_POST['aktif'] === 1 ? 1 : 0;
$catatan = trim((string)($_POST['catatan'] ?? ''));

if ($gudangId <= 0) exit(json_encode(['ok' => false, 'msg' => 'Gudang wajib dipilih']));
if ($kode === '' || $nama === '') exit(json_encode(['ok' => false, 'msg' => 'Kode lokasi dan nama lokasi wajib diisi']));
if ($kapasitas < 0) exit(json_encode(['ok' => false, 'msg' => 'Kapasitas tidak boleh negatif']));

try {
    lokasi_rak_ensure_schema($pos_db);

    $stG = $pos_db->prepare("SELECT gudang_id FROM gudang WHERE gudang_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
    $stG->bind_param("ii", $gudangId, $tokoId);
    $stG->execute();
    $okG = $stG->get_result()->fetch_assoc();
    $stG->close();
    if (!$okG) exit(json_encode(['ok' => false, 'msg' => 'Gudang tidak valid']));

    $dup = $pos_db->prepare("
        SELECT lokasi_id
        FROM lokasi_rak
        WHERE toko_id=? AND gudang_id=? AND kode_lokasi=? AND deleted_at IS NULL AND (?=0 OR lokasi_id<>?)
        LIMIT 1
    ");
    $dup->bind_param("iisii", $tokoId, $gudangId, $kode, $id, $id);
    $dup->execute();
    $exists = $dup->get_result()->fetch_assoc();
    $dup->close();
    if ($exists) exit(json_encode(['ok' => false, 'msg' => 'Kode lokasi sudah dipakai di gudang tersebut']));

    if ($id > 0) {
        $stmt = $pos_db->prepare("
            UPDATE lokasi_rak
            SET gudang_id=?, kode_lokasi=?, nama_lokasi=?, zona=?, lorong=?, level_rak=?, bin=?,
                kapasitas=?, aktif=?, catatan=?
            WHERE lokasi_id=? AND toko_id=? AND deleted_at IS NULL
        ");
        $stmt->bind_param(
            "issssssiisii",
            $gudangId,
            $kode,
            $nama,
            $zona,
            $lorong,
            $levelRak,
            $bin,
            $kapasitas,
            $aktif,
            $catatan,
            $id,
            $tokoId
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pos_db->prepare("
            INSERT INTO lokasi_rak
            (toko_id,gudang_id,kode_lokasi,nama_lokasi,zona,lorong,level_rak,bin,kapasitas,aktif,catatan)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "iissssssiis",
            $tokoId,
            $gudangId,
            $kode,
            $nama,
            $zona,
            $lorong,
            $levelRak,
            $bin,
            $kapasitas,
            $aktif,
            $catatan
        );
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['ok' => true, 'msg' => 'Lokasi rak tersimpan']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
