<template>
    <div class="nav-container">
        <div class="container-fluid">
            <div class="headerNav-container">
                <div class="sider-inner">
                    <div class="menu" @click="menuDrawerShow = true" v-if="screenWidth < 1280">
                        <img src="@/assets/menu_line.png" alt="" />
                    </div>
                    <div class="logo" v-show="screenWidth > 600">
                        <router-link to="/" class="logo-link">
                            <img
                                src="@/assets/logo2.png"
                                alt=""
                                v-if="mainTheme === 'light'"
                            />
                            <!-- <img
                                src="@/assets/h2o.png"
                                alt=""
                                v-if="mainTheme === 'light'"
                            /> -->
                            <img src="@/assets/logo2.png" alt="" v-else />
                        <!-- <br /> -->
                        </router-link>
                    </div>
                    <div class="title" v-show="screenWidth > 600"> BLOCK MINING </div>
                </div>
                <!-- <div class="title" v-show="screenWidth > 600"> Swan Lake Quant<br>H2O Finance </div> -->

                <div v-show="screenWidth >= 1280">
                    <el-menu 
                        class="el-menu-demo" 
                        v-if="navList.length" 
                        :default-active="$route.path" 
                        mode="horizontal" 
                        @select="handleSelect" 
                        :router="true" 
                        menu-trigger="click" 
                        @open="menuSelectOpen" 
                        :collapse-transition="false"
                        :default-openeds="defaultOpenedsArray"
                        :unique-opened="true"
                    >
                        <template v-for="(item, index) in navList">
                            <el-menu-item :index="item.path" v-if="!item.children.length" :key="index">
                                <a :href="item.link" target="_blank" v-if="item.link">{{ item.name }}</a>
                                <font v-else-if="item.isText" color="#0096FF">{{ item.name }}</font>
                                <span v-else>{{ item.name }}</span>
                            </el-menu-item>
                            <el-submenu
                                v-else
                                :index="item.path == '#' ? item.path + item.id : item.path"
                                :key="'submenu-' + (item.id || index)"
                            >
                                <template slot="title">{{ item.name }}</template>
                                <el-menu-item
                                    v-for="(childe, keye) in item.children"
                                    :key="'submenu-' + (item.id || index) + '-' + (childe.id || keye)"
                                    :index="childe.path"
                                >
                                    {{ childe.name }}
                                </el-menu-item>
                            </el-submenu>
                        </template>
                    </el-menu>
                </div>
                <div class="nav-right">
                    <div class="connectWallet pending" v-if="isConnected && pendingOrderAmount" >
                        <span>Trading</span>
                        <img src="@/assets/shuaxin.png" alt="">
                    </div>
                    <div class="connectWallet" @click="connectWallet" v-else>
                        {{isConnected ? addressStr : 'bsc ' + $t('public:ConnectWallet') }}
                    </div>
                </div>
            </div>

            <el-drawer
                title="我是标题"
                :visible.sync="menuDrawerShow"
                :with-header="false"
                direction="ltr"
                size="320px">
                <div class="sider-inner">
                    <div class="logo">
                        <router-link to="/" class="logo-link">
                            <img
                                src="@/assets/logo2.png"
                                alt=""
                                v-if="mainTheme === 'light'"
                            />
                            <!-- <img
                                src="@/assets/h2o.png"
                                alt=""
                                v-if="mainTheme === 'light'"
                            /> -->
                            <img src="@/assets/logo2.png" alt="" v-else />
                        <!-- <br /> -->
                        </router-link>
                    </div>
                    <div class="title"> BLOCK MINING </div>
                </div>
                <br><br>
                <el-menu
                    v-if="navList.length" 
                    :default-active="$route.path"
                    class="el-menu-vertical-demo"
                    :router="true"
                    @select="handleSelect"
                    @open="menuSelectOpen" 
                    :collapse-transition="false"
                    :default-openeds="defaultOpenedsArray"
                    >
                    <template v-for="(item, index) in navList">
                        <el-menu-item :index="item.path" v-if="!item.children.length" :key="index">
                            <a :href="item.link" target="_blank" v-if="item.link">{{ item.name }}</a>
                            <font v-else-if="item.isText" color="#0096FF">{{ item.name }}</font>
                            <span v-else>{{ item.name }}</span>
                        </el-menu-item>
                        <el-submenu 
                            v-else 
                            :index="item.path == '#' ? item.path + item.id : item.path" 
                            :key="item.path == '#' ? item.path + item.id : item.path">
                            <template slot="title">{{item.name}}</template>
                            <div v-for="(childe, keye) in item.children" :key="childe.path">
                                <el-menu-item :index="childe.path">{{childe.name}}</el-menu-item>
                            </div>
                        </el-submenu>
                    </template>
                </el-menu>

            </el-drawer>
        </div>
        <div class="footer">
            <div class="container">
                <!-- <div class="risk-warning">
                    风险提示：加密货币投资存在较高的市场风险。价格波动剧烈，可能导致投资本金损失。请务必充分了解相关风险，并考虑自身的经验水平和投资目标。
                </div> -->
                <font style="vertical-align: inherit;">
                    <font style="vertical-align: inherit;">版权所有 © 1999 - 2022 BLOCK EARNING Company, LLC。</font>
                    <!-- <font style="vertical-align: inherit;">版权所有。</font> -->
                    <a class="privacy-link" href="https://sg.godaddy.com/legal/agreements/privacy-policy?target=_blank" target="_blank" data-eid="uxp.hyd.int.pc.app_header.footer.privacy_policy.link.click">
                        <!-- <font style="vertical-align: inherit;">隐私政策</font> -->
                    </a>
                </font>
            </div>
        </div>
    </div>
