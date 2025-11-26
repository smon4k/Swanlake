<template>
    <div class="container">

        <div class="commin-title">
            <div class="title-inner">
                <span class="tit">{{ $t('hashpower:StakingHashpowerCoin') }}</span>
                <!-- <div :class="[ 'btn' ]" @click="buyHashpower()">{{ $t('subscribe:BuyBTCS19Pro') }}</div> -->
            </div>
        </div>
        <div v-if="!isMobel">
            <el-table
                v-loading="loading"
                :data="filteredHashPowerPoolsList"
                style="width: 100%">
                <el-table-column
                    prop="name"
                    :label="$t('hashpower:ProductName')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <el-link type="primary" :href="scope.row.chain_address" target='_blank'>
                            <span>{{ scope.row.name }}</span><br>
                            <span>{{ scope.row.power_consumption_ratio }}W/THS</span>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:Price')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.price || 0, 2) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:Unit')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.hash_rate }} TH/s</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:Stock')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.stock }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:DailyOutput')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_output_usdt || 0, 2) }} USDT</span><br>
                        <span>{{ fromSATBTCNum(scope.row.daily_output_btc, 2) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:DailyExpenditure')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_expenditure_usdt || 0, 2) }} USDT</span><br>
                        <span>{{ fromSATBTCNum(scope.row.daily_expenditure_btc, 2) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('hashpower:DailyIncome')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_income_usdt || 0, 2) }} USDT</span><br>
                        <span>{{ fromSATBTCNum(scope.row.daily_income_btc, 2) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    :label="$t('hashpower:AnnualizedRate')"
                    align="center"
                    width="">
                    <template slot-scope="scope">
                        {{ toFixed(scope.row.annualized_rate || 0, 2) }}%
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    :label="$t('hashpower:Operation')"
                    align="center"
                    width="180">
                    <template slot-scope="scope">
                        <div>
                            <el-button size="" type="primary" round @click="hashpowerBuyClick(scope.row, 1)">{{ $t('hashpower:Buy') }}</el-button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <div v-else>
            <div v-if="filteredHashPowerPoolsList.length">
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in filteredHashPowerPoolsList" :key="index">
                    <el-descriptions-item :label="$t('hashpower:ProductName')">{{ item.name }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:Price')">{{ toFixed(item.price || 0, 2) }} USDT</el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:Unit')">{{ item.hash_rate }} TH/s</el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:Stock')">{{ item.stock }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:DailyOutput')">
                        {{ toFixed(item.daily_output_usdt || 0, 2) }} USDT /
                        {{ fromSATBTCNum(item.daily_output_btc, 2) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:DailyExpenditure')">
                        {{ toFixed(item.daily_expenditure_usdt || 0, 2) }} USDT /
                        {{ fromSATBTCNum(item.daily_expenditure_btc, 2) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:DailyIncome')">
                        {{ toFixed(item.daily_income_usdt || 0, 2) }} USDT /
                        {{ fromSATBTCNum(item.daily_income_btc, 2) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('hashpower:AnnualizedRate')">{{ toFixed(item.annualized_rate || 0, 2) }}%</el-descriptions-item>
                    <el-descriptions-item>
                        <div style="text-align:center;">
                            <el-button type="primary" @click="hashpowerBuyClick(item, 1)">{{ $t('hashpower:Buy') }}</el-button>
                        </div>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty :description="$t('hashpower:NoData')"></el-empty>
            </div>
        </div>
        
        <div style="float:right;color: #409EFF;">1 SAT = 0.00000001 BTC</div>

        <el-dialog
            :title="detailData.name + ' 算力规模'"
            :visible.sync="hashpowerPanelShow"
            :width="isMobel ? '80%' : '50%'"
            center>
            <div class="info" v-if="detailData">
                <el-row>
                  <el-col :span="24">
                      <el-row style="line-height:30px;">
                          <el-col :span="isMobel ? 12 : 12" align="center">
                              <span class="title">{{ $t('subscribe:outputYesterday') }}</span><br /> 
                              <span>{{toFixed(Number(detailData.yester_output), 4) || "--"}} USDT</span>
                              <br>
                              <span>{{ fromSATBTCNum(Number(detailData.yester_output_btc), 2)}}</span>
                          </el-col>
                          <el-col :span="isMobel ? 12 : 12" align="center">
                              <span class="title">{{ $t('subscribe:cumulativeOutput') }}</span> <br /> 
                              <span>{{toFixed(Number(detailData.count_output), 4) || "--"}} USDT</span>
                              <br>
                              <span>{{ fromSATBTCNum(Number(detailData.count_output_btc), 2) }}</span>
                          </el-col>
                      </el-row>
                      <br>
                      <el-row style="line-height:30px;">
                          <el-col :span="isMobel ? 12 : 12" align="center">
                              <span class="title">{{ $t('subscribe:EstimatedElectricityCharge') }}/T </span><br /> 
                              <span>{{ toFixed(detailData.daily_expenditure_usdt || 0, 4) }} USDT</span>
                              <br>
                              <span>{{ fromSATBTCNum(detailData.daily_expenditure_btc, 2) }}</span>
                          </el-col>
                          <el-col :span="isMobel ? 12 : 12" align="center">
                              <span class="title">{{ $t('subscribe:NetProfit') }}/T </span><br /> 
                              <span>{{ toFixed(detailData.daily_income_usdt || 0, 4) }} USDT</span>
                              <br>
                              <span></span>{{ fromSATBTCNum(detailData.daily_income_btc, 2) }}
                          </el-col>
                      </el-row>
                      <el-row style="line-height:30px;">
                          <el-col :span="isMobel ? 24 : 24" align="center">
                              <span class="title">{{ $t('subscribe:onlineDays') }} </span><br /> 
                              {{Number(detailData.online_days) || "--"}}</el-col>
                      </el-row>
                  </el-col>
              </el-row>
            </div>
        </el-dialog>

        <el-dialog :title="$t('hashpower:ChainRevenueQuery')" :visible.sync="dialogTableIncome" width="50%">
            <el-table :data="hashpowerIncomeList" v-loading="incomeLoading" max-height="500">
                <!-- <el-table-column type="index" width="50" :label="$t('hashpower:Operation')"></el-table-column> -->
                <el-table-column property="date" :label="$t('hashpower:Date')" align="center"></el-table-column>
                <el-table-column property="name" :label="$t('hashpower:Income')" align="center">
                    <template slot-scope="scope">
                        <span>
                            <!-- {{ toFixed(scope.row.income_usdt || 0, 6) }} USDT / {{ toFixed(scope.row.income_btc || 0, 10) }} BTC -->
                            {{ toFixed(scope.row.income_usdt || 0, 4) }} USDT / {{ fromSATBTCNum(scope.row.income_usdt / poolBtcData.transaction_price, 2)}}
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
import { getPoolBtcData, setStatiscData } from "@/wallet/serve";
import { keepDecimalNotRounding, fromSATBTCNum } from "@/utils/tools";
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
            count_btc_output: 0,
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
            totalPledgePower: 0,
            hashpowerPanelShow: false,
            detailData: {},
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
    deactivated() { //页面停用（keep-alive 停用时）
        if (this.timeInterval) {
            clearInterval(this.timeInterval);
            this.timeInterval = null;
        }
    },
    beforeDestroy() { //组件销毁时
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
            hashPowerPoolsList:state=>state.base.hashPowerPoolsList
        }),
        filteredHashPowerPoolsList() {
            return this.hashPowerPoolsList.filter(item => item.hashpowerAddress);
        }

    },
    created() {
        setTimeout(async() => {
            await setStatiscData(1);
        },300)
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    this.refreshData();
                    if(val && !this.hashPowerPoolsList.length) {
                        setTimeout(async() => {
                            this.$store.dispatch("getHashPowerPoolsList");
                            
                            this.getPoolBtcData();

                            this.getOutputDetail();

                            // console.log(this.hashPowerPoolsList);
                        }, 300);
                    }
                }
            },
        },
        hashPowerPoolsList: {
            immediate: true,
            handler(val) {
                if(val) {
                    console.log(val);
                    let totalTvl = 0;
                    val.forEach(element => {
                        totalTvl += Number(element.total);
                    });
                    this.totalPledgePower = totalTvl;
                }
            },
            deep: true
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
            // 先清除已存在的定时器，防止重复创建
            if (this.timeInterval) {
                clearInterval(this.timeInterval);
            }
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
                    this.count_btc_output = json.data.count_btc_output;
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
        estimatedElectricityCharge(item) { //预估电费->日支出 预估电费=29.55*0.07/美元币价
            // let num = (24 * 29.55 * 0.07) / item.transaction_price;
            let num = item.electricity_price * item.power_consumption_ratio * 24 / 1000;
            return num.toFixed(4);
        },
        dailyExpenditure(item) { //日支出 BTC数量
            let num = this.estimatedElectricityCharge(item) / item.transaction_price;
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
            let num = this.netProfit(item) / item.transaction_price;
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
                hashId: item.id,
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
            // this.$router.push({
            //     path:'/hashpower/buy',
            //     query: {
            //         type: type,
            //         hash_id: row.id,
            //     }
            // })
            this.$router.push({
                name:'hashpowerBuy',
                params: {
                    type: type,
                    hash_id: row.id,
                    hashpowerAddress: row.hashpowerAddress,
                    functionName: row.functionName
                }
            })
        },
    },
}
</script>
<style lang="scss" scoped>
    .container {
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
                font-size: 13px;  
                color: #fff;      
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
</style>
