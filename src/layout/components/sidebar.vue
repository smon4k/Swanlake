<template>
  <div class="sidebar-container">
    <div class="sider-inner">
      <div class="logo">
        <router-link to="/hashpower/pools" class="logo-link">
          <img
            src="@/assets/h2oToken.png"
            alt=""
            v-if="mainTheme === 'light'"
          />
          <img src="@/assets/h2oToken_dark.png" alt="" v-else />
          <br />
          <span> H2O Finance </span>
        </router-link>
      </div>
      <div class="menu">
        <ul class="menulist">
          <li :class="['item']">
            <div @click="toggleSubmenu">
              <i class="menu-icon hashpower"></i>
              <span>{{ $t('nav:Hashpower') }}</span>
              <img
                v-if="mainTheme === 'light'"
                :class="['menu_down', { toggle: showSubMenu }]"
                src="@/assets/menu_down.png"
                alt=""
              />
              <img
                v-else
                :class="['menu_down', { toggle: showSubMenu }]"
                src="@/assets/menu_down_light.png"
                alt=""
                style="width:12px;height:10px;"
              />
            </div>
          </li>
          <li class="subMenu">
            <transition name="slide-fade">
              <ul v-show="showSubMenu">

                <!-- 算力币 -->
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/hashpower/buy') !== -1 },
                  ]"
                >
                  <router-link to="/hashpower/buy" tag="div" class="menuLink">
                    {{ $t('nav:Subscribe') }}
                  </router-link>
                </li>
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/hashpower/abstract') !== -1 },
                  ]"
                >
                  <router-link to="/hashpower/abstract" tag="div" class="menuLink">
                    {{$t('nav:Whitepaper')}}
                  </router-link>
                </li>
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/hashpower/history') !== -1 },
                  ]"
                >
                  <router-link to="/hashpower/history" tag="div" class="menuLink">
                    {{ $t('nav:OrderHistory') }}
                  </router-link>
                </li>
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/hashpower/pools') !== -1 },
                  ]"
                >
                  <router-link to="/hashpower/pools" tag="div" class="menuLink">
                    {{ $t('nav:HashpowerIncome') }}
                  </router-link>
                </li>
              </ul>
            </transition>
          </li>

          <li
            :class="['item']"
          >
            <router-link to="/" @click.native="toShortVideoBlank" tag="div" class="menuLink">
              <i class="menu-icon shortVideo"></i>
              <span>{{ $t('nav:shortVideo') }} </span>
            </router-link>
          </li>

          <li
            :class="['item', { active: currentPath.indexOf('/Market') !== -1 }]"
          >
            <router-link to="/" @click.native="toMarketBlank" tag="div" class="menuLink">
              <i class="menu-icon market"></i>
              <span>{{ $t('nav:NFT') }} </span>
            </router-link>
          </li>
          <!-- <li
            :class="['item', { active: currentPath.indexOf('/mysterBox') !== -1 }]"
          >
            <router-link to="/mysteryBox" tag="div" class="menuLink">
              <i class="menu-icon mysterbox"></i>
              <span>{{ $t('nav:MysteryBox') }} </span>
            </router-link>
          </li> -->
          <li
            :class="['item', { active: currentPath === '/pools' }]"
          >
            <router-link to="/pools" tag="div" class="menuLink">
              <i class="menu-icon mining"></i>
              <!-- <span >质押挖矿</span> -->
              <span>{{ $t('nav:Pools') }}</span>
            </router-link>
          </li>

          <!-- <li
            :class="['item', { active: currentPath.indexOf('/hashpower/buy') !== -1 }]"
          >
            <router-link to="/hashpower/buy" tag="div" class="menuLink">
              <i class="menu-icon hashpower"></i>
              <span>Hashpower</span>
            </router-link>
          </li> -->
          <li
            :class="[
              'item',
              { active: currentPath.indexOf('/deposit') !== -1 },
            ]"
          >
            <router-link to="/deposit" tag="div" class="menuLink">
              <i class="menu-icon deposit"></i>
              <!-- <span >单币存款</span> -->
              <span>{{$t('nav:Vault')}}</span>
            </router-link>
          </li>
          <li
            :class="[
              'item',
              { active: currentPath.indexOf('/pledgeMint') !== -1 },
            ]"
          >
            <router-link to="/pledgeMint" tag="div" class="menuLink">
              <i class="menu-icon pledge"></i>
              <!-- <span >质押挖矿</span> -->
              <span>{{$t('nav:Stake')}}</span>
            </router-link>
          </li>

          <li
            :class="[
              'item',
              { active: currentPath.indexOf('/totalMinting') !== -1 },
            ]"
          >
            <router-link to="/totalMinting" tag="div" class="menuLink">
               <i class="menu-icon lever"></i>
              <span>{{$t('nav:LeverageFarms')}}</span>
            </router-link>
          </li>
          <li
            :class="[
              'item',
              { active: currentPath.indexOf('/farms') !== -1 },
            ]"
          >
            <router-link to="/farms" tag="div" class="menuLink">
               <i class="menu-icon lever"></i>
              <span>{{$t('nav:Farms')}}</span>
            </router-link>
          </li>
          <li
            :class="[
              'item',
              { active: currentPath === '/position'},
            ]"
          >
            <router-link to="/position" tag="div" class="menuLink">
              <!-- <span class="subPoint"></span> -->
              <i class="menu-icon positions"></i>
              <!-- 我的持仓 -->
              {{$t('nav:Portfolio')}}
            </router-link>
          </li>
          <!-- <li
            :class="[
              'item',
              { active: currentPath === '/position/my' },
            ]"
          >
            <router-link to="/position/my" tag="div" class="menuLink">
              <i class="menu-icon liquidation"></i>
              {{$t('nav:MyPortfolio')}}
            </router-link>
          </li> -->
          <!-- <li
            :class="[
              'item',
              { active: currentPath.indexOf('/Liquidation') !== -1 },
            ]"
          >
            <router-link to="/Liquidation/all" tag="div" class="menuLink">
              <i class="menu-icon liquidation"></i>
              Liquidation
            </router-link>
          </li> -->

          <!-- <li :class="['item']">
            <div @click="toggleSubmenu">
              <i class="menu-icon lever"></i>
              <span>Farm</span>
              <img
                :class="['menu_down', { toggle: showSubMenu }]"
                src="@/assets/menu_down.png"
                alt=""
              />
            </div>
          </li> -->
          <!-- <li class="subMenu">
            <transition name="slide-fade">
              <ul v-show="showSubMenu">
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/totalMinting') !== -1 },
                  ]"
                >
                  <router-link to="/totalMinting" tag="div" class="menuLink">
                    <span class="subPoint"></span>
                    All Farms
                  </router-link>
                </li>
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/position') !== -1 },
                  ]"
                >
                  <router-link to="/position" tag="div" class="menuLink">
                    <span class="subPoint"></span>
                    My Positions
                  </router-link>
                </li>
                <li
                  :class="[
                    'item',
                    { active: currentPath.indexOf('/Liquidation') !== -1 },
                  ]"
                >
                  <router-link to="/Liquidation/all" tag="div" class="menuLink">
                    <span class="subPoint"></span>
                    Liquidation
                  </router-link>
                </li>
              </ul>
            </transition>
          </li> -->
          <li :class="['item', { active: currentPath === '/Referral' }]">
            <router-link to="/Referral" tag="div" class="menuLink">
              <i class="menu-icon flag"></i>
              <!-- <span >收获</span> -->
              <span>{{$t('nav:Referral')}}</span>
            </router-link>
          </li>
          <li
            :class="['item', { active: currentPath.indexOf('/Claim') !== -1 }]"
          >
            <router-link to="/Claim" tag="div" class="menuLink">
              <i class="menu-icon claim"></i>
              <!-- <span >收获</span> -->
              <span>{{$t('nav:Claim')}}</span>
            </router-link>
          </li>
          <li
            :class="['item', { active: currentPath.indexOf('/Swap') !== -1 }]"
          >
            <router-link to="/Swap" tag="div" class="menuLink">
              <i class="menu-icon swap"></i>
              <!-- <span>{{$t('nav:Swap')}}</span> -->
              <span>{{$t('nav:buy')}} H2O</span>
            </router-link>
          </li>
          <!-- <li
            :class="['item', { active: currentPath.indexOf('/Liquidity') !== -1 }]"
          >
            <router-link to="/Liquidity" tag="div" class="menuLink">
              <i class="menu-icon liquidity"></i>
              <span>{{$t('nav:Liquidity')}}</span>
            </router-link>
          </li> -->
          <li
            :class="['item', { active: currentPath.indexOf('/airdrops') !== -1 }]"
          >
            <router-link to="/airdrops" tag="div" class="menuLink">
              <i class="menu-icon airdrop"></i>
              <span> {{ $t('nav:AirDrop') }} </span>
            </router-link>
          </li>
        </ul>
      </div>
      <div class="bottomArea">
        <div class="exchange">
          <div class="item balance">
            <img
              src="@/assets/h2oToken.png"
              alt=""
              v-if="mainTheme === 'light'"
            />
            <img src="@/assets/h2oToken_dark.png" alt="" v-else />
            <!-- <span>$ 100.68</span> -->
            <span>$ {{ H2OPrice }}</span>
          </div>
          <!-- <div class="item active">
            <a href="" target="_blank">
              <img src="@/assets/h2oToken.png" alt="">
              <span> Buy H2O</span>
            </a>
          </div> -->
          <!-- <div class="item">
                    <img src="@/assets/m_token.png" alt="">
                    <span> Buy H2O</span>
                </div>
                <div class="item">
                    <img src="@/assets/pancake.png" alt="">
                    <span> Buy H2O</span>
                </div> -->
          <!-- <div class="item">
                    <img src="@/assets/mm_token.png" alt="">
                    <span>CoinMarketCap</span>
                </div>
                <div class="item">
                    <img src="@/assets/gecko.png" alt="">
                    <span>CoinGecko</span>
                </div> -->
        </div>
        <div class="Social">
          <!-- <div class="item">
            <router-link to="/Swap" tag="div" class="buy-class">
                <img src="@/assets/cake_buy.png" alt="">
              <span>{{$t('nav:buy')}} H2O</span>
            </router-link>
          </div> -->
          <div class="item">
            <a href="https://twitter.com/FinanceH2O" target="_blank">
              <!-- <img src="@/assets/twitter.png" alt=""> -->
              <span class="img twitter"></span>
              <span>{{$t('nav:Twitter')}}</span>
            </a>
          </div>
          <div class="item">
            <a href="https://t.me/h2ofinanceofficial" target="_blank">
              <!-- <img src="@/assets/telegram.png" alt=""> -->
              <span class="img telegram"></span>
              <span>{{$t('nav:Telegram')}}</span>
            </a>
          </div>
          <!-- <div class="item">
                    <img src="@/assets/disc.png" alt="">
                </div> -->
          <!-- <div class="item">
                    <img src="@/assets/Social1.png" alt="">
                </div> -->
          <div class="item">
            <a
              href="https://h2ofinance.gitbook.io/h2o-finance-en/"
              target="_blank"
            >
              <span class="img Social2"></span>
              <span>{{$t('nav:Whitepaper')}}</span>
            </a>
          </div>
          <!-- <div class="item">
                    <img src="@/assets/Social3.png" alt="">
                </div> -->
        </div>
      </div>
      <div class="fixAbs"></div>
    </div>
  </div>
