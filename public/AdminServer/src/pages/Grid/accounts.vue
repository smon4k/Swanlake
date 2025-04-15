<template>
    <div>
      <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item>账户列表</el-breadcrumb-item>
      </el-breadcrumb>
  
      <el-table :data="accountList" style="width: 100%; margin-top: 20px;">
        <el-table-column prop="name" label="账户名称" align="center"></el-table-column>
        <el-table-column prop="api_key" label="API Key" align="center"></el-table-column>
        <el-table-column prop="api_secret" label="API Secret" align="center"></el-table-column>
        <el-table-column prop="api_passphrase" label="API Passphrase" align="center"></el-table-column>
        <el-table-column prop="balance" label="账户余额" align="center">
          <template slot-scope="scope">
            {{ scope.row.balance }} USDT
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
        PageSearchWhere: {}
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
            get("/Grid/grid/getAccountList", {}, response => {
                if (response.data.code == 10000) {
                    this.accountList = response.data.data;
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
                        account.balance = response.data.data;
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