<template>
  <div>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="账号:">
          <el-input clearable placeholder="账号" v-model="user_name"></el-input>
        </el-form-item>
        <el-form-item label="用户名:">
          <el-input clearable placeholder="用户名" v-model="real_name"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
          <el-button class="pull-right" type="primary" @click="AddUserInfoShow()">添加管理员</el-button>
        </el-form-item>
      </el-form>
      <!-- <el-button @click="AddUser()">添加管理员</el-button> -->
    </div>
    <el-table :data="tableData" height="550" style="width: 100%">
      <el-table-column sortable prop="id" label="ID" align="center"></el-table-column>
      <el-table-column prop="user_name" label="账号" align="center"></el-table-column>
      <el-table-column prop="real_name" label="用户名" align="center"></el-table-column>
      <el-table-column prop="ceil_phone" label="手机号" align="center"></el-table-column>
      <el-table-column prop="email" label="邮箱" align="center"></el-table-column>
      <el-table-column prop="group_name" label="角色" align="center"></el-table-column>
      <el-table-column label="操作" width="120" align="center">
        <template slot-scope="scope" v-if="scope.row.id > 1">
          <el-button type="text" @click="UpdateAdminUserInfo(scope.row)">修改</el-button>
          <el-button type="text" @click="DelData(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-row>
      <el-col :span="24">
        <div style="float:right;">
          <wbc-page
            :total="total"
            :pageSize="pageSize"
            :currPage="currPage"
            @changeLimit="limitPaging"
            @changeSkip="skipPaging"
          ></wbc-page>
        </div>
      </el-col>
    </el-row>

    <el-dialog
    :title="DialogTitle + '管理员信息'"
    :visible.sync="dialogVisibleShow"
    width="30%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="80px">
          <el-form-item label="账号" prop="user_name">
              <el-input v-model="FormData.user_name" placeholder="请输入账号"></el-input>
          </el-form-item>
          <el-form-item label="权限角色" prop="groupid">
            <el-select v-model="FormData.groupid" placeholder="请选权限角色" style="width:100%" clearable>
              <el-option v-for="(item,key) in AuthGroupData" :key="key" :label="item.title" :value="item.id"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="用户名" prop="real_name">
              <el-input v-model="FormData.real_name" placeholder="请输入用户名"></el-input>
          </el-form-item>
          <el-form-item label="手机号">
              <el-input v-model="FormData.ceil_phone" placeholder="请输入手机号"></el-input>
          </el-form-item>
          <el-form-item label="邮箱">
              <el-input v-model="FormData.email" placeholder="请输入邮箱"></el-input>
          </el-form-item>
          <el-form-item label="密码" prop="password">
              <el-input show-password v-model="FormData.password" placeholder="请输入密码"></el-input>
          </el-form-item>
          <el-form-item label="确认密码" prop="passwords">
              <el-input show-password v-model="FormData.passwords" placeholder="确认密码"></el-input>
          </el-form-item>
          <el-form-item align="right">
              <el-button @click="resetForm('FormData')">取消</el-button>
              <el-button type="primary" @click="onUpdateSubmit('FormData')">立即{{DialogTitle}}</el-button>
          </el-form-item>
      </el-form>
    </el-dialog>

  </div>
