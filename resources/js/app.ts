import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './vue/App.vue';
import { router } from './vue/router';

createApp(App).use(createPinia()).use(router).mount('#app');
