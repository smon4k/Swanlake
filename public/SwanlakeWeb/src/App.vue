<template>
  <div id="app">
      <router-view  />
  </div>
</template>

<script>
// import { connectInfo,fetchAccountData } from '@/wallet/connect/metaMask'
import { walletConnect } from '@/wallet/connect/walletConnect'
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
      // fetchAccountData()
      walletConnect()
    }, 300)
  },
}
</script>

<style>
</style>
