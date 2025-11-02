<template>
  <div class="container" :style="cssVariables">
      <div style="text-align:right;margin-bottom: 10px;">
          <!-- <el-button type="primary" @click="shareProfitShow()">分润</el-button>
          <el-button type="primary" @click="shareProfitRecordShow()">分润记录</el-button>
          &nbsp;
          <el-button type="primary" @click="DepositWithdrawalShow()">出入金</el-button> -->
          <el-button type="primary" @click="dialogVisibleListClick()">出入金记录</el-button>
          <el-button type="primary" @click="addQuantityAccount()">添加账户</el-button>
      </div>
      <el-tabs v-model="activeName" :tab-position="isMobel ? 'top' : 'left'" :stretch="isMobel ? true : false" style="background-color: #fff;" @tab-click="tabsHandleClick" v-if="accountList.length">
          <el-tab-pane :data-id="item.id" :label="item.name" :name="item.name" v-for="(item, index) in accountList" :key="index">
              <div v-if="!isMobel">
                  <el-table
                      :data="tableData"
                      style="width: 100%"
                      height="600">
                      <el-table-column prop="date" label="日期" align="center"></el-table-column>
                      <el-table-column prop="principal" label="累计本金(U)" align="center" width="150">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.principal, 2, true) }}</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="总结余(U)" align="center">
                          <template slot-scope="scope">
                          <!-- <el-link type="primary" @click="accountBalanceDetailsFun(scope.row.account_id)"> -->
                          <el-link type="primary" @click="getTotalBalanceClick(scope.row)">
                              <span>{{ keepDecimalNotRounding(scope.row.total_balance, 2, true) }}</span>
                          </el-link>
                          </template>
                      </el-table-column>
                      <!-- <el-table-column prop="" label="币价" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.price, 2, true) }} USDT</span>
                          </template>
                      </el-table-column> -->
                      <el-table-column prop="" label="日利润(U)" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.daily_profit, 2, true) }}</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="日利润率" align="center" width="100">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.daily_profit_rate, 2, true) }}%</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="平均日利率" align="center" width="100">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.average_day_rate, 2, true) }}%</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="平均年利率" align="center" width="130">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.average_year_rate, 2, true) }}%</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="利润(U)" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.profit, 2, true) }}</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="总分润" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.total_share_profit, 2, true) }}</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="总利润" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.total_profit, 2, true) }}</span>
                          </template>
                      </el-table-column>
                      <el-table-column prop="" label="利润率" align="center">
                          <template slot-scope="scope">
                          <span>{{ keepDecimalNotRounding(scope.row.profit_rate * 100, 2, true) }}%</span>
                          </template>
                      </el-table-column>
                  </el-table>
                  <el-row class="pages" v-if="total > pageSize">
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
              <div v-else>
                  <!-- <div v-if="tableData.length" class="descriptions-table-list"> -->
                    <div
                        class="infinite-list-wrapper"
                        v-if="tableData.length" 
                        v-infinite-scroll="load"
                        style="overflow:auto"
                    >
                      <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                          <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                          <!-- <el-descriptions-item label="账户名称">{{ item.account }}</el-descriptions-item> -->
                            <el-descriptions-item label="总结余">
                                <el-link type="primary" @click="getTotalBalanceClick(item)">
                                    <span>{{ keepDecimalNotRounding(item.total_balance, 2, true) }} USDT</span>
                                </el-link>
                            </el-descriptions-item>
                          <!-- <el-descriptions-item label="币价">{{ keepDecimalNotRounding(item.price, 2, true) }} USDT</el-descriptions-item> -->
                          <el-descriptions-item label="日利润">{{ keepDecimalNotRounding(item.daily_profit, 2, true) }} USDT</el-descriptions-item>
                          <el-descriptions-item label="日利润率">{{ keepDecimalNotRounding(item.daily_profit_rate, 4, true) }}</el-descriptions-item>
                          <el-descriptions-item label="平均日利率">{{ keepDecimalNotRounding(item.average_day_rate, 2, true) }}%</el-descriptions-item>
                          <el-descriptions-item label="平均年利率">{{ keepDecimalNotRounding(item.average_year_rate, 2, true) }}%</el-descriptions-item>
                          <el-descriptions-item label="利润">{{ keepDecimalNotRounding(item.profit, 2, true) }} USDT</el-descriptions-item>
                          <el-descriptions-item label="利润率">{{ keepDecimalNotRounding(item.profit_rate * 100, 2, true) }}%</el-descriptions-item>
                      </el-descriptions>
                      <p v-if="loading">加载中...</p>
                      <p v-if="finished">没有更多了</p>
                  </div>
                  <div v-else>
                      <el-empty description="没有数据"></el-empty>
                  </div>
              </div>
          </el-tab-pane>
        </el-tabs>
        <div v-else>
          <el-empty description="没有数据"></el-empty>
      </div>

      <el-dialog
          title="添加账户"
          :visible.sync="addQuantityAccountShow"
          :width="isMobel ? '90%' : '50%'">
          <el-form :model="accountForm" :rules="accountRules" ref="accountForm" label-width="100px">
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
              <!-- <el-form-item label="网络" prop="type">
                  <el-radio-group v-model="accountForm.type">
                      <el-radio label="1" disabled>Binance</el-radio>
                      <el-radio label="2">OKX</el-radio>
                  </el-radio-group>
              </el-form-item> -->
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

      <el-dialog
      title="出/入金记录"
      :visible.sync="dialogVisibleListShow"
      :width="isMobel ? '100%' : '80%'">
      <div v-if="!isMobel">
          <el-table :data="InoutGoldList" style="width: 100%;" height="500">
              <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column>
              <el-table-column prop="time" label="时间" align="center" width="200"></el-table-column>
              <el-table-column prop="amount" label="出入金额" align="center" width="150">
                  <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.amount, 8, true) }} USDT</span>
                  </template>
              </el-table-column>
              <el-table-column prop="type" label="类型" align="center">
                  <template slot-scope="scope">
                  <span v-if="scope.row.type == 1">入金</span>
                  <span v-else>出金</span>
                  </template>
              </el-table-column>
              <el-table-column prop="total_balance" label="账户余额" align="center" width="150">
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
                      :total="InoutGoldTotal"
                      :pageSize="InoutGoldPageSize"
                      :currPage="InoutGoldCurrPage"
                      @changeLimit="InoutGoldLimitPaging"
                      @changeSkip="InoutGoldSkipPaging"
                  ></wbc-page>
                  </div>
              </el-col>
          </el-row>
      </div>
        <div v-else>
            <div 
                class="infinite-list-wrapper"
                v-if="InoutGoldList.length" 
                v-infinite-scroll="accountBalanceDetailsLoad"
            >
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in InoutGoldList" :key="index">
                    <el-descriptions-item label="时间">{{ item.time }}</el-descriptions-item>
                    <el-descriptions-item label="出入金额"><span>{{ keepDecimalNotRounding(item.amount, 8, true) }} USDT</span></el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <span v-if="item.type == 1">入金</span>
                        <span v-else>出金</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="账户余额"><span>{{ keepDecimalNotRounding(item.total_balance, 4, true) }} USDT</span></el-descriptions-item>
                    <el-descriptions-item label="备注">{{ item.remark }}</el-descriptions-item>
                </el-descriptions>
                <p v-if="InoutGoldLoading">加载中...</p>
                <p v-if="InoutGoldFinished">没有更多了</p>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>
  </el-dialog>

      <!-- 账户余额明细数据 -->
      <el-dialog
          title="账户余额明细"
          :visible.sync="accountBalanceDetailsShow"
          :width="isMobel ? '100%' : '80%'"
          class="dialog-accont-balance"
          @close="closeAccountBalanceDetails">
          <div @tab-click="accountBalanceTabClick">
                  <el-select v-model="currency" clearable placeholder="请选择" @change="selectCurrencyChange">
                      <el-option
                          v-for="item in currencyList"
                          :key="item"
                          :label="item"
                          :value="item">
                      </el-option>
                  </el-select>
                  <div v-if="!isMobel">
                      <el-table :data="accountBalanceDetailsList" style="width: 100%;" height="500">
                          <el-table-column prop="currency" label="币种" align="center" width="">
                              <template slot-scope="scope">
                                  <el-link type="primary">
                                      <span>{{ scope.row.currency }}</span>
                                  </el-link>
                              </template>
                          </el-table-column>
                          <el-table-column prop="" label="价格" align="center" width="150">
                              <template slot-scope="scope">
                                  <span>{{ scope.row.price ? keepDecimalNotRounding(scope.row.price, 10, true) : 0 }}</span>
                              </template>
                          </el-table-column>
                          <el-table-column prop="" label="余额" align="center" width="150">
                              <template slot-scope="scope">
                                  <span>{{ scope.row.balance ? keepDecimalNotRounding(scope.row.balance, 10, true) : 0 }}</span>
                              </template>
                          </el-table-column>
                          <el-table-column prop="" label="USDT估值" align="center" width="">
                              <template slot-scope="scope">
                                  <span v-if="scope.row.currency !== 'USDT'">{{ scope.row.valuation ? keepDecimalNotRounding(scope.row.valuation, 4, true) : 0 }}</span>
                                  <span v-else>————</span>
                              </template>
                          </el-table-column>
                          <el-table-column prop="time" label="更新时间" align="center" width="200"></el-table-column>
                      </el-table>
                      <el-row class="pages">
                          <el-col :span="24">
                              <div style="float:right;">
                              <wbc-page
                                  :total="accountBalanceDetailsTotal"
                                  :pageSize="accountBalanceDetailsLimit"
                                  :currPage="accountBalanceDetailsPage"
                                  @changeLimit="accountBalanceLimitPaging"
                                  @changeSkip="accountBalanceSkipPaging"
                              ></wbc-page>
                              </div>
                          </el-col>
                      </el-row>
                  </div>
                  <div v-else>
                    <div 
                        class="infinite-list-wrapper"
                        v-if="accountBalanceDetailsList.length" 
                        v-infinite-scroll="accountBalanceDetailsLoad"
                    >
                      <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in accountBalanceDetailsList" :key="index">
                          <el-descriptions-item label="币种">{{ item.currency }}</el-descriptions-item>
                          <el-descriptions-item label="价格"><span>{{ item.price ? keepDecimalNotRounding(item.price, 10, true) : 0 }}</span></el-descriptions-item>
                          <el-descriptions-item label="余额">{{ item.balance ? keepDecimalNotRounding(item.balance, 10, true) : 0 }}</el-descriptions-item>
                          <el-descriptions-item label="USDT估值">
                            <span v-if="item.currency !== 'USDT'">{{ item.valuation ? keepDecimalNotRounding(item.valuation, 4, true) : 0 }}</span>
                            <span v-else>————</span>
                          </el-descriptions-item>
                          <el-descriptions-item label="更新时间">{{ item.time }}</el-descriptions-item>
                      </el-descriptions>
                      <p v-if="accountBalanceDetailsLoading">加载中...</p>
                      <p v-if="accountBalanceDetailsFinished">没有更多了</p>
                  </div>
                  <div v-else>
                      <el-empty description="没有数据"></el-empty>
                  </div>
                  </div>
                </div>
      </el-dialog>
  </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getPoolBtcData } from "@/wallet/serve";
