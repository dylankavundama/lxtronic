// offline_sync.js - Universal Sync Engine for LXTRONIC
const DB_NAME = 'LxtronicOffline';
const DB_VERSION = 2; // Incremented for new stores

const STORES = {
    ACTIONS: 'pending_actions',
    PRODUCTS: 'products',
    CLIENTS: 'clients',
    CATEGORIES: 'categories',
    SETTINGS: 'settings'
};

let db;

// 1. DATABASE INITIALIZATION
function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            // Action queue for anything that needs to be sent to server
            if (!db.objectStoreNames.contains(STORES.ACTIONS)) {
                db.createObjectStore(STORES.ACTIONS, { keyPath: 'tempId', autoIncrement: true });
            }
            // Reference stores for offline viewing/usage
            if (!db.objectStoreNames.contains(STORES.PRODUCTS)) {
                db.createObjectStore(STORES.PRODUCTS, { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains(STORES.CLIENTS)) {
                db.createObjectStore(STORES.CLIENTS, { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains(STORES.CATEGORIES)) {
                db.createObjectStore(STORES.CATEGORIES, { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains(STORES.SETTINGS)) {
                db.createObjectStore(STORES.SETTINGS, { keyPath: 'key' });
            }
        };
        request.onsuccess = (e) => {
            db = e.target.result;
            resolve(db);
        };
        request.onerror = (e) => reject(e.target.error);
    });
}

// 2. DATA PERSISTENCE (Generic)
async function putData(storeName, data) {
    if (!db) await initDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);

        if (Array.isArray(data)) {
            store.clear(); // Clear existing to avoid duplicates/stale
            data.forEach(item => store.put(item));
        } else {
            store.put(data);
        }

        transaction.oncomplete = () => resolve(true);
        transaction.onerror = (e) => reject(e.target.error);
    });
}

async function getData(storeName, key = null) {
    if (!db) await initDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const request = key ? store.get(key) : store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = (e) => reject(e.target.error);
    });
}

// 3. ACTION QUEUE MANAGEMENT
async function queueAction(type, payload) {
    return putData(STORES.ACTIONS, {
        type: type,
        payload: payload,
        timestamp: new Date().toISOString()
    });
}

async function removeAction(tempId) {
    if (!db) await initDB();
    const transaction = db.transaction([STORES.ACTIONS], 'readwrite');
    transaction.objectStore(STORES.ACTIONS).delete(tempId);
}

// 4. REFERENCE DATA SYNC (Download from Server)
async function syncReferenceData() {
    if (!navigator.onLine) return;
    try {
        const res = await fetch('sync_reference_data.php');
        const data = await res.json();
        if (data.success) {
            await putData(STORES.PRODUCTS, data.products);
            await putData(STORES.CLIENTS, data.clients);
            await putData(STORES.CATEGORIES, data.categories);
            // Settings map to array of {key, value} for IndexedDB
            const settingsArr = Object.entries(data.settings).map(([k, v]) => ({ key: k, value: v }));
            await putData(STORES.SETTINGS, settingsArr);
            console.log('SW: Reference data updated from server');
        }
    } catch (err) {
        console.warn('SyncRef error:', err);
    }
}

// 5. OFFLINE DATA SYNC (Upload to Server)
async function syncPendingActions() {
    if (!navigator.onLine) return;

    const actions = await getData(STORES.ACTIONS);
    if (actions.length === 0) return;

    console.log(`Syncing ${actions.length} actions...`);

    for (const action of actions) {
        try {
            let endpoint = '';
            const formData = new FormData();

            // Map action types to endpoints
            switch (action.type) {
                case 'sale':
                    endpoint = 'sync_sales.php';
                    Object.entries(action.payload).forEach(([k, v]) => formData.append(k, v));
                    break;
                case 'client':
                    endpoint = 'quick_add_client.php';
                    Object.entries(action.payload).forEach(([k, v]) => formData.append(k, v));
                    break;
                case 'expense':
                    endpoint = 'expenses.php';
                    formData.append('action', 'save');
                    Object.entries(action.payload).forEach(([k, v]) => formData.append(k, v));
                    break;
                // Add more cases as needed
            }

            if (!endpoint) continue;

            // Indispensable pour que le backend sache qu'il doit retourner du JSON et non du HTML !
            formData.append('is_ajax', '1');

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });

            const resData = await res.json();
            if (resData.success) {
                await removeAction(action.tempId);
                console.log(`Action ${action.type} synced: ${action.tempId}`);
            }
        } catch (err) {
            console.error('Sync execution error:', err);
            break;
        }
    }
}

// 6. INITIALIZATION & LISTENERS
window.addEventListener('load', async () => {
    try {
        await initDB();
        await syncReferenceData();
        await syncPendingActions();
    } catch (e) {
        console.error('Sync Init Failed:', e);
    }
});

window.addEventListener('online', () => {
    syncReferenceData();
    syncPendingActions();
});
