<template>
    <div class="container">



        <!-- 算力币信息 -->
        <el-card class="box-card">
            <!-- PC -->
            <el-row class="public-info" v-if="!isMobel">
                <el-col :span="24" style="float:right;">
                    <span>
                        {{ $t('subscribe:MinimumElectricityBill') }}
                        <span>0.065 USDT</span> 
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span>
                        {{ $t('subscribe:DailyEarnings') }}/T
                        <span>{{toFixed(Number(poolBtcData.daily_income), 4) || "--"}} USDT</span> 
                        <span>{{ toFixed(poolBtcData.daily_income / poolBtcData.currency_price, 8)}} BTC</span>
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span>
                        {{ $t('subscribe:Hashrate') }}
                        <span> {{toFixed(Number(poolBtcData.power),3) || "--"}} EH/s</span> 
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span>
                        {{ $t('subscribe:CurrencyPrice') }}
                        <span> $ {{toFixed(Number(poolBtcData.currency_price), 2) || "--"}}</span> 
                    </span>
                </el-col>
            </el-row>
            <!-- 移动 -->
            <el-row style="line-height:20px;font-size:8px;" v-else>
                <el-col :span="6" align="center">{{ $t('subscribe:DailyEarnings') }}/T<br /> 
                    <span>{{toFixed(Number(poolBtcData.daily_income), 4) || "--"}} USDT</span> 
                    <br> 
                    <span>{{ toFixed(poolBtcData.daily_income / poolBtcData.currency_price, 8)}} BTC</span>
                </el-col>
                <el-col :span="6" align="center">{{ $t('subscribe:Hashrate') }}<br /> {{toFixed(Number(poolBtcData.power),3) || "--"}} EH/s</el-col>
                <el-col :span="6" align="center">{{ $t('subscribe:CurrencyPrice') }}<br /> $ {{toFixed(Number(poolBtcData.currency_price), 2) || "--"}}</el-col>
                <el-col :span="6" align="center">{{ $t('subscribe:MinimumElectricityBill') }}<br /> 0.065 USDT</el-col>
            </el-row>

            <div :class="[isMobel ? 'model-info' : 'info']" v-if="poolBtcData">
                <el-row style="line-height:30px;">
                    <el-col :span="isMobel ? 12 : 6" align="center">{{ $t('subscribe:outputYesterday') }}<br /> 
                        <span>{{toFixed(Number(yester_output), 2) || "--"}} USDT</span>
                        <br>
                        <span>{{toFixed(Number(yester_output) / Number(poolBtcData.currency_price), 8)}} BTC</span>
                    </el-col>
                    <el-col :span="isMobel ? 12 : 6" align="center">{{ $t('subscribe:cumulativeOutput') }}<br /> 
                        <span>{{toFixed(Number(count_output), 2) || "--"}} USDT</span>
                        <br>
                        <span>{{toFixed(Number(count_output) / Number(poolBtcData.currency_price), 8) || "--"}} BTC</span>
                    </el-col>
                    <el-col :span="isMobel ? 12 : 6" align="center">总质押算力<br /> 
                        <span>{{toFixed(Number(totalPledgePower), 2) || "--"}} T</span>
                    </el-col>
                    <!-- <el-col :span="isMobel ? 8 : 5" align="center">{{ $t('subscribe:EstimatedElectricityCharge') }}/T<br /> 
                        <span>{{ toFixed(daily_expenditure_usdt || 0, 4) }} USDT</span>
                        <br>
                        <span>{{ toFixed(daily_expenditure_btc || 0, 8) }} BTC</span>
                    </el-col>
                    <el-col :span="isMobel ? 12 : 5" align="center">{{ $t('subscribe:NetProfit') }}/T<br /> 
                        <span>{{ toFixed(daily_income_usdt || 0, 3) }} USDT</span>
                        <br>
                        <span></span>{{ toFixed(daily_income_btc || 0, 8) }} BTC
                    </el-col> -->
                    <el-col :span="isMobel ? 12 : 6" align="center">{{ $t('subscribe:onlineDays') }}<br /> {{Number(online_days) || "--"}}</el-col>
                </el-row>
            </div>
        </el-card>

        <div class="commin-title">
            <div class="title-inner">
                <span class="tit">质押算力币获取BTC收益</span>
                <!-- <div :class="[ 'btn' ]" @click="buyHashpower()">{{ $t('subscribe:BuyBTCS19Pro') }}</div> -->
            </div>
        </div>
        <div v-if="!isMobel">
            <el-table
                v-loading="loading"
                :data="hashPowerPoolsList"
                style="width: 100%">
                <el-table-column
                    prop="name"
                    label="产品名称"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="annualized_income"
                    label="年化利率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.annualized_income || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    label="总质押算力"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total || 0, 4) }} {{ scope.row.currency === 'BTCB' ? 'T' : scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    label="我的质押"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.balance || 0, 4) }} {{ scope.row.currency === 'BTCB' ? 'T' : scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="昨日收益"
                    align="center">
                    <template slot-scope="scope">
                        <span>
                            {{ toFixed(scope.row.yest_income_usdt || 0, 6) }} USDT <br>
                            {{ toFixed(scope.row.yest_income_btcb || 0, 10) }} BTC
                        </span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="总收益"
                    align="center">
                    <template slot-scope="scope">
                        <el-link type="primary" @click="showHashpowerIncomeList(scope.row.id)">
                            {{ toFixed(scope.row.total_income_usdt || 0, 6) }} USDT <br>
                            {{ toFixed(scope.row.total_income_btcb || 0, 10) }} BTC
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    label="操作"
                    align="center"
                    width="230">
                    <template slot-scope="scope">
                        <div>
                            <el-button size="mini" round @click="hashpowerBuyClick(scope.row, 1)">购买</el-button>
                            <el-button size="mini" round @click="receiveBTCBReward(scope.row)" :loading="receiveLoading" :disabled="!Number(scope.row.btcbReward)">收获</el-button>
                        </div>
                        <div style="margin-top:5px">
                            <el-button size="mini" round @click="toHashpowerDetail(1, scope.row)">存入</el-button>
                            <el-button size="mini" round @click="toHashpowerDetail(2, scope.row)" >提取</el-button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <div v-else>
            <div v-if="hashPowerPoolsList.length">
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in hashPowerPoolsList" :key="index">
                    <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                    <el-descriptions-item label="年化利率">{{ toFixed(item.annualized_income || 0, 2) }}%</el-descriptions-item>
                    <!-- <el-descriptions-item label="总份数">{{ toFixed(item.total_size || 0, 4) }}</el-descriptions-item> -->
                    <el-descriptions-item label="总质押算力">{{ toFixed(item.total || 0, 2) }} {{ item.currency === 'BTCB' ? 'T' : item.currency }}</el-descriptions-item>
                    <el-descriptions-item label="我的质押">{{ toFixed(item.balance || 0, 2) }} {{ item.currency === 'BTCB' ? 'T' : item.currency }}</el-descriptions-item>
                    <el-descriptions-item label="昨日收益">
                        <span>
                            {{ toFixed(item.yest_income_usdt || 0, 6) }} USDT <br>
                            {{ toFixed(item.yest_income_btcb || 0, 10) }} BTC
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item label="总收益">
                        <span>
                            {{ toFixed(item.total_income_usdt || 0, 6) }} USDT <br>
                            {{ toFixed(item.total_income_btcb || 0, 10) }} BTC
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item>
                        <div style="text-align:center;">
                            <el-button size="mini" type="primary" @click="hashpowerBuyClick(scope.row, 1)">购买</el-button>
                            <el-button size="mini" type="primary" @click="receiveBTCBReward(item)" :loading="receiveLoading" :disabled="!Number(item.btcbReward)">收获</el-button>
                            <el-button size="mini" type="primary" @click="toHashpowerDetail(1, item)">存入</el-button>
                            <el-button size="mini" type="primary" @click="toHashpowerDetail(2, item)">提取</el-button>
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

        <el-dialog title="历史收益" :visible.sync="dialogTableIncome" width="50%">
            <el-table :data="hashpowerIncomeList" v-loading="incomeLoading" max-height="500">
                <!-- <el-table-column type="index" width="50" label="ID"></el-table-column> -->
                <el-table-column property="date" label="日期" align="center"></el-table-column>
                <el-table-column property="name" label="收益" align="center">
                    <template slot-scope="scope">
                        <span>
                            {{ toFixed(scope.row.income_usdt || 0, 6) }} USDT / {{ toFixed(scope.row.income_btc || 0, 10) }} BTC
                        </span>
                    </template>
                </el-table-column>
            </el-table>
            <el-row class="pages" v-if="totalIncome > pageIncomeSize">
                <el-col :span="24">
                    <div style="float:right;">
                    <wbc-page
                        :total="totalIncome"
                        :pageSize="pageIncomeSize"
                        :currPage="currIncomePage"
                        @changeLimit="limitIncomePaging"
                        @changeSkip="skipIncomePaging"
                    ></wbc-page>
                    </div>
                </el-col>
            </el-row>
        </el-dialog>
    </div>
