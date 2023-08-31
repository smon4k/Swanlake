<template>
    <div class="container">
        <el-row>
            <el-col :span="12" v-if="isMobel">
                <el-select size="mini" filterable v-model="name" placeholder="请选择" @change="selectChange">
                    <el-option :label="item.name" :value="item.name" v-for="(item, index) in optionData" :key="index"></el-option>
                </el-select>
            </el-col>
            <el-col :span="24" align="left" v-else style="overflow-x: scroll;overflow-y: hidden;">
                <div class="search">
                    <!-- <span>币种选择：</span> -->
                    <div v-for="(item, index) in optionData" :key="index" :class="['button', {'button-active': currencyIndex == item.id}]" :tabindex="item.index" @click="selectButton(item.name, item.id, item.token)">{{ item.name }}</div>
                </div>
            </el-col>
            <br>
            <el-col :span="24" align="left" style="margin-top:10px;">
                <div class="search times">
                    <!-- <span>时间范围：</span> -->
                    <div :class="['button', {'button-active': timesIndex == 1}]" tabindex="1" @click="searchClick('1 day', 1)">1天</div>
                    <div :class="['button', {'button-active': timesIndex == 2}]" tabindex="2" @click="searchClick('1 week', 2)">1周</div>
                    <div :class="['button', {'button-active': timesIndex == 3}]" tabindex="3" @click="searchClick('1 month', 3)">1月</div>
                    <div :class="['button', {'button-active': timesIndex == 4}]" tabindex="4" @click="searchClick('3 month', 4)">3月</div>
                    <div :class="['button', {'button-active': timesIndex == 5}]" tabindex="5" @click="searchClick('6 month', 5)">6月</div>
                    <div :class="['button', {'button-active': timesIndex == 6}]" tabindex="6" @click="searchClick('year', 6)">本年</div>
                    <div :class="['button', {'button-active': timesIndex == 7}]" tabindex="7" @click="searchClick('1 year', 7)">1年</div>
                    <div :class="['button', {'button-active': timesIndex == 8}]" tabindex="8" @click="searchClick('all', 8)">全部</div>
                    &nbsp;&nbsp;
                    <el-date-picker
                        v-model="start_end_time"
                        type="daterange"
                        range-separator="至"
                        start-placeholder="开始日期"
                        end-placeholder="结束日期"
                        value-format="yyyy-MM-dd"
                        @change="timesChange">
                    </el-date-picker>
                </div>
                <br>
            </el-col>
        </el-row>
        <el-tabs v-model="activeName" @tab-click="tabHandleClick">
            <el-tab-pane label="总地址量" name="1">
                <div style="text-align:right;" v-if="name !== 'BTC(稳定币)'">   
                    Address:  
                    <el-link v-if="name === 'TON(ETH)'" :underline="false" style="text-decoration:underline;" :href="'https://www.oklink.com/zh-cn/eth/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <el-link v-else :underline="false" style="text-decoration:underline;" :href="'https://bscscan.com/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <i class="el-icon-document-copy" style="cursor: pointer" v-clipboard:copy="selectAddress" v-clipboard:success="copySuccess"></i>
                </div>
                <div class="left-text" v-if="Object.keys(dataList).length && name !== 'BTC(稳定币)'">起始地址数：{{ numberFormatFilter(dataList.holders.data[0]) }} 最终地址数：{{ numberFormatFilter(dataList.holders.data[dataList.holders.data.length - 1]) }} 增加地址数：{{ numberFormatFilter(dataList.holders.add_holders) }} 增加百分比：{{ toFixed(dataList.holders.add_percentage, 4) }}%</div>
                <div v-if="activeName == 1 && Object.keys(dataList).length" class="threeBarChart" id="countAddress"></div>
                <el-empty v-else description="没有数据"></el-empty>
            </el-tab-pane>
            <el-tab-pane label="新增地址量" name="2">
                <div style="text-align:right;" v-if="name !== 'BTC(稳定币)'">  
                    Address:  
                    <el-link v-if="name === 'TON(ETH)'" :underline="false" style="text-decoration:underline;" :href="'https://www.oklink.com/zh-cn/eth/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <el-link v-else :underline="false" style="text-decoration:underline;" :href="'https://bscscan.com/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <i class="el-icon-document-copy" style="cursor: pointer" v-clipboard:copy="selectAddress" v-clipboard:success="copySuccess"></i>
                </div>
                <div v-if="activeName == 2 && Object.keys(dataList).length" class="threeBarChart" id="addAddress"></div>
                <el-empty v-else description="没有数据"></el-empty>
            </el-tab-pane>

            <el-tab-pane label="总销毁量" name="3" v-if="name !== 'BTC(稳定币)'">
                <div style="text-align:right;">  
                    Address:  
                    <el-link v-if="name === 'TON(ETH)'" :underline="false" style="text-decoration:underline;" :href="'https://www.oklink.com/zh-cn/eth/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <el-link v-else :underline="false" style="text-decoration:underline;" :href="'https://bscscan.com/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <i class="el-icon-document-copy" style="cursor: pointer" v-clipboard:copy="selectAddress" v-clipboard:success="copySuccess"></i>
                </div>
                <div class="left-text" v-if="Object.keys(destructionDataList).length">起始销毁数：{{ numberFormatFilter(destructionDataList.balances.data[0]) }} 最终销毁数：{{ numberFormatFilter(destructionDataList.balances.data[destructionDataList.balances.data.length - 1]) }} 增加销毁数：{{ numberFormatFilter(destructionDataList.balances.add_destroy) }} 增加百分比：{{ toFixed(destructionDataList.balances.add_percentage, 4) }}%</div>
                <div v-if="currencyIndex == 2" style="position: absolute;right: 190px;font-size: 13px;line-height: 23px;">自动销毁 {{ numberFormatFilter(autoDestruction) }} BNB, 手续费实时销毁 {{ numberFormatFilter(bnbNewValues) }} BNB，总计销毁（{{numberFormatFilter(autoDestruction + bnbNewValues)}}）BNB</div>
                <div v-if="activeName == 3 && Object.keys(destructionDataList).length" class="threeBarChart" id="countDestruction"></div>
                <el-empty v-else description="没有数据"></el-empty>
            </el-tab-pane>
            <el-tab-pane label="新增销毁量" name="4" v-if="name !== 'BTC(稳定币)'">
                <div style="text-align:right;">  
                    Address:  
                    <el-link v-if="name === 'TON(ETH)'" :underline="false" style="text-decoration:underline;" :href="'https://www.oklink.com/zh-cn/eth/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <el-link v-else :underline="false" style="text-decoration:underline;" :href="'https://bscscan.com/token/' + selectAddress" target="_blank">{{ strAddress() }}</el-link>
                    <i class="el-icon-document-copy" style="cursor: pointer" v-clipboard:copy="selectAddress" v-clipboard:success="copySuccess"></i>
                </div>
                <div v-if="currencyIndex == 2" style="position: absolute;right: 200px;font-size: 13px;line-height: 23px;">自动销毁 {{ numberFormatFilter(autoDestruction) }} BNB, 手续费实时销毁 {{ numberFormatFilter(bnbNewValues) }} BNB，总计销毁（{{numberFormatFilter(autoDestruction + bnbNewValues)}}）BNB</div>
                <div v-if="activeName == 4 && Object.keys(destructionDataList).length" class="threeBarChart" id="addDestruction"></div>
                <el-empty v-else description="没有数据"></el-empty>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>
