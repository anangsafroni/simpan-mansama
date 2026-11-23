<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Cek apakah pengguna sudah login dan rolenya adalah guru/admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'guru') {
    header("Location: index.php");
    exit;
}

$nama_guru = $_SESSION['nama'] ?? 'Guru';
$username_guru = $_SESSION['username'] ?? '';

// Konfigurasi Database (Sesuaikan jika diperlukan)
$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

$pesan_sukses = "";
$pesan_error = "";

// Proses ketika form pengajuan disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $tanggal_mulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($nama_kegiatan) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $pesan_error = "Semua kolom wajib diisi!";
    } else {
        // Contoh simpan ke tabel pengajuan (pastikan tabel pengajuan Anda ada, atau sesuaikan kolomnya)
        // Kolom bisa disesuaikan dengan struktur database Anda (misal: pengaju, nama_kegiatan, tgl_mulai, tgl_selesai, keterangan)
        $stmt = $conn->prepare("INSERT INTO pengajuan_dispensasi (pengaju, nama_kegiatan, tanggal_mulai, tanggal_selesai, keterangan, status) VALUES (?, ?, ?, ?, ?, 'Menunggu')");
        if ($stmt) {
            $stmt->bind_param("sssss", $nama_guru, $nama_kegiatan, $tanggal_mulai, $tanggal_selesai, $keterangan);
            if ($stmt->execute()) {
                $pesan_sukses = "Pengajuan dispensasi berhasil dikirim!";
            } else {
                $pesan_error = "Gagal menyimpan pengajuan: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $pesan_error = "Gagal menyiapkan kueri database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pengajuan Dispensasi Guru - MAN 1 Kota Madiun</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); width: 100%; max-width: 600px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; }
        .header h2 { margin: 0; font-size: 20px; color: #0f172a; }
        .user-info { font-size: 13px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-family: inherit; }
        .form-group textarea { resize: vertical; height: 100px; }
        .btn-submit { width: 100%; background: #2563eb; color: #fff; padding: 10px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 10px; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert-success { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .logout-link { display: block; text-align: center; margin-top: 15px; font-size: 13px; color: #ef4444; text-decoration: none; }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h2>Form Pengajuan Dispensasi</h2>
            <span class="user-info">Login sebagai: <b><?= htmlspecialchars($nama_guru); ?></b></span>
        </div>
    </div>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="alert-success"><?= htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert-error"><?= htmlspecialchars($pesan_error); ?></div>
    <?php endif; ?>

    <form action="pengajuan_guru.php" method="POST">
        <div class="form-group">
            <label for="nama_kegiatan">Nama Kegiatan / Keperluan:</label>
            <input type="text" name="nama_kegiatan" id="nama_kegiatan" placeholder="Contoh: Dinas Luar / Pelatihan LKS" required>
        </div>

        <div class="form-group">
            <label for="tanggal_mulai">Tanggal Mulai:</label>
            <input type="date" name="tanggal_mulai" id="tanggal_mulai" required>
        </div>

        <div class="form-group">
            <label for="tanggal_selesai">Tanggal Selesai:</label>
            <input type="date" name="tanggal_selesai" id="tanggal_selesai" required>
        </div>

        <div class="form-group">
            <label for="keterangan">Keterangan Tambahan:</label>
            <textarea name="keterangan" id="keterangan" placeholder="Masukkan keterangan tambahan jika ada..."></textarea>
        </div>

        <button type="submit" class="btn-submit">Kirim Pengajuan</button>
    </form>

    <a href="logout.php" class="logout-link">Keluar dari Sistem (Logout)</a>
</div>

</body>
</html>