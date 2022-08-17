<template>
    <div class="footer" :class="type">
      <div>
        <p>Copyright&copy;2021-2022&nbsp;&nbsp;&nbsp;AdminSystem All rights reserved</p>
        <p>京ICP备123456789号</p>
      </div>
    </div>
</template>
<style scoped>
.footer {
  position: fixed;
  bottom: 0;
  text-align: center;
  padding: 10px 0;
  width: 100%;
  line-height: 1.6;
  z-index: 4;
  color: #fff;
}
.dark {
  background: #324157;
}
</style>

<script>
export default {
   data() {
    return {
      is_url_start: 0, //访问域名检测 0：慕华尚侧域名 1：慕华普雷域名
    };
  },
  props: {
    type: String
  },
  created() {
    if(window.location.host && window.location.host == "pryadmin.sshangce.com") {
      this.is_url_start = 1;
    }
  }
};
</script>

