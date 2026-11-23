<?php
$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("Koneksi Database Gagal: " . $conn->connect_error); }

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dispensasi'])) {
    $list_siswa_input  = $_POST['nama_siswa'] ?? [];
    $kelas_utama       = $_POST['kelas_utama'] ?? '';
    $nama_kegiatan     = $_POST['nama_kegiatan'] ?? '';
    
    // TANGGAL & JAM PICKER
    $tgl_mulai         = $_POST['tgl_mulai'] ?? '';
    $tgl_selesai       = $_POST['tgl_selesai'] ?? '';
    $jam_mulai         = $_POST['jam_mulai'] ?? '';
    $jam_selesai       = $_POST['jam_selesai'] ?? '';

    if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
        if ($tgl_mulai === $tgl_selesai) {
            $jadwal_dispensasi = date('d-m-Y', strtotime($tgl_mulai)) . " (Pukul " . $jam_mulai . " - " . $jam_selesai . " WIB)";
        } else {
            $jadwal_dispensasi = date('d-m-Y', strtotime($tgl_mulai)) . " s/d " . date('d-m-Y', strtotime($tgl_selesai)) . " (Pukul " . $jam_mulai . " - " . $jam_selesai . " WIB)";
        }
    } else {
        $jadwal_dispensasi = "-";
    }

    $tempat            = $_POST['tempat'] ?? '';
    $guru_pendamping   = $_POST['guru_pendamping'] ?? '';
    $alasan            = $_POST['alasan'] ?? '';
    
    $nama_siswa_gabungan = is_array($list_siswa_input) ? implode(", ", array_filter($list_siswa_input)) : $list_siswa_input;

    $sql = "INSERT INTO dispensasi (nama_siswa, kelas, nama_kegiatan, jadwal_dispensasi, tempat, guru_pendamping, alasan, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $nama_siswa_gabungan, $kelas_utama, $nama_kegiatan, $jadwal_dispensasi, $tempat, $guru_pendamping, $alasan);
    
    if ($stmt->execute()) {
        $msg = "Pengajuan dispensasi berhasil dikirim!";
        $msg_type = "success";
    } else {
        $msg = "Gagal menyimpan data: " . $stmt->error;
        $msg_type = "error";
    }
}

// FETCH DATA DROP-DOWN
$list_kelas = [];
$res_kelas = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
if ($res_kelas) { while ($k = $res_kelas->fetch_assoc()) { $list_kelas[] = $k['kelas']; } }

$list_siswa = [];
$res_siswa = $conn->query("SELECT nama, kelas FROM siswa ORDER BY nama ASC");
if ($res_siswa) { while ($s = $res_siswa->fetch_assoc()) { $list_siswa[] = $s; } }

