import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from './stores/auth';
import NotFound from './views/NotFound.vue';

const routes: RouteRecordRaw[] = [
    // ── Public / guest ──────────────────────────────────────────────
    {
        path: '/login',
        component: () => import('./views/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/register',
        component: () => import('./views/Register.vue'),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        component: () => import('./views/ForgotPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/share/doc/:token',
        component: () => import('./views/DocShare.vue'),
        meta: { public: true },
    },
    {
        path: '/reset-password/:token',
        component: () => import('./views/ResetPassword.vue'),
        meta: { guest: true },
    },

    // ── Authenticated ───────────────────────────────────────────────
    {
        path: '/',
        redirect: () => (useAuthStore().isAdmin ? '/admin/users' : '/dashboard'),
    },
    { path: '/dashboard', component: () => import('./views/Dashboard.vue') },
    { path: '/weekly-review', redirect: '/reports/weekly' },
    { path: '/search', component: () => import('./views/Search.vue') },
    { path: '/profile', component: () => import('./views/Profile.vue') },
    { path: '/projects', component: () => import('./views/Projects.vue') },
    { path: '/projects/:id', component: () => import('./views/ProjectShow.vue') },

    // Daily-progress
    {
        path: '/daily-progress',
        component: () => import('./views/Records.vue'),
        props: { type: 'daily-progress' },
    },
    {
        path: '/daily-progress/create',
        component: () => import('./views/RecordForm.vue'),
        props: { type: 'daily-progress' },
    },
    {
        path: '/daily-progress/:id',
        component: () => import('./views/RecordDetail.vue'),
        props: (route) => ({ type: 'daily-progress', id: route.params.id }),
    },
    {
        path: '/daily-progress/:id/edit',
        component: () => import('./views/RecordForm.vue'),
        props: (route) => ({ type: 'daily-progress', id: route.params.id }),
    },

    // Work-logs
    {
        path: '/work-logs',
        component: () => import('./views/Records.vue'),
        props: { type: 'work-logs' },
    },
    {
        path: '/work-logs/create',
        component: () => import('./views/RecordForm.vue'),
        props: { type: 'work-logs' },
    },
    {
        path: '/work-logs/:id',
        component: () => import('./views/RecordDetail.vue'),
        props: (route) => ({ type: 'work-logs', id: route.params.id }),
    },
    {
        path: '/work-logs/:id/edit',
        component: () => import('./views/RecordForm.vue'),
        props: (route) => ({ type: 'work-logs', id: route.params.id }),
    },

    // Tasks
    {
        path: '/tasks',
        component: () => import('./views/Records.vue'),
        props: { type: 'tasks' },
    },
    {
        path: '/tasks/create',
        component: () => import('./views/RecordForm.vue'),
        props: { type: 'tasks' },
    },
    {
        path: '/tasks/:id',
        component: () => import('./views/RecordDetail.vue'),
        props: (route) => ({ type: 'tasks', id: route.params.id }),
    },
    {
        path: '/tasks/:id/edit',
        component: () => import('./views/RecordForm.vue'),
        props: (route) => ({ type: 'tasks', id: route.params.id }),
    },

    // Learning
    {
        path: '/learning',
        component: () => import('./views/Records.vue'),
        props: { type: 'learning' },
    },
    {
        path: '/learning/create',
        component: () => import('./views/RecordForm.vue'),
        props: { type: 'learning' },
    },
    {
        path: '/learning/:id',
        component: () => import('./views/RecordDetail.vue'),
        props: (route) => ({ type: 'learning', id: route.params.id }),
    },
    {
        path: '/learning/:id/edit',
        component: () => import('./views/RecordForm.vue'),
        props: (route) => ({ type: 'learning', id: route.params.id }),
    },

    // Milestones
    {
        path: '/milestones',
        component: () => import('./views/Records.vue'),
        props: { type: 'milestones' },
    },
    {
        path: '/milestones/create',
        component: () => import('./views/RecordForm.vue'),
        props: { type: 'milestones' },
    },
    {
        path: '/milestones/:id',
        component: () => import('./views/RecordDetail.vue'),
        props: (route) => ({ type: 'milestones', id: route.params.id }),
    },
    {
        path: '/milestones/:id/edit',
        component: () => import('./views/RecordForm.vue'),
        props: (route) => ({ type: 'milestones', id: route.params.id }),
    },

    // ── Productivity ────────────────────────────────────────────────
    { path: '/journal', component: () => import('./views/Journals.vue') },
    { path: '/journal/new', component: () => import('./views/JournalShow.vue'), props: { id: 'new' } },
    { path: '/journal/:id', component: () => import('./views/JournalShow.vue'), props: (route) => ({ id: route.params.id }) },
    { path: '/activity', component: () => import('./views/Activity.vue') },
    { path: '/habits', component: () => import('./views/Habits.vue') },
    { path: '/goals', component: () => import('./views/Goals.vue') },
    { path: '/tasks/board', component: () => import('./views/TaskBoard.vue') },

    // ── Reporting & docs ────────────────────────────────────────────
    { path: '/reports/:period', component: () => import('./views/Reports.vue') },
    { path: '/docs', component: () => import('./views/Docs.vue') },
    { path: '/docs/create', component: () => import('./views/DocForm.vue') },
    { path: '/docs/:id', component: () => import('./views/DocDetail.vue') },
    { path: '/docs/:id/edit', component: () => import('./views/DocForm.vue') },
    { path: '/lists', component: () => import('./views/Lists.vue') },
    { path: '/lists/:id', component: () => import('./views/ListDetail.vue') },
    { path: '/bills', component: () => import('./views/Bills.vue') },
    { path: '/money', component: () => import('./views/Money.vue') },

    // ── Games ───────────────────────────────────────────────────────
    { path: '/games', component: () => import('./views/Games.vue') },
    { path: '/games/sudoku', component: () => import('./views/Sudoku.vue') },
    { path: '/games/minesweeper', component: () => import('./views/Minesweeper.vue') },
    { path: '/games/2048', component: () => import('./views/Game2048.vue') },
    { path: '/games/memory', component: () => import('./views/MemoryMatch.vue') },
    { path: '/games/melody-memory', component: () => import('./views/MelodyMemory.vue') },
    { path: '/games/pitch-trainer', component: () => import('./views/PitchTrainer.vue') },

    // ── Productivity extras ────────────────────────────────────────
    { path: '/activity/analytics', component: () => import('./views/Analytics.vue') },
    { path: '/weekly-review', component: () => import('./views/WeeklyReview.vue') },
    { path: '/chat', component: () => import('./views/Chat.vue') },

    // ── Messaging & admin ───────────────────────────────────────────
    { path: '/inbox', component: () => import('./views/Inbox.vue') },
    {
        path: '/admin/users',
        component: () => import('./views/AdminUsers.vue'),
        meta: { admin: true },
    },
    {
        path: '/configuration',
        component: () => import('./views/Configuration.vue'),
        meta: { admin: true },
    },

    // ── 404 ─────────────────────────────────────────────────────────
    { path: '/:pathMatch(.*)*', component: NotFound },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        // Back/forward: restore scroll
        if (savedPosition) return savedPosition;
        // Default: scroll to top on new navigation
        if (to.path !== from.path) return { top: 0 };
        return undefined;
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.booted) await auth.boot();

    if (to.meta.guest && auth.user) {
        return auth.isAdmin ? '/admin/users' : '/dashboard';
    }
    if (!to.meta.guest && !to.meta.public && !auth.user) return '/login';

    // Admin hanya boleh akses /admin/* dan /profile
    if (auth.user && auth.isAdmin && !to.meta.admin && to.path !== '/profile') {
        return '/admin/users';
    }
    // User biasa tidak boleh akses /admin/*
    if (auth.user && !auth.isAdmin && to.meta.admin) {
        return '/dashboard';
    }
});
