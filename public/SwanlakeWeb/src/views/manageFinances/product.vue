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
                    prop="annualized_income"
                    label="预期年化收益率"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.annualized_income || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <!-- <el-table-column
                    prop="total_size"
                    label="总份数"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_size || 0, 4) }}</span>
                    </template>
                </el-table-column> -->
                <el-table-column
                    prop="networth"
                    label="净值"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ keepDecimalNotRounding(scope.row.networth || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    label="总结余"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_balance ? scope.row.total_balance : Number(scope.row.total_size) * Number(scope.row.networth) || 0, 2) }} {{ scope.row.currency === 'BTCB' ? 'T' : scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="yest_income"
                    label="昨日收益"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span v-if="scope.row.is_hash">
                            {{ toFixed(scope.row.yest_income || 0, 2) }} USDT <br>
                            {{ toFixed(scope.row.yest_income_btcb || 0, 8) }} BTC
                        </span>
                        <span v-else>{{ toFixed(scope.row.yest_income || 0, 2) }} {{ scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="yest_income"
                    label="昨日收益率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.yest_income_rate || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="initial_deposit"
                    label="初始入金"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.initial_deposit || 0, 2) }} {{ scope.row.is_hash ? 'UDT' : scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    label="操作"
                    align="center"
                    width="200">
                    <template slot-scope="scope">
                        <el-button @click="showHashpowerDetail()" type="text" v-if="scope.row.is_hash">详情</el-button>
                        <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
                        <el-button type="text" @click="incomeClick(scope.row)" v-if="scope.row.name !== 'BTCS19Pro'">历史净值</el-button>
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
                    <el-descriptions-item label="预期年化收益率">{{ toFixed(item.annualized_income || 0, 2) }}%</el-descriptions-item>
                    <!-- <el-descriptions-item label="总份数">{{ toFixed(item.total_size || 0, 4) }}</el-descriptions-item> -->
                    <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="总结余">{{ toFixed(item.total_balance ? item.total_balance : Number(item.total_size) * Number(item.networth) || 0, 2) }} {{ item.currency === 'BTCB' ? 'T' : item.currency }}</el-descriptions-item>
                    <el-descriptions-item label="昨日收益">
                        <span v-if="item.is_hash">
                            {{ toFixed(item.yest_income || 0, 2) }} USDT <br>
                            {{ toFixed(item.yest_income_btcb || 0, 4) }} BTC
                        </span>
                        <span v-else>{{ toFixed(item.yest_income || 0, 2) }} {{ item.currency }}</span> 
                    </el-descriptions-item>
                    <el-descriptions-item label="昨日收益率">{{ toFixed(item.yest_income_rate || 0, 2) }}%</el-descriptions-item>
                    <el-descriptions-item label="初始入金">{{ toFixed(item.initial_deposit || 0, 2) }} {{ item.is_hash ? 'UDT' : item.currency }}</el-descriptions-item>
                    <el-descriptions-item>
                        <div class="operate">
                            <el-button size="mini" type="primary" @click="showHashpowerDetail()">详情</el-button>
                            <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                            <el-button type="text" @click="incomeClick(item)" v-if="item.name !== 'BTCS19Pro'">历史净值</el-button>
                        </div>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>

        <el-dialog
            title="算力规模"
            :visible.sync="hashpowerDetail"
            :width="isMobel ? '80%' : '50%'"
            center>
            <div class="info" v-if="poolBtcData">
            <el-row style="line-height:30px;">
                <el-col :span="12" align="center">
                    <span class="title">{{ $t('subscribe:Hashrate') }}</span>
                    <br>
                    <span> {{toFixed(Number(poolBtcData.power),3) || "--"}} EH/s</span> 
                </el-col>
                <el-col :span="12" align="center">
                    <span class="title">{{ $t('subscribe:CurrencyPrice') }}</span>
                    <br>
                    <span> $ {{toFixed(Number(poolBtcData.currency_price), 2) || "--"}}</span> 
                </el-col>
            </el-row>
            <br>
            <el-row style="line-height:30px;">
                <el-col :span="12" align="center">
                    <span class="title">{{ $t('subscribe:MinimumElectricityBill') }}</span><br>
                    <span>0.065 USDT</span> 
                </el-col>
                <el-col :span="12" align="center">
                    <span>
                        <span class="title">{{ $t('subscribe:DailyEarnings') }}/T </span><br>
                        <span>{{toFixed(Number(poolBtcData.daily_income), 4) || "--"}} USDT</span> 
                        <span>{{ toFixed(poolBtcData.daily_income / poolBtcData.currency_price, 8)}} BTC</span>
                    </span>
                </el-col>
            </el-row>
            <br>
            <el-row>
                <el-col :span="24">
                    <el-row style="line-height:30px;">
                        <el-col :span="isMobel ? 12 : 12" align="center">
                            <span class="title">{{ $t('subscribe:outputYesterday') }}</span><br /> 
                            <span>{{toFixed(Number(yester_output), 2) || "--"}} USDT</span>
                            <br>
                            <span>{{toFixed(Number(yester_output) / Number(poolBtcData.currency_price), 8)}} BTC</span>
                        </el-col>
                        <el-col :span="isMobel ? 12 : 12" align="center">
                            <span class="title">{{ $t('subscribe:cumulativeOutput') }}</span> <br /> 
                            <span>{{toFixed(Number(count_output), 2) || "--"}} USDT</span>
                            <br>
                            <span>{{toFixed(Number(count_output) / Number(poolBtcData.currency_price), 8) || "--"}} BTC</span>
                        </el-col>
                    </el-row>
                    <br>
                    <el-row style="line-height:30px;">
                        <el-col :span="isMobel ? 12 : 12" align="center">
                            <span class="title">{{ $t('subscribe:EstimatedElectricityCharge') }}/T </span><br /> 
                            <span>{{ estimatedElectricityCharge(poolBtcData) }} USDT</span>
                            <br>
                            <span>{{ dailyExpenditure(poolBtcData) }} BTC</span>
                        </el-col>
                        <el-col :span="isMobel ? 12 : 12" align="center">
                            <span class="title">{{ $t('subscribe:NetProfit') }}/T </span><br /> 
                            <span>{{ netProfit(poolBtcData) }} USDT</span>
                            <br>
                            <span></span>{{ netProfitBtcNumber(poolBtcData) }} BTC
                        </el-col>
                    </el-row>
                    <el-row style="line-height:30px;">
                        <el-col :span="isMobel ? 24 : 24" align="center">
                            <span class="title">{{ $t('subscribe:onlineDays') }} </span><br /> 
                            {{Number(online_days) || "--"}}</el-col>
                    </el-row>
                </el-col>
            </el-row>
            </div>
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
            timeInterval: null,
            refreshTime: 10000, //数据刷新间隔时间
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            PageSearchWhere: [], //分页搜索数组
            tableData: [],
            poolBtcData: {},
            loading: false,
            hashpowerDetail: false,

            count_output: 0,
            online_days: 0, //上线天数
            to_output: 0, //今日产出
            yester_output: 0, //昨日产出
            cost_revenue: 0, //收益成本
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
            hashPowerPoolsList:state=>state.base.hashPowerPoolsList,
        }),

    },
    created() {
        this.loading = true;
        this.getPoolBtcData();
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    if(!this.hashPowerPoolsList.length) {
                        this.$store.dispatch("getHashPowerPoolsList");
                    }
                    setTimeout(async() => {
                        
                        this.getPoolBtcData();

                        this.getOutputDetail();

                        this.getListData();

                        this.refreshData();

                        // console.log(this.hashPowerPoolsList);
                    }, 300);
                }
            },
        },
        hashPowerPoolsList: {
            immediate: true,
            async handler(val) {
                // console.log(val);
                await this.getListData();
            },
        },
        poolBtcData: {
            immediate: true,
            async handler(val) {
                if(val) {
                    this.getListData();
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
                this.$store.dispatch('refreshHashPowerPoolsList')
                await this.getPoolBtcData();
                await this.getOutputDetail();
                await this.getListData();
                // await this.getHashpowerData();
            }, this.refreshTime)
        },
        getListData(ServerWhere) {
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.pageSize,
                    page: that.currPage,
                };
            }
            get(this.apiUrl + "/Api/Product/getProductList", ServerWhere, async json => {
                console.log(json);
                if (json.code == 10000) {
                    let list = (json.data && json.data.lists) || [];
                    let hashpowerData = await this.getHashpowerData();
                    // console.log(hashpowerData);
                    if(hashpowerData && hashpowerData.length > 0) {
                        list = [...list, ...hashpowerData];
                    }
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
        async getHashpowerData(ServerWhere) { //获取算力币数据
            return new Promise(async (resolve, reject) => {
                var that = this.$data;
                if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                    ServerWhere = {
                        limit: that.pageSize,
                        page: that.currPage,
                    };
                }
                let list = [];
                this.hashPowerPoolsList.map((hashpowerObj, index) => {
                    list[index] = {...list[index], hashpowerObj};
                    let annualized_income = Number(hashpowerObj.daily_income) / Number(hashpowerObj.cost_revenue) * 365 * 100;//年化收益 = 日收益 / 收益成本 * 365 * 100
                    list[index]['id'] = hashpowerObj.id; 
                    list[index]['name'] = hashpowerObj.name; 
                    list[index]['annualized_income'] = annualized_income; 
                    list[index]['total_balance'] = hashpowerObj.total;
                    let yest_income_usdt = Number(hashpowerObj.total) * Number(hashpowerObj.daily_income);
                    let yest_income_btcb = Number(hashpowerObj.total) * (Number(hashpowerObj.daily_income) / Number(this.poolBtcData.currency_price));
                    list[index]['yest_income'] = yest_income_usdt;
                    list[index]['yest_income_btcb'] = yest_income_btcb;
                    // list[index]['yest_income_rate'] = yest_income_usdt > 0 ? (yest_income_usdt / Number(element.cost_revenue) * Number(hashpowerObj.total)) : 0; //昨日收益率 = 昨日收益 / 算力币价格 * 总数量
                    list[index]['yest_income_rate'] = annualized_income > 0 ? (annualized_income / 365) : 0; //昨日收益率 = 年化收益 / 365
                    list[index]['initial_deposit'] = Number(hashpowerObj.total) * Number(hashpowerObj.cost_revenue); //初始入金 = 总T数乘算力币价格
                    list[index]['is_hash'] = true;
                });
                console.log(list);
                resolve(list);
            })
        },
        async getOutputDetail() {
            axios.get(this.nftUrl + "/hashpower/hashpower/getHashpowerOutput",{
                params: {}
            }).then((json) => {
                console.log(json);
                if (json.code == 10000) {
                    this.count_output = json.data.count_output;
                    this.online_days = json.data.online_days;
                    this.to_output = json.data.to_output;
                    this.yester_output = json.data.yester_output;
                    this.cost_revenue = json.data.cost_revenue;
                    // this.detailData = json.data;
                } 
                else {
                    // this.$message.error("error");
                    console.log("get Data error");
                }
            }).catch((error) => {
                this.$message.error(error);
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
            if(row.name === 'BTCS19Pro') {
                this.$router.push({
                    path:'/hashpower/buy',
                    query: {
                        type: type,
                        hash_id: row.id,
                    }
                })
            } else {
                this.$router.push({
                    path:'/financial/currentDetail',
                    query: {
                        type: type,
                        product_id: row.id,
                    }
                })
            }
        },
        incomeClick(row) {
            this.$router.push({
                path:'/financial/productDetailsList',
                query: {
                    product_id: row.id,
                }
            })
        },
        showHashpowerDetail() { //查看详情
            this.hashpowerDetail = true;
        },
        estimatedElectricityCharge(item) { //预估电费->日支出 预估电费=29.55*0.065/美元币价
            // let num = (24 * 29.55 * 0.065) / item.currency_price;
            let num = 0.065 * 29.55 * 24 / 1000;
            return num.toFixed(4);
        },
        dailyExpenditure(item) { //日支出 BTC数量
            let num = this.estimatedElectricityCharge(item) / item.currency_price;
            return this.toFixed(num, 8);
        },
        netProfit(item) { //净收益 = 收益-电费
            let estimatedElectricityNum = this.estimatedElectricityCharge(item);
            let num = (item.daily_income - estimatedElectricityNum) * 0.95;
            if(num > 0) {
                return keepDecimalNotRounding(Number(keepDecimalNotRounding(num, 3)) + Number(0.001), 3);
            } else {
                return 0;
            }
        },
        netProfitBtcNumber(item) { // 净收益 BTC 数量
            let num = this.netProfit(item) / item.currency_price;
            if(num > 0) {
                return num.toFixed(8);
            } else {
                return 0;
            }
        },
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