$list_guru = [];
$res_guru = $conn->query("SELECT nama_guru, nip FROM guru ORDER BY nama_guru ASC");
if ($res_guru) { while ($g = $res_guru->fetch_assoc()) { $list_guru[] = $g; } }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Dispensasi - MAN 1 Kota Madiun</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 680px; margin: 20px auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .nav-menu { display: flex; gap: 10px; margin-bottom: 20px; }
        .nav-menu a { flex: 1; text-align: center; padding: 10px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .nav-active { background: #2563eb; color: #fff; }
        .nav-inactive { background: #e2e8f0; color: #475569; }

        .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; }
        .header h2 { margin: 0; color: #0f172a; font-size: 22px; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 14px; }
        
        .form-group { margin-bottom: 18px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #334155; }
        input[type="text"], input[type="date"], input[type="time"], textarea, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background-color: #fff; }
        
        .siswa-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .siswa-row select { flex: 1; }
        .btn-remove { background: #ef4444; color: #fff; border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-add { background: #059669; color: #fff; border: none; padding: 9px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-top: 5px; }

        .btn-submit { width: 100%; background: #2563eb; color: #fff; padding: 12px; border: none; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; margin-top: 15px; }
        .btn-submit:hover { background: #1d4ed8; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- SUB MENU / NAVBAR -->
    <div class="nav-menu">
        <a href="index.php" class="nav-active">Dispensasi Individu / Per-Siswa</a>
        <a href="dispensasi_rombongan.php" class="nav-inactive">Dispensasi Rombongan / Banyak Siswa</a>
    </div>

    <div class="header">
        <h2>E-DISPENSASI SISWA</h2>
        <p>MAN 1 Kota Madiun</p>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msg_type; ?>"><?= $msg; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        
        <div class="form-group">
            <label for="kelas_utama">1. Pilih Kelas Utama / Asal Kegiatan:</label>
            <select name="kelas_utama" id="kelas_utama" required onchange="updateAllSiswaDropdowns()">
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($list_kelas as $kls): ?>
                    <option value="<?= htmlspecialchars($kls); ?>"><?= htmlspecialchars($kls); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>2. Pilih Nama Siswa:</label>
            <div id="container_siswa">
                <div class="siswa-row">
                    <select name="nama_siswa[]" class="select-siswa" required disabled>
                        <option value="">-- Pilih Kelas Utama Terlebih Dahulu --</option>
                    </select>
                </div>
            </div>
            
            <button type="button" class="btn-add" onclick="addSiswaRow()">+ Tambah Siswa Lain</button>
        </div>

        <div class="form-group">
            <label for="nama_kegiatan">Nama Kegiatan / Keperluan:</label>
            <input type="text" name="nama_kegiatan" placeholder="Contoh: Lomba LKS / Petugas Upacara" required>
        </div>

        <!-- DATE PICKER TANGGAL MULAI & SELESAI -->
        <div class="form-group">
            <div class="grid-2">
                <div>
                    <label for="tgl_mulai">Tanggal Mulai:</label>
                    <input type="date" name="tgl_mulai" id="tgl_mulai" required>
                </div>
                <div>
                    <label for="tgl_selesai">Tanggal Selesai:</label>
                    <input type="date" name="tgl_selesai" id="tgl_selesai" required>
                </div>
            </div>
        </div>

        <!-- TIME PICKER JAM MULAI & SELESAI -->
        <div class="form-group">
            <div class="grid-2">
                <div>
                    <label for="jam_mulai">Jam Mulai:</label>
                    <input type="time" name="jam_mulai" id="jam_mulai" required>
                </div>
                <div>
                    <label for="jam_selesai">Jam Selesai:</label>
                    <input type="time" name="jam_selesai" id="jam_selesai" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="tempat">Tempat / Lokasi Kegiatan:</label>
            <input type="text" name="tempat" placeholder="Contoh: Aula MAN 1 Kota Madiun" required>
        </div>

        <div class="form-group">
            <label for="guru_pendamping">Guru Pendamping / Pembina:</label>
            <select name="guru_pendamping" id="guru_pendamping" required>
                <option value="">-- Pilih Guru Pendamping --</option>
                <?php foreach ($list_guru as $guru): ?>
                    <option value="<?= htmlspecialchars($guru['nama_guru']); ?>">
                        <?= htmlspecialchars($guru['nama_guru']); ?> <?= !empty($guru['nip']) && $guru['nip'] != '-' ? '('.$guru['nip'].')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="alasan">Alasan / Keterangan Tambahan:</label>
            <textarea name="alasan" rows="3" placeholder="Masukkan alasan atau rincian tambahan..." required></textarea>
        </div>

        <button type="submit" name="submit_dispensasi" class="btn-submit">Kirim Pengajuan Dispensasi</button>
    </form>
</div>

<script>
    const dataSiswa = <?= json_encode($list_siswa); ?>;

    function updateAllSiswaDropdowns() {
        const selectedKelas = document.getElementById('kelas_utama').value;
        const allSelects = document.querySelectorAll('.select-siswa');

        allSelects.forEach(select => {
            populateSelectOptions(select, selectedKelas);
        });
    }

    function populateSelectOptions(selectElement, kelas) {
        selectElement.innerHTML = '';

        if (!kelas) {
            selectElement.disabled = true;
            selectElement.innerHTML = '<option value="">-- Pilih Kelas Utama Terlebih Dahulu --</option>';
            return;
        }

        const filteredSiswa = dataSiswa.filter(s => s.kelas === kelas);

        if (filteredSiswa.length > 0) {
            selectElement.disabled = false;
            selectElement.innerHTML = '<option value="">-- Pilih Nama Siswa --</option>';
            filteredSiswa.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.nama;
                opt.textContent = s.nama;
                selectElement.appendChild(opt);
            });
        } else {
            selectElement.disabled = true;
            selectElement.innerHTML = '<option value="">-- Tidak Ada Data Siswa di Kelas Ini --</option>';
        }
    }

    function addSiswaRow() {
        const container = document.getElementById('container_siswa');
        const selectedKelas = document.getElementById('kelas_utama').value;

        const div = document.createElement('div');
        div.className = 'siswa-row';

        const select = document.createElement('select');
        select.name = 'nama_siswa[]';
        select.className = 'select-siswa';
        select.required = true;

        populateSelectOptions(select, selectedKelas);

        const btnRemove = document.createElement('button');
        btnRemove.type = 'button';
        btnRemove.className = 'btn-remove';
        btnRemove.textContent = '✕';
        btnRemove.onclick = function() {
            container.removeChild(div);
        };

        div.appendChild(select);
        div.appendChild(btnRemove);
        container.appendChild(div);
    }
</script>

</body>
</html>