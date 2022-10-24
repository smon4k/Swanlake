<template>
    <div class="container">
        <el-select size="mini" v-model="name" placeholder="请选择" @change="selectChange">
            <el-option label="Cake" value="Cake"></el-option>
            <el-option label="BNB" value="BNB"></el-option>
        </el-select>
        <div class="threeBarChart" id="main"></div>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import * as echarts from 'echarts';
import { get, post } from "@/common/axios.js";
export default {
    name: '',
    data() {
        return {
            name: 'Cake',
            dataList: [],
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
        this.getHourDataList();
    },
    watch: {

    },
    mounted() {
        // this.myChart()
    },
    components: {

    },
    methods: {
        myChart() {
            // console.log(this.dataList);
            // 获取节点
            const myChart = echarts.init(document.getElementById('main'));
            let option;

            option = {
                title: {
                    // text: '折线图堆叠'
                },
                tooltip: {
                    trigger: 'item',
                    axisPointer:{       //坐标轴指示器
                        type:'cross',   //十字准星指示器
                    },
                },
                legend: {
                    data: ['总地址量', '价格'],
                    right: '0'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    axisTick: {
                        alignWithLabel: true
                    },
                    // splitNumber: 24,
                    // data: [
                    //     '2022-10-21 00:00',
                    //     '2022-10-21 02:00',
                    //     '2022-10-21 03:00',
                    //     '2022-10-21 04:00',
                    //     '2022-10-21 05:00',
                    //     '2022-10-22 01:00',
                    //     '2022-10-22 02:00',
                    //     '2022-10-22 03:00',
                    //     '2022-10-22 04:00',
                    //     '2022-10-22 05:00',
                    // ],
                    data: this.dataList.times,
                    axisLabel: {
                        interale: 0,
                        // rotate: -40, //设置日期显示样式（倾斜度）
                        formatter: function (value) {//在这里写你需要的时间格式
                            var t_date = new Date(value);
                            // console.log(t_date);
                            return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-')
                            // + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); 时分
                        }
                    }
                },
                yAxis: [
                    {
                        type: 'value',
                        // name: 'k',
                    //坐标轴最大值、最小值、强制设置数据的步长间隔
                        interval: 1000,
                        min: parseInt(this.dataList.holders.min - 100),
                        max: parseInt(this.dataList.holders.max + 100),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return parseInt(value / 1000) + 'k'
                            }
                        },
                        //轴线
                        axisLine: {
                            show: true,
                            lineStyle: {
                                color: '#F79729'
                            }
                        },
                        //分割线
                        splitLine: {
                            show: false
                        }
                    },
                    {
                        type: 'value',
                        // interval: 1200000,
                        // name: 'k',
                        min: parseInt(this.dataList.prices.min * 0.9),
                        max: parseInt(this.dataList.prices.max * 1.1),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return '$' + value
                            }
                        },
                        //轴线
                        axisLine: {
                            show: true,
                            lineStyle: {
                                color: '#7C7C7C'
                            }
                        },
                        //分割线
                        splitLine: {
                            show: false
                        }
    
                    }
                ],
                series: [
                    {
                        name: '总地址量',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 0,
                        // data: [1158820, 1128820, 1168820, 1258820, 1358820, 1458820, 1459820, 1468820, 1478820, 1488820 ],
                        data: this.dataList.holders.data,
                        itemStyle: {
                            color: '#F79729',
                        },
                    }, {
                        name: '价格',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 1,
                        // data: [0.32372331,0.11752043,0.97107555,0.62991315,0.16098689,0.59809298,0.28456582,0.14334360,0.78546394,0.00756064 ],
                        data: this.dataList.prices.data,
                        itemStyle: {
                            color: '#7C7C7C',
                        },
                    }
                ]
            };
            option && myChart.setOption(option);
        },
        getHourDataList() {
            get(this.apiUrl + "/Api/Bscaddressstatistics/getHourDataList", {
                name: this.name,
            }, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.dataList = json.data;
                    this.myChart();
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        selectChange(name) {
            this.name = name;
            this.getHourDataList();
        }
    }
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            height: 100%;
            overflow: auto;
            .threeBarChart {
                height: 500px;
                width: 100%;
            }
        }
    }
</style>
