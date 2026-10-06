/**
 * MeowNesa Admin Shared Layout Component System
 * Mengatur Sidebar Navigasi, Topbar Toolbar, Notification Bell, dan Auth Session untuk seluruh modul Admin.
 */

(function() {
  function getBasePath() {
    const currentScript = document.currentScript;
    if (currentScript && currentScript.getAttribute('data-root')) {
      return currentScript.getAttribute('data-root');
    }
    const path = window.location.pathname.replace(/\\/g, '/');
    if (path.includes('/src/admin/')) {
      return '../../';
    } else if (path.includes('/src/')) {
      return '../';
    }
    return './';
  }

  const BASE_PATH = getBasePath();
  const ADMIN_PATH = BASE_PATH + 'src/admin/';

  // Template Sidebar Admin
  function getAdminSidebarHTML(activeMenu) {
    const menus = [
      { id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', href: ADMIN_PATH + 'dashboard.html', badge: null },
      { id: 'cats', label: 'Data Populasi Kucing', icon: 'shield-check', href: ADMIN_PATH + 'cats.html', badge: '6' },
      { id: 'feeding', label: 'Feeding Log Harian', icon: 'utensils', href: ADMIN_PATH + 'feeding.html', badge: 'Aktif' },
      { id: 'health', label: 'Rekam Medis & Klinik', icon: 'stethoscope', href: ADMIN_PATH + 'health.html', badge: null },
      { id: 'adoptions', label: 'Screening Adopsi', icon: 'clipboard-check', href: ADMIN_PATH + 'adoptions.html', badge: '2 Baru', badgeColor: 'bg-sun text-ink' },
      { id: 'monitoring', label: 'Monitoring Pasca-Adopsi', icon: 'heart-handshake', href: ADMIN_PATH + 'monitoring.html', badge: '1 Alert', badgeColor: 'bg-coral text-white' },
      { id: 'cms', label: 'CMS Artikel & Edukasi', icon: 'book-open', href: ADMIN_PATH + 'cms.html', badge: null },
      { id: 'superadmin', label: 'Super Admin & Sistem', icon: 'shield-alert', href: ADMIN_PATH + 'superadmin.html', badge: 'SUPER', badgeColor: 'bg-amber-400 text-ink font-black shadow-xs' },
      { id: 'audit', label: 'Audit Log Aktivitas', icon: 'history', href: ADMIN_PATH + 'audit.html', badge: null }
    ];

    let userName = 'Super Admin JS';
    let userRole = 'Ketua & Lead Pengurus';
    let userInitials = 'SA';

    try {
      const storedUser = localStorage.getItem('meownesa_auth_user');
      if (storedUser) {
        const u = JSON.parse(storedUser);
        userName = u.name || userName;
        userRole = u.roleLabel || u.role || userRole;
        userInitials = userName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'SA';
      }
    } catch (e) {}

    return `
    <div class="h-full flex flex-col justify-between bg-ink text-white w-64 border-r border-magenta-900/40 select-none">
      
      <!-- Brand Logo Header -->
      <div>
        <div class="h-20 px-6 flex items-center justify-between border-b border-white/10">
          <div class="flex items-center gap-2.5">
            <a href="${ADMIN_PATH}dashboard.html" class="inline-flex items-center">
              <img src="${BASE_PATH}images/meownesa-logo.png" alt="MeowNesa" class="h-6 w-auto object-contain brightness-0 invert">
            </a>
            <span class="text-white/30 font-bold text-xs">✕</span>
            <span class="text-[10px] font-extrabold uppercase tracking-wider bg-magenta text-white px-2 py-0.5 rounded-md">
              ADMIN
            </span>
          </div>
          <button id="closeAdminSidebarBtn" class="lg:hidden text-white/70 hover:text-white p-1">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <!-- Community Seal -->
        <div class="px-6 py-3 bg-white/5 border-b border-white/5 flex items-center gap-2 text-xs text-white/70">
          <img src="${BASE_PATH}images/jagasatwa-logo.png" alt="Jaga Satwa" class="h-4 w-auto object-contain brightness-0 invert">
          <span class="font-semibold truncate">Komunitas Jaga Satwa UNESA</span>
        </div>

        <!-- Navigation Menu Items -->
        <nav class="p-4 space-y-1.5 text-xs font-semibold">
          ${menus.map(m => {
            const isActive = m.id === activeMenu;
            const activeClass = isActive 
              ? 'bg-magenta text-white shadow-sm font-bold' 
              : 'text-white/80 hover:bg-white/10 hover:text-white';
            const defaultBadgeColor = m.badgeColor || 'bg-white/20 text-white';

            return `
              <a href="${m.href}" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl transition ${activeClass} group">
                <div class="flex items-center gap-3">
                  <i data-lucide="${m.icon}" class="w-4 h-4 ${isActive ? 'text-sun' : 'text-white/70 group-hover:text-sun'} transition"></i>
                  <span>${m.label}</span>
                </div>
                ${m.badge ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${defaultBadgeColor}">${m.badge}</span>` : ''}
              </a>
            `;
          }).join('')}
        </nav>
      </div>

      <!-- Bottom Profile & Public Site Link -->
      <div class="p-4 border-t border-white/10 flex flex-col gap-2">
        <a href="${BASE_PATH}index.html" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/90 text-xs font-semibold transition">
          <span class="flex items-center gap-2">
            <i data-lucide="external-link" class="w-3.5 h-3.5 text-sun"></i>
            <span>Lihat Website Publik</span>
          </span>
          <i data-lucide="arrow-up-right" class="w-3 h-3 text-white/40"></i>
        </a>

        <div class="p-3 rounded-2xl bg-white/10 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-magenta to-amber-500 text-white flex items-center justify-center font-bold text-xs flex-shrink-0 shadow-xs">
              ${userInitials}
            </div>
            <div class="min-w-0">
              <span class="text-xs font-bold text-white block truncate">${userName}</span>
              <span class="text-[10px] text-amber-300 font-semibold block truncate">${userRole}</span>
            </div>
          </div>
          <button onclick="adminLogout()" class="text-white/70 hover:text-coral p-1.5 rounded-lg hover:bg-white/10 transition" title="Keluar / Logout">
            <i data-lucide="log-out" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

    </div>
    `;
  }

  // Template Topbar Admin
  function getAdminTopbarHTML(title, breadcrumb) {
    const today = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    return `
    <header class="h-20 bg-white border-b border-line px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 shadow-xs">
      
      <!-- Left: Mobile Toggle & Breadcrumb -->
      <div class="flex items-center gap-4">
        <button id="openAdminSidebarBtn" class="lg:hidden w-10 h-10 rounded-xl border border-line flex items-center justify-center text-ink hover:bg-cream transition">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <div>
          <div class="flex items-center gap-1.5 text-[11px] text-ink-muted font-medium">
            <span>MeowNesa Admin</span>
            <span>/</span>
            <span class="text-magenta font-semibold">${breadcrumb || 'Dashboard'}</span>
          </div>
          <h1 class="font-display font-extrabold text-xl sm:text-2xl text-ink leading-tight">
            ${title || 'Dashboard Utama'}
          </h1>
        </div>
      </div>

      <!-- Right: Live Date, Notification Bell & Actions -->
      <div class="flex items-center gap-3 sm:gap-4">
        
        <!-- Live Date Pill (Hidden on xs) -->
        <div class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cream border border-line text-xs font-medium text-ink-light">
          <i data-lucide="calendar" class="w-3.5 h-3.5 text-magenta"></i>
          <span>${today}</span>
        </div>

        <!-- Notification Bell with Dropdown -->
        <div class="relative">
          <button id="notifBellBtn" onclick="toggleNotifDropdown()" class="w-10 h-10 rounded-xl border border-line flex items-center justify-center hover:bg-cream text-ink transition relative">
            <i data-lucide="bell" class="w-4 h-4 text-ink"></i>
            <span class="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-coral animate-pulse ring-2 ring-white"></span>
          </button>

          <!-- Notification Popup Menu -->
          <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-line rounded-3xl shadow-xl z-50 p-4 flex-col gap-3">
            <div class="flex items-center justify-between border-b border-line pb-2.5">
              <span class="font-display font-bold text-sm text-ink">Notifikasi &amp; Pengingat</span>
              <span class="text-[10px] bg-coral/10 text-coral font-bold px-2 py-0.5 rounded-full">2 Perlu Tindakan</span>
            </div>

            <div class="space-y-2.5 text-xs">
              <a href="${ADMIN_PATH}adoptions.html" class="p-3 rounded-2xl bg-sun/15 border border-sun/30 flex items-start gap-3 hover:bg-sun/25 transition">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-ink flex-shrink-0 mt-0.5"></i>
                <div>
                  <strong class="text-ink block font-bold text-xs">2 Pengajuan Adopsi Baru</strong>
                  <span class="text-[11px] text-ink-muted">Pemohon baru untuk Mochi &amp; Milo menunggu screening berkas.</span>
                </div>
              </a>

              <a href="${ADMIN_PATH}monitoring.html" class="p-3 rounded-2xl bg-coral/10 border border-coral/25 flex items-start gap-3 hover:bg-coral/20 transition">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-coral flex-shrink-0 mt-0.5"></i>
                <div>
                  <strong class="text-coral block font-bold text-xs">1 Update Adopter Jatuh Tempo</strong>
                  <span class="text-[11px] text-ink-muted">Adopter Emma belum mengirimkan kabar mingguan (>7 hari).</span>
                </div>
              </a>
            </div>

            <div class="pt-2 border-t border-line text-center">
              <span class="text-[10px] text-ink-muted">Data sinkron dengan pencatatan Jaga Satwa UNESA</span>
            </div>
          </div>
        </div>

        <!-- Quick Public Link -->
        <a href="${BASE_PATH}index.html" target="_blank" class="hidden md:flex px-3.5 py-2 rounded-xl bg-magenta hover:bg-magenta-dark text-white text-xs font-bold transition items-center gap-1.5 shadow-xs">
          <i data-lucide="globe" class="w-3.5 h-3.5"></i>
          <span>Web Publik</span>
        </a>

      </div>

    </header>
    `;
  }

  // Toggle Dropdown & Sidebar
  window.toggleNotifDropdown = function() {
    const drop = document.getElementById('notifDropdown');
    if (drop) {
      drop.classList.toggle('hidden');
      drop.classList.toggle('flex');
    }
  };

  window.adminLogout = function() {
    if (confirm('Apakah Anda yakin ingin keluar dari Admin Dashboard?')) {
      sessionStorage.removeItem('meownesa_admin_auth');
      window.location.href = ADMIN_PATH + 'index.html';
    }
  };

  function setupAdminEvents() {
    const sidebar = document.getElementById('admin-sidebar');
    const openBtn = document.getElementById('openAdminSidebarBtn');
    const closeBtn = document.getElementById('closeAdminSidebarBtn');
    const backdrop = document.getElementById('adminSidebarBackdrop');

    if (openBtn && sidebar) {
      openBtn.addEventListener('click', () => {
        sidebar.classList.remove('-translate-x-full');
        if (backdrop) backdrop.classList.remove('hidden');
      });
    }

    if (closeBtn && sidebar) {
      closeBtn.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        if (backdrop) backdrop.classList.add('hidden');
      });
    }

    if (backdrop && sidebar) {
      backdrop.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
      });
    }

    // Close notif dropdown on click outside
    document.addEventListener('click', (e) => {
      const bell = document.getElementById('notifBellBtn');
      const drop = document.getElementById('notifDropdown');
      if (drop && bell && !bell.contains(e.target) && !drop.contains(e.target)) {
        drop.classList.add('hidden');
        drop.classList.remove('flex');
      }
    });
  }

  function renderAdminLayout() {
    const sidebarEl = document.getElementById('admin-sidebar');
    const topbarEl = document.getElementById('admin-topbar');

    if (sidebarEl) {
      const activeMenu = sidebarEl.getAttribute('data-active-menu') || 'dashboard';
      sidebarEl.innerHTML = getAdminSidebarHTML(activeMenu);
    }

    if (topbarEl) {
      const title = topbarEl.getAttribute('data-page-title') || 'Dashboard Utama';
      const breadcrumb = topbarEl.getAttribute('data-breadcrumb') || 'Dashboard';
      topbarEl.innerHTML = getAdminTopbarHTML(title, breadcrumb);
    }

    setupAdminEvents();

    if (window.lucide) {
      lucide.createIcons();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderAdminLayout);
  } else {
    renderAdminLayout();
  }
})();
