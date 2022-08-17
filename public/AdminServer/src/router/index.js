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

const PiggybankOrder = () => import('@/pages/Piggybank/list.vue')
const PiggybankDate = () => import('@/pages/Piggybank/date.vue')

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
        // 存钱罐管理
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
