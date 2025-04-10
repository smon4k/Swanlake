<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
            <el-breadcrumb-item to="">机器人配置</el-breadcrumb-item>
        </el-breadcrumb>
      <div class="project-top">
        <el-form :inline="true" class="demo-form-inline">
          <!-- <el-form-item label="接口名称:">
            <el-input clearable placeholder="接口名称" v-model="name"></el-input>
          </el-form-item> -->
          <el-form-item>
            <!-- <el-button type="primary" @click="SearchClick()">搜索</el-button> -->
            <el-button class="pull-right" type="primary" @click="AddUserInfoShow()">添加配置</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-table :data="tableData" stripe style="width: 100%; margin-top: 20px;">
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="symbol" label="币种" />
        <el-table-column prop="multiple" label="倍数" />
        <el-table-column prop="position_percent" label="开仓比例" />
        <el-table-column prop="max_position" label="最大仓位" />
        <el-table-column prop="total_position" label="总仓位" />
        <el-table-column prop="stop_profit_loss" label="止盈止损" />
        <el-table-column prop="grid_step" label="网格间距" />
        <el-table-column prop="grid_sell_percent" label="卖出比例" />
        <el-table-column prop="grid_buy_percent" label="买入比例" />
        <el-table-column prop="commission_price_difference" label="价格差" />
  
        <el-table-column label="操作" width="180">
          <template #default="scope">
            <el-button size="mini" type="primary" @click="UpdateAdminUserInfo(scope.row)">编辑</el-button>
            <el-button size="mini" type="danger" @click="DelData(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-row v-if="tableData && tableData.length > 0">
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
      :title="DialogTitle + '接口权限'"
      :visible.sync="dialogVisibleShow"
      width="50%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="120px">
        <el-form-item label="多空倍数" prop="multiple">
          <el-input v-model="FormData.multiple" placeholder="如 3"></el-input>
        </el-form-item>
        <el-form-item label="开仓比例" prop="position_percent">
          <el-input v-model="FormData.position_percent" placeholder="如 0.8"></el-input>
        </el-form-item>
        <el-form-item label="最大仓位 (JSON)" prop="max_position">
          <el-input type="textarea" :rows="3" v-model="FormData.max_position" placeholder='如 {"BTC-USDT-SWAP": 2000}'></el-input>
        </el-form-item>
        <el-form-item label="总仓位" prop="total_position">
          <el-input v-model="FormData.total_position" placeholder="如 5000"></el-input>
        </el-form-item>
        <el-form-item label="止盈止损比" prop="stop_profit_loss">
          <el-input v-model="FormData.stop_profit_loss" placeholder="如 0.007"></el-input>
        </el-form-item>
        <el-form-item label="网格间距" prop="grid_step">
          <el-input v-model="FormData.grid_step" placeholder="如 0.002"></el-input>
        </el-form-item>
        <el-form-item label="卖出比例" prop="grid_sell_percent">
          <el-input v-model="FormData.grid_sell_percent" placeholder="如 0.05"></el-input>
        </el-form-item>
        <el-form-item label="买入比例" prop="grid_buy_percent">
          <el-input v-model="FormData.grid_buy_percent" placeholder="如 0.04"></el-input>
        </el-form-item>
        <el-form-item label="价格差(USDT)" prop="commission_price_difference">
          <el-input v-model="FormData.commission_price_difference" placeholder="如 50"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button @click="resetForm('FormData')">取消</el-button>
          <el-button type="primary" @click="onUpdateSubmit('FormData')">立即{{ DialogTitle }}</el-button>
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
            id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            max_position: '{"BTC-USDT-SWAP": 2000, "ETH-USDT-SWAP": 1000}', // 默认值
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            grid_sell_percent: '0.05',
            grid_buy_percent: '0.04',
            commission_price_difference: '50',
        },
        DialogTitle: '添加',
        is_save_add_start: 1, //1：添加 2：修改
        AuthMenuRuleData: [], //权限接口角色数据
        rules: {
            symbol: [{ required: true, message: '请输入币种', trigger: 'blur' }],
            multiple: [{ required: true, message: '请输入多空倍数', trigger: 'blur' }],
            position_percent: [{ required: true, message: '请输入开仓比例', trigger: 'blur' }],
            max_position: [{ required: true, message: '请输入最大仓位(JSON)', trigger: 'blur' }],
            total_position: [{ required: true, message: '请输入总仓位', trigger: 'blur' }],
            stop_profit_loss: [{ required: true, message: '请输入止盈止损', trigger: 'blur' }],
            grid_step: [{ required: true, message: '请输入网格间距', trigger: 'blur' }],
            grid_sell_percent: [{ required: true, message: '请输入卖出比例', trigger: 'blur' }],
            grid_buy_percent: [{ required: true, message: '请输入买入比例', trigger: 'blur' }],
            commission_price_difference: [{ required: true, message: '请输入价格差', trigger: 'blur' }]
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
        get("/Grid/grid/getRobotConfig", ServerWhere, json => {
            console.log(json);
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
          this.FormData = {
                id: row.id,
                symbol: row.symbol,
                multiple: row.multiple,
                position_percent: row.position_percent,
                max_position: row.max_position,
                total_position: row.total_position,
                stop_profit_loss: row.stop_profit_loss,
                grid_step: row.grid_step,
                grid_sell_percent: row.grid_sell_percent,
                grid_buy_percent: row.grid_buy_percent,
                commission_price_difference: row.commission_price_difference
            };
          this.dialogVisibleShow = true;
      },
      onUpdateSubmit(formName) { //修改
        this.$refs[formName].validate((valid) => {
          if (valid) {
            const url = this.is_save_add_start === 1
                ? '/Grid/grid/addRobotConfig'
                : '/Grid/grid/updateRobotConfig';
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
            id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            max_position: '{"BTC-USDT-SWAP": 2000, "ETH-USDT-SWAP": 1000}',
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            grid_sell_percent: '0.05',
            grid_buy_percent: '0.04',
            commission_price_difference: '50',
        };
        console.log(111);
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