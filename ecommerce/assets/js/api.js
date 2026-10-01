const API_BASE = '/ecommerce/api/';

async function apiFetch(endpoint, options = {}) {
    const token = localStorage.getItem('token');

    const config = {
        method: options.method || 'GET',
        headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
        }
    };

    if (options.body) {
        config.body = JSON.stringify(options.body);
    }

    const res = await fetch(API_BASE + endpoint, config);
    const data = await res.json();

    if (res.status === 401) {
        localStorage.removeItem('token');
        window.location.href = 'login.html';
    }

    return data;
}

// Dekodiraj payload iz JWT (bez verifikacije — samo za prikaz)
function decodeToken(token) {
    try {
        const payload = token.split('.')[1];
        return JSON.parse(atob(payload));
    } catch {
        return null;
    }
}

function isLoggedIn() {
    const token = localStorage.getItem('token');
    if (!token) return false;
    const payload = decodeToken(token);
    if (!payload || payload.exp * 1000 < Date.now()) {
        localStorage.removeItem('token');
        return false;
    }
    return true;
}  