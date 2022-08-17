<template>
  <div>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="接口名称:">
          <el-input clearable placeholder="接口名称" v-model="name"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
          <el-button class="pull-right" type="primary" @click="AddUserInfoShow()">添加接口</el-button>
        </el-form-item>
      </el-form>
      <!-- <el-button @click="AddUser()">添加管理员</el-button> -->
    </div>
    <el-table 
    :data="tableData" 
    height="600" 
    style="width: 100%" 
    row-key="id"
    default-expand-all
    :tree-props="TreeProps"
    >
      <el-table-column sortable prop="id" label="ID" align="center"></el-table-column>
      <el-table-column prop="name" label="名称" align="center"></el-table-column>
      <el-table-column prop="path" label="路由" align="center" width="300"></el-table-column>
      <el-table-column prop="css" label="图标" align="center"></el-table-column>
      <el-table-column prop="sort" label="排序" align="center"></el-table-column>
      <el-table-column prop="status" label="状态" align="center">
        <template slot-scope="scope">
            <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0" @change="startKeywordList(scope.row)"></el-switch>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" align="center">
        <template slot-scope="scope">
          <el-button type="text" @click="UpdateAdminUserInfo(scope.row)">修改</el-button>
          <el-button type="text" @click="DelData(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- <el-row>
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
    </el-row> -->

    <el-dialog
    :title="DialogTitle + '接口权限'"
    :visible.sync="dialogVisibleShow"
    width="50%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="80px">
          <el-form-item label="接口名称" prop="name">
              <el-input v-model="FormData.name" placeholder="请输入接口名称"></el-input>
          </el-form-item>
          <el-form-item label="父级接口">
            <el-cascader
            :options="tableData"
            :props="{ checkStrictly: true, value: 'id', label: 'name', children: 'child'}"
            clearable
            v-model="FormData.pid"
            placeholder="默认父级"
            style="width:100%;">
            </el-cascader>
          </el-form-item>
          <el-form-item label="路由" prop="path">
              <el-input v-model="FormData.path" placeholder="请输入路由 例如：/index/project"></el-input>
          </el-form-item>
          <el-form-item label="css">
              <el-input v-model="FormData.css" placeholder="请输入css 例如：el-css-user-solid 参考Element UI"></el-input>
          </el-form-item>
          <el-form-item label="排序" prop="sort">
              <el-input v-model="FormData.sort" placeholder="请输入排序顺序"></el-input>
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
      pageSize: 10, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      name: "",
      tableData: [],
      TreeProps: {children: 'child', hasChildren: 'hasChildren'},
      dialogVisibleShow: false,
      FormData: {
          'id' : '',
          'name' : '',
          'pid' : [],
          'css' : '',
          'sort' : '0',
          'path' : '',
      },
      DialogTitle: '添加',
      is_save_add_start: 1, //1：添加 2：修改
      AuthMenuRuleData: [], //权限接口角色数据
      rules: {
        name: [
          { required: true, message: '请输入名称', trigger: 'blur' },
        ],
        pid: [
            { required: true, message: '请选择父级接口', trigger: 'change' }
        ],
        path: [
          { required: true, message: '请输入路由路径', trigger: 'blur' }
        ],
        sort: [
          { required: true, message: '请输入排序', trigger: 'blur' }
        ],
        css: [
          { required: true, message: '请输入css', trigger: 'blur' }
        ]
      },
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
      get("/Admin/Authrule/getAuthRuleList", ServerWhere, json => {
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.FormData.sort = json.data.data.maxId + 1;
        //   this.total = json.data.data.count;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    SearchClick() {
      //搜索事件
      var SearchWhere = {
        page: this.currPage,
        limit: this.pageSize,
      };
      if (this.name && this.name !== "") {
        SearchWhere["name"] = this.name;
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
      this.getListData();
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
      this.getListData(this.PageSearchWhere); //刷新列表
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
      this.getListData(this.PageSearchWhere); //刷新列表
    },
    DelData(row) { //删除管理员
        this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).then(() => {
            get('/Admin/Authrule/delAuthRuleRow', {id: row.id}, (json) => {
                if (json && json.data.code == 10000) {
                    this.getListData();
                    this.$message({
                        type: 'success',
                        message: '删除成功!'
                    });
                } else {
                    this.$message.error(json.data.msg);
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
        console.log(row);
        this.is_save_add_start = 2;
        this.DialogTitle = "修改";
        this.FormData.id = row.id
        this.FormData.name = row.name
        this.FormData.pid = row.pid_value
        this.FormData.path = row.path
        this.FormData.css = row.css
        this.FormData.sort = row.sort
        this.dialogVisibleShow = true;
    },
    onUpdateSubmit(formName) { //修改
      this.$refs[formName].validate((valid) => {
        if (valid) {
            // console.log(this.FormData.pid);return false;
            if(this.is_save_add_start == 1) {
                var url = "/Admin/Authrule/addAuthRuleData";
            } else {
                var url = "/Admin/Authrule/saveAuthRuleData";
            }
            post(url, this.FormData, (json) => {
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
                    // this.$message.error(this.DialogTitle + '失败');
                    this.$message.error(json.data.msg);
                }
            })
        } else {
        //   console.log('error submit!!');
          return false;
        }
      });
    },
    AddUserInfoShow() { //添加
      this.is_save_add_start = 1;
      this.DialogTitle = "添加";
      this.FormData = {
        'id' : '',
        'name' : '',
        'pid' : [],
        'css' : '',
        'sort' : this.FormData.sort,
      };
      this.dialogVisibleShow = true;
    },
    startKeywordList(row) { //更改状态
        get('/Admin/Authrule/startAuthRule', {id: row.id, start: row.status}, (json) => {
            if(json.data.code == 10000) {
                this.$message({
                    message: '修改成功',
                    type: 'success'
                });
            } else {
                this.$message.error('修改失败');
            }
        })
    },
    // getAuthMenuRuleData() { //获取角色列表
    //   get('/Admin/Authmenurule/getAuthMenuRuleList', {}, (json) => {
    //     // console.log(json);
    //       if (json && json.data.code == 10000) {
    //         this.AuthMenuRuleData = json.data.data.data;
    //       } else {
    //           this.$message.error('获取权限角色数据失败');
    //       }
    //   })
    // },
    resetForm(formName) {
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    }
  },
  created() {
    this.getListData();
    // this.getAuthMenuRuleData();
  },
  components: {
    "wbc-page": Page //加载分页组件
  }
};
</script>