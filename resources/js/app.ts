import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './vue/App.vue';
import { router } from './vue/router';

const pinia = createPinia();
const app = createApp(App);
app.use(pinia);
app.use(router);
app.mount('#app');
