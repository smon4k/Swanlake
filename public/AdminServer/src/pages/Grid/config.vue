<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
            <el-breadcrumb-item to="">机器人配置</el-breadcrumb-item>
        </el-breadcrumb>
      <div class="project-top">
        <el-form :inline="true" class="demo-form-inline">
          <!-- <el-form-item label="接口名称:">
            <el-input clearable placeholder="接口名称" v-model="name"></el-input>
          </el-form-item> -->
          <el-form-item>
            <!-- <el-button type="primary" @click="SearchClick()">搜索</el-button> -->
            <el-button type="primary" @click="AddUserInfoShow()">添加配置</el-button>
            <el-button type="primary" @click="showTradeDialog()">手动操作</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-descriptions v-for="(item, index) in tableData" :key="index" border :column="2">
        <template slot="title">
          <div>{{ item.account_id }} &nbsp;&nbsp; {{ item.account_name }}</div> 
          <div class="balance-container">
            <span v-if="!item.balanceLoading">{{ keepDecimalNotRounding(item.balance, 2) }} USDT</span>
            <span v-else style="display: contents;"><span class="loading"></span>&nbsp;&nbsp;USDT</span>
          </div>
        </template>
        <el-descriptions-item label="API Key">{{ item.api_key }}</el-descriptions-item>
        <template slot="extra">
          <el-button size="mini" type="primary" @click="UpdateAdminUserInfo(item)">编辑</el-button>
          <el-button size="mini" type="danger" @click="DelData(item)">删除</el-button>
        </template>
        <!-- <el-descriptions-item label="API Key">{{ item.api_key }}</el-descriptions-item> -->
        <el-descriptions-item label="API Secret">{{ item.api_secret }}</el-descriptions-item>
        <el-descriptions-item label="倍数">{{ item.multiple }}</el-descriptions-item>
        <el-descriptions-item label="开仓比例">{{ item.position_percent }}</el-descriptions-item>
        <el-descriptions-item label="总仓位">{{ item.total_position }}</el-descriptions-item>
        <el-descriptions-item label="止盈止损">{{ item.stop_profit_loss }}</el-descriptions-item>
        <el-descriptions-item label="网格间距">{{ item.grid_step }}</el-descriptions-item>
        <el-descriptions-item label="网格比例配置">
          <div v-for="(item, index) in item.grid_percent_list" :key="index">
            <span>{{ item.direction }}：</span>
            <span>买入比例{{ item.buy }}</span>&nbsp;&nbsp;
            <span>卖出比例{{ item.sell }}</span>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="币种最大仓位数配置	">
          <div v-for="(item, index) in item.max_position_list" :key="index" style="margin-top: 10px;width: max-content;">
            <span>{{ index + 1 }}.{{ item.symbol }}：</span><br>
            <span>最大仓位：{{ item.value }}</span>&nbsp;&nbsp;
            <span>币种策略：{{ item.tactics }}</span>
            <br>
            <span>最大亏损次数：{{ item.max_loss_number || 0 }}/{{ item.loss_number || 0 }}</span>&nbsp;&nbsp;
            <span>最小亏损比例：{{ item.min_loss_ratio * 100 || 0 }}%</span>
            <br>
            <span>盈利增加比例：{{ item.increase_ratio || 0 }}%</span>&nbsp;&nbsp;
            <span>盈利减少比例：{{ item.decrease_ratio || 0 }}%</span>
            <br>
            <span>清0值：{{ item.clear_value || 0 }}</span>
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="价格浮动比例">{{ item.commission_price_difference }}</el-descriptions-item>
      </el-descriptions>
      <el-row v-if="tableData && tableData.length > 0" style="margin-bottom: 50px;">
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
  
      <el-dialog
      :title="DialogTitle + '配置'"
      :visible.sync="dialogVisibleShow"
      width="80%">
      <el-form :model="FormData" :rules="rules" ref="FormData" label-width="150px">
        <el-form-item label="选择账户" prop="account_id">
          <el-select v-model="FormData.account_id" placeholder="请选择账户" clearable :disabled="is_save_add_start == 2">
            <el-option
              v-for="item in accountList"
              :key="item.id"
              :label="item.name || `账户ID: ${item.id}-${item.api_key}`"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="多空倍数" prop="multiple">
          <el-input v-model="FormData.multiple" placeholder="如 3"></el-input>
        </el-form-item>
        <el-form-item label="开仓比例" prop="position_percent">
          <el-input v-model="FormData.position_percent" placeholder="如 0.8"></el-input>
        </el-form-item>
        <el-form-item label="最大仓位配置">
          <div v-for="(item, index) in FormData.max_position_list" :key="index" style="display: block; align-items: center; margin-bottom: 10px;">
            <div style="display: flex;">
              <el-select v-model="item.symbol" placeholder="选择交易对" style="width: 180px; margin-right: 10px;">
                <el-option
                  v-for="symbol in availableSymbols"
                  :key="symbol"
                  :label="symbol"
                  :value="symbol"
                  :disabled="isSymbolSelected(symbol) && item.symbol !== symbol">
                </el-option>
              </el-select>
              <el-input-number
                v-model="item.value"
                :min="0"
                :step="100"
                style="width: 140px; margin-right: 10px;">
              </el-input-number>
              <el-select v-model="item.tactics" placeholder="选择策略" style="width: 180px; margin-right: 10px;">
                <el-option
                  v-for="item in strategyOptions"
                  :key="item.name"
                  :label="item.name"
                  :value="item.name">
                    <span style="float: left">{{ item.name }}</span>
                    <span style="float: right; color: #8492a6; font-size: 13px">{{ item.label }}</span>
                </el-option>
              </el-select>
            </div>
            <div style="display: flex;margin-top: 5px;margin-bottom: 10px;">
              <div style="display: block;margin-right: 5px;">
                <div style="width: 180px;line-height: 20px;">最大亏损次数</div>
                <el-input type="number" style="width:180px" v-model="item.max_loss_number" placeholder="请输入最大亏损次数"></el-input>
              </div>
              <div style="display: block;margin-right: 5px;">
                <div style="width: 180px;line-height: 20px;">最小亏损比例，单位小数</div>
                <el-input type="number" style="width:180px" v-model="item.min_loss_ratio" placeholder="请输入最小亏损比例 例如：0.001"></el-input>
              </div>
              <div style="display: block;margin-right: 5px;">
                <div style="width: 180px;line-height: 20px;">盈利增加比例，单位百分比</div>
                <el-input type="number" style="width:180px" v-model="item.increase_ratio" placeholder="请输入盈利增加比例 例如：5%"></el-input>
              </div>
              <div style="display: block;margin-right: 5px;">
                <div style="width: 180px;line-height: 20px;">盈利减少比例，单位百分比</div>
                <el-input type="number" style="width:180px" v-model="item.decrease_ratio" placeholder="请输入盈利减少比例 例如：5%"></el-input>
              </div>
              <div style="display: block;margin-right: 5px;">
                <div style="width: 180px;line-height: 20px;">清0值</div>
                <el-input type="number" style="width:180px" v-model="item.clear_value" placeholder="请输入清0值 例如：5000"></el-input>
              </div>
            </div>
            <el-button type="danger" icon="el-icon-delete" @click="removeMaxPosition(index)"></el-button>
          </div>
          <el-button type="primary" icon="el-icon-plus" @click="addMaxPosition"  :disabled="FormData.max_position_list.length >= availableSymbols.length">添加</el-button>
          <p v-if="FormData.max_position_list.length >= availableSymbols.length" style="color: #999;">
            已添加所有可配置交易对
          </p>
        </el-form-item>
        <el-form-item label="网格买卖比例配置">
          <div v-for="(item, index) in FormData.grid_percent_list" :key="index" style="display: flex; align-items: center; margin-bottom: 10px;">
            <el-select v-model="item.direction" style="width: 120px; margin-right: 10px;" disabled>
              <el-option label="做多 (long)" value="long"></el-option>
              <el-option label="做空 (short)" value="short"></el-option>
            </el-select>
            <el-form-item label="买入比例" prop="" class="grid-form-label">
              <el-input
                v-model="item.buy"
                placeholder="买入比例"
                style="">
              </el-input>
            </el-form-item>
            <el-form-item label="卖出比例" prop="" class="grid-form-label">
              <el-input
                v-model="item.sell"
                placeholder="卖出比例"
                style="">
              </el-input>
            </el-form-item>
          </div>
        </el-form-item>
        <el-form-item label="总仓位" prop="total_position">
          <el-input v-model="FormData.total_position" placeholder="如 5000"></el-input>
        </el-form-item>
        <el-form-item label="止盈止损比" prop="stop_profit_loss">
          <el-input v-model="FormData.stop_profit_loss" placeholder="如 0.007"></el-input>
        </el-form-item>
        <el-form-item label="网格间距" prop="grid_step">
          <el-input v-model="FormData.grid_step" placeholder="如 0.002"></el-input>
        </el-form-item>
        <el-form-item label="价格浮动比(百分比)" prop="commission_price_difference">
          <el-input v-model="FormData.commission_price_difference" placeholder="如 0.06%"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button @click="resetForm('FormData')">取消</el-button>
          <el-button type="primary" @click="onUpdateSubmit('FormData')">立即{{ DialogTitle }}</el-button>
        </el-form-item>
      </el-form>
      </el-dialog>
      
      <!-- 新增的交易信号弹框 -->
      <el-dialog
        title="交易信号配置"
        :visible.sync="tradeDialogVisible"
        width="50%">
        <el-form :model="tradeForm" :rules="tradeRules" ref="tradeForm" label-width="100px">
          <!-- <el-form-item label="选择账户" prop="account_id">
            <el-select v-model="tradeForm.account_id" placeholder="请选择账户" clearable>
              <el-option
                v-for="item in accountList"
                :key="item.id"
                :label="item.name || `账户ID: ${item.id}`"
                :value="item.id"
              />
            </el-select>
          </el-form-item> -->
          
          <el-form-item label="策略名称" prop="name">
            <el-select v-model="tradeForm.name" placeholder="请选择策略">
              <el-option
                v-for="item in strategyOptions"
                :key="item.name"
                :label="`${item.name} (${item.label})`"
                :value="item.name">
              </el-option>
            </el-select>
          </el-form-item>
          
          <el-form-item label="交易对" prop="symbol">
            <el-select v-model="tradeForm.symbol" placeholder="请选择交易对">
              <el-option label="BTC-USDT-SWAP" value="BTC-USDT-SWAP"></el-option>
              <el-option label="ETH-USDT-SWAP" value="ETH-USDT-SWAP"></el-option>
            </el-select>
          </el-form-item>
          
          <el-form-item label="价格" prop="price">
            <el-input-number 
              v-model="tradeForm.price" 
              :min="1000" 
              :precision="2" 
              :step="1000"
              placeholder="请输入价格">
            </el-input-number>
          </el-form-item>
          <el-form-item label="操作类型" prop="direction">
            <el-radio-group v-model="tradeForm.direction">
              <el-radio label="buy">买入</el-radio>
              <el-radio label="sell">卖出</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="submitTrade('open')">开仓</el-button>
            <el-button type="danger" @click="submitTrade('close')">平仓</el-button>
            <el-button @click="tradeDialogVisible = false">取消</el-button>
          </el-form-item>
        </el-form>
      </el-dialog>
    </div>
  </template>
  <script>
  import Page from "@/components/Page.vue";
  import { get, post } from "@/common/axios.js";
  export default {
    data() {
      return {
        currPage: 1, //当前页
        pageSize: 50, //每页显示条数
        total: 100, //总条数
        PageSearchWhere: [], //分页搜索数组
        name: "",
        tableData: [],
        TreeProps: {children: 'child', hasChildren: 'hasChildren'},
        dialogVisibleShow: false,
        FormData: {
            id: '',
            account_id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            commission_price_difference: '50',
            max_position: '', // 最终提交还是字符串
            max_position_list: [], // 中间结构用于动态配置
            grid_percent_list: [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ]
        },
        accountList: [], // 账户列表
        availableSymbols: ['BTC-USDT', 'ETH-USDT', 'BNB-USDT'],
        strategyOptions: [],
        DialogTitle: '添加',
        is_save_add_start: 1, //1：添加 2：修改
        AuthMenuRuleData: [], //权限接口角色数据
        rules: {
            account_id: [{ required: true, message: '请选择账户', trigger: 'change' }],
            symbol: [{ required: true, message: '请输入币种', trigger: 'blur' }],
            multiple: [{ required: true, message: '请输入多空倍数', trigger: 'blur' }],
            position_percent: [{ required: true, message: '请输入开仓比例', trigger: 'blur' }],
            max_position: [{ required: true, message: '请输入最大仓位(JSON)', trigger: 'blur' }],
            total_position: [{ required: true, message: '请输入总仓位', trigger: 'blur' }],
            stop_profit_loss: [{ required: true, message: '请输入止盈止损', trigger: 'blur' }],
            grid_step: [{ required: true, message: '请输入网格间距', trigger: 'blur' }],
            commission_price_difference: [{ required: true, message: '请输入价格差', trigger: 'blur' }]
        },
        // 新增交易信号弹框相关数据
        tradeDialogVisible: false,
        tradeForm: {
          account_id: '',
          name: '',
          symbol: 'ETH-USDT-SWAP',
          price: 0,
          direction: 'buy', // 默认买入
        },
        tradeRules: {
          account_id: [{ required: true, message: '请选择账户', trigger: 'change' }],
          name: [{ required: true, message: '请选择策略', trigger: 'change' }],
          symbol: [{ required: true, message: '请选择交易对', trigger: 'change' }],
          price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
          direction: [{ required: true, message: '请选择操作类型', trigger: 'change' }],
        }
      };
    },
    methods: {
      addMaxPosition() {
        console.log(this.FormData.max_position_list)
        this.FormData.max_position_list.push({ symbol: '', value: 0, max_loss_number: 5, min_loss_ratio: 0.001, increase_ratio: 5, decrease_ratio: 5, clear_value: 2000 });
      },
      removeMaxPosition(index) {
        this.FormData.max_position_list.splice(index, 1);
      },
      isSymbolSelected(symbol) {
        return this.FormData.max_position_list.some(item => item.symbol === symbol);
      },
      getListData(ServerWhere) {
        var that = this.$data;
        if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
          ServerWhere = {
            limit: that.pageSize,
            page: that.currPage,
          };
        }
        get("/Grid/grid/getRobotConfig", ServerWhere, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.total = json.data.data.count;
                this.tableData = json.data.data.data.map(account => ({
                    ...account,
                    balance: '--',
                    balanceLoading: true // 初始化loading状态
                }));
                this.fetchAccountBalances();
            } else {
                this.$message.error("加载数据失败");
            }
        });
      },
      getAccountList() {
        get("/Grid/grid/getAccountList", {}, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.accountList = json.data.data;
            } else {
                this.$message.error("加载账户数据失败");
            }
        });
      },
      getAllStrategyList() {
        get("/Grid/grid/getAllStrategyList", {}, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.strategyOptions = json.data.data;
            } else {
                this.$message.error("加载策略数据失败");
            }
        });
      },
      fetchAccountBalances() {
            this.tableData.forEach(account => {
                get(`/${process.env.SIG_URL_NAME}/get_account_over`, {
                    account_id: account.account_id,
                    inst_id: this.inst_id
                }, response => {
                    console.log(response);
                    if (response.status == 200 && response.data.data) {
                        account.balance = response.data.data.data.trading_balance;
                        account.balanceLoading = false; // 结束loading
                    } else {
                        account.balance = 0;
                        account.balanceLoading = false; // 结束loading
                        // this.$message.error("获取账户余额失败");
                    }
                });
            });
        },
      SearchClick() {
        //搜索事件
        var SearchWhere = {
          page: this.currPage,
          limit: this.pageSize,
        };
        if (this.name && this.name !== "") {
          SearchWhere["name"] = this.name;
        }
        this.PageSearchWhere = [];
        this.PageSearchWhere = SearchWhere;
        this.getListData(SearchWhere);
      },
      SearchReset() {
        //搜索条件重置
        this.province = "";
        this.city = "";
        this.area = "";
        this.PageSearchWhere = [];
        this.getListData();
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
        this.getListData(this.PageSearchWhere); //刷新列表
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
        this.getListData(this.PageSearchWhere); //刷新列表
      },
      DelData(row) { //删除管理员
          this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
              confirmButtonText: '确定',
              cancelButtonText: '取消',
              type: 'warning'
          }).then(() => {
              get('/Grid/grid/deleteRobotConfig', {id: row.id}, (json) => {
                  if (json && json.data.code == 10000) {
                      this.getListData();
                      this.$message({
                          type: 'success',
                          message: '删除成功!'
                      });
                  } else {
                      this.$message.error(json.data.msg);
                  }
              })
          }).catch(() => {
              this.$message({
                  type: 'info',
                  message: '已取消删除'
              });
          });
      },
      UpdateAdminUserInfo(row) { //修改管理员信息 弹框
          console.log(row);
          this.is_save_add_start = 2;
          this.DialogTitle = "修改";
          this.FormData = {
                id: row.id,
                account_id: row.account_id,
                symbol: row.symbol,
                multiple: row.multiple,
                position_percent: row.position_percent,
                total_position: row.total_position,
                stop_profit_loss: row.stop_profit_loss,
                grid_step: row.grid_step,
                commission_price_difference: row.commission_price_difference,
                max_position_list: row.max_position_list, // 中间结构用于动态配置
                grid_percent_list: row.grid_percent_list
            };
          this.dialogVisibleShow = true;
      },
      onUpdateSubmit(formName) { //修改
        const map = {};
        if(this.FormData.max_position_list.length <= 0) {
          this.$message.error('请添加仓位配置');
          return;
        }
        this.FormData.max_position_list.forEach(item => {
          if (item.symbol) map[item.symbol + '-SWAP'] = item.value;  // 加 -SWAP 是为了兼容原有字段
        });

        if (this.FormData.max_position_list.some(item => !item.tactics || item.tactics.trim() === '')) {
          this.$message.error('请为所有交易对选择策略');
          return;
        }

        const gridObj = {};
        this.FormData.grid_percent_list.forEach(item => {
          gridObj[item.direction] = {
            buy: parseFloat(item.buy),
            sell: parseFloat(item.sell)
          };
        });
        this.$refs[formName].validate((valid) => {
          if (valid) {
            const url = this.is_save_add_start === 1
                ? '/Grid/grid/addRobotConfig'
                : '/Grid/grid/updateRobotConfig';
              post(url, this.FormData, (json) => {
                  console.log(json);
                  if (json && json.data.code == 10000) {
                      this.dialogVisibleShow = false;
                      this.$message({
                          type: 'success',
                          message: this.DialogTitle + '成功! 1分钟后生效'
                      });
                      // this.refreshConfig(json.data.data.data);
                      this.getListData();
                  } else {
                      this.dialogVisibleShow = false;
                      // this.$refs.multipleTable.clearSelection();
                      // this.$message.error(this.DialogTitle + '失败');
                      this.$message.error(json.data.msg);
                  }
              })
          } else {
          //   console.log('error submit!!');
            return false;
          }
        });
      },
      AddUserInfoShow() { //添加
        this.is_save_add_start = 1;
        this.DialogTitle = "添加";
        this.FormData = {
            id: '',
            account_id: '',
            symbol: '',
            multiple: '3',
            position_percent: '0.8',
            total_position: '5000',
            stop_profit_loss: '0.007',
            grid_step: '0.002',
            commission_price_difference: '50',
            max_position_list: [], // 中间结构用于动态配置
            grid_percent_list: [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ]
        };
        this.dialogVisibleShow = true;
      },
      startKeywordList(row) { //更改状态
          get('/Admin/Authrule/startAuthRule', {id: row.id, start: row.status}, (json) => {
              if(json.data.code == 10000) {
                  this.$message({
                      message: '修改成功',
                      type: 'success'
                  });
              } else {
                  this.$message.error('修改失败');
              }
          })
      },
      refreshConfig(account_id) { // 刷新持仓机器人本地缓存
        if(account_id) {
            get(`/${process.env.SIG_URL_NAME}/refresh_config`, {
            account_id: account_id
          }, (json) => {
            if (json.status == 200) {
              this.$message({
                  message: '刷新持缓存成功',
                  type: 'success'
              });
            } else {
              this.$message.error('刷新持缓存失败');
            }
          })
        }
      },
      // getAuthMenuRuleData() { //获取角色列表
      //   get('/Admin/Authmenurule/getAuthMenuRuleList', {}, (json) => {
      //     // console.log(json);
      //       if (json && json.data.code == 10000) {
      //         this.AuthMenuRuleData = json.data.data.data;
      //       } else {
      //           this.$message.error('获取权限角色数据失败');
      //       }
      //   })
      // },
      resetForm(formName) {
        this.$refs[formName].resetFields();
        this.dialogVisibleShow = false;
      },
      // 新增交易信号相关方法
      showTradeDialog() {
        this.tradeDialogVisible = true;
        // 重置表单
        this.tradeForm = {
          account_id: '',
          name: '',
          symbol: '',
          price: 0,
        };
      },
      submitTrade(action) {
        this.$refs.tradeForm.validate(valid => {
          if (valid) {
            const params = {
              name: this.tradeForm.name,
              symbol: this.tradeForm.symbol,
              price: this.tradeForm.price,
              // account_id: this.tradeForm.account_id,
              side: this.tradeForm.direction,
                size: action === 'open' 
                ? (this.tradeForm.direction === 'buy' ? '1' : '-1') 
                : '0'
            };
            
            post(`/${process.env.SIG_URL_NAME}/insert_signal`, params, response => {
              if (response.data.success) {
                this.$message.success(`${action === 'open' ? '开仓' : '平仓'}指令发送成功`);
                this.tradeDialogVisible = false;
              } else {
                this.$message.error(response.data.message || '操作失败');
              }
            }).catch(error => {
              this.$message.error("请求失败: " + error.message);
            });
          }
        });
      }
    },
    created() {
      this.getAllStrategyList();
      this.getListData();
      this.getAccountList();
      // this.getAuthMenuRuleData();
    },
    components: {
      "wbc-page": Page //加载分页组件
    }
  };
  </script>

<style lang="scss">
  .grid-form-label {
    .el-form-item__label {
      width: 70px !important;
    }
    .el-form-item__content {
      margin-left: 70px!important;
    }
  }
  .el-descriptions {
    margin-bottom: 20px;
    .el-descriptions__header {
      margin-bottom: 10px;
    }
  }
  .el-descriptions__title {
    display: flex;
  }
  .balance-container {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-left: 20px;
  }
  .loading {
    display: contents;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 14px;
  }
  .loading::after {
    content: ' ';
    display: block;
    width: 16px;
    height: 16px;
    border: 2px solid #606266;
    border-top-color: transparent;
    border-radius: 50%;
    animation: loading 1s linear infinite;
  }
  @keyframes loading {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>