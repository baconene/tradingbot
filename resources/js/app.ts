import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Dashboard from './pages/Dashboard.vue';
import '../css/app.css';
createInertiaApp({
  resolve: name => ({ Dashboard })[name] ?? Dashboard,
  setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
  progress: { color: '#7cc4ab' },
});
