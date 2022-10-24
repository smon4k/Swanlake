<template>
    <div class="nav-container">
        <div class="container">
            <div class="headerNav-container">
                <div class="sider-inner">
                    <div class="menu" @click="menuDrawerShow = true" v-if="screenWidth < 1280">
                        <img src="@/assets/menu.png" alt="" />
                    </div>
                    <div class="logo" v-show="screenWidth > 600">
                        <router-link to="/" class="logo-link">
                            <img
                                src="@/assets/log.jpeg"
                                alt=""
                                v-if="mainTheme === 'light'"
                            />
                            <img src="@/assets/log.jpeg" alt="" v-else />
                        <!-- <br /> -->
                        </router-link>
                    </div>
                    <div class="title" v-show="screenWidth > 600"> Swan Lake Quant </div>
                </div>
                <!-- 安全审计 -->
                <!-- <div class="security-audit" v-if="$route.path !== '/deposit'" @click="SecurityAudit()">
                    <img src="@/assets/certik_light.svg" alt="" v-if="mainTheme==='light'">
                    <img src="@/assets/certik.svg" alt="" v-else>
                </div> -->
                <!-- {{ language }} -->
                <!-- <i class="el-icon-arrow-down el-icon--right"></i> -->
                <div class="language">
                      <!-- <el-button v-if="language === 'en'" @click="clickLanguageDropdown('zh')">中文</el-button>
                      <el-button v-else @click="clickLanguageDropdown('en')">英文</el-button> -->
                    <!-- <el-dropdown trigger="click" @command="clickLanguageDropdown">
                        <span class="el-dropdown-link">
                            {{ language }}
                            <i class="el-icon-arrow-down el-icon--right"></i>
                            <img src="@/assets/zh.png" alt="" v-if="activeTheme==='light'" width="22">
                            <img src="@/assets/en.png" alt="" v-else width="22">
                        </span>
                        <el-dropdown-menu slot="dropdown" :append-to-body="false" popper-class="popper-select" class="popper-select">
                            <el-dropdown-item command="zh">中文</el-dropdown-item>
                            <el-dropdown-item command="en">English</el-dropdown-item>
                        </el-dropdown-menu>
                    </el-dropdown> -->

                </div>
                <!-- <div class="main-theme" @click="changeTheme">
                    <img src="@/assets/theme.png" alt="" v-if="activeTheme==='light'">
                    <img src="@/assets/daily_icon.png" alt="" v-else>
                </div> -->
                <!-- <div class="switchLang">
                    <div @click="changeLang('zh')" :class="{'activeCN':activeLang === 'zh'}">中文</div>
                    <div @click="changeLang('en')" :class="{'activeEN':activeLang === 'en'}">EN</div>
                    <span class="bar"></span>
                </div> -->
                <div class="connectWallet pending" v-if="isConnected && pendingOrderAmount" >
                    <span>Trading</span>
                    <img src="@/assets/shuaxin.png" alt="">
                </div>
                <div class="connectWallet" @click="connectWallet" v-else>
                    {{isConnected ? addressStr : 'bsc ' + $t('public:ConnectWallet') }}
                </div>
            </div>

            <el-divider></el-divider>

            <div v-show="screenWidth >= 1280">
                <el-menu :default-active="$route.path" class="el-menu-demo" mode="horizontal" @select="handleSelect" :router="true" v-if="navList.length">
                    <el-menu-item v-for="(item, index) in navList" :key="index" :index="item.path">{{ item.name }}</el-menu-item>
                </el-menu>
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
                                src="@/assets/log.jpeg"
                                alt=""
                                v-if="mainTheme === 'light'"
                            />
                            <img src="@/assets/log.jpeg" alt="" v-else />
                        <!-- <br /> -->
                        </router-link>
                    </div>
                    <div class="title"> Swan Lake Quant </div>
                </div>
                <br><br>
                <el-menu
                    :default-active="$route.path"
                    class="el-menu-vertical-demo"
                    :router="true"
                    @select="handleSelect">
                    <el-menu-item v-for="(item, index) in navList" :key="index" :index="item.path">
                        <span slot="title">{{ item.name }}</span>
                    </el-menu-item>
                </el-menu>

            </el-drawer>
        </div>
        <div class="footer">
            <div class="container">
                <font style="vertical-align: inherit;">
                    <font style="vertical-align: inherit;">版权所有 © 1999 - 2022 SwanLake Company, LLC。</font>
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
export default {
    name:'headerNav',
    data(){
        return {
            activeLang:'en',
            activeTheme:'dark',
            activeMintNav:'All',
            activeLiquidation:'',
            language: 'ZH',
            nftRewardList: [],
            window: window,
            screenWidth: document.body.clientWidth,
            timer: null,
            activeIndex: '1',
            menuDrawerShow: false,
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
                {
                    name: '首页',
                    path: "/home",
                    // img: require("@/assets/images/hashpower.png"),
                    // img2: require("@/assets/images/hashpower_light.png"),
                    children: [],
                    isHref: false,
                },
                {
                    name: '我的理财',
                    path: "/my/finances",
                },
                {
                    name: '理财产品',
                    path: "/financial/product",
                },
                {
                    name: '资金监控',
                    path: "/fund/monitoring",
                },
                {
                    name: '充提',
                    path: "/depositWithdrawal",
                },
                {
                    name: '资金账户监控',
                    path: "/fund/monitoring/account",
                },
                {
                    name: '净值配置',
                    path: "/day/networth",
                },
                {
                    name: '币种统计',
                    path: "/bscTokenStatistics",
                },
            ];
            if(!this.isAdmin) {
                arr.splice(arr.length - 3, 3);
            }
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

        let language = "en";
        language = localStorage.getItem('i18nextLng');
        if(language && language !== undefined) {
            this.language = language;
        }
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
        }
    }
}
</script>
<style lang="scss" scoped>
/deep/ {
    .el-drawer__body {
        padding: 20px !important;
    }
}
.sider-inner {
    display: flex;
    position: absolute;
    left: 10px;
    align-items: center;
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
        justify-content: space-around;
        flex-direction: column;
        font-weight: 900;
    }
    .logo {
        margin-left: 20px;
        margin-right: 10px;
        height: 40px;
        box-sizing: border-box;
        .logo-link {
            display: block;
            height: 100%;
            text-align: center;
            img {
                height: 40px;
                margin-left: -24px;
                border-radius: 50%;
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
}
.el-menu-item {
    font-size: 18px;
    .is-active {
        border-bottom: 4px solid #409EFF;
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
    /deep/ {
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
    /deep/ {
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
</style>