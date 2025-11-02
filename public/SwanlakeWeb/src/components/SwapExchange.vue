<template>
  <div class="container">
    <!-- 删除流动性的时候才会有 WBNB -->
    <div class="input" v-show="pageState == 3">
      <el-row>
        <el-col :span="5">
          <el-button class="input-b">
            <div class="input-b-box" v-if="exchangeArray.INPUT !== '' && exchangeArray.INPUT >= 0">
                <img size="small" :src="getFilersSwapPoolsArr(exchangeArray.INPUT).logo" width="20" />
                <img size="small" :src="getFilersSwapPoolsArr(exchangeArray.OUTPUT).logo" width="20" style="margin-left:5px;" />
                <span>{{getFilersSwapPoolsArr(exchangeArray.INPUT).name}}:{{getFilersSwapPoolsArr(exchangeArray.OUTPUT).name}}</span>
              <!-- <i class="el-icon-arrow-down el-icon--down"></i> -->
            </div>
            <div class="input-b-box" v-else>
              <span>{{ $t('swap:SelectCurrency') }}：</span> 
              <i class="el-icon-arrow-down el-icon--down"></i>
            </div>
          </el-button>
        </el-col>
        <el-col :span="19" class="textRight">{{ $t('swap:Balance') }}: {{currentPools && currentPools.tokenBalance && computeTokenBalanceChange(currentPools.tokenBalance, currentPools.tokenDecimals, 10)}}</el-col>
      </el-row>
      <el-row class="input-box">
        <el-col :span="24">
          <el-input
            class="input-input"
            v-model="formToTokenBlance"
            placeholder="0.0"
            @input="inputFormChangeValue"
          ></el-input>
          <el-button 
            v-show="currentPools && formToTokenBlance && (formToTokenBlance < computeTokenBalanceChange(currentPools.tokenBalance, currentPools.tokenDecimals, 10) || formToTokenBlance > computeTokenBalanceChange(currentPools.tokenBalance, currentPools.tokenDecimals, 10))" 
            class="input-max" 
            size="small" 
            round 
            @click="inputFormChangeValue(computeTokenBalanceChange(currentPools.tokenBalance, currentPools.tokenDecimals, 10)
            ) ">{{ $t('swap:Max') }}</el-button>
        </el-col>
      </el-row>
      <div class="arrow">
          <el-button size="small" circle>
            <i class="el-icon-bottom"></i>
          </el-button>
      </div>
    </div>

    <!-- INPUT  -->
    <div class="input">
      <el-row>
        <el-col :span="12">
          <el-button class="input-b" @click="handleClickOpen('INPUT')" :disabled="pageState == 3 ? true : false">
            <div class="input-b-box" v-if="exchangeArray.INPUT !== '' && exchangeArray.INPUT >= 0">
              <img size="small" :src="getFilersSwapPoolsArr(exchangeArray.INPUT).logo" width="20" />
              <span>{{getFilersSwapPoolsArr(exchangeArray.INPUT).name}}</span>
              <i v-show="pageState !== 3" class="el-icon-arrow-down el-icon--down"></i>
            </div>
            <div class="input-b-box" v-else>
              <span>{{ $t('swap:SelectCurrency') }}：</span> 
              <i class="el-icon-arrow-down el-icon--down"></i>
            </div>
          </el-button>
        </el-col>
        <el-col :span="12" class="textRight">{{ $t('swap:Balance') }}: {{balanceTransformation(getFilersSwapPoolsArr(exchangeArray.INPUT).tokenBalance)}}</el-col>
      </el-row>
      <el-row class="input-box">
        <el-col :span="24">
          <el-input
            class="input-input"
            v-model="exchangeMoney.INPUT"
            placeholder="0.0"
            @input="inputChangeValue"
          ></el-input>
          <el-button 
            v-show="pageState < 3 && getFilersSwapPoolsArr(exchangeArray.INPUT).tokenBalance > 0 && keepDecimalNotRounding(exchangeMoney.INPUT) != keepDecimalNotRounding(getFilersSwapPoolsArr(exchangeArray.INPUT).tokenBalance)" 
            class="input-max" 
            size="small" 
            round 
            @click="inputChangeValue(keepDecimalNotRounding(getFilersSwapPoolsArr(exchangeArray.INPUT).tokenBalance))">{{ $t('swap:Max') }}</el-button>
        </el-col>
      </el-row>
    </div>

    <div class="arrow" v-if="pageState == 1">
      <el-button size="small" circle @click="switchCurrencyClick">
        <i class="el-icon-sort"></i>
      </el-button>
    </div>
    <div class="arrow" v-else>
      <el-button size="small" circle>
        <i class="el-icon-plus"></i>
      </el-button>
    </div>

    <!-- OUTPUT  -->
    <div class="output">
      <el-row>
        <el-col :span="12">
          <el-button class="input-b" @click="handleClickOpen('OUTPUT')" :disabled="pageState == 3 ? true : false">
            <div class="input-b-box" v-if="exchangeArray.OUTPUT !== '' && exchangeArray.OUTPUT >= 0">
                <img size="small" :src="getFilersSwapPoolsArr(exchangeArray.OUTPUT).logo" width="20" />
                <span>{{getFilersSwapPoolsArr(exchangeArray.OUTPUT).name}}</span>
                <i v-show="pageState !== 3" class="el-icon-arrow-down el-icon--down"></i>
            </div>
            <div class="input-b-box" v-else>
                <span>{{ $t('swap:SelectCurrency') }}：</span> 
                <i class="el-icon-arrow-down el-icon--down"></i>
            </div>
          </el-button>
        </el-col>
        <el-col :span="12" class="textRight">Balance: {{balanceTransformation(getFilersSwapPoolsArr(exchangeArray.OUTPUT).tokenBalance)}}</el-col>
      </el-row>
      <el-row class="input-box">
        <el-col :span="24">
          <el-input
            class="input-input"
            v-model="exchangeMoney.OUTPUT"
            placeholder="0.0"
            @input="outputChangeValue"
          ></el-input>
          <el-button 
            v-show="pageState < 3 && getFilersSwapPoolsArr(exchangeArray.OUTPUT).tokenBalance > 0 && keepDecimalNotRounding(exchangeMoney.OUTPUT) != keepDecimalNotRounding(getFilersSwapPoolsArr(exchangeArray.OUTPUT).tokenBalance)"
            class="input-max" 
            size="small" 
            round 
            @click="outputChangeValue(keepDecimalNotRounding(getFilersSwapPoolsArr(exchangeArray.OUTPUT).tokenBalance))"
            >{{ $t('swap:Max') }}</el-button>
        </el-col>
      </el-row>
    </div>
    
    <!-- Select a Token -->
    <el-dialog
      title="Select a Token"
      :visible.sync="selectTokenopen"
      :before-close="handleSelectTokenClose"
      class="dialogClass"
      center>
      <div>
        <el-table
          :row-class-name="tableRowClassName"
          :data="swapPools" 
          @row-click="tableRowClick"
          height="500" 
          :show-header="false"
        >
          <el-table-column width="50">
            <template slot-scope="scope">
              <!-- <el-button class="input-b"> -->
                <img :src="scope.row.logo" alt="" width="20">
              <!-- </el-button> -->
            </template>
          </el-table-column>
          <el-table-column property="name" width="100"></el-table-column>
          <el-table-column>
            <template slot-scope="scope">
                <span  style="float: right;">{{scope.row.tokenBalance}}</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
      <!-- <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="dialogVisible = false">确 定</el-button>
      </span> -->
    </el-dialog>
  </div>