<script>
import { mapGetters, mapState } from "vuex";
import * as echarts from 'echarts';
import { get, post } from "@/common/axios.js";
import { numberFormat, keepDecimalNotRounding } from "@/utils/tools.js";
export default {
    name: '',
    data() {
        return {
            currencyIndex: 32,
            timesIndex: 1,
            activeName: '1',
            name: 'BTC(稳定币)',
            selectAddress: '0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82',
            dataList: [],
            destructionDataList: [],
            start_end_time: '',
            time_range: '1 day',
            this_year: '', //是否本年度
            bnbNewValues: 0, //BNB最新销毁量
            autoDestruction: 0, //BNB自动销毁量
            optionData: [],
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
        this.getTokensList();
        this.getHourDataList();
        this.getDestructionDataList();
    },
    watch: {

    },
    mounted() {
        // this.myChart()
    },
    components: {

    },
    methods: {
        myAddAddressChart() { //新增地址量
            // console.log(this.dataList);
            // 获取节点
            const myChart = echarts.init(document.getElementById('addAddress'));
            let option;
            let _this = this;
            option = {
                title: {
                    // text: '折线图堆叠'
                },
                tooltip: {
                    trigger: 'axis',
                    extraCssText: 'width:300px;height:auto;background-color:#fff;color:#333',
                    axisPointer:{       //坐标轴指示器
                        type:'cross',   //十字准星指示器
                    },
                    formatter: function (params) {
                        // console.log(params);
                        let str = params[0].name + '<br/>'
                        for (let item of params) {
                            if(item.seriesIndex == 0) {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>"+item.value+"</span>" + "<br/>"
                            } else {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + '$' + keepDecimalNotRounding(Number(item.value), 18, true) + "</span>" + "<br/>"
                            }
                        }
                        return str
                    }
                },
                legend: {
                    data: ['新增地址量', _this.name + ' 价格'],
                    right: '0'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                dataZoom: { // 放大和缩放
                    type: 'inside'
                },
                xAxis: {
                    type: 'category',
                    axisTick: {
                        alignWithLabel: true
                    },
                    data: this.dataList.times,
                    axisLabel: {
                        interale: 0,
                        // rotate: -40, //设置日期显示样式（倾斜度）
                        formatter: function (value) {//在这里写你需要的时间格式
                            var t_date = new Date(value);
                            // console.log(t_date);
                            if(_this.timesIndex == 1) {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-')
                                + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); //时分
                            } else {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-');
                            }
                        }
                    }
                },
                yAxis: [
                    {
                        type: 'value',
                        // name: 'k',
                    //坐标轴最大值、最小值、强制设置数据的步长间隔
                        // interval: 1000,
                        min: parseInt(this.dataList.addAddress.min - 100),
                        max: parseInt(this.dataList.addAddress.max + 100),
                        axisLabel: {
                            //y轴上带的单位
                            // formatter: function(value) { // y轴自定义数据
                            //     return parseInt(value / 10000) + 'k'
                            // }
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
                        // interval: 1,
                        min: Number(this.dataList.prices.min) - (Number(this.dataList.prices.min) * 0.01),
                        max: Number(this.dataList.prices.max) + (Number(this.dataList.prices.max) * 0.01),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return '$' + keepDecimalNotRounding(value, 18, true)
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
                        name: '新增地址量',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 0,
                        // data: [1158820, 1128820, 1168820, 1258820, 1358820, 1458820, 1459820, 1468820, 1478820, 1488820 ],
                        data: this.dataList.addAddress.data,
                        itemStyle: {
                            color: '#F79729',
                        },
                        // areaStyle: {}
                    }, {
                        name: _this.name + ' 价格',
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
        myCountAddressChart() { //总地址量
            // console.log(this.dataList);
            // 获取节点
            const myChart = echarts.init(document.getElementById('countAddress'));

            // 构建 ECharts 的 legend 数据
            let option;
            let _this = this;
            let legendData = ["总地址量", _this.name + ' 价格'];
            let combinedData = [];
            let seriesData = [];
            if( _this.name == "BTC(稳定币)") {
                const keys = Object.keys(this.dataList.other_data);
                combinedData = legendData.concat(keys);
                Object.keys(this.dataList.other_data).forEach((key, index) => {
                    // console.log(key, index);
                    seriesData.push({
                        name: key,
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 0,
                        data: this.dataList.other_data[key],
                    });
                });

            }

            // console.log(seriesData);
            // console.log(dataIndex, this.dataList.other_data[dataIndex]);
            option = {
                title: {
                    // text: '折线图堆叠'
                },
                tooltip: {
                    trigger: 'axis',
                    extraCssText: 'width:300px;height:auto;background-color:#fff;color:#333',
                    axisPointer:{       //坐标轴指示器
                        type:'cross',   //十字准星指示器
                    },
                    formatter: function (params) {
                        // console.log(params);
                        let str = params[0].name + '<br/>'
                        for (let item of params) {
                            if(item.seriesIndex == 0) {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + numberFormat(item.value) + "</span>" + "<br/>"
                            } else {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + '$' + numberFormat(keepDecimalNotRounding(Number(item.value), 18, true)) + "</span>" + "<br/>"
                            }
                        }
                        return str
                    }
                },
                legend: {
                    data: combinedData,
                    right: '0'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                dataZoom: { // 放大和缩放
                    type: 'inside'
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
                            if(_this.timesIndex == 1) {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-')
                                + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); //时分
                            } else {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-');
                            }
                            // + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); //时分
                        }
                    }
                },
                yAxis: [
                    {
                        type: 'value',
                        // name: 'k',
                    //坐标轴最大值、最小值、强制设置数据的步长间隔
                        // interval: 1000,
                        min: parseInt(this.dataList.holders.min - 100),
                        max: parseInt(this.dataList.holders.max + 100),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                // return parseInt(value / 1000) + 'k'
                                return _this.UnitConversion(value);
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
                        min: Number(this.dataList.prices.min) - (Number(this.dataList.prices.min) * 0.01),
                        max: Number(this.dataList.prices.max) + (Number(this.dataList.prices.max) * 0.01),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return '$' + keepDecimalNotRounding(value, 18, true)
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
                        name: _this.name + ' 价格',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 1,
                        // data: [0.32372331,0.11752043,0.97107555,0.62991315,0.16098689,0.59809298,0.28456582,0.14334360,0.78546394,0.00756064 ],
                        data: this.dataList.prices.data,
                        itemStyle: {
                            color: '#7C7C7C',
                        },
                    }, 
                    ...seriesData
                ]
            };
            option && myChart.setOption(option);
        },
        myCountDestructionChart() { //总销毁量
            // console.log(this.dataList);
            // 获取节点
            const myChart = echarts.init(document.getElementById('countDestruction'));
            let option;
            let _this = this;

            option = {
                title: {
                    // text: '折线图堆叠'
                },
                tooltip: {
                    trigger: 'axis',
                    extraCssText: 'width:300px;height:auto;background-color:#fff;color:#333',
                    axisPointer:{       //坐标轴指示器
                        type:'cross',   //十字准星指示器
                    },
                    formatter: function (params) {
                        // console.log(params);
                        let str = params[0].name + '<br/>'
                        for (let item of params) {
                            if(item.seriesIndex == 0) {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + numberFormat(item.value) + "</span>" + "<br/>"
                            } else {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + '$' + keepDecimalNotRounding(Number(item.value), 18, true) + "</span>" + "<br/>"
                            }
                        }
                        return str
                    }
                },
                legend: {
                    data: ['总销毁量', _this.name + ' 价格'],
                    right: '0'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                dataZoom: { // 放大和缩放
                    type: 'inside'
                },
                xAxis: {
                    type: 'category',
                    axisTick: {
                        alignWithLabel: true
                    },
                    data: this.destructionDataList.times,
                    axisLabel: {
                        interale: 0,
                        // rotate: -40, //设置日期显示样式（倾斜度）
                        formatter: function (value) {//在这里写你需要的时间格式
                            var t_date = new Date(value);
                            // console.log(t_date);
                            if(_this.timesIndex == 1) {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-')
                                + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); //时分
                            } else {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-');
                            }
                        }
                    }
                },
                yAxis: [
                    {
                        type: 'value',
                        // name: 'k',
                    //坐标轴最大值、最小值、强制设置数据的步长间隔
                        // interval: 100000000,
                        min: parseInt(Number(this.destructionDataList.balances.min) - (Number(this.destructionDataList.balances.min) * 0.01)),
                        max: parseInt(Number(this.destructionDataList.balances.max) + (Number(this.destructionDataList.balances.max)) * 0.01),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return _this.UnitConversion(value);
                                // return parseInt(value / 1000) + 'k'
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
                        min: Number(this.destructionDataList.prices.min) - (Number(this.destructionDataList.prices.min) * 0.01),
                        max: Number(this.destructionDataList.prices.max) + (Number(this.destructionDataList.prices.max) * 0.01),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return '$' + keepDecimalNotRounding(value, 18, true);
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
                        name: '总销毁量',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 0,
                        // data: [1158820, 1128820, 1168820, 1258820, 1358820, 1458820, 1459820, 1468820, 1478820, 1488820 ],
                        data: this.destructionDataList.balances.data,
                        itemStyle: {
                            color: '#F79729',
                        },
                    }, {
                        name: _this.name + ' 价格',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 1,
                        // data: [0.32372331,0.11752043,0.97107555,0.62991315,0.16098689,0.59809298,0.28456582,0.14334360,0.78546394,0.00756064 ],
                        data: this.destructionDataList.prices.data,
                        itemStyle: {
                            color: '#7C7C7C',
                        },
                    }
                ]
            };
            option && myChart.setOption(option);
        },
        myAddDestructionChart() { //新增销毁量
            // console.log(this.dataList);
            // 获取节点
            const myChart = echarts.init(document.getElementById('addDestruction'));
            let option;
            let _this = this;
            option = {
                title: {
                    // text: '折线图堆叠'
                },
                tooltip: {
                    trigger: 'axis',
                    extraCssText: 'width:300px;height:auto;background-color:#fff;color:#333',
                    axisPointer:{       //坐标轴指示器
                        type:'cross',   //十字准星指示器
                    },
                    formatter: function (params) {
                        // console.log(params);
                        let str = params[0].name + '<br/>'
                        for (let item of params) {
                            if(item.seriesIndex == 0) {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>"+_this.toFixed(item.value, 4)+"</span>" + "<br/>"
                            } else {
                                str += "<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:" + item.color + ";'></span>&nbsp;" + item.seriesName + ": " + "<span style='float:right;'>" + '$' + keepDecimalNotRounding(Number(item.value), 18, true) + "</span>" + "<br/>"
                            }
                        }
                        return str
                    }
                },
                legend: {
                    data: ['新增销毁量', _this.name + ' 价格'],
                    right: '0'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                dataZoom: { // 放大和缩放
                    type: 'inside'
                },
                xAxis: {
                    type: 'category',
                    axisTick: {
                        alignWithLabel: true
                    },
                    data: this.destructionDataList.times,
                    axisLabel: {
                        interale: 0,
                        // rotate: -40, //设置日期显示样式（倾斜度）
                        formatter: function (value) {//在这里写你需要的时间格式
                            var t_date = new Date(value);
                            // console.log(t_date);
                            if(_this.timesIndex == 1) {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-')
                                + " " + [t_date.getHours(), t_date.getMinutes()].join(':'); //时分
                            } else {
                                return [t_date.getFullYear(), t_date.getMonth() + 1, t_date.getDate()].join('-');
                            }
                        }
                    }
                },
                yAxis: [
                    {
                        type: 'value',
                        // name: 'k',
                    //坐标轴最大值、最小值、强制设置数据的步长间隔
                        // interval: 1000,
                        min: Number(this.destructionDataList.addBalances.min) - Number(this.destructionDataList.addBalances.min) * 0.1,
                        max: Number(this.destructionDataList.addBalances.max) + Number(this.destructionDataList.addBalances.max) * 0.1,
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return _this.UnitConversion(value);
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
                        // interval: 1,
                        min: Number(this.destructionDataList.prices.min) - (Number(this.destructionDataList.prices.min) * 0.01),
                        max: Number(this.destructionDataList.prices.max) + (Number(this.destructionDataList.prices.max) * 0.01),
                        axisLabel: {
                            //y轴上带的单位
                            formatter: function(value) { // y轴自定义数据
                                return '$' + keepDecimalNotRounding(value, 18, true)
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
                        name: '新增销毁量',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 0,
                        // data: [1158820, 1128820, 1168820, 1258820, 1358820, 1458820, 1459820, 1468820, 1478820, 1488820 ],
                        data: this.destructionDataList.addBalances.data,
                        itemStyle: {
                            color: '#F79729',
                        },
                        // areaStyle: {}
                    }, {
                        name: _this.name + ' 价格',
                        type: 'line',
                        symbolSize: 5, // 设置折线上圆点大小
                        symbol: 'circle', // 设置拐点为实心圆
                        yAxisIndex: 1,
                        // data: [0.32372331,0.11752043,0.97107555,0.62991315,0.16098689,0.59809298,0.28456582,0.14334360,0.78546394,0.00756064 ],
                        data: this.destructionDataList.prices.data,
                        itemStyle: {
                            color: '#7C7C7C',
                        },
                    }
                ]
            };
            option && myChart.setOption(option);
        },
        getTokensList() {
            get(this.apiUrl + "/Api/Bscaddressstatistics/getTokensList", {}, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.optionData = json.data;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getHourDataList() { //获取总地址和新增地址量
            let start_time = '';
            let end_time = '';
            if(this.start_end_time && this.start_end_time.length > 0) {
                start_time = this.start_end_time[0];
                end_time = this.start_end_time[1];
            }
            get(this.apiUrl + "/Api/Bscaddressstatistics/getHourDataList", {
                name: this.name,
                this_year: this.this_year,
                time_range: this.time_range,
                start_time: start_time,
                end_time: end_time,
            }, json => {
                console.log(json);
                if (json.code == 10000) {
                    if(Object.keys(json.data).length) {
                        this.dataList = json.data;
                        this.$nextTick(() => {
                            if(this.activeName == 1) {
                                console.log(111);
                                this.myCountAddressChart(); 
                            }    
                            if(this.activeName == 2) {
                                this.myAddAddressChart();  
                            }               
                        })                  
                    } else {
                        this.dataList = [];
                    }
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getDestructionDataList() { //获取总销毁和新增销毁量
            let start_time = '';
            let end_time = '';
            if(this.start_end_time && this.start_end_time.length > 0) {
                start_time = this.start_end_time[0];
                end_time = this.start_end_time[1];
            }
            get(this.apiUrl + "/Api/Bscaddressstatistics/getDestructionDataList", {
                name: this.name,
                this_year: this.this_year,
                time_range: this.time_range,
                start_time: start_time,
                end_time: end_time,
            }, json => {
                console.log(json);
                if (json.code == 10000) {
                    if(Object.keys(json.data).length) {
                        this.destructionDataList = json.data;
                        this.autoDestruction = Number(json.data.autoDestruction);
                        this.bnbNewValues = Number(json.data.bnbNewValues);
                        this.$nextTick(() => {
                            if(this.activeName == 3) {
                                this.myCountDestructionChart(); 
                            }    
                            if(this.activeName == 4) {
                                this.myAddDestructionChart();  
                            }               
                        })                  
                    } else {
                        this.destructionDataList = [];
                    }
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        searchClick(name, index) { //时间筛选
            this.timesIndex = index;
            if(name && name !== '') {
                this.start_end_time = '';
                if(name === 'all') {
                    this.this_year = '';
                    this.time_range = '';
                    this.getHourDataList();
                    this.getDestructionDataList();
                } else if(name === 'year') {
                    this.this_year = name;
                    this.time_range = '';
                    this.getHourDataList();
                    this.getDestructionDataList();
                } else {
                    this.this_year = '';
                    this.time_range = name;
                    this.getHourDataList();
                    this.getDestructionDataList();
                }
            }
        },
        timesChange(val) { //时间筛选
            console.log(val);
            this.this_year = '';
            this.time_range = '';
            if(val == null) {
                this.timesIndex = 8;
            } else {
                this.timesIndex = 0;
            }
            if(this.activeName == 1 || this.activeName == 2) {
                this.getHourDataList();
            } else {
                this.getDestructionDataList();
            }
        },
        selectButton(name, index, token) { //按钮筛选币种
            this.currencyIndex = index;
            this.name = name;
            this.selectAddress = token;
            if(this.activeName == 1 || this.activeName == 2) {
                this.getHourDataList();
            } else {
                this.getDestructionDataList();
            }
        },
        selectChange(name) { //下拉框筛选币种
            if(name && name !== '') {
                let obj = this.optionData.find((item)=>{ // 这里的userList就是上面遍历的数据源
                    return item.name === name; // 筛选出匹配数据
                });
                this.currencyIndex = obj.id;
                this.selectAddress = obj.token;
                if(this.activeName == 1 || this.activeName == 2) {
                    this.getHourDataList();
                } else {
                    this.getDestructionDataList();
                }
            }
        },
        tabHandleClick(tab, event) {
            console.log(tab, event);
            if(tab.name == 1 || tab.name == 2) {
                this.getHourDataList();
            } else {
                this.getDestructionDataList();
            }
            // if(tab.name == 1) {
            //     if(Object.keys(this.dataList).length) {
            //         this.$nextTick(() => {
            //             this.myCountAddressChart();
            //         })
            //     }
            // }
            // if(tab.name == 2) {
            //     if(Object.keys(this.dataList).length) {
            //         this.$nextTick(() => {
            //             this.myAddAddressChart();
            //         })
            //     }
            // }
            // if(tab.name == 3) {
            //     if(Object.keys(this.destructionDataList).length) {
            //         this.$nextTick(() => {
            //             this.myCountDestructionChart();
            //         })
            //     }
            // }
            // if(tab.name == 4) {
            //     if(Object.keys(this.destructionDataList).length) {
            //         this.$nextTick(() => {
            //             this.myAddDestructionChart();
            //         })
            //     }
            // }
        },
        UnitConversion(value){ //数值转换
            let param = {}
            let k = 10000
            let sizes = ['', '万', '亿', '万亿', '百万亿', '千万亿', '兆', '十兆', '百兆', '千兆']
            let i
            if (value < k) {
                param.value = value
                param.unit = ''
            } else {
                i = Math.floor(Math.log(value) / Math.log(k));
                // console.log(i);
                param.value = ((value / Math.pow(k, i))).toFixed(2);
                param.unit = sizes[i];
            }
            console.log(param)
            return param.value + param.unit;
        },
        numberFormatFilter(num) {
            return numberFormat(num);
        },
        strAddress() {
            // console.log(this.selectAddress);
            if(this.selectAddress && this.selectAddress !== null) {
                return this.selectAddress.substring(0, 4) + "***" + this.selectAddress.substring(this.selectAddress.length - 3)
            }
        },
        copySuccess(){
            this.$message({
                message: 'Copy successfully',
                type: 'success'
            });
        },
    }
}
</script>
<style lang="scss" scoped>
    .container {
        /deep/ {
            height: 100%;
            overflow: auto;
            .left-text {
                position: absolute;
                left: 0;
                font-size: 13px;
                line-height: 23px;
            }
            .threeBarChart {
                height: 500px;
                width: 100%;
            }
            .search {
                // float: right;
                display: inline-flex;
                // height: 56px;
                align-items: center;
                justify-content: flex-start;
                .button {
                    // background-color: #f1f1f1;
                    background-color: #e6e6e6;
                    height: 22px;
                    width: 70px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    color: #333;
                    padding-left: 10px;
                    padding-right: 10px;
                    text-align: center;
                    line-height: .9;
                    margin-left: 4px;
                    cursor: pointer;
                    user-select: none;
                }
                .button-active {
                    background-color: #bfbbbb;
                }
                .button:active {
                    background-color: #bfbbbb;
                }
                .button:focus {
                    background-color: #bfbbbb;
                }
                .el-input__inner {
                    width: 240px;
                    padding: 0 3px 0 3px;
                    height: 30px;
                    line-height: 30px;
                    font-size: 10px;
                }
                .el-date-editor .el-range-separator {
                    line-height: 27px;
                }
                .el-date-editor .el-range__icon {
                    line-height: 28px;
                }
            }
            .times {
                .button {
                    width: 30px;
                }
            }
        }
    }
</style>
