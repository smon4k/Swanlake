<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">{{tradingPairData.transaction_currency}}存钱罐管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">出/入金</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <!-- <el-form-item label="产品名称:">
          <el-input clearable placeholder="产品名称" v-model="product_name"></el-input>
        </el-form-item> -->
        <el-form-item>
          <!-- <el-button type="primary" @click="SearchClick()">搜索</el-button> -->
          <el-button class="pull-right" type="primary" @click="DepositWithdrawalShow()">出/入金</el-button>
        </el-form-item>
      </el-form>
    </div>
    <el-table :data="tableData" style="width: 100%;">
        <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column>
        <el-table-column prop="time" label="时间" align="center"></el-table-column>
        <el-table-column prop="amount" label="出入金额" align="center">
            <template slot-scope="scope">
            <span>{{ keepDecimalNotRounding(scope.row.amount, 8, true) }} USDT</span>
            </template>
        </el-table-column>
        <el-table-column prop="total_balance" label="账户余额" align="center">
            <template slot-scope="scope">
            <span>{{ keepDecimalNotRounding(scope.row.total_balance, 4, true) }} USDT</span>
            </template>
        </el-table-column>
        <el-table-column prop="" label="备注" align="center">
            <template slot-scope="scope">
            <span>{{ scope.row.remark }}</span>
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
        currency_id: 0,
        currPage: 1, //当前页
        pageSize: 20, //每页显示条数
        total: 100, //总条数
        PageSearchWhere: [], //分页搜索数组
        product_name: "BTC-USDT",
        address: "",
        status: "",
        class_id: "",
        imageUrl: '',
        fileObjData: {},
        tableData: [],
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
        },
        tradingPairData: {},
        loading: true,
    };
  },
  methods: {
    getTradingPairData() { //获取交易币种信息
      get("/Piggybank/index/getTradingPairData", {
        currency_id: this.currency_id,
      }, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.tradingPairData = json.data.data;
          this.product_name = this.tradingPairData.name;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    getListData(ServerWhere) { //获取U本位数据
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
          currency_id: that.currency_id,
        };
      }
      get("/Piggybank/index/getInoutGoldList", ServerWhere, json => {
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
    submitForm(formName) {
        this.$refs[formName].validate((valid) => {
            if (valid) {
                const loading = this.$loading({
                  target: '.el-dialog',
                });
                get('/Piggybank/index/calcDepositAndWithdrawal', {
                    product_name: this.product_name,
                    direction: this.ruleForm.direction,
                    amount: this.ruleForm.amount,
                    remark: this.ruleForm.remark,
                    currency_id: this.currency_id,
                }, (json) => {
                    if (json && json.data.code == 10000) {
                        this.$message.success('更新成功');
                        this.$refs[formName].resetFields();
                        loading.close();
                        this.dialogVisibleShow = false;
                        this.getListData();
                    } else {
                        loading.close();
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
    DepositWithdrawalShow() { //出入金 弹框显示\
        this.dialogVisibleShow = true;
    },
    handleClose() {
        this.dialogVisibleShow = false;
    }

  },
  created() {
    this.currency_id = this.$route.query.currency_id;
    this.getTradingPairData();
    this.getListData();
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