</template>
<script>
import axios from 'axios'
import { connectInfo , connect } from '@/wallet/connect/metaMask'
import { mapGetters, mapState } from "vuex";
import { getToken2TokenPrice } from "@/wallet/serve";
import Address from "@/wallet/address.json";
export default {
    name:'headerNav',
    data(){
        return {
            activeLang:'en',
            activeTheme:'dark',
            activeMintNav:'All',
            activeLiquidation:'',
            language: 'zh',
            nftRewardList: [],
            window: window,
            screenWidth: document.body.clientWidth,
            timer: null,
            activeIndex: '1',
            menuDrawerShow: false,
            defaultOpenedsArray:[],
            isHashpowerMenu: false,
            h2oPrice: 0,
        }
    },
    mounted() {
        window.onresize = () => {
            this.screenWidth = document.body.clientWidth;
        }
    },
    computed:{
        ...mapState({
            address:state=>state.base.address,
            isConnected:state=>state.base.isConnected,
            mainTheme:state=>state.comps.mainTheme,
            apiUrl:state=>state.base.apiUrl,
            isAdmin:state=>state.base.isAdmin,
            isMobel:state=>state.comps.isMobel,
        }),
        ...mapGetters(['pendingOrderAmount']),
        addressStr(){
            // console.log(this.address);
            if(!this.address || this.address == undefined || this.address == '') {
                return "Connect Wallet";
            } else {
                return this.address.substring(0, 4) + "***" + this.address.substring(this.address.length - 3)
            }
        },
        isTotalMintPath(){
            return this.$route.path === '/totalMinting'
        },
        isLiquidation(){
            // return this.$route.path.indexOf('Liquidation') !== -1
            return this.$route.path.indexOf('position') !== -1
        },
        currentPath(){
            return this.$route.path
        },
        navList () { //导航菜单
            let arr = [
                // {
                //     name: '首页',
                //     path: "/home",
                //     // img: require("@/assets/images/hashpower.png"),
                //     // img2: require("@/assets/images/hashpower_light.png"),
                //     children: [],
                //     isHref: false,
                // },
                // {
                //     name: '我的理财',
                //     path: "/my/finances",
                //     children: [],
                // },
                // {
                //     name: '理财产品',
                //     path: "/financial/product",
                //     children: [],
                // },
                // {
                //     name: '资金监控',
                //     path: "/fund/monitoring",
                //     children: [],
                // },
                // {
                //     name: '充提',
                //     path: "/depositWithdrawal",
                //     children: [],
                // },
                // {
                //     name: '币种统计',
                //     path: "/bscTokenStatistics",
                //     children: [],
                // },
                {
                    name: '仪表板',
                    path: "/hashpower/my",
                    children: [],
                },
                {
                    name: '订单',
                    path: "/hashpower/history",
                    children: [],
                },
                {
                    name: '算力总览',
                    path: "/hashpower/overview",
                    children: [],
                },
                {
                    name: '开始挖矿',
                    path: "/hashpower/list",
                    children: [
                        // {
                        //     name: '算力币列表',
                        //     path: "/hashpower/list",
                        // },
                        // {
                        //     name: '白皮书',
                        //     path: "/hashpower/abstract",
                        // },
                    ]
                },
                {
                    name: '推特',
                    path: "",
                    link: "https://twitter.com/FinanceH2O",
                    children: [],
                },
                {
                    name: '电报',
                    path: "",
                    link: "https://t.me/h2ofinanceofficial",
                    children: [],
                },
                {
                    name: '白皮书',
                    path: "",
                    link: "https://h2ofinance.gitbook.io/h2o-finance-en/",
                    children: [],
                },
                {
                    name: '购买H2O',
                    path: "",
                    // link: "https://h2o.h2ohash.live/#/Swap?inputCurrency=0x55d398326f99059fF775485246999027B3197955&outputCurrency=H2O",
                    link: "https://pancakeswap.finance/swap?outputCurrency=BNB&inputCurrency=0x80ce4734F7C46De0b7f97527Cbc7EC189f668984",
                    children: [],
                },
                {
                    name: 'Price: $ ' + this.toFixed(this.h2oPrice, 5),
                    path: "",
                    link: "",
                    isText: true,
                    children: [],
                },
                // {
                //     name: '算力租赁',
                //     path: "/power/list",
                //     children: [
                //         {
                //             name: '我的租赁',
                //             path: "/power/user",
                //         },
                //         {
                //             name: 'BTCB 充提',
                //             path: "/deposit/withdraw/btcb",
                //         },
                //     ]
                // },
                // {
                //     name: '资金账户监控',
                //     path: "/fund/monitoring/account",
                //     children: [],
                // },
                // {
                //     name: '净值配置',
                //     path: "/day/networth",
                //     children: [],
                // },
                // {
                //     name: '量化账户监控',
                //     path: "/quantify/account",
                //     children: [],
                // },
            ];
            // if(!this.isAdmin) {
            //     arr.splice(arr.length - 3, 3);
            // }
            return arr;
        }
    },
    created(){
        document.documentElement.setAttribute( "data-theme", 'dark' )
        let theme = "light";
        // theme  = localStorage.getItem('theme')
        if(theme === 'light' || theme === 'dark'){
            this.activeTheme = theme
        } else {
            theme = "dark";
            this.activeTheme = "dark"
        }
        localStorage.setItem('theme', this.activeTheme)
        document.documentElement.setAttribute( "data-theme", theme )

        let language = "zh";
        localStorage.setItem('i18nextLng', language);
        setTimeout(async () => {
            this.h2oPrice = await getToken2TokenPrice(Address.H2O, Address.BUSDT);
        }, 400);
        // language = localStorage.getItem('i18nextLng');
        // if(language && language !== undefined) {
        //     this.language = language;
        // }
        // if(this.currentPath.indexOf('Liquidation')){
        //     let subNav = this.currentPath.replace('/Liquidation/' , '')
        //     console.log('subNav' , subNav);
        //     if(subNav === 'all' || subNav === 'my'){
        //         this.activeLiquidation = subNav
        //     }
        // }
    },
    watch:{
        currentPath:{
            immediate:true,
            handler(val, old){
                // console.log(val, old);
                // this.changeMintNav('All');
            }
        },
        screenWidth(newValue) {
            // 为了避免频繁触发resize函数导致页面卡顿，使用定时器
            if (!this.timer) {
                console.log(newValue);
                // 一旦监听到的screenWidth值改变，就将其重新赋给data里的screenWidth
                this.screenWidth = newValue;
                this.$store.commit('setScreenWidth', newValue)
                this.timer = true;
                setTimeout(() => {
                    //console.log(this.screenWidth);
                    this.timer = false;
                }, 400);
            }
        },
        $route: (val) => {
            console.log(val);
            // if(this.$route.path === '/hashpower/list') {
            //     this.isHashpowerMenu = true;
            // }
        }
    },
    methods:{
        handleSelect(index, path) { //菜单激活时事件
            // console.log(index, path);
            this.menuDrawerShow = false;
        },
        connectWallet(){
            // if (window.ethereum.isMetaMask) {
            //     connect();
            // } else {
            //     alert("您未安装MetaMask")
            // }
            // console.log(this.isConnected);
            // if(this.isConnected) return 
            if(this.address && this.address !== undefined && this.address !== '') {
                this.$disconnect();
            } else {
                this.$connect();
            }
        },
        changeLang(lang){
            this.activeLang = lang
        },
        toAirdrop(){
            this.$router.push({path:'/airdrop' })
        },
        changeTheme(){
            this.activeTheme = this.activeTheme === 'light' ? 'dark' : 'light'
            this.$store.commit('setMainTheme', this.activeTheme)
            localStorage.setItem('theme', this.activeTheme)
            document.documentElement.setAttribute( "data-theme", this.activeTheme )
        },
        changeMintNav(val){
            this.activeMintNav = val
            this.$store.commit('setMintTopNavCurrent' , val)
        },
        changeLiquidationNav(val){
            // console.log(val);
            if(this.activeLiquidation === val) return 

            this.activeLiquidation = val
            if(val && val !== '') {
                this.$router.push({path:'/position/'+val})
            } else {
                this.$router.push({path:'/position'})
            }
        },
        SecurityAudit() {
            window.open("https://www.certik.com/projects/h2ofinance")
        },
        clickLanguageDropdown(command) {
            if(command) {
                this.language = command;
                // 获取当前语言
                let curLng = this.$i18n.i18next.language
                // 切换语言
                this.$i18n.i18next.changeLanguage(command);
            }
            // console.log(item);
        },
        substring(str) {
            var str1 = str.replace(str.substring(8, str.length - 4), "****");
            return str1;
        },
        handleOpen(key, keyPath) {
            console.log(key, keyPath);
        },
        handleClose(key, keyPath) {
            console.log(key, keyPath);
        },
        menuSelectOpen(index, indexPath) {
            let formPath = this.$route.path;
            console.log(index, formPath, indexPath, this.isHashpowerMenu);
            if(index !== formPath) {
                this.defaultOpenedsArray = [];
            }
            if((index === '/hashpower/list' && formPath !== '/hashpower/list') || (index === '/power/list' && formPath !== '/power/list')) {
                this.$router.push({path: indexPath[0]})
                this.isHashpowerMenu = true;
                this.menuDrawerShow = false;
            }
        }
    }
}
</script>
<style lang="scss" scoped>
    .sider-inner {
        display: flex;
        // position: absolute;
        align-items: center;
        flex-direction: row;
        left: 0;
        top: 50%;
        // transform: translateY(-50%);
        // top: 15px;
        // height: 100%;
        // min-height: 436px;
        .menu {
            img {
                width: 30px;
            }
            box-sizing: border-box;
            margin-right: 10px;
            margin-top: 5px;
        }
        .title {
            display: flex;
            align-items: center;
            margin-left: -10px;
            font-weight: 900;
            font-size: 18px;
            color: #fff;
        }
        .logo {
            display: flex;
            align-items: center;
            // margin-left: 20px;
            // margin-right: 10px;
            height: 78px;
            box-sizing: border-box;
            .logo-link {
                display: flex;
                align-items: center;
                height: 100%;
                text-align: left;
                img {
                    // width: 70px;
                    height: 70px;
                    margin-left: -24px;
                    border-radius: 50%;
                }
                span {
                    display: inline-block;
                    font-size: 16px;
                    @include mainFont($color-mainFont-light);
                    // color: #1C1C1B;
                    padding-top: 4px;
                    // margin-left: -14px;
                }
                title {
                    font-size: 16px;
                    color: #fff;
                    padding-top: 4px;
                    // margin-left: -14px;
                }
            }
        }
    }
    .el-menu {
        background-color: transparent;
        border-bottom: 0;
        color: #9ca3af;

        .el-menu-item {
            background-color: transparent !important;
            color: #fff;
            &:hover {
                background-color: transparent !important;
                color: #fff;
            }
        }

        .el-menu-item.is-active {
            background-color: transparent !important;
        }

        .el-submenu {
            background-color: transparent !important;

            .el-submenu__title {
                font-size: 18px !important;
                background-color: transparent !important;

                &:hover {
                    background-color: transparent !important;
                    color: #fff;
                }
            }
        }

        .el-submenu__title {
            background-color: transparent !important;
            font-size: 18px;
            &:hover {
                background-color: transparent !important;
                color: #fff;
            }
        }

        .el-menu--popup {
            background-color: #1a1a1a;
            border-color: rgba(0, 232, 137, 0.15);

            .el-menu-item {
                background-color: #1a1a1a !important;
                color: #9ca3af;

                &:hover {
                    background-color: #2a2a2a !important;
                    color: #fff !important;
                }
            }
        }
    }
    .el-menu-demo {
        .el-menu-item {
            font-size: 18px;
            .is-active {
                border-bottom: 4px solid #409EFF;
            }
        }
        .el-submenu {
            .el-submenu__title {
                font-size: 18px;
            }
        }
    }
    .el-divider--horizontal {
        margin: 5px 0;
    }
    .totalMintNav{
        margin-right: auto;
        padding-left: 60px;
        display: flex;
        .item{
            min-width: 120px;
            margin-right: 8px;
            height: 63px;
            line-height: 63px;
            text-align: center;
            border-bottom: 2px solid transparent;
            box-sizing: border-box;
            cursor: pointer;
        }
        .active {
            color: #0096FF;
            font-weight: bold;
            border-color: #0096FF;
        }
    }
    .security-audit {
        cursor: pointer;
        height: 36px;
        // border: 1px solid #1C1C1B;
        // border-style: solid;
        // border-width: 1px;
        @include infoBoxBorder($infoBoxBorder-light);
        // border-radius: 8px;
        width: 150px;
        margin-right: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        img {
            width: 115px;
            height: 29px;
        }
    }
    .language {
        ::v-deep  {
            display: flex;
            align-items: center;
            justify-content: center;
            // width: 40px;
            margin-right: 15px;
            button {
                height: 30px;
                line-height: 1px;
            }
            .el-dropdown {
                font-size: 18px;
            }
            .el-dropdown-link {
                @include mainFont($color-mainFont-light);
                cursor: pointer;
                // color: #409EFF;
            }
            .el-icon-arrow-down {
                font-size: 12px;
            }
            .popper-select {
                position: absolute !important;
                top: 23px !important;
                left: -40px !important;
                @include tooltipPopperBgcTwo($color-tooltipPopper-2-dark);
                border: 0;
            }
            .el-dropdown-menu__item {
                @include mainFont($color-mainFont-light);
            }
            .el-dropdown-menu__item:hover {
                @include sideBarBgc($color-bgc-sideBar-dark);
            }
            img {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .popper__arrow {
                left: 45px !important;
            }
            .el-popper[x-placement^=bottom] {
                .popper__arrow::after {
                    @include tooltipPopperBorderBottom($color-tooltipPopper-dark)
                }
                .popper__arrow {
                    @include tooltipPopperBorderBottom($color-tooltipPopper-dark)
                }
            }
        }
    }
    .carousel {
        ::v-deep  {
            margin-right: auto;
            width: 80%;
            height: 50px;
            // margin-bottom: 15px;
            // display: contents;
            >div {
                background-color: #333;
                border-radius: 38px;
                padding-left: 20px;
                box-sizing: border-box;
                .tit {
                    font-size: 16px;
                    font-weight: 600;
                    line-height: 16px;
                    margin: 17px 0 12px 0;
                    @include mainFont($color-mainFont-light);
        
                    img {
                        height: 18px;
                        vertical-align: middle;
                        margin-right: 8px;
                        position: relative;
                        top: -2px;
                    }
                }
                .num {
                    font-size: 34px;
                    margin: 0 ;
                    font-weight: 600;
                    color: #31c77f;
                    // background-image:-webkit-linear-gradient(bottom,red,#fd8403,yellow); 
                    // background: linear-gradient(90deg, #0096ff, #0024ff);
                }
            }
            .el-carousel__indicators--vertical {
                display: none !important;
            }
            .el-carousel__item h3 {
                // @include mainFont($color-mainFont-light);
                color: #31c77f;
                font-size: 14px;
                opacity: 0.75;
                line-height: 50px;
                margin: 0;
            }
        }
    }

.nav-container {
    position: relative;
}

.headerNav-container {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 80px;
}
</style>