<template>
    <div>
      <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item>账户列表</el-breadcrumb-item>
      </el-breadcrumb>
  
      <el-table :data="accountList" style="width: 100%; margin-top: 20px;"  v-loading="loading">
        <el-table-column prop="name" label="账户名称" align="center"></el-table-column>
        <el-table-column prop="api_key" label="API Key" align="center"></el-table-column>
        <el-table-column prop="api_secret" label="API Secret" align="center"></el-table-column>
        <el-table-column prop="api_passphrase" label="API Passphrase" align="center"></el-table-column>
        <el-table-column prop="balance" label="账户余额" align="center">
          <template slot-scope="scope">
            <div class="balance-container">
                <span v-if="!scope.row.balanceLoading">{{ keepDecimalNotRounding(scope.row.balance, 2) }} USDT</span>
                <span v-else style="display: contents;"><span class="loading"></span>&nbsp;&nbsp;USDT</span>
              </div>
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
  import { get } from "@/common/axios.js";
  
  export default {
    data() {
      return {
        currPage: 1,
        pageSize: 10,
        total: 0,
        accountList: [],
        inst_id: 'BTC-USDT-SWAP',
        PageSearchWhere: {},
        loading: false,
      };
    },
    created() {
      this.getAccountList();
    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    methods: {
        limitPaging(limit) {
        //赋值当前条数
        this.pageSize = limit;
        if (
            this.PageSearchWhere.limit &&
            this.PageSearchWhere.limit !== undefined
        ) {
            this.PageSearchWhere.limit = limit;
        }
        this.getAccountList(this.PageSearchWhere); //刷新列表
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
        this.getAccountList(this.PageSearchWhere); //刷新列表
        },
        getAccountList() {
            this.loading = true;
            get("/Grid/grid/getAccountList", {}, response => {
                if (response.data.code == 10000) {
                    this.loading = false;
                    this.accountList = response.data.data.map(account => ({
                        ...account,
                        balance: '--',
                        balanceLoading: true // 初始化loading状态
                    }));
                    this.total = response.data.data.count;
                    this.fetchAccountBalances();
                } else {
                    this.$message.error("加载账户数据失败");
                }
            });
        },
        fetchAccountBalances() {
            this.accountList.forEach(account => {
                get("/sigadmin/get_account_over", {
                    account_id: account.id,
                    inst_id: this.inst_id
                }, response => {
                    console.log(response);
                    if (response.status == 200) {
                        account.balance = response.data.data.data;
                        account.balanceLoading = false; // 结束loading
                    } else {
                        account.balance = '获取失败';
                        this.$message.error("获取账户余额失败");
                    }
                });
            });
        },
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
  .balance-container {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .loading {
    display: contents;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 14px;
  }
  .loading::after {
    content: ' ';
    display: block;
    width: 16px;
    height: 16px;
    border: 2px solid #606266;
    border-top-color: transparent;
    border-radius: 50%;
    animation: loading 1s linear infinite;
  }
  @keyframes loading {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>