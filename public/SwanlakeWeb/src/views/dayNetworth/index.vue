<template>
    <div class="container">
        <div class="main">
            <el-descriptions :column="isMobel ? 1 : 3" :title="date">
                <el-descriptions-item label="总结余">{{ toFixed(is_networth ? count_balance : yest_count_balance, 4) }} USDT</el-descriptions-item>
                <!-- <el-descriptions-item label="总结余">{{ toFixed(yest_count_balance || 0, 2) }} USDT</el-descriptions-item> -->
                <el-descriptions-item label="总的份数">{{ toFixed(count_buy_number || 0, 4) }}</el-descriptions-item>
                <el-descriptions-item label="当前净值">{{ keepDecimalNotRounding(today_net_worth > 0 ? today_net_worth : yest_net_worth || 0, 4) }}</el-descriptions-item>
            </el-descriptions>
            <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="80px" class="demo-ruleForm">
                <el-form-item label="今日利润" prop="profit">
                    <el-input v-model="ruleForm.profit" @input="calcNewsNetWorth" @blur="calcNewsNetWorthBlur" placeholder="请输入今日利润"></el-input>
                </el-form-item>
                <el-form-item label="渠道费" prop="channel_fee">
                    <el-input v-model="ruleForm.channel_fee" placeholder="请输入渠道费"></el-input>
                </el-form-item>
                <el-form-item label="管理费" prop="management_fee">
                    <el-input v-model="ruleForm.management_fee" placeholder="请输入管理费"></el-input>
                </el-form-item>
                <el-form-item label="更新后净值" label-width="100px">
                    <span>{{ keepDecimalNotRounding(newDaynetworth || 0, 4) }}</span>
                </el-form-item>
                <el-form-item align="center" class="submit">
                    <el-button type="primary" @click="submitForm('ruleForm')">更新</el-button>
                </el-form-item>
            </el-form>
        </div>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import { get, post } from "@/common/axios.js";
import { keepDecimalNotRounding } from "@/utils/tools.js";
export default {
    name: '',
    data() {
        return {
            product_id: 1,
            date: '',
            count_balance: 0, //总的结余
            yest_count_balance: 0, //昨日总的结余
            count_buy_number: 0, //总的份数
            // count_buy_networth: 0, //总的购买净值
            today_net_worth: 1, //今日最新净值
            yest_net_worth: 1, //昨日最新净值
            newDaynetworth: 0,
            is_networth: false,
            ruleForm: {
                profit: '',
                channel_fee: '',
                management_fee: '',
            },
            rules: {
                profit: [
                    { required: true, message: '请输入利润值', trigger: 'input' },
                ],
                channel_fee: [
                    { required: true, message: '请输入渠道费', trigger: 'input' },
                ],
                management_fee: [
                    { required: true, message: '请输入管理费', trigger: 'input' },
                ],
            }
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
        submitForm(formName) {
            this.$refs[formName].validate((valid) => {
            if (valid) {
                post(this.apiUrl + "/Api/Product/saveDayNetworth", {
                    profit: this.ruleForm.profit,
                    channel_fee: this.ruleForm.channel_fee,
                    management_fee: this.ruleForm.management_fee,
                    address: this.address,
                    product_id: this.product_id,
                }, json => {
                    if (json.code == 10000) {
                        this.$message.success("更新成功");
                        this.getDayAmount();
                    } else {
                        this.$message.error("加载数据失败");
                    }
                });
            } else {
                console.log('error submit!!');
                return false;
            }
            });
        },
        resetForm(formName) {
            this.$refs[formName].resetFields();
        },
        getDayAmount() { //获取最新数值
            get(this.apiUrl + "/Api/Product/getNewsBuyAmount", {
                product_id: this.product_id,
            }, json => {
                if (json.code == 10000) {
                    this.date = json.data.date;
                    this.count_balance = json.data.count_balance;
                    this.yest_count_balance = json.data.yest_count_balance;
                    this.count_buy_number = json.data.count_buy_number;
                    // this.count_buy_networth = json.data.count_buy_networth;
                    this.today_net_worth = json.data.today_net_worth;
                    this.yest_net_worth = json.data.yest_net_worth;
                    this.is_networth = json.data.is_networth;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        calcNewsNetWorthBlur(event) { //实时根据利润值计算当天净值
            let value = event.target.value;
            this.calcNewsNetWorth(value);
        },
        calcNewsNetWorth(amount) { //实时根据利润值计算当天净值
            this.ruleForm.channel_fee = '';
            this.ruleForm.management_fee = '';
            if(amount && amount !== 0) {
                this.ruleForm.channel_fee = amount / 2;
                this.ruleForm.management_fee = amount / 2;
                get(this.apiUrl + "/Api/Product/calcNewsNetWorth", {
                    address: this.address,
                    profit: amount
                }, json => {
                    if (json.code == 10000) {
                        this.newDaynetworth = json.data;
                    } else {
                        console.log(json.msg);
                        // this.$message.error("加载数据失败");
                    }
                });
            } else {
                this.newDaynetworth = 0;
            }
        }
    },
    mounted() {
        this.getDayAmount();
    },
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            .main {
                .el-descriptions {
                    .el-descriptions__body {
                        // width: 80%;
                        margin: 0 auto;
                        .el-descriptions__table {
                            table-layout: auto;
                        }
                    }
                }
                .submit {
                    .el-form-item__content {
                        margin-left: 0 !important;
                    }
                    button {
                        width: 120px;
                    }
                }
                background-color: #fff;
                padding: 30px;
            }
        }
    }
</style>
