<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'siswa') {
    header("Location: index.php");
    exit;
}

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { 
    die("Koneksi Database Gagal: " . $conn->connect_error); 
}

$msg = "";
$msg_type = "";

if (isset($_GET['action']) && $_GET['action'] === 'get_siswa') {
    $kelas_pilihan = $_GET['kelas'] ?? '';
    $stmt_s = $conn->prepare("SELECT nama FROM siswa WHERE kelas = ? ORDER BY nama ASC");
    $stmt_s->bind_param("s", $kelas_pilihan);
    $stmt_s->execute();
    $res_s = $stmt_s->get_result();
    
    $data_siswa = [];
    while ($row = $res_s->fetch_assoc()) {
        $data_siswa[] = $row['nama'];
    }
    header('Content-Type: application/json');
    echo json_encode($data_siswa);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rombongan'])) {
    $list_siswa      = $_POST['siswa_terpilih'] ?? [];
    $kelas_list      = $_POST['kelas_terpilih'] ?? [];
    $nama_kegiatan   = $_POST['nama_kegiatan'] ?? '';
    
    $tgl_mulai       = $_POST['tgl_mulai'] ?? '';
    $tgl_selesai     = $_POST['tgl_selesai'] ?? '';
    $jam_mulai       = $_POST['jam_mulai'] ?? '';
    $jam_selesai     = $_POST['jam_selesai'] ?? '';

    if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
        if ($tgl_mulai === $tgl_selesai) {
            $jadwal_dispensasi = date('d-m-Y', strtotime($tgl_mulai)) . " (Pukul " . $jam_mulai . " - " . $jam_selesai . " WIB)";
        } else {
            $jadwal_dispensasi = date('d-m-Y', strtotime($tgl_mulai)) . " s/d " . date('d-m-Y', strtotime($tgl_selesai)) . " (Pukul " . $jam_mulai . " - " . $jam_selesai . " WIB)";
        }
    } else {
        $jadwal_dispensasi = "-";
    }

    $tempat          = $_POST['tempat'] ?? '';
    $guru_pendamping = $_POST['guru_pendamping'] ?? '';
    $alasan          = $_POST['alasan'] ?? '';

    $nama_siswa_gabungan = is_array($list_siswa) ? implode(", ", $list_siswa) : '';
    $kelas_gabungan      = is_array($kelas_list) ? implode(", ", array_unique(array_filter($kelas_list))) : '';

    if (empty($nama_siswa_gabungan)) {
        $msg = "Harap pilih minimal 1 siswa!";
        $msg_type = "error";
    } else {
        $nis_pembuat = $_SESSION['nis'];
        $sql = "INSERT INTO dispensasi (nis, nama_siswa, kelas, nama_kegiatan, jadwal_dispensasi, tempat, guru_pendamping, alasan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Persetujuan')";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            die("SQL Error: " . $conn->error);
        }

        $stmt->bind_param("ssssssss", $nis_pembuat, $nama_siswa_gabungan, $kelas_gabungan, $nama_kegiatan, $jadwal_dispensasi, $tempat, $guru_pendamping, $alasan);

        if ($stmt->execute()) {
            $msg = "Pengajuan dispensasi berhasil dikirim!";
            $msg_type = "success";
        } else {
            $msg = "Gagal menyimpan data: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
}

$list_kelas = [];
$res_kelas = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
if ($res_kelas) { while ($k = $res_kelas->fetch_assoc()) { $list_kelas[] = $k['kelas']; } }

$list_guru = [];
$res_guru = $conn->query("SELECT nama_guru, nip FROM guru ORDER BY nama_guru ASC");
if ($res_guru) { while ($g = $res_guru->fetch_assoc()) { $list_guru[] = $g; } }

