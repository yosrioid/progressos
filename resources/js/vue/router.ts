import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import Login from './views/Login.vue';
import Dashboard from './views/Dashboard.vue';
import DocShare from './views/DocShare.vue';
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
import DocDetail from './views/DocDetail.vue';
import DocForm from './views/DocForm.vue';
import Docs from './views/Docs.vue';
import Lists from './views/Lists.vue';
import ListDetail from './views/ListDetail.vue';
import Bills from './views/Bills.vue';
import Money from './views/Money.vue';
import Activity from './views/Activity.vue';
import Goals from './views/Goals.vue';
import Habits from './views/Habits.vue';
import TaskBoard from './views/TaskBoard.vue';
import Game2048 from './views/Game2048.vue';
import MemoryMatch from './views/MemoryMatch.vue';
import Minesweeper from './views/Minesweeper.vue';
import Sudoku from './views/Sudoku.vue';
import Journals from './views/Journals.vue';
import JournalShow from './views/JournalShow.vue';
import Chat from './views/Chat.vue';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login, meta: { guest: true } },
    { path: '/forgot-password', component: ForgotPassword, meta: { guest: true } },
    { path: '/share/doc/:token', component: DocShare, meta: { public: true } },
    { path: '/reset-password/:token', component: ResetPassword, meta: { guest: true } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: Dashboard },
    { path: '/analytics', redirect: '/activity' },
    { path: '/weekly-review', redirect: '/reports/weekly' },
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
    { path: '/chat', component: Chat },
    { path: '/journal', component: Journals },
    { path: '/journal/new', component: JournalShow, props: { id: 'new' } },
    { path: '/journal/:id', component: JournalShow, props: (route) => ({ id: route.params.id }) },
    { path: '/activity', component: Activity },
    { path: '/habits', component: Habits },
    { path: '/goals', component: Goals },
    { path: '/tasks/board', component: TaskBoard },
    { path: '/reports/:period', component: Reports },
    { path: '/docs', component: Docs },
    { path: '/docs/create', component: DocForm },
    { path: '/lists', component: Lists },
    { path: '/lists/:id', component: ListDetail },
    { path: '/bills', component: Bills },
    { path: '/money', component: Money },
    { path: '/docs/:id', component: DocDetail },
    { path: '/docs/:id/edit', component: DocForm },
    { path: '/games', component: Games },
    { path: '/games/sudoku', component: Sudoku },
    { path: '/games/minesweeper', component: Minesweeper },
    { path: '/games/2048', component: Game2048 },
    { path: '/games/memory', component: MemoryMatch },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.booted) await auth.boot();
  if (to.meta.guest && auth.user) return '/dashboard';
  if (!to.meta.guest && !to.meta.public && !auth.user) return '/login';
});