</template>
<script>
import { keepDecimalNotRounding, replaceParamVal, getUrlParams, changeURLPar, scientificNotationToString} from '@/utils/tools'
import configAddress from '@/wallet/swap_pools';
const publicAddress = configAddress.publicAddress;
import { getTokenAmountsoutPrice,getTokenAmountsIntPrice } from "@/wallet/Inquire";
import merge from 'webpack-merge';
export default {
  props: [
    'childSwapPoolsClick',
    'childExchangeArray',
    'swapPools',
    'fetchVaultsSwapDataDone',
    'childApprovedArrStatus',
    'childExchangeMoney',
    'fetchLiquidityDataDone',
    'currentPools',
    'formTokenBlance',
    'childFormTokenBlance',
    'updateChildExchangeMoney',
    'updateChildValuationState',
    'updateChildApprovedArrStatus'
  ],
  data() {
    return {
      pageState: 0,
      defaultCurrency: "H2O",
      exchangeArray: {
        INPUT: '',
        OUTPUT: '',
      },
      exchangeMoney: { //输入框值
        INPUT: '',
        OUTPUT: '',
      },
      approvedArrStatus: {
        INPUT: false,
        OUTPUT: false,
      },
      selectTokenopen: false, //选择代币弹框
      selectedIndex: 0,
      exchangeArrayKey: '', //区分是INPUT 还是 OUTPUT
      valuationState: '',
      currentRow: null,
      formToTokenBlance: '',
    };
  },
  computed: {},
  watch: {
    exchangeArray: {
      handler(newVal, oldVal) {
        this.$emit('childSwapPoolsClick', newVal);
      },
      deep: true
    },
    exchangeMoney: {
      handler(newVal, oldVal) {
        this.$emit('updateChildExchangeMoney', newVal);
      },
      deep: true
    },
    approvedArrStatus: {
      handler(newVal, oldVal) {
        console.log(newVal);
        this.$emit('updateChildApprovedArrStatus', newVal);
      },
      deep: true
    },
    valuationState: {
      handler(newVal, oldVal) {
        this.$emit('updateChildValuationState', newVal);
      },
      deep: true
    },
    // childExchangeMoney: {
    //   mmediate: true,
    //     handler(val) {
    //       console.log(val);
    //     },
    //     deep: true
    // }
    swapPools: {
        immediate: true,
        handler(val) {
            console.log(val);
            this.paramsUrlAddress();
    //         let inputAllowance;
    //         let outputAllowance;
    //         if(this.exchangeArray.INPUT >= 0) {
    //             const inputAllowanceNum = this.getFilersSwapPoolsArr(this.exchangeArray.INPUT).allowance;
    //             // console.log(inputAllowanceNum);
    //             if(inputAllowanceNum && inputAllowanceNum > 0) {
    //                 inputAllowance = true;
    //             }
    //             this.$emit('childApprovedArrStatus', inputAllowance, 'INPUT');
    //         }
    //         if(this.exchangeArray.OUTPUT >= 0) {
    //             const inputAllowanceNum = this.getFilersSwapPoolsArr(this.exchangeArray.OUTPUT).allowance;
    //             if(inputAllowanceNum && inputAllowanceNum > 0) {
    //                 outputAllowance = true;
    //             }
    //             this.$emit('childApprovedArrStatus', outputAllowance, 'OUTPUT');
    //         }

    //         // this.approvedArrStatus = {
    //         //     INPUT: inputAllowance,
    //         //     OUTPUT: outputAllowance,
    //         // };
    //         // console.log(this.approvedArrStatus);
    //         // this.timeRefusr = new Date().getTime();
        },
        deep: true
    }
  },
  mounted() {
    if(this.$route.name === 'Swap') {
      this.pageState = 1;
    } else if(this.$route.name === 'LiquidityAdd') {
      this.pageState = 2;
    } else if(this.$route.name === 'LiquidityRemove') {
      this.pageState = 3;
    } else {
      this.pageState = 0;
    }
  },
  created(){
    if(this.childExchangeArray) {
      // console.log(this.childExchangeArray);
      this.exchangeArray = this.childExchangeArray;
      // this.exchangeMoney = this.childExchangeMoney;
    }
    if(this.childExchangeMoney && this.childExchangeMoney.INPUT && this.childExchangeMoney.OUTPUT) {
      this.exchangeMoney = this.childExchangeMoney;
    }
    // console.log(this.childApprovedArrStatus);
    // if(this.childApprovedArrStatus && this.childApprovedArrStatus.INPUT && this.childApprovedArrStatus.OUTPUT) {
    //   this.approvedArrStatus = this.childApprovedArrStatus;
    // }
    if(this.formTokenBlance && this.formTokenBlance > 0) {
      this.formToTokenBlance = this.formTokenBlance;
    }
    // console.log(this.exchangeMoney);
  },
  methods: {
    handleClickOpen(key) { //打开选择币种弹框
      this.selectTokenopen = true;
      this.exchangeArrayKey = key;
    },
    handleSelectTokenClose() { //关闭选择币种弹框
       this.selectTokenopen = false;
    },
    handleCurrentChange(val) {
      this.currentRow = val;
      this.$refs.singleTable.setCurrentRow(val);
      this.selectTokenopen = false;
    },
    //获取地址栏对应值
    paramsUrlAddress() {
        const inputCurrency = this.$route.query.inputCurrency;
        console.log(inputCurrency, this.$route.query);
        const outputCurrency = this.$route.query.outputCurrency;
        let inputSerchData = [];
        let outputSerchData = [];
        let input = undefined;
        let ouput = undefined;
        let inputAllowance = false;
        let ouputAllowance = false;
        if (inputCurrency && inputCurrency !== undefined) {
            if (inputCurrency === publicAddress.DEFANT_CURRENCY) {
                console.log(this.swapPools);
                inputSerchData = this.swapSearchProps(this.swapPools, inputCurrency, 'name');
            } else {
                inputSerchData = this.swapSearchProps(this.swapPools, inputCurrency, 'tokenAddress');
            }
            // console.log(inputSerchData);
            if (inputSerchData && inputSerchData[0]) {
                input = inputSerchData[0].poolId;
                inputAllowance = Number(inputSerchData[0].allowance) > 0 ? true : false;
                // console.log(inputAllowance);
            }
        }
        // else {
        //     changeURLPar(window.location.href, "inputCurrency", publicAddress.DEFANT_CURRENCY);
        // }
        if (outputCurrency && outputCurrency !== undefined) {
            // console.log("111");
            if (outputCurrency === publicAddress.DEFANT_CURRENCY) {
                outputSerchData = this.swapSearchProps(this.swapPools, outputCurrency, 'name');
            } else {
                outputSerchData = this.swapSearchProps(this.swapPools, outputCurrency, 'tokenAddress');
            }
            if (outputSerchData && outputSerchData[0]) {
                ouput = outputSerchData[0].poolId;
                ouputAllowance = Number(outputSerchData[0].allowance > 0) ? true : false;
            }
        }
        console.log(input);
        this.exchangeArray = {
            INPUT: input, 
            OUTPUT: ouput
        };
        this.approvedArrStatus = {
            INPUT: inputAllowance, 
            OUTPUT: ouputAllowance
        };
        // this.$emit('childApprovedArrStatus', inputAllowance, 'INPUT');
        // this.$emit('childApprovedArrStatus', ouputAllowance, 'OUTPUT');
        // console.log(this.exchangeMoney);
        // this.timeRefusr = new Date().getTime();
        console.log(inputAllowance);
    },
    //模糊搜索
    swapSearchProps(list, keyWord, name) {
        let arr = [];
        for (let i = 0; i < list.length; i++) {
            let flag = false
            if (list[i][name] && list[i][name].indexOf(keyWord) >= 0) {
                flag = true
            }
            if (flag) {
                arr.push(list[i])
            }
        }
        return arr;
    },
    tableRowClassName({row, rowIndex}) {
      // console.log(this.exchangeArray);
      if((this.exchangeArray.INPUT !== '' && this.exchangeArray.INPUT >= 0) || (this.exchangeArray.OUTPUT !== '' && this.exchangeArray.OUTPUT >= 0)) {
        if(row.poolId == this.exchangeArray.INPUT || row.poolId == this.exchangeArray.OUTPUT) {
          return 'select-row';
        } else {
          return '';
        }
      } else {
        return '';
      }
    },
    tableRowClick(row, column, event) { //选择token事件 
      if(row) {
        let addressStr = row.tokenAddress;
        if(row.poolId == 0) {
          addressStr = this.defaultCurrency;
        }
        this.selectedIndex = row.poolId;
        if(this.exchangeArrayKey === 'INPUT') {
          if(this.exchangeArray.OUTPUT !== row.poolId) {
            this.exchangeArray.INPUT = row.poolId;
            if (row.allowance > 0) {
              this.$emit('childApprovedArrStatus', true, 'INPUT');
            } else {
              this.$emit('childApprovedArrStatus', false, 'INPUT');
            }
            // const inputCurrency = getUrlParams('inputCurrency');
            const inputCurrency = this.$route.query.inputCurrency;
            if (inputCurrency && inputCurrency !== undefined) {
                replaceParamVal("inputCurrency", addressStr);
            } else {
                changeURLPar(window.location.href, "inputCurrency", addressStr);
            }
            if (this.valuationState === 'INPUT' && this.exchangeMoney.OUTPUT > 0) {
                const toValue = this.exchangeMoney.OUTPUT * row.price;
                this.exchangeMoney.INPUT = toValue;
            }
            this.selectTokenopen = false;
          }
        } else {
          if(this.exchangeArray.INPUT !== row.poolId) {
            this.exchangeArray.OUTPUT = row.poolId;
            if (row.allowance > 0) {
              this.$emit('childApprovedArrStatus', true, 'OUTPUT');
            } else {
              this.$emit('childApprovedArrStatus', false, 'OUTPUT');
            }
            // const inputCurrency = getUrlParams('outputCurrency');
            const inputCurrency = this.$route.query.outputCurrency;
            if (inputCurrency && inputCurrency !== undefined) {
                replaceParamVal("outputCurrency", addressStr);
            } else {
                changeURLPar(window.location.href, "outputCurrency", addressStr);
            }
            if (this.valuationState === 'OUTPUT' && this.exchangeMoney.INPUT > 0) {
                const toValue = this.exchangeMoney.INPUT * row.price;
                this.exchangeMoney.OUTPUT = toValue;
            }
            this.selectTokenopen = false;
          }
        }
        
      }
    },
    //获取指定币种对应数据
    getFilersSwapPoolsArr(putKey) {
        // console.log(putKey);
        let arr = {
            poolId: '',
            tokenAddress: '',
            name: '',
            logo: '',
            logo2: '',
            tokenBalance: 0,
            tokenBalanceUsd: 0,
            tk0Address: '',
            routerContractAddress: '',
            usdtContractAddress: '',
            allowance: 0,
        };
        if (putKey !== "" && putKey >= 0) {
            const filesArray = this.filersSwapPoolsArr(putKey);
            arr = this.swapPools[filesArray]
        }
        // console.log(arr);
        return arr;
    },
    //筛选指定选择币种事件
    filersSwapPoolsArr(poolId) {
        // return 0.0001;
        let swapPoolsArrIndex = {};
        if(poolId !== '' && poolId >= 0) {
          if (this.swapPools.length > 0) {
              this.swapPools.map((pool, index) => {
                  if (poolId == pool.poolId) {
                      swapPoolsArrIndex = index;
                  }
              });
          }
        }
        // console.log(swapPoolsArrIndex);
        return swapPoolsArrIndex;
    },
    balanceTransformation(blance) { //处理选择币种余额
      if(blance > 0) {
        return keepDecimalNotRounding(blance, 8, true);
      } else {
        return '--';
      }
    },
    // 上下切换事件
    switchCurrencyClick() {
        console.log(this.exchangeArray.INPUT, this.exchangeArray.OUTPUT);
        this.exchangeArray = {
          INPUT: this.exchangeArray.OUTPUT,
          OUTPUT: this.exchangeArray.INPUT,
        };
        // console.log(this.exchangeArray.INPUT, this.exchangeArray.OUTPUT);
        this.exchangeMoney = {
          INPUT: this.exchangeMoney.OUTPUT,
          OUTPUT: this.exchangeMoney.INPUT,
        };
        this.approvedArrStatus = {
          INPUT: this.approvedArrStatus.OUTPUT,
          OUTPUT: this.approvedArrStatus.INPUT,
        };

        // // console.log(exchangeMoney.OUTPUT);
        if(this.valuationState === 'INPUT') {
          this.valuationState = 'OUTPUT';
        } else {
          this.valuationState = 'INPUT';
        }        
        this.saveUrlParamsVal();
        // console.log(inputArray);
    },
    // 更新地址参数值
    saveUrlParamsVal() {
        let inputAddress = "";
        let outputAddress = "";
        // console.log(this.exchangeArray.INPUT);
        if (this.exchangeArray.INPUT !== '' && this.exchangeArray.INPUT >= 0) {
            let inputIndex = this.filersSwapPoolsArr(this.exchangeArray.INPUT);
            inputAddress = this.swapPools[inputIndex].tokenAddress;
            if (inputIndex == 0) {
                inputAddress = this.defaultCurrency;
            }
            // console.log(inputAddress);
            // replaceParamVal("inputCurrency", inputAddress);
            this.$router.push({
                query:merge(this.$route.query,{'inputCurrency':inputAddress})
            })
        }
        if (this.exchangeArray.OUTPUT !== '' && this.exchangeArray.OUTPUT >= 0) {
            let outputIndex = this.filersSwapPoolsArr(this.exchangeArray.OUTPUT);
            outputAddress = this.swapPools[outputIndex].tokenAddress;
            if (outputIndex == 0) {
                outputAddress = this.defaultCurrency;
            }
            // replaceParamVal("outputCurrency", outputAddress);
            this.$router.push({
                query:merge(this.$route.query,{'outputCurrency':outputAddress})
            })
        }
    },
    //To Value 触发事件
    async inputChangeValue(toValue) {
        // const toValue = event.target.value;
        // console.log(toValue);
        // const toValue = "0.00001";
        const inputArray = this.getFilersSwapPoolsArr(this.exchangeArray.INPUT);
        // const outPrice = inputArray.tokenBalanceUsd;
        let outPrice = await getTokenAmountsoutPrice(inputArray.tk0Address, inputArray.tk1Address, toValue);
        // console.log(outPrice);
        let formValue = 0;
        // console.log(inputArray);
        if (toValue >= 0) {
            if (this.exchangeArray.OUTPUT >= 0) {
                // formValue = toValue > 0 ? keepDecimalNotRounding(toValue * outPrice, 16, true) : 0;
                formValue = toValue > 0 ? keepDecimalNotRounding(outPrice, 16, true) : 0;
                // formValue = '0.000000126644';
                // console.log(keepDecimalNotRounding(toValue * outPrice, 16));

                // console.log(toValue.toString());
                // console.log(toValue, outPrice, toNonExponential(toValue * outPrice));
                if (this.pageState < 3) {
                  this.exchangeMoney = {
                    INPUT: toValue, 
                    OUTPUT: formValue > 0 ? formValue : '' 
                  };
                } else { //如果是删除流动性的话
                    const blanceValue = keepDecimalNotRounding(inputArray.tokenBalance);
                    if (toValue <= blanceValue) {
                        this.exchangeMoney = {
                          INPUT: toValue, 
                          OUTPUT: formValue > 0 ? formValue : ''
                        };
                        if (this.currentPools) {
                            const totalSupply = this.computeTokenBalanceChange(this.currentPools.totalSupply, this.currentPools.tokenDecimals, 18);
                            const componentOneNumber = this.computeTokenBalanceChange(this.currentPools.reserves[0], this.currentPools.tokenDecimals, 18);
                            const formLpValue = (toValue / componentOneNumber * totalSupply).toFixed(16);
                            // console.log(formLpValue);
                            if (formLpValue > 0) {
                                this.formToTokenBlance = formLpValue;
                                this.$emit('childFormTokenBlance', formLpValue);
                            } else {
                              this.formToTokenBlance = 0;
                              this.$emit('childFormTokenBlance', 0);
                            }
                        }
                    } else {
                      this.exchangeMoney = {
                        INPUT: toValue, 
                        OUTPUT: ''
                      };
                      this.formToTokenBlance = 0;
                      this.$emit('childFormTokenBlance', 0);
                    }
                }
            } else {
                this.exchangeMoney = {
                  INPUT: toValue, 
                  OUTPUT: ''
                };
                if (this.pageState == 3) {
                  this.formToTokenBlance = 0;
                  this.$emit('childFormTokenBlance', 0);
                }
            }
            this.valuationState = 'OUTPUT';
            // console.log(exchangeMoney.INPUT);
        } else {
            this.exchangeMoney = {INPUT: '', OUTPUT: ''};
            if (this.pageState == 3) {
              this.formToTokenBlance = 0;
              this.$emit('childFormTokenBlance', 0);
            }
        }
    },
    //Form Value 触发事件
    async outputChangeValue(fromValue) {
      // console.log(fromValue);
        // console.log(outputArray);
        const outputArray = this.getFilersSwapPoolsArr(this.exchangeArray.OUTPUT);
        // const inputPrice = outputArray.tokenBalanceUsd;
        let inputPrice = await getTokenAmountsIntPrice(outputArray.tk1Address, outputArray.tk0Address, fromValue);
        // console.log(inputPrice);
        // let fromValue = event.target.value;
        let toValue = 0;
        if (fromValue >= 0) {
            if (this.exchangeArray.INPUT >= 0) {
                // toValue = fromValue > 0 ? keepDecimalNotRounding(fromValue * inputPrice, 16, true) : 0;
                toValue = fromValue > 0 ? keepDecimalNotRounding(inputPrice, 16, true) : 0;
                if (this.pageState < 3) {
                  this.exchangeMoney = {
                    OUTPUT: fromValue, 
                      INPUT: toValue > 0 ? toValue : ''
                    };
                } else {  //如果是删除流动性的话
                    const blanceValue = keepDecimalNotRounding(outputArray.tokenBalance);
                      // console.log(toValue);
                    if (fromValue <= blanceValue) {
                        this.exchangeMoney = {
                          OUTPUT: fromValue, 
                          INPUT: toValue > 0 ? toValue : ''
                        };
                        if (this.currentPools) {
                            const totalSupply = this.computeTokenBalanceChange(this.currentPools.totalSupply, this.currentPools.tokenDecimals, 18);
                            const componentTwoNumber = this.computeTokenBalanceChange(this.currentPools.reserves[1], this.currentPools.tokenDecimals, 18);
                            const formLpValue = (fromValue / componentTwoNumber * totalSupply).toFixed(16);
                            if (formLpValue > 0) {
                                this.formToTokenBlance = formLpValue;
                                this.$emit('childFormTokenBlance', formLpValue);
                            } else {
                              this.formToTokenBlance = 0;
                              this.$emit('childFormTokenBlance', 0);
                            }
                        }
                    } else {
                        this.exchangeMoney = {
                          OUTPUT: fromValue, 
                          INPUT: ''
                        };
                        this.formToTokenBlance = 0;
                        this.$emit('childFormTokenBlance', 0);
                    }
                }
            } else {
                this.exchangeMoney = {
                  OUTPUT: fromValue, 
                  INPUT: '' 
                };
                if (this.pageState == 3) {
                  this.formToTokenBlance = 0;
                  this.$emit('childFormTokenBlance', 0);
                }
            }
            this.valuationState = 'INPUT';
            // console.log(exchangeMoney.INPUT);
        } else {
            this.exchangeMoney = {
              OUTPUT: '', 
              INPUT: '' 
            }
            if (this.pageState == 3) {
              this.formToTokenBlance = 0;
              this.$emit('childFormTokenBlance', 0);
            }
        }
    },
    /**
     * Form LP 输出值计算
     * @param {*} event 
     */
    inputFormChangeValue(formValue) {

        // console.log(formValue);
        const formArray = this.currentPools;
        let inputValue = 0;
        let outputValue = 0;
        const tokenBalance = this.computeTokenBalanceChange(formArray.tokenBalance, formArray.tokenDecimals, 18);
        if (formValue >= 0 && formValue <= tokenBalance) {
            this.formToTokenBlance = formValue;
            const totalSupply = this.computeTokenBalanceChange(formArray.totalSupply, formArray.tokenDecimals, 18);
            const componentOneNumber = this.computeTokenBalanceChange(formArray.reserves[0], formArray.tokenDecimals, 18);
            const componentTwoNumber = this.computeTokenBalanceChange(formArray.reserves[1], formArray.tokenDecimals, 18);
            const input = formValue / totalSupply * componentOneNumber;
            const output = formValue / totalSupply * componentTwoNumber;
            inputValue = input > 0 ? input.toFixed(16) : '';
            outputValue = output > 0 ? output.toFixed(16) : '';
            this.$emit('childFormTokenBlance', formValue);//更新父组件value值
            this.exchangeMoney = { ////更新当期页面value值
              INPUT: inputValue, 
              OUTPUT: outputValue
            };
            // console.log(this.exchangeMoney);
        } else {
          this.$emit('childFormTokenBlance', formValue);//更新父组件value值
          this.exchangeMoney = {
            INPUT: '', 
            OUTPUT: ''
          };
        }
        // console.log(inputValue, outputValue);
    },
    /**
     * 金额转换
     * @param {*} tokenBalance 
     * @param {*} tokenDecimals 
     * @returns 
     */
    computeTokenBalanceChange(tokenBalance, tokenDecimals, length) {
        let number = 0;
        const num = length && length > 0 ? length : 8;
        if (tokenBalance && tokenDecimals) {
            number = keepDecimalNotRounding(tokenBalance, num);
        }
        // console.log(tokenBalance, tokenDecimals);
        return number;
    },
  },
};
</script>
<style lang="scss" scoped>
.container {
  .input-box {
    @include sideBarSwapInputBgc($claimCardSwapInput-light);
    min-height: 60px;
    line-height: 30px;
    border-radius: 16px;
    margin-top: 6px;
    padding: 0.75rem 0.75rem 0.75rem 1rem;
    .input-input {
      ::v-deep  {
        .el-input__inner {
          // width: 0px;
          position: relative;
          font-weight: 500;
          outline: none;
          border: none;
          flex: 1 1 auto;
          background-color: transparent;
          font-size: 16px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          padding: 0px;
          text-align: right;
          appearance: textfield;
          @include mainFont($color-mainFont-light);
        }
      }
    }
    .input-max {
      @include sideBarSwapInputBgc($claimCardSwapInput-light);
      border: 1px solid #0096ff;
      @include mainFont($color-mainFont-light);
      float: right;
    }
  }
  .input-b {
    -webkit-box-align: center;
    align-items: center;
    border: 0px;
    border-radius: 16px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    -webkit-box-pack: center;
    justify-content: center;
    letter-spacing: 0.03em;
    padding: 0px 16px;
    background-color: transparent;
    @include mainFont($color-mainFont-light);
    box-shadow: none;
    .input-b-box {
      display: flex;
      -webkit-box-align: center;
      align-items: center;
      -webkit-box-pack: justify;
      justify-content: space-between;
    }
    span {
      margin-left: 5px;
    }
  }
  .arrow {
    text-align: center;
    margin: 10px 0 10px 0;
    button {
      @include sideBarSwapInputBgc($claimCardSwapInput-light);
      border: 0;
      i {
        color: #0096ff;
        font-weight: 800;
      }
    }
  }

  .textRight {
    text-align: right;
    padding: 0 16px;
  }

  .dialogClass {
    ::v-deep  {
      .el-dialog--center {
        width: 100%;
        max-width: 420px;
        min-height: 70vh;
        border-radius: 32px;
        @include sideBarSwapInputBgc($claimCardSwapInput-light);
        margin: 0 auto;
        // margin-left: 45%;
      }
      .el-dialog__title{
        @include mainFont($color-mainFont-light);
        // float: left;
      }
      .el-table__row {
        // background-color: red;
        @include sideBarSwapInputBgc($claimCardSwapInput-dark);
        @include mainFont($color-mainFont-light);
      }
      .el-table .select-row {
        // background: rgba(255, 255, 255, 0.16);
        @include selectSwapTokenBgc($selectSwapToken-dark);
        cursor:pointer;
        // background: #909399;
      }
      .el-table__body-wrapper {
        @include sideBarSwapInputBgc($claimCardSwapInput-light);
      }
      .el-table--enable-row-hover .el-table__body tr:hover>td {
        // background-color: rgba(255, 255, 255, 0.16);
        @include selectSwapTokenBgc($selectSwapToken-dark);
        // @include sideBarSwapInputBgc($claimCardSwapInput-light);
        @include mainFont($color-mainFont-light);
      }
      // .el-table__body tr.current-row>td.el-table__cell {
      //   background-color: rgba(255, 255, 255, 0.16);
      // }
      .el-table td.el-table__cell {
        border-bottom: 0;
      }
      .el-table::before {
        z-index: 0;
      }
    }
    .balance {
      td {
        float: right;
      } 
    }
  }
}
</style>