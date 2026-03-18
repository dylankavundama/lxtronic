<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<script src="assets/js/main.js" defer></script>

