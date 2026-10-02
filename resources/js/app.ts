import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');

createInertiaApp({
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`];
    if (!page) {
      throw new Error(`Inertia page not found: ${name}`);
    }
    return page();
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
  progress: { color: '#7cc4ab' },
});
