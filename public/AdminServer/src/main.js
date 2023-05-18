// The Vue build version to load with the `import` command
// (runtime-only or standalone) has been set in webpack.base.conf with an alias.
import Vue from 'vue'
import App from './App'
import router from './router'
import ElementUI from 'element-ui'
import axios from 'axios'
// import Web3 from 'web3'
import qs from 'qs'
import 'element-ui/lib/theme-chalk/index.css';
import '../static/main.css';
import utils from './utils/utils'
// import 'element-ui/lib/theme-chalk/index.css'


//百度富文本框
import '../static/ueditor/ueditor.config.js'
import '../static/ueditor/ueditor.all.js'
import '../static/ueditor/lang/zh-cn/zh-cn.js'

import {
  toFixed,
  toolNumber,
  keepDecimalNotRounding,
  numberFormat
} from '@/utils/tools'


Object.assign(Vue.prototype, {
  toFixed,
  keepDecimalNotRounding,
  toolNumber,
  numberFormat
})

Vue.config.productionTip = false
Vue.use(ElementUI)
Vue.use(ElementUI, { // 初始ele组件配置
  zIndex: 999,
});
Vue.prototype.axios = axios
Vue.prototype.utils = utils
import axiosfn from './common/axios.js'; // 对ajax配置
global.axios = axiosfn(axios, router); // 把axios放到全局
Vue.config.productionTip = false; // false 阻止生成生产提示
/* eslint-disable no-new */
new Vue({
  el: '#app',
  router,
  template: '<App/>',
  components: { App }
})
