<template>
    <div class="container">
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/financial/product' }">理财产品</el-breadcrumb-item>
            <el-breadcrumb-item>历史净值</el-breadcrumb-item>
        </el-breadcrumb>
        <div v-if="!isMobel">
            <el-table
                :data="tableData"
                style="width: 100%">
                <el-table-column
                    prop="date"
                    label="日期"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="account_balance"
                    label="账户余额(USDT)"
                    align="center"
                    width="150">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.account_balance || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="networth"
                    label="净值"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ keepDecimalNotRounding(scope.row.networth || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="total_revenue"
                    label="总收益(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_revenue || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="daily_income"
                    label="日收益(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_income || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="daily_rate_return"
                    label="日收益率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_rate_return || 0, 4) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="total_revenue_rate"
                    label="总收益率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_revenue_rate || 0, 4) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="daily_arg_rate"
                    label="日均收益率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_arg_rate || 0, 4) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="daily_arg_annualized"
                    label="日均年化收益"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.daily_arg_annualized || 0, 4) }}%</span>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <div v-else>
            <div v-if="tableData.length">
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                    <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                    <el-descriptions-item label="账户余额(USDT)">{{ toFixed(item.account_balance || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="总收益(USDT)">{{ toFixed(item.total_revenue || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="日收益(USDT)">{{ toFixed(item.daily_income || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="日收益率">{{ toFixed(item.daily_rate_return || 0, 4) }}%</el-descriptions-item>
                    <el-descriptions-item label="总收益率">{{ toFixed(item.total_revenue_rate || 0, 4) }}%</el-descriptions-item>
                    <el-descriptions-item label="日均收益率">{{ toFixed(item.daily_arg_rate || 0, 4) }}%</el-descriptions-item>
                    <el-descriptions-item label="日均年化收益">{{ toFixed(item.daily_arg_annualized || 0, 4) }}%</el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import { get } from "@/common/axios.js";
import Page from "@/components/Page.vue";
export default {
    name: '',
    data() {
        return {
            tableData: [],
            product_id: 0,
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 1, //总条数
            loading: false,
            finished: false,
            PageSearchWhere: [],
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
        changeData() {
            const {address, product_id} = this
            return {
                address, product_id
            };
        },

    },
    created() {
        try {
            let product_id = this.$route.query.product_id;
            if(product_id && product_id > 0) {
                this.product_id = product_id;
            }
        } catch (err) {}
    },
    watch: {
        changeData: {
            immediate: true,
            handler(val){
                if(val.address && val.product_id) {
                    this.getMyProductDetailsList();
                }
            }
        }
    },
    components: {

    },
    methods: {
        buyClick(row, type) {
            this.$router.push({
                path:'/financial/currentDetail',
                query: {
                    type: type
                }
            })
        },
        getMyProductDetailsList(ServerWhere) {
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: this.pageSize,
                    page: this.currPage,
                    address: this.address,
                    product_id: this.product_id,
                };
            }
            get("/Api/Product/getProductDetailsList", ServerWhere, json => {
                if (json.code == 10000) {
                    this.tableData = json.data.lists;
                    this.total = json.data.count;
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
