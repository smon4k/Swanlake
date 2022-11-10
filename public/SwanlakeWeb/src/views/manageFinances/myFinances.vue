<template>
    <div class="container">
        <el-tabs v-model="activeName" @tab-click="handleClick" type="border-card">
            <el-tab-pane label="天鹅湖" name="1">
                <div v-if="!isMobel">
                    <el-table
                        v-loading="loading"
                        :data="tableData"
                        style="width: 100%">
                        <el-table-column
                            prop="name"
                            label="产品名称"
                            align="center">
                        </el-table-column>
                        <el-table-column
                            prop="total_balance"
                            label="总结余"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_balance || 0, 4) }} {{scope.row.currency}}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_number"
                            label="购买份数"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_number || 0, 2) }}</span>
                            </template>
                        </el-table-column>
                        <!-- <el-table-column
                            prop="time"
                            label="购买时间"
                            align="center"
                            width="200">
                        </el-table-column> -->
                        <!-- <el-table-column
                            prop="networth"
                            label="净值"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.networth || 0, 4) }}</span>
                            </template>
                        </el-table-column> -->
                        <el-table-column
                            prop="yest_income"
                            label="昨日收益"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.yest_income || 0, 2) }} {{ scope.row.currency}}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_income"
                            label="总收益"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_income || 0, 2) }} {{ scope.row.currency}}</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_rate"
                            label="总收益率"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_rate || 0, 2) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="year_rate"
                            label="年化收益率"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.year_rate || 0, 2) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                        fixed="right"
                        label="操作"
                        align="center"
                        width="180">
                        <template slot-scope="scope">
                            <div>
                                <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
                                <el-button type="text" @click="buyClick(scope.row, 2)">赎回</el-button>
                                <el-button type="text" @click="incomeClick(scope.row)">历史净值</el-button>
                            </div>
                        </template>
                        </el-table-column>
                    </el-table>
                    <!-- v-if="total > pageSize" -->
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
                    <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                        <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                        <el-descriptions-item label="总结余">{{ toFixed(item.total_balance || 0, 4) }} {{item.currency}}</el-descriptions-item>
                        <el-descriptions-item label="购买份数">{{ toFixed(item.total_number || 0, 2) }}</el-descriptions-item>
                        <!-- <el-descriptions-item label="购    买时间">{{ item.time }}</el-descriptions-item> -->
                        <!-- <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item> -->
                        <el-descriptions-item label="昨日收益">
                                <span>{{ toFixed(item.yest_income || 0, 2) }} {{ item.currency }}</span> 
                            </el-descriptions-item>
                        <el-descriptions-item label="总收益">
                            <span>{{ toFixed(item.total_income || 0, 2) }} {{ item.currency }}</span>
                        </el-descriptions-item>
                        <el-descriptions-item label="总收益率">{{ toFixed(item.total_rate || 0, 2) }}%</el-descriptions-item>
                        <el-descriptions-item label="年化收益率">{{ toFixed(item.year_rate || 0, 2) }}%</el-descriptions-item>
                        <el-descriptions-item>
                            <div class="operate">
                                <div>
                                    <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                                    <el-button size="mini" type="primary" @click="buyClick(item, 2)">赎回</el-button>
                                    <el-button size="mini" type="primary" @click="incomeClick(item)">历史净值</el-button>
                                </div>
                            </div>
                        </el-descriptions-item>
                    </el-descriptions>
                </div>
            </el-tab-pane>
            <el-tab-pane label="算力币" name="2">
                <div v-if="!isMobel">
                    <el-table
                        v-loading="loading"
                        :data="hashpowerList"
                        style="width: 100%">
                        <el-table-column
                            prop="name"
                            label="产品名称"
                            align="center"
                            width="130">
                        </el-table-column>
                        <el-table-column
                            prop="total_balance"
                            label="总结余"
                            align="center"
                            width="180">
                            <template slot-scope="scope">
                                <span v-if="scope.row.is_hash">钱包余额: {{ scope.row.btcb19ProBalance > 0 ? toFixed(scope.row.btcb19ProBalance || 0, 4) : 0}} T</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_number"
                            label="购买份数"
                            align="center"
                            width="100">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_number || 0, 2) }}</span>
                            </template>
                        </el-table-column>
                        <!-- <el-table-column
                            prop="time"
                            label="购买时间"
                            align="center"
                            width="200">
                        </el-table-column> -->
                        <!-- <el-table-column
                            prop="networth"
                            label="净值"
                            align="center">
                            <template slot-scope="scope">
                                <span>{{ keepDecimalNotRounding(scope.row.networth || 0, 4) }}</span>
                            </template>
                        </el-table-column> -->
                        <el-table-column
                            prop="yest_income"
                            label="昨日收益"
                            align="center"
                            width="180">
                            <template slot-scope="scope">
                                <span>
                                    {{ scope.row.yest_income ? toFixed(scope.row.yest_income || 0, 6) : 0 }} USDT <br>
                                    {{ scope.row.yest_income_btcb ? toFixed(scope.row.yest_income_btcb || 0, 10) : 0 }} BTC
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_income"
                            label="总收益"
                            align="center"
                            width="180">
                            <template slot-scope="scope">
                                <span>
                                    {{ scope.row.total_income_usdt ? toFixed(scope.row.total_income_usdt || 0, 6) : 0 }} USDT <br>
                                    {{ scope.row.total_income_btcb ? toFixed(scope.row.total_income_btcb || 0, 10) : 0 }} BTC
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="total_rate"
                            label="总收益率"
                            align="center"
                            width="100">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.total_rate || 0, 2) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                            prop="year_rate"
                            label="年化收益率"
                            align="center"
                            width="100">
                            <template slot-scope="scope">
                                <span>{{ toFixed(scope.row.year_rate || 0, 2) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column
                        fixed="right"
                        label="操作"
                        align="center"
                        width="230">
                        <template slot-scope="scope">
                            <div>
                                <el-button @click="HashpowerBuyClick(scope.row, 1)" type="text">购买</el-button>
                                <el-button @click="receiveBTCBReward(scope.row)" type="text" :loading="receiveLoading" :disabled="!Number(scope.row.btcbReward)">收获{{scope.row.currency}}</el-button>
                                <el-button @click="toHashpowerDetail(1, scope.row)" type="text">存入</el-button>
                                <el-button type="text" @click="toHashpowerDetail(2, scope.row)" >提取</el-button>
                            </div>
                        </template>
                        </el-table-column>
                    </el-table>
                    <!-- v-if="total > pageSize" -->
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
                    <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in hashpowerList" :key="index">
                        <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                        <el-descriptions-item label="总结余">{{ toFixed(item.total_balance || 0, 4) }} {{item.currency === 'BTCB' ? 'T' : item.currency}}</el-descriptions-item>
                        <el-descriptions-item label="购买份数">{{ toFixed(item.total_number || 0, 2) }}</el-descriptions-item>
                        <!-- <el-descriptions-item label="购    买时间">{{ item.time }}</el-descriptions-item> -->
                        <!-- <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item> -->
                        <el-descriptions-item label="昨日收益">
                                <span>
                                    {{ item.yest_income ? toFixed(item.yest_income || 0, 6) : 0 }} USDT <br>
                                    {{ item.yest_income_btcb ? toFixed(item.yest_income_btcb || 0, 10) : 0 }} BTC
                                </span>
                            </el-descriptions-item>
                        <el-descriptions-item label="总收益">
                            <span>
                                {{ item.total_income_usdt ? toFixed(item.total_income_usdt || 0, 6) : 0 }} USDT <br>
                                {{ item.total_income_btcb ? toFixed(item.total_income_btcb || 0, 10) : 0 }} BTC
                            </span>
                        </el-descriptions-item>
                        <el-descriptions-item label="总收益率">{{ toFixed(item.total_rate || 0, 2) }}%</el-descriptions-item>
                        <el-descriptions-item label="年化收益率">{{ toFixed(item.year_rate || 0, 2) }}%</el-descriptions-item>
                        <el-descriptions-item>
                            <div class="operate">
                                <div>
                                    <el-button size="mini" type="primary" @click="HashpowerBuyClick(item, 1)">购买</el-button>
                                    <el-button size="mini" type="primary" @click="receiveBTCBReward(item)" :loading="receiveLoading" :disabled="!Number(item.btcbReward)">收获{{item.currency}}</el-button>
                                    <br><br>
                                    <el-button size="mini" type="primary" @click="toHashpowerDetail(1, item)">存入</el-button>
                                    <el-button size="mini" type="primary" @click="toHashpowerDetail(2, item)">提取</el-button>
                                </div>
                            </div>
                        </el-descriptions-item>
                    </el-descriptions>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import { get, post } from "@/common/axios.js";
import { $get } from '@/utils/request'
import Page from "@/components/Page.vue";
import { getPoolBtcData, getBalance } from "@/wallet/serve";
import { depositPoolsIn } from "@/wallet/trade";
import { keepDecimalNotRounding } from "@/utils/tools";
export default {
    name: '',
    data() {
        return {
            activeName: '1',
            timeInterval: null,
            refreshTime: 10000, //数据刷新间隔时间
            tableData: [],
            hashpowerList: [],
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            poolBtcData: {},
            loading: true,
            receiveLoading: false,
        }
    },
    activated() { //页面进来
        // this.refreshData();
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
        this.getPoolBtcData();
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val){
                if (val) {
                    if(!this.hashPowerPoolsList.length) {
                        this.$store.dispatch("getHashPowerPoolsList");
                    }
                    setTimeout(async() => {   

                        this.getPoolBtcData();

                        this.getMyProductList();

                        this.getMyHashpowerList();

                        this.refreshData();

                        console.log(this.hashPowerPoolsList);
                    }, 300);
                }
            }
        },
        hashPowerPoolsList: {
            immediate: true,
            async handler(val) {
                if(val && this.address) {
                    // this.getMyProductList();
                }
            },
        },
        poolBtcData: {
            immediate: true,
            async handler(val) {
                if(val && this.address) {
                    this.getMyProductList();
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
                await this.getMyProductList();
                await this.getMyHashpowerList();
                // await this.getHashpowerData();
            }, this.refreshTime)
        },
        async getPoolBtcData() { //获取BTC爬虫数据
            let data = await getPoolBtcData();
            if(data && data.length > 0) {
                this.poolBtcData = data[0];
            }
        },
        buyClick(row, type) {
            this.$router.push({
                path:'/financial/currentDetail',
                query: {
                    type: type,
                    product_id: row.product_id,
                }
            })
        },
        HashpowerBuyClick(row, type) {
            this.$router.push({
                path:'/hashpower/buy',
                query: {
                    type: type,
                    hash_id: row.id,
                }
            })
        },
        incomeClick(row) {
            this.$router.push({
                path:'/financial/userDetailsList',
                query: {
                    product_id: row.product_id,
                }
            })
        },
        getMyProductList(ServerWhere) {
            if(this.address) {
                if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                    ServerWhere = {
                        limit: this.pageSize,
                        page: this.currPage,
                        address: this.address,
                    };
                }
                get(this.apiUrl + "/Api/Product/getMyProductList", ServerWhere, async json => {
                    if (json.code == 10000) {
                        let list = (json.data && json.data.lists) || [];
                        // let hashpowerData = await this.getMyHashpowerList();
                        // console.log(hashpowerData);
                        // if(hashpowerData && hashpowerData.length > 0) {
                        //     list = [...list, ...hashpowerData];
                        // }
                        // console.log(list);
                        this.loading = false;
                        this.tableData = list;
                        this.total = json.data.count;
                        this.$forceUpdate();
                    } else {
                        this.$message.error("加载数据失败");
                    }
                });
            } else {
                this.loading = false;
            }
        },
        async getMyHashpowerList() { //获取我的算力币数据
            // return new Promise(async (resolve, reject) => {
                let list = [];
                console.log(this.hashPowerPoolsList, this.poolBtcData);
                this.hashPowerPoolsList.map((hashpowerObj, index) => {
                    if(hashpowerObj.btcb19ProBalance > 0 || hashpowerObj.balance > 0) {
                        list[index] = hashpowerObj;
                        list[index]['id'] = hashpowerObj.id;
                        list[index]['total_number'] = Number(hashpowerObj.balance) * 10; //购买数量
                        list[index]['total_balance'] = Number(hashpowerObj.balance); //总结余 = 购买数量 T数
                        // if(Number(element.buy_price) !== Number(element.cost_revenue)) {
                        let yest_income_usdt = Number(hashpowerObj.balance) * Number(hashpowerObj.daily_income); //昨日收益 usdt
                        let yest_income_btcb = keepDecimalNotRounding(Number(hashpowerObj.balance) * (Number(hashpowerObj.daily_income) / Number(this.poolBtcData.currency_price)), 10, true); //昨日收益 btcb
                        list[index]['yest_income'] = yest_income_usdt;
                        list[index]['yest_income_btcb'] = yest_income_btcb;
                        let countIncome = Number(hashpowerObj.btcbReward) + Number(hashpowerObj.harvest_btcb_amount); // 总的收益 = 奖励收益数量 + 已收割奖励数量
                        list[index]['total_income_btcb'] = countIncome; //btcb总收益
                        let total_income_usdt = countIncome * Number(this.poolBtcData.currency_price); //usdt总收益
                        list[index]['total_income_usdt'] = total_income_usdt;
                        list[index]['total_rate'] = keepDecimalNotRounding(total_income_usdt / (Number(hashpowerObj.balance) * Number(hashpowerObj.cost_revenue)), 10) * 100; //总收益率=总收益/总投入=总收益/(算力币价*算力币数)
                        // console.log(this.poolBtcData);
                        let dailyYield = yest_income_usdt / (Number(hashpowerObj.balance) * Number(hashpowerObj.cost_revenue)); //昨日收益/（算力币T数*算力币价格）
                        // console.log(dailyYield);
                        list[index]['year_rate'] = dailyYield * 365 * 100;
                        // }
                        list[index]['is_hash'] = true;
                    }
                    // console.log(hashpowerObj, index);
                })
                console.log(list);
                this.hashpowerList = list;
                // resolve(arr);
            // })
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
            this.getMyProductList(this.PageSearchWhere); //刷新列表
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
            this.getMyProductList(this.PageSearchWhere); //刷新列表
        },
        toHashpowerDetail(type, item) {
            //type 1=>存入 2=>提取
            console.log(item);
            if (!item.address_h)
                return this.$notify.error({
                    message: "Failed to get data, please refresh and try again",
                    duration: 6000,
                });

            // this.$store.commit('setDepositCurrent' , item.address)
            let query = {
                hashId: item.hash_id,
                pId: item.pId,
                token: item.address_h,
                currencyToken: item.currencyToken,
                goblin: item.goblin,
                decimals: item.decimals_h,
                name: item.name,
            };

            // if(route === 'un'){
            //     query.reward = item.reward
            // }
            sessionStorage.setItem("hashpowerPoolsDetailInfo", JSON.stringify(query));
            this.$router.push({
                path: "/hashpower/detail",
                query: {
                    type: type,
                    // kind:item.name ,
                    // token:address,
                    // decimals,
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

            // let hash = "0xc2882808e2ce6f72b397fce968fe256966824aa875b0a3b94523225188e50f4a";
            // let setHarvest = await this.setHashpowerHarvest(item.id, '0.000015', item.currency, hash);
            // if(setHarvest) {
            //     this.$message({
            //         type: 'success',
            //         message: 'Success!'
            //     });
            //     this.$store.dispatch("getHashPowerPoolsList");
            //     this.receiveLoading = false;
            // }
            // return false;
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
        }
    }
</style>
