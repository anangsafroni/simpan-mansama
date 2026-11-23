<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login_admin.php");
    exit;
}

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Koneksi Database Gagal: " . $conn->connect_error); }

// Buat tabel admin jika belum ada (hanya username dan password)
$conn->query("CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

$msg = "";
$msg_type = "";
$menu = $_GET['menu'] ?? 'cetak';

// --- PROSES TAMBAH ADMIN MANUAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed_password);
        if ($stmt->execute()) {
            $msg = "Akun admin baru berhasil ditambahkan!";
            $msg_type = "success";
        } else {
            $msg = "Gagal menambahkan admin. Kemungkinan username sudah terdaftar.";
            $msg_type = "danger";
        }
        $stmt->close();
    } else {
        $msg = "Username dan password tidak boleh kosong!";
        $msg_type = "danger";
    }
}

// --- PROSES UPDATE STATUS SURAT (Terima / Ditolak) ---
if (isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $id = intval($_GET['id'] ?? 0);
    $status = $_GET['status'] ?? '';
    if ($id > 0 && in_array($status, ['Diterima', 'Ditolak'])) {
        $stmt_up = $conn->prepare("UPDATE dispensasi SET status = ? WHERE id = ?");
        $stmt_up->bind_param("si", $status, $id);
        $stmt_up->execute();
        $stmt_up->close();
        header("Location: admin.php?menu=cetak");
        exit;
    }
}

// --- PROSES SIMPAN PENGATURAN KUSTOM SURAT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kustom'])) {
    $nama_kepsek = trim($_POST['nama_kepsek'] ?? '');
    
    if (!is_dir('assets')) {
        mkdir('assets', 0777, true);
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_tmp = $_FILES['logo']['tmp_name'];
        $logo_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($logo_ext, ['png', 'jpg', 'jpeg'])) {
            foreach (['png', 'jpg', 'jpeg'] as $ext) {
                if (file_exists("assets/logo.$ext")) unlink("assets/logo.$ext");
            }
            move_uploaded_file($logo_tmp, 'assets/logo.' . $logo_ext);
        }
    }

    if (isset($_FILES['ttd']) && $_FILES['ttd']['error'] === UPLOAD_ERR_OK) {
        $ttd_tmp = $_FILES['ttd']['tmp_name'];
        $ttd_ext = strtolower(pathinfo($_FILES['ttd']['name'], PATHINFO_EXTENSION));
        if ($ttd_ext === 'png') {
            move_uploaded_file($ttd_tmp, 'assets/ttd.png');
        }
    }

    $_SESSION['config_kepsek'] = $nama_kepsek;
    $msg = "Pengaturan kustom surat berhasil diperbarui!";
    $msg_type = "success";
}

// --- PROSES IMPORT CSV LOKAL (TABEL ADMIN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv_admin'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            $row_count = 0;
            $sukses = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                if ($row_count === 1) continue; 
                
                if (!empty($data[0]) && !empty($data[1])) {
                    $username = trim($data[0]);
                    $password = password_hash(trim($data[1]), PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)");
                    $stmt->bind_param("ss", $username, $password);
                    if ($stmt->execute()) { $sukses++; }
                    $stmt->close();
                }
            }
            fclose($handle);
            $msg = "Berhasil mengimpor $sukses data admin dari file CSV!";
            $msg_type = "success";
        } else {
            $msg = "Gagal membaca file CSV.";
            $msg_type = "danger";
        }
    }
}

// --- PROSES IMPORT LINK GOOGLE SHEETS (TABEL ADMIN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_sheets_admin'])) {
    $url_sheets = trim($_POST['url_sheets'] ?? '');
    if (strpos($url_sheets, 'edit') !== false) {
        $url_sheets = preg_replace('/\/edit.*$/', '/export?format=csv', $url_sheets);
    }

    if (!empty($url_sheets)) {
        $csv_data = @file_get_contents($url_sheets);
        if ($csv_data !== false) {
            $lines = explode(PHP_EOL, $csv_data);
            $sukses = 0;
            foreach ($lines as $index => $line) {
                if ($index === 0 || empty(trim($line))) continue; 
                $data = str_getcsv($line);
                if (count($data) >= 2) {
                    $username = trim($data[0]);
                    $password = password_hash(trim($data[1]), PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)");
                    $stmt->bind_param("ss", $username, $password);
                    if ($stmt->execute()) { $sukses++; }
                    $stmt->close();
                }
            }
            $msg = "Berhasil menyinkronkan dan mengimpor $sukses data admin dari Google Sheets!";
            $msg_type = "success";
        } else {
            $msg = "Gagal mengakses link Google Sheets. Pastikan akses spreadsheet diset 'Anyone with the link can view'.";
            $msg_type = "danger";
        }
    } else {
        $msg = "URL Google Sheets tidak boleh kosong.";
        $msg_type = "danger";
    }
}

