<template>
  <div id="app">
      <router-view  />
  </div>
</template>

<script>
import { connectInfo,fetchAccountData } from '@/wallet/connect/metaMask'
export default {
  name: 'App',
  // data(){
  //   return {
  //   }
  // },
  // computed:{
  //   key() {
  //     return this.$route.fullPath
  //   }
  // },
  mounted(){
    setTimeout(()=>{
      fetchAccountData()
      // connectInfo()
    }, 300)
  },
}
</script>

<style>
</style>
