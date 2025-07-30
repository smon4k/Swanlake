<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item to="">持仓管理</el-breadcrumb-item>
      <el-breadcrumb-item to="">订单列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <!-- <h2>{{ tradingPairData.name }} 策略详情</h2> -->
      <el-select v-model="account_id" clearable placeholder="选择要搜索的账户" @change="accountChange" @clear="accountClear">
        <el-option v-for="(item, index) in accountList" :key="index" :label="item.name" :value="item.id">
        </el-option>
      </el-select>
      &nbsp;&nbsp;
      <el-select v-model="strategy_name" clearable placeholder="选择策略" @change="strategyChange" @clear="strategyClear">
        <el-option v-for="item in strategyOptions" :key="item.name" :label="item.name" :value="item.name">
          <span style="float: left">{{ item.name }}</span>
          <span style="float: right; color: #8492a6; font-size: 13px">{{ item.label }}</span>
        </el-option>
      </el-select>
      <div style="margin-left: 20px;">总利润：{{ keepDecimalNotRounding(totalProfit, 4) }} USDT</div>
      <!-- <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="产品名称:">
          <el-input clearable placeholder="产品名称" v-model="product_name"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
        </el-form-item>
      </el-form> -->
    </div>

    <el-collapse v-model="activeNames" @change="handleChange" v-for="(item, index) in tableData" :key="index">
      <el-collapse-item :title="item.timestamp" :name="index">
        <template slot="title">
          <div style="margin-right:20px;">{{ item.account_name }} &nbsp;&nbsp;&nbsp;&nbsp; 时间：{{ item.timestamp }}</div>
          <!-- <div style="margin-right:100px;">价差：{{ keepDecimalNotRounding(item.price, 4, true) }} USDT</div> -->
          <div style="">利润：{{ keepDecimalNotRounding(item.profit, 4, true) }} USDT</div>
        </template>
        <el-table :data="item.lists" style="width: 100%;" v-show="true">
          <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left"></el-table-column>
          <el-table-column prop="symbol" label="交易对" align="center" width="150"></el-table-column>
          <el-table-column prop="order_id" label="订单号" align="center" width="200"></el-table-column>
          <el-table-column prop="order_type" label="订单类型" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.order_type">
                <span v-if="scope.row.order_type == 'market'">市价单</span>
                <span v-else>限价单</span>
              </span>
              <span v-else>——</span>
            </template>
          </el-table-column>
          <!-- <el-table-column prop="base_ccy" label="交易货币币种" align="center"></el-table-column>
                <el-table-column prop="quote_ccy" label="计价货币币种" align="center"></el-table-column> -->
          <el-table-column prop="" label="时间" align="center" width="200">
            <template slot-scope="scope">
              <span>{{ scope.row.timestamp ? scope.row.timestamp : "--" }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="type" label="交易方向" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.side">
                <span v-if="scope.row.side === 'buy' && scope.row.pos_side === 'long'"
                  style="color:#05C48E">买入开多</span>
                <span v-else-if="scope.row.side === 'buy' && scope.row.pos_side === 'short'"
                  style="color:#05C48E">买入平空</span>
                <span v-else-if="scope.row.side === 'sell' && scope.row.pos_side === 'long'"
                  style="color:#df473d;">卖出平多</span>
                <span v-else-if="scope.row.side === 'sell' && scope.row.pos_side === 'short'"
                  style="color:#df473d;">卖出开空</span>
                <span v-else style="color:#df473d;">卖出</span>
              </div>
              <!-- <div v-else>{{ scope.row.type_str }}</div> -->
            </template>
          </el-table-column>
          <el-table-column prop="amount" label="委托数量" align="center" width="150">
            <template slot-scope="scope">
              <div v-if="scope.row.quantity">
                <span>{{ keepDecimalNotRounding((scope.row.quantity * Number(symbol_decimal[scope.row.symbol])) *
                  scope.row.price, 4, true) }} USDT</span>
              </div>
              <div v-else>——</div>
            </template>
          </el-table-column>
          <el-table-column prop="capped" label="成交均价" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.executed_price">
                <span>{{ keepDecimalNotRounding(scope.row.executed_price, 4, true) }}</span>
              </div>
              <div v-else>——</div>
            </template>
          </el-table-column>
          <el-table-column prop="capped" label="是否平仓" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.is_clopos">
                <span v-if="scope.row.is_clopos === 1">已平仓</span>
                <span v-else>——</span>
              </div>
              <div v-else>——</div>
            </template>
          </el-table-column>
          <el-table-column prop="capped" label="成交状态" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.status">
                <span v-if="scope.row.status === 'live'">待成交</span>
                <span v-else-if="scope.row.status === 'filled'">已成交</span>
                <span v-else-if="scope.row.status === 'partially_filled'">部分成交</span>
                <span v-else-if="scope.row.status === 'canceled'">已撤单</span>
              </div>
              <div v-else>——</div>
            </template>
          </el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>

    <el-row class="pages">
      <el-col :span="24">
        <div style="float:right;">
          <wbc-page :total="total" :pageSize="pageSize" :currPage="currPage" @changeLimit="limitPaging"
            @changeSkip="skipPaging"></wbc-page>
        </div>
      </el-col>
    </el-row>


    <el-dialog title="" :visible.sync="previewFilesShow" :width="dialogWidth" :before-close="previewFilesShowClose"
      class="preview-class">
      <div v-if="fileTempType == 1">
        <el-carousel :interval="5000" :autoplay="false" arrow="always">
          <el-carousel-item v-for="item in imagesUrls" :key="item">
            <!-- <h3>{{ item }}</h3> -->
            <img :src="item" alt="" srcset="">
          </el-carousel-item>
        </el-carousel>
      </div>
      <div v-if="fileTempType == 2">
        <video id="videoPlay" ref="player" class="video" controls autoplay preload="auto" :src="videoUrl"
          :poster="videoPoster" v-if="videoUrl" controlslist="noplaybackrate nodownload nofullscreen noremoteplayback"
          disablePictureInPicture="true"></video>
      </div>
      <!-- <span slot="footer" class="dialog-footer">
        <el-button @click="previewFilesShow = false">取 消</el-button>
        <el-button type="primary" @click="previewFilesShow = false">确 定</el-button>
      </span> -->
    </el-dialog>
  </div>
</template>
<script>
import Page from "@/components/Page.vue";
import { get, post, upload } from "@/common/axios.js";
export default {
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 20, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      product_name: "",
      account_id: '',
      address: "",
      status: "",
      class_id: "",
      imageUrl: '',
      fileObjData: {},
      tableData: [],
      srcList: [], //列表存放大图路径
      dialogVisibleShow: false,
      DialogTitle: '添加',
      is_save_add_start: 1, //1：添加 2：修改
      is_img_upload: 0,
      id: '',
      type: '',
      GradeArr: [], //等级数据
      ClassArr: [], //分类数据
      UserAuthUid: 0, //当前登录用户id
      previewFilesShow: false,
      fileTempType: 2,
      dialogWidth: '50%',
      imagesUrls: [],
      videoUrl: '',
      videoPoster: '',
      activeNames: ['1'],
      tradingPairData: {},
      accountList: [], // 账户列表
      symbol_decimal: {
        'BTC-USDT-SWAP': 0.01,
        'ETH-USDT-SWAP': 0.1,
      }, //小数位数
      totalProfit: 0,
      strategyOptions: [], // 策略列表
      strategy_name: '', // 策略名称
    };
  },
  mounted() {
    setTimeout(() => {
    }, 300)
  },
  created() {
    this.getAllStrategyList();
    this.getAccountList();
    this.getListData();
  },
  components: {
    "wbc-page": Page, //加载分页组件
  },
  methods: {
    getTradingPairData() { //获取交易币种信息
      get("/Admin/Piggybank/getTradingPairData", {}, json => {
        console.log(json);
        if (json.data.code == 10000) {
          this.tradingPairData = json.data.data;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    accountChange(val) {
      this.PageSearchWhere = {
        limit: this.pageSize,
        page: this.currPage,
        account_id: val,
        strategy_name: this.strategy_name,
      }
      this.getListData(this.PageSearchWhere);
    },
    strategyChange(val) {
      this.PageSearchWhere = {
        limit: this.pageSize,
        page: this.currPage,
        strategy_name: val,
        account_id: this.account_id,
      }
      this.getListData(this.PageSearchWhere);
    },
    accountClear() {
      this.account_id = '';
      this.getListData();
    },
    strategyClear() {
      this.strategy_name = '';
      this.getListData();
    },
    getListData(ServerWhere) {
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
          account_id: that.account_id,
          strategy_name: that.strategy_name,
        };
      }
      get("/Grid/grid/getOrderList", ServerWhere, json => {
        console.log(json);
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.activeNames = json.data.data.data.map((item, index) => index);
          this.total = json.data.data.count;
          this.totalProfit = json.data.data.totalProfit;
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
    getAllStrategyList() {
      get("/Grid/grid/getAllStrategyList", {}, json => {
        console.log(json);
        if (json.data.code == 10000) {
          this.strategyOptions = json.data.data;
        } else {
          this.$message.error("加载策略数据失败");
        }
      });
    },
    // getClassList() {
    //   get("/H2omarketplace/H2onft/getClassList", {}, (json) => {
    //       // console.log(json);
    //     if (json.data.code == 10000) {
    //       this.ClassArr = json.data.data;
    //     } else {
    //       this.$message.error("加载数据失败");
    //     }
    //   });
    // },
    SearchClick() {
      //搜索事件
      var SearchWhere = {
        page: this.currPage,
        limit: this.pageSize,
      };
      if (this.product_name && this.product_name !== "") {
        SearchWhere["product_name"] = this.product_name;
      }
      this.PageSearchWhere = [];
      this.PageSearchWhere = SearchWhere;
      this.getListData(SearchWhere);
    },
    SearchReset() {
      //搜索条件重置
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
        get('/Admin/Notes/delNotesRow', { id: row.id }, (json) => {
          if (json && json.data.code == 10000) {
            this.getListData();
            this.$message({
              type: 'success',
              message: '删除成功!'
            });
          } else if (json.data.code == 10007) {
            this.$message.error(json.data.msg);
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
    resetForm(formName) {
      // console.log(this.$refs[formName])
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    },
    rackUpStart(row, status) { //审批 修改状态
      get('/Admin/Notes/rackUpStart', { id: row.id, status: status }, (json) => {
        if (json.data.code == 10000) {
          this.$message({
            message: '操作成功',
            type: 'success'
          });
          this.getListData();
        } else if (json.data.code == 10007) {
          this.$message.error(json.data.msg);
        } else {
          this.$message.error('修改失败');
        }
      })
    },
    previewFilesShowClose() {
      if (this.fileTempType == 2) {
        this.$refs.player.pause();//暂停
      }
      this.previewFilesShow = false;
    },
    getTicketDetails(row) { //预览
      // console.log(row);
      this.$router.push({
        name: 'TicketListDetails',
        query: {
          ticket_id: row.id,
          ticket_name: row.name
        }
      })
    },
    handleChange() {

    }

  },
};
</script>
<style lang="scss" scoped>
.project-top {
  display: flex;
  align-items: center;
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

.el-radio-group .el-radio+.el-radio {
  margin-left: 0 !important;
}

.pages {
  margin-top: 0 !important;
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
