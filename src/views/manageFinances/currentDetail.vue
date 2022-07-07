<template>
    <div class="container">
        <el-card class="box-card">
            <div slot="header" class="clearfix">
                <img src="@/assets/usdt.png" width="30" height="30" alt="">
                <span>USDT活期理财</span>
                <!-- <el-button style="float: right; padding: 3px 0" type="text">操作按钮</el-button> -->
            </div>
            <div class="buy-box">
                <el-row>
                    <el-col :span="24" align="center">
                        <p class="annualized-income desc">预计年化收益</p>
                        <p class="rate">4.5%</p>
                    </el-col>
                </el-row>
                <el-row class="content" :style="'width:'+isMobel ? '100%' : '80%'">
                    <el-col :span="12">
                        <p class="desc">我的可用余额</p>
                        <p class="balance">0 USDT</p>
                    </el-col>
                    <el-col :span="12">
                        <p class="desc">预计每日收益</p>
                        <p class="balance gree">0.000000 USDT</p>
                    </el-col>
                    <el-col :span="24">
                        <el-input v-model="input" placeholder="1 USDT 起投">
                            <template slot="prepend">USDT</template>
                            <template slot="append">
                                <el-button v-if="type == 1" type="primary" @click="allfunClick()">全投</el-button>
                                <el-button v-else type="primary" @click="allfunClick()">全部</el-button>
                            </template>
                        </el-input>
                    </el-col>
                    <el-col :span="24" class="protocol">
                        <span>继续代表你同意</span>
                        <a href="/page/agreement.html#/?agreement=financing_market_stakingprotocol" target="_blank">《活期宝产品服务协议》</a>
                    </el-col>
                    <el-col :span="24">
                        <el-button v-if="type == 1" class="invest-but" type="primary">立即投资</el-button>
                        <el-button v-else class="invest-but" type="primary">立即赎回</el-button>
                    </el-col>
                </el-row>
            </div>
        </el-card>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
export default {
    name: '',
    data() {
        return {
            input: '0.000000'
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
        try {
            let type = this.$route.query.type;
            if(type && type !== undefined) {
                this.type = type;
            }
        } catch (err) {}
    },
    watch: {

    },
    components: {

    },
    methods: {
        allfunClick() {

        },
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            .box-card {
                .clearfix {
                    img {
                      vertical-align: middle;  
                    }
                }
                .el-card__body {
                    padding-bottom: 80px;
                }
                // max-width: 374px;
                border-radius: 30px;
                .desc {
                    color: #99a1b1;
                    font-size: 16px;
                    font-weight: 400;
                }
                .gree {
                    color: #3ab293;
                }
                .buy-box {
                    .annualized-income {
                        margin: 0;
                        width: 380px;
                    }
                    .content {
                        text-align: center;
                        margin: 0 auto;
                        .el-input__inner {
                            height: 60px;
                        }
                        .protocol {
                            font-size: 12px;
                            line-height: 50px;
                            text-align: center;
                            // width: 50%;
                            span {
                                color: #a0abc0;
                            }
                        }
                        .invest-but {
                            font-size: 16px;
                            font-weight: 600;
                            height: 48px;
                            width: 178px;
                        }
                    }
                    .rate {
                        border-bottom: 1px solid #eceef2;
                        color: #3ab293;
                        font-size: 48px;
                        font-weight: 700;
                        margin: 0 auto;
                        padding-bottom: 10px;
                        width: 380px;
                    }
                }
            }
        }
    }
</style>
