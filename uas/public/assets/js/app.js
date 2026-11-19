/* MeowNesa — jQuery + Ajax. Semua POST Ajax otomatis membawa token CSRF. */
$(function () {
    'use strict';

    $.ajaxSetup({
        headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
    });

    // ---- Katalog: filter & pencarian real-time via Ajax ---------------------
    var $grid = $('#cat-grid');
    if ($grid.length) {
        var $form = $('#filter-form');
        var timer = null;
        var request = null;

        function updateCount() {
            var n = $grid.find('.mn-cat-card').length;
            $('#result-count').text(n ? n + ' kucing ditemukan' : '');
        }

        function load() {
            var query = $form.serialize();
            if (request) { request.abort(); }
            $grid.addClass('loading');
            request = $.get($grid.data('endpoint'), query)
                .done(function (html) {
                    $grid.html(html);
                    updateCount();
                    // sinkronkan URL agar hasil filter bisa dibagikan
                    history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
                })
                .fail(function (xhr, status) {
                    if (status !== 'abort') {
                        $grid.html('<div class="col-12"><div class="alert alert-danger">Gagal memuat data. Coba lagi.</div></div>');
                    }
                })
                .always(function () { $grid.removeClass('loading'); });
        }

        $form.on('submit', function (e) { e.preventDefault(); load(); });
        $form.on('change', 'select', load);
        $form.on('input', 'input[type="search"]', function () {
            clearTimeout(timer);
            timer = setTimeout(load, 300); // debounce
        });
        $('#filter-reset').on('click', function () {
            setTimeout(load, 0); // tunggu form ter-reset
        });
        updateCount();
    }

    // ---- Detail kucing: galeri ---------------------------------------------
    $('.mn-thumb-btn').on('click', function () {
        $('#gallery-main').attr('src', $(this).data('src'));
        $('.mn-thumb-btn').removeClass('active');
        $(this).addClass('active');
    });

    // ---- Login: validasi sisi klien + tampilkan password ---------------------
    $('#login-form').on('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    $('#toggle-pass').on('click', function () {
        var $i = $('#password');
        var show = $i.attr('type') === 'password';
        $i.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
    });
});
