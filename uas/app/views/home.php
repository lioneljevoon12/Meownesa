<section class="mn-hero">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="badge rounded-pill text-bg-warning mb-3">Jaga Satwa UNESA</span>
                <h1 class="display-5 fw-bold mn-display">Satu rumah untuk <span class="text-brand">kucing kampus</span> UNESA.</h1>
                <p class="lead text-ink-light mt-3">
                    Lihat kucing binaan Jaga Satwa di Kampus Ketintang &amp; Lidah Wetan, kenali kisahnya,
                    dan ajukan adopsi — semuanya tercatat dan bisa dilacak.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="<?= url('kucing') ?>" class="btn btn-brand btn-lg"><i class="bi bi-search me-2"></i>Lihat Katalog</a>
                    <a href="<?= e(config('app.laporan_url')) ?>" target="_blank" rel="noopener" class="btn btn-outline-brand btn-lg">
                        <i class="bi bi-megaphone me-2"></i>Lapor Kucing
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <?php if ($kucing): ?>
                    <div class="mn-hero-photo shadow-card">
                        <img src="<?= e(img($kucing[0]['foto'])) ?>" alt="<?= e($kucing[0]['nama_kucing']) ?>">
                        <div class="mn-hero-caption">
                            <strong><?= e($kucing[0]['nama_kucing']) ?></strong>
                            <small><?= e($kucing[0]['nama_kampus']) ?> · <?= status_badge($kucing[0]['status_kucing']) ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="container my-5">
    <div class="row g-3 text-center">
        <?php foreach ([
            [$stats['total'],    'Kucing terdata',    'bi-collection'],
            [$stats['tersedia'], 'Siap diadopsi',     'bi-house-heart'],
            [$stats['steril'],   'Sudah steril',      'bi-shield-check'],
            [$stats['kampus'],   'Kampus dikelola',   'bi-geo-alt'],
        ] as [$n, $label, $icon]): ?>
            <div class="col-6 col-md-3">
                <div class="mn-stat">
                    <i class="bi <?= $icon ?>"></i>
                    <div class="mn-stat-num"><?= (int) $n ?></div>
                    <div class="text-muted small"><?= e($label) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="container my-5">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h2 class="mn-display fw-bold mb-0">Siap Mencari Rumah</h2>
            <p class="text-muted mb-0">Kucing yang sudah steril, sehat, dan dinyatakan siap diadopsi.</p>
        </div>
        <a href="<?= url('kucing') ?>" class="btn btn-link text-brand fw-semibold">Semua kucing <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
        <?php \App\Core\View::partial('kucing/_cards', ['cats' => $kucing]); ?>
    </div>
</section>

<?php if ($artikel): ?>
<section class="container my-5">
    <h2 class="mn-display fw-bold mb-3">Kabar Jaga Satwa</h2>
    <div class="row g-4">
        <?php foreach ($artikel as $a): ?>
            <div class="col-md-4">
                <article class="card mn-card h-100">
                    <img src="<?= e(img($a['thumbnail_url'])) ?>" class="card-img-top mn-thumb" alt="">
                    <div class="card-body">
                        <span class="badge text-bg-light border mb-2"><?= e($a['kategori']) ?></span>
                        <h3 class="h6 fw-bold"><?= e($a['judul']) ?></h3>
                        <small class="text-muted"><?= e(tanggal_id($a['tanggal_diterbitkan'])) ?></small>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
