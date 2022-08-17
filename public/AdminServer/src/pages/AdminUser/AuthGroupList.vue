<template>
  <div>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="角色名称:">
          <el-input clearable placeholder="角色名称" v-model="title"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
          <el-button class="pull-right" type="primary" @click="AddAuthGrupShow()">添加角色</el-button>
        </el-form-item>
      </el-form>
      <!-- <el-button @click="AddUser()">添加管理员</el-button> -->
    </div>
    <el-table :data="tableData" height="550" style="width: 100%">
      <el-table-column sortable prop="id" label="ID" align="center"></el-table-column>
      <el-table-column prop="title" label="角色名称" align="center"></el-table-column>
      <el-table-column label="接口权限" align="center">
          <template slot-scope="scope" v-if="scope.row.rules_array.length">
            <el-tooltip placement="top">
                <div slot="content" v-for="(item,key) in scope.row.rules_array" :key="key">
                    <span>{{key + 1}}. {{item.name}}</span><br/>
                    <div v-for="(items,keys) in item.child" :key="keys">
                      <span>&nbsp;&nbsp;{{keys + 1}}. {{items.name}}</span>
                        <div v-for="(itemss,keyss) in items.child" :key="keyss">
                          <span>&nbsp;&nbsp;&nbsp;&nbsp;{{keyss + 1}}. {{itemss.name}}</span>
                        </div>
                    </div>
                </div>
                <el-button>禁止的接口权限</el-button>
            </el-tooltip>
          </template>
      </el-table-column>
      <el-table-column label="菜单权限" align="center">
          <template slot-scope="scope" v-if="scope.row.menu_rules_array.length">
            <el-tooltip placement="top">
                <div slot="content" v-for="(item,key) in scope.row.menu_rules_array" :key="key">
                    <span>{{key + 1}}. {{item.name}}</span><br/>
                    <div v-for="(items,keys) in item.child" :key="keys">
                      <span>&nbsp;&nbsp;{{keys + 1}}. {{items.name}}</span>
                        <div v-for="(itemss,keyss) in items.child" :key="keyss">
                          <span>&nbsp;&nbsp;&nbsp;&nbsp;{{keyss + 1}}. {{itemss.name}}</span>
                        </div>
                    </div>
                </div>
                <el-button>拥有的菜单权限</el-button>
            </el-tooltip>
          </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" align="center">
        <template slot-scope="scope" v-if="scope.row.id > 1">
            <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0" @change="startKeywordList(scope.row)"></el-switch>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" align="center">
        <template slot-scope="scope" v-if="scope.row.id > 1">
          <el-button type="text" @click="UpdateAuthGroupInfo(scope.row)">修改</el-button>
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
    :title="DialogTitle + '角色'"
    :visible.sync="dialogVisibleShow"
    width="50%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="80px">
          <el-form-item label="角色名称" prop="title">
              <el-input v-model="FormData.title" placeholder="角色名称"></el-input>
          </el-form-item>
          <el-form-item label="菜单权限" prop="menu_rules">
            <el-cascader
            :options="AuthMenuRuleData"
            :props="AuthRuleProps"
            v-model="FormData.menu_rules"
            clearable  style="width:100%">
            </el-cascader>
          </el-form-item>
          <el-form-item label="接口权限">
            <el-cascader
            :options="AuthRuleData"
            :props="AuthRulePropss"
            v-model="FormData.rules"
            clearable  style="width:100%"
            placeholder="禁止访问的接口权限">
            </el-cascader>
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
      title: "",
      tableData: [],
      dialogVisibleShow: false,
      FormData: {
          'id' : '',
          'title' : '',
          'rules' : [],
          'menu_rules' : [],
      },
      DialogTitle: '添加',
      is_save_add_start: 1, //1：添加 2：修改
      AuthGroupData: [], //权限角色数据
      AuthRuleData: [], //接口权限数据
      AuthMenuRuleData: [], //菜单权限数据
      rules: {
        title: [
          { required: true, message: '请输入角色名称', trigger: 'blur' },
        ],
        rules: [
            { required: true, message: '请选择接口权限', trigger: 'change' }
        ],
        menu_rules: [
            { required: true, message: '请选择菜单权限', trigger: 'change' }
        ],
      },
      AuthRuleProps: { //级联选择器 权限配置选项
          multiple: true,
          value: 'id',
          label: 'name',
          children: 'child',
      },
      AuthRulePropss: { //级联选择器 权限配置选项
          multiple: true,
          value: 'id',
          label: 'name',
          children: 'child',
      },
      AuthRuleSelectValue: [], //接口权限默认选中值
      AuthMenuRuleSelectValue: [], //接口菜单权限默认选中值
    };
  },
  methods: {
    getListData(ServerWhere) {
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
          export: 1,
        };
      }
      get("/Admin/Authgroup/getAuthGroupList", ServerWhere, json => {
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.total = json.data.data.count;
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
        export: 1,
      };
      if (this.title && this.title !== "") {
        SearchWhere["title"] = this.title;
      }
      this.PageSearchWhere = [];
      this.PageSearchWhere = SearchWhere;
      this.getListData(SearchWhere);
    },
    SearchReset() {
      //搜索条件重置
      this.title = "";
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
    DelData(row) { //删除角色信息
        this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).then(() => {
            get('/Admin/Authgroup/delAuthGroupRow', {id: row.id}, (json) => {
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
    UpdateAuthGroupInfo(row) { //修改角色信息 弹框
        console.log(row);
        this.is_save_add_start = 2;
        this.DialogTitle = "修改";
        this.FormData.id = row.id
        this.FormData.title = row.title
        // this.FormData.rules = row.rules.split(',');
        this.FormData.rules = row.rules_arr_select;
        // this.FormData.menu_rules = row.menu_rules.split(',');
        this.FormData.menu_rules = row.menu_rules_array_select;
        // this.FormData.menu_rules = [
        //   [11],
        //   [16,17],
        //   [16,18],
        // ];
        this.dialogVisibleShow = true;
    },
    onUpdateSubmit(formName) { //添加或者修改操作 提交数据
      this.$refs[formName].validate((valid) => {
        if (valid) {
            if(this.is_save_add_start == 1) {
                var url = "/Admin/Authgroup/addAuthGroupInfo";
            } else {
                var url = "/Admin/Authgroup/sevaAuthGroupInfo";
            }
            // console.log(this.FormData);return false;
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
                    this.$message.error(this.DialogTitle + '失败');
                }
            })
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    AddAuthGrupShow() { //添加角色
      this.is_save_add_start = 1;
      this.DialogTitle = "添加";
      this.FormData = {
        'id' : '',
        'title' : '',
      };
      this.AuthRuleSelectValue = [], //接口权限默认选中值
      this.AuthMenuRuleSelectValue = [], //接口菜单权限默认选中值
      this.dialogVisibleShow = true;
    },
    getAuthGroupData() { //获取角色列表
      get('/Admin/Authgroup/getAuthGroupList', {}, (json) => {
        // console.log(json);
          if (json && json.data.code == 10000) {
            this.AuthGroupData = json.data.data;
          } else {
              this.$message.error('获取权限角色数据失败');
          }
      })
    },
    getAuthRuleData() { //获取接口权限列表
      get('/Admin/Authrule/getAuthRuleList', {status: 1}, (json) => {
        // console.log(json);
          if (json && json.data.code == 10000) {
            this.AuthRuleData = json.data.data.data;
          } else {
              this.$message.error('获取接口权限数据失败');
          }
      })
    },
    getAuthMenuRuleData() { //获取菜单权限列表
      get('/Admin/Authmenurule/getAuthMenuRuleList', {status: 1}, (json) => {
        // console.log(json);
          if (json && json.data.code == 10000) {
            this.AuthMenuRuleData = json.data.data.data;
          } else {
              this.$message.error('获取接口权限数据失败');
          }
      })
    },
    startKeywordList(row) { //更改状态
        get('/Admin/Authgroup/startAuthGroupRule', {id: row.id, start: row.status}, (json) => {
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
    resetForm(formName) {
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    }
  },
  created() {
    this.getListData();
    this.getAuthGroupData();
    this.getAuthRuleData();
    this.getAuthMenuRuleData();
  },
  components: {
    "wbc-page": Page //加载分页组件
  }
};
</script>

