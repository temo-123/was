// Tech tips page on the gym tablet (resources/views/tips/kiosk.blade.php): only the Vue app, no Bootstrap JS.
import { mountComponents } from './mount';

mountComponents();

// A kiosk: no long-press menu, no pinch zoom of the whole page.
document.addEventListener('contextmenu', (event) => event.preventDefault());
document.addEventListener('gesturestart', (event) => event.preventDefault());
