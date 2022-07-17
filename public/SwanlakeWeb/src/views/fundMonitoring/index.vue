<template>
    <div class="container">
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
                    label="OKEX账户余额(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.okex_balance || 0, 4) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="networth"
                    label="火币账户余额(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span>{{ toFixed(scope.row.huobi_balance || 0, 4) }}</span>
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <div v-else>
            <div v-if="tableData.length">
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                    <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                    <el-descriptions-item label="OKEX账户余额">{{ toFixed(item.okex_balance || 0, 2) }}</el-descriptions-item>
                    <el-descriptions-item label="火币账户余额">{{ toFixed(item.huobi_balance || 0, 4) }}</el-descriptions-item>
                </el-descriptions>
            </div>
            <div v-else>
                <el-empty description="没有数据"></el-empty>
            </div>
        </div>
    </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getPoolBtcData } from "@/wallet/serve";
export default {
    name: '',
    data() {
        return {
            active: 2,
            btc_price: 0,
            tableData: [],
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
        this.getPoolBtc();
        this.getList();
    },
    watch: {

    },
    components: {

    },
    methods: {
        async getPoolBtc() {
            let poolBtc = await getPoolBtcData();
            if(poolBtc && poolBtc[0].currency_price) {
                this.btc_price = poolBtc[0].currency_price;
            }
        },
        getList(ServerWhere) {
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: this.pageSize,
                    page: this.currPage
                };
            }
            get("/Api/Product/getFundMonitoring", ServerWhere, json => {
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
            this.getList(this.PageSearchWhere); //刷新列表
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
            this.getList(this.PageSearchWhere); //刷新列表
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
