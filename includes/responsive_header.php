<?php
// responsive_header.php - Mobile header with burger menu
?>
<div class="mobile-top-bar no-print">
    <div style="display: flex; align-items: center; gap: 12px;">
        <button class="burger-btn" onclick="toggleSidebar()" aria-label="Menu">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
        </button>
        <span style="font-weight: 800; font-size: 1.1rem; color: var(--color-slate-800);">LXTRONIC</span>
    </div>
    <img src="logo.jpg" alt="Logo" style="width:32px; height:32px; object-fit:contain; border-radius:6px;">
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    
    // Empêcher le scroll du body quand le menu est ouvert
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}
</script>
