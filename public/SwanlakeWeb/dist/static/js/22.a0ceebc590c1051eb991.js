webpackJsonp([22],{"+1KM":function(t,e,a){var i=a("M5us");"string"==typeof i&&(i=[[t.i,i,""]]),i.locals&&(t.exports=i.locals);a("rjj0")("987a91d6",i,!0,{})},M5us:function(t,e,a){(t.exports=a("FZ+f")(!0)).push([t.i,".container[data-v-22bd2a27]{height:100%;overflow:auto}.container[data-v-22bd2a27] .left-text{position:absolute;left:0;font-size:13px;line-height:23px}.container[data-v-22bd2a27] .threeBarChart{height:500px;width:100%}.container[data-v-22bd2a27] .search{display:inline-flex;align-items:center;justify-content:flex-start}.container[data-v-22bd2a27] .search .button{background-color:#e6e6e6;height:22px;width:70px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#333;padding-left:10px;padding-right:10px;text-align:center;line-height:.9;margin-left:4px;cursor:pointer;user-select:none}.container[data-v-22bd2a27] .search .button-active,.container[data-v-22bd2a27] .search .button:active,.container[data-v-22bd2a27] .search .button:focus{background-color:#bfbbbb}.container[data-v-22bd2a27] .search .el-input__inner{width:240px;padding:0 3px;height:30px;line-height:30px;font-size:10px}.container[data-v-22bd2a27] .search .el-date-editor .el-range-separator{line-height:27px}.container[data-v-22bd2a27] .search .el-date-editor .el-range__icon{line-height:28px}.container[data-v-22bd2a27] .times .button{width:30px}","",{version:3,sources:["C:/Users/v-linhuiqin/www/Swanlake/public/SwanlakeWeb/src/views/bscTokenStatistics/index.vue"],names:[],mappings:"AACA,4BAA4B,YAAY,aAAa,CACpD,AACD,uCAAuC,kBAAkB,OAAO,eAAe,gBAAgB,CAC9F,AACD,2CAA2C,aAAa,UAAU,CACjE,AACD,oCAAoC,oBAAoB,mBAAmB,0BAA0B,CACpG,AACD,4CAA4C,yBAAyB,YAAY,WAAW,aAAa,mBAAmB,uBAAuB,eAAe,WAAW,kBAAkB,mBAAmB,kBAAkB,eAAe,gBAAgB,eAAe,gBAAgB,CACjS,AAKD,wJAAkD,wBAAwB,CACzE,AACD,qDAAqD,YAAY,cAAoB,YAAY,iBAAiB,cAAc,CAC/H,AACD,wEAAwE,gBAAgB,CACvF,AACD,oEAAoE,gBAAgB,CACnF,AACD,2CAA2C,UAAU,CACpD",file:"index.vue",sourcesContent:["\n.container[data-v-22bd2a27]{height:100%;overflow:auto\n}\n.container[data-v-22bd2a27] .left-text{position:absolute;left:0;font-size:13px;line-height:23px\n}\n.container[data-v-22bd2a27] .threeBarChart{height:500px;width:100%\n}\n.container[data-v-22bd2a27] .search{display:inline-flex;align-items:center;justify-content:flex-start\n}\n.container[data-v-22bd2a27] .search .button{background-color:#e6e6e6;height:22px;width:70px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#333;padding-left:10px;padding-right:10px;text-align:center;line-height:.9;margin-left:4px;cursor:pointer;user-select:none\n}\n.container[data-v-22bd2a27] .search .button-active{background-color:#bfbbbb\n}\n.container[data-v-22bd2a27] .search .button:active{background-color:#bfbbbb\n}\n.container[data-v-22bd2a27] .search .button:focus{background-color:#bfbbbb\n}\n.container[data-v-22bd2a27] .search .el-input__inner{width:240px;padding:0 3px 0 3px;height:30px;line-height:30px;font-size:10px\n}\n.container[data-v-22bd2a27] .search .el-date-editor .el-range-separator{line-height:27px\n}\n.container[data-v-22bd2a27] .search .el-date-editor .el-range__icon{line-height:28px\n}\n.container[data-v-22bd2a27] .times .button{width:30px\n}\n"],sourceRoot:""}])},RmIh:function(t,e,a){"use strict";var i={render:function(){var t=this,e=t.$createElement,a=t._self._c||e;return a("div",{staticClass:"container"},[a("el-row",[t.isMobel?a("el-col",{attrs:{span:12}},[a("el-select",{attrs:{size:"mini",filterable:"",placeholder:"请选择"},on:{change:t.selectChange},model:{value:t.name,callback:function(e){t.name=e},expression:"name"}},t._l(t.optionData,function(t,e){return a("el-option",{key:e,attrs:{label:t.name,value:t.name}})}),1)],1):a("el-col",{staticStyle:{"overflow-x":"scroll","overflow-y":"hidden"},attrs:{span:24,align:"left"}},[a("div",{staticClass:"search"},t._l(t.optionData,function(e,i){return a("div",{key:i,class:["button",{"button-active":t.currencyIndex==e.id}],attrs:{tabindex:e.index},on:{click:function(a){return t.selectButton(e.name,e.id,e.token)}}},[t._v(t._s(e.name))])}),0)]),t._v(" "),a("br"),t._v(" "),a("el-col",{staticStyle:{"margin-top":"10px"},attrs:{span:24,align:"left"}},[a("div",{staticClass:"search times"},[a("div",{class:["button",{"button-active":1==t.timesIndex}],attrs:{tabindex:"1"},on:{click:function(e){return t.searchClick("1 day",1)}}},[t._v("1天")]),t._v(" "),a("div",{class:["button",{"button-active":2==t.timesIndex}],attrs:{tabindex:"2"},on:{click:function(e){return t.searchClick("1 week",2)}}},[t._v("1周")]),t._v(" "),a("div",{class:["button",{"button-active":3==t.timesIndex}],attrs:{tabindex:"3"},on:{click:function(e){return t.searchClick("1 month",3)}}},[t._v("1月")]),t._v(" "),a("div",{class:["button",{"button-active":4==t.timesIndex}],attrs:{tabindex:"4"},on:{click:function(e){return t.searchClick("3 month",4)}}},[t._v("3月")]),t._v(" "),a("div",{class:["button",{"button-active":5==t.timesIndex}],attrs:{tabindex:"5"},on:{click:function(e){return t.searchClick("6 month",5)}}},[t._v("6月")]),t._v(" "),a("div",{class:["button",{"button-active":6==t.timesIndex}],attrs:{tabindex:"6"},on:{click:function(e){return t.searchClick("year",6)}}},[t._v("本年")]),t._v(" "),a("div",{class:["button",{"button-active":7==t.timesIndex}],attrs:{tabindex:"7"},on:{click:function(e){return t.searchClick("1 year",7)}}},[t._v("1年")]),t._v(" "),a("div",{class:["button",{"button-active":8==t.timesIndex}],attrs:{tabindex:"8"},on:{click:function(e){return t.searchClick("all",8)}}},[t._v("全部")]),t._v("\r\n                      \r\n                    "),a("el-date-picker",{attrs:{type:"daterange","range-separator":"至","start-placeholder":"开始日期","end-placeholder":"结束日期","value-format":"yyyy-MM-dd"},on:{change:t.timesChange},model:{value:t.start_end_time,callback:function(e){t.start_end_time=e},expression:"start_end_time"}})],1),t._v(" "),a("br")])],1),t._v(" "),a("el-tabs",{on:{"tab-click":t.tabHandleClick},model:{value:t.activeName,callback:function(e){t.activeName=e},expression:"activeName"}},[a("el-tab-pane",{attrs:{label:"BTC(稳定币)"==t.name?"总量":"总地址量",name:"1"}},["BTC(稳定币)"!==t.name?a("div",{staticStyle:{"text-align":"right"}},[t._v("   \r\n                    Address:  \r\n                    "),"TON(ETH)"===t.name?a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://www.oklink.com/zh-cn/eth/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]):a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://bscscan.com/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]),t._v(" "),a("i",{directives:[{name:"clipboard",rawName:"v-clipboard:copy",value:t.selectAddress,expression:"selectAddress",arg:"copy"},{name:"clipboard",rawName:"v-clipboard:success",value:t.copySuccess,expression:"copySuccess",arg:"success"}],staticClass:"el-icon-document-copy",staticStyle:{cursor:"pointer"}})],1):t._e(),t._v(" "),Object.keys(t.dataList).length&&"BTC(稳定币)"!==t.name?a("div",{staticClass:"left-text"},[t._v("起始地址数："+t._s(t.numberFormatFilter(t.dataList.holders.data[0]))+" 最终地址数："+t._s(t.numberFormatFilter(t.dataList.holders.data[t.dataList.holders.data.length-1]))+" 增加地址数："+t._s(t.numberFormatFilter(t.dataList.holders.add_holders))+" 增加百分比："+t._s(t.toFixed(t.dataList.holders.add_percentage,4))+"%")]):t._e(),t._v(" "),1==t.activeName&&Object.keys(t.dataList).length?a("div",{staticClass:"threeBarChart",attrs:{id:"countAddress"}}):a("el-empty",{attrs:{description:"没有数据"}})],1),t._v(" "),a("el-tab-pane",{attrs:{label:"BTC(稳定币)"==t.name?"新增量":"新增地址量",name:"2"}},["BTC(稳定币)"!==t.name?a("div",{staticStyle:{"text-align":"right"}},[t._v("  \r\n                    Address:  \r\n                    "),"TON(ETH)"===t.name?a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://www.oklink.com/zh-cn/eth/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]):a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://bscscan.com/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]),t._v(" "),a("i",{directives:[{name:"clipboard",rawName:"v-clipboard:copy",value:t.selectAddress,expression:"selectAddress",arg:"copy"},{name:"clipboard",rawName:"v-clipboard:success",value:t.copySuccess,expression:"copySuccess",arg:"success"}],staticClass:"el-icon-document-copy",staticStyle:{cursor:"pointer"}})],1):t._e(),t._v(" "),2==t.activeName&&Object.keys(t.dataList).length?a("div",{staticClass:"threeBarChart",attrs:{id:"addAddress"}}):a("el-empty",{attrs:{description:"没有数据"}})],1),t._v(" "),"BTC(稳定币)"!==t.name?a("el-tab-pane",{attrs:{label:"总销毁量",name:"3"}},[a("div",{staticStyle:{"text-align":"right"}},[t._v("  \r\n                    Address:  \r\n                    "),"TON(ETH)"===t.name?a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://www.oklink.com/zh-cn/eth/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]):a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://bscscan.com/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]),t._v(" "),a("i",{directives:[{name:"clipboard",rawName:"v-clipboard:copy",value:t.selectAddress,expression:"selectAddress",arg:"copy"},{name:"clipboard",rawName:"v-clipboard:success",value:t.copySuccess,expression:"copySuccess",arg:"success"}],staticClass:"el-icon-document-copy",staticStyle:{cursor:"pointer"}})],1),t._v(" "),Object.keys(t.destructionDataList).length?a("div",{staticClass:"left-text"},[t._v("起始销毁数："+t._s(t.numberFormatFilter(t.destructionDataList.balances.data[0]))+" 最终销毁数："+t._s(t.numberFormatFilter(t.destructionDataList.balances.data[t.destructionDataList.balances.data.length-1]))+" 增加销毁数："+t._s(t.numberFormatFilter(t.destructionDataList.balances.add_destroy))+" 增加百分比："+t._s(t.toFixed(t.destructionDataList.balances.add_percentage,4))+"%")]):t._e(),t._v(" "),2==t.currencyIndex?a("div",{staticStyle:{position:"absolute",right:"190px","font-size":"13px","line-height":"23px"}},[t._v("自动销毁 "+t._s(t.numberFormatFilter(t.autoDestruction))+" BNB, 手续费实时销毁 "+t._s(t.numberFormatFilter(t.bnbNewValues))+" BNB，总计销毁（"+t._s(t.numberFormatFilter(t.autoDestruction+t.bnbNewValues))+"）BNB")]):t._e(),t._v(" "),3==t.activeName&&Object.keys(t.destructionDataList).length?a("div",{staticClass:"threeBarChart",attrs:{id:"countDestruction"}}):a("el-empty",{attrs:{description:"没有数据"}})],1):t._e(),t._v(" "),"BTC(稳定币)"!==t.name?a("el-tab-pane",{attrs:{label:"新增销毁量",name:"4"}},[a("div",{staticStyle:{"text-align":"right"}},[t._v("  \r\n                    Address:  \r\n                    "),"TON(ETH)"===t.name?a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://www.oklink.com/zh-cn/eth/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]):a("el-link",{staticStyle:{"text-decoration":"underline"},attrs:{underline:!1,href:"https://bscscan.com/token/"+t.selectAddress,target:"_blank"}},[t._v(t._s(t.strAddress()))]),t._v(" "),a("i",{directives:[{name:"clipboard",rawName:"v-clipboard:copy",value:t.selectAddress,expression:"selectAddress",arg:"copy"},{name:"clipboard",rawName:"v-clipboard:success",value:t.copySuccess,expression:"copySuccess",arg:"success"}],staticClass:"el-icon-document-copy",staticStyle:{cursor:"pointer"}})],1),t._v(" "),2==t.currencyIndex?a("div",{staticStyle:{position:"absolute",right:"200px","font-size":"13px","line-height":"23px"}},[t._v("自动销毁 "+t._s(t.numberFormatFilter(t.autoDestruction))+" BNB, 手续费实时销毁 "+t._s(t.numberFormatFilter(t.bnbNewValues))+" BNB，总计销毁（"+t._s(t.numberFormatFilter(t.autoDestruction+t.bnbNewValues))+"）BNB")]):t._e(),t._v(" "),4==t.activeName&&Object.keys(t.destructionDataList).length?a("div",{staticClass:"threeBarChart",attrs:{id:"addDestruction"}}):a("el-empty",{attrs:{description:"没有数据"}})],1):t._e()],1)],1)},staticRenderFns:[]};e.a=i},m6RP:function(t,e,a){"use strict";var i=a("NYxO"),s=a("Pg0u"),n=(a.n(s),a("+zHA")),r=a("GKmE"),o=Object.assign||function(t){for(var e=1;e<arguments.length;e++){var a=arguments[e];for(var i in a)Object.prototype.hasOwnProperty.call(a,i)&&(t[i]=a[i])}return t};e.a={name:"",data:function(){return{currencyIndex:32,timesIndex:1,activeName:"1",name:"BTC(稳定币)",selectAddress:"0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82",dataList:[],destructionDataList:[],start_end_time:"",time_range:"1 day",this_year:"",bnbNewValues:0,autoDestruction:0,optionData:[]}},computed:o({},Object(i.c)({address:function(t){return t.base.address},isConnected:function(t){return t.base.isConnected},isMobel:function(t){return t.comps.isMobel},mainTheme:function(t){return t.comps.mainTheme},apiUrl:function(t){return t.base.apiUrl}})),created:function(){this.getTokensList(),this.getHourDataList(),this.getDestructionDataList()},watch:{},mounted:function(){},components:{},methods:{myAddAddressChart:function(){var t,e=s.init(document.getElementById("addAddress")),a=this;(t={title:{},tooltip:{trigger:"axis",extraCssText:"width:300px;height:auto;background-color:#fff;color:#333",axisPointer:{type:"cross"},formatter:function(t){var e=t[0].name+"<br/>",a=!0,i=!1,s=void 0;try{for(var n,o=t[Symbol.iterator]();!(a=(n=o.next()).done);a=!0){var c=n.value;0==c.seriesIndex?e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+c.color+";'></span>&nbsp;"+c.seriesName+": <span style='float:right;'>"+c.value+"</span><br/>":e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+c.color+";'></span>&nbsp;"+c.seriesName+": <span style='float:right;'>$"+Object(r.h)(Number(c.value),18,!0)+"</span><br/>"}}catch(t){i=!0,s=t}finally{try{!a&&o.return&&o.return()}finally{if(i)throw s}}return e}},legend:{data:["BTC(稳定币)"==a.name?"新增量":"新增地址量",a.name+" 价格"],right:"0"},grid:{left:"3%",right:"4%",bottom:"3%",containLabel:!0},dataZoom:{type:"inside"},xAxis:{type:"category",axisTick:{alignWithLabel:!0},data:this.dataList.times,axisLabel:{interale:0,formatter:function(t){var e=new Date(t);return 1==a.timesIndex?[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")+" "+[e.getHours(),e.getMinutes()].join(":"):[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")}}},yAxis:[{type:"value",min:parseInt(this.dataList.addAddress.min-100),max:parseInt(this.dataList.addAddress.max+100),axisLabel:{},axisLine:{show:!0,lineStyle:{color:"#F79729"}},splitLine:{show:!1}},{type:"value",min:Number(this.dataList.prices.min)-.01*Number(this.dataList.prices.min),max:Number(this.dataList.prices.max)+.01*Number(this.dataList.prices.max),axisLabel:{formatter:function(t){return"$"+Object(r.h)(t,18,!0)}},axisLine:{show:!0,lineStyle:{color:"#7C7C7C"}},splitLine:{show:!1}}],series:[{name:"BTC(稳定币)"==this.name?"新增量":"新增地址量",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:this.dataList.addAddress.data,itemStyle:{color:"#F79729"}},{name:a.name+" 价格",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:1,data:this.dataList.prices.data,itemStyle:{color:"#7C7C7C"}}]})&&e.setOption(t)},myCountAddressChart:function(){var t,e=this,a=s.init(document.getElementById("countAddress")),i=this,n=[],o=[],c=[],l=parseInt(this.dataList.holders.min-100),d=parseInt(this.dataList.holders.max+100);if("BTC(稳定币)"==i.name){n=["总量","总量02",i.name+" 价格"];var u=Object.keys(this.dataList.other_data);o=n.concat(u),Object.keys(this.dataList.other_data).forEach(function(t,a){c.push({name:t,type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:e.dataList.other_data[t]})}),this.dataList.holders_two.min<l&&(l=parseInt(this.dataList.holders_two.min-100)),this.dataList.holders_two.max>d&&(d=parseInt(this.dataList.holders_two.max+100))}else n=["总地址量",i.name+" 价格"];(t={title:{},tooltip:{trigger:"axis",extraCssText:"width:300px;height:auto;background-color:#fff;color:#333",axisPointer:{type:"cross"},formatter:function(t){var e=t[0].name+"<br/>",a=!0,s=!1,n=void 0;try{for(var o,c=t[Symbol.iterator]();!(a=(o=c.next()).done);a=!0){var l=o.value;l.seriesName!==i.name+" 价格"?e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+l.color+";'></span>&nbsp;"+l.seriesName+": <span style='float:right;'>"+Object(r.i)(l.value)+"</span><br/>":e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+l.color+";'></span>&nbsp;"+l.seriesName+": <span style='float:right;'>$"+Object(r.i)(Object(r.h)(Number(l.value),18,!0))+"</span><br/>"}}catch(t){s=!0,n=t}finally{try{!a&&c.return&&c.return()}finally{if(s)throw n}}return e}},legend:{data:o,right:"0"},grid:{left:"3%",right:"4%",bottom:"3%",containLabel:!0},dataZoom:{type:"inside"},xAxis:{type:"category",axisTick:{alignWithLabel:!0},data:this.dataList.times,axisLabel:{interale:0,formatter:function(t){var e=new Date(t);return 1==i.timesIndex?[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")+" "+[e.getHours(),e.getMinutes()].join(":"):[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")}}},yAxis:[{type:"value",min:l,max:d,axisLabel:{formatter:function(t){return i.UnitConversion(t)}},axisLine:{show:!0,lineStyle:{color:"#F79729"}},splitLine:{show:!1}},{type:"value",min:Number(this.dataList.prices.min)-.01*Number(this.dataList.prices.min),max:Number(this.dataList.prices.max)+.01*Number(this.dataList.prices.max),axisLabel:{formatter:function(t){return"$"+Object(r.h)(t,18,!0)}},axisLine:{show:!0,lineStyle:{color:"#7C7C7C"}},splitLine:{show:!1}}],series:[{name:"BTC(稳定币)"==i.name?"总量":"总地址量",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:this.dataList.holders.data},{name:"BTC(稳定币)"==i.name?"总量02":"总地址量",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:this.dataList.holders_two.data},{name:i.name+" 价格",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:1,data:this.dataList.prices.data}].concat(c)})&&a.setOption(t)},myCountDestructionChart:function(){var t,e=s.init(document.getElementById("countDestruction")),a=this;(t={title:{},tooltip:{trigger:"axis",extraCssText:"width:300px;height:auto;background-color:#fff;color:#333",axisPointer:{type:"cross"},formatter:function(t){var e=t[0].name+"<br/>",a=!0,i=!1,s=void 0;try{for(var n,o=t[Symbol.iterator]();!(a=(n=o.next()).done);a=!0){var c=n.value;0==c.seriesIndex?e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+c.color+";'></span>&nbsp;"+c.seriesName+": <span style='float:right;'>"+Object(r.i)(c.value)+"</span><br/>":e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+c.color+";'></span>&nbsp;"+c.seriesName+": <span style='float:right;'>$"+Object(r.h)(Number(c.value),18,!0)+"</span><br/>"}}catch(t){i=!0,s=t}finally{try{!a&&o.return&&o.return()}finally{if(i)throw s}}return e}},legend:{data:["总销毁量",a.name+" 价格"],right:"0"},grid:{left:"3%",right:"4%",bottom:"3%",containLabel:!0},dataZoom:{type:"inside"},xAxis:{type:"category",axisTick:{alignWithLabel:!0},data:this.destructionDataList.times,axisLabel:{interale:0,formatter:function(t){var e=new Date(t);return 1==a.timesIndex?[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")+" "+[e.getHours(),e.getMinutes()].join(":"):[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")}}},yAxis:[{type:"value",min:parseInt(Number(this.destructionDataList.balances.min)-.01*Number(this.destructionDataList.balances.min)),max:parseInt(Number(this.destructionDataList.balances.max)+.01*Number(this.destructionDataList.balances.max)),axisLabel:{formatter:function(t){return a.UnitConversion(t)}},axisLine:{show:!0,lineStyle:{color:"#F79729"}},splitLine:{show:!1}},{type:"value",min:Number(this.destructionDataList.prices.min)-.01*Number(this.destructionDataList.prices.min),max:Number(this.destructionDataList.prices.max)+.01*Number(this.destructionDataList.prices.max),axisLabel:{formatter:function(t){return"$"+Object(r.h)(t,18,!0)}},axisLine:{show:!0,lineStyle:{color:"#7C7C7C"}},splitLine:{show:!1}}],series:[{name:"总销毁量",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:this.destructionDataList.balances.data,itemStyle:{color:"#F79729"}},{name:a.name+" 价格",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:1,data:this.destructionDataList.prices.data,itemStyle:{color:"#7C7C7C"}}]})&&e.setOption(t)},myAddDestructionChart:function(){var t,e=s.init(document.getElementById("addDestruction")),a=this;(t={title:{},tooltip:{trigger:"axis",extraCssText:"width:300px;height:auto;background-color:#fff;color:#333",axisPointer:{type:"cross"},formatter:function(t){var e=t[0].name+"<br/>",i=!0,s=!1,n=void 0;try{for(var o,c=t[Symbol.iterator]();!(i=(o=c.next()).done);i=!0){var l=o.value;0==l.seriesIndex?e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+l.color+";'></span>&nbsp;"+l.seriesName+": <span style='float:right;'>"+a.toFixed(l.value,4)+"</span><br/>":e+="<span style='display:inline-block;width:10px;height:10px;border-radius:10px;background-color:"+l.color+";'></span>&nbsp;"+l.seriesName+": <span style='float:right;'>$"+Object(r.h)(Number(l.value),18,!0)+"</span><br/>"}}catch(t){s=!0,n=t}finally{try{!i&&c.return&&c.return()}finally{if(s)throw n}}return e}},legend:{data:["新增销毁量",a.name+" 价格"],right:"0"},grid:{left:"3%",right:"4%",bottom:"3%",containLabel:!0},dataZoom:{type:"inside"},xAxis:{type:"category",axisTick:{alignWithLabel:!0},data:this.destructionDataList.times,axisLabel:{interale:0,formatter:function(t){var e=new Date(t);return 1==a.timesIndex?[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")+" "+[e.getHours(),e.getMinutes()].join(":"):[e.getFullYear(),e.getMonth()+1,e.getDate()].join("-")}}},yAxis:[{type:"value",min:Number(this.destructionDataList.addBalances.min)-.1*Number(this.destructionDataList.addBalances.min),max:Number(this.destructionDataList.addBalances.max)+.1*Number(this.destructionDataList.addBalances.max),axisLabel:{formatter:function(t){return a.UnitConversion(t)}},axisLine:{show:!0,lineStyle:{color:"#F79729"}},splitLine:{show:!1}},{type:"value",min:Number(this.destructionDataList.prices.min)-.01*Number(this.destructionDataList.prices.min),max:Number(this.destructionDataList.prices.max)+.01*Number(this.destructionDataList.prices.max),axisLabel:{formatter:function(t){return"$"+Object(r.h)(t,18,!0)}},axisLine:{show:!0,lineStyle:{color:"#7C7C7C"}},splitLine:{show:!1}}],series:[{name:"新增销毁量",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:0,data:this.destructionDataList.addBalances.data,itemStyle:{color:"#F79729"}},{name:a.name+" 价格",type:"line",symbolSize:5,symbol:"circle",yAxisIndex:1,data:this.destructionDataList.prices.data,itemStyle:{color:"#7C7C7C"}}]})&&e.setOption(t)},getTokensList:function(){var t=this;Object(n.b)(this.apiUrl+"/Api/Bscaddressstatistics/getTokensList",{},function(e){console.log(e),1e4==e.code?t.optionData=e.data:t.$message.error("加载数据失败")})},getHourDataList:function(){var t=this,e="",a="";this.start_end_time&&this.start_end_time.length>0&&(e=this.start_end_time[0],a=this.start_end_time[1]),Object(n.b)(this.apiUrl+"/Api/Bscaddressstatistics/getHourDataList",{name:this.name,this_year:this.this_year,time_range:this.time_range,start_time:e,end_time:a},function(e){console.log(e),1e4==e.code?Object.keys(e.data).length?(t.dataList=e.data,t.$nextTick(function(){1==t.activeName&&(console.log(111),t.myCountAddressChart()),2==t.activeName&&t.myAddAddressChart()})):t.dataList=[]:t.$message.error("加载数据失败")})},getDestructionDataList:function(){var t=this,e="",a="";this.start_end_time&&this.start_end_time.length>0&&(e=this.start_end_time[0],a=this.start_end_time[1]),Object(n.b)(this.apiUrl+"/Api/Bscaddressstatistics/getDestructionDataList",{name:this.name,this_year:this.this_year,time_range:this.time_range,start_time:e,end_time:a},function(e){console.log(e),1e4==e.code?Object.keys(e.data).length?(t.destructionDataList=e.data,t.autoDestruction=Number(e.data.autoDestruction),t.bnbNewValues=Number(e.data.bnbNewValues),t.$nextTick(function(){3==t.activeName&&t.myCountDestructionChart(),4==t.activeName&&t.myAddDestructionChart()})):t.destructionDataList=[]:t.$message.error("加载数据失败")})},searchClick:function(t,e){this.timesIndex=e,t&&""!==t&&(this.start_end_time="","all"===t?(this.this_year="",this.time_range="",this.getHourDataList(),this.getDestructionDataList()):"year"===t?(this.this_year=t,this.time_range="",this.getHourDataList(),this.getDestructionDataList()):(this.this_year="",this.time_range=t,this.getHourDataList(),this.getDestructionDataList()))},timesChange:function(t){console.log(t),this.this_year="",this.time_range="",this.timesIndex=null==t?8:0,1==this.activeName||2==this.activeName?this.getHourDataList():this.getDestructionDataList()},selectButton:function(t,e,a){this.currencyIndex=e,this.name=t,this.selectAddress=a,1==this.activeName||2==this.activeName?this.getHourDataList():this.getDestructionDataList()},selectChange:function(t){if(t&&""!==t){var e=this.optionData.find(function(e){return e.name===t});this.currencyIndex=e.id,this.selectAddress=e.token,1==this.activeName||2==this.activeName?this.getHourDataList():this.getDestructionDataList()}},tabHandleClick:function(t,e){console.log(t,e),1==t.name||2==t.name?this.getHourDataList():this.getDestructionDataList()},UnitConversion:function(t){var e={},a=void 0;return t<1e4?(e.value=t,e.unit=""):(a=Math.floor(Math.log(t)/Math.log(1e4)),e.value=(t/Math.pow(1e4,a)).toFixed(2),e.unit=["","万","亿","万亿","百万亿","千万亿","兆","十兆","百兆","千兆"][a]),e.value+e.unit},numberFormatFilter:function(t){return Object(r.i)(t)},strAddress:function(){if(this.selectAddress&&null!==this.selectAddress)return this.selectAddress.substring(0,4)+"***"+this.selectAddress.substring(this.selectAddress.length-3)},copySuccess:function(){this.$message({message:"Copy successfully",type:"success"})}}}},qQOf:function(t,e,a){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=a("m6RP"),s=a("RmIh");var n=function(t){a("+1KM")},r=a("VU/8")(i.a,s.a,!1,n,"data-v-22bd2a27",null);e.default=r.exports}});