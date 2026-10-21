import './bootstrap';
import './forms';
import 'bootstrap';
import { mountComponents } from './mount';

// Public site. Vue components can be added to ./components and mounted with
// <div data-vue="ComponentName" data-props='{"foo": 1}'></div>
mountComponents();
