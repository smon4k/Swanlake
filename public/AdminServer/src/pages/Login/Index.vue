<template>
  <div class="login">
    <div class="top-area">
      <img src="../../assets/adminLog.png" alt="AdminSystem">
      <!-- <span class="bitguru">AdminSystem</span>  -->
      <h2>后台管理系统</h2>
    </div>
    <div class="login-area" @keydown.enter="login()">
      <el-input v-model.trim="username" placeholder="帐号"></el-input>
      <el-input type="password" v-model.trim="password" placeholder="密码"></el-input>
      <el-button type="primary" @click="login()">登录</el-button>
      <p>如遇到登录问题，可邮件xxx@xxx.com</p>
    </div>
    <div class="empty"></div>
    <v-footer type="light"></v-footer>
  </div>
</template>
<style scoped>
.login {
  width: 100%;
  height: 100%;
  min-width: 500px;
  min-height: 400px;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
  background: #324157;
  flex-direction: column;
}
.login-area {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  background: #fff;
  border-radius: 10px;
  padding: 30px;
  min-height: 200px;
}
.top-area {
  display: flex;
  height: 100px;
  overflow: hidden;
  line-height: 100px;
  align-items: center;
  justify-content: space-around;
  width: 280px;
  padding-bottom: 20px;
}
.bitguru {
    color: #fff;
    font-weight: 700;
    margin-right: 40px;
    margin-top: 20px;
    font-size: 20px;
}
img {
  height: 80%;
}
h2 {
  font-size: 24px;
  color: #fff;
  /* line-height: 3; */
}
.el-input,
.el-button {
  width: 400px;
  margin: 10px 0;
}
/* .login-area {
  width: 500px;
  height: 400px;
  background: #fff;
  border-radius: 20px;
} */
.empty {
  height: 200px;
}
</style>

<script>
import vFooter from "@/components/Footer.vue";
export default {
  data() {
    return {
      username: "",
      password: "",
      is_url_start: 0, //访问域名检测 //访问域名检测 0：慕华尚侧域名 1：慕华普雷域名
    };
  },
  methods: {
    login() {
      this.axios
        .post("/Admin/Adminuser/chklogin", {
          user_name: this.username,
          password: this.password
        })
        .then(res => {
          if (res.data.code === 10000) {
            this.$message.success("登录成功");
            localStorage.setItem("token", res.data.data.token);
            localStorage.setItem("ServerIp", res.data.data.server_ip);
            localStorage.setItem("UserAuthName", res.data.data.user_name);
            localStorage.setItem("UserAuthUid", res.data.data.uid);
            this.$router.push("/index/index");
          } else {
            this.$notify.error({
              title: "登录失败",
              message: res.data.msg
            });
          }
        });
    }
  },
  components: { vFooter },
  created() {
    // console.log(window.location.host);
    if(window.location.host && window.location.host == "pryadmin.sshangce.com") {
      this.is_url_start = 1;
    } else if(window.location.host && window.location.host == "absadminpre.sshangce.com") {
      this.is_url_start = 2;
    }
  }
};
</script>

