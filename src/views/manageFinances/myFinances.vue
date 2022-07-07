<template>
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
                    prop="cast_number"
                    label="在投数量"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="buy_number"
                    label="购买份数"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="net_worth"
                    label="净值"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="yest_income"
                    label="昨日收益"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="total_rate"
                    label="总收益率"
                    align="center">
                </el-table-column>
                <el-table-column
                    prop="year_rate"
                    label="年化收益率"
                    align="center">
                </el-table-column>
                <el-table-column
                fixed="right"
                label="操作"
                align="center">
                <template slot-scope="scope">
                    <el-button @click="buyClick(scope.row, 1)" type="text">购买</el-button>
                    <el-button type="text" @click="buyClick(scope.row, 2)">赎回</el-button>
                    <el-button type="text" @click="incomeClick(scope.row)">明细</el-button>
                </template>
                </el-table-column>
            </el-table>
        </div>
        <div v-else>
            <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                <el-descriptions-item label="在投数量">{{ item.cast_number }}</el-descriptions-item>
                <el-descriptions-item label="购买份数">{{ item.buy_number }}</el-descriptions-item>
                <el-descriptions-item label="净值">{{ item.net_worth }}</el-descriptions-item>
                <el-descriptions-item label="昨日收益">{{ item.yest_income }}</el-descriptions-item>
                <el-descriptions-item label="总收益率">{{ item.total_rate }}</el-descriptions-item>
                <el-descriptions-item label="年化收益率">{{ item.year_rate }}</el-descriptions-item>
                <el-descriptions-item>
                    <div class="operate">
                        <el-button size="mini" type="primary" @click="buyClick(item, 1)">购买</el-button>
                        <el-button size="mini" type="primary" @click="buyClick(item, 2)">赎回</el-button>
                        <el-button size="mini" type="primary" @click="incomeClick(item)">明细</el-button>
                    </div>
                </el-descriptions-item>
            </el-descriptions>
        </div>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
export default {
    name: '',
    data() {
        return {
            tableData: [{
                id: 1,
                name: '红天鹅1号',
                cast_number: '20000USDT', //在投数量
                buy_number: '2000份', //购买份数
                net_worth: '1.01', //净值
                yest_income: '20USDT', //昨日收益
                total_rate: '2300USDT', //总收益率
                year_rate: '20%', //年化收益率
            }]
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

    },
    watch: {

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
        incomeClick(row) {
            this.$router.push({
                path:'/financial/incomeList',
            })
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
