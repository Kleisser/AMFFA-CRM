import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import Login from '../views/Login.vue'
import Layout from '../components/Layout.vue'
import Dashboard from '../views/Dashboard.vue'
import Contacts from '../views/Contacts.vue'
import ContactForm from '../views/ContactForm.vue'
import ContactDetail from '../views/ContactDetail.vue'
import Conversations from '../views/Conversations.vue'
import Tasks from '../views/Tasks.vue'
import CalendarVisits from '../views/CalendarVisits.vue'
import PipelineKanban from '../views/PipelineKanban.vue'
import Plans from '../views/Plans.vue'
import Users from '../views/Users.vue'
import Pipelines from '../views/Pipelines.vue'
import Zones from '../views/Zones.vue'
import ExternalChecks from '../views/ExternalChecks.vue'
import AltasBajas from '../views/AltasBajas.vue'

const routes = [
  { path: '/login', name: 'Login', component: Login, meta: { guest: true } },
  {
    path: '/',
    component: Layout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'Dashboard', component: Dashboard },
      { path: 'contacts', name: 'Contacts', component: Contacts },
      { path: 'contacts/new', name: 'ContactCreate', component: ContactForm },
      { path: 'contacts/:id/edit', name: 'ContactEdit', component: ContactForm, props: true },
      { path: 'contacts/:id', name: 'ContactDetail', component: ContactDetail, props: true },
      { path: 'conversations', name: 'Conversations', component: Conversations },
      { path: 'tasks', name: 'Tasks', component: Tasks },
      { path: 'visits', name: 'CalendarVisits', component: CalendarVisits },
      { path: 'pipeline', name: 'PipelineKanban', component: PipelineKanban },
      { path: 'plans', name: 'Plans', component: Plans },
      { path: 'users', name: 'Users', component: Users, meta: { role: ['admin', 'supervisor'] } },
      { path: 'pipelines', name: 'Pipelines', component: Pipelines, meta: { role: ['admin'] } },
      { path: 'zones', name: 'Zones', component: Zones, meta: { role: ['admin'] } },
      { path: 'external-checks', name: 'ExternalChecks', component: ExternalChecks, meta: { role: ['admin', 'supervisor'] } },
      { path: 'altas-bajas', name: 'AltasBajas', component: AltasBajas, meta: { role: ['admin', 'supervisor'] } },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next('/login')
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return next('/')
  }

  if (to.meta.role && !to.meta.role.includes(auth.user?.role)) {
    return next('/')
  }

  next()
})

export default router
