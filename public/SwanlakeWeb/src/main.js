import Vue from 'vue'
import App from './App.vue'
import '@/styles/index.scss'
import router from './router'
import store from './store'
import 'normalize.css/normalize.css'
import ElementUI from 'element-ui';
import 'element-ui/lib/theme-chalk/index.css';
import VueClipboard from 'vue-clipboard2'
import {
  $inputLimit,
  toFixed,
  toWei,
  fromWei,
  fromSATBTCNum,
  toolNumber,
  keepDecimalNotRounding
} from './utils/tools'
import { connectWallet, disconnectWallet } from './wallet/connect/metaMask'
import Web3 from 'web3'
import { $post , $get } from '@/utils/request'  
import i18next from 'i18next';
import VueI18Next from '@panter/vue-i18next';
import XHR from 'i18next-xhr-backend';
import LngDetector from 'i18next-browser-languagedetector';
import visibility from 'vue-visibility-change';

import axios from 'axios'
Vue.prototype.axios = axios
import axiosfn from './common/axios.js'; // 对ajax配置
global.axios = axiosfn(axios, router); // 把axios放到全局

if(window.ethereum){
  window.web3 = new Web3(ethereum);
} 
else if(typeof web3 !== 'undefined') {
    window.web3 = new Web3(web3.currentProvider);
    web3.eth.defaultAccount = web3.eth.accounts[0];
} 
else {
    // set the provider you want from Web3.providers
    window.web3 = new Web3(new Web3.providers.HttpProvider("https://bsc-dataseed.binance.org"));
}

Vue.use(ElementUI);
Vue.use(VueClipboard)

Vue.use(VueI18Next);

Vue.use(visibility);
Vue.config.productionTip = false

// 语言包配置
i18next.use(XHR).use(LngDetector).init({
  // lng: 'en', // 设定语言
  fallbackLng: 'zh', // 默认语言包
  ns: ['public', 'nav', 'subscribe'],
  defaultNS: 'public',
  backend: {
      loadPath: '../static/locales/{{lng}}/{{ns}}.json'
  },
  detection: {
      // order and from where user language should be detected
      order: ['localStorage'],
      // cache user language on
      caches: ['localStorage']
  },
});

const i18n = new VueI18Next(i18next);

Object.assign(Vue.prototype, {
  $inputLimit,
  $connect:connectWallet,
  $disconnect:disconnectWallet,
  toFixed,
  toWei,
  fromWei,
  fromSATBTCNum,
  keepDecimalNotRounding,
  $get,
  $post,
  toolNumber
})

window.__ownInstance__ = new Vue({
  store,
  router,
  i18n: i18n,
  render: h => h(App),
}).$mount('#app')
