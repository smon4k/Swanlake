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
                    width="130">
                </el-table-column>
                <el-table-column
                    prop="total_balance"
                    label="总结余"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_balance || 0, 4) }} {{scope.row.currency === 'BTCB' ? 'T' : scope.row.currency}}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="total_number"
                    label="购买份数"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_number || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <!-- <el-table-column
                    prop="time"
                    label="购买时间"
                    align="center"
                    width="200">
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
                    prop="yest_income"
                    label="昨日收益"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.yest_income || 0, 4) }} {{scope.row.currency}}</span>
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
                width="200">
                <template slot-scope="scope">
                    <div v-if="!scope.row.is_hash">
                        <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
                        <el-button type="text" @click="buyClick(scope.row, 2)">赎回</el-button>
                        <el-button type="text" @click="incomeClick(scope.row)">历史净值</el-button>
                    </div>
                    <div v-else>
                        <el-button @click="receiveBTCBReward(scope.row)" type="text" :loading="receiveLoading" :disabled="!Number(scope.row.hashpowerObj.btcbReward)">收获{{scope.row.currency}}</el-button>
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
            <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                <el-descriptions-item label="总结余">{{ toFixed(item.total_balance || 0, 4) }} {{item.currency === 'BTCB' ? 'T' : item.currency}}</el-descriptions-item>
                <el-descriptions-item label="购买份数">{{ toFixed(item.total_number || 0, 4) }}</el-descriptions-item>
                <!-- <el-descriptions-item label="购    买时间">{{ item.time }}</el-descriptions-item> -->
                <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item>
                <el-descriptions-item label="昨日收益">{{ toFixed(item.yest_income || 0, 2) }} {{item.currency}}</el-descriptions-item>
                <el-descriptions-item label="总收益率">{{ toFixed(item.total_rate || 0, 2) }}%</el-descriptions-item>
                <el-descriptions-item label="年化收益率">{{ toFixed(item.year_rate || 0, 2) }}%</el-descriptions-item>
                <el-descriptions-item>
                    <div class="operate">
                        <div v-if="!item.is_hash">
                            <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                            <el-button size="mini" type="primary" @click="buyClick(item, 2)">赎回</el-button>
                            <el-button size="mini" type="primary" @click="incomeClick(item)">历史净值</el-button>
                        </div>
                        <div v-else>
                            <el-button size="mini" type="primary" @click="receiveBTCBReward(item)" :loading="receiveLoading" :disabled="!Number(item.hashpowerObj.btcbReward)">收获{{item.currency}}</el-button>
                            <el-button size="mini" type="primary" @click="toHashpowerDetail(1, item)">购买</el-button>
                            <el-button size="mini" type="primary" @click="toHashpowerDetail(2, item)">赎回</el-button>
                        </div>
                    </div>
                </el-descriptions-item>
            </el-descriptions>
        </div>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import { get } from "@/common/axios.js";
import Page from "@/components/Page.vue";
import { getPoolBtcData } from "@/wallet/serve";
import { depositPoolsIn } from "@/wallet/trade";
export default {
    name: '',
    data() {
        return {
            timeInterval: null,
            refreshTime: 10000, //数据刷新间隔时间
            tableData: [],
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

                        this.refreshData();

                        // console.log(this.hashPowerPoolsList);
                    }, 300);
                }
            }
        },
        hashPowerPoolsList: {
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
                        let hashpowerData = await this.getMyHashpowerList();
                        // console.log(hashpowerData);
                        if(hashpowerData && hashpowerData.length > 0) {
                            list = [...list, ...hashpowerData];
                        }
                        console.log(list);
                        this.tableData = list;
                        this.total = json.data.count;
                    } else {
                        this.$message.error("加载数据失败");
                    }
                    this.loading = false;
                });
            } else {
                this.loading = false;
            }
        },
        async getMyHashpowerList(ServerWhere) { //获取我的算力币数据
            return new Promise(async (resolve, reject) => {
                var that = this.$data;
                if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                    ServerWhere = {
                        limit: that.pageSize,
                        page: that.currPage,
                        address: this.address,
                    };
                }
                get(this.apiUrl + "/Hashpower/Hashpower/getMyHashpowerList", ServerWhere, json => {
                    console.log(json);
                    if (json.code == 10000) {
                        let list = (json.data && json.data.lists) || [];
                        console.log(this.hashPowerPoolsList);
                        for (let index = 0; index < list.length; index++) {
                            const element = list[index];
                            this.hashPowerPoolsList.forEach(hashpowerObj => {
                                if(element.name == hashpowerObj.name) {
                                    list[index] = {...list[index], hashpowerObj};
                            //         list[index]['total_balance'] = hashpowerObj.total;
                                    let yest_income = Number(hashpowerObj.balance) * Number(element.daily_income);
                                    list[index]['total_number'] = hashpowerObj.balance; //购买数量
                                    list[index]['total_balance'] = Number(hashpowerObj.balance) * Number(this.poolBtcData.currency_price); //总结余 = 购买数量 * 价格
                                    list[index]['yest_income'] = yest_income;
                                    list[index]['total_rate'] = hashpowerObj.btcbReward;
                                    // console.log(this.poolBtcData);
                                    let dailyYield = (Number(element.daily_income) / Number(this.poolBtcData.currency_price) * Number(hashpowerObj.balance)) * 365 * 100; //日收益率 = 当日收益/算力币价*总购买算力
                                    // console.log(dailyYield);
                                    list[index]['year_rate'] = dailyYield;
                                    list[index]['is_hash'] = true;
                                }
                            });
                        }
                        console.log(list);
                        resolve(list);
                    } else {
                        this.$message.error("加载数据失败");
                        resolve(false);
                    }
                });
            })
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
            if (!item.hashpowerObj.address_h)
                return this.$notify.error({
                    message: "Failed to get data, please refresh and try again",
                    duration: 6000,
                });

            // this.$store.commit('setDepositCurrent' , item.address)
            let query = {
                hashId: item.hash_id,
                pId: item.hashpowerObj.pId,
                token: item.hashpowerObj.address_h,
                currencyToken: item.hashpowerObj.currencyToken,
                goblin: item.hashpowerObj.goblin,
                decimals: item.hashpowerObj.decimals_h,
                name: item.hashpowerObj.name,
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
        receiveBTCBReward(item) { //收获BTCB
            console.log("item", item)
            if (!Number(item.hashpowerObj.btcbReward)) return;
            this.$store.commit("sethashPowerPoolsListClaimLoading", {
                goblin: item.hashpowerObj.goblin,
                val: true,
            });
            this.receiveLoading = true;
            depositPoolsIn(
                item.hashpowerObj.goblin,
                item.hashpowerObj.decimals,
                0,
                item.pId
            ).then(() => {
                this.$store.dispatch("getHashPowerPoolsList");
            }).finally(() => {
                this.$store.commit("sethashPowerPoolsListClaimLoading", {
                    goblin: item.hashpowerObj.goblin,
                    val: false,
                });
                this.receiveLoading = false;
            });
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
        }
    }
</style>
