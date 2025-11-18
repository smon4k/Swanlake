<template>
    <div class="container">
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>我的订单</el-breadcrumb-item>
        </el-breadcrumb>
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
                    prop="quantity"
                    label="数量(USDT)"
                    align="center">
                    <template slot-scope="scope">
                        <span v-if="scope.row.type == 1">{{ '-' + scope.row.quantity }}</span>
                        <span v-if="scope.row.type == 2">{{ '+' + scope.row.quantity }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="number"
                    label="份数"
                    align="center">
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
                    label="类型"
                    align="center">
                    <template slot-scope="scope">
                        <span v-if="scope.row.type == 1">购买</span>
                        <span v-if="scope.row.type == 2">赎回</span>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="time"
                    label="操作时间"
                    align="center">
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
            <div 
                class="infinite-list-wrapper"
                v-if="tableData.length" 
                v-infinite-scroll="load"
                style="overflow:auto"
                >
                <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                    <el-descriptions-item label="产品名称">{{ item.name }}</el-descriptions-item>
                    <el-descriptions-item label="数量(USDT)">{{ item.quantity }}</el-descriptions-item>
                    <el-descriptions-item label="份数">{{ item.number }}</el-descriptions-item>
                    <el-descriptions-item label="净值">{{ keepDecimalNotRounding(item.networth || 0, 4) }}</el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <span v-if="item.type == 1">购买</span>
                        <span v-if="item.type == 2">赎回</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="操作时间">{{ item.time }}</el-descriptions-item>
                </el-descriptions>
                <p v-if="loading">加载中...</p>
                <p v-if="finished">没有更多了</p>
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
            // let product_id = this.$route.query.product_id;
            // if(product_id && product_id > 0) {
            //     this.product_id = product_id;
            // }
        } catch (err) {}
    },
    watch: {
        changeData: {
            immediate: true,
            handler(val){
                if(val.address) {
                    this.getProductOrderList();
                }
            }
        }
    },
    components: {
        "wbc-page": Page, //加载分页组件
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
        load () { //加载更多
            if(!this.finished) {
                this.getProductOrderList();
            }
        },
        getProductOrderList(ServerWhere) {
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: this.pageSize,
                    page: this.currPage,
                    address: this.address,
                    product_id: this.product_id,
                };
            }
            this.loading = true;
            get(this.apiUrl + "/Api/Product/getProductOrderList", ServerWhere, json => {
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
                this.loading = false;
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
            }getProductOrderList
            this.getMyProductList(this.PageSearchWhere); //刷新列表
        },
        skipPaging(page) {
            //赋值当前页数
            console.log(this.PageSearchWhere);
            this.currPage = page;
            if (
                this.PageSearchWhere.page &&
                this.PageSearchWhere.page !== undefined
            ) {
                this.PageSearchWhere.page = page;
            }
            this.getProductOrderList(this.PageSearchWhere); //刷新列表
        },
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
    .container {
        ::v-deep  {
            .el-breadcrumb {
                height: 25px;
                font-size: 16px;
            }
            .el-table {
                font-size: 16px;
            }
            .infinite-list-wrapper {
                height: 100vh;
                p {
                    text-align: center;
                }
            }
            .infinite-list-wrapper::-webkit-scrollbar { width: 0 !important }
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
