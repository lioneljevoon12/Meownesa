<?php
$cards = [
    ['Total Kucing',       $stats['total'],          'bi-collection',     'primary', ''],
    ['Siap Adopsi',        $stats['tersedia'],       'bi-house-heart',    'success', ''],
    ['Antrean Review',     $stats['antrean'],        'bi-hourglass-split', 'warning', ''],
    ['Feeding Hari Ini',   $stats['feeding'],        'bi-egg-fried',      'info',    ''],
    ['Steril',             $stats['steril'],         'bi-shield-check',   'success', '%'],
    ['Pembaruan Terlambat', $stats['overdue'],       'bi-bell',           'danger',  ''],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $tone, $suffix]): ?>
        <div class="col-6 col-xl-2">
            <div class="mn-kpi card border-0 shadow-card h-100">
                <div class="card-body">
                    <div class="mn-kpi-icon text-bg-<?= $tone ?>"><i class="bi <?= $icon ?>"></i></div>
                    <div class="mn-kpi-num"><?= (int) $value ?><?= $suffix ?></div>
                    <div class="small text-muted"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-card">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span>Antrean Pengajuan Adopsi</span>
                <span class="badge text-bg-warning"><?= (int) $stats['antrean'] ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>No. Pengajuan</th><th>Pemohon</th><th>Kucing</th><th>Tanggal</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$antrean): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada pengajuan yang menunggu.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($antrean as $a): ?>
                        <tr>
                            <td class="font-mono small"><?= e($a['nomor_pengajuan']) ?></td>
                            <td><?= e($a['nama_pemohon']) ?></td>
                            <td><?= e($a['nama_kucing']) ?></td>
                            <td><?= e(tanggal_id($a['tanggal_pengajuan'])) ?></td>
                            <td><span class="badge text-bg-<?= $a['status_pengajuan'] === 'MENUNGGU' ? 'secondary' : 'warning' ?>"><?= e($a['status_pengajuan']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-card">
            <div class="card-header bg-white fw-bold">Persebaran per Kampus</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($perKampus as $k): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= e($k['nama_kampus']) ?>
                        <span class="badge rounded-pill text-bg-primary"><?= (int) $k['jumlah'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