import Page from "@/components/Page.vue";
export default {
  name: '',
  data() {
      return {
          active: 2,
          btc_price: 0,
          tableData: [],
          currPage: 1, //当前页
          pageSize: 20, //每页显示条数
          total: 1, //总条数
          loading: false,
          finished: false,
          accountBalanceDetailsLoading: false,
          accountBalanceDetailsFinished: false,
          PageSearchWhere: [],
          dialogVisibleShow: false,
          dialogVisibleListShow: false,
          profitShow: false,
          profitListShow: false,
          ruleForm: {
              direction: '',
              amount: '',
              remark: '',
          },
          ruleProfitForm: {
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
              type: [
                  { required: true, message: '请选择网络', trigger: 'change' }
              ],
              is_position: [
                  { required: true, message: '请选择是否持仓', trigger: 'change' }
              ],
          },
          activeName: '1-0001',
          tabAccountId: 0,
          accountList: [],
          InoutGoldList: [],
          InoutGoldCurrPage: 1, //当前页
          InoutGoldPageSize: 20, //每页显示条数
          InoutGoldLoading: false,
          InoutGoldFinished: false,
          InoutGoldTotal: 1, //总条数
          accountBalanceDetailsShow: false,
          accountBalanceDetailsList: [],
          accountBalanceDetailsPage: 1,
          accountBalanceDetailsLimit: 20,
          accountBalanceDetailsTotal: 0, //总条数
          currencyList: [],
          currency: '',
          accountBalanceTabValue: '',

          currencyPositionsList: [],
          currencyPositionsPage: 1,
          currencyPositionsLimit: 20,
          currencyPositionsTotal: 0, //总条数

          accountCurrencyDetailsShow: false,
          accountCurrencyDetailsList: [],
          accountCurrencyDetailsPage: 1,
          accountCurrencyDetailsLimit: 20,
          accountCurrencyDetailsTotal: 0, //总条数
          selectCurrency: '',

          profitGoldCurrPage: 1, //当前页
          profitGoldPageSize: 20, //每页显示条数
          profitGoldTotal: 1, //总条数
          profitGoldList: [],

          maxMinUplRateShow: false,
          maxMinUplRateLimit: 20,
          maxMinUplRatePage: 1,
          maxMinUplRateList: [],
          maxMinUplRateTotal: 0,
          addQuantityAccountShow: false,
          accountForm: {
              name: '',
              api_key: '',
              secret_key: '',
              pass_phrase: '',
              type: '2',
              is_position: '0',
          },
          clickDate: '',
      }
  },
  computed: {
      ...mapState({
          address:state=>state.base.address,
          isConnected:state=>state.base.isConnected,
          isMobel:state=>state.comps.isMobel,
          mainTheme:state=>state.comps.mainTheme,
          apiUrl:state=>state.base.apiUrl,
          userOkxId:state=>state.base.userOkxId,
      }),
      cssVariables() {
        return {
            '--dialog-margin-top': this.isMobel ? '0' : '15vh',
            '--dialog-height': this.isMobel ? '100vh' : 'none',
        };
    }
  },
  created() {
    //   this.getList();
    //   this.getAccountList();
  },
  watch: {
    userOkxId: {
        handler: function (val, oldVal) {
            if(val) {
                this.getAccountList();
            }
        },
        immediate: true,
    },
  },
  components: {
      "wbc-page": Page, //加载分页组件
  },
  methods: {
        load () { //加载更多
            if(!this.finished && this.tabAccountId) {
                this.getList();
            }
        },
        closeAccountBalanceDetails() {
            this.accountBalanceDetailsPage = 1;
            this.clickDate = '';
            this.currencyList  = [];
            this.accountBalanceDetailsFinished = false;
            this.accountBalanceDetailsLoading = false;
            this.accountBalanceDetailsList = [];
        },
      getList(ServerWhere) {
          if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
              ServerWhere = {
                  limit: this.pageSize,
                  page: this.currPage,
                  account_id: this.tabAccountId,
              };
          }
        //   console.log(ServerWhere);
          this.loading = true;
          get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountDateList", ServerWhere, json => {
              // console.log(json.data);
              if (json.code == 10000) {
                  if (json.data.data) {
                      let list = (json.data && json.data.data) || [];
                      if(this.isMobel) {
                          if (this.currPage <= 1) {
                              // console.log('首次加载');
                              this.tableData = list;
                              // this.$forceUpdate();
                          } else {
                              // console.log('二次加载');
                              if (ServerWhere.page <= json.data.allpage) {
                                  // console.log(ServerWhere.page, json.data.allpage);
                                  this.tableData = [...this.tableData, ...list];
                                  // this.$forceUpdate();
                              }
                          }
                          this.currPage += 1;
                          if (ServerWhere.page >= json.data.allpage) {
                              // console.log(ServerWhere.page, json.data.allpage);
                              this.finished = true;
                          } else {
                              this.finished = false;
                          }
                      } else {
                          this.tableData = list;
                      }
                  }
                  this.total = json.data.count;
                  this.loading = false;
              } else {
                  this.$message.error("加载数据失败");
              }
          });
      },
      getAccountList() {
          get(this.apiUrl + "/Api/QuantifyAccount/getAccountList", {
            user_id: this.userOkxId,
          }, json => {
              console.log("getAccountList", json.data);
              if (json.code == 10000) {
                  this.accountList = json.data;
                  this.activeName = json.data[0].name;
                  this.tabAccountId = json.data[0].id;
                  this.getList();
              }
          })
      },
      accountBalanceDetailsLoad () {
        if(!this.accountBalanceDetailsFinished) {
            this.accountBalanceDetailsFun();
        }
      },
      accountBalanceDetailsFun() { //余额明细数据
          // console.log(account_id);
          // this.accountBalanceTabValue = '1'
          this.accountBalanceDetailsLoading = true;
          get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountDetails", {
              account_id: this.tabAccountId,
              limit: this.accountBalanceDetailsLimit,
              page: this.accountBalanceDetailsPage,
              currency: this.currency,
              date: this.clickDate,
          }, json => {
              console.log(json.data);
              if (json.code == 10000) {
                if (json.data.lists) {
                      let list = (json.data && json.data.lists) || [];
                      if(this.isMobel) {
                          if (this.accountBalanceDetailsPage <= 1) {
                              // console.log('首次加载');
                            this.accountBalanceDetailsList = list;
                              // this.$forceUpdate();
                          } else {
                            this.accountBalanceDetailsList = [...this.accountBalanceDetailsList, ...list];
                          }
                          this.accountBalanceDetailsPage += 1;
                          if (this.accountBalanceDetailsPage >= json.data.allpage) {
                              // console.log(ServerWhere.page, json.data.allpage);
                              this.accountBalanceDetailsFinished = true;
                          } else {
                              this.accountBalanceDetailsFinished = false;
                          }
                      } else {
                          this.accountBalanceDetailsList = list;
                      }
                  }
                  this.accountBalanceDetailsTotal = json.data.count;
                  this.accountBalanceDetailsLoading = false;
              }
          })
      },
      getCurrencyList() {
          get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountCurrencyList", {
              account_id: this.tabAccountId,
          }, json => {
              console.log(json.data);
              if (json.code == 10000) {
                  this.currencyList = json.data;
              }
          })
      },
      selectCurrencyChange() { //根据币种选择对应的账户余额明细数据
        this.accountBalanceDetailsPage = 1;
        this.accountBalanceDetailsFun();
      },

      limitPaging(limit) {
          //赋值当前条数
          this.pageSize = limit;
          this.getList(); //刷新列表
      },
      skipPaging(page) {
          //赋值当前页数
          this.currPage = page;
          this.getList(); //刷新列表
      },
      copySuccess() {
          this.$message({
              message: 'Copy successfully!',
              type: 'success'
          });
      },
      submitForm(formName) { //出入金
          this.$refs[formName].validate((valid) => {
              if (valid) {
                  const loading = this.$loading({
                      target: '.el-dialog',
                  });
                  get('/Api/QuantifyAccount/calcDepositAndWithdrawal', {
                      account_id: this.tabAccountId,
                      direction: this.ruleForm.direction,
                      amount: this.ruleForm.amount,
                      remark: this.ruleForm.remark,
                  }, (json) => {
                      console.log(json);
                      if (json && json.code == 10000) {
                          this.$message.success('更新成功');
                          this.$refs[formName].resetFields();
                          loading.close();
                          this.dialogVisibleShow = false;
                          this.getList();
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
      InoutGoldLoad () {
        if(!this.InoutGoldFinished) {
            this.getInoutGoldList();
        }
      },
      getInoutGoldList(ServerWhere) { //获取出入金记录数据
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.InoutGoldPageSize,
                    page: that.InoutGoldCurrPage,
                    account_id: that.tabAccountId,
                };
            }
            get("/Api/QuantifyAccount/getInoutGoldList", ServerWhere, json => {
                console.log(json);
                if (json.code == 10000) {
                    if (json.data.data) {
                        // this.InoutGoldList = json.data.data;
                        // this.InoutGoldTotal = json.data.count;
                        let list = (json.data && json.data.data) || [];

                        if(this.isMobel) {
                            if (this.InoutGoldCurrPage <= 1) {
                                // console.log('首次加载');
                                this.InoutGoldList = list;
                                // this.$forceUpdate();
                            } else {
                                this.InoutGoldList = [...this.InoutGoldList, ...list];
                            }
                            this.InoutGoldCurrPage += 1;
                            if (this.InoutGoldCurrPage >= json.data.allpage) {
                                // console.log(ServerWhere.page, json.data.allpage);
                                this.InoutGoldFinished = true;
                            } else {
                                this.InoutGoldFinished = false;
                            }
                        } else {
                            this.InoutGoldList = list;
                        }
                    }
                    this.InoutGoldTotal = json.data.count;
                    this.InoutGoldLoading = false;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
      addAccountSubmitForm(formName) { //添加账户
          this.$refs[formName].validate((valid) => {
              if (valid) {
                  const loading = this.$loading({
                      target: '.el-dialog',
                  });
                  post(this.apiUrl + '/Api/QuantifyAccount/addQuantityAccount', this.accountForm, (json) => {
                      console.log(json);
                      if (json && json.code == 10000) {
                          this.$message.success('添加成功');
                          this.$refs[formName].resetFields();
                          loading.close();
                          this.addQuantityAccountShow = false;
                          this.getAccountList();
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
      dialogVisibleListClick() {
          this.dialogVisibleListShow = true;
          this.getInoutGoldList();
      },
      accountBalanceTabClick(tab) {
          console.log(tab);
          this.currencyPositionsList = [];
          this.accountBalanceDetailsList = [];
          if(tab.name == 1) {
              // this.accountBalanceTabValue = '1'
              this.accountBalanceDetailsFun();
          }
          if(tab.name == 3) {
              this.getMaxMinUplRate();
          }
      },
      getTotalBalanceClick(row) { //获取总结余弹框
          this.closeAccountBalanceDetails();
          this.currencyPositionsList = [];
          this.accountBalanceDetailsList = [];
          this.accountBalanceTabValue = '1'
          this.clickDate = row.date;
          this.getCurrencyList();
          this.accountBalanceDetailsFun();
          this.accountBalanceDetailsPage = 1;
          this.accountBalanceDetailsShow = true;
      },
      InoutGoldLimitPaging(limit) {
          //赋值当前条数
          this.InoutGoldPageSize = limit;
          this.getInoutGoldList(); //刷新列表
      },
      InoutGoldSkipPaging(page) {
          //赋值当前页数
          this.InoutGoldCurrPage = page;
          this.getInoutGoldList(); //刷新列表
      },
      profitGoldLimitPaging(limit) {
          //赋值当前条数
          this.profitGoldPageSize = limit;
          this.getProfitGoldList(); //刷新列表
      },
      profitGoldSkipPaging(page) {
          //赋值当前页数
          this.profitGoldCurrPage = page;
          this.getProfitGoldList(); //刷新列表
      },
      accountCurrencyDetailsLimitPaging(limit) {
          //赋值当前条数
          this.accountCurrencyDetailsLimit = limit;
          this.getAccountCurrencyDetailsList(); //刷新列表
      },
      accountCurrencyDetailsSkipPaging(page) {
          //赋值当前页数
          this.accountCurrencyDetailsPage = page;
          this.getAccountCurrencyDetailsList(); //刷新列表
      },
      accountBalanceLimitPaging(limit) {
          //赋值当前条数
          this.accountBalanceDetailsLimit = limit;
          this.accountBalanceDetailsFun(); //刷新列表
      },
      accountBalanceSkipPaging(page) {
          //赋值当前页数
          this.accountBalanceDetailsPage = page;
          this.accountBalanceDetailsFun(); //刷新列表
      },
      currencyPositionsLimitPaging(limit) {
          this.currencyPositionsLimit = limit;
          this.getAccountCurrencyPositionsList();
      },  
      currencyPositionsSkipPaging(page) {
          this.currencyPositionsPage = page;
          this.getAccountCurrencyPositionsList();
      },
      resetForm(formName) {
          this.$refs[formName].resetFields();
      },
      DepositWithdrawalShow() { //出入金 弹框显示\
          this.dialogVisibleShow = true;
      },
      addQuantityAccount() {
          this.addQuantityAccountShow = true;
      },
      tabsHandleClick(tab, event) { //tab切换
          // console.log(tab.$attrs['data-id'], event);
          this.tabAccountId = tab.$attrs['data-id'];
          this.pageSize = 20;
          this.currPage = 1;
          this.tableData = [];
          this.getList();
      },
      shareProfitShow() { //分润 弹框
          this.profitShow = true;
      },
      shareProfitRecordShow() { //分润记录
          this.profitListShow = true;
          this.getProfitGoldList();
      },
      getMaxMinUplRate() { //获取收益率最大最小历史记录
          get("/Api/QuantifyAccount/getMaxMinUplRateData", {
              limit: this.maxMinUplRateLimit,
              page: this.maxMinUplRatePage,
              account_id: this.tabAccountId,
              currency: 'GMX',
          }, json => {
              console.log(json);
              // this.maxMinUplRateShow = true;
              if (json.code == 10000) {
                  this.maxMinUplRateList = json.data.lists;
                  this.maxMinUplRateTotal = json.data.count;
              } else {
                  this.$message.error("加载数据失败");
              }
          });
      },
      maxMinUplRateLimitPaging(limit) {
          //赋值当前条数
          this.maxMinUplRateLimit = limit;
          this.getMaxMinUplRate(); //刷新列表
      },
      maxMinUplRatePaging(page) {
          //赋值当前页数
          this.maxMinUplRatePage = page;
          this.getMaxMinUplRate(); //刷新列表
      },
  },
  mounted() {

  },
}
</script>
<style lang="scss" scoped>
   .container {
      ::v-deep  {
            padding: 16px;
          .el-breadcrumb {
              height: 25px;
              font-size: 10px;
          }
          .el-table {
              font-size: 10px;
          }
          .el-tabs--top {
            border-radius: 12px;
          }
          .el-tabs__item {
              font-size: 10px;
          }
          .descriptions-table-list {
            display: block;
            padding: 0 16px;
          }
          .descriptions-list {
            display: block;
            height: 500px;
            overflow-y: auto;
            margin-top: 20px;
          }
        //   .el-dialog {
        //     margin-top: 0 !important;
        //     height: 100vh;
        //   }
        .el-dialog__wrapper {
            overflow-y: hidden;
        }
        .infinite-list-wrapper {
            height: 100vh;
            margin-bottom: 50px;
            p {
                text-align: center;
            }
        }
        .dialog-accont-balance {
            .el-dialog {
                margin-top: var(--dialog-margin-top) !important;
                height: var(--dialog-height);
            }
            .infinite-list-wrapper {
                height: 80vh;
                overflow: auto;
            }
        }
        .infinite-list-wrapper::-webkit-scrollbar { width: 0 !important }
          .el-descriptions {
              padding: 8px 8px;
              .el-descriptions__body {
                  padding: 20px;
                  border-radius: 20px;
                  background-color: #f5f7f8;
                  .el-descriptions-item__container {
                      .el-descriptions-item__content {
                          display: unset;
                          text-align: right;
                          .operate {
                              text-align: center;
                              button {
                                  width: 80px;
                              }
                          }
                      }
                  }
              }
          }
      }
  }
</style>
