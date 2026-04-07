<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- PWA Manifest & iOS Support -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2563eb">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="LxTronic">
<link rel="apple-touch-icon" href="logo.jpg">


<!-- Favicon -->
<link rel="icon" type="image/jpeg" href="favicon.jpg">
<link rel="shortcut icon" type="image/jpeg" href="favicon.jpg">
<!-- Custom CSS Design System -->
<link rel="stylesheet" href="assets/css/global.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<?php
$page_css = "assets/css/pages/" . basename($_SERVER['PHP_SELF'], '.php') . ".css";
if (file_exists($page_css)) {
    echo '<link rel="stylesheet" href="' . $page_css . '">' . "\n";
}
?>
<!-- Google Fonts (also imported in CSS fallback) -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&family=Courier+Prime&display=swap" rel="stylesheet">
<!-- Tailwind CDN - FOR ANIMATIONS ONLY (animate-bounce, animate-pulse, etc.) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  // Disable Tailwind's preflight/reset to avoid conflicts with our CSS
  tailwind.config = { corePlugins: { preflight: false } }
</script>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js')
        .then(reg => console.log('SW Registered'))
        .catch(err => console.log('SW Reg Failed', err));
    });
  }
</script>
<script src="assets/js/offline_sync.js"></script>