$nis_aktif = $_SESSION['nis'];
$query_riwayat = $conn->prepare("SELECT * FROM dispensasi WHERE nis = ? ORDER BY id DESC");
if ($query_riwayat) {
    $query_riwayat->bind_param("s", $nis_aktif);
    $query_riwayat->execute();
    $result_riwayat = $query_riwayat->get_result();
} else {
    $result_riwayat = null;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Form Pengajuan Dispensasi - Siswa</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 15px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        .header-card { background: #ffffff; padding: 18px 20px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .header-card h2 { margin: 0; font-size: 18px; color: #0f172a; }
        .header-card p { margin: 3px 0 0; font-size: 13px; color: #64748b; }
        .btn-logout { background: #dc2626; color: #fff; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; text-align: center; white-space: nowrap; }
        .btn-logout:hover { background: #b91c1c; }

        .card { background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h3 { margin-top: 0; font-size: 16px; color: #0f172a; margin-bottom: 15px; }

        .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #0f172a; font-size: 20px; }
        
        .form-group { margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #334155; }
        input[type="text"], input[type="date"], input[type="time"], textarea, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background-color: #fff; font-family: inherit; }

        .kelas-block { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; margin-bottom: 15px; position: relative; }
        .btn-remove-kelas { position: absolute; right: 12px; top: 12px; background: #ef4444; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; }
        
        .siswa-action-bar { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .btn-toggle-siswa { background: #0284c7; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; flex: 1; min-width: 140px; }
        .btn-toggle-siswa:hover { background: #0369a1; }
        .check-all-btn { background: #475569; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; flex: 1; min-width: 140px; }
        
        .siswa-box { border: 1px solid #cbd5e1; border-radius: 6px; max-height: 180px; overflow-y: auto; padding: 10px; background: #ffffff; margin-top: 10px; display: none; }
        .siswa-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 14px; }
        .siswa-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .btn-add-kelas { background: #059669; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-bottom: 20px; width: 100%; }
        .btn-add-kelas:hover { background: #047857; }

        .preview-box { background: #f0fdf4; border: 2px dashed #16a34a; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .preview-box h3 { margin-top: 0; color: #15803d; font-size: 15px; border-bottom: 1px solid #bbf7d0; padding-bottom: 8px; }
        .preview-item { font-size: 13px; margin-bottom: 8px; color: #166534; word-break: break-word; }
        .preview-item strong { color: #14532d; }
        .preview-siswa-list { background: #ffffff; border: 1px solid #bbf7d0; padding: 10px; border-radius: 6px; font-size: 13px; max-height: 120px; overflow-y: auto; color: #1e293b; }

        .btn-submit { width: 100%; background: #2563eb; color: #fff; padding: 12px; border: none; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; margin-top: 20px; }
        .btn-submit:hover { background: #1d4ed8; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; min-width: 500px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background-color: #f1f5f9; color: #334155; font-weight: 600; }

        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error, .alert-danger { background: #fee2e2; color: #991b1b; }

        /* Media Query untuk HP */
        @media screen and (max-width: 600px) {
            body { padding: 8px; }
            .header-card { flex-direction: column; align-items: flex-start; }
            .btn-logout { width: 100%; margin-top: 5px; }
            .grid-2 { grid-template-columns: 1fr; }
            input[type="text"], input[type="date"], input[type="time"], textarea, select { font-size: 16px; }
            .btn-remove-kelas { position: static; width: 100%; margin-bottom: 8px; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-card">
        <div>
            <h2>Halo, <?= htmlspecialchars($_SESSION['nama']); ?></h2>
            <p>NIS: <?= htmlspecialchars($_SESSION['nis']); ?> | Kelas: <?= htmlspecialchars($_SESSION['kelas']); ?></p>
        </div>
        <a href="logout.php" class="btn-logout">Keluar</a>
    </div>

    <div class="card">
        <div class="header">
            <h2>Sistem Informasi Izin dan Dispensasi</h2>
            <p style="margin: 3px 0 0; color: #64748b; font-size: 13px;">MAN 1 Kota Madiun</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'error'; ?>"><?= $msg; ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="formDispensasi">
            
            <div id="container_multi_kelas">
                
                <div class="kelas-block" id="block_1" data-index="1">
                    <label style="color: #1e293b;">Pilih Kelas #1:</label>
                    <select name="kelas_terpilih[]" class="select-kelas" required onchange="loadSiswaByKelas(this, 1)">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($list_kelas as $kls): ?>
                            <option value="<?= htmlspecialchars($kls); ?>"><?= htmlspecialchars($kls); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="siswa-action-bar" id="action_bar_1" style="display: none;">
                        <button type="button" class="btn-toggle-siswa" id="btn_toggle_1" onclick="toggleSiswaBox(1)">👁️ Tampilkan Daftar Siswa</button>
                        <button type="button" class="check-all-btn" onclick="toggleSelectAll(1)">Pilih/Batal Semua</button>
                    </div>

                    <div class="siswa-box" id="siswa_box_1">
                        <em style="color: #64748b; font-size: 13px;">Pilih kelas terlebih dahulu...</em>
                    </div>
                </div>

            </div>

            <button type="button" class="btn-add-kelas" onclick="addKelasBlock()">+ Tambah Kelas Lain</button>

            <div class="form-group">
                <label for="nama_kegiatan">Nama Kegiatan / Keperluan:</label>
                <input type="text" name="nama_kegiatan" id="nama_kegiatan" placeholder="Contoh: Lomba LKS / Kemah Bantara" required oninput="updatePreview()">
            </div>

            <div class="form-group">
                <div class="grid-2">
                    <div>
                        <label for="tgl_mulai">Tanggal Mulai:</label>
                        <input type="date" name="tgl_mulai" id="tgl_mulai" required onchange="updatePreview()">
                    </div>
                    <div>
                        <label for="tgl_selesai">Tanggal Selesai:</label>
                        <input type="date" name="tgl_selesai" id="tgl_selesai" required onchange="updatePreview()">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="grid-2">
                    <div>
                        <label for="jam_mulai">Jam Mulai:</label>
                        <input type="time" name="jam_mulai" id="jam_mulai" required onchange="updatePreview()">
                    </div>
                    <div>
                        <label for="jam_selesai">Jam Selesai:</label>
                        <input type="time" name="jam_selesai" id="jam_selesai" required onchange="updatePreview()">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="tempat">Tempat / Lokasi Kegiatan:</label>
                <input type="text" name="tempat" id="tempat" placeholder="Contoh: Aula MAN 1 Kota Madiun" required oninput="updatePreview()">
            </div>

            <div class="form-group">
                <label for="guru_pendamping">Guru Pendamping / Pembina:</label>
                <select name="guru_pendamping" id="guru_pendamping" required onchange="updatePreview()">
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
                <textarea name="alasan" id="alasan" rows="3" placeholder="Masukkan rincian keterangan..." required oninput="updatePreview()"></textarea>
            </div>

            <div class="preview-box">
                <h3>👁️ Pratinjau Pengajuan Dispensasi</h3>
                <div class="preview-item"><strong>Kegiatan:</strong> <span id="prev_kegiatan">-</span></div>
                <div class="preview-item"><strong>Waktu:</strong> <span id="prev_waktu">-</span></div>
                <div class="preview-item"><strong>Tempat:</strong> <span id="prev_tempat">-</span></div>
                <div class="preview-item"><strong>Guru Pendamping:</strong> <span id="prev_guru">-</span></div>
                <div class="preview-item"><strong>Jumlah Siswa Terpilih:</strong> <span id="prev_count">0</span> Siswa</div>
                <div class="preview-item"><strong>Daftar Nama Siswa & Kelas:</strong></div>
                <div class="preview-siswa-list" id="prev_siswa_list">Belum ada siswa yang dipilih.</div>
            </div>

            <button type="submit" name="submit_rombongan" class="btn-submit">Kirim Pengajuan</button>
        </form>
    </div>

    <div class="card">
        <h3>Riwayat Pengajuan Anda</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kegiatan</th>
                        <th>Jadwal & Tempat</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_riwayat && $result_riwayat->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $result_riwayat->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['nama_kegiatan']); ?></strong><br>
                                <small style="color: #475569;">Siswa/Rombongan: <?= htmlspecialchars($row['nama_siswa']); ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['jadwal_dispensasi']); ?><br>
                                <small style="color: #64748b;">Lokasi: <?= htmlspecialchars($row['tempat']); ?></small>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: <?= strtolower($row['status']) === 'diterima' ? '#166534' : '#d97706'; ?>">
                                    <?= htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 15px;">Belum ada riwayat pengajuan dispensasi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    let blockCounter = 1;
    const listKelasOptions = `<?php foreach ($list_kelas as $kls): ?><option value="<?= htmlspecialchars($kls); ?>"><?= htmlspecialchars($kls); ?></option><?php endforeach; ?>`;

    function loadSiswaByKelas(selectEl, blockId) {
        const kelas = selectEl.value;
        const box = document.getElementById(`siswa_box_${blockId}`);
        const actionBar = document.getElementById(`action_bar_${blockId}`);
        const btnToggle = document.getElementById(`btn_toggle_${blockId}`);

        if (!kelas) {
            box.style.display = 'none';
            actionBar.style.display = 'none';
            box.innerHTML = '';
            updatePreview();
            return;
        }

        actionBar.style.display = 'flex';
        box.style.display = 'none';
        btnToggle.innerText = '👁️ Tampilkan Daftar Siswa';
        box.innerHTML = '<em>Memuat data siswa...</em>';

        fetch(`pengajuan.php?action=get_siswa&kelas=${encodeURIComponent(kelas)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    box.innerHTML = '<em style="color: #ef4444; font-size: 13px;">Tidak ada siswa di kelas ini.</em>';
                    updatePreview();
                    return;
                }

                let html = '';
                data.forEach(nama => {
                    html += `
                        <div class="siswa-item">
                            <input type="checkbox" name="siswa_terpilih[]" value="${nama} (${kelas})" id="chk_${blockId}_${nama}" onchange="updatePreview()">
                            <label for="chk_${blockId}_${nama}" style="margin:0; font-weight:normal; cursor:pointer;">${nama}</label>
                        </div>
                    `;
                });
                box.innerHTML = html;
                updatePreview();
            })
            .catch(() => {
                box.innerHTML = '<em style="color: #ef4444; font-size: 13px;">Gagal memuat data siswa.</em>';
            });
    }

    function toggleSiswaBox(blockId) {
        const box = document.getElementById(`siswa_box_${blockId}`);
        const btnToggle = document.getElementById(`btn_toggle_${blockId}`);

        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            btnToggle.innerText = '🙈 Sembunyikan Daftar Siswa';
        } else {
            box.style.display = 'none';
            btnToggle.innerText = '👁️ Tampilkan Daftar Siswa';
        }
    }

    function toggleSelectAll(blockId) {
        const checkboxes = document.querySelectorAll(`#siswa_box_${blockId} input[type="checkbox"]`);
        if(checkboxes.length === 0) return;
        
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        updatePreview();
    }

    function addKelasBlock() {
        blockCounter++;
        const container = document.getElementById('container_multi_kelas');
        
        const div = document.createElement('div');
        div.className = 'kelas-block';
        div.id = `block_${blockCounter}`;
        div.setAttribute('data-index', blockCounter);

        div.innerHTML = `
            <button type="button" class="btn-remove-kelas" onclick="removeKelasBlock(${blockCounter})">Hapus Kelas Ini</button>
            <label style="color: #1e293b;">Pilih Kelas #${blockCounter}:</label>
            <select name="kelas_terpilih[]" class="select-kelas" required onchange="loadSiswaByKelas(this, ${blockCounter})">
                <option value="">-- Pilih Kelas --</option>
                ${listKelasOptions}
            </select>

            <div class="siswa-action-bar" id="action_bar_${blockCounter}" style="display: none;">
                <button type="button" class="btn-toggle-siswa" id="btn_toggle_${blockCounter}" onclick="toggleSiswaBox(${blockCounter})">👁️ Tampilkan Daftar Siswa</button>
                <button type="button" class="check-all-btn" onclick="toggleSelectAll(${blockCounter})">Pilih/Batal Semua</button>
            </div>

            <div class="siswa-box" id="siswa_box_${blockCounter}">
                <em style="color: #64748b; font-size: 13px;">Pilih kelas terlebih dahulu...</em>
            </div>
        `;

        container.appendChild(div);
    }

    function removeKelasBlock(blockId) {
        const block = document.getElementById(`block_${blockId}`);
        if (block) {
            block.remove();
            updatePreview();
        }
    }

    function updatePreview() {
        document.getElementById('prev_kegiatan').innerText = document.getElementById('nama_kegiatan').value || '-';
        document.getElementById('prev_tempat').innerText = document.getElementById('tempat').value || '-';
        document.getElementById('prev_guru').innerText = document.getElementById('guru_pendamping').value || '-';

        const tglMulai = document.getElementById('tgl_mulai').value;
        const tglSelesai = document.getElementById('tgl_selesai').value;
        const jamMulai = document.getElementById('jam_mulai').value;
        const jamSelesai = document.getElementById('jam_selesai').value;

        if (tglMulai && tglSelesai) {
            let formatTgl = tglMulai === tglSelesai ? tglMulai : `${tglMulai} s/d ${tglSelesai}`;
            let formatJam = (jamMulai && jamSelesai) ? ` (Pukul ${jamMulai} - ${jamSelesai} WIB)` : '';
            document.getElementById('prev_waktu').innerText = formatTgl + formatJam;
        } else {
            document.getElementById('prev_waktu').innerText = '-';
        }

        const selectedCheckboxes = document.querySelectorAll('input[name="siswa_terpilih[]"]:checked');
        document.getElementById('prev_count').innerText = selectedCheckboxes.length;

        if (selectedCheckboxes.length > 0) {
            let listHtml = '<ol style="margin:0; padding-left:20px;">';
            selectedCheckboxes.forEach(cb => {
                listHtml += `<li>${cb.value}</li>`;
            });
            listHtml += '</ol>';
            document.getElementById('prev_siswa_list').innerHTML = listHtml;
        } else {
            document.getElementById('prev_siswa_list').innerHTML = 'Belum ada siswa yang dipilih.';
        }
    }
</script>

</body>
</html>