</template>
<script>
import { mapState } from "vuex";
// import { getToken2TokenPrice, getSwapPoolsAmountsOut, getTokenAmountsoutPrice } from "@/wallet/Inquire";
import { keepDecimalNotRounding } from "@/utils/tools";
// import tokenList from "@/wallet/tokens.js";
// import Address from "@/wallet/address.json";
export default {
  name: "LayoutSidebar",
  data() {
    return {
      showSubMenu: false,
      H2OPrice: 0,
      // currentPath:''
    };
  },
  created() {
    this.getH2OPrice();
  },
  mounted() {
    console.log("currentPath", this.currentPath);
    setInterval(() => {
      // console.log(111);
      // this.getH2OPrice();
    }, 10000);
  },
  computed: {
    currentPath() {
      return this.$route.path;
    },
    ...mapState({
      mainTheme: (state) => state.comps.mainTheme,
    }),
  },
  methods: {
    async getH2OPrice() {
      this.H2OPrice = 1;
    },
    handleOpen(key, keyPath) {
      console.log(key, keyPath);
    },
    handleClose() {},
    toggleSubmenu() {
      this.showSubMenu = !this.showSubMenu;
    },
    toMarketBlank() {
      window.open(window.location.origin + "/nft");
    },
    toShortVideoBlank() {
      window.open('https://apiy.h2ofinance.pro');
    }
  },
};
</script>
<style lang="scss" scoped>
.menu {
  padding: 0 45px;
  margin-top: 36px;
  max-height: 500px;
  overflow-y: auto;
  overflow-x: overlay;
  .menuLink {
    display: block;
    color: inherit;
    .item.active .subPoint {
      background-color: #fff;
    }
  }
  .menulist {
    padding: 0;
    margin: 0;
    .menu_down {
      margin-left: 20px;
      height: 6px;
      transform: rotateZ(180deg);
      transition: all 0.3s linear;
    }
    .menu_down.toggle {
      transform: rotateZ(0deg);
    }
    li {
      list-style: none;
      padding-left: 44px;
      cursor: pointer;
      height: 38px;
      margin-bottom: 8px;
      color: #333333;
      font-size: 14px;
      position: relative;
      line-height: 38px;
      border-radius: 19px;

      .menu-icon {
        position: absolute;
        height: 14px;
        width: 20px;
        left: 21px;
        top: 12px;
      }
      // .deposit {
      //   background: url("~@/assets/deposit.png") no-repeat;
      //   background-size: contain;
      // }
    }
    .item {
      @include mainFont($color-mainFont-light);
    }
    .item.active {
      background: linear-gradient(90deg, #0096ff, #0024ff);
      color: #fff;
      .subPoint {
        background-color: #fff;
      }
      // .deposit {
      //   background: url("~@/assets/deposit_light.png") no-repeat;
      //   background-size: contain;
      // }
    }
    .item:not(.active):hover {
      color: #0096ff;
      .subPoint {
        background-color: #0096ff;
      }
    }
    .subMenu {
      padding: 0;
      height: auto;
      padding-left: 16px;
      .item {
        font-size: 12px;
        @include mainFontSubNav($color-mainFontSubNav-light);
      }
      .item.active {
        color: #fff;
      }
      ul {
        padding: 0;
      }
      .subPoint {
        position: absolute;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background-color: #333333;
        left: 27px;
        top: 16.5px;
      }
    }
  }
}
.menu::-webkit-scrollbar{
  display: none;
}
.logo {
  height: 53px;
  box-sizing: border-box;
  .logo-link {
    display: block;
    height: 100%;
    text-align: center;

    img {
      height: 53px;
      margin-left: -24px;
    }
    span {
      display: inline-block;
      font-size: 16px;
      @include mainFont($color-mainFont-light);
      // color: #1C1C1B;
      padding-top: 4px;
      margin-left: -14px;
    }
  }
}
.bottomArea {
  // position: absolute;
  bottom: 0;
  width: 100%;
  @include topNavBgc($color-bgc-topnav-dark);
  .Social {
    // display: flex;
    padding: 0 50px;
    padding-left: 65px;
    // justify-content: space-around;
    margin-top: 16px;
    a {
      img {
        margin-right: 12px;
        vertical-align: middle;
      }
      .img {
        display: inline-block;
        vertical-align: middle;
        height: 20px;
        width: 20px;
        margin-right: 12px;
      }
      .img.twitter {
        background: url("~@/assets/twitter.png") no-repeat;
        background-size: contain;
      }
      .img.telegram {
        background: url("~@/assets/telegram.png") no-repeat;
        background-size: contain;
      }
      .img.Social2 {
        background: url("~@/assets/wendang.png") no-repeat;
        background-size: contain;
      }
      span {
        @include mainFont($color-mainFont-light);
        font-size: 14px;
      }
    }
    a:hover {
      span {
        color: #0096FF;
      }
      .img.twitter {
        background: url("~@/assets/twitter_hover.png") no-repeat;
        background-size: contain;
      }
      .img.telegram {
        background: url("~@/assets/telegram_hover.png") no-repeat;
        background-size: contain;
      }
      .img.Social2 {
        background: url("~@/assets/wendang_hover.png") no-repeat;
        background-size: contain;
      }
    }
    .item {
      cursor: pointer;
      margin-bottom: 20px;
      img {
        margin-right: 12px;
        vertical-align: middle;
        display: inline-block;
        vertical-align: middle;
        height: 20px;
        width: 20px;
      }
      .buy-class {
        @include mainFont($color-mainFont-light);
        font-size: 14px;
      }
    }
    .buy-h2o{
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background-color: #fff;
    }
  }
  .exchange {
    padding: 0 45px;
    .item {
      height: 38px;
      line-height: 38px;
      margin: 4px 0;
      border-radius: 19px;
      font-size: 14px;
      @include otherFont($color-otherFont-light);

      // color: #333333;
      cursor: pointer;
      img {
        height: 23px;
        width: 23px;
        margin: 0 9px 0 18px;
        vertical-align: middle;
      }
      span {
        display: inline-block;
        vertical-align: middle;
      }
    }
    .item.active {
      @include exchangeBgc($color-exchange-activeBg-light);
      // background: rgba(245, 246, 250,0.96);
      box-shadow: 0px 0px 8px 0px rgba(0, 0, 0, 0.14);
      @include activeFont($color-activeFont-light);
    }
    .item.balance {
      @include mainFont($color-mainFont-light);
    }
  }
}

.slide-fade-enter-active {
  transition: all 0.2s ease;
}
.slide-fade-leave-active {
  transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter,
.slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}
</style>