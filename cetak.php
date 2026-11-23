<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { 
    die("Koneksi Database Gagal: " . $conn->connect_error); 
}

$id = $_GET['id'] ?? 0;

// Proses Simpan Perubahan Jadwal & Jam Cepat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_surat'])) {
    $tgl_mulai   = $_POST['tgl_mulai'] ?? date('Y-m-d');
    $tgl_selesai = $_POST['tgl_selesai'] ?? $tgl_mulai;
    $jam_mulai   = $_POST['jam_mulai'] ?? '07:00';
    $jam_selesai = $_POST['jam_selesai'] ?? '15:00';
    
    // Format gabungan jadwal baru: DD-MM-YYYY s/d DD-MM-YYYY (Pukul HH:MM - HH:MM WIB)
    $formatted_tgl_mulai   = date('d-m-Y', strtotime($tgl_mulai));
    $formatted_tgl_selesai = date('d-m-Y', strtotime($tgl_selesai));
    
    $new_jadwal = $formatted_tgl_mulai . " s/d " . $formatted_tgl_selesai . " (Pukul " . $jam_mulai . " - " . $jam_selesai . " WIB)";
    $new_tempat   = trim($_POST['tempat']);
    $new_kegiatan = trim($_POST['nama_kegiatan']);

    $update_stmt = $conn->prepare("UPDATE dispensasi SET jadwal_dispensasi = ?, tempat = ?, nama_kegiatan = ? WHERE id = ?");
    $update_stmt->bind_param("sssi", $new_jadwal, $new_tempat, $new_kegiatan, $id);
    $update_stmt->execute();
    
    header("Location: cetak.php?id=" . $id);
    exit;
}

// 1. Ambil data dispensasi dan gabungkan (JOIN) dengan tabel guru
$sql = "SELECT d.*, g.nip FROM dispensasi d 
        LEFT JOIN guru g ON d.guru_pendamping = g.nama_guru 
        WHERE d.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Data dispensasi tidak ditemukan.");
}

// 2. Data Pengaturan Instansi
$nama_instansi = 'MADRASAH ALIYAH NEGERI 1 KOTA MADIUN';
$alamat_inst   = 'Jalan Soekarno Hatta Nomor 68 B Kota Madiun';
$telepon_inst  = 'Telepon (0351) 455477';
$email_web     = 'E-mail : man1kotamadiun@gmail.com Website : www.man1kotamadiun.sch.id';

// Data Kepala Madrasah
$nama_kamad    = 'Trisnoto, S.Pd., M.M';
$nip_kamad     = '197110242005011001';

// Folder penyimpanan file upload & file gambar
$upload_dir   = 'uploads/';
$logo_kemenag = $upload_dir . 'logo_kemenag.png';
$tanda_tangan = $upload_dir . 'ttd.png';

// Ambil NIP Guru hasil JOIN
$nip_guru = !empty($data['nip']) ? $data['nip'] : '-';

/*
 * PARSING DATA SISWA & KELAS (MULTI-KELAS / ROMBONGAN)
 */
$string_siswa = $data['nama_siswa'] ?? '';
$string_kelas = $data['kelas'] ?? '';

$list_siswa = [];
if (!empty($string_siswa)) {
    $raw_siswa = explode(',', $string_siswa);
    $raw_kelas = explode(',', $string_kelas);

    foreach ($raw_siswa as $index => $item_siswa) {
        $item_siswa = trim($item_siswa);
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $item_siswa, $matches)) {
            $list_siswa[] = [
                'nama'  => trim($matches[1]),
                'kelas' => trim($matches[2])
            ];
        } else {
            $kelas_val = isset($raw_kelas[$index]) ? trim($raw_kelas[$index]) : trim($string_kelas);
            $list_siswa[] = [
                'nama'  => $item_siswa,
                'kelas' => $kelas_val
            ];
        }
    }
}

