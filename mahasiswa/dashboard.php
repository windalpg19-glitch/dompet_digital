<?php
require_once '../config.php';
cek_login('mahasiswa');

$user_id = $_SESSION['user_id'];
$bulan   = (int) date('n');
$tahun   = (int) date('Y');

/* Helper: jalankan prepared statement SELECT, kembalikan 1 baris (array) atau null */
function ambil_satu($koneksi, $sql, $tipe = '', $params = []) {
    $stmt = mysqli_prepare($koneksi, $sql);
    if ($tipe !== '') {
        mysqli_stmt_bind_param($stmt, $tipe, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $baris = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);
    return $baris ?: null;
}

/* 1. Total saldo keseluruhan (pemasukan - pengeluaran) */
$row = ambil_satu(
    $koneksi,
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'pemasukan'   THEN nominal END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN nominal END), 0) AS total_keluar
     FROM transaksi
     WHERE user_id = ?",
    'i',
    [$user_id]
);
$total_saldo = (float) $row['total_masuk'] - (float) $row['total_keluar'];

/* 2. Pemasukan & pengeluaran bulan ini */
$row = ambil_satu(
    $koneksi,
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'pemasukan'   THEN nominal END), 0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN nominal END), 0) AS keluar
     FROM transaksi
     WHERE user_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?",
    'iii',
    [$user_id, $bulan, $tahun]
);
$masuk_bulan_ini  = (float) $row['masuk'];
$keluar_bulan_ini = (float) $row['keluar'];

/* 3. Budget bulan ini -> banner peringatan jika pengeluaran >= 80% batas */
$anggaran = ambil_satu(
    $koneksi,
    "SELECT batas_nominal
     FROM anggaran
     WHERE user_id = ? AND bulan = ? AND tahun = ?
     LIMIT 1",
    'iii',
    [$user_id, $bulan, $tahun]
);

$persen_budget = null; // null = budget belum diatur
if ($anggaran && (float) $anggaran['batas_nominal'] > 0) {
    $batas         = (float) $anggaran['batas_nominal'];
    $persen_budget = ($keluar_bulan_ini / $batas) * 100;
}

/* 4. Lebar bar chart CSS (relatif terhadap nilai terbesar, hindari bagi nol) */
$nilai_maks   = max($masuk_bulan_ini, $keluar_bulan_ini);
$lebar_masuk  = $nilai_maks > 0 ? round(($masuk_bulan_ini  / $nilai_maks) * 100, 1) : 0;
$lebar_keluar = $nilai_maks > 0 ? round(($keluar_bulan_ini / $nilai_maks) * 100, 1) : 0;

/* 5. Tips keuangan terbaru (tabel global, tidak punya kolom user_id) */
$tips = ambil_satu(
    $koneksi,
    "SELECT judul, konten FROM tips_keuangan ORDER BY created_at DESC, id DESC LIMIT 1"
);

$nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
               'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Keuangan Mahasiswa</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav>
    <a href="dashboard.php">Dashboard</a> |
    <a href="transaksi.php">Transaksi</a> |
    <a href="budget.php">Budget</a> |
    <a href="tabungan.php">Tabungan</a> |
    <a href="laporan.php">Laporan</a> |
    <a href="../logout.php">Logout</a>
</nav>

<h1>Halo, <?= htmlspecialchars($_SESSION['nama']) ?></h1>
<p>Ringkasan keuangan — <?= $nama_bulan[$bulan] . ' ' . $tahun ?></p>

<?php if ($persen_budget !== null && $persen_budget >= 100): ?>
    <div class="alert alert-danger">
        Pengeluaran bulan ini sudah melebihi batas budget
        (<?= round($persen_budget) ?>% dari <?= format_rupiah($batas) ?>).
    </div>
<?php elseif ($persen_budget !== null && $persen_budget >= 80): ?>
    <div class="alert alert-warning">
        Peringatan: pengeluaran bulan ini sudah mencapai
        <?= round($persen_budget) ?>% dari batas budget <?= format_rupiah($batas) ?>.
    </div>
<?php endif; ?>

<section class="cards">
    <div class="card">
        <h3>Total Saldo</h3>
        <p><?= format_rupiah($total_saldo) ?></p>
    </div>
    <div class="card">
        <h3>Pemasukan Bulan Ini</h3>
        <p><?= format_rupiah($masuk_bulan_ini) ?></p>
    </div>
    <div class="card">
        <h3>Pengeluaran Bulan Ini</h3>
        <p><?= format_rupiah($keluar_bulan_ini) ?></p>
    </div>
</section>

<section class="chart">
    <h2>Pemasukan vs Pengeluaran Bulan Ini</h2>

    <div class="bar-row">
        <span class="bar-label">Pemasukan</span>
        <div class="bar-track">
            <div class="bar bar-masuk" style="width: <?= $lebar_masuk ?>%;"></div>
        </div>
        <span class="bar-value"><?= format_rupiah($masuk_bulan_ini) ?></span>
    </div>

    <div class="bar-row">
        <span class="bar-label">Pengeluaran</span>
        <div class="bar-track">
            <div class="bar bar-keluar" style="width: <?= $lebar_keluar ?>%;"></div>
        </div>
        <span class="bar-value"><?= format_rupiah($keluar_bulan_ini) ?></span>
    </div>
</section>

<section class="tips">
    <h2>Tips Keuangan</h2>
    <?php if ($tips): ?>
        <h3><?= htmlspecialchars($tips['judul']) ?></h3>
        <p><?= nl2br(htmlspecialchars($tips['konten'])) ?></p>
    <?php else: ?>
        <p>Belum ada tips keuangan.</p>
    <?php endif; ?>
</section>

</body>
</html>
