4<template>
    <div class="container">
        <div v-if="!isMobel">
            <el-table
                :data="tableData"
                style="width: 100%">
                <el-table-column
                    prop="name"
                    label="产品名称"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="annualized_income"
                    label="预期年化收益率"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.annualized_income || 0, 2) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="total_size"
                    label="总份数"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.total_size || 0, 4) }}</span>
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
                    label="总结余(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(Number(scope.row.total_size) * Number(scope.row.networth) || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="yest_income"
                    label="昨日收益(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.yest_income || 0, 2) }}</span>
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
                    fixed="right"
                    label="操作"
                    align="center">
                    <template slot-scope="scope">
                        <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
                        <el-button type="text" @click="incomeClick(scope.row)">历史净值</el-button>
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
                    <el-descriptions-item label="总份数">{{ toFixed(item.total_size || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="总结余(USDT)">{{ toFixed(Number(item.total_size) * Number(item.networth) || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="昨日收益(USDT)">{{ toFixed(item.yest_income || 0, 2) }}</el-descriptions-item>
                    <el-descriptions-item label="昨日收益率">{{ toFixed(item.yest_income_rate || 0, 2) }}%</el-descriptions-item>
                    <el-descriptions-item>
                        <div class="operate">
                            <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                            <el-button type="text" @click="incomeClick(item)">历史净值</el-button>
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
export default {
    name: '',
    data() {
        return {
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            PageSearchWhere: [], //分页搜索数组
            tableData: []
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
        this.getListData();
    },
    watch: {

    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    methods: {
        getListData(ServerWhere) {
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.pageSize,
                    page: that.currPage,
                };
            }
            get(this.apiUrl + "/Api/Product/getProductList", ServerWhere, json => {
                console.log(json);
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
            this.$router.push({
                path:'/financial/currentDetail',
                query: {
                    type: type,
                    product_id: row.id,
                }
            })
        },
        incomeClick(row) {
            this.$router.push({
                path:'/financial/productDetailsList',
                query: {
                    product_id: row.id,
                }
            })
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
