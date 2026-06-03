import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import Login from './views/Login.vue';
import Register from './views/Register.vue';
import Dashboard from './views/Dashboard.vue';
import ForgotPassword from './views/ForgotPassword.vue';
import Configuration from './views/Configuration.vue';
import Games from './views/Games.vue';
import Projects from './views/Projects.vue';
import ProjectShow from './views/ProjectShow.vue';
import Profile from './views/Profile.vue';
import RecordDetail from './views/RecordDetail.vue';
import RecordForm from './views/RecordForm.vue';
import Records from './views/Records.vue';
import Reports from './views/Reports.vue';
import ResetPassword from './views/ResetPassword.vue';
import Search from './views/Search.vue';
import Sudoku from './views/Sudoku.vue';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login, meta: { guest: true } },
    { path: '/register', component: Register, meta: { guest: true } },
    { path: '/forgot-password', component: ForgotPassword, meta: { guest: true } },
    { path: '/reset-password/:token', component: ResetPassword, meta: { guest: true } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: Dashboard },
    { path: '/search', component: Search },
    { path: '/profile', component: Profile },
    { path: '/configuration', component: Configuration },
    { path: '/projects', component: Projects },
    { path: '/projects/:id', component: ProjectShow },
    { path: '/daily-progress', component: Records, props: { type: 'daily-progress' } },
    { path: '/daily-progress/create', component: RecordForm, props: { type: 'daily-progress' } },
    { path: '/daily-progress/:id', component: RecordDetail, props: (route) => ({ type: 'daily-progress', id: route.params.id }) },
    { path: '/daily-progress/:id/edit', component: RecordForm, props: (route) => ({ type: 'daily-progress', id: route.params.id }) },
    { path: '/work-logs', component: Records, props: { type: 'work-logs' } },
    { path: '/work-logs/create', component: RecordForm, props: { type: 'work-logs' } },
    { path: '/work-logs/:id', component: RecordDetail, props: (route) => ({ type: 'work-logs', id: route.params.id }) },
    { path: '/work-logs/:id/edit', component: RecordForm, props: (route) => ({ type: 'work-logs', id: route.params.id }) },
    { path: '/tasks', component: Records, props: { type: 'tasks' } },
    { path: '/tasks/create', component: RecordForm, props: { type: 'tasks' } },
    { path: '/tasks/:id', component: RecordDetail, props: (route) => ({ type: 'tasks', id: route.params.id }) },
    { path: '/tasks/:id/edit', component: RecordForm, props: (route) => ({ type: 'tasks', id: route.params.id }) },
    { path: '/learning', component: Records, props: { type: 'learning' } },
    { path: '/learning/create', component: RecordForm, props: { type: 'learning' } },
    { path: '/learning/:id', component: RecordDetail, props: (route) => ({ type: 'learning', id: route.params.id }) },
    { path: '/learning/:id/edit', component: RecordForm, props: (route) => ({ type: 'learning', id: route.params.id }) },
    { path: '/milestones', component: Records, props: { type: 'milestones' } },
    { path: '/milestones/create', component: RecordForm, props: { type: 'milestones' } },
    { path: '/milestones/:id', component: RecordDetail, props: (route) => ({ type: 'milestones', id: route.params.id }) },
    { path: '/milestones/:id/edit', component: RecordForm, props: (route) => ({ type: 'milestones', id: route.params.id }) },
    { path: '/reports/:period', component: Reports },
    { path: '/games', component: Games },
    { path: '/games/sudoku', component: Sudoku },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.booted) await auth.boot();
  if (to.meta.guest && auth.user) return '/dashboard';
  if (!to.meta.guest && !auth.user) return '/login';
});
