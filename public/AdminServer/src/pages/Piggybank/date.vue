<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">存钱罐管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">数据统计</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="产品名称:">
          <el-input clearable placeholder="产品名称" v-model="product_name"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
          <!-- <el-button class="pull-right" type="primary" @click="DepositWithdrawalShow()">出/入金</el-button> -->
        </el-form-item>
      </el-form>
    </div>
    <el-tabs type="card" @tab-click="tabClick">
        <el-tab-pane label="网格数据">
            <el-table :data="tableData" style="width: 100%;">
                <el-table-column sortable prop="id" type="index" label="序号" width="100" align="center" fixed="left"></el-table-column>
                <el-table-column prop="product_name" label="产品名称" align="center"></el-table-column>
                <el-table-column prop="date" label="日期" align="center"></el-table-column>
                <el-table-column prop="amount" label="总市值" align="center">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.count_market_value, 8, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="grid_spread" label="网格日利润" align="center">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.grid_day_spread, 4, true) }} {{scope.row.base_ccy}} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="网格日利润率" align="center">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.grid_day_spread / scope.row.count_market_value * 100, 4, true) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="网格总利润" align="center">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.grid_spread, 4, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="网格总利润率" align="center">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.grid_spread / scope.row.count_market_value * 100, 4, true) }}%</span>
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
        <el-tab-pane label="币种统计">
            <el-tabs tab-position="left">
                <el-tab-pane label="U本位">
                    <div v-if="UtableData.length">
                        <el-table :data="UtableData" style="width: 100%;">
                            <el-table-column sortable prop="id" type="index" label="序号" width="100" align="center" fixed="left"></el-table-column>
                            <el-table-column prop="product_name" label="产品名称" align="center"></el-table-column>
                            <el-table-column prop="date" label="日期" align="center"></el-table-column>
                            <el-table-column prop="principal" label="累计本金" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.principal, 4, true) }} USDT</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="总结余" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.total_balance, 4, true) }} USDT</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="日利润" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.daily_profit, 4, true) }} USDT</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="日利润率" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.daily_profit_rate, 4, true) }}%</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="利润" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.profit, 4, true) }} USDT</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="利润率" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.profit_rate, 4, true) }}%</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-row class="pages">
                            <el-col :span="24">
                                <div style="float:right;">
                                <wbc-page
                                    :total="Utotal"
                                    :pageSize="UpageSize"
                                    :currPage="UcurrPage"
                                    @changeLimit="UlimitPaging"
                                    @changeSkip="UskipPaging"
                                ></wbc-page>
                                </div>
                            </el-col>
                        </el-row>
                    </div>
                    <div v-else>
                        <el-empty description="数据为空"></el-empty>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="币本位">
                    <div v-if="BtableData.length">
                        <el-table :data="BtableData" style="width: 100%;">
                            <el-table-column sortable prop="id" type="index" label="序号" width="100" align="center" fixed="left"></el-table-column>
                            <el-table-column prop="product_name" label="产品名称" align="center"></el-table-column>
                            <el-table-column prop="date" label="日期" align="center"></el-table-column>
                            <el-table-column prop="principal" label="累计本金" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.principal, 4, true) }} BTC</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="总结余" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.total_balance, 4, true) }} BTC</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="日利润" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.daily_profit, 4, true) }} BTC</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="日利润率" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.daily_profit_rate, 4, true) }}%</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="利润" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.profit, 4, true) }} BTC</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="" label="利润率" align="center">
                                <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.profit_rate, 4, true) }}%</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-row class="pages">
                            <el-col :span="24">
                                <div style="float:right;">
                                <wbc-page
                                    :total="Btotal"
                                    :pageSize="BpageSize"
                                    :currPage="BcurrPage"
                                    @changeLimit="BlimitPaging"
                                    @changeSkip="BskipPaging"
                                ></wbc-page>
                                </div>
                            </el-col>
                        </el-row>
                    </div>
                    <div v-else><el-empty description="没有数据"></el-empty></div>
                </el-tab-pane>
            </el-tabs>
        </el-tab-pane>
    </el-tabs>

    <el-dialog
        title="出/入金"
        :visible.sync="dialogVisibleShow"
        width="30%"
        :before-close="handleClose">
        <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="80px">
            <el-form-item label="方向" prop="direction">
                <el-radio-group v-model="ruleForm.direction">
                <el-radio label="1">入金</el-radio>
                <el-radio label="2">出金</el-radio>
                </el-radio-group>
            </el-form-item>
            <el-form-item label="金额" prop="amount">
                <el-input v-model="ruleForm.amount"></el-input>
            </el-form-item>
            <el-form-item label="备注">
                <el-input v-model="ruleForm.remark"></el-input>
            </el-form-item>
        </el-form>
        <span slot="footer" class="dialog-footer">
            <el-button @click="dialogVisibleShow = false">取 消</el-button>
            <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
        </span>
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
        UcurrPage: 1, //当前页
        UpageSize: 20, //每页显示条数
        Utotal: 100, //总条数
        BcurrPage: 1, //当前页
        BpageSize: 20, //每页显示条数
        Btotal: 100, //总条数
        PageSearchWhere: [], //分页搜索数组
        UPageSearchWhere: [], //分页搜索数组
        BPageSearchWhere: [], //分页搜索数组
        product_name: "BTC-USDT",
        address: "",
        status: "",
        class_id: "",
        imageUrl: '',
        fileObjData: {},
        tableData: [],
        UtableData: [],
        BtableData: [],
        srcList: [], //列表存放大图路径
        dialogVisibleShow: false,
        DialogTitle: '添加',
        is_save_add_start: 1, //1：添加 2：修改
        is_img_upload: 0, 
        id: '', //球队id
        type: '', //球队类型
        GradeArr: [], //等级数据
        ClassArr: [], //分类数据
        UserAuthUid: 0, //当前登录用户id
        fileTempType: 2,
        dialogWidth: '50%',
        imagesUrls: [],
        videoUrl: '',
        videoPoster: '',
        ruleForm: {
            direction: '',
            amount: '',
            remark: '',
        },
        rules: {
          amount: [
            { required: true, message: '请输入金额', trigger: 'blur' },
          ],
          direction: [
            { required: true, message: '请选择方向', trigger: 'change' }
          ],
        }
    };
  },
  methods: {
    tabClick(item) {
        console.log(item);
        if(item.label === '币种统计') {
            this.getUListData();
            this.getBListData();
        }
        if(item.label === '网格数据') {
            this.getListData();
        }
    },
    getListData(ServerWhere) { //获取网格数据
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
        };
      }
      get("/Admin/Piggybank/getPiggybankOrderDateList", ServerWhere, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.total = json.data.data.count;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    getUListData(ServerWhere) { //获取U本位数据
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.UpageSize,
          page: that.UcurrPage,
          standard: 1,
        };
      }
      get("/Admin/Piggybank/getUBPiggybankOrderDateList", ServerWhere, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.UtableData = json.data.data.data;
          this.Utotal = json.data.data.count;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    getBListData(ServerWhere) { //获取B本位数据
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.BpageSize,
          page: that.BcurrPage,
          standard: 2,
        };
      }
      get("/Admin/Piggybank/getUBPiggybankOrderDateList", ServerWhere, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.BtableData = json.data.data.data;
          this.Btotal = json.data.data.count;
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
    UlimitPaging(limit) {
      //赋值当前条数
      this.UpageSize = limit;
      if (
        this.UPageSearchWhere.limit &&
        this.UPageSearchWhere.limit !== undefined
      ) {
        this.UPageSearchWhere.limit = limit;
      }
      this.getUListData(this.UPageSearchWhere); //刷新列表
    },
    UskipPaging(page) {
      //赋值当前页数
      this.UcurrPage = page;
      if (
        this.UPageSearchWhere.page &&
        this.UPageSearchWhere.page !== undefined
      ) {
        this.UPageSearchWhere.page = page;
      }
      this.getUListData(this.UPageSearchWhere); //刷新列表
    },
    BlimitPaging(limit) {
      //赋值当前条数
      this.BpageSize = limit;
      if (
        this.BPageSearchWhere.limit &&
        this.BPageSearchWhere.limit !== undefined
      ) {
        this.BPageSearchWhere.limit = limit;
      }
      this.getBListData(this.BPageSearchWhere); //刷新列表
    },
    BskipPaging(page) {
      //赋值当前页数
      this.BcurrPage = page;
      if (
        this.BPageSearchWhere.page &&
        this.BPageSearchWhere.page !== undefined
      ) {
        this.BPageSearchWhere.page = page;
      }
      this.getBListData(this.BPageSearchWhere); //刷新列表
    },
    submitForm(formName) {
        this.$refs[formName].validate((valid) => {
            if (valid) {
                get('/Admin/Piggybank/calcDepositAndWithdrawal', {
                    product_name: this.product_name,
                    direction: this.ruleForm.direction,
                    amount: this.ruleForm.amount,
                    remark: this.ruleForm.remark,
                }, (json) => {
                    if (json && json.data.code == 10000) {
                        this.$message.success('更新成功');
                        this.$refs[formName].resetFields();
                        this.dialogVisibleShow = false;
                    } else {
                        this.$message.error(json.data.msg);
                    }
                })
            } else {
                console.log('error submit!!');
                return false;
            }
        });
    },
    resetForm(formName) {
        this.$refs[formName].resetFields();
    },
    DepositWithdrawalShow() { //出入金 弹框显示
        this.dialogVisibleShow = true;
    },
    handleClose() {
        this.dialogVisibleShow = false;
    }

  },
  created() {
    this.getListData();
    this.UserAuthUid = localStorage.getItem("UserAuthUid");
  },
  components: {
    "wbc-page": Page, //加载分页组件
  }
};
</script>
<style lang="scss" scoped>
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
