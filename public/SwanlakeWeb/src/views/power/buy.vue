<template>
  <div class="container">
    <!-- <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item :to="{ path: '/financial/product' }">理财产品</el-breadcrumb-item>
      <el-breadcrumb-item>投注</el-breadcrumb-item>
    </el-breadcrumb> -->
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <el-page-header @back="goBack" :content="detailData.name">
          <template slot="content">
            <span>{{ detailData.name }}</span>
            <el-link type="primary" :href="detailData.chain_address" target='_blank'>{{ '查看合约' }}</el-link>
          </template>
        </el-page-header>
      </div>
      <!-- <el-container class="container"> -->
      <!-- <el-header>Header</el-header> -->
      <el-main class="mian">

        <el-row class="mian-detail" :gutter="20" v-loading="detailLoding">
          <!-- <el-col :span="12">
              <el-card class="box-card box-card-left" shadow="never">
                <el-image :src="detailData.img">
                  <div slot="error" class="image-slot">
                    <i class="el-icon-picture-outline"></i>
                  </div>
                </el-image>
              </el-card>
            </el-col> -->
          <el-row>
            <el-col :span="24">
              <!-- <el-card class="box-card box-card-right" shadow="never"> -->
              <div slot="header" class="clearfix">
                <span>{{ detailData.name }}</span>
                <!-- <el-button style="float: right; padding: 3px 0" type="text">操作按钮</el-button> -->
              </div>
              <div class="text item">
                <el-row class="content" :style="'width:' + isMobel ? '100%' : '80%'">
                  <el-col :span="24" align="right">
                    <el-button style="float: right; padding: 3px 0" type="text"
                      @click="hashpowerPanelShow = true">算力规模</el-button>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">规格</p>
                    <p class="balance">{{ detailData.specific }}</p>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">功耗比</p>
                    <p class="balance">{{ detailData.power_consumption_ratio }} W/THS</p>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">电价</p>
                    <p class="balance">{{ detailData.electricity_price }} USDT/KWH</p>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">单位</p>
                    <p class="balance">1T * 7Day</p>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">价格</p>
                    <p class="balance">{{ keepDecimalNotRounding(detailData.price || 0, 4) }} USDT</p>
                  </el-col>
                  <el-col :span="isMobel ? 12 : 8" align="center">
                    <p class="desc">库存</p>
                    <p class="balance" v-if="detailData.stock <= 0">{{ $t('subscribe:SoldOut') }}</p>
                    <p class="balance" v-else>{{ detailData.stock }}</p>
                  </el-col>
                </el-row>
                <el-row>
                  <el-col align="center" :span="24">
                    <font color="red" style="font-size:10px;">{{ $t('subscribe:Desc') }}</font>
                  </el-col>
                </el-row>
              </div>
              <!-- </el-card> -->
            </el-col>
            <el-col :span="24" class="el-card-num">
              <!-- <el-card class="box-card box-card-right-bottom" shadow="never"> -->
              <el-row>
                <el-col :span="24">
                  <!-- {{ $t('subscribe:Amount(T)') }}({{ detailData.hash_rate }}T)： -->
                  <el-input v-model.trim="num" type="number" onkeyup="value=value.replace(/[^0-9]/g, '')" :main="1"
                    placeholder="请输入数量">
                    <!-- <template slot="prepend">1T*7Day</template> -->
                    <!-- <template slot="append">
                              <el-button v-if="type == 1" type="primary" @click="allfunBetClick()">全投</el-button>
                              <el-button v-else type="primary" @click="allfunRedClick()">全部</el-button>
                          </template> -->
                  </el-input>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="24">
                  <span style="float: right;">
                    <span>
                      账户余额：
                      <el-link type="primary" style="font-size:14px;" @click="clickUsdtBalance()">
                        {{ toFixed(Number(walletBalance) + Number(userInfo.local_balance), 4) }}
                      </el-link>
                    </span>&nbsp;&nbsp;
                    <span>USDT: $ {{ detailData.price * num }}</span>
                  </span>
                </el-col>
              </el-row>
              <!-- <el-row class="recomme">
                    <el-col :span="24">
                      <el-checkbox v-model="is_recomme_code">我有推荐码</el-checkbox>
                      <el-input v-if="is_recomme_code" v-model.trim="recomme_code" type="text" minlength=”4“ maxlength="8" placeholder="请输入推荐码" @keyup.native="recommeCodeCheck" @keydown.native="extraordinaryCheck"></el-input>
                    </el-col>
                  </el-row> -->
              <!-- </el-card> -->
            </el-col>
          </el-row>
        </el-row>
        <el-row>
          <el-col :span="24" class="agree-item">
            <div class="agree-item-checkbox">
              <el-checkbox v-model="agree">{{ $t('subscribe:IACCEPT') }}</el-checkbox>
            </div>
            <div class="agree-item-buttom">
              <el-button :disabled="!agree || trading || detailData.stock <= 0" v-loading="trading"
                @click="startPurchase">{{
                  detailData.stock > 0 ? '租赁' : $t('subscribe:SoldOut') }}</el-button>
            </div>
            <!-- <div class="receive-agree-item-buttom">
                <span>{{ $t('subscribe:RequiresPledgeIncome') }}</span>
                <el-link type="primary" @click="receiveBenefits">{{ $t('subscribe:RequiresPledgeIncomeLike') }}</el-link>
              </div> -->
          </el-col>
        </el-row>

        <!-- 算力币说明 -->
        <el-row v-show="false">
          <el-col :span="24" class="content">
            <el-card class="box-card" shadow="never" v-if="$t('public:language') === 'zh'">
              <section data-role="paragraph" class="_135editor">
                <h3 data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px 0px 20px; padding: 0px; border: 0px; font-size: 16px !important; vertical-align: baseline; background-color: #ffffff; text-decoration-thickness: initial; font-family: FontAwesome, &quot;Microsoft YaHei&quot;, STXihei; color: #555555; text-align: center;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline;"></strong></span><strong>{{
                    detailData.name }} 算力币合约</strong><span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"><strong
                      data-v-532aaa84=""></strong></span>
                </h3>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin-top: 0px; margin-right: 0px; margin-bottom: 5px !important; margin-left: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; line-height: 18px !important; font-family: emoji, BlinkMacSystemFont, -apple-system, &quot;Helvetica Neue&quot;, Arial, sans-serif, &quot;PingFang SC&quot;, Helvetica, &quot;sans-serif&quot;, &quot;Segoe UI&quot;, Roboto, Oxygen, Ubuntu, Cantarell, &quot;Fira Sans&quot;, &quot;Droid Sans&quot; !important; color: #000000; background-color: #ffffff; text-decoration-thickness: initial;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; caret-color: #000000; color: #000000; font-family: &quot;PingFang HK&quot;; -webkit-text-stroke-color: #000000;">买入算力币质押立即即可获取BTC和SWAN收益。</span><br
                    data-v-532aaa84="" />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">一、{{
                      detailData.name }}算力币合约的确认</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"></span>&nbsp;&nbsp;&nbsp;&nbsp;1、请您先仔细阅读本合约内容，充分理解本合约及各条款，尤其是字体加粗部分。如果您对本合约内容有任何疑问，请勿进行下一步操作。您可通过SwanLake官方电报群进行咨询，以便我们为您解释和说明。您通过页面访问或其他方式确认即表示您已同意本合约。<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"></span>&nbsp;&nbsp;&nbsp;&nbsp;2、如我们对本合约进行修改，我们将通过www.swanlake.club网站公告的方式提前予以公布，变更后的协议在公告届满30日起生效。<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;3</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、您确认：</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">a</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">）您年满</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">18</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">周岁并具有完全民事行为能力。</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">b</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">）您接受并使用SwanLake提供的服务在您的居住地</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">/</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">国家符合适用法律法规和相关政策，且不违反您对于任何其他第三方的义务。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">&nbsp;&nbsp;&nbsp;&nbsp;您发现当由于事实或法律法规变化您无法承诺本条</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">a</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">和</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">/</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">或</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">b</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">款规定的内容时，您会立即停止使用SwanLake提供的服务并通过客服务渠道告知SwanLake停止服务。终止服务后，您使用SwanLake服务的权利立即终止。您同意：在这种情况下，SwanLake没有义务将任何未处理的信息或未完成的服务传输给您或任何第三方。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">二、算力币的购买</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、</span>购买算力币{{
                  detailData.name }}，则代表SwanLake和您之间就该订单所约定的算力产生了一份具有约束力的合约（“{{ detailData.name
                  }}算力币合约”），该合约约定了算力的具体内容<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;2</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、用户在线下单并支付成功后，订单无法撤销。SwanLake不支持任何取消订单和退款的请求，请下单前仔细阅读并确认本合约条款</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">三、电费与维护费的收取</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、</span>SwanLake对{{
                  detailData.name }}以美元0.065每千瓦时为标准，向用户收取维护费（含电费和管理费），根据算力巢实际测试该矿机的功耗计算每天每TH/S的维护费，计算公式如下<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;">计算公式：每</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">T&nbsp;</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;">功耗（</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">KW/H</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;">）</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">X24</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;">小时</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">X
                    0.065USD</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;2</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、</span>电费在收益分配的同时收取，并参考收益发放同一时刻的AICoin平台上美元对比特币或泰达币，转换后从用户账户余额中扣除<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;3</span><span
                      style="font-family:PingFang HK">、</span></span><span
                    style="font-family:PingFang HK">如果根据实际运营情况需要对维护费进行调整，我们将通过
                    www.swanlake.club网站公告的方式提前3天予以公布，变更后的维护费在公告届满3天起生效</span><span data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">四、算力币{{
                      detailData.name }}合约的终止</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、</span>本合约的生效期从购买下单日开始计算，当收益连续50天收益低于电费，算力币{{
                  detailData.name }}合约自动作废，SwanLake将在官网www.swanlake.club公告终止算力币合约<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;2</span><span
                      style="font-family:PingFang HK">、由于法律政策、战争、地震、火灾、电力故障或者网络故障等不可抗原因导致矿场无法继续运营的，本合约提前终止，SwanLake会告知用户并不承担相应的赔偿责任。</span></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                        data-v-532aaa84="" style=""><strong style=""><span style="font-family:PingFang HK">五</span><span
                            style="font-family:Helvetica">.&nbsp;</span></strong></span></strong></span><span
                    data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">风险因素和免责声明</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>&nbsp;&nbsp;&nbsp;&nbsp;凡购买本算力币{{
                  detailData.name }}前，用户应当认真阅读本合约，了解潜在风险，进行独立的投资判断。如对本合约条款有任何疑问，请咨询SwanLake客服。以下各项风险因素因，造成的任何用户损失，
                  项目方不承担损害赔偿责任，风险因素包括但不限于<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">：</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1</span><span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">、</span>数字货币风险：任何数字货币持有人和任何数量的任何数字货币的价值都可能在任何时刻失去部分或全部价值；对于因为数字货币价值降低（甚至为零）而受到的全部损失由用户自行承担，SwanLake不承担任何责任<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;2</span><span
                      style="font-family:PingFang HK">、</span></span>市场风险：由于挖矿难度和/或其他挖矿参数/属性的变化，数字货币的市场价格波动（法定货币对数字货币汇率），数字货币价值下跌，导致的投资亏损，SwanLake不承担任何责任和赔偿义务<span
                    data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;3</span></span><span data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;">、</span><span
                    style="font-family:PingFang SC">不可抗力及意外事件风险：自然灾害、数字货币市场危机、战争、或者国家政策变化等不能预见、不能避免、不能克服的不可抗力事件，或者病毒、木马、恶意程序攻击、网络拥堵、系统不稳定、系统或设备故障、通讯故障、电力故障、数据不能传输、数据异常、市场交易停止、第三方服务问题或政府行为等意外事件的出现，可能导致本合约终止、用户投资收益降低乃至本金损失，SwanLake不承担任何责任</span><span
                    data-v-532aaa84=""
                    style="font-family: &quot;PingFang SC&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;4</span><span
                      style="font-family:PingFang HK">、用户自身原因或操作不当产生的损失，例如您的电脑或手机软硬件和通信线路、供电线路出现故障的；您操作不当或通过非经我们授权或认可的方式使用我们服务的，我们不承担任何责任。</span></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;5</span><span
                      style="font-family:PingFang HK">、</span></span><span
                    style="font-family:PingFang HK">SwanLake有权随时通过正式的页面公告、站内信、电子邮件、客服电话、手机短信或常规的信件发布，修改，和/或提供与SwanLake和SwanLake提供的服务相关的任何信息。任何非经SwanLake正规渠道获得的信息，SwanLake不承担法律责任</span><span
                    data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">。</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">责任限制</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">我们可能同时为您及您的（交易）对手方提供服务，您同意对我们可能存在的该等行为予以明确豁免任何实际或潜在的利益冲突，并不得以此来主张我们在提供服务时存在法律上的瑕疵。</span>
                </p>
              </section>
            </el-card>
            <el-card class="box-card" shadow="never" v-else>
              <section data-role="paragraph" class="_135editor">
                <h3 data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px 0px 20px; padding: 0px; border: 0px; font-size: 16px !important; vertical-align: baseline; background-color: #ffffff; text-decoration-thickness: initial; font-family: FontAwesome, &quot;Microsoft YaHei&quot;, STXihei; color: #555555; text-align: center;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline;"></strong></span><strong>{{
                    detailData.name }} Hashrate Coin Contract</strong><span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 16px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"><strong
                      data-v-532aaa84=""></strong></span>
                </h3>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin-top: 0px; margin-right: 0px; margin-bottom: 5px !important; margin-left: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; line-height: 18px !important; font-family: emoji, BlinkMacSystemFont, -apple-system, &quot;Helvetica Neue&quot;, Arial, sans-serif, &quot;PingFang SC&quot;, Helvetica, &quot;sans-serif&quot;, &quot;Segoe UI&quot;, Roboto, Oxygen, Ubuntu, Cantarell, &quot;Fira Sans&quot;, &quot;Droid Sans&quot; !important; color: #000000; background-color: #ffffff; text-decoration-thickness: initial;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; caret-color: #000000; color: #000000; font-family: &quot;PingFang HK&quot;; -webkit-text-stroke-color: #000000;">Buy
                    {{ detailData.name }} and pledge to get BTC and SWAN income immediately.</span><br
                    data-v-532aaa84="" />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">I.
                      Confirmation of {{ detailData.name }} Hashpower Coin Contract</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"></span>&nbsp;&nbsp;&nbsp;&nbsp;1.
                  Please read the contents of this contract carefully and fully understand this contract and its terms,
                  especially the bolded part. If you have any questions about the contents of this contract, please do
                  not
                  proceed to the next step. You can consult through the SwanLake official telegram group so that we can
                  explain for you. Your confirmation by visiting the page or otherwise means that you have agreed to
                  this
                  contract. <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;"></span>&nbsp;&nbsp;&nbsp;&nbsp;
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  &nbsp;&nbsp;&nbsp;&nbsp;2. If we revise this contract, we will announce it in advance through the
                  announcement on the www.swanlake.club website, and the revised agreement will take effect 30 days
                  after the
                  announcement expires.<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;</span>You
                  confirm that: a) You are over 18 years old and have full capacity for civil conduct. b) Your
                  acceptance and
                  use of the services provided by SwanLake is in compliance with applicable laws, regulations and
                  relevant
                  policies in your place of residence/country, and does not violate your obligations to any other third
                  party.<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  &nbsp;&nbsp;&nbsp;&nbsp;If you find that you cannot promise the content stipulated in paragraph a
                  and/or b
                  of this article due to changes in facts or laws and regulations, you should immediately stop using the
                  services provided by SwanLake and inform SwanLake to stop the service through the customer service
                  channel.
                  After the termination of the service, your right to use the SwanLake service will terminate
                  immediately. You
                  agree: In this case, SwanLake is not obligated to transmit any unprocessed information or unfinished
                  service
                  to you or any third party.
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">II.
                      Purchase
                      of {{ detailData.name }} Token</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1.&nbsp;</span>Purchasing
                  the {{ detailData.name }} token means a binding contract (“{{ detailData.name }} hashrate contract”)
                  between
                  SwanLake and you regarding the hashrate agreed upon in the order, which stipulates the specific
                  content of
                  the hashrate.
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;2.&nbsp;</span>After
                  the user places an online order and pays successfully, the order cannot be cancelled. SwanLake does
                  not
                  support any request for cancellation of orders and refunds, please read and confirm the terms of this
                  contract carefully before placing an order.
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">III.
                      Charge
                      of electricity and maintenance fees</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1.&nbsp;</span>SwanLake
                  charges users a maintenance fee (including electricity fee and management fee) for {{ detailData.name
                  }}
                  based on USD 0.065 per kWh, and calculates the maintenance fee per TH/S per day according to the
                  actual
                  power consumption of the mining machine tested by Hashnest. The calculation formula is as follows
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"></span>Calculation
                  formula: Power consumption per T (KW/H) X 24 hours X 0.065USD<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;2.&nbsp;</span>The
                  electricity fee is charged at the same time as the income distribution, and refers to the US dollar to
                  Bitcoin or USDT on the AICoin platform at the same time as the income distribution. After conversion,
                  it
                  will be deducted from the user's account balance.<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;</span></span><span
                    style="font-family:PingFang HK">If the maintenance fee needs to be adjusted according to the actual
                    operation situation, we will announce it 3 days in advance through the announcement on the
                    www.swanlake.club website, and the changed maintenance fee will take effect 3 days after the
                    announcement
                    expires.</span><span data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK Semibold&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: &quot;PingFang HK&quot;;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">IV.
                      Termination of Hashrate {{ detailData.name }} Contract</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1.&nbsp;</span>The
                  effective period of this contract is calculated from the date of the purchase order. When the income
                  is
                  lower than the electricity bill for 50 consecutive days, the SwanLake {{ detailData.name }} contract
                  will be
                  automatically invalidated. SwanLake will announce the termination of the SWAN contract on the official
                  website www.swanlake.club.
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;2.&nbsp;</span><span
                      style="font-family:PingFang HK">If the mining farm cannot continue to operate due to force majeure
                      reasons such as legal policy, war, earthquake, fire, power failure or network failure, this
                      contract
                      will be terminated in advance, and SwanLake will inform the user that it will not be liable for
                      the
                      corresponding compensation.</span></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                        data-v-532aaa84="" style=""><strong style=""><span style="font-family:PingFang HK">V</span><span
                            style="font-family:Helvetica">.&nbsp;</span></strong></span></strong></span><span
                    data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><strong
                      data-v-532aaa84=""
                      style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">Risk
                      Factors
                      and Disclaimer</strong></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>&nbsp;&nbsp;&nbsp;&nbsp;Before
                  purchasing this {{ detailData.name }} token, users should read this contract carefully, understand the
                  potential risks, and make independent investment judgments. If you have any questions about the terms
                  of
                  this contract, please consult SwanLake customer service. The project party will not be liable for any
                  loss
                  to the user caused by the following risk factors, including but not limited to:<span
                    data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal; font-family: Helvetica;">&nbsp;&nbsp;&nbsp;&nbsp;1.&nbsp;</span>Digital
                  currency risk: The value of any digital currency holder and any amount of any digital currency may
                  lose some
                  or all of its value at any time; all losses due to the reduction in the value of digital currency
                  (even
                  zero) are borne by the user , SwanLake does not assume any responsibility.<span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;2.&nbsp;</span></span>Market risk: due to
                  changes
                  in mining difficulty and/or other mining parameters/attributes, fluctuations in the market price of
                  digital
                  currencies (the exchange rate between fiat currencies and digital currencies), and declines in the
                  value of
                  digital currencies, resulting in investment losses, SwanLake is not responsible for and Indemnity
                  obligation.<span data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline; font-stretch: normal; line-height: normal;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;3.&nbsp;</span></span>Force majeure and
                  unexpected
                  event risks: natural disasters, digital currency market crises, wars, or national policy changes and
                  other
                  unforeseeable, unavoidable, and insurmountable force majeure events, or viruses, Trojan horses,
                  malicious
                  program attacks, network congestion, system instability, system Or the occurrence of unexpected events
                  such
                  as equipment failure, communication failure, power failure, data inability to transmit, data
                  abnormality,
                  market transaction stop, third-party service problems or government actions may lead to the
                  termination of
                  this contract, the reduction of user investment income and even the loss of principal, SwanLake We do
                  not
                  assume any responsibility.<span data-v-532aaa84=""
                    style="font-family: &quot;PingFang SC&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;4.&nbsp;</span><span
                      style="font-family:PingFang HK">Losses caused by the user&#39;s own reasons or improper operation,
                      such
                      as the failure of your computer or mobile phone software and hardware, communication lines, and
                      power
                      supply lines; if you operate improperly or use our services in ways not authorized or approved by
                      us, we
                      will not be responsible for any responsibility.</span></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"><span
                      style="font-family:Helvetica">&nbsp;&nbsp;&nbsp;&nbsp;5.&nbsp;</span></span>SwanLake reserves the
                  right
                  to release, modify, and/or provide any information related to SwanLake and the services provided by
                  SwanLake
                  at any time through official page announcements, internal letters, emails, customer service calls,
                  mobile
                  phone text messages or regular letters. SwanLake does not assume legal responsibility for any
                  information
                  not obtained through SwanLake&#39;s official channels.<span data-v-532aaa84=""
                    style="font-family: &quot;PingFang HK&quot;; margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;"></span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: Helvetica; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal; min-height: 13px;">
                  <br />
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">Limitation
                    of
                    Liability</span>
                </p>
                <p data-v-532aaa84="" data-children-count="0"
                  style="margin: 0px; padding: 0px; border: 0px; font-size: 11px; vertical-align: baseline; line-height: normal; font-family: &quot;PingFang HK&quot;; color: #000000; -webkit-text-stroke: #000000; background-color: #ffffff; text-decoration-thickness: initial; font-stretch: normal;">
                  <span data-v-532aaa84=""
                    style="margin: 0px; padding: 0px; border: 0px; font-size: 12px; vertical-align: baseline;">&nbsp;&nbsp;&nbsp;&nbsp;We
                    may provide the Services to both you and your (trading) counterparties, and you agree to expressly
                    waive
                    any actual or potential conflict of interest that we may have with respect to such conduct and not
                    to rely
                    on it to claim that we are legally defective in the provision of the Services.</span>
                </p>
              </section>
              <section class="_135editor" data-role="paragraph">
                <p style="vertical-align:inherit;">
                  <br />
                </p>
              </section>
            </el-card>
          </el-col>
        </el-row>
      </el-main>
      <!-- </el-container> -->
    </el-card>

  </div>
