<template>
    <div>
      <el-breadcrumb separator="/">
          <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
          <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
          <el-breadcrumb-item to="">数据统计</el-breadcrumb-item>
      </el-breadcrumb>
      <div class="project-top">
        <el-select v-model="account_id" placeholder="选择要搜索的账户" @change="accountChange">
          <el-option
            v-for="(item, index) in accountList"
            :key="index"
            :label="item.name"
            :value="item.id">
          </el-option>
        </el-select>
        <!-- <el-form :inline="true" class="demo-form-inline" size="mini">
          <el-form-item label="产品名称:">
            <el-input clearable placeholder="产品名称" v-model="product_name"></el-input>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="SearchClick()">搜索</el-button>
            <el-button class="pull-right" type="primary" @click="DepositWithdrawalShow()">出/入金</el-button>
          </el-form-item>
        </el-form> -->
      </div>
      <el-tabs type="card" @tab-click="tabClick">
          <el-tab-pane label="当前持仓">
            <el-table :data="currentPositionsList" style="width: 100%;" v-loading="currentLoading">
              <!-- 交易品种 -->
              <el-table-column prop="symbol" label="交易品种" align="center"></el-table-column>
              
              <!-- 持仓方向 -->
              <el-table-column prop="side" label="持仓方向" align="center">
                <template slot-scope="scope">
                  <span v-if="scope.row.side === 'long'">开多</span>
                  <span v-else-if="scope.row.side === 'short'">开空</span>
                </template>
              </el-table-column>

              <!-- 持仓量 -->
              <el-table-column label="持仓量" align="center">
                <template slot-scope="scope">
                  <span>{{ scope.row.contracts }} 张</span>
                  <br>
                  <span>{{ keepDecimalNotRounding(scope.row.contracts * scope.row.contractSize * scope.row.markPrice, 2, true) }} USDT</span>
                </template>
              </el-table-column>
            
              <!-- 标记价格 -->
              <el-table-column label="标记价格" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.markPrice, 2, true) }}</span>
                </template>
              </el-table-column>
            
              <!-- 开仓均价 -->
              <el-table-column label="开仓均价" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.entryPrice, 2, true) }}</span>
                </template>
              </el-table-column>
            
              <!-- 浮动收益 -->
              <el-table-column label="浮动收益" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.unrealizedPnl, 2, true) }}</span>
                </template>
              </el-table-column>
            
              <!-- 维持保证金率 -->
              <el-table-column label="维持保证金率" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.maintenanceMarginPercentage * 100, 2, true) }}%</span>
                </template>
              </el-table-column>
            
              <!-- 保证金 -->
              <el-table-column label="保证金" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.collateral, 2, true) }} USDT</span>
                </template>
              </el-table-column>
            
              <!-- 盈亏平衡价 -->
              <el-table-column label="盈亏平衡价" align="center">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.entryPrice + (scope.row.unrealizedPnl / scope.row.contracts), 2, true) }} USDT</span>
                </template>
              </el-table-column>
            </el-table>
          </el-tab-pane>
          <el-tab-pane label="历史持仓">
            <el-table :data="positionsHistoryList" style="width: 100%;" v-loading="historyLoading">
              <!-- 交易品种 -->
              <el-table-column prop="symbol" label="交易品种" align="left">
                <template slot-scope="scope">
                  <span>{{ scope.row.symbol }}</span>
                  <br>
                  <span v-if="scope.row.info.mgnMode === 'cross'">全仓</span>
                  <span v-if="scope.row.info.mgnMode === 'isolated'">逐仓</span>
                </template>
              </el-table-column>
            
              <!-- 委托时间 -->
              <el-table-column prop="datetime" label="委托时间" align="left">
                <template slot-scope="scope">
                  <span>{{ formatDate(scope.row.datetime) }}</span>
                </template>
              </el-table-column>
            
              <!-- 交易方向 -->
              <el-table-column prop="side" label="交易方向" align="left">
                <template slot-scope="scope">
                  <div v-if="scope.row.info">
                    <span v-if="scope.row.info.direction ===  'long' && scope.row.info.posSide === 'long'" style="color:#05C48E">买入开多</span>
                    <span v-else-if="scope.row.info.direction === 'long' && scope.row.info.posSide === 'short'" style="color:#05C48E">买入平空</span>
                    <span v-else-if="scope.row.info.direction === 'short' && scope.row.info.posSide === 'long'" style="color:#df473d;">卖出平多</span>
                    <span v-else-if="scope.row.info.direction === 'short' && scope.row.info.posSide ==='short'" style="color:#df473d;">卖出开空</span>
                    <span v-else style="color:#df473d;">卖出</span>
                  </div>
                </template>
              </el-table-column>
            
              <!-- 成交均价 | 委托价 -->
              <el-table-column label="成交均价 | 委托价" align="left">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.entryPrice, 2, true) }} USDT</span>
                </template>
              </el-table-column>
            
              <!-- 已成交 | 委托总量 -->
              <!-- <el-table-column label="已成交 | 委托总量" align="left">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.contractSize, 2, true) }} 合约</span>
                </template>
              </el-table-column> -->
            
              <!-- 已成交｜委托价值 -->
              <el-table-column label="已成交｜委托价值" align="left">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.contractSize * scope.row.entryPrice, 2, true) }} USDT</span>
                </template>
              </el-table-column>
            
              <!-- 收益 | 收益率 -->
              <el-table-column label="收益 | 收益率" align="left">
                <template slot-scope="scope">
                  <span style="color: #25a750">
                    {{ keepDecimalNotRounding(scope.row.realizedPnl, 2, true) }} USDT
                    <br>
                    {{ keepDecimalNotRounding(scope.row.realizedPnl / (scope.row.contractSize * scope.row.entryPrice) * 100, 2, true) }}%
                  </span>
                </template>
              </el-table-column>
            
              <!-- 手续费 -->
              <el-table-column prop="fee" label="手续费" align="left">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.fee || 0, 2, true) }} USDT</span>
                </template>
              </el-table-column>
            
              <!-- 止盈止损 -->
              <!-- <el-table-column label="止盈止损" align="left">
                <template slot-scope="scope">
                  <span>
                    止损价：{{ scope.row.stopLossPrice || '无' }}
                    <br>
                    止盈价：{{ scope.row.takeProfitPrice || '无' }}
                  </span>
                </template>
              </el-table-column> -->
            
              <!-- 只减仓 -->
              <el-table-column prop="hedged" label="只减仓" align="left" width="100">
                <template slot-scope="scope">
                  <span>{{ scope.row.hedged ? '是' : '否' }}</span>
                </template>
              </el-table-column>
            
              <!-- 订单状态 | 编号 -->
              <el-table-column label="订单状态 | 编号" align="left">
                <template slot-scope="scope">
                  <span>
                    <span v-if="scope.row.info.type == '1'">部分成交</span>
                    <span v-if="scope.row.info.type == '2'">完全成交</span>
                    <span v-if="scope.row.info.type == '3'">强平</span>
                    <span v-if="scope.row.info.type == '4'">强减</span>
                    <span v-if="scope.row.info.type == '5'">ADL自动减仓</span>
                    <br>
                    {{ scope.row.id }}
                  </span>
                </template>
              </el-table-column>
            </el-table>
              <el-row class="pages">
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
          </el-tab-pane>
      </el-tabs>
    </div>
  </template>
  <script>
  import Page from "@/components/Page.vue";
  import sig_url from "@/utils/utils.js";
  import { get, post, upload } from "@/common/axios.js";
  export default {
    data() {
      return {
          account_id: 1,
          inst_id: 'BTC-USDT-SWAP',
          currPage: 1, //当前页
          pageSize: 100, //每页显示条数
          total: 100, //总条数
          PageSearchWhere: [], //分页搜索数组
          positionsHistoryList: [],
          historyLoading: false,
          currentPositionsList: [],
          currentLoading: false,
          accountList: [],
      };
    },
    methods: {
      tabClick(item) {
          console.log(item);
          // if(item.label === '') {
          // }
          // if(item.label === '') {
          // }
      },
      accountChange(val) {
        if(val) {
          this.getCurrentPositionsListData();
          this.getPositionsHistoryListData();
        }
      },
      getCurrentPositionsListData(ServerWhere) { //获取当前持仓列表
        var that = this.$data;
        this.currentLoading = true;
        if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
          ServerWhere = {
            account_id: that.account_id,
            inst_id: that.inst_id,
          };
        }
        get("/sigadmin/get_current_positions", {
          account_id: that.account_id,
          inst_id: that.inst_id,
        }, json => {
            // console.log(json);
            this.currentLoading = false;
          if (json.status == 200) {
            this.currentPositionsList = json.data.data;
          } else {
            this.$message.error("加载数据失败");
          }
        });
      },
      getPositionsHistoryListData(ServerWhere) { //获取历史持仓列表
        var that = this.$data;
        this.historyLoading = true;
        if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
          ServerWhere = {
            account_id: that.account_id,
            limit: that.pageSize,
            page: that.currPage,
          };
        }
        get("/sigadmin/get_positions_history", {
          account_id: that.account_id,
          inst_id: that.inst_id,
          limit: that.pageSize,
        }, json => {
            // console.log(json);
            this.historyLoading = false;
          if (json.status == 200) {
            this.positionsHistoryList = json.data.data;
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
        this.PageSearchWhere = [];
        this.PageSearchWhere = SearchWhere;
        this.getPositionsHistoryListData(SearchWhere);
      },
      SearchReset() {
        //搜索条件重置
        this.PageSearchWhere = [];
        this.getPositionsHistoryListData();
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
      handleClose() {
          this.dialogVisibleShow = false;
      },
      formatDate(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        return date.toISOString().replace('T', ' ').substring(0, 19); // 格式化为 YYYY-MM-DD HH:MM:SS
      },
      getAccountList() {
        get("/Grid/grid/getAccountList", {}, json => {
            // console.log(json);
            if (json.data.code == 10000) {
                this.accountList = json.data.data;
            } else {
                this.$message.error("加载账户数据失败");
            }
        });
      },
    },
    created() {
      this.getAccountList();
      this.getCurrentPositionsListData();
      this.getPositionsHistoryListData();
    },
    components: {
      "wbc-page": Page, //加载分页组件
    }
  };
  </script>
  <style lang="scss" scoped>
    .project-top {
      margin-bottom: 20px;
      margin-top: 20px;
    }
    .avatar-uploader .el-upload {
      border: 1px dashed #d9d9d9;
      border-radius: 6px;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .avatar-uploader .el-upload:hover {
      border-color: #409EFF;
    }
    .avatar-uploader-icon {
      font-size: 28px;
      color: #8c939d;
      width: 178px;
      height: 178px;
      line-height: 178px;
      text-align: center;
    }
    .avatar {
      /* width: 178px; */
      width: 100%;
      height: 230px;
      display: block;
    }
    .el-radio-group .el-radio + .el-radio {
      margin-left: 0 !important;
    }
    .pages {
      margin-top: 0!important;
      margin-bottom: 80px !important;
    }
    .el-breadcrumb {
      z-index: 10 !important;
    }
  
    .el-carousel__item h3 {
      color: #475669;
      font-size: 18px;
      opacity: 0.75;
      line-height: 300px;
      margin: 0;
    }
    
    .el-carousel__item:nth-child(2n) {
      background-color: #99a9bf;
    }
    
    .el-carousel__item:nth-child(2n+1) {
      background-color: #d3dce6;
    }
  
    /deep/ {
      .preview-class {
        img {
          width: 100%;
          height: 100%;
          object-fit: contain;
        }
        video {
          // position: absolute;
          width: 100%;
          height: 70vh;
        }
        .el-dialog__headerbtn {
          z-index: 1000;
          color: rgb(3, 3, 3);
          .el-dialog__close {
            color: rgb(3, 3, 3);
            font-size: 20px;
            font-weight: 900;
          }
        }
        .el-dialog__header {
          padding: 0 !important;
        }
        .el-dialog__body {
          padding: 0;
        }
        /* height: 100%; */
      }
    }
  </style>
  