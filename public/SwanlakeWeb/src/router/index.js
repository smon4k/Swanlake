import Vue from 'vue'
import Router from 'vue-router'

Vue.use(Router)
import Layout from '@/layout'

export const constantRoutes = [
    {
      path: '/',
      component: Layout,
      redirect: '/hashpower/list',
      children: [
        {
          path: 'home',
          name: 'home',
          component: () => import('@/views/home/index'),
          meta: { title: 'home', keepAlive: true }
        },
        //我的理财
        {
          path: 'my/finances',
          name: 'myFinances',
          component: () => import('@/views/manageFinances/myFinances'),
          meta: { title: 'myFinances', keepAlive: false }
        },
        //理财产品列表
        {
          path: 'financial/product',
          name: 'financialProduct',
          component: () => import('@/views/manageFinances/product'),
          meta: { title: 'financialProduct', keepAlive: false }
        },
        //投注详情
        {
          path: 'financial/currentDetail',
          name: 'currentDetail',
          component: () => import('@/views/manageFinances/currentDetail'),
          meta: { title: 'currentDetail', keepAlive: false }
        },
        //我的产品投注 历史净值
        {
          path: 'financial/userDetailsList',
          name: 'incomeList',
          component: () => import('@/views/manageFinances/productUserDetailsList'),
          meta: { title: 'incomeList', keepAlive: true }
        },
        //产品投注 历史净值
        {
          path: 'financial/productDetailsList',
          name: 'incomeList',
          component: () => import('@/views/manageFinances/productDetailsList'),
          meta: { title: 'incomeList', keepAlive: true }
        },
        {
          path: 'order/record',
          name: 'orderRecord',
          component: () => import('@/views/manageFinances/orderRecord'),
          meta: { title: 'orderRecord', keepAlive: true }
        },
        //资金监控
        {
          path: 'fund/monitoring',
          name: 'fundMonitoring',
          component: () => import('@/views/fundMonitoring/index'),
          meta: { title: 'fundMonitoring', keepAlive: true }
        },
        //账户资金监控
        {
          path: 'fund/monitoring/account',
          name: 'fundMonitoringAccount',
          component: () => import('@/views/fundMonitoring/account'),
          meta: { title: 'fundMonitoringAccount', keepAlive: true }
        },
        //净值配置
        {
          path: 'day/networth',
          name: 'dayNetworth',
          component: () => import('@/views/dayNetworth/index'),
          meta: { title: 'dayNetworth', keepAlive: false }
        },
        //充提
        {
          path: 'depositWithdrawal',
          name: 'depositWithdrawal',
          component: () => import('@/views/depositWithdrawal/index'),
          meta: { title: 'depositWithdrawal', keepAlive: false }
        },
        {
          path: 'deposit/withdraw/btcb',
          name: 'depositWithdrawBtcb',
          component: () => import('@/views/depositWithdrawal/btcb'),
          meta: { title: 'depositWithdrawBtcb', keepAlive: false }
        },
        // BSC 币种统计
        {
          path: 'bscTokenStatistics',
          name: 'bscTokenStatistics',
          component: () => import('@/views/bscTokenStatistics/index'),
          meta: { title: 'bscTokenStatistics', keepAlive: false }
        },
        //算力收益
        {
          path: 'hashpower/list',
          name: 'hashpowerList',
          component: () => import('@/views/hashpower/list'),
          meta: { title: 'hashpowerList', keepAlive: false }
        },
        {
          path: 'hashpower/buy',
          name: 'hashpowerBuy',
          component: () => import('@/views/hashpower/buy'),
          meta: { title: 'hashpowerBuy', keepAlive: false }
        },
        {
          path: 'hashpower/detail',
          name: 'hashpowerDetail',
          component: () => import('@/views/hashpower/detail'),
          meta: { title: 'hashpowerDetail', keepAlive: false }
        },
        {
          path: 'hashpower/abstract',
          name: 'hashpowerAbstract',
          component: () => import('@/views/hashpower/abstract'),
          meta: { title: 'hashpowerAbstract', keepAlive: false }
        },
        {
          path: 'hashpower/history',
          name: 'hashpowerHistory',
          component: () => import('@/views/hashpower/history'),
          meta: { title: 'hashpowerHistory', keepAlive: false }
        },
        // 算力租赁
        {
          path: 'power/list',
          name: 'powerList',
          component: () => import('@/views/power/list'),
          meta: { title: 'powerList', keepAlive: false }
        },
        {
          path: 'power/buy',
          name: 'powerBuy',
          component: () => import('@/views/power/buy'),
          meta: { title: 'powerBuy', keepAlive: false }
        },
        {
          path: 'power/user',
          name: 'powerUser',
          component: () => import('@/views/power/userPower'),
          meta: { title: 'powerUser', keepAlive: false }
        },
        {
          path: 'quantify/account',
          name: 'quantifyAccount',
          component: () => import('@/views/quantifyAccount/index'),
          meta: { title: 'quantifyAccount', keepAlive: false }
        },
        {
          path: 'quantify/temporary',
          name: 'quantifyTemporary',
          component: () => import('@/views/quantifyAccount/temporary'),
          meta: { title: 'quantifyTemporary', keepAlive: false }
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