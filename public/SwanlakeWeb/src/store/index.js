import Vue from 'vue'
import Vuex from 'vuex'
import base from './base'
import comps from './comps'

Vue.use(Vuex)
export default new Vuex.Store({
    modules: {
      base,
      comps
    }
  })
  