// --- PROSES HAPUS AKUN ADMIN ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_admin' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $cek_admin = $conn->query("SELECT COUNT(*) as total FROM admin");
    $data_admin = $cek_admin->fetch_assoc();
    
    if ($data_admin['total'] > 1) {
        $conn->query("DELETE FROM admin WHERE id = $id");
        header("Location: admin.php?menu=akun_admin");
        exit;
    } else {
        $msg = "Gagal menghapus! Harus ada minimal 1 akun admin di dalam sistem.";
        $msg_type = "danger";
    }
}

// --- PROSES HAPUS DISPENSASI ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_dispensasi' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM dispensasi WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $msg = "Data pengajuan dispensasi berhasil dihapus.";
        $msg_type = "success";
    }
}

$default_kepsek = $_SESSION['config_kepsek'] ?? 'Trisnoto, S.Pd., M.M';
$default_logo = file_exists('assets/logo.png') ? 'assets/logo.png' : (file_exists('assets/logo.jpg') ? 'assets/logo.jpg' : (file_exists('assets/logo.jpeg') ? 'assets/logo.jpeg' : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'60\' height=\'60\'%3E%3Crect width=\'60\' height=\'60\' fill=\'%23e2e8f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'10\' fill=\'%2364748b\'%3ELogo%3C/text%3E%3C/svg%3E'));
$default_ttd = file_exists('assets/ttd.png') ? 'assets/ttd.png' : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'120\' height=\'50\'%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-size=\'10\' fill=\'%2364748b\'%3ETTd PNG%3C/text%3E%3C/svg%3E';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Admin - SIMPAN MAN 1 Kota Madiun</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Navigation */
        .sidebar { width: 260px; background: #0f172a; color: #fff; display: flex; flex-direction: column; justify-content: space-between;  z-index: 1200; flex-shrink: 0; }
        .sidebar.hide { margin-left: -260px; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #1e293b; text-align: center; }
        .sidebar-header h2 { margin: 0; font-size: 18px; color: #f8fafc; }
        .sidebar-header p { margin: 5px 0 0; font-size: 12px; color: #94a3b8; }
        
        .sidebar-menu { padding: 15px 0; flex: 1; overflow-y: auto; }
        .sidebar-menu a { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 500; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #1e293b; color: #fff; border-left-color: #2563eb; }
        
        .sidebar-footer { padding: 15px 20px; border-top: 1px solid #1e293b; }
        .btn-logout { display: block; text-align: center; background: #dc2626; color: #fff; padding: 10px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-logout:hover { background: #b91c1c; }

        /* Main Wrapper & Top Navbar */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; min-width: 0; }
        
        .top-navbar { background: #ffffff; padding: 12px 20px; border-bottom: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; position: sticky; top: 0; z-index: 1100; }
        .btn-toggle { background: #2563eb; border: none; color: #ffffff; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .btn-toggle:hover { background: #1d4ed8; }

        .main-content { flex: 1; padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .card { background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #0f172a; font-size: 18px; }
        
        /* Table Responsiveness */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #f1f5f9; color: #334155; font-weight: 600; }
        
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; border: none; cursor: pointer; margin: 2px; }
        .btn-print { background: #0284c7; color: #fff; }
        .btn-print:hover { background: #0369a1; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }

        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }

        /* Flexible Layout Grids */
        .custom-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="file"], .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-family: inherit; }
        
        #loading-container { display: none; margin-top: 20px; }
        .progress-bar-wrapper { width: 100%; background: #e2e8f0; border-radius: 6px; overflow: hidden; height: 16px; margin-bottom: 10px; }
        .progress-bar { width: 0%; height: 100%; background: #2563eb; transition: width 0.4s ease; }
        #sync-log-box { background: #0f172a; color: #38bdf8; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; margin-top: 10px; }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1150; }

        /* Media Queries untuk Handphone & Tablet */
        @media (max-width: 900px) {
            .custom-grid, .grid-2 { grid-template-columns: 1fr; }
            .sidebar { position: fixed; height: 100vh; left: 0; top: 0; box-shadow: 4px 0 10px rgba(0,0,0,0.3); }
            .sidebar.hide { margin-left: -260px; }
            .sidebar:not(.hide) ~ .sidebar-overlay { display: block; }
            .main-content { padding: 12px; }
            input[type="text"], input[type="password"], input[type="file"], select { font-size: 16px !important; }
        }
    </style>
</head>
<body>

<div class="sidebar hide" id="sidebar">
    <div>
        <div class="sidebar-header">
            <h2>SIMPAN MAN 1 Kota Madiun</h2>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="admin.php?menu=cetak" class="<?= $menu === 'cetak' ? 'active' : ''; ?>"><i class="fa-solid fa-print"></i> Cetak Surat</a>
            <a href="admin.php?menu=riwayat" class="<?= $menu === 'riwayat' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Pengajuan</a>
            <a href="admin.php?menu=kustom" class="<?= $menu === 'kustom' ? 'active' : ''; ?>"><i class="fa-solid fa-sliders"></i> Kustom Surat</a>
            <a href="admin.php?menu=akun_admin" class="<?= $menu === 'akun_admin' ? 'active' : ''; ?>"><i class="fa-solid fa-user-gear"></i> Akun Admin</a>
            <a href="manajemen_akun.php?menu=" class="<?= $menu === 'manajemen_akun' ? 'active' : ''; ?>"><i class="fa-solid fa-users-gear"></i> Manajemen Akun</a>
        </div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar Sistem</a>
    </div>
</div>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-wrapper">
    <div class="top-navbar">
        <button class="btn-toggle" onclick="toggleSidebar()" title="Buka/Tutup Menu">
            <i class="fa-solid fa-bars"></i> <span>Menu</span>
        </button>
        <span style="font-size: 13px; font-weight: 600; color: #64748b;">MAN 1 Kota Madiun</span>
    </div>

    <div class="main-content">
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msg_type; ?>"><?= $msg; ?></div>
        <?php endif; ?>

        <!-- MENU 1: CETAK SURAT -->
        <?php if ($menu === 'cetak'): ?>
            <div class="card">
                <h3>Pusat Cetak & Kelola Arsip Surat Dispensasi</h3>
                <p style="color: #64748b; font-size: 13px;">Daftar rekapitulasi pengajuan dispensasi. Anda dapat mengubah status, mencetak, atau menghapus arsip.</p>
                <?php $result = $conn->query("SELECT * FROM dispensasi ORDER BY id DESC"); ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">No</th>
                                <th>Nama Siswa / Kelas</th>
                                <th>Nama Kegiatan</th>
                                <th>Jadwal & Tempat</th>
                                <th style="width: 90px; text-align: center;">Status</th>
                                <th style="width: 170px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td style="text-align: center;"><?= $no++; ?></td>
                                    <td>
                                        <?php 
                                        $teks_siswa = $row['nama_siswa'] ?? '';
                                        $pecah_nama = array_filter(array_map('trim', preg_split('/(,|\r\n|\n)/', $teks_siswa)));
                                        $total_siswa_dispensasi = count($pecah_nama) > 0 ? count($pecah_nama) : 1;

                                        if ($total_siswa_dispensasi > 2) {
                                            $ringkas_nama = implode(', ', array_slice($pecah_nama, 0, 2)) . " dan " . ($total_siswa_dispensasi - 2) . " siswa lainnya";
                                            echo "<strong>" . htmlspecialchars($ringkas_nama) . "</strong>";
                                        } else {
                                            echo "<strong>" . htmlspecialchars($teks_siswa) . "</strong>";
                                        }
                                        ?>
                                        <br><small style="color: #64748b;">Kelas: <?= htmlspecialchars($row['kelas']); ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan']); ?></td>
                                    <td><?= htmlspecialchars($row['jadwal_dispensasi']); ?><br><small style="color: #64748b;">Lokasi: <?= htmlspecialchars($row['tempat']); ?></small></td>
                                    <td style="text-align: center;"><span style="font-weight: 600; color: <?= strtolower($row['status']) === 'diterima' ? '#166534' : (strtolower($row['status']) === 'ditolak' ? '#991b1b' : '#d97706'); ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                                    <td style="text-align: center;">
                                        <a href="admin.php?menu=cetak&action=update_status&id=<?= $row['id']; ?>&status=Diterima" class="btn btn-success" onclick="return confirm('Terima pengajuan ini?')">Terima</a>
                                        <a href="cetak.php?id=<?= $row['id']; ?>" target="_blank" class="btn btn-print">Cetak</a>
                                        <a href="admin.php?menu=cetak&action=hapus_dispensasi&id=<?= $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus arsip surat ini?')">Hapus</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">Belum ada arsip surat dispensasi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- MENU 2: RIWAYAT -->
        <?php elseif ($menu === 'riwayat'): ?>
            <div class="card">
                <h3>Riwayat Pengajuan Siswa</h3>
                <?php $res_riwayat = $conn->query("SELECT * FROM dispensasi ORDER BY id DESC"); ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Kegiatan</th>
                                <th>Waktu</th>
                                <th>Nama Siswa</th>
                                <th style="text-align: center;">Jumlah Siswa</th>
                                <th>Nama Pemohon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_riwayat && $res_riwayat->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $res_riwayat->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan']); ?></td>
                                    <td><?= htmlspecialchars($row['jadwal_dispensasi']); ?></td>
                                    <td>
                                        <?php 
                                        $teks_siswa = $row['nama_siswa'] ?? '';
                                        $pecah_nama = array_filter(array_map('trim', preg_split('/(,|\r\n|\n)/', $teks_siswa)));
                                        $total_siswa_dispensasi = count($pecah_nama) > 0 ? count($pecah_nama) : 1;

                                        if ($total_siswa_dispensasi > 2) {
                                            $ringkas_nama = implode(', ', array_slice($pecah_nama, 0, 2)) . " dan " . ($total_siswa_dispensasi - 2) . " siswa lainnya";
                                            echo htmlspecialchars($ringkas_nama);
                                        } else {
                                            echo htmlspecialchars($teks_siswa);
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: center;"><?= htmlspecialchars($total_siswa_dispensasi); ?> Siswa</td>
                                    <td>
                                        <?php 
                                        $nama_pemohon = !empty($row['nama_pemohon']) ? $row['nama_pemohon'] : (!empty($row['pemohon']) ? $row['pemohon'] : (!empty($row['diajukan_oleh']) ? $row['diajukan_oleh'] : '-'));
                                        echo htmlspecialchars($nama_pemohon); 
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">Belum ada riwayat pengajuan dispensasi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- MENU 3: KUSTOM SURAT -->
        <?php elseif ($menu === 'kustom'): ?>
            <div class="card">
                <h3>Pengaturan & Kustomisasi Template Surat</h3>
                <div class="custom-grid">
                    <form action="admin.php?menu=kustom" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_kustom" value="1">
                        <div class="form-group">
                            <label>Ganti Logo Madrasah:</label>
                            <input type="file" name="logo" accept=".png, .jpg, .jpeg" onchange="previewImage(event, 'preview-logo-img')">
                        </div>
                        <div class="form-group">
                            <label>Tanda Tangan Kepala Madrasah:</label>
                            <input type="file" name="ttd" accept=".png" onchange="previewImage(event, 'preview-ttd-img')">
                        </div>
                        <div class="form-group">
                            <label>Nama & Gelar Kepala Madrasah:</label>
                            <input type="text" name="nama_kepsek" value="<?= htmlspecialchars($default_kepsek); ?>" oninput="updateText('nama_kepsek', 'preview-kepsek-text')">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Simpan Perubahan</button>
                    </form>

                    <div style="border: 1px dashed #cbd5e1; padding: 20px; text-align: center; border-radius: 8px; background: #fafafa;">
                         <p style="color: #64748b; font-size: 13px;">(Pratinjau Elemen Surat)</p>
                         <img id="preview-logo-img" src="<?= $default_logo; ?>" style="max-width: 60px; height: auto; margin-bottom: 10px;"><br>
                         <img id="preview-ttd-img" src="<?= $default_ttd; ?>" style="max-width: 100px; height: auto; margin-bottom: 10px;">
                         <p id="preview-kepsek-text" style="font-weight: bold; margin: 0; font-size: 14px;"><?= htmlspecialchars($default_kepsek); ?></p>
                    </div>
                </div>
            </div>

        <!-- MENU 4: MANAJEMEN AKUN ADMIN -->
        <?php elseif ($menu === 'akun_admin'): ?>
            <div class="card">
                <h3>Manajemen Akun Admin</h3>
                <p style="color: #64748b; font-size: 13px;">Kelola akun admin secara manual, via file CSV, atau sinkronisasi Google Sheets.</p>

                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <h4 style="margin-top:0; font-size:14px; color:#0f172a;"><i class="fa-solid fa-user-plus"></i> Tambah Admin Baru</h4>
                    <form action="admin.php?menu=akun_admin" method="POST">
                        <input type="hidden" name="tambah_admin" value="1">
                        <div class="grid-2">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Username:</label>
                                <input type="text" name="username" placeholder="Username baru" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Password:</label>
                                <input type="password" name="password" placeholder="Password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" style="margin-top: 12px; padding: 10px 16px;">Simpan Admin Baru</button>
                    </form>
                </div>

                <div class="grid-2">
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h4 style="margin-top:0; font-size:14px; color:#0f172a;">Import via CSV Lokal</h4>
                        <p style="font-size:11px; color:#64748b;">Format: Kolom 1 = Username, Kolom 2 = Password</p>
                        <form action="admin.php?menu=akun_admin" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="import_csv_admin" value="1">
                            <div class="form-group"><input type="file" name="file_csv" accept=".csv" required></div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">Import CSV Admin</button>
                        </form>
                    </div>

                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h4 style="margin-top:0; font-size:14px; color:#0f172a;">Sinkron via Google Sheets</h4>
                        <p style="font-size:11px; color:#64748b;">Akses diset ke <i>'Anyone with link'</i>.</p>
                        <form action="admin.php?menu=akun_admin" method="POST">
                            <input type="hidden" name="import_sheets_admin" value="1">
                            <div class="form-group"><input type="text" name="url_sheets" placeholder="Link Google Sheets" required></div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">Tarik & Update Admin</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <h4 style="margin-top:0; font-size:16px; color:#0f172a;">Daftar Akun Admin Terdaftar</h4>
                <?php $res_admin = $conn->query("SELECT id, username FROM admin ORDER BY id DESC"); ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Username Admin</th>
                                <th style="width: 100px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_admin && $res_admin->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $res_admin->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><code><?= htmlspecialchars($row['username']); ?></code></td>
                                    <td style="text-align: center;">
                                        <a href="admin.php?menu=akun_admin&action=hapus_admin&id=<?= $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus admin ini?')">Hapus</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align: center; color: #64748b; padding: 20px;">Belum ada data admin.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- MENU 5: SINKRONISASI DISPENSASI -->
        <?php elseif ($menu === 'sinkron'): ?>
            <div class="card">
                <h3>Sinkronisasi Data Web App Sheets (Dispensasi)</h3>
                <div class="form-group">
                    <label>URL Web App Google Apps Script:</label>
                    <input type="text" id="url_api" placeholder="https://script.google.com/macros/s/.../exec">
                </div>
                <button type="button" class="btn btn-primary" style="padding: 12px 20px;" onclick="mulaiSinkronisasi()">Mulai Sinkronisasi Data Siswa</button>

                <div id="loading-container">
                    <label style="font-weight: 600; font-size: 13px; margin-bottom: 5px; display: block;">Progres Sinkronisasi:</label>
                    <div class="progress-bar-wrapper"><div id="progress-bar" class="progress-bar"></div></div>
                    <div id="sync-log-box">Menyiapkan koneksi...</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebarState = localStorage.getItem('sidebar_state');
        const sidebar = document.getElementById('sidebar');
        // Pada layar HP default ditutup
        if (window.innerWidth <= 900) {
            sidebar.classList.add('hide');
        } else {
            if (sidebarState === 'hidden') {
                sidebar.classList.add('hide');
            } else {
                sidebar.classList.remove('hide');
            }
        }
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('hide');
        if (sidebar.classList.contains('hide')) {
            localStorage.setItem('sidebar_state', 'hidden');
        } else {
            localStorage.setItem('sidebar_state', 'visible');
        }
    }

    function previewImage(event, targetId) {
        const reader = new FileReader();
        reader.onload = function(){ document.getElementById(targetId).src = reader.result; };
        if(event.target.files[0]) { reader.readAsDataURL(event.target.files[0]); }
    }
    
    function updateText(inputId, targetId) {
        document.getElementById(targetId).textContent = document.getElementById(inputId).value;
    }

    async function mulaiSinkronisasi() {
        const url = document.getElementById('url_api').value.trim();
        const logBox = document.getElementById('sync-log-box');
        const progressBar = document.getElementById('progress-bar');
        const container = document.getElementById('loading-container');

        if (!url) { alert('URL Web App Google Sheets belum diisi!'); return; }

        container.style.display = 'block';
        logBox.innerHTML = "[INFO] Menghubungkan ke Web App...<br>";
        progressBar.style.width = '30%';

        try {
            const response = await fetch(url);
            progressBar.style.width = '60%';
            const jsonData = await response.json();
            progressBar.style.width = '80%';
            logBox.innerHTML += "[SUKSES] Sinkronisasi data siswa selesai diproses.<br>";
            progressBar.style.width = '100%';
        } catch (error) {
            progressBar.style.background = '#dc2626';
            logBox.innerHTML += `<br><span style="color: #f87171;">[GAGAL] Terjadi kesalahan: ${error.message}</span>`;
        }
    }
</script>

</body>
</html>