import { createApp, defineAsyncComponent } from 'vue';

// Every .vue file in ./components can be mounted from Blade:
// <div data-vue="VisitorEntry" data-props='@json($props)'></div>
const components = import.meta.glob('./components/**/*.vue');

export function mountComponents() {
    document.querySelectorAll('[data-vue]').forEach((el) => {
        const name = el.dataset.vue;
        const loader = components[`./components/${name}.vue`];
        if (!loader) {
            console.error(`Vue component "${name}" not found in resources/js/components`);
            return;
        }

        const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
        createApp(defineAsyncComponent(loader), props).mount(el);
    });
}
