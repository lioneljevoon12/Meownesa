<?php if (!$cats): ?>
    <div class="col-12">
        <div class="text-center text-muted py-5">
            <i class="bi bi-emoji-frown fs-1 d-block mb-2"></i>
            Tidak ada kucing yang cocok dengan filter ini.
        </div>
    </div>
<?php endif; ?>
<?php foreach ($cats as $c): ?>
    <div class="col-12 col-sm-6 col-lg-4">
        <a href="<?= url('kucing/' . (int) $c['id_kucing']) ?>" class="card mn-card mn-cat-card h-100 text-decoration-none">
            <div class="mn-cat-photo">
                <img src="<?= e(img($c['foto'])) ?>" alt="Foto <?= e($c['nama_kucing']) ?>" loading="lazy">
                <span class="mn-cat-status"><?= status_badge($c['status_kucing']) ?></span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h3 class="h5 mn-display fw-bold mb-1"><?= e($c['nama_kucing']) ?></h3>
                    <small class="text-muted font-mono"><?= e($c['kode_kucing']) ?></small>
                </div>
                <div class="small text-muted mb-2">
                    <i class="bi bi-geo-alt"></i> <?= e($c['nama_kampus']) ?> · <?= e($c['nama_lokasi']) ?>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge text-bg-light border"><i class="bi bi-gender-ambiguous"></i> <?= e($c['jenis_kelamin']) ?></span>
                    <?php if ($c['kelompok_usia']): ?><span class="badge text-bg-light border"><?= e($c['kelompok_usia']) ?></span><?php endif; ?>
                    <?php if ($c['status_steril'] === 'Sudah'): ?><span class="badge text-bg-light border text-success"><i class="bi bi-shield-check"></i> Steril</span><?php endif; ?>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
