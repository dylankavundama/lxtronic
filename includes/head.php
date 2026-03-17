<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Favicon -->
<link rel="icon" type="image/jpeg" href="favicon.jpg">
<link rel="shortcut icon" type="image/jpeg" href="favicon.jpg">
<!-- Custom CSS Design System -->
<link rel="stylesheet" href="assets/css/style.css">
<!-- Google Fonts (also imported in CSS fallback) -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&family=Courier+Prime&display=swap" rel="stylesheet">
<!-- Tailwind CDN - FOR ANIMATIONS ONLY (animate-bounce, animate-pulse, etc.) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  // Disable Tailwind's preflight/reset to avoid conflicts with our CSS
  tailwind.config = { corePlugins: { preflight: false } }
</script>

<!-- Splash Screen Loader -->
<div id="page-loader">
    <div class="loader-content">
        <img src="logo.jpg" alt="LxTronic" class="loader-logo">
        <h1 class="loader-title">LxTronic</h1>
        <p class="loader-slogan">Innovation At Your Service</p>
        <div class="loader-spinner"></div>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 800); // Durée minimale visible pour l'effet
    });
</script>
