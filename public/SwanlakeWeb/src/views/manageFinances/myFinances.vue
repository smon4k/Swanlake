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
            <!-- <el-row class="pages" v-if="total > pageSize">
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
            </el-row> -->
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
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import { get, post } from "@/common/axios.js";
import { $get } from '@/utils/request'
import Page from "@/components/Page.vue";
import { getPoolBtcData, getBalance } from "@/wallet/serve";
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
            hashPowerLoading: true,
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
                    setTimeout(async() => {   

                        this.getPoolBtcData();

                        this.getMyProductList();

                        this.refreshData();

                    }, 300);
                }
            }
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
                await this.getPoolBtcData();
                await this.getMyProductList();
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
                        this.tableData = list;
                        this.loading = false;
                        // console.log(list);
                    } else {
                        this.$message.error("加载数据失败");
                    }
                });
            } else {
                this.loading = false;
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
        ::v-deep  {
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
