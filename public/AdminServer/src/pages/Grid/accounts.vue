<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>账户列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div style="text-align:right;margin-bottom: 10px;">
      <!-- <el-button type="primary" @click="shareProfitShow()">分润</el-button>
        <el-button type="primary" @click="shareProfitRecordShow()">分润记录</el-button>
        &nbsp;
        <el-button type="primary" @click="DepositWithdrawalShow()">出入金</el-button>
        <el-button type="primary" @click="dialogVisibleListClick()">出入金记录</el-button> -->
      <el-button type="primary" @click="addQuantityAccount()">添加账户</el-button>
    </div>
    <el-table :data="accountList" style="width: 100%; margin-top: 20px;" v-loading="loading">
      <el-table-column prop="name" label="账户名称" align="center"></el-table-column>
      <el-table-column prop="exchange" label="网络" align="center"></el-table-column>
      <el-table-column prop="api_key" label="API Key" align="center"></el-table-column>
      <el-table-column prop="api_secret" label="API Secret" align="center"></el-table-column>
      <el-table-column prop="api_passphrase" label="API Passphrase" align="center"></el-table-column>
      <el-table-column prop="add_time" label="添加时间" align="center"></el-table-column>
      <el-table-column prop="total_balance" label="账户余额" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.total_balance, 2) }} USDT</span>
        </template>
      </el-table-column>
      <el-table-column prop="balance" label="操作" align="center">
        <template slot-scope="scope">
          <el-button type="text" @click="DelData(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-row class="pages">
      <el-col :span="24">
        <div style="float:right;">
          <wbc-page :total="total" :pageSize="pageSize" :currPage="currPage" @changeLimit="limitPaging"
            @changeSkip="skipPaging"></wbc-page>
        </div>
      </el-col>
    </el-row>

    <el-dialog title="添加账户" :visible.sync="addQuantityAccountShow" width="50%">
      <el-form :model="accountForm" :rules="accountRules" ref="accountForm" label-width="120px">
        <el-form-item label="账户名称" prop="name">
          <el-input v-model="accountForm.name"></el-input>
        </el-form-item>
        <el-form-item label="APIKey" prop="api_key">
          <el-input v-model="accountForm.api_key"></el-input>
        </el-form-item>
        <el-form-item label="SecretKey" prop="secret_key">
          <el-input v-model="accountForm.secret_key"></el-input>
        </el-form-item>
        <el-form-item label="Passphrase" prop="pass_phrase">
          <el-input v-model="accountForm.pass_phrase"></el-input>
        </el-form-item>
        <el-form-item label="网络" prop="exchange">
          <el-radio-group v-model="accountForm.exchange">
            <!-- <el-radio label="binance">Binance</el-radio> -->
            <el-radio label="okx">OKX</el-radio>
          </el-radio-group>
        </el-form-item>
        <!-- <el-form-item label="是否持仓" prop="is_position">
                    <el-radio-group v-model="accountForm.is_position">
                        <el-radio label="1">是</el-radio>
                        <el-radio label="0">否</el-radio>
                    </el-radio-group>
                </el-form-item> -->
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="addQuantityAccountShow = false">取 消</el-button>
        <el-button type="primary" @click="addAccountSubmitForm('accountForm')">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import Page from "@/components/Page.vue";
import { get, post } from "@/common/axios.js";

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
      addQuantityAccountShow: false,
      accountForm: {
        name: '',
        api_key: '',
        secret_key: '',
        pass_phrase: '',
        exchange: 'okx',
        is_position: '0',
      },
      accountRules: {
        name: [
          { required: true, message: '请输入账户名称', trigger: 'blur' }
        ],
        api_key: [
          { required: true, message: '请输入APIKey', trigger: 'blur' }
        ],
        secret_key: [
          { required: true, message: '请输入SecretKey', trigger: 'blur' }
        ],
        pass_phrase: [
          { required: true, message: '请输入Passphrase', trigger: 'blur' }
        ],
        exchange: [
          { required: true, message: '请选择网络', trigger: 'change' }
        ],
        is_position: [
          { required: true, message: '请选择是否持仓', trigger: 'change' }
        ],
      }
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
          // 按余额从大到小排序
          this.accountList = response.data.data.sort((a, b) => {
            const balanceA = Number(a.total_balance) || 0;
            const balanceB = Number(b.total_balance) || 0;
            return balanceB - balanceA;
          });
          this.total = response.data.data.length;
        } else {
          this.loading = false;
          this.$message.error("加载账户数据失败");
        }
      });
    },
    addQuantityAccount() {
      this.addQuantityAccountShow = true;
    },
    addAccountSubmitForm(formName) { //添加账户
      this.$refs[formName].validate((valid) => {
        if (valid) {
          const loading = this.$loading({
            target: '.el-dialog',
          });
          post('/Grid/Quantifyaccount/addQuantityAccount', this.accountForm, (json) => {
            console.log(json);
            if (json && json.data.code == 10000) {
              this.$message.success('添加成功');
              this.addQuantityAccountShow = false;
              this.$refs[formName].resetFields();
              loading.close();
              this.dialogVisibleShow = false;
              this.getAccountList();
            } else {
              loading.close();
              this.addQuantityAccountShow = false;
              this.$message.error(json.data.msg);
            }
          })
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    DelData(row) { //删除管理员
        this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).then(() => {
            get('/Grid/Quantifyaccount/deleteQuantityAccount', {account_id: row.id}, (json) => {
                if (json && json.data.code == 10000) {
                    this.getAccountList();
                    this.$message({
                        type: 'success',
                        message: '删除成功!'
                    });
                } else if(json.data.code == 10007) {
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
  },
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

.balance-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
}

</style>