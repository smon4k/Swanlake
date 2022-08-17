<template>
  <div>
    <el-menu theme="dark" style="border:0;" :router="true" :default-active="$route.path" class="el-menu-demo" mode="horizontal" background-color="#324157" text-color="#fff" active-text-color="#ffd04b">
      <!-- <img src="../../assets/logo-white-full.png" alt="BitGuru" class="logo"> -->
      <span style="line-height:60px;color:#fff;font-weight:700;"><img src="../../assets/adminLog.png" alt="AdminSystem" class="logo">AdminSystem</span>
        <div class="pull-right logout">
          <span>{{UserAuthName}}</span>&nbsp;&nbsp;
          <i class="iconfont" @click="logout()">&#xe66f;</i>
        </div>
    </el-menu>
    <!-- 左侧菜单 -->
    <el-container class="elcontainer">
      <el-aside width="200px" style="background-color:#324157;">
        <el-menu :router="true" :default-active="$route.path" background-color="#324157" text-color="#fff" active-text-color="#ffd04b">
          <template v-for="(item,key) in MenuList">
            <el-menu-item v-if="!item.child" :index="item.path" :key="key">{{item.name}}</el-menu-item>
            <el-submenu v-else :index="item.path == '#' ? item.path + item.id : item.path" :key="key">
              <template slot="title">{{item.name}}</template>
              <div v-for="(childe,keye) in item.child" :key="keye">
                <el-menu-item-group v-if="!childe.child">
                  <el-menu-item :index="childe.path">{{childe.name}}</el-menu-item>
                </el-menu-item-group>
                <el-submenu v-else :index="childe.path == '#' ? childe.path+childe.id : childe.path">
                  <template slot="title">{{childe.name}}</template>
                  <div v-for="(childes,keyes) in childe.child" :key="keyes">
                      <el-menu-item :index="childes.path">{{childes.name}}</el-menu-item>
                  </div>
                </el-submenu>
              </div>
              </el-submenu>
            </template>
        </el-menu>
      </el-aside>

      <el-container>
        <el-main>
          <keep-alive exclude="list">
            <router-view v-if="!$route.meta.notKeepAlive"></router-view>
          </keep-alive>
          <router-view v-if="$route.meta.notKeepAlive"></router-view>
        </el-main>
      </el-container>
      <v-footer type="dark"></v-footer>
    </el-container>
  </div>
</template>
<style scoped>
.el-header {
  background-color: #b3c0d1;
  color: #333;
  line-height: 60px;
}
/* .el-menu {
  border-right:none;
} */
.el-aside {
  color: #333;
}
.el-main {
  padding-top: 50px;
}
.elcontainer {
  /* padding-top: 61px; */
  /* padding-bottom: 0; */
  width: 0;
  width: 100%;
  margin: 0 auto;
  border: 0;
  height:90vh;
}
.el-menu {
  border-right:none;
  /* position: fixed; */
  /* top: 0; */
  width: 100%;
  z-index: 4;
  /* min-width: 1200px; */
}
.logo {
  float: left;
  height: 40px;
  margin: 10px 10px 10px 20px;
  width: auto;
}
.slogan {
  line-height: 60px;
  color: #fff;
  margin-right: 50px;
}
.el-dropdown-link {
  line-height: 60px;
  color: #fff;
  margin-right: 20px;
}
.fade-enter-active,
.fade-leave-active {
  transition: all 0.2s ease;
}

.fade-enter,
.fade-leave-active {
  opacity: 0;
}
.logout {
  line-height: 60px;
  color: #eef1f6;
  padding-right: 20px;
}
.logout i {
  font-size: 20px;
  cursor: pointer;
}
.logout i:hover {
  color: #fff;
}
</style>
<style>
.el-breadcrumb {
  /* margin-left: -20px; */
  /* position: fixed;   */
}
</style>

<script>
import vFooter from "@/components/Footer.vue";
import {get,post} from '@/common/axios.js'
export default {
  data() {
    const item = {
      date: "2016-05-02",
      name: "王小虎",
      address: "上海市普陀区金沙江路 1518 弄"
    };
    return {
      tableData: Array(20).fill(item),
      MenuList: [],
      breads: [],
      is_url_start: 0, //访问域名检测 0：慕华尚侧域名 1：慕华普雷域名
    };
  },
  components: { vFooter },
  methods: {
    getUserMenu() { //获取当前用户菜单功能
      var token = localStorage.getItem("token");
      get('/Admin/Adminuser/getUserMenu', {token: token, status: 1, is_format: 1}, (json) => {
        if(json && json.data.code == 10000) {
          this.MenuList = json.data.data;
          // console.log(this.MenuList);
        } else {
           this.$message({type: 'error', message: "获取菜单数据失败"});
        }
      })
    },
    logout() {
      localStorage.removeItem("token");
      localStorage.removeItem("UserAuthName");
      this.$router.push("/login");
    },
  },
  created() {
    // this.getUserMenu();
    this.breads = [];
    this.$route.matched.map(val => {
      if (val.meta.name) {
        this.breads.push({
          name: val.meta.name,
          link: val.path
        });
      }
    });
    if(window.location.host && window.location.host == "pryadmin.sshangce.com") {
      this.is_url_start = 1;
    }
    this.getUserMenu();
    this.UserAuthName = localStorage.getItem("UserAuthName");
  },
  watch: {
    $route(newVal, oldVal) {
      this.breads = [];
      newVal.matched.map(val => {
        if (val.meta.name) {
          this.breads.push({
            name: val.meta.name,
            link: val.path
          });
        }
      });
    }
  }
};
</script>