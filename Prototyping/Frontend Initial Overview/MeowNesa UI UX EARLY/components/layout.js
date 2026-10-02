/**
 * MeowNesa Shared Layout Component System
 * Mengatur Header (Top Notification Carousel + Dismiss + Navbar) dan Footer untuk semua halaman.
 */

(function() {
  // Hitung base path relatif berdasarkan kedalaman folder file saat ini
  function getBasePath() {
    const currentScript = document.currentScript;
    if (currentScript && currentScript.getAttribute('data-root')) {
      return currentScript.getAttribute('data-root');
    }
    const path = window.location.pathname.replace(/\\/g, '/');
    if (path.includes('/src/cat-informations/') || path.includes('/src/adoption/') || path.includes('/src/cms-blog-awarness/') || path.includes('/src/donation/')) {
      return '../../';
    } else if (path.includes('/src/')) {
      return '../';
    }
    return './';
  }

  const BASE_PATH = getBasePath();

  // Template Header & Top Notification Carousel
  function getHeaderHTML(activeNav) {
    return `
    <!-- ================= TOP ANNOUNCEMENT BAR (ROTATING + DISMISSIBLE) ================= -->
    <div id="topAnnouncementBar" class="bg-magenta text-white text-xs py-2 px-4 border-b border-magenta-dark/30 select-none transition-all duration-300">
      <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
        
        <!-- Rotating Announcement Items -->
        <div class="flex items-center gap-2 overflow-hidden h-5 flex-1 min-w-0">
          <div id="topNotifContainer" class="flex flex-col transition-transform duration-500">
            
            <!-- Notif 1: Populasi Resmi -->
            <div class="flex items-center gap-2 h-5">
              <span class="bg-sun text-ink font-extrabold px-2 py-0.5 rounded-full text-[10px] tracking-wide uppercase flex items-center gap-1 flex-shrink-0">
                <i data-lucide="shield-check" class="w-3 h-3"></i> Populasi Resmi
              </span>
              <span class="truncate">Kucing yang terdata di sistem ini adalah kucing binaan <strong>Jaga Satwa UNESA</strong> sejak 2021.</span>
            </div>

            <!-- Notif 2: Sterilisasi & Vaksinasi Medis -->
            <div class="flex items-center gap-2 h-5">
              <span class="bg-emerald-400 text-ink font-extrabold px-2 py-0.5 rounded-full text-[10px] tracking-wide uppercase flex items-center gap-1 flex-shrink-0">
                <i data-lucide="stethoscope" class="w-3 h-3"></i> Sterilisasi Medis
              </span>
              <span class="truncate">100% kucing terdata telah divaksinasi &amp; disteril berkala bersama <strong>Klinik Nusantara Vet</strong>.</span>
            </div>

            <!-- Notif 3: Proteksi Kalung Kucing -->
            <div class="flex items-center gap-2 h-5">
              <span class="bg-coral text-white font-extrabold px-2 py-0.5 rounded-full text-[10px] tracking-wide uppercase flex items-center gap-1 flex-shrink-0">
                <i data-lucide="tag" class="w-3 h-3"></i> Proteksi Kalung
              </span>
              <span class="truncate">Dilarang melepas kalung kucing UNESA! Kalung reflektif melindungi anabul di malam hari.</span>
            </div>

          </div>
        </div>

        <!-- Right Quick Links & Dismiss Button -->
        <div class="flex items-center gap-3 text-xs flex-shrink-0">
          <a href="${BASE_PATH}src/donation/index.html" class="hover:underline font-bold text-sun flex items-center gap-1">
            <i data-lucide="heart-handshake" class="w-3.5 h-3.5"></i> Donasi Resmi
          </a>
          <span class="text-white/40">•</span>
          <a href="https://forms.gle/unesa-jagasatwa-rescue" target="_blank" class="hover:underline font-bold text-sun flex items-center gap-1">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> Lapor Kucing (G-Form)
          </a>
          <span class="text-white/40">•</span>
          <a href="${BASE_PATH}src/adoption/tracking.html" class="hover:underline font-medium text-white/90 flex items-center gap-1">
            <i data-lucide="search" class="w-3.5 h-3.5"></i> Lacak Pengajuan
          </a>
          <span class="text-white/40">|</span>
          <!-- Dismiss Button -->
          <button onclick="document.getElementById('topAnnouncementBar').style.display='none'" class="w-5 h-5 rounded-full hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition font-bold" title="Tutup Notifikasi" aria-label="Tutup Notifikasi">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
          </button>
        </div>

      </div>
    </div>

    <!-- ================= MAIN NAVBAR ================= -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-line shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between">
        
        <!-- Brand Co-Logo: MeowNesa × Jaga Satwa -->
        <div class="flex items-center gap-2.5 sm:gap-3">
          <a href="${BASE_PATH}index.html" class="inline-flex items-center">
            <img src="${BASE_PATH}images/meownesa-logo.png" alt="MeowNesa" class="h-6 sm:h-7 w-auto object-contain brightness-0">
          </a>
          <span class="text-ink/20 font-bold text-xs select-none">✕</span>
          <a href="${BASE_PATH}index.html" class="inline-flex items-center">
            <img src="${BASE_PATH}images/jagasatwa-logo.png" alt="Jaga Satwa UNESA" class="h-5 sm:h-6 w-auto object-contain brightness-0">
          </a>
        </div>

        <!-- Navigation Links -->
        <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold text-ink-light">
          <a href="${BASE_PATH}index.html" class="${activeNav === 'beranda' ? 'text-magenta font-bold' : 'hover:text-magenta'} transition">Beranda</a>
          <a href="${BASE_PATH}src/cat-informations/index.html" class="${activeNav === 'informasi' ? 'text-magenta font-bold' : 'hover:text-magenta'} transition">Informasi Kucing</a>
          <a href="${BASE_PATH}src/cms-blog-awarness/blog.html" class="${activeNav === 'artikel' ? 'text-magenta font-bold' : 'hover:text-magenta'} transition">Artikel &amp; Edukasi</a>
          <a href="${BASE_PATH}src/donation/index.html" class="${activeNav === 'donasi' ? 'text-magenta font-bold' : 'hover:text-magenta'} transition">Donasi</a>
        </nav>

        <!-- Action Buttons -->
        <div class="hidden sm:flex items-center gap-3">
          <a href="${BASE_PATH}src/adoption/tracking.html" class="px-4 py-2.5 rounded-full border border-line hover:border-magenta text-ink hover:text-magenta text-xs font-bold transition bg-cream/40 flex items-center gap-1.5">
            <i data-lucide="search" class="w-3.5 h-3.5 text-magenta"></i>
            Lacak Pengajuan
          </a>
          <a href="${BASE_PATH}src/adoption/apply.html" class="px-5 py-2.5 rounded-full bg-magenta hover:bg-magenta-dark text-white text-xs font-bold shadow-md transition flex items-center gap-1.5">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            Ajukan Adopsi
          </a>
        </div>

        <!-- Mobile Button -->
        <button id="mobileMenuBtn" class="lg:hidden w-10 h-10 rounded-xl border border-line flex flex-col items-center justify-center gap-1.5 hover:bg-cream transition" aria-label="Menu Mobile">
          <span class="w-5 h-0.5 bg-ink rounded"></span>
          <span class="w-5 h-0.5 bg-ink rounded"></span>
          <span class="w-5 h-0.5 bg-ink rounded"></span>
        </button>

      </div>

      <!-- Mobile Menu Dropdown -->
      <div id="mobileMenu" class="lg:hidden hidden border-t border-line bg-white px-5 py-4 flex-col gap-3 text-sm font-semibold">
        <a href="${BASE_PATH}index.html" class="py-2 ${activeNav === 'beranda' ? 'text-magenta font-bold' : 'hover:text-magenta'}">Beranda</a>
        <a href="${BASE_PATH}src/cat-informations/index.html" class="py-2 ${activeNav === 'informasi' ? 'text-magenta font-bold' : 'hover:text-magenta'}">Informasi Kucing</a>
        <a href="${BASE_PATH}src/cms-blog-awarness/blog.html" class="py-2 ${activeNav === 'artikel' ? 'text-magenta font-bold' : 'hover:text-magenta'}">Artikel &amp; Edukasi</a>
        <a href="${BASE_PATH}src/donation/index.html" class="py-2 ${activeNav === 'donasi' ? 'text-magenta font-bold' : 'hover:text-magenta'}">Donasi</a>
        
        <div class="pt-3 border-t border-line flex flex-col gap-2">
          <a href="${BASE_PATH}src/adoption/tracking.html" class="w-full py-2.5 rounded-xl border border-line text-xs font-bold text-ink text-center flex items-center justify-center gap-1.5">
            <i data-lucide="search" class="w-3.5 h-3.5 text-magenta"></i> Lacak Pengajuan
          </a>
          <a href="${BASE_PATH}src/adoption/apply.html" class="w-full py-2.5 rounded-xl bg-magenta text-white text-xs font-bold text-center flex items-center justify-center gap-1.5">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Ajukan Adopsi
          </a>
        </div>
      </div>
    </header>
    `;
  }

  // Template Footer Standar dari Landing Page Utama
  function getFooterHTML() {
    return `
    <footer class="bg-ink text-white border-t border-magenta-900/40 pt-16 pb-10 mt-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6">
        
        <div class="grid sm:grid-cols-2 lg:grid-cols-12 gap-8 pb-12 border-b border-white/10">
          
          <!-- Col 1: Brand & Organization Info (5 cols) -->
          <div class="lg:col-span-5 flex flex-col gap-3">
            <div class="flex items-center gap-2.5">
              <a href="${BASE_PATH}index.html" class="inline-flex items-center">
                <img src="${BASE_PATH}images/meownesa-logo.png" alt="MeowNesa" class="h-5 sm:h-6 w-auto object-contain brightness-0 invert">
              </a>
              <span class="text-white/30 font-bold text-xs select-none">✕</span>
              <a href="${BASE_PATH}index.html" class="inline-flex items-center">
                <img src="${BASE_PATH}images/jagasatwa-logo.png" alt="Jaga Satwa UNESA" class="h-4.5 sm:h-5 w-auto object-contain brightness-0 invert">
              </a>
            </div>

            <p class="text-xs text-white/75 leading-relaxed max-w-sm">
              Platform informasi populasi kucing dan adopsi kucing di Universitas Negeri Surabaya bersama komunitas Jaga Satwa UNESA.
            </p>
          </div>

          <!-- Col 2: Navigasi Web (2 cols) -->
          <div class="lg:col-span-2 flex flex-col gap-2.5 text-xs text-white/80">
            <span class="font-display font-bold text-sm text-white mb-1">Navigasi</span>
            <a href="${BASE_PATH}index.html" class="hover:text-sun transition">Beranda</a>
            <a href="${BASE_PATH}src/cat-informations/index.html" class="hover:text-sun transition">Informasi Kucing</a>
            <a href="${BASE_PATH}src/adoption/apply.html" class="hover:text-sun transition">Adopsi Kucing</a>
            <a href="${BASE_PATH}src/cms-blog-awarness/blog.html" class="hover:text-sun transition">Artikel &amp; Edukasi</a>
            <a href="${BASE_PATH}src/donation/index.html" class="hover:text-sun transition">Donasi Resmi</a>
          </div>

          <!-- Col 3: Layanan & Pelaporan (2 cols) -->
          <div class="lg:col-span-2 flex flex-col gap-2.5 text-xs text-white/80">
            <span class="font-display font-bold text-sm text-white mb-1">Layanan</span>
            <a href="${BASE_PATH}src/adoption/apply.html" class="hover:text-sun transition">Formulir Adopsi</a>
            <a href="${BASE_PATH}src/adoption/tracking.html" class="hover:text-sun transition">Lacak Pengajuan</a>
            <a href="https://forms.gle/unesa-jagasatwa-rescue" target="_blank" class="hover:text-sun transition">Lapor Kucing (G-Form)</a>
            <a href="https://instagram.com/jagasatwa.unesa" target="_blank" class="hover:text-sun transition">Instagram Resmi</a>
          </div>

          <!-- Col 4: Kampus UNESA (3 cols) -->
          <div class="lg:col-span-3 flex flex-col gap-2.5 text-xs text-white/80">
            <span class="font-display font-bold text-sm text-white mb-1">Wilayah Kampus</span>
            <div class="flex flex-col gap-3">
              <div>
                <strong class="text-white block font-semibold">Kampus Ketintang:</strong>
                <span class="text-white/60">Jl. Ketintang, Gayungan, Surabaya</span>
              </div>
              <div>
                <strong class="text-white block font-semibold">Kampus Lidah Wetan:</strong>
                <span class="text-white/60">Jl. Raya Kampus UNESA, Lakarsantri</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Copyright Bottom Bar -->
        <div class="pt-8 flex flex-wrap items-center justify-between gap-4 text-xs text-white/60">
          <p>© 2026 MeowNesa × Jaga Satwa UNESA. Dikembangkan oleh Kelompok 7 D4 Manajemen Informatika UNESA.</p>
          <span class="text-white/40">Surabaya, Indonesia</span>
        </div>

      </div>
    </footer>
    `;
  }

  // Rotating Notification Interval Logic
  function startNotifCarousel() {
    const container = document.getElementById('topNotifContainer');
    if (!container) return;

    let currentIndex = 0;
    const totalItems = 3;

    setInterval(() => {
      currentIndex = (currentIndex + 1) % totalItems;
      container.style.transform = `translateY(-${currentIndex * 20}px)`;
    }, 4000);
  }

  // Setup Mobile Menu Logic
  function setupMobileMenu() {
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileBtn && mobileMenu) {
      mobileBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
        mobileMenu.classList.toggle('flex');
      });
    }
  }

  // Inisialisasi Rendering
  function renderLayout() {
    const headerEl = document.getElementById('app-header');
    const footerEl = document.getElementById('app-footer');

    if (headerEl) {
      const activeNav = headerEl.getAttribute('data-active-nav') || '';
      headerEl.innerHTML = getHeaderHTML(activeNav);
      setupMobileMenu();
      startNotifCarousel();
    }

    if (footerEl) {
      footerEl.innerHTML = getFooterHTML();
    }

    if (window.lucide) {
      lucide.createIcons();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderLayout);
  } else {
    renderLayout();
  }
})();
