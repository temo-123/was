import axios from 'axios';

// Same-origin requests to /api/* are authenticated with the session cookie (Sanctum stateful mode).
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

/** Ask for confirmation before submitting forms with data-confirm="...". */
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
