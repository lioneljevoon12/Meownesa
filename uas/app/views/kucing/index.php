<section class="container py-4">
    <h1 class="mn-display fw-bold">Katalog Kucing</h1>
    <p class="text-muted">Kucing binaan Jaga Satwa UNESA. Data yang tampil hanya informasi publik.</p>

    <form id="filter-form" class="mn-filter row g-2 align-items-end mb-4" method="get" action="<?= url('kucing') ?>">
        <div class="col-12 col-md-4">
            <label class="form-label small mb-1" for="q">Cari</label>
            <input type="search" class="form-control" id="q" name="q" placeholder="Nama, kode kalung, ciri, atau lokasi…" value="<?= e($filters['q'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="kampus">Kampus</label>
            <select class="form-select" id="kampus" name="kampus">
                <option value="">Semua</option>
                <?php foreach ($kampus as $k): ?>
                    <option value="<?= (int) $k['id_kampus'] ?>" <?= ($filters['kampus'] ?? '') == $k['id_kampus'] ? 'selected' : '' ?>><?= e($k['nama_kampus']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="gender">Jenis kelamin</label>
            <select class="form-select" id="gender" name="gender">
                <option value="">Semua</option>
                <?php foreach (['Jantan', 'Betina'] as $g): ?>
                    <option <?= ($filters['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Semua</option>
                <option value="Tersedia" <?= ($filters['status'] ?? '') === 'Tersedia' ? 'selected' : '' ?>>Siap diadopsi</option>
                <option value="Terdata" <?= ($filters['status'] ?? '') === 'Terdata' ? 'selected' : '' ?>>Terdata kampus</option>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid">
            <button type="reset" class="btn btn-outline-secondary" id="filter-reset">Reset</button>
        </div>
        <noscript><div class="col-12"><button class="btn btn-brand">Terapkan</button></div></noscript>
    </form>

    <div class="small text-muted mb-2" id="result-count" aria-live="polite"></div>
    <div class="row g-4" id="cat-grid" data-endpoint="<?= url('kucing/data') ?>">
        <?php \App\Core\View::partial('kucing/_cards', ['cats' => $cats]); ?>
    </div>
</section>
