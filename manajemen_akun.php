<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login_admin.php");
    exit;
}

// ================= DOWNLOAD TEMPLATE CSV =================
if (isset($_GET['template'])) {
    $t = $_GET['template'];
    if ($t === 'siswa') {
        $rows = [['nis', 'nama', 'kelas'], ['12345', 'Ahmad Fauzi', 'X-1'], ['12346', 'Siti Aisyah', 'X-1']];
    } elseif ($t === 'guru') {
        $rows = [['nip', 'nama_guru'], ['198001012005011001', 'Drs. Contoh Guru, M.Pd'], ['-', 'Guru Tanpa NIP, S.Pd']];
    } else { exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_' . $t . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM agar Excel membaca UTF-8
    $out = fopen('php://output', 'w');
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Koneksi Database Gagal: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// Buat tabel jika belum ada (tidak mengubah tabel yang sudah ada)
$conn->query("CREATE TABLE IF NOT EXISTS siswa (
    nis VARCHAR(30) NOT NULL PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    kelas VARCHAR(30) NOT NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(30) NOT NULL DEFAULT '-',
    nama_guru VARCHAR(150) NOT NULL
)");

// Migrasi: jika PRIMARY KEY tabel guru masih di kolom nip, ganti ke kolom id otomatis
// agar banyak guru tanpa NIP ('-') bisa disimpan. Data lama tidak dihapus.
try {
    $pk = $conn->query("SHOW KEYS FROM guru WHERE Key_name = 'PRIMARY'");
    if ($pk && $pk->num_rows > 0) {
        $kolom_pk = $pk->fetch_assoc()['Column_name'];
        if ($kolom_pk === 'nip') {
            $conn->query("ALTER TABLE guru DROP PRIMARY KEY");
            $cek_id = $conn->query("SHOW COLUMNS FROM guru LIKE 'id'");
            if ($cek_id && $cek_id->num_rows === 0) {
                $conn->query("ALTER TABLE guru ADD id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            }
            $conn->query("ALTER TABLE guru ADD INDEX idx_nip (nip)");
        }
    }
} catch (mysqli_sql_exception $e) {
    die("Gagal menyesuaikan struktur tabel guru: " . htmlspecialchars($e->getMessage()));
}

// ================= FUNGSI SIMPAN (INSERT / UPDATE) =================
function simpan_siswa($conn, $nis, $nama, $kelas) {
    $nis = trim($nis); $nama = trim($nama); $kelas = trim($kelas);
    if ($nis === '' || $nama === '' || $kelas === '') return false;
    $c = $conn->prepare("SELECT 1 FROM siswa WHERE nis = ?");
    $c->bind_param("s", $nis); $c->execute();
    $ada = $c->get_result()->num_rows > 0; $c->close();
    if ($ada) {
        $s = $conn->prepare("UPDATE siswa SET nama = ?, kelas = ? WHERE nis = ?");
        $s->bind_param("sss", $nama, $kelas, $nis);
    } else {
        $s = $conn->prepare("INSERT INTO siswa (nis, nama, kelas) VALUES (?, ?, ?)");
        $s->bind_param("sss", $nis, $nama, $kelas);
    }
    try { $ok = $s->execute(); } catch (mysqli_sql_exception $e) { $ok = false; }
    $s->close();
    return $ok;
}

function simpan_guru($conn, $nip, $nama) {
    $nip = trim($nip); $nama = trim($nama);
    if ($nama === '') return false;
    if ($nip === '') $nip = '-';
    // NIP '-' dianggap tanpa NIP, dicocokkan lewat nama
    if ($nip !== '-') {
        $c = $conn->prepare("SELECT 1 FROM guru WHERE nip = ?");
        $c->bind_param("s", $nip);
    } else {
        $c = $conn->prepare("SELECT 1 FROM guru WHERE nip = '-' AND nama_guru = ?");
        $c->bind_param("s", $nama);
    }
    $c->execute();
    $ada = $c->get_result()->num_rows > 0; $c->close();
    if ($ada) {
        if ($nip === '-') return true;
        $s = $conn->prepare("UPDATE guru SET nama_guru = ? WHERE nip = ?");
        $s->bind_param("ss", $nama, $nip);
    } else {
        $s = $conn->prepare("INSERT INTO guru (nip, nama_guru) VALUES (?, ?)");
        $s->bind_param("ss", $nip, $nama);
    }
    try { $ok = $s->execute(); } catch (mysqli_sql_exception $e) { $ok = false; }
    $s->close();
    return $ok;
}

// Baca CSV (otomatis deteksi pemisah koma / titik koma, abaikan header & BOM)
function baca_csv($path) {
    $rows = [];
    $h = fopen($path, "r");
    if (!$h) return $rows;
    $first = fgets($h);
    rewind($h);
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $i = 0;
    while (($d = fgetcsv($h, 2000, $delim)) !== false) {
        if ($i === 0 && isset($d[0])) $d[0] = preg_replace('/^\xEF\xBB\xBF/', '', $d[0]);
        $i++;
        if ($i === 1 && in_array(strtolower(trim($d[0] ?? '')), ['nis', 'nip'])) continue;
        if (count(array_filter($d, fn($x) => trim($x) !== '')) === 0) continue;
        $rows[] = $d;
    }
    fclose($h);
    return $rows;
}

$msg = ""; $msg_type = "";
$tab = $_GET['tab'] ?? 'siswa';
if (!in_array($tab, ['siswa', 'guru'])) $tab = 'siswa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah_siswa') {
        $tab = 'siswa';
        if (simpan_siswa($conn, $_POST['nis'] ?? '', $_POST['nama'] ?? '', $_POST['kelas'] ?? '')) {
            $msg = "Data siswa berhasil disimpan."; $msg_type = "success";
        } else { $msg = "Gagal menyimpan. Pastikan NIS, nama, dan kelas terisi."; $msg_type = "danger"; }

    } elseif ($aksi === 'tambah_guru') {
        $tab = 'guru';
        if (simpan_guru($conn, $_POST['nip'] ?? '', $_POST['nama_guru'] ?? '')) {
            $msg = "Data guru berhasil disimpan."; $msg_type = "success";
        } else { $msg = "Gagal menyimpan. Nama guru wajib diisi."; $msg_type = "danger"; }

    } elseif ($aksi === 'import_siswa' || $aksi === 'import_guru') {
        $tab = ($aksi === 'import_siswa') ? 'siswa' : 'guru';
        if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
            $rows = baca_csv($_FILES['file_csv']['tmp_name']);
            $ok = 0; $gagal = 0;
            foreach ($rows as $r) {
                $berhasil = ($tab === 'siswa')
                    ? simpan_siswa($conn, $r[0] ?? '', $r[1] ?? '', $r[2] ?? '')
                    : simpan_guru($conn, $r[0] ?? '', $r[1] ?? '');
                $berhasil ? $ok++ : $gagal++;
            }
            $msg = "Import selesai: $ok data berhasil, $gagal baris dilewati.";
            $msg_type = $ok > 0 ? "success" : "danger";
        } else { $msg = "File CSV belum dipilih atau gagal diunggah."; $msg_type = "danger"; }

    } elseif ($aksi === 'hapus_siswa') {
        $tab = 'siswa';
        $s = $conn->prepare("DELETE FROM siswa WHERE nis = ?");
        $s->bind_param("s", $_POST['nis']); $s->execute(); $s->close();
        $msg = "Data siswa dihapus."; $msg_type = "success";

    } elseif ($aksi === 'hapus_guru') {
        $tab = 'guru';
        $s = $conn->prepare("DELETE FROM guru WHERE nip = ? AND nama_guru = ?");
        $s->bind_param("ss", $_POST['nip'], $_POST['nama_guru']); $s->execute(); $s->close();
        $msg = "Data guru dihapus."; $msg_type = "success";
    }
}

$list_siswa = $conn->query("SELECT nis, nama, kelas FROM siswa ORDER BY kelas ASC, nama ASC");
$list_guru  = $conn->query("SELECT nip, nama_guru FROM guru ORDER BY nama_guru ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Akun Siswa & Guru - E-Dispensasi</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #0f172a; font-size: 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tabs a { flex: 1; text-align: center; padding: 10px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; background: #e2e8f0; color: #475569; }
        .tabs a.active { background: #2563eb; color: #fff; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 700px) { .grid-2 { grid-template-columns: 1fr; } }
        .box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .box h4 { margin: 0 0 10px; font-size: 14px; color: #0f172a; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff; }
        .btn { padding: 8px 14px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-success { background: #059669; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; padding: 5px 10px; }
        .btn-secondary { background: #64748b; color: #fff; margin-bottom: 15px; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background: #f1f5f9; color: #334155; }
        .hint { font-size: 11px; color: #64748b; margin: 0 0 10px; }
    </style>
</head>
<body>
<div class="container">
    <a href="admin.php" class="btn btn-secondary">&larr; Kembali ke Panel Admin</a>

    <?php if ($msg !== ""): ?>
        <div class="alert alert-<?= $msg_type; ?>"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=siswa" class="<?= $tab === 'siswa' ? 'active' : ''; ?>">Akun Siswa</a>
        <a href="?tab=guru" class="<?= $tab === 'guru' ? 'active' : ''; ?>">Akun Guru</a>
    </div>

<?php if ($tab === 'siswa'): ?>
    <div class="card">
        <h3>Tambah Akun Siswa (NIS, Nama, Kelas)</h3>
        <div class="grid-2">
            <div class="box">
                <h4>Tambah Manual</h4>
                <form method="POST">
                    <input type="hidden" name="aksi" value="tambah_siswa">
                    <div class="form-group"><label>NIS</label><input type="text" name="nis" required></div>
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" required></div>
                    <div class="form-group"><label>Kelas</label><input type="text" name="kelas" placeholder="Contoh: X-1" required></div>
                    <button class="btn btn-primary">Simpan</button>
                </form>
            </div>
            <div class="box">
                <h4>Import dari CSV</h4>
                <p class="hint">Kolom: <code>nis, nama, kelas</code>. NIS yang sudah ada akan diperbarui.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="import_siswa">
                    <div class="form-group"><input type="file" name="file_csv" accept=".csv" required></div>
                    <button class="btn btn-primary">Upload & Import</button>
                    <a href="?template=siswa" class="btn btn-success">Unduh Template</a>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <h4 style="margin-top:0;">Daftar Siswa (<?= $list_siswa ? $list_siswa->num_rows : 0; ?>)</h4>
        <table>
            <tr><th style="width:40px;">No</th><th>NIS</th><th>Nama</th><th>Kelas</th><th style="width:70px;">Aksi</th></tr>
            <?php if ($list_siswa && $list_siswa->num_rows > 0): $no = 1; while ($r = $list_siswa->fetch_assoc()): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['nis']); ?></td>
                <td><?= htmlspecialchars($r['nama']); ?></td>
                <td><?= htmlspecialchars($r['kelas']); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Hapus siswa ini?')">
                        <input type="hidden" name="aksi" value="hapus_siswa">
                        <input type="hidden" name="nis" value="<?= htmlspecialchars($r['nis']); ?>">
                        <button class="btn btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada data siswa.</td></tr>
            <?php endif; ?>
        </table>
    </div>

<?php else: ?>
    <div class="card">
        <h3>Tambah Akun Guru (NIP, Nama Guru)</h3>
        <div class="grid-2">
            <div class="box">
                <h4>Tambah Manual</h4>
                <form method="POST">
                    <input type="hidden" name="aksi" value="tambah_guru">
                    <div class="form-group"><label>NIP</label><input type="text" name="nip" placeholder="Isi - jika tidak ada NIP"></div>
                    <div class="form-group"><label>Nama Guru</label><input type="text" name="nama_guru" required></div>
                    <button class="btn btn-primary">Simpan</button>
                </form>
            </div>
            <div class="box">
                <h4>Import dari CSV</h4>
                <p class="hint">Kolom: <code>nip, nama_guru</code>. NIP yang sudah ada akan diperbarui.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="import_guru">
                    <div class="form-group"><input type="file" name="file_csv" accept=".csv" required></div>
                    <button class="btn btn-primary">Upload & Import</button>
                    <a href="?template=guru" class="btn btn-success">Unduh Template</a>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <h4 style="margin-top:0;">Daftar Guru (<?= $list_guru ? $list_guru->num_rows : 0; ?>)</h4>
        <table>
            <tr><th style="width:40px;">No</th><th>NIP</th><th>Nama Guru</th><th style="width:70px;">Aksi</th></tr>
            <?php if ($list_guru && $list_guru->num_rows > 0): $no = 1; while ($r = $list_guru->fetch_assoc()): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['nip']); ?></td>
                <td><?= htmlspecialchars($r['nama_guru']); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Hapus guru ini?')">
                        <input type="hidden" name="aksi" value="hapus_guru">
                        <input type="hidden" name="nip" value="<?= htmlspecialchars($r['nip']); ?>">
                        <input type="hidden" name="nama_guru" value="<?= htmlspecialchars($r['nama_guru']); ?>">
                        <button class="btn btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;color:#64748b;">Belum ada data guru.</td></tr>
            <?php endif; ?>
        </table>
    </div>
<?php endif; ?>
</div>
</body>
</html>