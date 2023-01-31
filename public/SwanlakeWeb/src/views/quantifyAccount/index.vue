<template>
    <div class="container">
        <div style="text-align:right;margin-bottom: 10px;">
            <el-button type="primary" @click="DepositWithdrawalShow()">出入金</el-button>
        </div>
        <el-tabs v-model="activeName" :tab-position="isMobel ? 'top' : 'left'" :stretch="isMobel ? true : false" style="background-color: #fff;" @tab-click="tabsHandleClick">
            <el-tab-pane id="1" label="MartinObserve" name="MartinObserve">
                <div v-if="!isMobel">
                    <el-table
                        :data="tableData"
                        style="width: 100%">
                        <el-table-column prop="date" label="日期" align="center"></el-table-column>
                        <el-table-column prop="principal" label="累计本金" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.principal, 2, true) }} USDT</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="总结余" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.total_balance, 2, true) }} USDT</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="币价" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.price, 2, true) }} USDT</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="日利润" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.daily_profit, 2, true) }} USDT</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="日利润率" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.daily_profit_rate, 4, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="平均日利率" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.average_day_rate, 4, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="平均年利率" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.average_year_rate, 4, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="利润" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.profit, 2, true) }} USDT</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="利润率" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.profit_rate * 100, 4, true) }}%</span>
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
                            <el-descriptions-item label="币价">{{ keepDecimalNotRounding(item.price, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="日利润">{{ keepDecimalNotRounding(item.daily_profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="日利润率">{{ keepDecimalNotRounding(item.daily_profit_rate, 4, true) }}</el-descriptions-item>
                            <el-descriptions-item label="平均日利率">{{ keepDecimalNotRounding(item.average_day_rate, 4, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="平均年利率">{{ keepDecimalNotRounding(item.average_year_rate, 4, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="利润">{{ keepDecimalNotRounding(item.profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="利润率">{{ keepDecimalNotRounding(item.profit_rate * 100, 4, true) }}%</el-descriptions-item>
                        </el-descriptions>
                    </div>
                    <div v-else>
                        <el-empty description="没有数据"></el-empty>
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

         <el-dialog
            title="出/入金"
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
            tabAccountId: 1,
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
        limitPaging(limit) {
            //赋值当前条数
            this.pageSize = limit;
            if (
                this.PageSearchWhere.limit &&
                this.PageSearchWhere.limit !== undefined
            ) {
                this.PageSearchWhere.limit = limit;
            }
            this.PageSearchWhere.account_id = this.tabAccountId;
            this.getList(this.PageSearchWhere); //刷新列表
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
            this.PageSearchWhere.account_id = this.tabAccountId;
            this.getList(this.PageSearchWhere); //刷新列表
        },
        copySuccess() {
            this.$message({
                message: 'Copy successfully!',
                type: 'success'
            });
        },
        submitForm(formName) {
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
        resetForm(formName) {
            this.$refs[formName].resetFields();
        },
        DepositWithdrawalShow() { //出入金 弹框显示\
            this.dialogVisibleShow = true;
        },
        tabsHandleClick(tab, event) { //tab切换
            // console.log(tab, event);
            this.tabAccountId = tab.id;
            this.getList();
        }
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
                font-size: 16px;
            }
            .el-table {
                font-size: 16px;
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
