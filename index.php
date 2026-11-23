<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

$host = "localhost";
$user = "u533808516_kuncen";
$pass = "Suhat68b!";
$db   = "u533808516_dispen";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =========================================================================
// ENDPOINT AJAX UNTUK WIDGET SISWA DISPENSASI HARI INI (STATUS: DITERIMA)
// =========================================================================
if (isset($_GET['ajax_live_dispen'])) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    $list         = [];
    $today        = date('Y-m-d');
    $todayDisplay = date('d-m-Y');

    $bulanIndo = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
        '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];

    // Ambil peta NIP guru dari tabel `guru` (jika tersedia) agar NIP Penanggung Jawab muncul otomatis
    $guruNipMap = [];
    $resGuru = @$conn->query("SELECT * FROM guru");
    if ($resGuru) {
        while ($g = $resGuru->fetch_assoc()) {
            $gNama = trim($g['nama'] ?? $g['nama_guru'] ?? '');
            $gNip  = trim($g['nip'] ?? $g['nip_guru'] ?? '');
            if ($gNama !== '' && $gNip !== '') {
                $guruNipMap[strtolower($gNama)] = $gNip;
            }
        }
    }

    $sql = "SELECT 
                id,
                nama_siswa, 
                kelas, 
                nama_kegiatan, 
                jadwal_dispensasi, 
                tempat,
                guru_pendamping,
                tanggal_pengajuan
            FROM dispensasi
            WHERE LOWER(TRIM(status)) = 'diterima'
            ORDER BY id DESC
            LIMIT 100";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $jadwal = trim($row['jadwal_dispensasi'] ?? '');
            $isHariIni = false;

            // Cek kecocokan tanggal hari ini pada teks jadwal_dispensasi
            preg_match_all('/(\d{2})-(\d{2})-(\d{4})/', $jadwal, $dateMatches, PREG_SET_ORDER);

            if (count($dateMatches) >= 2) {
                $tglMulai   = $dateMatches[0][3] . '-' . $dateMatches[0][2] . '-' . $dateMatches[0][1];
                $tglSelesai = $dateMatches[1][3] . '-' . $dateMatches[1][2] . '-' . $dateMatches[1][1];
                if ($today >= $tglMulai && $today <= $tglSelesai) {
                    $isHariIni = true;
                }
            } elseif (count($dateMatches) === 1) {
                $tglKegiatan = $dateMatches[0][3] . '-' . $dateMatches[0][2] . '-' . $dateMatches[0][1];
                if ($today === $tglKegiatan) {
                    $isHariIni = true;
                }
            }

            if ($isHariIni) {
                // Untuk tampilan ringkas di kartu widget: tanggal hari ini saja + jam
                $jamPart = '';
                if (preg_match('/(\(.*?\))/', $jadwal, $jamMatch)) {
                    $jamPart = ' ' . $jamMatch[1];
                }
                $waktuHariIni = $todayDisplay . $jamPart;

                // Format tanggal titik mangsa surat (Madiun, DD Bulan YYYY)
                $tsSurat = !empty($row['tanggal_pengajuan']) ? strtotime($row['tanggal_pengajuan']) : time();
                $tglSuratIndo = date('j', $tsSurat) . ' ' . $bulanIndo[date('m', $tsSurat)] . ' ' . date('Y', $tsSurat);

                $guruNama = trim($row['guru_pendamping'] ?? '-');
                $guruNip  = $guruNipMap[strtolower($guruNama)] ?? '-';

                $list[] = [
                    'nama'            => htmlspecialchars($row['nama_siswa'] ?? '-'),
                    'kelas'           => htmlspecialchars($row['kelas'] ?? '-'),
                    'kegiatan'        => htmlspecialchars($row['nama_kegiatan'] ?? '-'),
                    'waktu'           => htmlspecialchars($waktuHariIni),
                    'jadwal_lengkap'  => htmlspecialchars($jadwal),
                    'lokasi'          => htmlspecialchars($row['tempat'] ?? '-'),
                    'guru_pendamping' => htmlspecialchars($guruNama),
                    'guru_nip'        => htmlspecialchars($guruNip),
                    'tgl_surat_indo'  => $tglSuratIndo
                ];
            }
        }
    }

    echo json_encode([
        'status' => 'ok',
        'data'   => $list
    ]);
    exit;
}

