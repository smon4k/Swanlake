<template>
  <div id="app">
    <el-breadcrumb separator="/" v-if="routes.length">
      <el-breadcrumb-item v-for="(item,index) in routes" :key="index" :to="{ path: item.path }">{{item.name}}</el-breadcrumb-item>
    </el-breadcrumb>
    <router-view/>
  </div>
</template>

<script>
export default {
  name: "app",
  data() {
    return {
      notify: null,
      routes: []
    };
  },
  created() {},
  watch: {
    $route(val) {
      const routeMap = this.$router.options.routes[0].children;
      //
      let pathArr = val.path.split("/");
      pathArr.shift();
      pathArr.shift();
      let temp = [];

      switch (pathArr[0]) {
        case "system":
          temp.push({ path: "/index/system", name: "系统总览" });
          break;
        case "project":
          temp.push({ path: "/index/project", name: "项目中心" });
          if (pathArr.length === 1) {
          } else {
            temp.push({
              path: "/index/project/detail/" + pathArr[2],
              name: "项目详情"
            });
            if (pathArr[1] == "detail") {
            } else {
              temp.push({
                path: "",
                name: this.$route.meta.name
              });
            }
          }
          break;
        case "user":
          if (pathArr.length === 1) {
            temp.push({ path: "", name: "用户介绍" });
          } else {
            temp.push({ path: "", name: "用户中心" });
            // temp.push({ path: "/index/user/list", name: "用户列表" });
            if (pathArr[1] !== "list") {
              temp.push({ path: "", name: this.$route.meta.name });
            }
          }
          break;
        case "news":
          temp.push({ path: "", name: "消息管理" });
          // temp.push({ path: "/index/user/list", name: "用户列表" });
          if (pathArr[1] !== "list") {
            temp.push({ path: "", name: this.$route.meta.name });
          }
          break;
        case "order":
          temp.push({ path: "", name: "订单管理" });
          // temp.push({ path: "/index/user/list", name: "用户列表" });
          if (pathArr[1] !== "list") {
            temp.push({ path: "", name: this.$route.meta.name });
          }
          break;
        case "log":
          temp.push({ path: "", name: "日志管理" });
          // temp.push({ path: "/index/user/list", name: "用户列表" });
          if (pathArr[1] !== "list") {
            temp.push({ path: "", name: this.$route.meta.name });
          }
          break;
        case "tasklist":
          temp.push({ path: "", name: "日志管理" });
          // temp.push({ path: "/index/user/list", name: "用户列表" });
          if (pathArr[1] !== "list") {
            temp.push({ path: "", name: this.$route.meta.name });
          }
          break;
        case "school":
          temp.push({ path: "", name: "学校管理" });
          // temp.push({ path: "/index/user/list", name: "用户列表" });
          if (pathArr[1] !== "list") {
            temp.push({ path: "", name: this.$route.meta.name });
          }
          break;
        case "scale":
          temp.push({ path: "/index/scale", name: "量表中心" });
          if (pathArr.length !== 1) {
            let name = "";
            let editType = sessionStorage.getItem("editType");
            if (editType === "new") {
              name = "新建量表";
            } else if (editType === "edit") {
              name = "编辑量表";
            } else {
              name = "查看量表";
            }
            temp.push({
              path: "",
              name: name
            });
          }
          break;
          // case "ad":
          //   temp.push({ path: "", name: "广告管理" });
          //   if (pathArr[1]) {
          //     temp.push({ path: "", name: this.$route.meta.name });
          //   }
          // break;
          case "adminuser":
            temp.push({ path: "", name: "权限管理" });
            if (pathArr[1]) {
              temp.push({ path: "", name: this.$route.meta.name });
            }
          break;
        default:
          break;
      }
      this.routes = temp;
    }
  }
};
</script>

<style>
* {
  margin: 0;
  padding: 0;
  list-style: none;
  box-sizing: border-box;
}
a {
  text-decoration: none;
  color: inherit;
}
html,
body,
#app {
  width: 100%;
  height: 100%;
  min-width: 1200px;
  min-height: 400px;
  position: relative;
}
@font-face {
  font-family: "iconfont"; /* project id 450472 */
  src: url("//at.alicdn.com/t/font_450472_mfcdfjfj1bk5ipb9.eot");
  src: url("//at.alicdn.com/t/font_450472_mfcdfjfj1bk5ipb9.eot?#iefix")
      format("embedded-opentype"),
    url("//at.alicdn.com/t/font_450472_mfcdfjfj1bk5ipb9.woff") format("woff"),
    url("//at.alicdn.com/t/font_450472_mfcdfjfj1bk5ipb9.ttf") format("truetype"),
    url("//at.alicdn.com/t/font_450472_mfcdfjfj1bk5ipb9.svg#iconfont")
      format("svg");
}
.iconfont {
  font-family: "iconfont" !important;
  font-size: 16px;
  font-style: normal;
  -webkit-font-smoothing: antialiased;
  -webkit-text-stroke-width: 0.2px;
  -moz-osx-font-smoothing: grayscale;
}
.clearfix:after {
  content: ".";
  display: block;
  height: 0;
  clear: both;
  visibility: hidden;
}
/* .clearfix {
  *+height: 1%;
} */
.danger {
  color: #ff4949;
}
.line-height-36 {
  line-height: 36px;
}
.clearfix:after {
  content: "";
  display: block;
  clear: both;
}
.el-dialog--small {
  min-width: 800px;
}
.pull-right {
  float: right;
}
.pull-left {
  float: left;
}
.text-center {
  text-align: center;
}
.text-right {
  text-align: right;
}
.padding-15 {
  padding: 15px;
}
.inline-block {
  display: inline-block;
}
body .el-breadcrumb {
  line-height: 40px;
  height: 40px;
  padding-left: 20px;
  background: #eaeefb;
  position: absolute;
  top: 60px;
  width: calc(100% - 200px);
  padding-left: 10px;
  left: 200px;
  z-index: 2;
}
body .el-row {
  margin-top: 10px;
  margin-bottom: 10px;
}
body .el-dropdown {
  cursor: pointer;
}
body .el-radio-group .el-radio + .el-radio {
  margin-left: 60px;
}
body .center-btn {
  margin: 10px auto;
}
.container {
  padding-top: 120px;
  padding-bottom: 70px;
  /* width: 1200px; */
  width: 90%;
  margin: 0 auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
}
.table td {
  border: 1px solid #dfe6ec;
  line-height: 36px;
  padding: 3px 8px;
}
.mt-20 {
  margin-top: 20px;
}
.mb-20 {
  margin-bottom: 20px;
}
.mr-20 {
  margin-right: 20px;
}
.top-banner {
  overflow: hidden;
  margin-bottom: 15px;
}
.school-box {
  border: 1px solid #dfe6ec;
  margin-top: 10px;
}
.school-box h4 {
  height: 30px;
  line-height: 30px;
  background-color: #f2f2f2;
  text-align: center;
}
.school-box .content {
  min-height: 50px;
  padding: 20px;
}
.submit-box {
  margin-top: 20px;
  text-align: center;
}
/* pagination */
body .el-pagination {
  padding: 0;
  padding-top: 15px;
}
.out-banner {
  overflow: hidden;
  margin-bottom: 10px;
  line-height: 36px;
}
.el-picker-panel {
    left: 200px !important;
}
</style>
