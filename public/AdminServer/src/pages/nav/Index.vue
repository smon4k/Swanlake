<template> 
<!-- 头部导航 -->
    <div class="home">
      <el-menu theme="dark" style="border:0;" :router="true" :default-active="$route.path" class="el-menu-demo" mode="horizontal" background-color="#545c64" text-color="#fff" active-text-color="#ffd04b">
          <img src="../../assets/logo-white-full.png" alt="慕华尚测" class="logo" v-if="is_url_start == 0">
          <img src="../../assets/logo-white-pulei.png" alt="慕华普雷" class="logo" v-else>
          <template v-for="(item,key) in MenuList">
            <el-menu-item v-if="!item.child" :index="item.path" :key="key">{{item.name}}</el-menu-item>
            <el-submenu v-else :index="item.path == '#' ? item.path + item.id : item.path" :key="key">
              <template slot="title">{{item.name}}</template>
              <div v-for="(childe,keye) in item.child" :key="keye">
                <el-menu-item-group v-if="!childe.child">
                  <el-menu-item :index="childe.path">{{childe.name}}</el-menu-item>
                </el-menu-item-group>
                <el-submenu v-else :index="childe.path == '#' ? childe.path+childes.id : childe.path">
                  <template slot="title">{{childe.name}}</template>
                  <div v-for="(childes,keyes) in childe.child" :key="keyes">
                      <el-menu-item :index="childes.path">{{childes.name}}</el-menu-item>
                  </div>
                </el-submenu>
              </div>
              </el-submenu>
            </template>
            <!-- <p class="pull-right logout">
             <i class="iconfont" @click="logout()">
               &#xe66f;
             </i>
            </p> -->
            <div class="pull-right logout">
              <!-- <el-dropdown>
                <el-dropdown-menu slot="dropdown">
                  <el-dropdown-item>查看</el-dropdown-item>
                  <el-dropdown-item>新增</el-dropdown-item>
                  <el-dropdown-item>删除</el-dropdown-item>
                </el-dropdown-menu>
              </el-dropdown> -->
              <!-- <i class="el-icon-setting" style="margin-right: 15px"></i> -->
              <span>{{UserAuthName}}</span>&nbsp;&nbsp;
              <i class="iconfont" @click="logout()">&#xe66f;</i>
            </div>
        </el-menu>
        <!-- <el-menu theme="dark" :router="true" :default-active="$route.path" class="el-menu-demo" mode="horizontal" background-color="#545c64" text-color="#fff" active-text-color="#ffd04b">
            <img src="../../assets/logo-white-full.png" alt="慕华尚测" class="logo" v-if="is_url_start == 0">
            <img src="../../assets/logo-white-pulei.png" alt="慕华普雷" class="logo" v-else>
            <el-menu-item  index="/index/system">系统总览</el-menu-item>
            <el-menu-item  index="/index/project">项目中心</el-menu-item>
            <el-submenu index="/index/user">
              <template slot="title">
                用户中心
              </template>
              <el-menu-item index="/index/user">
                用户介绍
              </el-menu-item>
              <el-menu-item index="/index/user/auth">
                用户权限
              </el-menu-item>
              <el-menu-item index="/index/user/list">
                用户列表
              </el-menu-item>
            </el-submenu>
            <el-menu-item  index="/index/exam/index">联考管理</el-menu-item>
            <el-submenu index="/index/exam/index">
               <template slot="title">
                联考管理
              </template>
              <el-menu-item index="/index/exam/index/examprojectlist">
                联考列表
              </el-menu-item>
            </el-submenu>
            <el-menu-item  index="/index/scale">量表中心</el-menu-item>
            <el-menu-item  index="/index/log">日志中心</el-menu-item>
            <el-menu-item  index="/index/region">地址中心</el-menu-item>
            <el-menu-item index="/index/data">数据中心</el-menu-item>
            <el-dropdown  class="pull-right" trigger="click">
              <span class="el-dropdown-link">
                操作<i class="el-icon-caret-bottom el-icon--right"></i>
              </span>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item>
                  <router-link to="/userinfo">
                    个人信息
                  </router-link>
                </el-dropdown-item>
                <el-dropdown-item>
                  <router-link to="password">
                    修改密码
                  </router-link>
                </el-dropdown-item>
                <el-dropdown-item>
                  <div @click="logout()">
                    退出登录
                  </div>
                </el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>
            <p class="pull-right logout">
             <i class="iconfont" @click="logout()">
               &#xe66f;
             </i>
            </p>
            <p class="pull-right slogan">"评价"驱动教育变革</p>
        </el-menu> -->
        <keep-alive exclude="list">
          <router-view class="container" v-if="!$route.meta.notKeepAlive"></router-view>
        </keep-alive>
        <router-view class="container" v-if="$route.meta.notKeepAlive"></router-view>
        <v-footer type="dark"></v-footer>
    </div>
</template>
<script>
import vFooter from "@/components/Footer.vue";
import routerMap from "../../router/routerMap";
import {get,post} from '@/common/axios.js'
export default {
  data() {
    return {
      breads: [],
      is_url_start: 0, //访问域名检测 0：慕华尚侧域名 1：慕华普雷域名
      MenuList: [ //菜单配置项
        // {
        //   path: "/index/system",
        //   icon: "",
        //   name: "系统总览"
        // },
        // {
        //   path: "/index/project",
        //   icon: "",
        //   name: "项目中心"
        // },
        // {
        //   icon: "",
        //   name: "用户中心",
        //   path: "#1",
        //   child: [
        //     { path: "/index/user", name: "用户介绍" },
        //     { path: "/index/user/list", name: "用户列表" }
        //   ]
        // },
        // {
        //   icon: "",
        //   name: "权限管理",
        //   path: "#2",
        //   child: [
        //     { path: "/index/users", name: "用户介绍01" },
        //     { path: "/index/user/lists", name: "用户列表01" }
        //   ]
        // },
        // {
        //   path: "/index/exam/index",
        //   icon: "",
        //   name: "联考管理"
        // },
        // {
        //   path: "/index/scale",
        //   icon: "",
        //   name: "量表中心"
        // },
        // {
        //   path: "/index/log",
        //   icon: "",
        //   name: "日志中心",
        // },
        // {
        //   path: "/index/region",
        //   icon: "",
        //   name: "地址中心",
        // },
      ],
      UserAuthName: '', //用户名称
    };
  },
  components: { vFooter },
  methods: {
    logout() {
      localStorage.removeItem("token");
      localStorage.removeItem("UserAuthName");
      this.$router.push("/login");
    },
    getUserMenu() { //获取当前用户菜单功能
      var token = localStorage.getItem("token");
      get('/Admin/Adminuser/getUserMenu', {token: token, status: 1}, (json) => {
        if(json && json.data.code == 10000) {
          this.MenuList = json.data.data;
          // console.log(this.MenuList);
        } else {
           this.$message({type: 'error', message: "获取菜单数据失败"});
        }
      })
    }
  },
  created() {
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
    // this.getUserMenu();
    this.UserAuthName = localStorage.getItem("UserAuthName");
    // console.log(this.menus);
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
<style scoped>
.el-menu {
  position: fixed;
  top: 0;
  width: 100%;
  z-index: 4;
  /* min-width: 1200px; */
}
.logo {
  float: left;
  height: 40px;
  margin: 10px 100px 10px 20px;
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