// =========================================================================
// PROSES LOGIN SISWA
// =========================================================================
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis'] ?? '');

    if (empty($nis)) {
        $error = "NIS tidak boleh kosong!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE nis = ?");
        if ($stmt) {
            $stmt->bind_param("s", $nis);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $siswa_data = $result->fetch_assoc();
                $_SESSION['user_role'] = 'siswa';
                $_SESSION['nis'] = $siswa_data['nis'];
                $_SESSION['nama'] = $siswa_data['nama'];
                $_SESSION['kelas'] = $siswa_data['kelas'];
                
                header("Location: pengajuan.php"); 
                exit;
            } else {
                $error = "NIS tidak ditemukan di database siswa!";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa - SIMPAN MAN 1 Kota Madiun</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8fafc; 
            color: #1e293b; 
            margin: 0; 
            padding: 20px 15px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }

        .main-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            width: 100%;
            max-width: 940px;
            justify-content: center;
            align-items: stretch;
        }

        /* Kartu Login */
        .login-card { 
            background: #ffffff; 
            padding: 30px 30px 28px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
            border: 1px solid #e2e8f0;
            width: 100%; 
            max-width: 390px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .login-logo {
            width: 82px;
            height: auto;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        .login-card h2 { 
            margin: 0 0 4px 0; 
            color: #0f172a; 
            font-size: 24px; 
            letter-spacing: 0.5px;
        }
        .app-subtitle { 
            color: #334155; 
            font-size: 13.5px; 
            font-weight: 600;
            margin: 0 0 6px 0; 
            line-height: 1.4;
        }
        .login-role {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 999px;
            margin-top: 4px;
            text-decoration: none;
            cursor: default;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 13px; 
            color: #334155; 
            margin-bottom: 6px; 
        }
        .form-group input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            font-size: 16px; 
            font-family: inherit; 
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-login { 
            width: 100%; 
            background: #2563eb; 
            color: #fff; 
            padding: 12px; 
            border: none; 
            border-radius: 6px; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 15px; 
            margin-top: 6px; 
            transition: background 0.15s;
        }
        .btn-login:hover { background: #1d4ed8; }
        .alert-error { 
            background: #fee2e2; 
            color: #991b1b; 
            padding: 10px; 
            border-radius: 6px; 
            font-size: 13px; 
            margin-bottom: 16px; 
            text-align: center; 
        }

        /* Widget Slider Dispensasi Hari Ini */
        .widget-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            flex: 1;
            min-width: 320px;
            max-width: 520px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .widget-header {
            padding: 14px 20px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .widget-title-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .live-dot {
            width: 10px;
            height: 10px;
            background: #16a34a;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
            animation: pulse-green 1.8s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(22, 163, 74, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }
        .widget-header h3 {
            margin: 0;
            font-size: 15px;
            color: #0f172a;
        }
        .realtime-clock-box {
            text-align: right;
            line-height: 1.25;
        }
        .clock-date {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        .clock-time {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 5px;
            margin-top: 2px;
        }
        .widget-body {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        /* Komponen Slider Kiri-Kanan */
        .slider-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
        }
        .slider-track {
            display: flex;
            transition: transform 0.35s ease-in-out;
            width: 100%;
        }
        .slide-page {
            min-width: 100%;
            padding: 2px;
        }
        .dispen-item {
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #16a34a;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .dispen-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .siswa-nama {
            font-weight: 700;
            font-size: 14.5px;
            color: #0f172a;
            line-height: 1.45;
        }
        .siswa-kelas {
            background: #dcfce7;
            color: #15803d;
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
            flex-shrink: 0;
            max-width: 150px;
            text-align: right;
        }
        .dispen-kegiatan {
            font-size: 13.5px;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .dispen-detail {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 12.5px;
            color: #475569;
        }
        .dispen-detail span {
            display: inline-flex;
            align-items: flex-start;
            gap: 6px;
        }
        .btn-lihat-surat {
            margin-top: 12px;
            width: 100%;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 8px 12px;
            border-radius: 7px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-lihat-surat:hover {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
        }

        /* Kontrol Navigasi Slider */
        .slider-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        .slider-btn {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, color 0.15s;
            user-select: none;
        }
        .slider-btn:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
        .slider-indicator-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .slider-counter {
            font-size: 11.5px;
            font-weight: 600;
            color: #64748b;
        }
        .slider-dots {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 240px;
        }
        .slider-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #cbd5e1;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .slider-dot.active {
            background: #16a34a;
            transform: scale(1.25);
        }

        .empty-state {
            margin: auto;
            text-align: center;
            padding: 40px 15px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            width: 100%;
        }

        /* =========================================================
           MODAL SURAT DISPENSASI (SESUAI GAMBAR REFERENSI + WATERMARK)
           ========================================================= */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 9999;
            padding: 16px;
            overflow-y: auto;
            justify-content: center;
            align-items: flex-start;
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            width: 100%;
            max-width: 790px;
            border-radius: 10px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            margin: 16px auto;
        }
        .modal-toolbar {
            background: #0f172a;
            color: #ffffff;
            padding: 11px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-toolbar-title {
            font-size: 13.5px;
            font-weight: 600;
        }
        .btn-close-modal {
            background: #334155;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-close-modal:hover { background: #475569; }

        /* Lembar Kertas Surat */
        .surat-paper {
            position: relative;
            padding: 36px 46px 42px;
            font-family: 'Times New Roman', Times, serif;
            color: #000000;
            line-height: 1.45;
            background: #ffffff;
            overflow: hidden;
            user-select: none;
        }

        /* Watermark DISETUJUI */
        .watermark-disetujui {
            position: absolute;
            top: 52%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-28deg);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 74px;
            font-weight: 900;
            letter-spacing: 8px;
            color: rgba(22, 163, 74, 0.14);
            border: 6px solid rgba(22, 163, 74, 0.16);
            padding: 8px 28px;
            border-radius: 14px;
            pointer-events: none;
            z-index: 1;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .surat-content-layer {
            position: relative;
            z-index: 2;
        }

        /* Kop Surat */
        .kop-surat {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 3px double #000000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .kop-logo {
            width: 82px;
            height: auto;
            flex-shrink: 0;
        }
        .kop-text {
            text-align: center;
            flex: 1;
            line-height: 1.28;
        }
        .kop-text .instansi-1 {
            font-size: 14.5px;
            font-weight: bold;
        }
        .kop-text .instansi-2 {
            font-size: 14px;
            font-weight: bold;
        }
        .kop-text .instansi-3 {
            font-size: 16.5px;
            font-weight: bold;
        }
        .kop-text .alamat {
            font-size: 11.5px;
            margin-top: 2px;
        }

        /* Judul Surat */
        .judul-surat {
            text-align: center;
            margin-bottom: 18px;
        }
        .judul-surat h4 {
            margin: 0;
            font-size: 15px;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }

        .isi-surat {
            font-size: 13.5px;
            text-align: justify;
        }
        .isi-surat p {
            margin: 0 0 12px 0;
        }

        /* Tabel 6 Kolom persis seperti gambar referensi */
        .tabel-dispen-resmi {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 14px;
            font-size: 12.5px;
        }
        .tabel-dispen-resmi th,
        .tabel-dispen-resmi td {
            border: 1px solid #000000;
            padding: 7px 8px;
            vertical-align: middle;
        }
        .tabel-dispen-resmi th {
            font-weight: bold;
            text-align: center;
            line-height: 1.25;
        }
        .tabel-dispen-resmi td.col-no {
            text-align: center;
            width: 38px;
        }
        .tabel-dispen-resmi td.col-kelas {
            text-align: center;
            width: 78px;
        }
        .tabel-dispen-resmi td.col-kegiatan {
            text-align: center;
            width: 155px;
        }
        .tabel-dispen-resmi td.col-jadwal {
            text-align: center;
            width: 135px;
            line-height: 1.35;
        }
        .tabel-dispen-resmi td.col-tempat {
            text-align: center;
            width: 105px;
        }

        /* Grid Tanda Tangan: Kepala Madrasah (Kiri) & Penanggung Jawab Kegiatan (Kanan) */
        .ttd-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 24px;
            font-size: 13.5px;
        }
        .ttd-col {
            padding-left: 14%;
        }
        .ttd-jabatan {
            min-height: 40px;
            line-height: 1.35;
        }
        .ttd-canvas-box {
            height: 82px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin: 2px 0;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
            line-height: 1.3;
        }
        .ttd-nip {
            font-size: 13px;
            line-height: 1.3;
        }

        @media (max-width: 640px) {
            .surat-paper { padding: 22px 18px; }
            .kop-logo { width: 58px; }
            .kop-text .instansi-1 { font-size: 11.5px; }
            .kop-text .instansi-2 { font-size: 11px; }
            .kop-text .instansi-3 { font-size: 13px; }
            .kop-text .alamat { font-size: 9.5px; }
            .tabel-dispen-resmi { font-size: 10.5px; }
            .ttd-grid { grid-template-columns: 1fr; gap: 22px; }
            .ttd-col { padding-left: 4%; }
            .watermark-disetujui { font-size: 46px; }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <!-- KARTU LOGIN -->
    <div class="login-card">
        <div class="login-header">
            <img src="cropped-logo-mansa.png" alt="Logo MAN 1 Kota Madiun" class="login-logo">
            <h2>SIMPAN</h2>
            <p class="app-subtitle">Sistem Izin dan Dispensasi<br>MAN 1 Kota Madiun</p>
            <a href="admin.php" class="login-role">Portal Login Siswa</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="nis">Nomor Induk Siswa (NIS):</label>
                <input type="text" name="nis" id="nis" placeholder="Masukkan NIS Anda" required autofocus>
            </div>

            <button type="submit" class="btn-login">Masuk Sistem</button>
        </form>
    </div>

    <!-- WIDGET SLIDER DISPENSASI HARI INI -->
    <div class="widget-card" id="widget-card">
        <div class="widget-header">
            <div class="widget-title-wrap">
                <span class="live-dot"></span>
                <h3>Dispensasi Hari Ini</h3>
            </div>
            <div class="realtime-clock-box">
                <span class="clock-date" id="realtime-date">-</span>
                <span class="clock-time" id="realtime-clock">00:00:00 WIB</span>
            </div>
        </div>
        <div class="widget-body" id="live-dispen-container">
            <div class="empty-state">Tidak ada siswa yang sedang dispensasi</div>
        </div>
    </div>
</div>

<!-- MODAL PRATINJAU SURAT DISPENSASI (TANPA TOMBOL CETAK, DENGAN WATERMARK DISETUJUI) -->
<div class="modal-overlay" id="surat-modal" onclick="if(event.target === this) closeSuratModal()">
    <div class="modal-box">
        <div class="modal-toolbar">
            <span class="modal-toolbar-title">Surat Dispensasi Resmi (Status: Disetujui)</span>
            <button type="button" class="btn-close-modal" onclick="closeSuratModal()">Tutup</button>
        </div>
        <div class="surat-paper" id="surat-print-area">
            <!-- Konten surat diisi dinamis oleh JavaScript -->
        </div>
    </div>
</div>

<script>
    let dispenItems = [];
    let currentSlide = 0;
    let autoSlideTimer = null;
    let isHovered = false;
    let isModalOpen = false;
    let lastDataHash = '';

    function updateRealtimeClock() {
        const now = new Date();
        const hariList = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const bulanList = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        const hari = hariList[now.getDay()];
        const tanggal = String(now.getDate()).padStart(2, '0');
        const bulan = bulanList[now.getMonth()];
        const tahun = now.getFullYear();

        const jam = String(now.getHours()).padStart(2, '0');
        const menit = String(now.getMinutes()).padStart(2, '0');
        const detik = String(now.getSeconds()).padStart(2, '0');

        document.getElementById('realtime-date').textContent = `${hari}, ${tanggal} ${bulan} ${tahun}`;
        document.getElementById('realtime-clock').textContent = `${jam}:${menit}:${detik} WIB`;
    }

    // Tanda tangan Kepala Madrasah (Trisnoto, S.Pd., M.M) menyerupai gambar referensi
    function getKepalaMadrasahSignatureSVG() {
        return `
            <svg width="175" height="78" viewBox="0 0 185 82" xmlns="http://www.w3.org/2000/svg">
                <path d="M 48 14 L 44 68 M 36 22 C 58 10, 84 12, 74 34 C 66 48, 34 44, 52 34 C 68 26, 94 34, 118 28 C 124 26, 120 16, 116 24 C 110 36, 114 48, 132 42" 
                      fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M 18 46 C 54 44, 96 48, 134 54" 
                      fill="none" stroke="#1e293b" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M 56 38 C 72 34, 92 38, 108 40" 
                      fill="none" stroke="#1e293b" stroke-width="1.4" stroke-linecap="round"/>
                <circle cx="144" cy="52" r="1.5" fill="#1e293b"/>
            </svg>
        `;
    }

    // Tanda tangan generic SVG untuk Guru Pembimbing / Penanggung Jawab Kegiatan
    function generateGenericSignatureSVG(nameStr) {
        let hash = 0;
        for (let i = 0; i < nameStr.length; i++) {
            hash = ((hash << 5) - hash) + nameStr.charCodeAt(i);
            hash |= 0;
        }
        const seed = Math.abs(hash);
        const y1 = 46 + (seed % 14);
        const y2 = 18 + ((seed >> 3) % 16);
        const y3 = 44 + ((seed >> 5) % 14);
        const c1 = 32 + ((seed >> 2) % 26);
        const c2 = 88 + ((seed >> 4) % 30);

        return `
            <svg width="170" height="76" viewBox="0 0 180 78" xmlns="http://www.w3.org/2000/svg">
                <path d="M 14 ${y1} C ${c1} ${y2}, 48 66, 68 ${y2 + 6} C 84 14, ${c2} 60, 108 ${y3 - 8} C 122 28, 136 52, 152 ${y3 - 4}" 
                      fill="none" stroke="#1e293b" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M 22 60 Q 82 ${50 + (seed % 10)} 148 56" 
                      fill="none" stroke="#1e293b" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
        `;
    }

    function openSuratModal(index) {
        const item = dispenItems[index];
        if (!item) return;

        isModalOpen = true;
        const modal = document.getElementById('surat-modal');
        const printArea = document.getElementById('surat-print-area');

        // Susun baris siswa (memecah jika lebih dari satu siswa dipisahkan koma)
        const namaArray = item.nama.split(',').map(s => s.trim()).filter(Boolean);
        const kelasArray = item.kelas.split(',').map(s => s.trim()).filter(Boolean);
        const totalRows = Math.max(namaArray.length, 1);

        // Format jadwal bertumpuk rapi seperti di gambar referensi
        const jadwalFormatted = item.jadwal_lengkap.replace(/\s*\(/, '<br>(');

        let tableRowsHtml = '';
        for (let i = 0; i < totalRows; i++) {
            let rawNama = namaArray[i] || item.nama;
            let namaBersih = rawNama;
            let kelasBaris = kelasArray[i] || kelasArray[0] || item.kelas;

            const matchKelas = rawNama.match(/^(.*?)\s*\(([^)]+)\)$/);
            if (matchKelas) {
                namaBersih = matchKelas[1].trim();
                kelasBaris = matchKelas[2].trim();
            }

            if (i === 0) {
                tableRowsHtml += `
                    <tr>
                        <td class="col-no">${i + 1}</td>
                        <td>${namaBersih}</td>
                        <td class="col-kelas">${kelasBaris}</td>
                        <td class="col-kegiatan" rowspan="${totalRows}">${item.kegiatan}</td>
                        <td class="col-jadwal" rowspan="${totalRows}">${jadwalFormatted}</td>
                        <td class="col-tempat" rowspan="${totalRows}">${item.lokasi}</td>
                    </tr>
                `;
            } else {
                tableRowsHtml += `
                    <tr>
                        <td class="col-no">${i + 1}</td>
                        <td>${namaBersih}</td>
                        <td class="col-kelas">${kelasBaris}</td>
                    </tr>
                `;
            }
        }

        const nipGuruText = (item.guru_nip && item.guru_nip !== '-') ? `NIP. ${item.guru_nip}` : '&nbsp;';

        printArea.innerHTML = `
            <div class="watermark-disetujui">DISETUJUI</div>
            <div class="surat-content-layer">
                <div class="kop-surat">
                    <img src="cropped-logo-mansa.png" alt="Logo MAN 1 Kota Madiun" class="kop-logo">
                    <div class="kop-text">
                        <div class="instansi-1">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
                        <div class="instansi-2">KANTOR KEMENTERIAN AGAMA KOTA MADIUN</div>
                        <div class="instansi-3">MADRASAH ALIYAH NEGERI 1 KOTA MADIUN</div>
                        <div class="alamat">Jalan Soekarno Hatta Nomor 68 B Kota Madiun</div>
                        <div class="alamat">Telepon (0351) 455477</div>
                        <div class="alamat">E-mail : man1kotamadiun@gmail.com Website : www.man1kotamadiun.sch.id</div>
                    </div>
                </div>

                <div class="judul-surat">
                    <h4>SURAT DISPENSASI</h4>
                </div>

                <div class="isi-surat">
                    <p>Yang bertanda tangan di bawah ini, Kepala MADRASAH ALIYAH NEGERI 1 KOTA MADIUN memberikan tugas sekaligus dispensasi pada peserta didik yang namanya tertera di daftar berikut:</p>

                    <table class="tabel-dispen-resmi">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>NAMA</th>
                                <th>KELAS</th>
                                <th>NAMA<br>KEGIATAN</th>
                                <th>JADWAL<br>DISPENSASI</th>
                                <th>TEMPAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRowsHtml}
                        </tbody>
                    </table>

                    <p>Demikian surat dispensasi ini disampaikan, atas perhatian, kerjasama dan dukungannya disampaikan terima kasih.</p>
                </div>

                <div class="ttd-grid">
                    <div class="ttd-col">
                        <div class="ttd-jabatan">
                            Kepala Madrasah,
                        </div>
                        <div class="ttd-canvas-box">
                            ${getKepalaMadrasahSignatureSVG()}
                        </div>
                        <div class="ttd-nama">Trisnoto, S.Pd., M.M</div>
                        <div class="ttd-nip">NIP. 197110242005011001</div>
                    </div>

                    <div class="ttd-col">
                        <div class="ttd-jabatan">
                            Madiun, ${item.tgl_surat_indo}<br>
                            Penanggung Jawab Kegiatan,
                        </div>
                        <div class="ttd-canvas-box">
                            ${generateGenericSignatureSVG(item.guru_pendamping)}
                        </div>
                        <div class="ttd-nama">${item.guru_pendamping.toUpperCase()}</div>
                        <div class="ttd-nip">${nipGuruText}</div>
                    </div>
                </div>
            </div>
        `;

        modal.classList.add('open');
    }

    function closeSuratModal() {
        isModalOpen = false;
        document.getElementById('surat-modal').classList.remove('open');
    }

    function renderSlider() {
        const container = document.getElementById('live-dispen-container');

        if (!dispenItems || dispenItems.length === 0) {
            container.innerHTML = '<div class="empty-state">Tidak ada siswa yang sedang dispensasi</div>';
            return;
        }

        if (currentSlide >= dispenItems.length) {
            currentSlide = 0;
        }

        let slidesHtml = '';
        let dotsHtml = '';

        dispenItems.forEach((item, idx) => {
            slidesHtml += `
                <div class="slide-page">
                    <div class="dispen-item">
                        <div class="dispen-top">
                            <span class="siswa-nama">${item.nama}</span>
                            <span class="siswa-kelas">${item.kelas}</span>
                        </div>
                        <div class="dispen-kegiatan">${item.kegiatan}</div>
                        <div class="dispen-detail">
                            <span>🕒 ${item.waktu}</span>
                            <span>📍 ${item.lokasi}</span>
                        </div>
                        <button type="button" class="btn-lihat-surat" onclick="openSuratModal(${idx})">
                            📄 Lihat Surat Dispensasi
                        </button>
                    </div>
                </div>
            `;
            dotsHtml += `<span class="slider-dot ${idx === currentSlide ? 'active' : ''}" onclick="goToSlide(${idx})"></span>`;
        });

        const showNav = dispenItems.length > 1;

        container.innerHTML = `
            <div class="slider-wrapper" id="slider-touch-area">
                <div class="slider-track" id="slider-track" style="transform: translateX(-${currentSlide * 100}%);">
                    ${slidesHtml}
                </div>
            </div>
            ${showNav ? `
            <div class="slider-controls">
                <button type="button" class="slider-btn" onclick="prevSlide()" aria-label="Sebelumnya">&#8249;</button>
                <div class="slider-indicator-wrap">
                    <span class="slider-counter" id="slider-counter">${currentSlide + 1} / ${dispenItems.length}</span>
                    <div class="slider-dots" id="slider-dots">${dotsHtml}</div>
                </div>
                <button type="button" class="slider-btn" onclick="nextSlide()" aria-label="Berikutnya">&#8250;</button>
            </div>
            ` : ''}
        `;

        const touchArea = document.getElementById('slider-touch-area');
        if (touchArea && showNav) {
            let startX = 0;
            touchArea.addEventListener('touchstart', e => {
                startX = e.touches[0].clientX;
            }, { passive: true });
            touchArea.addEventListener('touchend', e => {
                const diff = startX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) {
                    diff > 0 ? nextSlide() : prevSlide();
                }
            }, { passive: true });
        }
    }

    function updateSlidePosition() {
        const track = document.getElementById('slider-track');
        if (!track) return;
        track.style.transform = `translateX(-${currentSlide * 100}%)`;

        const counter = document.getElementById('slider-counter');
        if (counter) {
            counter.textContent = `${currentSlide + 1} / ${dispenItems.length}`;
        }

        const dots = document.querySelectorAll('#slider-dots .slider-dot');
        dots.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === currentSlide);
        });
    }

    function nextSlide() {
        if (dispenItems.length <= 1) return;
        currentSlide = (currentSlide + 1) % dispenItems.length;
        updateSlidePosition();
    }

    function prevSlide() {
        if (dispenItems.length <= 1) return;
        currentSlide = (currentSlide - 1 + dispenItems.length) % dispenItems.length;
        updateSlidePosition();
    }

    function goToSlide(index) {
        currentSlide = index;
        updateSlidePosition();
    }

    function loadLiveDispensasi() {
        fetch('index.php?ajax_live_dispen=1&t=' + Date.now())
            .then(response => response.json())
            .then(res => {
                const newData = res.data || [];
                const newHash = JSON.stringify(newData);
                if (newHash !== lastDataHash) {
                    lastDataHash = newHash;
                    dispenItems = newData;
                    renderSlider();
                }
            })
            .catch(() => {
                if (dispenItems.length === 0) {
                    document.getElementById('live-dispen-container').innerHTML = 
                        '<div class="empty-state">Tidak ada siswa yang sedang dispensasi</div>';
                }
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateRealtimeClock();
        setInterval(updateRealtimeClock, 1000);

        loadLiveDispensasi();
        setInterval(loadLiveDispensasi, 10000);

        const widgetCard = document.getElementById('widget-card');
        widgetCard.addEventListener('mouseenter', () => { isHovered = true; });
        widgetCard.addEventListener('mouseleave', () => { isHovered = false; });

        autoSlideTimer = setInterval(() => {
            if (!isHovered && !isModalOpen && dispenItems.length > 1) {
                nextSlide();
            }
        }, 5000);
    });
</script>

</body>
</html>