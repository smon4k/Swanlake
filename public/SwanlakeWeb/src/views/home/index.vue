<template>
    <div class="container">
        <div class="main">
            <el-row class="header">
                <el-col :span="isMobel ? 24 : 12">
                    <div class="title">我的账户</div>
                </el-col>
            </el-row>
            <el-card class="box-card">
                <div slot="header" class="clearfix" @click="cardClick()">
                    <span>我的账户</span>
                    <!-- <div style="float: right; padding: 3px 0">
                        <i class="el-icon-arrow-right"></i>
                    </div> -->
                </div>
                <el-row class="total">
                    <el-col :span="12">
                        <div>平台余额(USDT)</div>
                        <!-- <div class="price">{{ toFixed(Number(userInfo.wallet_balance) + Number(userInfo.local_balance), 4) }}</div> -->
                        <div class="price">{{ toFixed(Number(walletBalance) + Number(userInfo.local_balance), 4) }}</div>
                        <div>≈{{ toFixed((Number(walletBalance) + Number(userInfo.local_balance)) * CNY_USD || 0, 4) }} CNY</div>
                    </el-col>
                    <el-col :span="12">
                        <div>钱包余额(USDT)</div>
                        <div class="price">{{ toFixed(h2oBalance || 0, 4) }}</div>
                        <div>≈{{ toFixed(h2oBalance * CNY_USD || 0, 4) }} CNY</div>
                    </el-col>
                </el-row>
                <el-row class="total" style="margin-top:20px;">
                    <el-col :span="12">
                        <div>累计投资(USDT)</div>
                        <div class="price">{{ toFixed(userInfo.total_invest || 0, 4) }}</div>
                        <div>≈{{ toFixed(userInfo.total_invest * CNY_USD || 0, 4) }} CNY</div>
                    </el-col>
                    <el-col :span="12">
                        <div>累计收益(USDT)</div>
                        <div class="price gree">{{ toFixed(userInfo.cumulative_income || 0, 4) }}</div>
                        <div>≈{{ toFixed(userInfo.cumulative_income * CNY_USD || 0, 4) }} CNY</div>
                    </el-col>
                </el-row>
                <el-row type="flex" class="total" style="margin-top:20px;">
                    <el-col :span="12">
                        <div>投资结余(USDT)</div>
                        <div class="price">{{ toFixed(userInfo.count_balance || 0, 4) }}</div>
                        <div>≈{{ toFixed(userInfo.count_balance * CNY_USD || 0, 4) }} CNY</div>
                    </el-col>
                     <el-col :span="12" class="my-order">
                        <el-button @click="routeMyOrder()" round>我的订单 <i class="el-icon-caret-right"></i></el-button>
                    </el-col>
                </el-row>

            </el-card>
            <br>
            <el-row class="header" @click="cardClick()">
                <el-col :span="isMobel ? 24 : 12">
                    <div class="title">强力推荐</div>
                </el-col>
            </el-row>
            <el-row :gutter="20">
                <el-col :span="isMobel ? 24 : 8">
                    <el-card class="box-card recommend" v-for="(item,index) in tableData" :key="index">
                        <div class="novice">
                            <span>活期</span>
                        </div>
                        <div class="title-name">
                            <img src="@/assets/usdt.png" width="30" height="30" alt="">
                            <p>
                                <span class="span1">{{ item.currency }}</span>
                                <span class="span2">{{ item.name }}</span>
                            </p>
                        </div>
                        <div class="interest-rate">
                            <p>
                                <i class="p1">{{ toFixed(item.annualized_income || 0, 2) }}</i>
                                <i class="pi">%</i>
                                <i class="p2">预期年化收益率</i>
                            </p>
                        </div>
                        <div class="recommend-button">
                            <el-button type="primary" @click="buyClick(item)">立即投资</el-button>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="isMobel ? 24 : 8" v-for="(item,index) in hashPowerPoolsList" :key="index">
                    <el-card class="box-card recommend">
                        <div class="novice">
                            <span>活期</span>
                        </div>
                        <div class="title-name">
                            <img src="@/assets/usdt.png" width="30" height="30" alt="">
                            <p>
                                <span class="span1">{{ item.currency }}</span>
                                <span class="span2">{{ item.name }}</span>
                            </p>
                        </div>
                        <div class="interest-rate">
                            <p>
                                <i class="p1">{{ toFixed(item.annualized_income || 0, 2) }}</i>
                                <i class="pi">%</i>
                                <i class="p2">预期年化收益率</i>
                            </p>
                        </div>
                        <div class="recommend-button">
                            <el-button type="primary" @click="hashpowerBuyClick(item)">立即投资</el-button>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </div>
    </div>
