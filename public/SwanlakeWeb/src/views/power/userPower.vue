<template>
    <div class="container">
        <div v-if="!isMobel">
            <el-table
                v-loading="loading"
                :data="tableData"
                style="width: 100%">
                <el-table-column
                    prop="name"
                    label="产品名称"
                    align="center"
                    width="150">
                </el-table-column>
                <el-table-column
                    prop="amount"
                    label="数量"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.amount || 0, 2) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="total_quota"
                    label="金额"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_quota || 0, 2) }}</span> USDT
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="购买时间"
                    align="center"
                    width="180">
                    <template slot-scope="scope">
                        <span>{{ scope.row.add_time }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="到期时间"
                    align="center"
                    width="180">
                    <template slot-scope="scope">
                        <span>{{ scope.row.expire_date }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="收益"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ keepDecimalNotRounding(scope.row.income, 6, true) }} BTCB</span>
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    label="状态"
                    align="center">
                    <template slot-scope="scope">
                        <span v-if="scope.row.state == 1">有效</span>
                        <span v-else>失效</span>
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    label="操作"
                    align="center">
                    <template slot-scope="scope">
                        <el-button size="mini" round @click="showIncome(scope.row, 1)">查看收益</el-button>
                        <!-- <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button> -->
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
                    <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                    <el-descriptions-item label="数量">{{ toFixed(item.amount || 0, 2) }}</el-descriptions-item>
                    <el-descriptions-item label="金额">{{ toFixed(item.total_quota || 0, 2) }} USDT</el-descriptions-item>
                    <el-descriptions-item label="购买时间">{{ item.add_time }}</el-descriptions-item>
                    <el-descriptions-item label="收益">{{ keepDecimalNotRounding(item.income, 6, true) }} BTCB</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <span v-if="item.state == 1">有效</span>
                        <span v-else>失效</span>
                    </el-descriptions-item>
                    <el-descriptions-item>
                        <div class="operate">
                            <el-button size="mini" round @click="showIncome(item, 1)">查看收益</el-button>
                            <!-- <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button> -->
                        </div>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>

        <el-dialog title="7日收益" :visible.sync="dialogTableIncome" :width="isMobel ? '90%' : '50%'">
            <el-table :data="hashpowerIncomeList" v-loading="incomeLoading" max-height="500">
                <!-- <el-table-column type="index" width="50" label="ID"></el-table-column> -->
                <el-table-column property="date" label="日期" align="center"></el-table-column>
                <el-table-column property="name" label="收益" align="center">
                    <template slot-scope="scope">
                        <span>
                            {{ toFixed(scope.row.btcb_amount || 0, 6) }} BTCB
                            <!-- {{ toFixed(scope.row.income_usdt || 0, 4) }} USDT / {{ fromSATBTCNum(scope.row.income_usdt / poolBtcData.currency_price, 2)}} -->
                        </span>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
    </div>
</template>
<script>
import Page from "@/components/Page.vue";
import { get, post, upload } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getPoolBtcData } from "@/wallet/serve";
import { keepDecimalNotRounding } from "@/utils/tools";
export default {
    name: '',
    data() {
        return {
            activeName: '1',
            timeInterval: null,
            refreshTime: 100000, //数据刷新间隔时间
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            PageSearchWhere: [], //分页搜索数组
            tableData: [],
            poolBtcData: {},
            loading: false,

            hashpowerIncomeList: [],
            incomeLoading: false,
            dialogTableIncome: false,
            currIncomePage: 1, //当前页
            pageIncomeSize: 20, //每页显示条数
            totalIncome: 100, //总条数
            PageIncomeSearchWhere: [],
        }
    },
    activated() { //页面进来
        this.refreshData();
    },
    beforeRouteLeave(to, from, next){ //页面离开
        next();
        if (this.timeInterval) {
            clearInterval(this.timeInterval);
            this.timeInterval = null;
        }
    },
    computed: {
        ...mapState({
            address:state=>state.base.address,
            isConnected:state=>state.base.isConnected,
            isMobel:state=>state.comps.isMobel,
            mainTheme:state=>state.comps.mainTheme,
            apiUrl:state=>state.base.apiUrl,
            nftUrl:state=>state.base.nftUrl,
        }),

    },
    created() {
        this.loading = true;
        // this.getPoolBtcData();
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    setTimeout(async() => {
                        
                        // this.getPoolBtcData();

                        this.getListData();

                        this.refreshData();

                    }, 300);
                }
            },
        },
        poolBtcData: {
            immediate: true,
            async handler(val) {
                if(val) {
                }
            },
        }
    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    methods: {
        refreshData() { //定时刷新数据
            this.timeInterval = setInterval(async () => {
                // await this.getPoolBtcData();
                await this.getListData();
            }, this.refreshTime)
        },
        getListData(ServerWhere) {
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.pageSize,
                    page: that.currPage,
                    address: this.address
                };
            }
            get(this.apiUrl + "/Power/Power/getUserPowerList", ServerWhere, async json => {
                console.log(json);
                if (json.code == 10000) {
                    let list = (json.data && json.data.lists) || [];
                    // console.log(list);
                    this.tableData = list;
                    this.loading = false;
                    this.total = json.data.count;
                    this.$forceUpdate();
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        async getPoolBtcData() { //获取BTC爬虫数据
            let data = await getPoolBtcData();
            if(data && data.length > 0) {
                this.poolBtcData = data[0];
            }
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
        buyClick(row, type) {
            console.log(row);
            this.$router.push({
                name:'powerBuy',
                params: {
                    type: type,
                    hash_id: row.id,
                }
            })
        },
        showIncome(row) { //查看收益
            console.log(row);
            this.dialogTableIncome = true;
            var SearchWhere = {
                page: this.currIncomePage,
                limit: this.pageIncomeSize,
                address: this.address,
                user_power_id: row.id,
                hash_id: row.hash_id
            };
            this.PageIncomeSearchWhere = [];
            this.PageIncomeSearchWhere = SearchWhere;
            this.getPowerDailyincomeList(this.PageIncomeSearchWhere); //刷新列表
        },
        getPowerDailyincomeList(ServerWhere) { //获取收益列表
            var that = this.$data;
            this.incomeLoading = true;
            get(this.apiUrl + "/Power/power/getPowerDailyincomeList", ServerWhere, async json => {
                console.log(json);
                if (json.code == 10000) {
                    let list = (json.data && json.data.lists) || [];
                    // console.log(list);
                    this.hashpowerIncomeList = list;
                    this.incomeLoading = false;
                    this.totalIncome = json.data.count;
                    this.$forceUpdate();
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        handleClick(tab, event) {
            console.log(tab, event);
        }
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
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
            .info {
                .title {
                    font-weight: 800;
                }
            }
        }
    }
</style>
