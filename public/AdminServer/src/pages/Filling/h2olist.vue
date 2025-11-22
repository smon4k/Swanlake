<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">充提管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">H2O充提列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="钱包地址:">
          <el-input style="width:500px" clearable placeholder="钱包地址" v-model="address"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
        </el-form-item>
      </el-form>
    </div>
    <el-table :data="tableData" style="width: 100%;" v-loading="loading">
      <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left"></el-table-column>
      <el-table-column prop="address" label="Token" align="center" :show-overflow-tooltip="true"></el-table-column>
      <el-table-column prop="time" label="时间" width="180" align="center"></el-table-column>
      <el-table-column prop="local_balance" label="充值数量(H2O)" width="120" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(Number(scope.row.amount), 2)}}</span>
        </template>
      </el-table-column>
      <el-table-column prop="local_balance" label="平台业务余额(H2O)" width="150" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(Number(scope.row.gs_balance), 2)}}</span>
        </template>
      </el-table-column>
      <el-table-column prop="local_balance" label="钱包充提余额(H2O)" width="150" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(Number(scope.row.cs_balance), 2)}}</span>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="类型" width="100" align="center">
        <template slot-scope="scope">
          <span v-if="scope.row.type == 1">充值</span>
          <span v-else>提取</span>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="状态" width="100" align="center">
        <template slot-scope="scope">
          <span v-if="scope.row.status == 1">进行中</span>
          <span v-else>已完成</span>
        </template>
      </el-table-column>
       <el-table-column prop="type" label="BSCscan" width="100" align="center">
        <template slot-scope="scope">
          <a :href="'https://bscscan.com//tx/' + scope.row.hash" target="_blank" rel="noopener noreferrer">
            <img src="@/assets/bsc-show.png" alt="" width="30">
          </a>
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
      address: "",
      tableData: [],
      loading: false,
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
      this.loading = true;
      get("/Admin/Filling/getH2ODepositWithdrawRecord", ServerWhere, json => {
          console.log(json);
          this.loading = false;
        if (json.data.code == 10000) {
          this.tableData = json.data.data.lists;
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
      if (this.address && this.address !== "") {
        SearchWhere["address"] = this.address;
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
    resetForm(formName) {
      // console.log(this.$refs[formName])
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    },

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

  ::v-deep {
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