</template>
<script>
import axios from 'axios'
import { approve, BuyTokenToS19 } from "@/wallet/trade";
import { getBalance, isApproved, setStatiscData, getGameFillingBalance } from "@/wallet/serve";
import Address from "@/wallet/address.json";
import { keepDecimalNotRounding, getUrlParams } from "@/utils/tools";
import { mapState } from "vuex";
export default {
  name: "Index",
  data() {
    return {
      hashId: 1,
      num: '',
      agree: false,
      approve: false,
      trading: false,
      detailData: {},
      detailLoding: false,
      numPrice: 1,
      type: 0,
      hashpowerPanelShow: false,
      poolBtcData: {},
      usdtBalance: 0,
      recomme_code: '',
      is_recomme_code: false,
      walletBalance: 0,
    };
  },
  created() {
    try {
      let hashId = this.$route.params.hash_id;
      if (hashId && hashId > 0) {
        this.hashId = hashId;
        this.detailLoding = true;
        this.getBoxDetail();
      } else {
        this.$router.go(-1);
      }
    } catch (err) { }
  },
  async mounted() { },
  computed: {
    ...mapState({
      isConnected: state => state.base.isConnected,
      address: state => state.base.address,
      apiUrl: state => state.base.apiUrl,
      nftUrl: state => state.base.nftUrl,
      isMobel: state => state.comps.isMobel,
      userInfo: state => state.base.userInfo,
    }),
    changeData() {
      const { address, apiUrl, hashId, hashPowerPoolsList } = this
      return {
        address, apiUrl, hashId, hashPowerPoolsList
      };
    }
  },
  watch: {
    isConnected: {
      immediate: true,
      async handler(val) {
      },
    },
    changeData: {
      handler(val) {
        if (val.address !== '' && val.hashId > 0 && val.nftUrl !== '') {
          // console.log(val.hashPowerPoolsList);
          // let index = val.hashPowerPoolsList.findIndex(item=>item.id == val.hashId);
          // this.hashpowerAddress = this.hashPowerPoolsList[index].hashpowerAddress;
          this.detailLoding = true;
          this.getBoxDetail();
        }
      }
    },
    address: {
      immediate: true,
      async handler(val) {
        if (val) {
          this.walletBalance = await getGameFillingBalance(); //获取合约余额
          console.log('链上余额：', this.walletBalance);
        }
      }
    },
  },
  methods: {
    handleEdit(e) {
      console.log(e);
      let value = e.replace(/[^d]/g, ""); // 只能输入数字
      value = value.replace(/^0+(d)/, "$1"); // 第一位0开头，0后面为数字，则过滤掉，取后面的数字
      value = value.replace(/(d{4})d*/, '$1') // 最多保留15位整数
      this.num = value;
    },
    clickUsdtBalance() {
      let userBalance = Number(this.walletBalance) + Number(this.userInfo.local_balance); //用户余额
      let copiesNum = userBalance / this.detailData.price;
      this.num = Math.floor(copiesNum);
    },
    inputNumberChange(currentValue) {
      console.log(currentValue);
      if (currentValue > 0) {
        this.numPrice = currentValue * this.detailData.price;
      }
    },
    recommeCodeCheck(e) { // 只能输入汉字、英文、数字
      e.target.value = e.target.value.replace(/[^\a-\z\A-\Z0-9]/g, "");
    },
    extraordinaryCheck(e) { // 限制输入特殊字符 及 字符长度
      e.target.value = e.target.value.replace(/[`~!@#$%^&*()_\-+=<>?:"{}|,.\/;'\\[\]·~！@#￥%……&*（）——\-+={}|《》？：“”【】、；‘’，。、]/g, "");
      let temp = 0;
      let len = 8;
      for (var i = 0; i < e.target.value.length; i++) {
        if (/[\u4e00-\u9fa5]/.test(e.target.value[i])) {
          temp += 2
        } else {
          temp++
        }
        if (temp > len) {
          e.target.value = str.value.substr(0, i)
        }
      }
      // e.target.value = value;
    },
    getBoxDetail() {
      axios.get(this.apiUrl + "/Power/power/getPowerDetail", {
        params: {
          hashId: this.hashId,
        }
      }).then((json) => {
        this.detailLoding = false;
        // console.log(json);
        // console.log(this.address);
        if (json.code == 10000) {
          this.detailData = json.data;
        }
        else {
          // this.$message.error("error");
          console.log("get Data error");
        }
      }).catch((error) => {
        this.$message.error(error);
      });
    },
    async startPurchase() { //开始认购
      this.trading = true;
      if (this.num <= 0) {
        this.$message.error("请输入购买数量");
        this.trading = false;
        return false;
      }
      if (this.detailData.stock < 0) {
        this.$message.error("Inventory shortage");
        this.trading = false;
        return false;
      }
      let priceNum = this.detailData.price * this.num; //购买金额
      let userBalance = Number(this.walletBalance) + Number(this.userInfo.local_balance); //用户余额
      if (priceNum > userBalance) {
        this.$message.error("余额不足");
        this.trading = false;
        return false;
      }
      if (this.is_recomme_code) {
        if (this.recomme_code == "") {
          this.$message.error("推荐码不能为空");
          this.trading = false;
          return false;
        } else {
          if (this.recomme_code.length < 4) {
            this.$message.error("推荐码长度不能低于4位，最长不超过8位");
            this.trading = false;
            return false;
          }
        }
      }
      // let hash = "111";
      await this.setPurchaseLog();
      // console.log(this.hashpowerAddress);
    },
    async setPurchaseLog() { //开始记录认购记录
      await axios.post(this.apiUrl + "/Power/power/buyHashPower", {
        hashId: this.hashId,
        amount: this.num,
        address: this.address,
        recomme_code: this.recomme_code,
      }).then((json) => {
        if (json.code == 10000) {
          this.getBoxDetail();
          this.agree = false;
          this.$message({
            type: 'success',
            message: '购买成功!'
          });
          this.trading = false;
          setTimeout(() => {
            this.$router.push({ path: '/power/user' })
          }, 2000)
        } else {
          this.$message.error("Error");
        }
      }).catch((error) => {
        this.$message.error(error);
      });
    },
    async getBUSDTIsApprove() { //获取余额 查看是否授权
      let balance = await getBalance(Address.BUSDT, 18); //获取余额
      this.usdtBalance = balance;
      // console.log("balance", balance);
      this.tokenBalance = balance;
      isApproved(Address.BUSDT, 18, balance, this.hashpowerAddress).then((bool) => {
        console.log("isApprove", bool);
        this.approve = bool ? true : false;
      });
    },
    startApprove() { //批准USDT
      this.trading = true;
      approve(Address.BUSDT, this.hashpowerAddress).then((hash) => {
        // console.log(result);
        if (hash) {
          this.approve = true;
          this.trading = false;
        }
      }).finally(() => {
        this.trading = false;
      });
    },
    receiveBenefits() { //领取收益 跳转到质押页面
      this.$router.push('/hashpower/list');
    },
    goBack() {
      this.$router.go(-1);
    }
  },
};
</script>
<style lang="scss" scoped>
.container {
  background-color: transparent !important;

  .el-breadcrumb {
    height: 25px;
    font-size: 16px;
  }

  .box-card {
    .el-card__body {
      padding-bottom: 80px;
    }

    border-radius: 30px;

    .mian-detail {

      // width: 50% !important;
      // margin: 0 auto !important;
      .el-card {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 30px;
      }

      .box-card-left {
        ::v-deep {
          height: 500px;
          // background-color: transparent;
          border: none;

          .el-card__body {
            padding: 0 !important;
            text-align: center;
          }
        }
      }

      .box-card-right {
        ::v-deep {
          @include sideBarBgc($color-bgc-sideBar-dark);
          @include mainFont($color-mainFont-light);

          .el-card {
            max-height: 426px;
          }

          .el-card__header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          }

          .el-card__body {
            .item {
              .el-row {
                line-height: 20px;
                padding: 15px;
              }
            }
          }
        }
      }

      .box-card-right-bottom {
        @include sideBarBgc($color-bgc-sideBar-dark);
        @include mainFont($color-mainFont-light);

        ::v-deep {
          margin-top: 20px;

          .el-row {
            line-height: 38px;
          }

          .el-card__body {
            .el-input__inner {
              @include sideBarBgc($color-bgc-sideBar-dark);
              @include mainFont($color-mainFont-light);
              border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .el-input-number__decrease,
            .el-input-number__increase {
              @include sideBarBgc($color-bgc-sideBar-dark);
              @include mainFont($color-mainFont-light);
              border: 1px solid rgba(255, 255, 255, 0.1);
            }
          }
        }
      }

      .el-card-num {
        margin-top: 20px;

        .el-row {
          // width: 50%;
          text-align: center;
          display: flex;
          align-items: center;

          .el-input__inner {
            height: 60px;
          }
        }
      }

      .recomme {
        .el-input {
          width: 150px;

          .el-input__inner {
            height: 50px;
          }
        }
      }
    }

    .agree-item {
      text-align: center;
      padding: 20px;

      .el-checkbox {
        .agree-item-checkbox {}
      }

      .agree-item-buttom {
        >button {
          background-color: #878585;
          color: #fff;
          width: 100%;
          max-width: 300px;
          border-radius: 30px;
          border: 1px solid rgba(255, 255, 255, 0.1);
        }

        >button.is-disabled:hover {
          background-color: #878585;
          color: #fff;
          width: 100%;
          max-width: 300px;
          border-radius: 30px;
          border: 1px solid rgba(255, 255, 255, 0.1);
        }

        >button:hover {
          background-color: #0096FF;
        }

        padding: 20px;
      }

      .receive-agree-item-buttom {
        >button {
          background-color: #0096FF;
          // @include sideBarBgc($color-bgc-sideBar-dark);
          color: #fff;
          width: 100%;
          max-width: 300px;
          border-radius: 30px;
          border: 1px solid rgba(255, 255, 255, 0.1);
        }
      }
    }

    .content {
      font-size: 16px;
      line-height: 16px;
      box-sizing: border-box;

      //   margin: 20px auto;
      .el-card__body {
        padding: 30px;
      }
    }
  }

}
</style>