</template>
<script>
import { get } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getBalance, getGameFillingBalance } from "@/wallet/serve";
import Address from '@/wallet/address.json'
export default {
    name: 'home',
    data() {
        return {
            active: 2,
            CNY_USD: 6.70,
            // userInfo: {},
            tableData: [],
            h2oBalance: 0,
            walletBalance: 0, //链上余额
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
            userInfo:state=>state.base.userInfo,
            hashPowerPoolsList:state=>state.base.hashPowerPoolsList,
        }),
        changeData() {
            const {address} = this
            return {
                address
            };
        },
    },
    created() {
        this.getListData();
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    if(!this.hashPowerPoolsList.length) {
                        this.$store.dispatch("getHashPowerPoolsList");
                    }
                    setTimeout(async() => {
                        console.log(this.hashPowerPoolsList);
                    }, 300)
                }
            },
        },
        address: {
            immediate: true,
            async handler(val){
                if(val) {
                    // this.getUserInfo();
                    this.h2oBalance = await getBalance(Address.BUSDT, 18); //获取H2O余额
                    console.log('USDT 余额：', this.h2oBalance);
                    this.walletBalance = await getGameFillingBalance(); //获取合约余额
                    console.log('链上余额：', this.walletBalance);
                }
            }
        },
        hashPowerPoolsList: {
            immediate: true,
            async handler(val){
                console.log(val);
            }
        }
    },
    components: {

    },
    methods: {
        cardClick() {
            // console.log(111);
        },
        buyClick(row) {
            console.log(row);
            this.$router.push({
                path:'/financial/currentDetail',
                query: {
                    type: 1,
                    product_id: row.id,
                }
            })
        },
        hashpowerBuyClick(row, type) {
            console.log(row);
            // this.$router.push({
            //     path:'/hashpower/buy',
            //     query: {
            //         type: 1,
            //         hash_id: row.id,
            //     }
            // })
            this.$router.push({
                name:'hashpowerBuy',
                params: {
                    type: type,
                    hash_id: row.id,
                    hashpowerAddress: row.hashpowerAddress
                }
            })
        },
        getUserInfo() { //获取用户数据
            get(this.apiUrl + "/Api/User/getUserInfo", {
                address: this.address
            }, async json => {
                console.log(json);
                if (json.code == 10000) {
                    this.userInfo = json.data;
                    this.h2oBalance = await getBalance(Address.BUSDT, 18); //获取H2O余额
                    console.log('USDT 余额：', this.h2oBalance);
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getListData() {
            let ServerWhere = {
                limit: this.pageSize,
                page: this.currPage,
            };
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
        routeMyOrder() { //跳转我的订单也
            this.$router.push('/order/record');
        }
        
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            // padding: 20px;
            .box-card {
                position: relative;
                margin-top: 20px;
                max-width: 400px;
                border-radius: 10px;
                .clearfix {
                    cursor: pointer;
                }
                .el-card__body {
                    font-weight: 400;
                    font-size: 14px;
                    .total {
                        // padding: 30px 0 23px;
                        line-height: 25px;
                        .gree {
                            color: #3ab293;
                        }
                        .price {
                            font-size: 24px;
                            font-weight: 800;
                            line-height: 33px;
                            margin-bottom: 5px;
                            word-break: break-all;
                        }
                        .my-order {
                            display: flex;
                            align-items: center;
                        }
                        .el-button {
                            background: #409EFF;
                            color: #fff;
                            line-height: unset;
                            .is-round {
                                padding: 0;
                            }
                        }
                    }
                    .el-button.is-round {
                        padding: 6px 10px;
                    }
                    .novice {
                        height: 24px;
                        padding: 0 10px;
                        position: absolute;
                        right: 0;
                        text-align: center;
                        top: 0;
                        background: rgba(229,87,90,.1);
                        border-radius: 0 10px 0 10px;
                        color: #e5575a;
                    }
                    .title-name {
                        // padding: 32px 0 27px;
                        text-align: center;
                        width: 100%;
                        img {
                            border: 1px solid rgba(100,114,141,.2);
                            border-radius: 50%;
                            // margin-left: -25px;
                            margin-top: 2px;
                            padding: 4px;
                        }
                        p {
                            display: inline-block;
                            margin-left: 5px;
                        }
                        span {
                            display: block;
                            text-align: left;
                        }
                        .span1 {
                            color: #3e495c;
                            font-size: 20px;
                            font-weight: 700;
                            line-height: 24px;
                            padding: 0 0 2px;
                            text-align: center;
                        }
                        .span2 {
                            color: #697384;
                            font-size: 12px;
                            font-weight: 400;
                            line-height: 18px;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            // width: 100px;
                        }
                    }
                    .interest-rate {
                        height: 62px;
                        margin-bottom: 40px;
                        text-align: center;
                        width: 100%;
                        .el-button--primary {
                            width: 170px;
                        }
                        i {
                            font-style: normal;
                        }
                        .p1 {
                            color: #3ab293;
                            font-size: 34px;
                            font-weight: 700;
                            line-height: 44px;
                        }
                        .pi {
                            color: #3ab293;
                            font-size: 18px;
                            font-weight: 700;
                            line-height: 22px;
                            margin-left: -6px;
                        }
                        .p2 {
                            color: #99a1b1;
                            font-size: 12px;
                            font-weight: 400;
                            line-height: 18px;
                            display: block;
                        }
                    }
                    .recommend-button {
                        display: block;
                        margin: 0 auto;
                        text-align: center;
                        button {
                            width: 170px;
                        }
                    }
                }
            }
            .recommend {
                // max-width: 300px;
            }
            .header {
                .title {
                    color: #0f1b30;
                    font-size: 20px;
                    font-weight: 800;
                    line-height: 40px;
                    // padding-top: 60px;
                    position: relative;
                    width: 50%;
                }
                .title:before {
                    background-color: #3378ff;
                    content: "";
                    display: inline-block;
                    height: 20px;
                    margin-right: 10px;
                    vertical-align: 0;
                    width: 4px;
                }
            }
        }
    }
</style>
