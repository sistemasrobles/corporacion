const TOPBAR_HTML = `<header class="topbar"><div class="topbar-left"><button class="sidebar-toggle" aria-label="Toggle sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button><div class="breadcrumb"><span>Home</span></div></div><div class="topbar-right"><button class="tb-avatar" title="User menu">A</button></div></header>`;

const SIDEBAR_HTML = `<aside class="sidebar"><div class="sidebar-brand"><div class="brand-icon">O</div><div class="brand-name">Orders</div></div><nav class="sidebar-nav"><div class="nav-group"><a href="/" class="nav-link" data-page="dashboard"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span class="nav-text">Dashboard</span></a><a href="/production/orders.html" class="nav-link" data-page="orders"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4m-21 2v12a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg><span class="nav-text">Mis Órdenes</span></a><a href="/production/invoice.html" class="nav-link" data-page="cuentas"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg><span class="nav-text">Cuentas por Pagar</span></a><a href="/production/order_detail.html" class="nav-link" data-page="historicas"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg><span class="nav-text">Órdenes Históricas</span></a></div></nav></aside>`;

const BACKDROP_HTML = `<div class="sidebar-backdrop" hidden></div>`;

const main = document.querySelector('main.main');
if (main && !document.querySelector('.sidebar')) {
  // Inject topbar
  const topbar = document.createElement('div');
  topbar.innerHTML = TOPBAR_HTML;
  document.body.insertBefore(topbar.firstElementChild, document.body.firstChild);

  // Inject sidebar
  const sidebar = document.createElement('div');
  sidebar.innerHTML = SIDEBAR_HTML;
  main.parentElement.insertBefore(sidebar.firstElementChild, main);

  // Inject mobile backdrop
  const backdrop = document.createElement('div');
  backdrop.innerHTML = BACKDROP_HTML;
  document.body.appendChild(backdrop.firstElementChild);

  const sidebarEl = document.querySelector('.sidebar');
  const backdropEl = document.querySelector('.sidebar-backdrop');
  const toggleBtn = document.querySelector('.sidebar-toggle');
  const currentPage = document.body.dataset.page;

  // Mark active nav link
  document.querySelectorAll('.nav-link').forEach(link => {
    if (link.dataset.page === currentPage) {
      link.classList.add('active');
    }
  });

  const isMobile = () => window.innerWidth < 769;

  const openMobile = () => {
    sidebarEl.classList.add('open');
    document.body.classList.add('sidebar-open');
    backdropEl.hidden = false;
  };
  const closeMobile = () => {
    sidebarEl.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    backdropEl.hidden = true;
  };

  // Toggle button: rail-collapse on desktop, slide-in/out on mobile
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (isMobile()) {
        sidebarEl.classList.contains('open') ? closeMobile() : openMobile();
      } else {
        document.body.classList.toggle('sidebar-rail');
      }
    });
  }

  // Backdrop click closes mobile drawer
  if (backdropEl) {
    backdropEl.addEventListener('click', closeMobile);
  }

  // Close mobile drawer after navigating
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => { if (isMobile()) closeMobile(); });
  });
}