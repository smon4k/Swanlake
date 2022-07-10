<template>
  <section class="app-main">
    <transition name="fade-transform" mode="out-in">
      <!-- <keep-alive :include="cacheViews">
        <router-view :key="key" />
      </keep-alive> -->
      <keep-alive>
          <router-view v-if="$route.meta.keepAlive" :key="key"></router-view>
      </keep-alive>
    </transition>
    <transition name="fade-transform" mode="out-in">
      <router-view v-if="!$route.meta.keepAlive" :key="key"></router-view>
    </transition>
  </section>
</template>
<script>
export default {
    name:'AppMain',
    data(){
      return {
        cacheViews:['Deposit' , 'PledgeMining']
      }
    },
    computed:{
        key(){
            return this.$route.fullPath
        }
    }
}
</script>