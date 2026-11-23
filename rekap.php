<?php
$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Koneksi Database Gagal: " . $conn->connect_error); }

// Ambil semua data dispensasi dari database, diurutkan dari yang terbaru
$result = $conn->query("SELECT * FROM dispensasi ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi E-Dispensasi - MAN 1 Kota Madiun</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 20px auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .nav-menu { display: flex; gap: 10px; margin-bottom: 20px; }
        .nav-menu a { flex: 1; text-align: center; padding: 10px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .nav-active { background: #2563eb; color: #fff; }
        .nav-inactive { background: #e2e8f0; color: #475569; }

        .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; }
        .header h2 { margin: 0; color: #0f172a; font-size: 22px; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 14px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        th { background-color: #f1f5f9; color: #334155; font-weight: 600; }
        tr:nth-child(even) { background-color: #f8fafc; }

        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        
        .btn-print { background: #059669; color: #white; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block; }
        .btn-print:hover { background: #047857; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- NAVBAR NAVIGASI -->
    <div class="nav-menu">
        <a href="index.php" class="nav-inactive">Form Input Dispensasi</a>
        <a href="rekap.php" class="nav-active">Daftar / Rekap Pengajuan</a>
    </div>

    <div class="header">
        <h2>REKAPITULASI PENGAJUAN DISPENSASI</h2>
        <p>MAN 1 Kota Madiun</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Siswa / Kelas</th>
                <th>Kegiatan</th>
                <th>Jadwal & Tempat</th>
                <th>Pendamping</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td>
                        <strong><?= htmlspecialchars($row['nama_siswa']); ?></strong><br>
                        <small style="color: #64748b;">Kelas: <?= htmlspecialchars($row['kelas']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['nama_kegiatan']); ?></td>
                    <td>
                        <?= htmlspecialchars($row['jadwal_dispensasi']); ?><br>
                        <small style="color: #64748b;">Lokasi: <?= htmlspecialchars($row['tempat']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['guru_pendamping']); ?></td>
                    <td>
                        <span class="badge badge-pending"><?= htmlspecialchars($row['status']); ?></span>
                    </td>
                    <td>
                        <!-- Tombol untuk menuju halaman cetak surat -->
                        <a href="cetak.php?id=<?= $row['id']; ?>" target="_blank" class="btn-print">Cetak Surat</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b; padding: 20px;">Belum ada data pengajuan dispensasi yang tersimpan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>