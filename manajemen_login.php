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

// Buat tabel user_login jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS user_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

$msg = "";
$msg_type = "";

// PROSES IMPORT DARI FILE CSV LOKAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            $row_count = 0;
            $sukses = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                if ($row_count === 1) continue; // Lewati baris header (Nama, User, Password)
                
                if (!empty($data[0]) && !empty($data[1]) && !empty($data[2])) {
                    $nama = trim($data[0]);
                    $username = trim($data[1]);
                    // Password di-hash menggunakan password_hash agar aman
                    $password = password_hash(trim($data[2]), PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO user_login (nama, username, password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama = VALUES(nama), password = VALUES(password)");
                    $stmt->bind_param("sss", $nama, $username, $password);
                    if ($stmt->execute()) {
                        $sukses++;
                    }
                    $stmt->close();
                }
            }
            fclose($handle);
            $msg = "Berhasil mengimpor $sukses data user login dari file CSV!";
            $msg_type = "success";
        } else {
            $msg = "Gagal membaca file CSV.";
            $msg_type = "danger";
        }
    }
}

// PROSES IMPORT DARI LINK CSV GOOGLE SHEETS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_sheets'])) {
    $url_sheets = trim($_POST['url_sheets'] ?? '');
    
    // Konversi link Google Sheets biasa menjadi link export format CSV jika diperlukan
    if (strpos($url_sheets, 'edit') !== false) {
        $url_sheets = preg_replace('/\/edit.*$/', '/export?format=csv', $url_sheets);
    }

    if (!empty($url_sheets)) {
        // Mengambil data CSV dari URL menggunakan cURL / file_get_contents
        $csv_data = @file_get_contents($url_sheets);
        if ($csv_data !== false) {
            $lines = explode(PHP_EOL, $csv_data);
            $sukses = 0;
            foreach ($lines as $index => $line) {
                if ($index === 0 || empty(trim($line))) continue; // Lewati header/baris kosong
                
                $data = str_getcsv($line);
                if (count($data) >= 3) {
                    $nama = trim($data[0]);
                    $username = trim($data[1]);
                    $password = password_hash(trim($data[2]), PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO user_login (nama, username, password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama = VALUES(nama), password = VALUES(password)");
                    $stmt->bind_param("sss", $nama, $username, $password);
                    if ($stmt->execute()) {
                        $sukses++;
                    }
                    $stmt->close();
                }
            }
            $msg = "Berhasil menyinkronkan dan mengimpor $sukses data user dari Google Sheets!";
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

// PROSES HAPUS USER
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM user_login WHERE id = $id");
    header("Location: manajemen_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User Login - E-Dispensasi</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #0f172a; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; vertical-align: middle; }
        th { background-color: #f1f5f9; color: #334155; font-weight: 600; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #64748b; color: #fff; text-decoration: none; margin-bottom: 15px; display: inline-block;}
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
        .form-group input[type="text"], .form-group input[type="file"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>

<div class="container">
    <a href="admin.php" class="btn btn-secondary">&larr; Kembali ke Panel Admin Utama</a>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msg_type; ?>"><?= $msg; ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Manajemen User Login (Nama, User, Password)</h3>
        <p style="color: #64748b; font-size: 13px;">Kelola akun yang dapat masuk ke sistem. Anda bisa mengunggah file CSV atau menghubungkannya langsung menggunakan Link Google Sheets.</p>

        <div class="grid-2" style="margin-top: 20px;">
            <!-- Opsi 1: Upload File CSV -->
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <h4 style="margin-top:0; font-size:14px; color:#0f172a;">Import dari File CSV Lokal</h4>
                <p style="font-size:11px; color:#64748b;">Format kolom baris pertama: <code>nama, username, password</code></p>
                <form action="manajemen_login.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="import_csv" value="1">
                    <div class="form-group">
                        <input type="file" name="file_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload & Import CSV</button>
                </form>
            </div>

            <!-- Opsi 2: Sinkronisasi Link Google Sheets -->
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <h4 style="margin-top:0; font-size:14px; color:#0f172a;">Import dari Link Google Sheets (CSV)</h4>
                <p style="font-size:11px; color:#64748b;">Pastikan Google Sheets dibagikan secara publik (Anyone with the link).</p>
                <form action="manajemen_login.php" method="POST">
                    <input type="hidden" name="import_sheets" value="1">
                    <div class="form-group">
                        <input type="text" name="url_sheets" placeholder="https://docs.google.com/spreadsheets/d/.../edit" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Tarik & Sinkron Sheets</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar User Login -->
    <div class="card">
        <h4 style="margin-top:0; font-size:16px; color:#0f172a;">Daftar Akun User Login Saat Ini</h4>
        <?php $result = $conn->query("SELECT id, nama, username FROM user_login ORDER BY id DESC"); ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th style="width: 100px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><code><?= htmlspecialchars($row['username']); ?></code></td>
                        <td style="text-align: center;">
                            <a href="manajemen_login.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus user login ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 20px;">Belum ada data user login. Silakan import melalui CSV atau Google Sheets.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>