<template>
    <div class="container">
        <div style="text-align:right;margin-bottom: 10px;">
            <el-button type="primary" @click="shareProfitShow()">分润</el-button>
            <el-button type="primary" @click="shareProfitRecordShow()">分润记录</el-button>
            &nbsp;
            <el-button type="primary" @click="DepositWithdrawalShow()">出入金</el-button>
            <el-button type="primary" @click="dialogVisibleListClick()">出入金记录</el-button>
        </div>
        <el-tabs v-model="activeName" :tab-position="isMobel ? 'top' : 'left'" :stretch="isMobel ? true : false" style="background-color: #fff;" @tab-click="tabsHandleClick">
            <el-tab-pane :data-id="item.id" :label="item.name" :name="item.name" v-for="(item, index) in accountList" :key="index">
                <div v-if="!isMobel">
                    <el-table
                        :data="tableData"
                        style="width: 100%">
                        <el-table-column prop="date" label="日期" align="center"></el-table-column>
                        <el-table-column prop="principal" label="累计本金(U)" align="center" width="150">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.principal, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="总结余(U)" align="center">
                            <template slot-scope="scope">
                            <!-- <el-link type="primary" @click="accountBalanceDetailsFun(scope.row.account_id)"> -->
                            <el-link type="primary" @click="getTotalBalanceClick()">
                                <span>{{ keepDecimalNotRounding(scope.row.total_balance, 2, true) }}</span>
                            </el-link>
                            </template>
                        </el-table-column>
                        <!-- <el-table-column prop="" label="币价" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.price, 2, true) }} USDT</span>
                            </template>
                        </el-table-column> -->
                        <el-table-column prop="" label="日利润(U)" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.daily_profit, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="日利润率" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.daily_profit_rate, 2, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="平均日利率" align="center" width="100">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.average_day_rate, 2, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="平均年利率" align="center" width="130">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.average_year_rate, 2, true) }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="利润(U)" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.profit, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="总分润" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.total_share_profit, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="总利润" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.total_profit, 2, true) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="利润率" align="center">
                            <template slot-scope="scope">
                            <span>{{ keepDecimalNotRounding(scope.row.profit_rate * 100, 2, true) }}%</span>
                            </template>
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
                    <div v-if="tableData.length">
                        <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in tableData" :key="index">
                            <el-descriptions-item label="日期">{{ item.date }}</el-descriptions-item>
                            <!-- <el-descriptions-item label="账户名称">{{ item.account }}</el-descriptions-item> -->
                            <el-descriptions-item label="总结余">{{ keepDecimalNotRounding(item.total_balance, 2, true) }} USDT</el-descriptions-item>
                            <!-- <el-descriptions-item label="币价">{{ keepDecimalNotRounding(item.price, 2, true) }} USDT</el-descriptions-item> -->
                            <el-descriptions-item label="日利润">{{ keepDecimalNotRounding(item.daily_profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="日利润率">{{ keepDecimalNotRounding(item.daily_profit_rate, 4, true) }}</el-descriptions-item>
                            <el-descriptions-item label="平均日利率">{{ keepDecimalNotRounding(item.average_day_rate, 2, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="平均年利率">{{ keepDecimalNotRounding(item.average_year_rate, 2, true) }}%</el-descriptions-item>
                            <el-descriptions-item label="利润">{{ keepDecimalNotRounding(item.profit, 2, true) }} USDT</el-descriptions-item>
                            <el-descriptions-item label="利润率">{{ keepDecimalNotRounding(item.profit_rate * 100, 2, true) }}%</el-descriptions-item>
                        </el-descriptions>
                    </div>
                    <div v-else>
                        <el-empty description="没有数据"></el-empty>
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

         <el-dialog
            :title="activeName + ' 出/入金'"
            :visible.sync="dialogVisibleShow"
            width="50%">
            <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="80px">
                <el-form-item label="方向" prop="direction">
                    <el-radio-group v-model="ruleForm.direction">
                    <el-radio label="1">入金</el-radio>
                    <el-radio label="2">出金</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="金额" prop="amount">
                    <el-input v-model="ruleForm.amount"></el-input>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="ruleForm.remark"></el-input>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button @click="dialogVisibleShow = false">取 消</el-button>
                <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
            </span>
        </el-dialog>

         <el-dialog
            title="出/入金记录"
            :visible.sync="dialogVisibleListShow"
            width="50%">
            <el-table :data="InoutGoldList" style="width: 100%;" height="500">
                <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column>
                <el-table-column prop="time" label="时间" align="center" width="200"></el-table-column>
                <el-table-column prop="amount" label="出入金额" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.amount, 8, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="type" label="类型" align="center">
                    <template slot-scope="scope">
                    <span v-if="scope.row.type == 1">入金</span>
                    <span v-else>出金</span>
                    </template>
                </el-table-column>
                <el-table-column prop="total_balance" label="账户余额" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.total_balance, 4, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="备注" align="center">
                    <template slot-scope="scope">
                    <span>{{ scope.row.remark }}</span>
                    </template>
                </el-table-column>
            </el-table>
            <el-row class="pages">
                <el-col :span="24">
                    <div style="float:right;">
                    <wbc-page
                        :total="InoutGoldTotal"
                        :pageSize="InoutGoldPageSize"
                        :currPage="InoutGoldCurrPage"
                        @changeLimit="InoutGoldLimitPaging"
                        @changeSkip="InoutGoldSkipPaging"
                    ></wbc-page>
                    </div>
                </el-col>
            </el-row>
        </el-dialog>

        <!-- 账户余额明细数据 -->
        <el-dialog
            title="账户余额明细"
            :visible.sync="accountBalanceDetailsShow"
            width="80%">
            <el-tabs v-model="accountBalanceTabValue" @tab-click="accountBalanceTabClick">
                <el-tab-pane label="余额明细" name="1" v-if="tabAccountId !== 7">
                    <el-select v-model="currency" clearable placeholder="请选择" @change="selectCurrencyChange">
                        <el-option
                            v-for="item in currencyList"
                            :key="item"
                            :label="item"
                            :value="item">
                        </el-option>
                    </el-select>
                    <el-table :data="accountBalanceDetailsList" style="width: 100%;" height="500">
                        <el-table-column prop="currency" label="币种" align="center" width="">
                            <template slot-scope="scope">
                                <el-link type="primary" @click="getAccountCurrencyDetailsShow(scope.row.currency)">
                                    <span>{{ scope.row.currency }}</span>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="价格" align="center" width="150">
                            <template slot-scope="scope">
                                <span>{{ scope.row.price ? keepDecimalNotRounding(scope.row.price, 10, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="余额" align="center" width="150">
                            <template slot-scope="scope">
                                <span>{{ scope.row.balance ? keepDecimalNotRounding(scope.row.balance, 10, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="USDT估值" align="center" width="">
                            <template slot-scope="scope">
                                <span v-if="scope.row.currency !== 'USDT'">{{ scope.row.valuation ? keepDecimalNotRounding(scope.row.valuation, 4, true) : 0 }}</span>
                                <span v-else>————</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="time" label="更新时间" align="center" width="200"></el-table-column>
                    </el-table>
                    <el-row class="pages">
                        <el-col :span="24">
                            <div style="float:right;">
                            <wbc-page
                                :total="accountBalanceDetailsTotal"
                                :pageSize="accountBalanceDetailsLimit"
                                :currPage="accountBalanceDetailsPage"
                                @changeLimit="accountBalanceLimitPaging"
                                @changeSkip="accountBalanceSkipPaging"
                            ></wbc-page>
                            </div>
                        </el-col>
                    </el-row>
                </el-tab-pane>
                <el-tab-pane label="持仓信息" name="2">
                    <el-table :data="currencyPositionsList" style="width: 100%;" height="500">
                        <el-table-column prop="currency" label="币种" align="center" width="">
                            <template slot-scope="scope">
                                <el-link type="primary" @click="getAccountCurrencyDetailsShow(scope.row.currency)">
                                    <span>{{ scope.row.currency }}</span>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="保证金模式" align="center">
                            <template slot-scope="scope">
                                <span v-if="scope.row.mgn_mode === 'cross'">全仓</span>
                                <span v-if="scope.row.mgn_mode === 'nisolated'">逐仓</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="持仓方向" align="center" width="">
                            <template slot-scope="scope">
                                <span v-if="scope.row.pos_side === 'long'">多头</span>
                                <span v-if="scope.row.pos_side === 'short'">空头</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="持仓数量" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.pos ? keepDecimalNotRounding(scope.row.pos, 4, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="开仓均价" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.avg_px ? keepDecimalNotRounding(scope.row.avg_px, 8, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                         <el-table-column prop="" label="标记价格" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.mark_px ? keepDecimalNotRounding(scope.row.mark_px, 8, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="保证金余额" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.margin_balance ? keepDecimalNotRounding(scope.row.margin_balance, 8, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="保证金率" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.margin_ratio ? keepDecimalNotRounding(scope.row.margin_ratio * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="收益" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.upl ? keepDecimalNotRounding(scope.row.upl, 8, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="收益率" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.upl_ratio ? keepDecimalNotRounding(scope.row.upl_ratio * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="最大收益率" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.max_upl_rate ? keepDecimalNotRounding(scope.row.max_upl_rate * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="最小收益率" align="center" width="">
                            <template slot-scope="scope">
                                <span>{{ scope.row.min_upl_rate ? keepDecimalNotRounding(scope.row.min_upl_rate * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="rate_average" label="平均值" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.rate_average ? keepDecimalNotRounding(scope.row.rate_average * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="time" label="更新时间" align="center" width="200"></el-table-column>
                    </el-table>
                    <el-row class="pages">
                        <el-col :span="24">
                            <div style="float:right;">
                            <wbc-page
                                :total="currencyPositionsTotal"
                                :pageSize="currencyPositionsLimit"
                                :currPage="currencyPositionsPage"
                                @changeLimit="currencyPositionsLimitPaging"
                                @changeSkip="currencyPositionsSkipPaging"
                            ></wbc-page>
                            </div>
                        </el-col>
                    </el-row>
                </el-tab-pane>
                 <el-tab-pane label="收益率列表" name="3" v-if="tabAccountId == 7">
                    <el-table :data="maxMinUplRateList" style="width: 100%;" height="">
                        <el-table-column label="序号">
                            <template slot-scope="scope">
                                {{ scope.$index + 1 }} 
                            </template>
                        </el-table-column>
                        <el-table-column prop="" label="持仓方向" align="center" width="">
                            <template slot-scope="scope">
                                <span v-if="scope.row.pos_side === 'long'">多头</span>
                                <span v-if="scope.row.pos_side === 'short'">空头</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="amount" label="最大收益率" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.max_rate ? keepDecimalNotRounding(scope.row.max_rate * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="total_profit" label="最小收益率" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.min_rate ? keepDecimalNotRounding(scope.row.min_rate * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="total_profit" label="平仓收益率" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.closing_yield ? keepDecimalNotRounding(scope.row.closing_yield * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="total_profit" label="平仓价格" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.avg_price ? keepDecimalNotRounding(scope.row.avg_price, 2, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="opening_price" label="开仓价格" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.opening_price ? keepDecimalNotRounding(scope.row.opening_price, 2, true) : 0 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="rate_average" label="平均值" align="center" width="">
                            <template slot-scope="scope">
                            <span>{{ scope.row.rate_average ? keepDecimalNotRounding(scope.row.rate_average * 100, 2, true) : 0 }}%</span>
                            </template>
                        </el-table-column>
                        <!-- <el-table-column prop="time" label="时间" align="center" width="200"></el-table-column> -->
                    </el-table>
                    <el-row class="pages">
                        <el-col :span="24">
                            <div style="float:right;">
                            <wbc-page
                                :total="maxMinUplRateTotal"
                                :pageSize="maxMinUplRateLimit"
                                :currPage="maxMinUplRatePage"
                                @changeLimit="maxMinUplRateLimitPaging"
                                @changeSkip="maxMinUplRatePaging"
                            ></wbc-page>
                            </div>
                        </el-col>
                    </el-row>
                 </el-tab-pane>
            </el-tabs>
        </el-dialog>

        <!-- 账户币种交易明细 -->
        <el-dialog
            title="账户币种交易明细"
            :visible.sync="accountCurrencyDetailsShow"
            width="80%"
            v-loading="loading">
            <el-table :data="accountCurrencyDetailsList" style="width: 100%;" height="600" >
                <!-- <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column> -->
                <!-- <el-table-column prop="currency" label="币种" align="center" width="">
                    <template slot-scope="scope">
                        <el-link type="primary" @click="getAccountCurrencyDetailsList(scope.row.currency)">
                            <span>{{ scope.row.currency }}</span>
                        </el-link>
                    </template>
                </el-table-column> -->
                <el-table-column prop="" label="订单id" align="center" width="150">
                    <template slot-scope="scope">
                        <span>{{ scope.row.order_id }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="成交价格" align="center" width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="成交量" align="center" width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.qty }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="成交总价" align="center" width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.quote_total_price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="成交方向" align="center" width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.side }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="成交时间" align="center" width="">
                    <template slot-scope="scope">
                        <span>{{ scope.row.trade_time }}</span>
                    </template>
                </el-table-column>
            </el-table>
            <el-row class="pages">
                <el-col :span="24">
                    <div style="float:right;">
                    <wbc-page
                        :total="accountCurrencyDetailsTotal"
                        :pageSize="accountCurrencyDetailsLimit"
                        :currPage="accountCurrencyDetailsPage"
                        @changeLimit="accountCurrencyDetailsLimitPaging"
                        @changeSkip="accountCurrencyDetailsSkipPaging"
                    ></wbc-page>
                    </div>
                </el-col>
            </el-row>
        </el-dialog>

        <el-dialog
            :title="activeName + ' 分润'"
            :visible.sync="profitShow"
            width="50%">
            <el-form :model="ruleProfitForm" :rules="rules" ref="ruleProfitForm" label-width="80px" class="profit-loading"> 
                <el-form-item label="金额" prop="amount">
                    <el-input v-model="ruleProfitForm.amount"></el-input>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="ruleProfitForm.remark"></el-input>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button @click="profitShow = false" :disabled="loading">取 消</el-button>
                <el-button type="primary" @click="submitProfitForm('ruleProfitForm')" :disabled="loading">确 定</el-button>
            </span>
        </el-dialog>

        <el-dialog
            title="分润记录"
            :visible.sync="profitListShow"
            width="50%">
            <el-table :data="profitGoldList" style="width: 100%;" height="500">
                <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column>
                <el-table-column prop="time" label="时间" align="center" width="200"></el-table-column>
                <el-table-column prop="amount" label="出入金额" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.amount, 8, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="total_profit" label="总分润" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.total_profit, 4, true) }} USDT</span>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="备注" align="center">
                    <template slot-scope="scope">
                    <span>{{ scope.row.remark }}</span>
                    </template>
                </el-table-column>
            </el-table>
            <el-row class="pages">
                <el-col :span="24">
                    <div style="float:right;">
                    <wbc-page
                        :total="profitGoldTotal"
                        :pageSize="profitGoldPageSize"
                        :currPage="profitGoldCurrPage"
                        @changeLimit="profitGoldLimitPaging"
                        @changeSkip="profitGoldSkipPaging"
                    ></wbc-page>
                    </div>
                </el-col>
            </el-row>
        </el-dialog>

        <!-- <el-dialog
            title="收益率列表"
            :visible.sync="maxMinUplRateShow"
            width="50%">
            <el-table :data="maxMinUplRateList" style="width: 100%;" height="500">
                <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left" type="index"></el-table-column>
                <el-table-column prop="" label="持仓方向" align="center" width="">
                    <template slot-scope="scope">
                        <span v-if="scope.row.pos_side === 'long'">多头</span>
                        <span v-if="scope.row.pos_side === 'short'">空头</span>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" label="最大收益率" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.max_rate * 100, 2, true) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column prop="total_profit" label="最小收益率" align="center" width="150">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.min_rate * 100, 2, true) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column prop="rate_average" label="平均值" align="center" width="">
                    <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.rate_average * 100, 2, true) }}%</span>
                    </template>
                </el-table-column>
                <el-table-column prop="time" label="时间" align="center" width="200"></el-table-column>
            </el-table>
            <el-row class="pages">
                <el-col :span="24">
                    <div style="float:right;">
                    <wbc-page
                        :total="maxMinUplRateTotal"
                        :pageSize="maxMinUplRateLimit"
                        :currPage="maxMinUplRatePage"
                        @changeLimit="maxMinUplRateLimitPaging"
                        @changeSkip="maxMinUplRatePaging"
                    ></wbc-page>
                    </div>
                </el-col>
            </el-row>
        </el-dialog> -->
    </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getPoolBtcData } from "@/wallet/serve";
import Page from "@/components/Page.vue";
export default {
    name: '',
    data() {
        return {
            active: 2,
            btc_price: 0,
            tableData: [],
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 1, //总条数
            loading: false,
            finished: false,
            PageSearchWhere: [],
            dialogVisibleShow: false,
            dialogVisibleListShow: false,
            profitShow: false,
            profitListShow: false,
            ruleForm: {
                direction: '',
                amount: '',
                remark: '',
            },
            ruleProfitForm: {
                amount: '',
                remark: '',
            },
            rules: {
                amount: [
                    { required: true, message: '请输入金额', trigger: 'blur' },
                ],
                direction: [
                    { required: true, message: '请选择方向', trigger: 'change' }
                ],
            },
            activeName: 'MartinObserve',
            tabAccountId: 1,
            accountList: [],
            InoutGoldList: [],
            InoutGoldCurrPage: 1, //当前页
            InoutGoldPageSize: 20, //每页显示条数
            InoutGoldTotal: 1, //总条数
            accountBalanceDetailsShow: false,
            accountBalanceDetailsList: [],
            accountBalanceDetailsPage: 1,
            accountBalanceDetailsLimit: 20,
            accountBalanceDetailsTotal: 0, //总条数
            currencyList: [],
            currency: '',
            accountBalanceTabValue: '',

            currencyPositionsList: [],
            currencyPositionsPage: 1,
            currencyPositionsLimit: 20,
            currencyPositionsTotal: 0, //总条数

            accountCurrencyDetailsShow: false,
            accountCurrencyDetailsList: [],
            accountCurrencyDetailsPage: 1,
            accountCurrencyDetailsLimit: 20,
            accountCurrencyDetailsTotal: 0, //总条数
            selectCurrency: '',

            profitGoldCurrPage: 1, //当前页
            profitGoldPageSize: 20, //每页显示条数
            profitGoldTotal: 1, //总条数
            profitGoldList: [],

            maxMinUplRateShow: false,
            maxMinUplRateLimit: 20,
            maxMinUplRatePage: 1,
            maxMinUplRateList: [],
            maxMinUplRateTotal: 0,
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
        // this.getList();
        this.getAccountList();
    },
    watch: {

    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    methods: {
        getList(ServerWhere) {
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: this.pageSize,
                    page: this.currPage,
                    account_id: this.tabAccountId,
                };
            }
            console.log(ServerWhere);
            this.loading = true;
            get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountDateList", ServerWhere, json => {
                // console.log(json.data);
                if (json.code == 10000) {
                    if (json.data.data) {
                        let list = (json.data && json.data.data) || [];
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
            });
        },
        getAccountList() {
            get(this.apiUrl + "/Api/QuantifyAccount/getAccountList", {}, json => {
                console.log(json.data);
                if (json.code == 10000) {
                    this.accountList = json.data;
                }
            })
        },
        accountBalanceDetailsFun(account_id) { //余额明细数据
            // console.log(account_id);
            // this.accountBalanceTabValue = '1'
            get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountDetails", {
                account_id: this.tabAccountId,
                limit: this.accountBalanceDetailsLimit,
                page: this.accountBalanceDetailsPage,
                currency: this.currency,
            }, json => {
                console.log(json.data);
                if (json.code == 10000) {
                    this.accountBalanceDetailsList = json.data.lists;
                    this.accountBalanceDetailsTotal = json.data.count;
                    if(account_id) {
                        this.getCurrencyList();
                    }
                }
            })
        },
        getCurrencyList() {
            get(this.apiUrl + "/Api/QuantifyAccount/getQuantifyAccountCurrencyList", {
                account_id: this.tabAccountId,
            }, json => {
                console.log(json.data);
                if (json.code == 10000) {
                    this.currencyList = json.data;
                }
            })
        },
        selectCurrencyChange() { //根据币种选择对应的账户余额明细数据
            this.accountBalanceDetailsFun();
        },
        getAccountCurrencyDetailsShow(currency) { //获取账户币种交易明细数据
            this.accountCurrencyDetailsList = [];
            this.accountCurrencyDetailsShow = true;
            this.selectCurrency = currency;
            this.accountCurrencyDetailsPage = 1;
            this.accountCurrencyDetailsTotal = 0;
            this.getAccountCurrencyDetailsList();
        },
        getAccountCurrencyDetailsList() { //获取账户币种交易明细数据
            this.loading = true;
            get(this.apiUrl + "/Api/QuantifyAccount/getAccountCurrencyDetailsList", {
                limit: this.accountCurrencyDetailsLimit,
                page: this.accountCurrencyDetailsPage,
                account_id: this.tabAccountId,
                currency: this.selectCurrency,
            }, json => {
                console.log(json.data);
                this.loading = false;
                if (json.code == 10000) {
                    this.accountCurrencyDetailsList = json.data.data;
                    this.accountCurrencyDetailsTotal = json.data.count;
                }
            })
        },

        limitPaging(limit) {
            //赋值当前条数
            this.pageSize = limit;
            this.getList(); //刷新列表
        },
        skipPaging(page) {
            //赋值当前页数
            this.currPage = page;
            this.getList(); //刷新列表
        },
        copySuccess() {
            this.$message({
                message: 'Copy successfully!',
                type: 'success'
            });
        },
        submitForm(formName) { //出入金
            this.$refs[formName].validate((valid) => {
                if (valid) {
                    const loading = this.$loading({
                        target: '.el-dialog',
                    });
                    get('/Api/QuantifyAccount/calcDepositAndWithdrawal', {
                        account_id: this.tabAccountId,
                        direction: this.ruleForm.direction,
                        amount: this.ruleForm.amount,
                        remark: this.ruleForm.remark,
                    }, (json) => {
                        console.log(json);
                        if (json && json.code == 10000) {
                            this.$message.success('更新成功');
                            this.$refs[formName].resetFields();
                            loading.close();
                            this.dialogVisibleShow = false;
                            this.getList();
                        } else {
                            loading.close();
                            this.$message.error(json.data.msg);
                        }
                    })
                } else {
                    console.log('error submit!!');
                    return false;
                }
            });
        },
        dialogVisibleListClick() {
            this.dialogVisibleListShow = true;
            this.getInoutGoldList();
        },
        accountBalanceTabClick(tab) {
            console.log(tab);
            this.currencyPositionsList = [];
            this.accountBalanceDetailsList = [];
            if(tab.name == 1) {
                // this.accountBalanceTabValue = '1'
                this.accountBalanceDetailsFun();
            }
            if(tab.name == 2) {
                // this.accountBalanceTabValue = '2'
                this.getAccountCurrencyPositionsList();
            }
            if(tab.name == 3) {
                this.getMaxMinUplRate();
            }
        },
        getTotalBalanceClick() { //获取总结余弹框
            this.currencyPositionsList = [];
            this.accountBalanceDetailsList = [];
            if(this.tabAccountId == 7) {
                this.accountBalanceTabValue = '2'
                this.getAccountCurrencyPositionsList();
            } else {
                this.accountBalanceTabValue = '1'
                this.accountBalanceDetailsFun();
            }
            this.accountBalanceDetailsShow = true;
        },
        getAccountCurrencyPositionsList() {
            get("/Api/QuantifyAccount/getAccountCurrencyPositionsList", {
                limit: this.currencyPositionsLimit,
                page: this.currencyPositionsPage,
                account_id: this.tabAccountId,
                currency: 'GMX',
            }, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.currencyPositionsList = json.data.lists;
                    this.currencyPositionsTotal = json.data.count;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getInoutGoldList(ServerWhere) { //获取出入金记录数据
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.InoutGoldPageSize,
                    page: that.InoutGoldCurrPage,
                    account_id: that.tabAccountId,
                };
            }
            get("/Api/QuantifyAccount/getInoutGoldList", ServerWhere, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.InoutGoldList = json.data.data;
                    this.InoutGoldTotal = json.data.count;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        getProfitGoldList(ServerWhere) { //获取分润记录数据
            var that = this.$data;
            if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
                ServerWhere = {
                    limit: that.profitGoldPageSize,
                    page: that.profitGoldCurrPage,
                    account_id: that.tabAccountId,
                };
            }
            get("/Api/QuantifyAccount/getDividendRecordList", ServerWhere, json => {
                console.log(json);
                if (json.code == 10000) {
                    this.profitGoldList = json.data.data;
                    this.profitGoldTotal = json.data.count;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        InoutGoldLimitPaging(limit) {
            //赋值当前条数
            this.InoutGoldPageSize = limit;
            this.getInoutGoldList(); //刷新列表
        },
        InoutGoldSkipPaging(page) {
            //赋值当前页数
            this.InoutGoldCurrPage = page;
            this.getInoutGoldList(); //刷新列表
        },
        profitGoldLimitPaging(limit) {
            //赋值当前条数
            this.profitGoldPageSize = limit;
            this.getProfitGoldList(); //刷新列表
        },
        profitGoldSkipPaging(page) {
            //赋值当前页数
            this.profitGoldCurrPage = page;
            this.getProfitGoldList(); //刷新列表
        },
        accountCurrencyDetailsLimitPaging(limit) {
            //赋值当前条数
            this.accountCurrencyDetailsLimit = limit;
            this.getAccountCurrencyDetailsList(); //刷新列表
        },
        accountCurrencyDetailsSkipPaging(page) {
            //赋值当前页数
            this.accountCurrencyDetailsPage = page;
            this.getAccountCurrencyDetailsList(); //刷新列表
        },
        accountBalanceLimitPaging(limit) {
            //赋值当前条数
            this.accountBalanceDetailsLimit = limit;
            this.accountBalanceDetailsFun(); //刷新列表
        },
        accountBalanceSkipPaging(page) {
            //赋值当前页数
            this.accountBalanceDetailsPage = page;
            this.accountBalanceDetailsFun(); //刷新列表
        },
        currencyPositionsLimitPaging(limit) {
            this.currencyPositionsLimit = limit;
            this.getAccountCurrencyPositionsList();
        },  
        currencyPositionsSkipPaging(page) {
            this.currencyPositionsPage = page;
            this.getAccountCurrencyPositionsList();
        },
        resetForm(formName) {
            this.$refs[formName].resetFields();
        },
        DepositWithdrawalShow() { //出入金 弹框显示\
            this.dialogVisibleShow = true;
        },
        tabsHandleClick(tab, event) { //tab切换
            // console.log(tab.$attrs['data-id'], event);
            this.tabAccountId = tab.$attrs['data-id'];
            this.pageSize = 20;
            this.currPage = 1;
            this.tableData = [];
            this.getList();
        },
        shareProfitShow() { //分润 弹框
            this.profitShow = true;
        },
        shareProfitRecordShow() { //分润记录
            this.profitListShow = true;
            this.getProfitGoldList();
        },
        submitProfitForm(formName) { //分润
            this.$refs[formName].validate((valid) => {
                if (valid) {
                    const loading = this.$loading({
                        target: '.profit-loading',
                    });
                    this.loading = true;
                    get('/Api/QuantifyAccount/calcDividendRecord', {
                        account_id: this.tabAccountId,
                        amount: this.ruleProfitForm.amount,
                        remark: this.ruleProfitForm.remark,
                    }, (json) => {
                        console.log(json);
                        this.loading = false;
                        this.profitShow = false;
                        if (json && json.code == 10000) {
                            this.$message.success('更新成功');
                            this.$refs[formName].resetFields();
                            loading.close();
                            this.getList();
                        } else {
                            loading.close();
                            this.$message.error(json.data.msg);
                        }
                    })
                } else {
                    console.log('error submit!!');
                    return false;
                }
            });
        },
        getMaxMinUplRate() { //获取收益率最大最小历史记录
            get("/Api/QuantifyAccount/getMaxMinUplRateData", {
                limit: this.maxMinUplRateLimit,
                page: this.maxMinUplRatePage,
                account_id: this.tabAccountId,
                currency: 'GMX',
            }, json => {
                console.log(json);
                // this.maxMinUplRateShow = true;
                if (json.code == 10000) {
                    this.maxMinUplRateList = json.data.lists;
                    this.maxMinUplRateTotal = json.data.count;
                } else {
                    this.$message.error("加载数据失败");
                }
            });
        },
        maxMinUplRateLimitPaging(limit) {
            //赋值当前条数
            this.maxMinUplRateLimit = limit;
            this.getMaxMinUplRate(); //刷新列表
        },
        maxMinUplRatePaging(page) {
            //赋值当前页数
            this.maxMinUplRatePage = page;
            this.getMaxMinUplRate(); //刷新列表
        },
    },
    mounted() {

    },
}
</script>
<style lang="scss" scoped>
     .container {
        /deep/ {
            .el-breadcrumb {
                height: 25px;
                font-size: 10px;
            }
            .el-table {
                font-size: 10px;
            }
            .el-tabs__item {
                font-size: 10px;
            }
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