</template>
<script>
import Page from "@/components/Page.vue";
import { get, post } from "@/common/axios.js";
export default {
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 20, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      user_name: "",
      real_name: "",
      tableData: [],
      dialogVisibleShow: false,
      FormData: {
          'id' : '',
          'user_name' : '',
          'real_name' : '',
          'ceil_phone' : '',
          'email' : '',
          'password' : '',
          'passwords' : '',
          'groupid' : '',
      },
      DialogTitle: '添加',
      is_save_add_start: 1, //1：添加 2：修改
      AuthGroupData: [], //权限角色数据
      rules: {
        user_name: [
          { required: true, message: '请输入账号', trigger: 'blur' },
        ],
        groupid: [
            { required: true, message: '请选择权限角色', trigger: 'change' }
        ],
        real_name: [
          { required: true, message: '请输入用户名', trigger: 'blur' }
        ],
        // ceil_phone: [
        //   { required: true, message: '请输入电话号码', trigger: 'blur' }
        // ],
        // email: [
        //   { required: true, message: '请输入邮箱', trigger: 'blur' }
        // ],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' }
        ],
        passwords: [
          { required: true, message: '请输入确认密码', trigger: 'blur' }
        ],
      }
    };
  },
  methods: {
    getListData(ServerWhere) {
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
        };
      }
      get("/Admin/Adminuser/AdminUserList", ServerWhere, json => {
        //   console.log(json);
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.total = json.data.data.count;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    showUserList(row) { //获取项目微信用户列表
        this.$router.replace({
            path: "/index/exam/index/projectwxlist?project_id=" + row.project_id
        });
    },
    SearchClick() {
      //搜索事件
      var SearchWhere = {
        page: this.currPage,
        limit: this.pageSize,
      };
      if (this.user_name && this.user_name !== "") {
        SearchWhere["user_name"] = this.user_name;
      }
      if (this.real_name && this.real_name !== "") {
        SearchWhere["real_name"] = this.real_name;
      }
      this.PageSearchWhere = [];
      this.PageSearchWhere = SearchWhere;
      this.getListData(SearchWhere);
    },
    SearchReset() {
      //搜索条件重置
      this.province = "";
      this.city = "";
      this.area = "";
      this.PageSearchWhere = [];
      this.getData();
    },
    limitPaging(limit) {
      //赋值当前条数
      this.pageSize = limit;
      if (
        this.PageSearchWhere.limit &&
        this.PageSearchWhere.limit !== undefined
      ) {
        this.PageSearchWhere.limit = limit;
      }
      this.getData(this.PageSearchWhere); //刷新列表
    },
    skipPaging(page) {
      //赋值当前页数
      this.currPage = page;
      if (
        this.PageSearchWhere.page &&
        this.PageSearchWhere.page !== undefined
      ) {
        this.PageSearchWhere.page = page;
      }
      this.getData(this.PageSearchWhere); //刷新列表
    },
    DelData(row) { //删除管理员
        this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).then(() => {
            get('/Admin/Adminuser/delAdminUseRow', {id: row.id}, (json) => {
                if (json && json.data.code == 10000) {
                    this.getListData();
                    this.$message({
                        type: 'success',
                        message: '删除成功!'
                    });
                } else {
                    this.$refs.multipleTable.clearSelection();
                    this.$message.error('删除失败');
                }
            })
        }).catch(() => {
            this.$message({
                type: 'info',
                message: '已取消删除'
            });
        });
    },
    UpdateAdminUserInfo(row) { //修改管理员信息 弹框
        this.is_save_add_start = 2;
        this.DialogTitle = "修改";
        this.FormData.id = row.id
        this.FormData.user_name = row.user_name
        this.FormData.real_name = row.real_name
        this.FormData.groupid = row.groupid
        this.FormData.password = ""
        this.FormData.passwords = ""
        this.dialogVisibleShow = true;
    },
    onUpdateSubmit(formName) { //修改
      this.$refs[formName].validate((valid) => {
        if (valid) {
          if(this.FormData.password == "") {
            this.$message({type: 'info', message: '两次密码输入不一致'});
          } else {
            if(this.FormData.password !== this.FormData.passwords) {
                this.$message({type: 'info', message: '两次密码输入不一致'});
            } else {
                if(this.is_save_add_start == 1) {
                  var url = "/Admin/Adminuser/addAdminUserInfo";
                } else {
                  var url = "/Admin/Adminuser/sevaAdminUserInfo";
                }
                get(url, this.FormData, (json) => {
                    if (json && json.data.code == 10000) {
                        this.dialogVisibleShow = false;
                        this.$message({
                            type: 'success',
                            message: this.DialogTitle + '成功!'
                        });
                        this.getListData();
                    } else {
                        this.dialogVisibleShow = false;
                        // this.$refs.multipleTable.clearSelection();
                        this.$message.error(this.DialogTitle + '失败');
                    }
                })
            }
          }
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    AddUserInfoShow() { //添加管理员
      this.is_save_add_start = 1;
      this.DialogTitle = "添加";
      this.FormData = {
        'id' : '',
        'user_name' : '',
        'real_name' : '',
        'ceil_phone' : '',
        'email' : '',
        'password' : '',
        'passwords' : '',
        'groupid' : '',
      };
      this.dialogVisibleShow = true;
    },
    getAuthGroupData() { //获取角色列表
      get('/Admin/Authgroup/getAuthGroupList', {status: 1}, (json) => {
        // console.log(json);
          if (json && json.data.code == 10000) {
            this.AuthGroupData = json.data.data.data;
          } else {
              this.$message.error('获取权限角色数据失败');
          }
      })
    },
    resetForm(formName) {
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    }
  },
  created() {
    this.getListData();
    this.getAuthGroupData();
  },
  components: {
    "wbc-page": Page //加载分页组件
  }
};
</script>

