<section class="container py-4">
    <nav aria-label="breadcrumb"><ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('kucing') ?>">Katalog</a></li>
        <li class="breadcrumb-item active"><?= e($cat['nama_kucing']) ?></li>
    </ol></nav>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="mn-gallery-main shadow-card">
                <img id="gallery-main" src="<?= e(img($fotos[0]['url_foto'] ?? null)) ?>" alt="Foto <?= e($cat['nama_kucing']) ?>">
            </div>
            <?php if (count($fotos) > 1): ?>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php foreach ($fotos as $i => $f): ?>
                        <button type="button" class="mn-thumb-btn <?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(img($f['url_foto'])) ?>" aria-label="Foto <?= $i + 1 ?>">
                            <img src="<?= e(img($f['url_foto'])) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="d-flex align-items-center gap-2 mb-2">
                <?= status_badge($cat['status_kucing']) ?>
                <small class="text-muted font-mono"><?= e($cat['kode_kucing']) ?></small>
            </div>
            <h1 class="mn-display fw-bold display-6"><?= e($cat['nama_kucing']) ?></h1>
            <p class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($cat['nama_kampus']) ?> · <?= e($cat['nama_lokasi']) ?></p>

            <dl class="row mn-dl">
                <dt class="col-5">Jenis kelamin</dt><dd class="col-7"><?= e($cat['jenis_kelamin']) ?></dd>
                <dt class="col-5">Kelompok usia</dt><dd class="col-7"><?= e($cat['kelompok_usia'] ?: '-') ?></dd>
                <dt class="col-5">Sterilisasi</dt><dd class="col-7"><?= e($cat['status_steril']) ?></dd>
            </dl>

            <p><?= nl2br(e($cat['deskripsi'])) ?></p>

            <?php if ($cat['status_kucing'] === 'Tersedia'): ?>
                <div class="alert alert-light border">
                    <i class="bi bi-info-circle me-1"></i> Formulir pengajuan adopsi akan tersedia di sini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="h5 mn-display fw-bold mt-5">Riwayat Kesehatan &amp; Perawatan</h2>
    <?php if (!$riwayat): ?>
        <p class="text-muted">Belum ada catatan.</p>
    <?php else: ?>
        <ul class="mn-timeline list-unstyled">
            <?php foreach ($riwayat as $r): ?>
                <li>
                    <div class="fw-semibold"><?= e($r['jenis_tindakan']) ?> <small class="text-muted fw-normal">· <?= e(tanggal_id($r['tanggal'])) ?></small></div>
                    <?php if ($r['catatan']): ?><div class="small text-muted"><?= e($r['catatan']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
