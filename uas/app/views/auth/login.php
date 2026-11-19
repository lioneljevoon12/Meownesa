<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
    <div class="mn-login card border-0 shadow-card p-4 p-md-5 w-100">
        <div class="text-center mb-4">
            <img src="<?= asset('img/brand/meownesa-logo.png') ?>" alt="MeowNesa" height="48" class="mb-3">
            <h1 class="h4 mn-display fw-bold mb-1">Portal Pengurus</h1>
            <p class="text-muted small mb-0">Khusus Admin Jaga Satwa &amp; Super Admin</p>
        </div>

        <?php if ($m = flash('error')): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($m) ?></div><?php endif; ?>
        <?php if ($m = flash('success')): ?><div class="alert alert-success py-2 small" role="status"><?= e($m) ?></div><?php endif; ?>

        <form method="post" action="<?= url('login') ?>" novalidate id="login-form">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="username" value="<?= e(old('email')) ?>">
                <div class="invalid-feedback">Masukkan email yang valid.</div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="toggle-pass" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                </div>
                <div class="invalid-feedback d-block-if-invalid">Password wajib diisi.</div>
            </div>
            <button class="btn btn-brand w-100 py-2" type="submit">Masuk</button>
        </form>
        <div class="text-center mt-3"><a class="small text-muted" href="<?= url() ?>"><i class="bi bi-arrow-left"></i> Kembali ke beranda</a></div>
    </div>
</div>
