import Vue from 'vue'
import Router from 'vue-router'
// const Index = () => import('@/pages/Index/Index.vue')
const Home = () => import('@/pages/Home/Index.vue')
const Login = () => import('@/pages/Login/Index.vue')

const AdminUserList = () => import('@/pages/AdminUser/AdminUserList.vue')
const AuthGroupList = () => import('@/pages/AdminUser/AuthGroupList.vue')
const AuthMenuRuleList = () => import('@/pages/AdminUser/AuthMenuRuleList.vue')
const AuthRuleList = () => import('@/pages/AdminUser/AuthRuleList.vue')

const UserList = () => import('@/pages/User/list.vue')

const FillingH2oList = () => import('@/pages/Filling/h2olist.vue')
const FillingUsdtList = () => import('@/pages/Filling/usdtlist.vue')

// BTC存钱罐
const PiggybankList = () => import('@/pages/Piggybank/currency_list.vue')
const PiggybankDetail = () => import('@/pages/Piggybank/detail.vue')
const PiggybankOrder = () => import('@/pages/Piggybank/list.vue')
const PiggybankDate = () => import('@/pages/Piggybank/date.vue')
const GoldIndex = () => import('@/pages/Gold/index.vue')
const PiggybankConfig = () => import('@/pages/Piggybank/config.vue')

// BIFI存钱罐
const PiggybankBifiDetail = () => import('@/pages/PiggybankBifi/detail.vue')
const PiggybankBifiOrder = () => import('@/pages/PiggybankBifi/list.vue')
const PiggybankBifiDate = () => import('@/pages/PiggybankBifi/date.vue')
const GoldBifis = () => import('@/pages/Gold/bifis.vue')


const LlpMonitor = () => import('@/pages/LlpMonitor/list.vue')

const GridOrderList = () => import('@/pages/Grid/list.vue')
const GridOrderConfig = () => import('@/pages/Grid/config.vue')
const GridPositionsList = () => import('@/pages/Grid/positions.vue')
const GridAccountsList = () => import('@/pages/Grid/accounts.vue')
const AccountStatuement = () => import('@/pages/Grid/AccountStatuement.vue')
const SignalList = () => import('@/pages/Grid/SignalList.vue')
const StrategyList = () => import('@/pages/Grid/StrategyList.vue')

Vue.use(Router)

const router = new Router({
  routes: [
    {
      path: '/home',
      name: 'home',
      component: Home,
      meta: {
        name: '首页'
      },
      children: [
        {
          path: 'adminuser/adminuserlist',
          name: 'AdminUserList',
          component: AdminUserList,
          meta: {
            name: '管理员列表',
            notKeepAlive: true
          }
        },
        {
          path: 'adminuser/authgrouplist',
          name: 'AuthGroupList',
          component: AuthGroupList,
          meta: {
            name: '角色权限列表',
            notKeepAlive: true
          }
        },
        {
          path: 'adminuser/authmenurulelist',
          name: 'AuthMenuRuleList',
          component: AuthMenuRuleList,
          meta: {
            name: '菜单权限列表',
            notKeepAlive: true
          }
        },
        {
          path: 'adminuser/authrulelist',
          name: 'AuthRuleList',
          component: AuthRuleList,
          meta: {
            name: '接口权限列表',
            notKeepAlive: true
          }
        },
        {
          path: 'user/list',
          name: 'UserList',
          component: UserList,
          meta: {
            name: '用户列表',
            notKeepAlive: true
          }
        },
        {
          path: 'filling/h2olist',
          name: 'FillingH2oList',
          component: FillingH2oList,
          meta: {
            name: 'H2O充提记录',
            notKeepAlive: true
          }
        },
        {
          path: 'filling/usdtlist',
          name: 'FillingUsdtList',
          component: FillingUsdtList,
          meta: {
            name: 'USDT充提记录',
            notKeepAlive: true
          }
        },
        // BTC-存钱罐管理
        {
          path: 'piggybank/detail',
          name: 'PiggybankDetail',
          component: PiggybankDetail,
          meta: {
            name: '项目详情',
            notKeepAlive: true
          }
        },
        {
          path: 'piggybank/order',
          name: 'PiggybankOrder',
          component: PiggybankOrder,
          meta: {
            name: '订单列表',
            notKeepAlive: true
          }
        },
        {
          path: 'piggybank/date',
          name: 'PiggybankDate',
          component: PiggybankDate,
          meta: {
            name: '产品统计',
            notKeepAlive: true
          }
        },
        {
          path: 'gold/index',
          name: 'GoldIndex',
          component: GoldIndex,
          meta: {
            name: '出入金',
            notKeepAlive: true
          }
        },
         // BIFI-存钱罐管理
        {
          path: 'piggybank/currency',
          name: 'PiggyList',
          component: PiggybankList,
          meta: {
            name: '币种列表',
            notKeepAlive: true
          }
        },
        {
          path: 'piggybank/bifi/detail',
          name: 'PiggybankBifiDetail',
          component: PiggybankBifiDetail,
          meta: {
            name: '项目详情',
            notKeepAlive: true
          }
        },
         {
          path: 'piggybank/bifi/order',
          name: 'PiggybankBifiOrder',
          component: PiggybankBifiOrder,
          meta: {
            name: '订单列表',
            notKeepAlive: true
          }
        },
        {
          path: 'piggybank/bifi/date',
          name: 'PiggybankBifiDate',
          component: PiggybankBifiDate,
          meta: {
            name: '产品统计',
            notKeepAlive: true
          }
        },
        {
          path: 'piggybank/config',
          name: 'PiggybankConfig',
          component: PiggybankConfig,
          meta: {
            name: '配置管理',
            notKeepAlive: true
          }
        },

        {
          path: 'gold/bifis',
          name: 'GoldBifis',
          component: GoldBifis,
          meta: {
            name: '出入金',
            notKeepAlive: true
          }
        },
        {
          path: 'LlpMonitor',
          name: 'LlpMonitor',
          component: LlpMonitor,
          meta: {
            name: 'LLP监控',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/list',
          name: 'GridOrderList',
          component: GridOrderList,
          meta: {
            name: '订单列表',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/config',
          name: 'GridOrderConfig',
          component: GridOrderConfig,
          meta: {
            name: '机器人配置',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/positions',
          name: 'GridPositionsList',
          component: GridPositionsList,
          meta: {
            name: '持仓列表',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/accounts',
          name: 'GridAccountsList',
          component: GridAccountsList,
          meta: {
            name: '账户列表',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/account/statistical',
          name: 'AccountStatuement',
          component: AccountStatuement,
          meta: {
            name: '账户报表',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/account/signallist',
          name: 'SignalList',
          component: SignalList,
          meta: {
            name: '信号列表',
            notKeepAlive: true
          }
        },
        {
          path: 'grid/account/strategylist',
          name: 'StrategyList',
          component: StrategyList,
          meta: {
            name: '策略列表',
            notKeepAlive: true
          }
        },
      ]
    },
    {
      path: '/login',
      name: 'Login',
      component: Login
    },
    {
      path: '*',
      redirect: '/home'
    }
  ]
})
router.beforeEach((to, from, next) => {
  if (to.name !== 'Login') {
    if (localStorage.getItem('token')) {
      next()
    } else {
      next('/login')
    }
  } else {
    localStorage.clear('token')
    next()
  }
})
export default router
