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
      <el-descriptions v-for="(item, index) in tableData" :key="index" :title="item.account_name" border :column="2">
        <template slot="extra">
          <el-button size="mini" type="primary" @click="UpdateAdminUserInfo(item)">编辑</el-button>
          <el-button size="mini" type="danger" @click="DelData(item)">删除</el-button>
        </template>
        <el-descriptions-item label="API Key">{{ item.api_key }}</el-descriptions-item>
        <el-descriptions-item label="API Secret">{{ item.api_secret }}</el-descriptions-item>
        <el-descriptions-item label="倍数">{{ item.multiple }}</el-descriptions-item>
        <el-descriptions-item label="开仓比例">{{ item.position_percent }}</el-descriptions-item>
        <el-descriptions-item label="总仓位">{{ item.total_position }}</el-descriptions-item>
        <el-descriptions-item label="止盈止损">{{ item.stop_profit_loss }}</el-descriptions-item>
        <el-descriptions-item label="网格间距">{{ item.grid_step }}</el-descriptions-item>
        <el-descriptions-item label="网格比例配置">
          <div v-for="(item, index) in item.grid_percent_list" :key="index">
            <span>{{ item.direction }}：</span>
            <span>买入比例{{ item.buy }}</span>&nbsp;&nbsp;
            <span>卖出比例{{ item.sell }}</span>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="币种最大仓位数配置	">
          <div v-for="(item, index) in item.max_position_list" :key="index">
            <span>{{ item.symbol }}：</span>
            <span>最大仓位：{{ item.value }}</span>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="价格差">{{ item.commission_price_difference }}</el-descriptions-item>
      </el-descriptions>
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
      :title="DialogTitle + '配置'"
      :visible.sync="dialogVisibleShow"
      width="80%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="150px">
        <el-form-item label="选择账户" prop="account_id">
          <el-select v-model="FormData.account_id" placeholder="请选择账户" clearable :disabled="is_save_add_start == 2">
            <el-option
              v-for="item in accountList"
              :key="item.id"
              :label="item.name || `账户ID: ${item.id}-${item.api_key}`"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="多空倍数" prop="multiple">
          <el-input v-model="FormData.multiple" placeholder="如 3"></el-input>
        </el-form-item>
        <el-form-item label="开仓比例" prop="position_percent">
          <el-input v-model="FormData.position_percent" placeholder="如 0.8"></el-input>
        </el-form-item>
        <el-form-item label="最大仓位配置">
          <div v-for="(item, index) in FormData.max_position_list" :key="index" style="display: flex; align-items: center; margin-bottom: 10px;">
            <el-select v-model="item.symbol" placeholder="选择交易对" style="width: 180px; margin-right: 10px;">
              <el-option
                v-for="symbol in availableSymbols"
                :key="symbol"
                :label="symbol"
                :value="symbol"
                :disabled="isSymbolSelected(symbol) && item.symbol !== symbol">
              </el-option>
            </el-select>
            <el-input-number
              v-model="item.value"
              :min="0"
              :step="100"
              style="width: 140px; margin-right: 10px;">
            </el-input-number>
            <el-button type="danger" icon="el-icon-delete" @click="removeMaxPosition(index)"></el-button>
          </div>
          <el-button type="primary" icon="el-icon-plus" @click="addMaxPosition"  :disabled="FormData.max_position_list.length >= availableSymbols.length">添加</el-button>
          <p v-if="FormData.max_position_list.length >= availableSymbols.length" style="color: #999;">
            已添加所有可配置交易对
          </p>
        </el-form-item>
        <el-form-item label="网格买卖比例配置">
          <div v-for="(item, index) in FormData.grid_percent_list" :key="index" style="display: flex; align-items: center; margin-bottom: 10px;">
            <el-select v-model="item.direction" style="width: 120px; margin-right: 10px;" disabled>
              <el-option label="做多 (long)" value="long"></el-option>
              <el-option label="做空 (short)" value="short"></el-option>
            </el-select>
            <el-form-item label="买入比例" prop="" class="grid-form-label">
              <el-input
                v-model="item.buy"
                placeholder="买入比例"
                style="">
              </el-input>
            </el-form-item>
            <el-form-item label="卖出比例" prop="" class="grid-form-label">
              <el-input
                v-model="item.sell"
                placeholder="卖出比例"
                style="">
              </el-input>
            </el-form-item>
          </div>
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
            account_id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            commission_price_difference: '50',
            max_position: '', // 最终提交还是字符串
            max_position_list: [], // 中间结构用于动态配置
            grid_percent_list: [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ]
        },
        accountList: [], // 账户列表
        availableSymbols: ['BTC-USDT', 'ETH-USDT', 'BNB-USDT'],
        DialogTitle: '添加',
        is_save_add_start: 1, //1：添加 2：修改
        AuthMenuRuleData: [], //权限接口角色数据
        rules: {
            account_id: [{ required: true, message: '请选择账户', trigger: 'change' }],
            symbol: [{ required: true, message: '请输入币种', trigger: 'blur' }],
            multiple: [{ required: true, message: '请输入多空倍数', trigger: 'blur' }],
            position_percent: [{ required: true, message: '请输入开仓比例', trigger: 'blur' }],
            max_position: [{ required: true, message: '请输入最大仓位(JSON)', trigger: 'blur' }],
            total_position: [{ required: true, message: '请输入总仓位', trigger: 'blur' }],
            stop_profit_loss: [{ required: true, message: '请输入止盈止损', trigger: 'blur' }],
            grid_step: [{ required: true, message: '请输入网格间距', trigger: 'blur' }],
            commission_price_difference: [{ required: true, message: '请输入价格差', trigger: 'blur' }]
        },
      };
    },
    methods: {
      addMaxPosition() {
        console.log(this.FormData.max_position_list)
        this.FormData.max_position_list.push({ symbol: '', value: 0 });
      },
      removeMaxPosition(index) {
        this.FormData.max_position_list.splice(index, 1);
      },
      isSymbolSelected(symbol) {
        return this.FormData.max_position_list.some(item => item.symbol === symbol);
      },
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
      getAccountList() {
        get("/Grid/grid/getAccountList", {}, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.accountList = json.data.data;
            } else {
                this.$message.error("加载账户数据失败");
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
              get('/Grid/grid/deleteRobotConfig', {id: row.id}, (json) => {
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
                account_id: row.account_id,
                symbol: row.symbol,
                multiple: row.multiple,
                position_percent: row.position_percent,
                total_position: row.total_position,
                stop_profit_loss: row.stop_profit_loss,
                grid_step: row.grid_step,
                commission_price_difference: row.commission_price_difference,
                max_position_list: row.max_position_list, // 中间结构用于动态配置
                grid_percent_list: row.grid_percent_list
            };
          this.dialogVisibleShow = true;
      },
      onUpdateSubmit(formName) { //修改
        const map = {};
        if(this.FormData.max_position_list.length <= 0) {
          this.$message.error('请添加仓位配置');
          return;
        }
        this.FormData.max_position_list.forEach(item => {
          if (item.symbol) map[item.symbol + '-SWAP'] = item.value;  // 加 -SWAP 是为了兼容原有字段
        });

        const gridObj = {};
        this.FormData.grid_percent_list.forEach(item => {
          gridObj[item.direction] = {
            buy: parseFloat(item.buy),
            sell: parseFloat(item.sell)
          };
        });
        this.$refs[formName].validate((valid) => {
          if (valid) {
            const url = this.is_save_add_start === 1
                ? '/Grid/grid/addRobotConfig'
                : '/Grid/grid/updateRobotConfig';
              post(url, this.FormData, (json) => {
                  console.log(json);
                  if (json && json.data.code == 10000) {
                      this.dialogVisibleShow = false;
                      this.$message({
                          type: 'success',
                          message: this.DialogTitle + '成功! 1分钟后生效'
                      });
                      // this.refreshConfig(json.data.data.data);
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
            account_id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            commission_price_difference: '50',
            max_position_list: [], // 中间结构用于动态配置
            grid_percent_list: [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ]
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
      refreshConfig(account_id) { // 刷新持仓机器人本地缓存
        if(account_id) {
          get('/sigadmin/refresh_config', {
            account_id: account_id
          }, (json) => {
            if (json.status == 200) {
              this.$message({
                  message: '刷新持缓存成功',
                  type: 'success'
              });
            } else {
              this.$message.error('刷新持缓存失败');
            }
          })
        }
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
      this.getAccountList();
      // this.getAuthMenuRuleData();
    },
    components: {
      "wbc-page": Page //加载分页组件
    }
  };
  </script>

<style lang="scss">
  .grid-form-label {
    .el-form-item__label {
      width: 70px !important;
    }
    .el-form-item__content {
      margin-left: 70px!important;
    }
  }
  .el-descriptions {
    margin-bottom: 20px;
    .el-descriptions__header {
      margin-bottom: 10px;
    }
  }
</style>