$total_siswa  = count($list_siswa);
// Aturan baru: Lampiran aktif jika jumlah siswa LEBIH DARI 5 (> 5)
$is_rombongan = $total_siswa > 5;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Dispensasi - <?= htmlspecialchars($nama_instansi); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 11pt; 
            margin: 0; 
            padding: 20px; 
            background: #e2e8f0; 
            color: #000; 
        }
        
        .paper { 
            background: #fff; 
            width: 210mm; 
            min-height: 297mm; 
            margin: 0 auto 20px auto; 
            padding: 15mm 20mm; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            position: relative;
        }
        
        /* Kop Surat: Hanya Garis Bawah (Bottom) Saja */
        .kop-table { 
            width: 100%; 
            border-collapse: collapse; 
            border: none !important;
            border-bottom: 3px double #000 !important; 
            padding-bottom: 8px; 
            margin-bottom: 20px; 
        }
        .kop-table td { 
            vertical-align: middle; 
            border: none !important; 
        }
        .logo { width: 115px; text-align: left; }
        .logo img { width: 105px; height: auto; display: block; }
        .kop-text { text-align: center; }
        .kop-text h3 { margin: 0; font-size: 13pt; text-transform: uppercase; font-weight: bold; }
        .kop-text h2 { margin: 2px 0; font-size: 14pt; text-transform: uppercase; font-weight: bold; }
        .kop-text h4 { margin: 2px 0; font-size: 12pt; text-transform: uppercase; font-weight: bold; }
        .kop-text p { margin: 2px 0; font-size: 9.5pt; }

        .title { 
            text-align: center; 
            font-size: 13pt; 
            font-weight: bold; 
            text-decoration: underline; 
            margin-bottom: 20px; 
            text-transform: uppercase; 
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 7px 10px;
            font-size: 11pt;
            vertical-align: middle;
        }

        table.dispensasi-table { margin-bottom: 20px; }
        table.dispensasi-table th { background-color: #f8fafc; text-align: center; font-weight: bold; }
        table.dispensasi-table td { padding: 8px 6px; font-size: 10.5pt; }

        table.lampiran-table { margin-top: 15px; }
        table.lampiran-table th { background-color: #f8fafc; text-align: center; font-weight: bold; }
        table.lampiran-table thead { display: table-header-group; }
        table.lampiran-table tr { page-break-inside: avoid; }

        .ttd-section { 
            width: 100%; 
            margin-top: 25px; 
            display: table;
            page-break-inside: avoid;
        }
        .ttd-column { 
            display: table-cell; 
            width: 50%; 
            vertical-align: top; 
            text-align: center; 
        }
        .ttd-box { 
            display: inline-block; 
            text-align: left; 
            min-width: 240px; 
        }

        /* Pengaturan Tanda Tangan Menimpa Teks */
        .ttd-img-container {
            height: 130px; 
            margin: 2px 0; 
            position: relative;
        }
        .ttd-img-container img {
            max-height: 150px; 
            max-width: 280px; 
            display: block;
            position: absolute;
            left: 0;
            top: -15px;
            z-index: 10;
        }

        /* Panel Kontrol Khusus Admin Berbasis Pilihan */
        .admin-panel {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
            padding: 12px 20px;
            width: 210mm;
            margin: 0 auto 15px auto;
            border-radius: 6px;
            font-family: sans-serif;
            font-size: 9.5pt;
        }
        .admin-panel .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }
        .admin-panel input {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 9.5pt;
        }
        .admin-panel button {
            padding: 6px 14px;
            background: #0284c7;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .admin-panel button:hover { background: #0369a1; }

        .btn-print { 
            display: inline-block; 
            padding: 8px 15px; 
            background: #1d4ed8; 
            color: #fff; 
            border: none; 
            border-radius: 6px; 
            font-weight: bold; 
            cursor: pointer; 
            text-align: center; 
            text-decoration: none; 
            font-family: sans-serif;
        }

        @media print {
            body { background: none; padding: 0; }
            .admin-panel { display: none !important; } /* Panel disembunyikan saat dicetak */
            .paper { width: 100%; margin: 0; padding: 15mm 20mm; box-shadow: none; min-height: auto; }
            .page-break { page-break-before: always; break-before: page; }
        }
    </style>
</head>
<body>

<!-- PANEL ADMIN: PENGATURAN TANGGAL & JAM -->
<div class="admin-panel">
    <form method="POST">
        <div class="form-row" style="margin-bottom: 8px;">
            <div><strong>⚙️ Koreksi Waktu & Kegiatan:</strong></div>
            <div>
                <label>Nama Kegiatan: <input type="text" name="nama_kegiatan" value="<?= htmlspecialchars($data['nama_kegiatan']); ?>" style="width: 240px;"></label>
                <label>Tempat: <input type="text" name="tempat" value="<?= htmlspecialchars($data['tempat']); ?>" style="width: 90px;"></label>
            </div>
        </div>
        <div class="form-row" style="border-top: 1px solid #bae6fd; padding-top: 8px;">
            <div>
                <label>Mulai Tgl: <input type="date" name="tgl_mulai" value="<?= date('Y-m-d'); ?>"></label>
                <label>s/d Tgl: <input type="date" name="tgl_selesai" value="<?= date('Y-m-d'); ?>"></label>
            </div>
            <div>
                <label>Jam Mulai: <input type="time" name="jam_mulai" value="07:00"></label>
                <label>s/d Jam: <input type="time" name="jam_selesai" value="15:00"></label>
            </div>
            <div>
                <button type="submit" name="update_surat">Terapkan Perubahan</button>
                <a href="javascript:window.print()" class="btn-print">🖨️ Cetak</a>
            </div>
        </div>
    </form>
</div>

<!-- HALAMAN 1: SURAT UTAMA -->
<div class="paper">
    <table class="kop-table">
        <tr>
            <td class="logo">
                <img src="<?= htmlspecialchars($logo_kemenag); ?>" alt="Logo Instansi">
            </td>
            <td class="kop-text">
                <h3>KEMENTERIAN AGAMA REPUBLIK INDONESIA</h3>
                <h4>KANTOR KEMENTERIAN AGAMA KOTA MADIUN</h4>
                <h2><?= htmlspecialchars($nama_instansi); ?></h2>
                <p><?= htmlspecialchars($alamat_inst); ?></p>
                <p><?= htmlspecialchars($telepon_inst); ?></p>
                <p><?= htmlspecialchars($email_web); ?></p>
            </td>
        </tr>
    </table>

    <div class="title">SURAT DISPENSASI</div>

    <p style="text-align: justify; line-height: 1.6; margin-bottom: 15px;">
        Yang bertanda tangan di bawah ini, Kepala <?= htmlspecialchars($nama_instansi); ?> memberikan tugas sekaligus dispensasi pada peserta didik yang namanya tertera di daftar berikut:
    </p>

    <table class="dispensasi-table">
        <thead>
            <tr>
                <th style="width: 8%;">NO</th>
                <th style="width: 25%;">NAMA</th>
                <th style="width: 15%;">KELAS</th>
                <th style="width: 22%;">NAMA KEGIATAN</th>
                <th style="width: 18%;">JADWAL DISPENSASI</th>
                <th style="width: 12%;">TEMPAT</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($is_rombongan): ?>
                <!-- JIKA SISWA LEBIH DARI 5: TAMPILKAN "TERLAMPIR" -->
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <strong>TERLAMPIR</strong><br><small style="font-style: italic;">(<?= $total_siswa; ?> Siswa)</small>
                    </td>
                    <td style="text-align: center;">
                        <strong>TERLAMPIR</strong>
                    </td>
                    <td><?= htmlspecialchars($data['nama_kegiatan'] ?? '-'); ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($data['jadwal_dispensasi'] ?? '-'); ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($data['tempat'] ?? '-'); ?></td>
                </tr>
            <?php else: ?>
                <!-- JIKA SISWA 5 ATAU KURANG: TAMPILKAN LANGSUNG DALAM TABEL UTAMA -->
                <?php 
                $no = 1;
                foreach ($list_siswa as $index => $s): 
                ?>
                    <tr>
                        <td style="text-align: center;"><?= $no++; ?></td>
                        <td><?= htmlspecialchars($s['nama']); ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($s['kelas']); ?></td>
                        <?php if ($index === 0): ?>
                            <td rowspan="<?= $total_siswa; ?>"><?= htmlspecialchars($data['nama_kegiatan'] ?? '-'); ?></td>
                            <td rowspan="<?= $total_siswa; ?>" style="text-align: center;"><?= htmlspecialchars($data['jadwal_dispensasi'] ?? '-'); ?></td>
                            <td rowspan="<?= $total_siswa; ?>" style="text-align: center;"><?= htmlspecialchars($data['tempat'] ?? '-'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="text-align: justify; line-height: 1.6; margin-top: 15px;">
        Demikian surat dispensasi ini disampaikan, atas perhatian, kerjasama dan dukungannya disampaikan terima kasih.
    </p>

    <div class="ttd-section">
        <div class="ttd-column">
            <div class="ttd-box">
                <p style="margin-bottom: 0; position: relative; z-index: 1;">Kepala Madrasah,</p>
                
                <div class="ttd-img-container">
                    <?php if (file_exists($tanda_tangan)): ?>
                        <img src="<?= htmlspecialchars($tanda_tangan); ?>" alt="Tanda Tangan">
                    <?php endif; ?>
                </div>

                <div style="height: 50px; position: relative; z-index: 1;">
                    <p style="margin: 0;"><strong><u><?= htmlspecialchars($nama_kamad); ?></u></strong></p>
                    <p style="margin: 0;">NIP. <?= htmlspecialchars($nip_kamad); ?></p>
                </div>
            </div>
        </div>
        <div class="ttd-column">
            <div class="ttd-box">
                <p style="margin-bottom: 0;">Madiun, <?= date('d F Y', strtotime($data['tanggal_pengajuan'] ?? 'now')); ?><br>Penanggung Jawab Kegiatan,</p>
                
                <div style="height: 130px; margin: 2px 0;"></div>
                
                <div style="height: 50px; margin-top: -18px;">
                    <p style="margin: 0;"><strong><u><?= htmlspecialchars($data['guru_pendamping'] ?? '.......................................'); ?></u></strong></p>
                    <p style="margin: 0;">NIP. <?= htmlspecialchars($nip_guru); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HALAMAN BERIKUTNYA: LAMPIRAN (HANYA MUNCUL JIKA LEBIH DARI 5 SISWA) -->
<?php if ($is_rombongan): ?>
<div class="paper page-break">
    <div style="font-size: 11pt; font-weight: bold; margin-bottom: 5px;">LAMPIRAN SURAT DISPENSASI</div>
    <table style="width: 100%; margin-bottom: 15px; font-size: 11pt; border: none !important;">
        <tr style="border: none !important;">
            <td style="width: 130px; border: none !important;">Nama Kegiatan</td>
            <td style="width: 10px; border: none !important;">:</td>
            <td style="border: none !important;"><?= htmlspecialchars($data['nama_kegiatan'] ?? '-'); ?></td>
        </tr>
        <tr style="border: none !important;">
            <td style="width: 130px; border: none !important;">Jadwal / Waktu</td>
            <td style="width: 10px; border: none !important;">:</td>
            <td style="border: none !important;"><?= htmlspecialchars($data['jadwal_dispensasi'] ?? '-'); ?></td>
        </tr>
    </table>

    <table class="lampiran-table">
        <thead>
            <tr>
                <th style="width: 8%;">NO</th>
                <th>NAMA SISWA</th>
                <th style="width: 30%;">KELAS</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($list_siswa as $siswa): 
            ?>
                <tr>
                    <td style="text-align: center;"><?= $no++; ?></td>
                    <td><?= htmlspecialchars($siswa['nama']); ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($siswa['kelas']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</body>
</html>