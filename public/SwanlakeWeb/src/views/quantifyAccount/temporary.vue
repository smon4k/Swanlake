<template>
    <div class="container">
        <div style="text-align:right;margin-bottom: 10px;">
            <el-button type="primary" @click="DepositWithdrawalShow()">出入金</el-button>
            <el-button type="primary" @click="dialogVisibleListClick()">出入金记录</el-button>
        </div>
        <el-tabs v-model="activeName" :tab-position="isMobel ? 'top' : 'left'" :stretch="isMobel ? true : false" style="background-color: #fff;" @tab-click="tabsHandleClick">
            <el-tab-pane :data-id="item.id" :label="item.name" :name="item.name" v-for="(item, index) in accountList" :key="index">
                <div v-if="!isMobel">
                    <el-table
                        :data="tableData"
                        style="width: 100%">
                        <el-table-column prop="date" label="日期" align="center"></el-table-column>
                        <el-table-column prop="principal" label="累计本金(USDT)" align="center" width="150">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.principal, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="总结余(USDT)" align="center">
                            <template slot-scope="scope">
                            <el-link type="primary" @click="accountBalanceDetailsFun(scope.row.account_id)">
                                <span>{{ keepDecimalNotRounding(scope.row.total_balance, 2, true) }}</span>
                            </el-link>
                            </template>
                        </el-table-column>
                        <!-- <el-table-column prop="" label="币价" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.price, 2, true) }} USDT</span>
                            </template>
                        </el-table-column> -->
                        <el-table-column prop="" label="日利润(USDT)" align="center">
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
                        <el-table-column prop="" label="利润(USDT)" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.profit, 2, true) }}</span>
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
                    <div v-if="tableData.length">
                        <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                            <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                            <!-- <el-descriptions-item label="账户名称">{{ item.account }}</el-descriptions-item> -->
                            <el-descriptions-item label="总结余">{{ keepDecimalNotRounding(item.total_balance, 2, true) }} USDT</el-descriptions-item>
                            <!-- <el-descriptions-item label="币价">{{ keepDecimalNotRounding(item.price, 2, true) }} USDT</el-descriptions-item> -->
                            <el-descriptions-item label="日利润">{{ keepDecimalNotRounding(item.daily_profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="日利润率">{{ keepDecimalNotRounding(item.daily_profit_rate, 4, true) }}</el-descriptions-item>
                            <el-descriptions-item label="平均日利率">{{ keepDecimalNotRounding(item.average_day_rate, 2, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="平均年利率">{{ keepDecimalNotRounding(item.average_year_rate, 2, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="利润">{{ keepDecimalNotRounding(item.profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="利润率">{{ keepDecimalNotRounding(item.profit_rate * 100, 2, true) }}%</el-descriptions-item>
                        </el-descriptions>
                    </div>
                    <div v-else>
                        <el-empty description="没有数据"></el-empty>
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

         <el-dialog
            :title="activeName + ' 出/入金'"
            :visible.sync="dialogVisibleShow"
            width="50%">
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

         <el-dialog
            title="出/入金记录"
            :visible.sync="dialogVisibleListShow"
            width="50%">
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
        </el-dialog>

        <!-- 账户余额明细数据 -->
        <el-dialog
            title="账户余额明细"
            :visible.sync="accountBalanceDetailsShow"
            width="50%">
            <el-table :data="accountBalanceDetailsList" style="width: 100%;" height="300">
                <!-- <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column> -->
                <el-table-column prop="currency" label="币种" align="center" width="">
                    <template slot-scope="scope">
                        <el-link type="primary" @click="getAccountCurrencyDetailsShow(scope.row.currency)">
                            <span>{{ scope.row.currency }}</span>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="余额" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.balance, 10, true) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="USDT估值" align="center" width="">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.valuation, 4, true) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="time" label="更新时间" align="center" width="200"></el-table-column>
            </el-table>
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
            PageSearchWhere: [],
            dialogVisibleShow: false,
            dialogVisibleListShow: false,
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
            activeName: 'MartinObserve',
            tabAccountId: 0,
            accountList: [],
            InoutGoldList: [],
            InoutGoldCurrPage: 1, //当前页
            InoutGoldPageSize: 20, //每页显示条数
            InoutGoldTotal: 1, //总条数
            accountBalanceDetailsShow: false,
            accountBalanceDetailsList: [],
            accountCurrencyDetailsShow: false,
            accountCurrencyDetailsList: [],
            accountCurrencyDetailsPage: 1,
            accountCurrencyDetailsLimit: 20,
            accountCurrencyDetailsTotal: 0, //总条数
            selectCurrency: '',
        }
    },
    computed: {
        ...mapState({
            address:state=>state.base.address,
            isConnected:state=>state.base.isConnected,
            isMobel:state=>state.comps.isMobel,
            mainTheme:state=>state.comps.mainTheme,
            apiUrl:state=>state.base.apiUrl,
        }),

    },
    created() {
        this.getList();
        this.getAccountList();
    },
    watch: {

    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    methods: {
        getList(ServerWhere) {
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: this.pageSize,
                    page: this.currPage,
                    account_id: this.tabAccountId,
                };
            }
            console.log(ServerWhere);
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
                external: 1,
            }, json => {
                console.log(json.data);
                if (json.code == 10000) {
                    this.accountList = json.data;
                    this.activeName = this.accountList[0].name;
                    this.tabAccountId = this.accountList[0].id;
                    this.getList();
                }
            })
        },
        accountBalanceDetailsFun(account_id) {
            console.log(account_id);
            get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountDetails", {
                account_id: account_id
            }, json => {
                console.log(json.data);
                if (json.code == 10000) {
                    this.accountBalanceDetailsList = json.data;
                }
            })
            this.accountBalanceDetailsShow = true;
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
        dialogVisibleListClick() {
            this.dialogVisibleListShow = true;
            this.getInoutGoldList();
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
                    this.InoutGoldList = json.data.data;
                    this.InoutGoldTotal = json.data.count;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
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
        resetForm(formName) {
            this.$refs[formName].resetFields();
        },
        DepositWithdrawalShow() { //出入金 弹框显示\
            this.dialogVisibleShow = true;
        },
        tabsHandleClick(tab, event) { //tab切换
            // console.log(tab.$attrs['data-id'], event);
            this.tabAccountId = tab.$attrs['data-id'];
            this.getList();
        },
        getAccountCurrencyDetailsShow(currency) { //获取账户币种交易明细数据
            this.accountCurrencyDetailsList = [];
            this.accountCurrencyDetailsShow = true;
            this.selectCurrency = currency;
            this.accountCurrencyDetailsPage = 1;
            this.accountCurrencyDetailsTotal = 0;
            this.getAccountCurrencyDetailsList();
        },
        getAccountCurrencyDetailsList() { //获取账户币种交易明细数据
            this.loading = true;
            get(this.apiUrl + "/Api/QuantifyAccount/getAccountCurrencyDetailsList", {
                limit: this.accountCurrencyDetailsLimit,
                page: this.accountCurrencyDetailsPage,
                account_id: this.tabAccountId,
                currency: this.selectCurrency,
            }, json => {
                console.log(json.data);
                this.loading = false;
                if (json.code == 10000) {
                    this.accountCurrencyDetailsList = json.data.data;
                    this.accountCurrencyDetailsTotal = json.data.count;
                }
            })
        },
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
     .container {
        /deep/ {
            .el-breadcrumb {
                height: 25px;
                font-size: 10px;
            }
            .el-table {
                font-size: 10px;
            }
            .el-tabs__item {
                font-size: 10px;
            }
            .el-descriptions {
                margin-bottom: 20px;
                .el-descriptions__body {
                    padding: 20px;
                    border-radius: 20px;
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
