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
                    prop=""
                    label="预期年化收益"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.annualized_rate || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="预期利润"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.profit || 0, 4) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="预期利润率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.profit_rate || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="服务期"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ scope.row.validity_period }} 天</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop=""
                    label="算力"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ scope.row.hash_rate }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    label="合约价格"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ keepDecimalNotRounding(scope.row.price || 0, 4) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column
                    fixed="right"
                    label="操作"
                    align="center">
                    <template slot-scope="scope">
                        <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
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
                    <el-descriptions-item label="预期年化收益">{{ keepDecimalNotRounding(item.annualized_rate, 2) }}%</el-descriptions-item>
                    <el-descriptions-item label="预期利润">{{ keepDecimalNotRounding(item.profit, 4) }} USDT</el-descriptions-item>
                    <el-descriptions-item label="预期利润率">{{ keepDecimalNotRounding(item.profit_rate, 2) }}%</el-descriptions-item>
                    <el-descriptions-item label="服务期">{{ item.validity_period }} 天</el-descriptions-item>
                    <el-descriptions-item label="算力">{{ item.hash_rate }}</el-descriptions-item>
                    <el-descriptions-item label="合约价格">{{ keepDecimalNotRounding(item.price, 4) }} USDT</el-descriptions-item>
                    <el-descriptions-item>
                        <div class="operate">
                            <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                        </div>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>
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
                    await this.getListData();
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
                };
            }
            get(this.apiUrl + "/Power/Power/getPowerList", ServerWhere, async json => {
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
