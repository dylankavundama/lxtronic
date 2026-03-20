const CACHE_NAME = 'lxtronic-v2.1';
const ASSETS = [
    './',
    'index.php',
    'dashboard.php',
    'sales.php',
    'clients.php',
    'products.php',
    'categories.php',
    'expenses.php',
    'caisse.php',
    'reports.php',
    'settings.php',
    'invoice.php',
    'includes/head.php',
    'includes/sidebar.php',
    'includes/responsive_header.php',
    'assets/css/global.css',
    'assets/css/layout.css',
    'assets/css/components.css',
    'assets/js/offline_sync.js',
    'logo.jpg',
    'favicon.jpg',
    'manifest.json',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&family=Courier+Prime&display=swap',
    'https://cdn.tailwindcss.com'
];

// Installation : Mise en cache des ressources de base
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('SW: Pre-caching all vital assets');
            // use Promise.allSettled to ensure installation completes even if one asset fails
            return Promise.allSettled(ASSETS.map(asset => cache.add(asset)));
        })
    );
    self.skipWaiting();
});

// Activation : Nettoyage de l'ancien cache
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Stratégie : Network First (Priorité réseau, fallback cache)
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    // Ignorer les requêtes spécifiques à la sync (JSON) ou PHP d'action
    if (event.request.url.includes('sync_') || event.request.url.includes('quick_add_')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Mettre à jour le cache dynamiquement pour les nouvelles pages visitées
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Si réseau échoue, essayer le cache
                return caches.match(event.request).then(cachedResponse => {
                    if (cachedResponse) return cachedResponse;

                    // Si c'est une navigation (page PHP), on peut rediriger vers le dashboard par défaut
                    if (event.request.mode === 'navigate') {
                        return caches.match('dashboard.php');
                    }
                });
            })
    );
});
