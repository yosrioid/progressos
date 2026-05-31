import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import Login from './views/Login.vue';
import Register from './views/Register.vue';
import Dashboard from './views/Dashboard.vue';
import Projects from './views/Projects.vue';
import ProjectShow from './views/ProjectShow.vue';
import Records from './views/Records.vue';
import Reports from './views/Reports.vue';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login, meta: { guest: true } },
    { path: '/register', component: Register, meta: { guest: true } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: Dashboard },
    { path: '/projects', component: Projects },
    { path: '/projects/:id', component: ProjectShow },
    { path: '/daily-progress', component: Records, props: { type: 'daily-progress' } },
    { path: '/work-logs', component: Records, props: { type: 'work-logs' } },
    { path: '/tasks', component: Records, props: { type: 'tasks' } },
    { path: '/learning', component: Records, props: { type: 'learning' } },
    { path: '/milestones', component: Records, props: { type: 'milestones' } },
    { path: '/reports/:period', component: Reports },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.booted) await auth.boot();
  if (to.meta.guest && auth.user) return '/dashboard';
  if (!to.meta.guest && !auth.user) return '/login';
});