</template>
<script>
import Page from "@/components/Page.vue";
import { get, post } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getPoolBtcData } from "@/wallet/serve";
import { keepDecimalNotRounding } from "@/utils/tools";
import { depositPoolsIn } from "@/wallet/trade";
export default {
    name: '',
    data() {
        return {
            activeName: '1',
            timeInterval: null,
            refreshTime: 10000, //数据刷新间隔时间
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            PageSearchWhere: [], //分页搜索数组
            
            hashpowerIncomeList: [],
            incomeLoading: false,
            currIncomePage: 1, //当前页
            pageIncomeSize: 20, //每页显示条数
            totalIncome: 100, //总条数
            PageIncomeSearchWhere: [],

            poolBtcData: {},
            loading: false,
            hashpowerDetail: false,

            count_output: 0,
            online_days: 0, //上线天数
            to_output: 0, //今日产出
            yester_output: 0, //昨日产出
            cost_revenue: 0, //收益成本
            daily_expenditure_usdt: 0,
            daily_expenditure_btc: 0,
            daily_income_usdt: 0,
            daily_income_btc: 0,
            receiveLoading: false,
            dialogTableIncome: false,
        }
    },
    activated() { //页面进来
        this.refreshData();
    },
    mounted() {
        setTimeout(()=>{
            this.getPoolBtcData();
            this.getOutputDetail();
        } , 300)
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
            totalPledgePower:state=>state.base.totalPledgePower,
        }),

    },
    created() {
        // this.loading = true;
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    if(val && !this.hashPowerPoolsList.length) {
                        setTimeout(async() => {
                            this.$store.dispatch("getHashPowerPoolsList");
                            
                            this.getPoolBtcData();


                            this.refreshData();

                            this.getOutputDetail();

                            // console.log(this.hashPowerPoolsList);
                        }, 300);
                    }
                }
            },
        },
        hashPowerPoolsList: {
            immediate: true,
            async handler(val) {
                console.log(val);
            },
        },
        poolBtcData: {
            immediate: true,
            async handler(val) {
                if(val) {
                }
            },
        },
        totalPledgePower: {
            immediate: true,
            handler(val) {
                console.log(val);
            }
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
            }, this.refreshTime)
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
                    this.daily_expenditure_usdt = json.data.daily_expenditure_usdt;
                    this.daily_expenditure_btc = json.data.daily_expenditure_btc;
                    this.daily_income_usdt = json.data.daily_income_usdt;
                    this.daily_income_btc = json.data.daily_income_btc;
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
        showHashpowerIncomeList(hash_id) { //查看收益数据
            // console.log(hash_id);
            this.PageIncomeSearchWhere.hashId = hash_id;
            this.currIncomePage = 1; //当前页
            this.pageIncomeSize = 20; //每页显示条数
            var SearchWhere = {
                page: this.currIncomePage,
                limit: this.pageIncomeSize,
                address: this.address
            };
            if (hash_id && hash_id > 0) {
                SearchWhere["hashId"] = hash_id;
            }
            this.PageIncomeSearchWhere = [];
            this.PageIncomeSearchWhere = SearchWhere;
            this.getHashpowerDailyincomeList(this.PageIncomeSearchWhere); //刷新列表
            this.dialogTableIncome = true;
        },
        getHashpowerDailyincomeList(ServerWhere) { //获取产品日收益列表
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.pageIncomeSize,
                    page: that.currIncomePage,
                    // hashId: this.hashId,
                    // address: this.address,
                };
            }
            this.incomeLoading = true;
            get(this.nftUrl + "/Hashpower/Hashpower/getHashpowerDailyincomeList", ServerWhere, async json => {
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
        limitIncomePaging(limit) { //日收益分页
            //赋值当前条数
            this.pageIncomeSize = limit;
            if (
                this.PageIncomeSearchWhere.limit &&
                this.PageIncomeSearchWhere.limit !== undefined
            ) {
                this.PageIncomeSearchWhere.limit = limit;
            }
            this.getHashpowerDailyincomeList(this.PageIncomeSearchWhere); //刷新列表
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
        skipIncomePaging(page) { //日收益分页
            //赋值当前页数
            this.currIncomePage = page;
            if (
                this.PageIncomeSearchWhere.page &&
                this.PageIncomeSearchWhere.page !== undefined
            ) {
                this.PageIncomeSearchWhere.page = page;
            }
            this.getHashpowerDailyincomeList(this.PageIncomeSearchWhere); //刷新列表
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
            let num = item.electricity_price * item.power_consumption_ratio * 24 / 1000;
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
        handleClick(tab, event) {
            console.log(tab, event);
        },
        toHashpowerDetail(type, item) {
            //type 1=>存入 2=>提取
            console.log(item);
            if (!item.goblin)
                return this.$notify.error({
                    message: "Failed to get data, please refresh and try again",
                    duration: 6000,
                });
            let query = {
                hashId: item.hash_id,
                pId: item.pId,
                currencyToken: item.currencyToken,
                goblin: item.goblin,
                decimals: item.decimals_h,
                name: item.name,
            };
            sessionStorage.setItem("hashpowerPoolsDetailInfo", JSON.stringify(query));
            this.$router.push({
                path: "/hashpower/detail",
                query: {
                    type: type,
                },
            });
        },
        async receiveBTCBReward(item) { //收获BTCB
            console.log("item", item)
            // if (!Number(item.btcbReward)) return;
            this.$store.commit("sethashPowerPoolsListClaimLoading", {
                goblin: item.goblin,
                val: true,
            });
            this.receiveLoading = true;

            depositPoolsIn(
                item.goblin,
                item.decimals,
                0,
                item.pId
            ).then(async(hash) => {
                let setHarvest = await this.setHashpowerHarvest(item.id, item.btcbReward, item.currency, hash);
                if(setHarvest) {
                    this.$message({
                        type: 'success',
                        message: 'Success!'
                    });
                    this.$store.dispatch("getHashPowerPoolsList");
                    this.receiveLoading = false;
                }
            }).finally(() => {
                this.$store.commit("sethashPowerPoolsListClaimLoading", {
                    goblin: item.goblin,
                    val: false,
                });
                this.receiveLoading = false;
            });
        },
        async setHashpowerHarvest(hashId, amount, currency, hash) {
            return new Promise(async (resolve, reject) => {
                post(this.nftUrl + '/hashpower/Hashpower/setHashpowerHarvest', { 
                    address: this.address, 
                    hashId: hashId, 
                    amount: amount,
                    hash: hash,
                    currency: currency
                }, (json) => {
                    console.log(json);
                    if (json && json.code == 10000) {
                        resolve(true);
                    } else {
                        resolve(false);
                    }
                })
            })
        },
        hashpowerBuyClick(row, type) {
            this.$router.push({
                path:'/hashpower/buy',
                query: {
                    type: type,
                    hash_id: row.id,
                }
            })
        },
    },
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            .box-card {
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 20px;
                .el-card__body {
                    padding: 0;
                }
                .public-info {
                    padding: 10px;
                    font-size: 10px;
                    text-align: right;
                    margin-right: 30px;
                }
                .info {
                    padding: 30px;
                    .el-divider {
                        background-color: rgba(255,255,255,0.1);
                        width: 80%;
                        // margin: 0 auto;
                        top: 10px;
                    }
                } 
                .model-info {
                    margin-top: 10px;
                    font-size: 13px;
                    padding: 0;
                }
            }
            .commin-title {
                margin-bottom: 10px;
                .btn {
                    display: inline-block;
                    padding: 0 17px;
                    height: 30px;
                    background: linear-gradient(90deg, #0096FF, #0024FF);
                    color: #fff;
                    border-style: solid;
                    border-width: 1px;
                    border-radius: 15px;
                    line-height: 28px;
                    vertical-align: middle;
                    margin-left: 8px;
                    cursor: pointer;
                    position: relative;
                    box-sizing: border-box;
                }
                .tit {
                    padding-right: 14px;
                    display: inline-block;
                    font-weight: 800;
                    font-size: 16px;        
                }
            }
            .el-table {
                border-radius: 20px;
                font-size: 16px;
                .cell {
                    padding-left: unset;
                    button {
                        color: #409EFF;
                        border-color: #409EFF;
                    }
                    button.is-disabled {
                        color: #C0C4CC;
                        border-color: #EBEEF5;
                    }
                    .el-link {
                        font-size: 16px;
                    }
                }
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
            .el-dialog__body {
                padding-top: 0;
                .el-table .el-table__cell {
                    padding: 10px 0;
                }
            }
        }
    }
</style>
