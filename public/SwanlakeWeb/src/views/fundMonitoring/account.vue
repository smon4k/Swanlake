<template>
    <div class="container">
        <el-descriptions class="margin-top" title="" :column="isMobel ? 1 : 3" size="medium" border>
            <template slot="title">OKEX</template>
            <el-descriptions-item>
                <template slot="label">
                    <!-- <i class="el-icon-user"></i> -->
                    APIKey
                </template>
                {{ APIKey }}
                &nbsp;
                <el-button type="text" v-clipboard:copy="APIKey" v-clipboard:success="copySuccess">复制</el-button>
            </el-descriptions-item>
            <el-descriptions-item>
                <template slot="label">
                    SecretKey
                </template>
                {{ SecretKey }}
                &nbsp;
                <el-button type="text" v-clipboard:copy="SecretKey" v-clipboard:success="copySuccess">复制</el-button>
            </el-descriptions-item>
            <el-descriptions-item>
                <template slot="label">
                    Passphrase
                </template>
                {{ Passphrase }}
                &nbsp;
                <el-button type="text" v-clipboard:copy="Passphrase" v-clipboard:success="copySuccess">复制</el-button>
            </el-descriptions-item>
        </el-descriptions>
        <el-descriptions class="margin-top" title="" :column="isMobel ? 1 : 3" size="medium" border>
            <template slot="title">火币</template>
            <el-descriptions-item>
                <template slot="label">
                    <!-- <i class="el-icon-user"></i> -->
                    Access Key
                </template>
                {{ Access_Key }}
                &nbsp;
                <el-button type="text" v-clipboard:copy="Access_Key" v-clipboard:success="copySuccess">复制</el-button>
            </el-descriptions-item>
            <el-descriptions-item>
                <template slot="label">
                    Secret Key
                </template>
                {{ Secret_Key }}
                &nbsp;
                <el-button type="text" v-clipboard:copy="Secret_Key" v-clipboard:success="copySuccess">复制</el-button>
            </el-descriptions-item>
        </el-descriptions>
        

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
                    prop="account"
                    label="账户名称"
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
                    <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                    <el-descriptions-item label="账户名称">{{ item.account }}</el-descriptions-item>
                    <el-descriptions-item label="OKEX账户余额(USDT)">{{ toFixed(item.okex_balance || 0, 2) }}</el-descriptions-item>
                    <el-descriptions-item label="火币账户余额(USDT)">{{ toFixed(item.huobi_balance || 0, 4) }}</el-descriptions-item>
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
import Page from "@/components/Page.vue";
export default {
    name: '',
    data() {
        return {
            active: 2,
            btc_price: 0,
            tableData: [],
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 1, //总条数
            loading: false,
            finished: false,
            PageSearchWhere: [],
            APIKey: '21d3e612-910b-4f51-8f8d-edf2fc6f22f5',
            SecretKey: '89D37429D52C5F8B8D8E8BFB964D79C8',
            Passphrase: 'Zx112211@',
            Access_Key: 'a36c5b20-qv2d5ctgbn-cb3de46a-f3ce8',
            Secret_Key: '3d2322fc-a5919d1d-18dc22e8-527e9',
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
        "wbc-page": Page, //加载分页组件
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
            this.loading = true;
            get("/Api/Product/getAccountFundMonitoring", ServerWhere, json => {
                if (json.code == 10000) {
                    if (json.data.lists) {
                        let list = (json.data && json.data.lists) || [];
                        if(this.isMobel) {
                            if (this.currPage <= 1) {
                                // console.log('首次加载');
                                this.tableData = list;
                                // this.$forceUpdate();
                            } else {
                                // console.log('二次加载');
                                if (ServerWhere.page <= json.data.allpage) {
                                    // console.log(ServerWhere.page, json.data.allpage);
                                    this.tableData = [...this.tableData, ...list];
                                    // this.$forceUpdate();
                                }
                            }
                            this.currPage += 1;
                            if (ServerWhere.page >= json.data.allpage) {
                                // console.log(ServerWhere.page, json.data.allpage);
                                this.finished = true;
                            } else {
                                this.finished = false;
                            }
                        } else {
                            this.tableData = list;
                        }
                    }
                    this.total = json.data.count;
                    this.loading = false;
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
        copySuccess() {
            this.$message({
                message: 'Copy successfully!',
                type: 'success'
            });
        }
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
