<?php
require_once '../config.php';
cek_login('mahasiswa');

$user_id = $_SESSION['user_id'];

$nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
               'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/* Pilihan bulan & tahun (GET), validasi + default ke bulan berjalan */
$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('n');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) date('n');
}
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) date('Y');
}

/* Ringkasan periode: total pemasukan & pengeluaran */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'pemasukan'   THEN nominal END), 0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN nominal END), 0) AS keluar
     FROM transaksi
     WHERE user_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?"
);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $bulan, $tahun);
mysqli_stmt_execute($stmt);
$ringkasan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$total_masuk  = (float) $ringkasan['masuk'];
$total_keluar = (float) $ringkasan['keluar'];
$saldo_periode = $total_masuk - $total_keluar;

/* Daftar transaksi periode tersebut */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT t.tanggal, t.jenis, t.nominal, t.catatan, k.nama_kategori
     FROM transaksi t
     JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
     ORDER BY t.tanggal ASC, t.id ASC"
);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $bulan, $tahun);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$daftar_transaksi = mysqli_fetch_all($hasil, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Sistem Keuangan Mahasiswa</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav class="no-print">
    <a href="dashboard.php">Dashboard</a> |
    <a href="transaksi.php">Transaksi</a> |
    <a href="budget.php">Budget</a> |
    <a href="tabungan.php">Tabungan</a> |
    <a href="laporan.php">Laporan</a> |
    <a href="../logout.php">Logout</a>
</nav>

<form method="get" action="laporan.php" class="no-print">
    <label for="bulan">Bulan</label>
    <select name="bulan" id="bulan" required>
        <?php foreach ($nama_bulan as $no => $nama): ?>
            <option value="<?= $no ?>" <?= $no === $bulan ? 'selected' : '' ?>><?= $nama ?></option>
        <?php endforeach; ?>
    </select>

    <label for="tahun">Tahun</label>
    <input type="number" name="tahun" id="tahun" value="<?= $tahun ?>" min="2000" max="2100" required>

    <button type="submit">Tampilkan</button>
</form>

<button type="button" class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>

<!-- Kop laporan -->
<header class="kop-laporan">
    <h1>Laporan Keuangan Mahasiswa</h1>
    <p>Sistem Keuangan Mahasiswa</p>
    <p>Nama: <?= htmlspecialchars($_SESSION['nama']) ?></p>
    <p>Periode: <?= $nama_bulan[$bulan] . ' ' . $tahun ?></p>
    <p>Dicetak: <?= date('d-m-Y') ?></p>
</header>

<!-- Ringkasan periode -->
<section class="ringkasan">
    <h2>Ringkasan</h2>
    <table>
        <tr>
            <td>Total Pemasukan</td>
            <td><?= format_rupiah($total_masuk) ?></td>
        </tr>
        <tr>
            <td>Total Pengeluaran</td>
            <td><?= format_rupiah($total_keluar) ?></td>
        </tr>
        <tr>
            <td><strong>Saldo Periode</strong></td>
            <td><strong><?= format_rupiah($saldo_periode) ?></strong></td>
        </tr>
    </table>
</section>

<!-- Tabel transaksi -->
<section class="daftar-transaksi">
    <h2>Rincian Transaksi</h2>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Jenis</th>
                <th>Nominal</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($daftar_transaksi) === 0): ?>
                <tr>
                    <td colspan="6">Tidak ada transaksi pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($daftar_transaksi as $i => $t): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= date('d-m-Y', strtotime($t['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($t['nama_kategori']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($t['jenis'])) ?></td>
                        <td><?= format_rupiah($t['nominal']) ?></td>
                        <td><?= htmlspecialchars($t['catatan'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>

</body>
</html>
