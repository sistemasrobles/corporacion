// Topbar shell — injected into every page
const TOPBAR_HTML = `
<header class="topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <div class="breadcrumb">
      <span>Home</span>
    </div>
  </div>
  <div class="search-box" style="display:none">
    <svg class="s-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" placeholder="Search pages or run a command" autocomplete="off">
    <kbd>⌘K</kbd>
  </div>
  <div class="topbar-right">
    <a href="https://docs.example.com" class="tb-btn tb-docs" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2H4a2 2 0 00-2 2v16a2 2 0 002 2h16a2 2 0 002-2V12"/><polyline points="16 2 20 2 20 6"/><line x1="12" y1="11" x2="20" y2="3"/></svg>
      Docs
    </a>
    <button class="tb-btn theme-toggle" title="Toggle dark mode" style="display:none">
      <svg class="theme-icon-light" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="5"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"/></svg>
      <svg class="theme-icon-dark" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <button class="tb-btn tb-notifications" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span class="dot"></span>
    </button>
    <button class="tb-btn tb-messages" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span class="dot"></span>
    </button>
    <button class="tb-avatar" title="User menu">A</button>
  </div>
</header>
`;

// Sidebar shell — injected into every page
const SIDEBAR_HTML = `
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">O</div>
    <div class="brand-name">Orders</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-group">
      <a href="/" class="nav-link active" data-page="dashboard">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="/production/orders.html" class="nav-link" data-page="orders">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4m-21 2v12a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg>
        <span class="nav-text">Mis Órdenes</span>
      </a>
      <a href="/production/invoice.html" class="nav-link" data-page="cuentas">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        <span class="nav-text">Cuentas por Pagar</span>
      </a>
      <a href="/production/order_detail.html" class="nav-link" data-page="historicas">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11z"/></svg>
        <span class="nav-text">Órdenes Históricas</span>
      </a>
    </div>
  </nav>
</aside>
`;

function initShell() {
  const main = document.querySelector('main.main');
  if (!main || document.querySelector('.sidebar')) return;

  // Insert topbar
  const topbar = document.createElement('div');
  topbar.innerHTML = TOPBAR_HTML;
  document.body.insertBefore(topbar.firstElementChild, document.body.firstChild);

  // Insert sidebar
  const sidebar = document.createElement('div');
  sidebar.innerHTML = SIDEBAR_HTML;
  main.parentElement.insertBefore(sidebar.firstElementChild, main);

  const sidebarEl = document.querySelector('.sidebar');
  const toggleBtn = document.querySelector('.sidebar-toggle');
  const breadcrumbEl = document.querySelector('.breadcrumb');
  const currentPage = document.body.dataset.page;

  // Mark active link
  document.querySelectorAll('.nav-link').forEach(link => {
    if (link.dataset.page === currentPage) {
      link.classList.add('active');
      if (breadcrumbEl) {
        breadcrumbEl.innerHTML = `<span>${link.textContent.trim()}</span>`;
      }
    }
  });

  // Sidebar toggle
  if (toggleBtn && sidebarEl) {
    toggleBtn.addEventListener('click', () => {
      sidebarEl.classList.toggle('open');
      document.body.classList.toggle('sidebar-open');
    });
  }

  // Close on nav click (mobile)
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 769) {
        sidebarEl.classList.remove('open');
        document.body.classList.remove('sidebar-open');
      }
    });
  });
}

// Run immediately and also on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initShell);
} else {
  initShell();
}