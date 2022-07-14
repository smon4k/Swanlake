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
                        <p class="annualized-income desc">预计年化收益率</p>
                        <p class="rate">{{ toFixed(annualized_income || 0, 2) }}%</p>
                    </el-col>
                </el-row>
                <el-row class="content" :style="'width:'+isMobel ? '100%' : '80%'">
                    <el-col :span="8">
                        <p class="desc">我的可用余额</p>
                        <p class="balance">{{ toFixed(balance || 0, 2) }} USDT</p>
                    </el-col>
                    <el-col :span="8">
                        <p class="desc">净值</p>
                        <p class="balance">{{ toFixed(networth || 0, 4) }}</p>
                    </el-col>
                    <el-col :span="8">
                        <p class="desc">日收益率</p>
                        <p class="balance gree">{{ toFixed(annualized_income / 365 || 0, 2) }}%</p>
                    </el-col>
                    <div v-if="type == 2">
                        <el-col :span="8">
                            <p class="desc">在投数量</p>
                            <p class="balance">{{ toFixed(total_invest | 0, 2) }} USDT</p>
                        </el-col>
                        <!-- <el-col :span="8">
                            <p class="desc">购买总份数</p>
                            <p class="balance">{{ toFixed(total_number || 0, 2) }} 份</p>
                        </el-col> -->
                    </div>
                    <el-col :span="24">
                        <el-input v-model="shareValue" placeholder="请输入起投份额">
                            <template slot="prepend">份额</template>
                            <template slot="append">
                                <el-button v-if="type == 1" type="primary" @click="allfunBetClick()">全投</el-button>
                                <el-button v-else type="primary" @click="allfunRedClick()">全部</el-button>
                            </template>
                        </el-input>
                    </el-col>
                    <el-col :span="24" align="left" style="margin-top:10px;margin-left:70px;">{{ getCountAmount() }} USDT</el-col>
                    <!-- <el-col :span="24" class="protocol">
                        <span>继续代表你同意</span>
                        <a href="/page/agreement.html#/?agreement=financing_market_stakingprotocol" target="_blank">《活期宝产品服务协议》</a>
                    </el-col> -->
                    <el-col :span="24">
                        <el-button v-if="type == 1" class="invest-but" type="primary" :loading="loading" @click="startInvestNow()" :disabled="is_bet">立即投资</el-button>
                        <el-button v-else class="invest-but" type="primary" :loading="loading" @click="startInvestNow()">立即赎回</el-button>
                    </el-col>
                </el-row>
            </div>
        </el-card>
    </div>
</template>
<script>
import { get, post, upload } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
export default {
    name: '',
    data() {
        return {
            input: '0.000000',
            type: 1,
            product_id: 0,
            shareValue: 0,
            loading: false,
            networth: 0,
            is_bet: false,
            balance: 0,
            daily_income: 0.0000,
            total_number: 0.0000, //投资总份数
            total_invest: 0.0000, //投资数量
            annualized_income: 0.0000 //预计年化收益
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
            let type = this.$route.query.type;
            if(type && type !== undefined) {
                this.type = type;
            }
            let product_id = this.$route.query.product_id;
            console.log(product_id);
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
                    this.getProductDetail();
                }
            }
        }
    },
    components: {

    },
    methods: {
        allfunBetClick() { //计算最大投注份额
            let num = 0;
            if(this.balance > 0) {
                num = this.balance / this.networth;
            }
            this.shareValue = num;
        },
        allfunRedClick() { //赎回 全部事件
            this.shareValue = this.total_number;
        },
        startInvestNow() { //立即投资或者赎回
            if(!this.address || this.address == undefined) {
                return false;
            }
            if(this.type == 1) { //投资的话
                if(this.balance <= 0) {
                    this.$message({
                        message: '余额不足',
                        type: 'warning'
                    });
                    return false;
                }
                let amount = this.getCountAmount();
                if(amount > this.balance) {
                    this.$message({
                        message: '超出可用余额',
                        type: 'warning'
                    });
                    return false;
                }
            }
            if(this.type == 2) { //赎回的话
                if(this.total_number <= 0) {
                    this.$message({
                        message: '投资份数不足',
                        type: 'warning'
                    });
                    return false;
                }
                if(this.shareValue > this.total_number) {
                    this.$message({
                        message: '已超过最大投资份数',
                        type: 'warning'
                    });
                    return false;
                }
            } 
            if(this.shareValue <= 0) {
                this.$message({
                    message: '请输入投资数量',
                    type: 'warning'
                });
                return false;
            }
            this.loading = true;
            setTimeout(() => {
                post('/Api/Product/startInvestNow', { 
                        address: this.address, 
                        product_id: this.product_id, 
                        number: this.shareValue,
                        type: this.type
                    }, (json) => {
                    this.loading = false;
                    console.log(json);
                    if (json && json.code == 10000) {
                        this.shareValue = 0;
                        this.$message({
                            type: 'success',
                            message: this.type == 1 ? '投资成功!' : '赎回成功!'
                        });
                        setTimeout(() => {
                            this.$router.push({path:'/my/finances'})
                        }, 2000)
                    } else {
                        this.$message.error(json.msg);
                    }
                })
            }, 2000)
        },
        getProductDetail() { //获取产品详情数据
            get("/Api/Product/getProductDetail", {
                product_id: this.product_id,
                address: this.address
            }, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.networth = json.data.networth;
                    this.balance = json.data.balance;
                    this.daily_income = json.data.daily_income;
                    this.total_number = json.data.total_number;
                    this.total_invest = json.data.total_invest;
                    this.annualized_income = json.data.annualized_income;
                    // this.is_bet = json.data.is_bet ? true : false;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getCountAmount() { //获取投资数量
           return this.toFixed(Number(this.shareValue) * Number(this.networth) || 0, 4);
        }
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
