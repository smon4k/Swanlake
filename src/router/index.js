import Vue from 'vue'
import Router from 'vue-router'

Vue.use(Router)
import Layout from '@/layout'

export const constantRoutes = [
    {
      path: '/',
      component: Layout,
      redirect: '/home',
      children: [
        {
          path: 'home',
          name: 'home',
          component: () => import('@/views/home/index'),
          meta: { title: 'home', keepAlive: true }
        },
        {
          path: 'my/finances',
          name: 'myFinances',
          component: () => import('@/views/manageFinances/myFinances'),
          meta: { title: 'myFinances', keepAlive: true }
        },
        {
          path: 'financial/product',
          name: 'financialProduct',
          component: () => import('@/views/manageFinances/product'),
          meta: { title: 'financialProduct', keepAlive: true }
        },
        {
          path: 'fund/monitoring',
          name: 'fundMonitoring',
          component: () => import('@/views/fundMonitoring/index'),
          meta: { title: 'fundMonitoring', keepAlive: true }
        },

      ]
    },
]

const createRouter = () => new Router({
  scrollBehavior: () => ({ y: 0 }),
  routes: constantRoutes
})

const router = createRouter()

export default router