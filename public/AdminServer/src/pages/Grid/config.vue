<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
            <el-breadcrumb-item to="">机器人配置</el-breadcrumb-item>
        </el-breadcrumb>
      <div class="project-top">
        <el-form :inline="true" class="demo-form-inline">
          <div class="toolbar-left">
            <el-form-item>
            <el-select
              class="strategy-filter-select"
              v-model="selectedStrategyName"
              clearable
              filterable
              placeholder="按策略筛选"
              @change="handleStrategyFilterChange"
            >
              <el-option
                v-for="item in strategyFilterOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
            </el-form-item>
          </div>
          <div class="toolbar-right">
            <el-form-item>
              <el-button type="primary" @click="AddUserInfoShow()">添加配置</el-button>
              <el-button type="primary" @click="showTradeDialog()">手动操作</el-button>
            </el-form-item>
          </div>
        </el-form>
      </div>
      <div
        v-loading="listLoading"
        element-loading-text="加载配置中..."
        class="config-list-wrapper">
      <div v-for="(item, index) in tableData" :key="index" class="config-overview-card">
        <div class="config-card-header">
          <div class="config-card-title">
            <div>{{ item.account_id }} &nbsp;&nbsp; {{ item.account_name }}</div>
            <div class="balance-container">
              <span>{{ keepDecimalNotRounding(item.total_balance || 0, 2) }} USDT</span>
            </div>
          </div>
          <div class="config-card-actions">
          <el-button size="mini" type="primary" @click="UpdateAdminUserInfo(item)">编辑</el-button>
          <el-button size="mini" type="danger" @click="DelData(item)">删除</el-button>
          </div>
        </div>

        <div class="config-card-body">
          <div class="config-overview-layout">
            <div class="config-overview-panel config-overview-panel-base">
              <div class="config-overview-panel-title">账户基础配置</div>
              <div class="config-overview-table">
                <div class="config-overview-row">
                  <div class="config-overview-label">API Key</div>
                  <div class="config-overview-value">{{ item.api_key }}</div>
                </div>
                <div class="config-overview-row">
                  <div class="config-overview-label">API Secret</div>
                  <div class="config-overview-value">{{ item.api_secret }}</div>
                </div>
                <div class="config-overview-row config-overview-row-double">
                  <div class="config-overview-cell">
                    <div class="config-overview-label">倍数</div>
                    <div class="config-overview-value">{{ item.multiple }}</div>
                  </div>
                  <div class="config-overview-cell">
                    <div class="config-overview-label">开仓比例</div>
                    <div class="config-overview-value">{{ item.position_percent }}</div>
                  </div>
                </div>
                <div class="config-overview-row">
                  <div class="config-overview-label">总仓位</div>
                  <div class="config-overview-value">{{ item.total_position }}</div>
                </div>
              </div>
            </div>

            <div class="config-overview-panel config-overview-panel-strategy">
              <div class="config-overview-panel-title">币种策略配置</div>
              <div class="config-overview-value config-overview-value-wide">
                <div class="symbol-config-list">
                  <div v-for="(symbolItem, symbolIndex) in item.max_position_list" :key="symbolIndex" class="symbol-config-card">
                    <div class="symbol-config-header">
                      <span class="symbol-config-title">{{ symbolIndex + 1 }}. {{ symbolItem.symbol }}</span>
                      <div class="symbol-config-tags">
                        <span class="symbol-config-tag symbol-config-tag-clickable" @click="openSignalDialog(item, symbolItem)">策略 {{ symbolItem.tactics }}</span>
                        <span class="symbol-config-tag symbol-config-tag-clickable" @click="openPositionDialog(item, symbolItem)">最大仓位 {{ symbolItem.value }}</span>
                      </div>
                    </div>

                    <div class="symbol-config-metrics">
                      <span>止盈止损比 {{ symbolItem.stop_profit_loss }}</span>
                      <span>网格间距 {{ symbolItem.grid_step }}</span>
                      <span>价格浮动比 {{ symbolItem.commission_price_difference }}</span>
                      <span>最大亏损次数 {{ symbolItem.max_loss_number !== '' && symbolItem.max_loss_number !== null && symbolItem.max_loss_number !== undefined ? symbolItem.max_loss_number : '--' }}/{{ symbolItem.loss_number || 0 }}</span>
                      <span>最小亏损比例 {{ symbolItem.min_loss_ratio !== '' && symbolItem.min_loss_ratio !== null && symbolItem.min_loss_ratio !== undefined ? symbolItem.min_loss_ratio * 100 + '%' : '--' }}</span>
                      <span>盈利增加比例 {{ symbolItem.increase_ratio !== '' && symbolItem.increase_ratio !== null && symbolItem.increase_ratio !== undefined ? symbolItem.increase_ratio : '--' }}</span>
                      <span>盈利减少比例 {{ symbolItem.decrease_ratio !== '' && symbolItem.decrease_ratio !== null && symbolItem.decrease_ratio !== undefined ? symbolItem.decrease_ratio : '--' }}</span>
                      <span>清0值 {{ symbolItem.clear_value !== '' && symbolItem.clear_value !== null && symbolItem.clear_value !== undefined ? symbolItem.clear_value : '--' }}</span>
                    </div>

                    <div class="symbol-config-ratios">
                      <div
                        v-for="(gridItem, gridIndex) in symbolItem.grid_percent_list"
                        :key="gridIndex"
                        class="ratio-chip"
                      >
                        <span class="ratio-chip-direction">{{ gridItem.direction }}</span>
                        <span>买 {{ gridItem.buy }}</span>
                        <span>卖 {{ gridItem.sell }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <el-empty
        v-if="!listLoading && (!tableData || tableData.length === 0)"
        class="config-empty-state"
        description="暂无匹配的机器人配置">
      </el-empty>
      </div>
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
          <el-collapse v-model="activeMaxPositionPanels" class="max-position-collapse">
            <el-collapse-item
              v-for="(item, index) in FormData.max_position_list"
              :key="getMaxPositionPanelName(index)"
              :name="getMaxPositionPanelName(index)"
            >
              <template slot="title">
                <div class="max-position-header">
                  <div class="max-position-summary">
                    <span class="summary-primary">{{ item.symbol || '未选择交易对' }}</span>
                    <span class="summary-meta">策略 {{ item.tactics || '--' }}</span>
                    <span class="summary-meta">最大仓位 {{ item.value || 0 }}</span>
                  </div>
                  <el-button
                    type="danger"
                    icon="el-icon-delete"
                    circle
                    size="mini"
                    class="max-position-delete"
                    @click.stop="removeMaxPosition(index)"
                  ></el-button>
                </div>
              </template>

              <div class="config-section">
                <div class="section-title">基础参数</div>
                <div
                  v-if="item.uses_legacy_fallback"
                  class="legacy-fallback-note">
                  当前有部分参数继承自旧账户级配置，保存后会按当前币种配置写入。
                </div>
                <div class="config-grid basic-grid">
                  <div class="field-block">
                    <div class="field-label">交易对</div>
                    <el-select v-model="item.symbol" placeholder="选择交易对">
                      <el-option
                        v-for="symbol in availableSymbols"
                        :key="symbol"
                        :label="symbol"
                        :value="symbol"
                        :disabled="isSymbolSelected(symbol) && item.symbol !== symbol">
                      </el-option>
                    </el-select>
                  </div>
                  <div class="field-block">
                    <div class="field-label">最大仓位</div>
                    <el-input-number
                      v-model="item.value"
                      :min="0"
                      :step="100"
                      class="field-full">
                    </el-input-number>
                  </div>
                  <div class="field-block">
                    <div class="field-label">策略</div>
                    <el-select v-model="item.tactics" placeholder="选择策略">
                      <el-option
                        v-for="strategy in strategyOptions"
                        :key="strategy.name"
                        :label="strategy.name"
                        :value="strategy.name">
                          <span style="float: left">{{ strategy.name }}</span>
                          <span style="float: right; color: #8492a6; font-size: 13px">{{ strategy.label }}</span>
                      </el-option>
                    </el-select>
                  </div>
                  <div class="field-block">
                    <div class="field-label">止盈止损比</div>
                    <el-input v-model="item.stop_profit_loss" placeholder="如 0.007"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">网格间距</div>
                    <el-input v-model="item.grid_step" placeholder="如 0.002"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">价格浮动比(百分比)</div>
                    <el-input v-model="item.commission_price_difference" placeholder="如 50"></el-input>
                  </div>
                </div>
              </div>

              <div class="config-section">
                <div class="section-title">多空比例</div>
                <div class="ratio-list">
                  <div
                    v-for="(gridItem, gridIndex) in item.grid_percent_list"
                    :key="gridIndex"
                    class="ratio-row"
                  >
                    <div class="field-block ratio-direction">
                      <div class="field-label">方向</div>
                      <el-select v-model="gridItem.direction" disabled>
                        <el-option label="做多 (long)" value="long"></el-option>
                        <el-option label="做空 (short)" value="short"></el-option>
                      </el-select>
                    </div>
                    <div class="field-block">
                      <div class="field-label">买入比例</div>
                      <el-input v-model="gridItem.buy" placeholder="买入比例"></el-input>
                    </div>
                    <div class="field-block">
                      <div class="field-label">卖出比例</div>
                      <el-input v-model="gridItem.sell" placeholder="卖出比例"></el-input>
                    </div>
                  </div>
                </div>
              </div>

              <div class="config-section">
                <div class="section-title">风控参数</div>
                <div class="config-grid risk-grid">
                  <div class="field-block">
                    <div class="field-label">最大亏损次数</div>
                    <el-input type="number" v-model="item.max_loss_number" placeholder="请输入最大亏损次数"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">最小亏损比例，单位小数</div>
                    <el-input type="number" v-model="item.min_loss_ratio" placeholder="请输入最小亏损比例 例如：0.001"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">盈利增加比例，单位百分比</div>
                    <el-input type="number" v-model="item.increase_ratio" placeholder="请输入盈利增加比例 例如：5%"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">盈利减少比例，单位百分比</div>
                    <el-input type="number" v-model="item.decrease_ratio" placeholder="请输入盈利减少比例 例如：5%"></el-input>
                  </div>
                  <div class="field-block">
                    <div class="field-label">清0值</div>
                    <el-input type="number" v-model="item.clear_value" placeholder="请输入清0值 例如：5000"></el-input>
                  </div>
                </div>
              </div>
            </el-collapse-item>
          </el-collapse>
          <el-button type="primary" icon="el-icon-plus" @click="addMaxPosition"  :disabled="FormData.max_position_list.length >= availableSymbols.length">添加</el-button>
          <p v-if="FormData.max_position_list.length >= availableSymbols.length" style="color: #999;">
            已添加所有可配置交易对
          </p>
        </el-form-item>
        <el-form-item label="总仓位" prop="total_position">
          <el-input v-model="FormData.total_position" placeholder="如 5000"></el-input>
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
              <el-option
                v-for="symbol in availableSwapSymbols"
                :key="symbol"
                :label="symbol"
                :value="symbol">
              </el-option>
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

      <el-dialog
        :title="signalDialogTitle"
        :visible.sync="signalDialogVisible"
        width="1000px"
        @close="resetSignalDialog"
      >
        <div class="dialog-summary-bar">
          <span>账户：{{ signalDialogContext.account_id || '--' }}</span>
          <span>币种：{{ signalDialogContext.symbol || '--' }}</span>
          <span>策略：{{ signalDialogContext.tactics || '--' }}</span>
        </div>
        <el-table
          :data="signalDialogRows"
          border
          v-loading="signalDialogLoading"
          empty-text="暂无匹配的信号数据"
          style="width: 100%;"
        >
          <el-table-column prop="pair_id" label="配对ID" width="90" align="center" />
          <el-table-column label="类型" align="center" width="120">
            <template slot-scope="scope">
              <span>{{ positionTypeLabel(scope.row) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="direction" label="方向" align="center" width="90">
            <template slot-scope="scope">
              <el-tag :type="orderSideLabel(scope.row) === 'buy' ? 'success' : 'danger'">
                {{ orderSideLabel(scope.row) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="信号类型" align="center" width="90">
            <template slot-scope="scope">
              {{ normalizeSignalSize(scope.row.size) === 0 ? '平仓' : '开仓' }}
            </template>
          </el-table-column>
          <el-table-column prop="price" label="价格" align="center" width="110">
            <template slot-scope="scope">
              {{ formatSignalNumber(scope.row.price) }}
            </template>
          </el-table-column>
          <el-table-column prop="timestamp" label="日期/时间" align="center" width="180">
            <template slot-scope="scope">
              {{ scope.row.position_at || scope.row.timestamp || '--' }}
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" align="center" min-width="130">
            <template slot-scope="scope">
              <el-tag :type="getSignalStatusTagType(scope.row)">
                {{ getSignalStatusText(scope.row) }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty
          v-if="!signalDialogLoading && signalDialogRows.length === 0"
          description="暂无匹配的信号数据"
        />
        <el-row v-if="signalDialogTotal > 0" class="dialog-pagination">
          <el-col :span="24">
            <div style="float:right;">
              <wbc-page
                :total="signalDialogTotal"
                :pageSize="signalDialogPageSize"
                :currPage="signalDialogCurrPage"
                @changeLimit="changeSignalDialogLimit"
                @changeSkip="changeSignalDialogPage"
              ></wbc-page>
            </div>
          </el-col>
        </el-row>
      </el-dialog>

      <el-dialog
        :title="positionDialogTitle"
        :visible.sync="positionDialogVisible"
        width="1100px"
        @close="resetPositionDialog"
      >
        <div class="dialog-summary-bar">
          <span>账户：{{ positionDialogContext.account_id || '--' }}</span>
          <span>币种：{{ positionDialogContext.symbol || '--' }}</span>
          <span>策略：{{ positionDialogContext.tactics || '--' }}</span>
        </div>
        <el-tabs v-model="positionDialogTab" @tab-click="handlePositionDialogTabClick">
          <el-tab-pane label="当前持仓" name="current">
            <el-table
              :data="currentPositionRows"
              border
              v-loading="currentPositionLoading"
              empty-text="暂无当前持仓"
              style="width: 100%;"
            >
              <el-table-column prop="symbol" label="交易品种" align="center" width="140" />
              <el-table-column label="仓位状态" align="center" width="100">
                <template slot-scope="scope">
                  <span>{{ formatMarginMode(scope.row) }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="side" label="持仓方向" align="center" width="90">
                <template slot-scope="scope">
                  <span v-if="scope.row.side === 'long'">开多</span>
                  <span v-else-if="scope.row.side === 'short'">开空</span>
                  <span v-else>--</span>
                </template>
              </el-table-column>
              <el-table-column label="持仓量" align="center" min-width="120">
                <template slot-scope="scope">
                  <span>{{ scope.row.contracts || 0 }} 张</span>
                </template>
              </el-table-column>
              <el-table-column label="开仓均价" align="center" width="120">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.entryPrice || 0, 2, true) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="标记价格" align="center" width="120">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.markPrice || 0, 2, true) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="浮动收益" align="center" width="120">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.unrealizedPnl || 0, 2, true) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="开仓时间" align="center" width="180">
                <template slot-scope="scope">
                  <span>{{ getPositionOpenTime(scope.row) }}</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty
              v-if="!currentPositionLoading && currentPositionRows.length === 0"
              description="暂无当前持仓"
            />
          </el-tab-pane>
          <el-tab-pane label="历史持仓" name="history">
            <el-table
              :data="pagedHistoryPositionRows"
              border
              v-loading="historyPositionLoading"
              empty-text="暂无历史持仓"
              style="width: 100%;"
            >
              <el-table-column prop="symbol" label="交易品种" align="center" width="120" />
              <el-table-column label="仓位状态" align="center" width="96">
                <template slot-scope="scope">
                  <span>{{ getHistoryPositionStatus(scope.row) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="交易方向" align="center" width="96">
                <template slot-scope="scope">
                  <span>{{ getHistoryPositionDirection(scope.row) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="开仓均价 / 平仓均价" align="center" width="132">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.entryPrice || 0, 2, true) }}</span>
                  <br>
                  <span>{{ keepDecimalNotRounding(scope.row.lastPrice || 0, 2, true) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="收益 / 收益率" align="center" width="128">
                <template slot-scope="scope">
                  <span>{{ keepDecimalNotRounding(scope.row.realizedPnl || 0, 2, true) }}</span>
                  <br>
                  <span>{{ formatHistoryProfitRate(scope.row) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="开仓时间" align="center" width="160">
                <template slot-scope="scope">
                  <span>{{ timestampToTime(getHistoryInfo(scope.row).cTime) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="平仓时间" align="center" width="160">
                <template slot-scope="scope">
                  <span>{{ timestampToTime(getHistoryInfo(scope.row).uTime) }}</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty
              v-if="!historyPositionLoading && historyPositionRows.length === 0"
              description="暂无历史持仓"
            />
            <el-row v-if="historyPositionTotal > 0" class="dialog-pagination">
              <el-col :span="24">
                <div style="float:right;">
                  <wbc-page
                    :total="historyPositionTotal"
                    :pageSize="historyPositionPageSize"
                    :currPage="historyPositionCurrPage"
                    @changeLimit="changeHistoryPositionLimit"
                    @changeSkip="changeHistoryPositionPage"
                  ></wbc-page>
                </div>
              </el-col>
            </el-row>
          </el-tab-pane>
        </el-tabs>
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
        selectedStrategyName: "",
        tableData: [],
        listLoading: false,
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
            max_loss_number: '',
            min_loss_ratio: '',
            increase_ratio: '',
            decrease_ratio: '',
            clear_value: '',
            max_position: '', // 最终提交还是字符串
            max_position_list: [], // 中间结构用于动态配置
            grid_percent_list: [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ]
        },
        accountList: [], // 账户列表
        availableSymbols: ['BTC-USDT', 'ETH-USDT', 'BNB-USDT', 'DOGE-USDT'],
        availableSwapSymbols: ['BTC-USDT-SWAP', 'ETH-USDT-SWAP', 'BNB-USDT-SWAP', 'DOGE-USDT-SWAP'],
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
          symbol: '',
          price: 0,
          direction: 'buy', // 默认买入
        },
        tradeRules: {
          account_id: [{ required: true, message: '请选择账户', trigger: 'change' }],
          name: [{ required: true, message: '请选择策略', trigger: 'change' }],
          symbol: [{ required: true, message: '请选择交易对', trigger: 'change' }],
          price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
          direction: [{ required: true, message: '请选择操作类型', trigger: 'change' }],
        },
        signalDialogVisible: false,
        signalDialogLoading: false,
        signalDialogRows: [],
        signalDialogTotal: 0,
        signalDialogPageSize: 10,
        signalDialogCurrPage: 1,
        signalDialogContext: {
          account_id: '',
          symbol: '',
          tactics: ''
        },
        positionDialogVisible: false,
        positionDialogTab: 'current',
        currentPositionLoading: false,
        historyPositionLoading: false,
        currentPositionRows: [],
        historyPositionRows: [],
        historyPositionTotal: 0,
        historyPositionPageSize: 10,
        historyPositionCurrPage: 1,
        positionDialogContext: {
          account_id: '',
          symbol: '',
          inst_id: '',
          tactics: ''
        },
        balanceCacheTTL: 60 * 1000,
        activeMaxPositionPanels: []
      };
    },
    computed: {
      strategyFilterOptions() {
        const options = (this.strategyOptions || []).map(item => ({
          label: item.name,
          value: item.name
        }));
        return [{ label: '全部策略', value: '' }, ...options];
      },
      signalDialogTitle() {
        const ctx = this.signalDialogContext || {};
        return `策略信号 - 账户${ctx.account_id || '--'} / ${ctx.symbol || '--'} / ${ctx.tactics || '--'}`;
      },
      positionDialogTitle() {
        const ctx = this.positionDialogContext || {};
        return `持仓详情 - 账户${ctx.account_id || '--'} / ${ctx.symbol || '--'}`;
      },
      pagedHistoryPositionRows() {
        const start = (this.historyPositionCurrPage - 1) * this.historyPositionPageSize;
        const end = start + this.historyPositionPageSize;
        return this.historyPositionRows.slice(start, end);
      }
    },
    methods: {
      getMaxPositionPanelName(index) {
        return `max-position-${index}`;
      },
      getDefaultGridPercentList(sourceList) {
        const fallbackList = Array.isArray(sourceList) && sourceList.length > 0
          ? sourceList
          : [
              { direction: 'long', buy: 0.04, sell: 0.05 },
              { direction: 'short', buy: 0.05, sell: 0.04 }
            ];
        return fallbackList.map(item => ({
          direction: item.direction,
          buy: item.buy,
          sell: item.sell
        }));
      },
      getDefaultSymbolConfigValues() {
        return {
          stop_profit_loss: this.FormData.stop_profit_loss || '0.007',
          grid_step: this.FormData.grid_step || '0.002',
          commission_price_difference: this.FormData.commission_price_difference || '50',
          grid_percent_list: this.getDefaultGridPercentList(this.FormData.grid_percent_list),
          max_loss_number: this.FormData.max_loss_number !== undefined && this.FormData.max_loss_number !== null ? this.FormData.max_loss_number : '',
          min_loss_ratio: this.FormData.min_loss_ratio !== undefined && this.FormData.min_loss_ratio !== null ? this.FormData.min_loss_ratio : '',
          increase_ratio: this.FormData.increase_ratio !== undefined && this.FormData.increase_ratio !== null ? this.FormData.increase_ratio : '',
          decrease_ratio: this.FormData.decrease_ratio !== undefined && this.FormData.decrease_ratio !== null ? this.FormData.decrease_ratio : '',
          clear_value: this.FormData.clear_value !== undefined && this.FormData.clear_value !== null ? this.FormData.clear_value : ''
        };
      },
      normalizeMaxPositionItem(item = {}, fallbackValues = {}) {
        const fallbackFields = [];
        const readValue = (field) => {
          if (item[field] !== undefined && item[field] !== null && item[field] !== '') {
            return item[field];
          }
          if (fallbackValues[field] !== undefined && fallbackValues[field] !== null && fallbackValues[field] !== '') {
            fallbackFields.push(field);
            return fallbackValues[field];
          }
          return '';
        };
        const itemGridPercentList = Array.isArray(item.grid_percent_list) ? item.grid_percent_list : [];
        const fallbackGridPercentList = Array.isArray(fallbackValues.grid_percent_list) ? fallbackValues.grid_percent_list : [];
        const useFallbackGridPercentList = itemGridPercentList.length <= 0 && fallbackGridPercentList.length > 0;
        if (useFallbackGridPercentList) {
          fallbackFields.push('grid_percent_list');
        }
        return {
          symbol: item.symbol || '',
          value: item.value || 0,
          tactics: item.tactics || '',
          stop_profit_loss: readValue('stop_profit_loss'),
          grid_step: readValue('grid_step'),
          commission_price_difference: readValue('commission_price_difference'),
          grid_percent_list: this.getDefaultGridPercentList(itemGridPercentList.length > 0 ? itemGridPercentList : fallbackGridPercentList),
          max_loss_number: readValue('max_loss_number'),
          min_loss_ratio: readValue('min_loss_ratio'),
          increase_ratio: readValue('increase_ratio'),
          decrease_ratio: readValue('decrease_ratio'),
          clear_value: readValue('clear_value'),
          loss_number: item.loss_number || 0,
          uses_legacy_fallback: fallbackFields.length > 0,
          legacy_fallback_fields: fallbackFields
        };
      },
      normalizeMaxPositionListForForm(maxPositionList = [], fallbackValues = {}) {
        return (maxPositionList || []).map(item => this.normalizeMaxPositionItem(item, fallbackValues));
      },
      addMaxPosition() {
        this.FormData.max_position_list.push(this.normalizeMaxPositionItem({}, this.getDefaultSymbolConfigValues()));
        this.$nextTick(() => {
          this.activeMaxPositionPanels = [this.getMaxPositionPanelName(this.FormData.max_position_list.length - 1)];
        });
      },
      removeMaxPosition(index) {
        this.FormData.max_position_list.splice(index, 1);
        this.activeMaxPositionPanels = this.activeMaxPositionPanels
          .filter(name => name !== this.getMaxPositionPanelName(index))
          .map((name) => {
            const currentIndex = Number(String(name).split('-').pop());
            if (Number.isNaN(currentIndex) || currentIndex < index) {
              return name;
            }
            return this.getMaxPositionPanelName(currentIndex - 1);
          });
      },
      isSymbolSelected(symbol) {
        return this.FormData.max_position_list.some(item => item.symbol === symbol);
      },
      openSignalDialog(accountItem, symbolItem) {
        this.signalDialogContext = {
          account_id: accountItem.account_id,
          symbol: symbolItem.symbol || '',
          tactics: symbolItem.tactics || ''
        };
        this.signalDialogRows = [];
        this.signalDialogTotal = 0;
        this.signalDialogCurrPage = 1;
        this.signalDialogVisible = true;
        this.loadSignalDialogData();
      },
      loadSignalDialogData() {
        const ctx = this.signalDialogContext || {};
        if (!ctx.tactics || !ctx.symbol) {
          this.signalDialogRows = [];
          return;
        }
        this.signalDialogLoading = true;
        get('/Grid/grid/getSignalsList', {
          page: this.signalDialogCurrPage,
          limit: this.signalDialogPageSize,
          strategy_name: ctx.tactics,
          symbol: ctx.symbol,
          open_only: 1
        }, json => {
          this.signalDialogLoading = false;
          if (json.data.code == 10000) {
            this.signalDialogRows = (json.data.data && json.data.data.lists) || [];
            this.signalDialogTotal = (json.data.data && json.data.data.count) || 0;
          } else {
            this.signalDialogRows = [];
            this.signalDialogTotal = 0;
            this.$message.error(json.data.msg || '加载策略信号失败');
          }
        });
      },
      changeSignalDialogLimit(limit) {
        this.signalDialogPageSize = limit;
        this.signalDialogCurrPage = 1;
        this.loadSignalDialogData();
      },
      changeSignalDialogPage(page) {
        this.signalDialogCurrPage = page;
        this.loadSignalDialogData();
      },
      resetSignalDialog() {
        this.signalDialogVisible = false;
        this.signalDialogLoading = false;
        this.signalDialogRows = [];
        this.signalDialogTotal = 0;
        this.signalDialogPageSize = 10;
        this.signalDialogCurrPage = 1;
        this.signalDialogContext = {
          account_id: '',
          symbol: '',
          tactics: ''
        };
      },
      openPositionDialog(accountItem, symbolItem) {
        const symbol = symbolItem.symbol || '';
        this.positionDialogContext = {
          account_id: accountItem.account_id,
          symbol: symbol,
          inst_id: this.toSwapInstId(symbol),
          tactics: symbolItem.tactics || ''
        };
        this.positionDialogTab = 'current';
        this.currentPositionRows = [];
        this.historyPositionRows = [];
        this.historyPositionTotal = 0;
        this.historyPositionCurrPage = 1;
        this.positionDialogVisible = true;
        this.loadCurrentPositionData();
      },
      resetPositionDialog() {
        this.positionDialogVisible = false;
        this.positionDialogTab = 'current';
        this.currentPositionLoading = false;
        this.historyPositionLoading = false;
        this.currentPositionRows = [];
        this.historyPositionRows = [];
        this.historyPositionTotal = 0;
        this.historyPositionPageSize = 10;
        this.historyPositionCurrPage = 1;
        this.positionDialogContext = {
          account_id: '',
          symbol: '',
          inst_id: '',
          tactics: ''
        };
      },
      handlePositionDialogTabClick(tab) {
        if (tab.name === 'history' && this.historyPositionRows.length === 0) {
          this.loadHistoryPositionData();
        }
      },
      toSwapInstId(symbol) {
        if (!symbol) return '';
        const normalized = String(symbol).toUpperCase();
        return normalized.endsWith('-SWAP') ? normalized : `${normalized}-SWAP`;
      },
      loadCurrentPositionData() {
        const ctx = this.positionDialogContext || {};
        if (!ctx.account_id || !ctx.inst_id) {
          this.currentPositionRows = [];
          return;
        }
        this.currentPositionLoading = true;
        get('/sigadmin/get_current_positions', {
          account_id: ctx.account_id,
          inst_id: ctx.inst_id
        }, json => {
          this.currentPositionLoading = false;
          if (json.status == 200) {
            this.currentPositionRows = Array.isArray(json.data.data) ? json.data.data : [];
          } else {
            this.currentPositionRows = [];
            this.$message.error('加载当前持仓失败');
          }
        });
      },
      loadHistoryPositionData() {
        const ctx = this.positionDialogContext || {};
        if (!ctx.account_id || !ctx.inst_id) {
          this.historyPositionRows = [];
          return;
        }
        this.historyPositionLoading = true;
        get('/sigadmin/get_positions_history', {
          account_id: ctx.account_id,
          inst_id: ctx.inst_id,
          limit: 50,
          page: 1
        }, json => {
          this.historyPositionLoading = false;
          if (json.status == 200) {
            this.historyPositionRows = Array.isArray(json.data.data) ? json.data.data : [];
            this.historyPositionTotal = this.historyPositionRows.length;
            this.historyPositionCurrPage = 1;
          } else {
            this.historyPositionRows = [];
            this.historyPositionTotal = 0;
            this.$message.error('加载历史持仓失败');
          }
        });
      },
      changeHistoryPositionLimit(limit) {
        this.historyPositionPageSize = limit;
        this.historyPositionCurrPage = 1;
      },
      changeHistoryPositionPage(page) {
        this.historyPositionCurrPage = page;
      },
      normalizeSignalSize(size) {
        const numericSize = Number(size);
        if (isNaN(numericSize)) return 0;
        if (numericSize > 0) return 1;
        if (numericSize < 0) return -1;
        return 0;
      },
      positionTypeLabel(row) {
        const sz = this.normalizeSignalSize(row.size);
        const dir = (row.direction || '').toLowerCase();
        if (sz === 1) return '多头进场';
        if (sz === -1) return '空头进场';
        if (sz === 0) {
          if (dir === 'long') return '空头出场';
          if (dir === 'short') return '多头出场';
          return '平仓';
        }
        return '--';
      },
      orderSideLabel(row) {
        return (row.direction || '').toLowerCase() === 'long' ? 'buy' : 'sell';
      },
      formatSignalNumber(num) {
        if (isNaN(num)) return '--';
        return parseFloat(num).toFixed(1);
      },
      parseJsonArray(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        try {
          const parsed = JSON.parse(value);
          return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          return [];
        }
      },
      getFailedAccountCount(row) {
        return this.parseJsonArray(row.failed_accounts).length;
      },
      getSignalStatusTagType(row) {
        const status = (row.status || '').toLowerCase();
        const statusMap = {
          pending: 'warning',
          processing: 'warning',
          partial: '',
          processed: 'success',
          failed: 'danger'
        };
        return statusMap[status] || 'info';
      },
      getSignalStatusText(row) {
        const status = (row.status || '').toLowerCase();
        const failedCount = this.getFailedAccountCount(row);
        if (status === 'pending') return '待处理';
        if (status === 'processing') return '处理中';
        if (status === 'processed') return '已完成';
        if (status === 'failed') return failedCount > 0 ? `失败(${failedCount}账户)` : '失败';
        if (status === 'partial') return failedCount > 0 ? `部分完成(${failedCount}待恢复)` : '部分完成';
        return row.status || '--';
      },
      formatMarginMode(row) {
        if (!row || !row.marginMode) return '--';
        if (row.marginMode === 'cross') return '全仓';
        if (row.marginMode === 'isolated') return '逐仓';
        return row.marginMode;
      },
      getPositionOpenTime(row) {
        const rowInfo = row && row.info ? row.info : {};
        return this.timestampToTime((row && (row.datetime || row.timestamp || row.openTime)) || rowInfo.cTime);
      },
      getHistoryInfo(row) {
        return row && row.info ? row.info : {};
      },
      getHistoryPositionStatus(row) {
        const type = this.getHistoryInfo(row).type;
        if (type === '1') return '部分平仓';
        if (type === '2') return '完全平仓';
        if (type === '3') return '强平';
        if (type === '4') return '强减';
        if (type === '5') return 'ADL自动减仓';
        return '--';
      },
      getHistoryPositionDirection(row) {
        const info = this.getHistoryInfo(row);
        if (info.direction === 'long' && info.posSide === 'long') return '买入开多';
        if (info.direction === 'long' && info.posSide === 'short') return '买入平空';
        if (info.direction === 'short' && info.posSide === 'long') return '卖出平多';
        if (info.direction === 'short' && info.posSide === 'short') return '卖出开空';
        return '--';
      },
      formatHistoryProfitRate(row) {
        const entryPrice = Number(row.entryPrice || 0);
        const contractSize = Number(row.contractSize || 0);
        const realizedPnl = Number(row.realizedPnl || 0);
        const base = contractSize * entryPrice;
        if (!base) {
          return '--';
        }
        return `${this.keepDecimalNotRounding ? this.keepDecimalNotRounding(realizedPnl / base * 100, 2, true) : ((realizedPnl / base) * 100).toFixed(2)}%`;
      },
      timestampToTime(value) {
        if (!value) return '--';
        const numeric = Number(value);
        if (isNaN(numeric) || numeric <= 0) {
          return value;
        }
        const date = new Date(String(value).length === 13 ? numeric : numeric * 1000);
        const pad = (num) => String(num).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
      },
      getListData(ServerWhere) {
        var that = this.$data;
        this.listLoading = true;
        if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
          ServerWhere = {
            limit: that.pageSize,
            page: that.currPage,
          };
        }
        if (this.selectedStrategyName) {
          ServerWhere.strategy_name = this.selectedStrategyName;
        } else {
          delete ServerWhere.strategy_name;
        }
        get("/Grid/grid/getRobotConfig", ServerWhere, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.total = json.data.data.count;
                // 将 getRobotConfig 的数据与 accountList 进行匹配，补充余额信息
                const robotData = json.data.data.data || [];
                const enrichedData = robotData.map(robot => {
                  const account = this.accountList.find(acc => acc.id === robot.account_id);
                  const fallbackValues = {
                    stop_profit_loss: robot.stop_profit_loss,
                    grid_step: robot.grid_step,
                    commission_price_difference: robot.commission_price_difference,
                    grid_percent_list: robot.grid_percent_list,
                    max_loss_number: robot.max_loss_number,
                    min_loss_ratio: robot.min_loss_ratio,
                    increase_ratio: robot.increase_ratio,
                    decrease_ratio: robot.decrease_ratio,
                    clear_value: robot.clear_value
                  };
                  return {
                    ...robot,
                    max_position_list: this.normalizeMaxPositionListForForm(robot.max_position_list, fallbackValues),
                    total_balance: account ? account.total_balance : 0
                  };
                });
                
                // 按余额从大到小排序
                this.tableData = enrichedData.sort((a, b) => {
                  const balanceA = Number(a.total_balance) || 0;
                  const balanceB = Number(b.total_balance) || 0;
                  return balanceB - balanceA;
                });
            } else {
                this.tableData = [];
                this.$message.error("加载数据失败");
            }
            this.listLoading = false;
        }, () => {
            this.tableData = [];
            this.listLoading = false;
        });
      },
      getAccountList() {
        get("/Grid/grid/getAccountList", {}, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.accountList = json.data.data;
                // 在账户列表加载完成后，再加载机器人配置
                this.getListData();
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
      SearchClick() {
        //搜索事件
        var SearchWhere = {
          page: this.currPage,
          limit: this.pageSize,
        };
        if (this.name && this.name !== "") {
          SearchWhere["name"] = this.name;
        }
        if (this.selectedStrategyName) {
          SearchWhere["strategy_name"] = this.selectedStrategyName;
        }
        this.PageSearchWhere = [];
        this.PageSearchWhere = SearchWhere;
        this.getListData(SearchWhere);
      },
      handleStrategyFilterChange() {
        this.currPage = 1;
        const searchWhere = {
          page: 1,
          limit: this.pageSize
        };
        if (this.selectedStrategyName) {
          searchWhere.strategy_name = this.selectedStrategyName;
        }
        this.PageSearchWhere = searchWhere;
        this.getListData(searchWhere);
      },
      SearchReset() {
        //搜索条件重置
        this.province = "";
        this.city = "";
        this.area = "";
        this.selectedStrategyName = "";
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
          this.is_save_add_start = 2;
          this.DialogTitle = "修改";
          const fallbackValues = {
            stop_profit_loss: row.stop_profit_loss,
            grid_step: row.grid_step,
            commission_price_difference: row.commission_price_difference,
            grid_percent_list: row.grid_percent_list,
            max_loss_number: row.max_loss_number,
            min_loss_ratio: row.min_loss_ratio,
            increase_ratio: row.increase_ratio,
            decrease_ratio: row.decrease_ratio,
            clear_value: row.clear_value
          };
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
                max_loss_number: row.max_loss_number,
                min_loss_ratio: row.min_loss_ratio,
                increase_ratio: row.increase_ratio,
                decrease_ratio: row.decrease_ratio,
                clear_value: row.clear_value,
                max_position_list: this.normalizeMaxPositionListForForm(row.max_position_list, fallbackValues),
                grid_percent_list: this.getDefaultGridPercentList(row.grid_percent_list)
            };
          this.activeMaxPositionPanels = this.FormData.max_position_list.map((_, index) => this.getMaxPositionPanelName(index));
          this.dialogVisibleShow = true;
      },
      onUpdateSubmit(formName) { //修改
        if(this.FormData.max_position_list.length <= 0) {
          this.$message.error('请添加仓位配置');
          return;
        }

        if (this.FormData.max_position_list.some(item => !item.symbol)) {
          this.$message.error('请为所有交易对选择币种');
          return;
        }

        if (this.FormData.max_position_list.some(item => !item.tactics || item.tactics.trim() === '')) {
          this.$message.error('请为所有交易对选择策略');
          return;
        }

        if (this.FormData.max_position_list.some(item => !item.stop_profit_loss || !item.grid_step || !item.commission_price_difference)) {
          this.$message.error('请补全每个交易对的止盈止损、网格间距和价格浮动比');
          return;
        }

        if (this.FormData.max_position_list.some(item => !item.grid_percent_list || item.grid_percent_list.length <= 0)) {
          this.$message.error('请补全每个交易对的网格买卖比例');
          return;
        }

        const payload = {
          ...this.FormData,
          max_position_list: this.FormData.max_position_list.map(item => ({
            symbol: item.symbol,
            value: item.value,
            tactics: item.tactics,
            stop_profit_loss: item.stop_profit_loss,
            grid_step: item.grid_step,
            commission_price_difference: item.commission_price_difference,
            grid_percent_list: this.getDefaultGridPercentList(item.grid_percent_list),
            max_loss_number: item.max_loss_number,
            min_loss_ratio: item.min_loss_ratio,
            increase_ratio: item.increase_ratio,
            decrease_ratio: item.decrease_ratio,
            clear_value: item.clear_value,
            loss_number: item.loss_number || 0
          })),
          grid_percent_list: this.getDefaultGridPercentList(this.FormData.grid_percent_list)
        };

        this.$refs[formName].validate((valid) => {
          if (valid) {
            const url = this.is_save_add_start === 1
                ? '/Grid/grid/addRobotConfig'
                : '/Grid/grid/updateRobotConfig';
              post(url, payload, (json) => {
                  if (json && json.data.code == 10000) {
                      this.dialogVisibleShow = false;
                      this.$message({
                          type: 'success',
                          message: this.DialogTitle + '成功! 1分钟后生效'
                      });
                      this.getListData();
                  } else {
                      this.dialogVisibleShow = false;
                      this.$message.error(json.data.msg);
                  }
              })
          } else {
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
            max_loss_number: '',
            min_loss_ratio: '',
            increase_ratio: '',
            decrease_ratio: '',
            clear_value: '',
            max_position_list: [],
            grid_percent_list: this.getDefaultGridPercentList()
        };
        this.activeMaxPositionPanels = [];
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
        this.activeMaxPositionPanels = [];
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
      this.getAccountList();  // 这会触发 getListData()
    },
    components: {
      "wbc-page": Page //加载分页组件
    }
  };
  </script>

<style lang="scss">
  .project-top {
    .demo-form-inline {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 12px;

      .toolbar-left,
      .toolbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
      }

      .toolbar-right {
        margin-left: auto;
        justify-content: flex-end;
      }

      .el-form-item {
        margin-right: 0;
        margin-bottom: 0;
      }
    }
  }

  .strategy-filter-select {
    width: 220px;
  }

  .config-list-wrapper {
    min-height: 320px;
  }

  .config-empty-state {
    padding: 48px 0 56px;
    background: #fff;
  }

  .max-position-collapse {
    border-top: none;
    margin-bottom: 12px;

    .el-collapse-item {
      margin-bottom: 12px;
      border: 1px solid #ebeef5;
      border-radius: 6px;
      overflow: hidden;
      background: #fff;
    }

    .el-collapse-item__header {
      height: auto;
      line-height: normal;
      padding: 14px 16px;
      border-bottom: 1px solid transparent;
      background: #fafafa;
    }

    .el-collapse-item__wrap {
      border-bottom: none;
    }

    .el-collapse-item__content {
      padding: 16px;
    }
  }

  .max-position-header {
    width: calc(100% - 24px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .max-position-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    min-width: 0;
  }

  .summary-primary {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
  }

  .summary-meta {
    font-size: 13px;
    color: #606266;
  }

  .symbol-config-tag-clickable {
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
      color: #409eff;
      background: #ecf5ff;
    }
  }

  .max-position-delete {
    flex: 0 0 auto;
  }

  .config-section + .config-section {
    margin-top: 20px;
  }

  .section-title {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 600;
    color: #303133;
  }

  .legacy-fallback-note {
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #f3d19e;
    border-radius: 6px;
    background: #fdf6ec;
    color: #8a5a00;
    font-size: 13px;
    line-height: 20px;
  }

  .config-grid {
    display: grid;
    gap: 12px;
  }

  .basic-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  }

  .risk-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  }

  .field-block {
    min-width: 0;
  }

  .field-label {
    margin-bottom: 6px;
    line-height: 20px;
    color: #606266;
    font-size: 13px;
  }

  .field-block .el-select,
  .field-block .el-input,
  .field-block .el-input-number,
  .field-full {
    width: 100%;
  }

  .ratio-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .ratio-row {
    display: grid;
    grid-template-columns: 180px 1fr 1fr;
    gap: 12px;
  }

  .ratio-direction {
    max-width: 180px;
  }

  .el-descriptions {
    margin-bottom: 20px;
    .el-descriptions__header {
      margin-bottom: 10px;
    }
  }
  .symbol-config-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
  }

  .config-overview-card {
    margin-bottom: 20px;
    padding: 16px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    background: #fff;
  }

  .config-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }

  .config-card-title {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 18px;
    font-weight: 600;
    color: #303133;
  }

  .config-card-actions {
    display: flex;
    gap: 10px;
    flex: 0 0 auto;
  }

  .config-card-body {
    min-width: 0;
  }

  .config-overview-layout {
    display: grid;
    grid-template-columns: minmax(340px, 0.92fr) minmax(560px, 1.48fr);
    gap: 16px;
    align-items: start;
  }

  .config-overview-panel {
    min-width: 0;
  }

  .config-overview-panel-title {
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #303133;
  }

  .dialog-summary-bar {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #f5f7fa;
    color: #606266;
    font-size: 13px;
  }

  .dialog-pagination {
    margin-top: 16px;
  }

  .el-dialog__body .el-empty {
    padding: 24px 0 8px;
  }

  .config-overview-table {
    border: 1px solid #ebeef5;
    border-bottom: none;
    background: #fff;
  }

  .config-overview-row {
    display: grid;
    grid-template-columns: 140px minmax(220px, 1fr);
  }

  .config-overview-row-double {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .config-overview-cell {
    display: grid;
    grid-template-columns: 120px minmax(0, 1fr);
    min-width: 0;
  }

  .config-overview-cell + .config-overview-cell {
    border-left: 1px solid #ebeef5;
  }

  .config-overview-label,
  .config-overview-value {
    padding: 18px 16px;
    border-bottom: 1px solid #ebeef5;
    min-width: 0;
  }

  .config-overview-label {
    display: flex;
    align-items: center;
    color: #909399;
    background: #fafafa;
    font-size: 14px;
  }

  .config-overview-value {
    display: flex;
    align-items: center;
    color: #303133;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-all;
  }

  .config-overview-value-wide {
    display: block;
    padding: 0;
    border: 1px solid #ebeef5;
    background: #fff;
  }

  .symbol-config-card {
    padding: 12px 14px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    background: #fff;
  }

  .symbol-config-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 10px;
  }

  .symbol-config-title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }

  .symbol-config-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .symbol-config-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f0f7ff;
    color: #409eff;
    font-size: 12px;
    line-height: 1;
  }

  .symbol-config-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px 16px;
    margin-bottom: 10px;
    color: #606266;
    font-size: 13px;
    line-height: 20px;
  }

  .symbol-config-ratios {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .ratio-chip {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #ebeef5;
    color: #606266;
    font-size: 12px;
  }

  .ratio-chip-direction {
    font-weight: 600;
    color: #303133;
    text-transform: lowercase;
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
  @keyframes loading {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  @media (max-width: 1200px) {
    .config-overview-layout {
      grid-template-columns: 1fr;
    }

    .config-card-header {
      flex-direction: column;
      align-items: stretch;
    }

    .config-card-actions {
      justify-content: flex-end;
    }

    .ratio-row {
      grid-template-columns: 1fr;
    }

    .ratio-direction {
      max-width: none;
    }

    .symbol-config-header {
      align-items: flex-start;
    }

    .config-overview-row-double {
      grid-template-columns: 1fr;
    }

    .config-overview-cell + .config-overview-cell {
      border-left: none;
      border-top: 1px solid #ebeef5;
    }
  }
</style>
