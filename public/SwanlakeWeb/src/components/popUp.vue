<template>
    <div class="popup_outer">
        <div class="trade_success item" v-if="type === 'success'">
            <div class="icon">
                <img src="@/assets/image/tradeSuccess.png" alt="">
            </div>
            <div class="info">
                <p>{{$t('popup_success')}} </p>
                <a 
                    :class="{'notZH':$i18n.locale !== 'zh_CN'}"
                    :href="httpAddress"  target="_blank">{{$t('popup_browser')}}</a>
            </div>
            <div class="close" @click="closePopUp"></div>
        </div>
        <div class="trade_pending item" v-if="type === 'pending'">
            <div class="icon">
                <img src="@/assets/image/trading.png" alt="">
            </div>
            <div class="info">
                <span class="num">{{pendingOrderAmount}}</span>
                <span>{{$t('popup_pending')}}</span>
            </div>
            <div class="close" @click="closePopUp"></div>
        </div>
        
        <div class="trade_pending item construction" v-if="type === 'construction'">
            <div class="icon">
                <img src="@/assets/image/jianshe.svg" alt="">
            </div>
            <div class="info">
                <span
                    :class="{'notZH':$i18n.locale !== 'zh_CN'}"
                >{{$t('popup_processing')}}...</span>
            </div>
            <div class="close" @click="closePopUp"></div>
        </div>
        <div class="trade_fail item" v-if="type === 'fail'">
            <div class="icon">
                <img src="@/assets/image/tradeFail.png" alt="">
            </div>
            <div class="info">
                <p>{{$t('popup_failed')}}</p>
                <span v-if="typeData.isUserDeny">{{$t('popup_refuse')}}</span>
                <a 
                    :class="{'notZH':$i18n.locale !== 'zh_CN'}"
                    :href="httpAddress"  v-else  target="_blank">{{$t('popup_browser')}}</a>
            </div>
            <div class="close" @click="closePopUp"></div>
        </div>


        <div class="close_line"></div>
    </div>
</template>
<script>
import { mapGetters } from 'vuex';
export default {
    name:'popUp',
    props:{
        typeData:{
            type:Object,
            default:()=>{ return {} }
        },
        type:String
    },
    data(){
        return {
            timeOut:null,
        }
    },
    mounted(){
        this.timeOut = setTimeout(()=>{
            let status = {}
            if(this.type === 'fail' || this.type === 'success'){
                status.id = this.typeData.id
            }else {
                status.kind = this.type
            }
            this.$store.dispatch('closePopUp' , status)
        },10800)
    },
    computed:{
        ...mapGetters([
            "pendingOrderAmount"
        ]),
        httpAddress(){
            return 'https://rinkeby.etherscan.io/tx/' + (this.typeData.hash || '')
        }
    },
    beforeDestroy(){
        clearTimeout(this.timeOut)
        this.timeOut = null 
    },
    watch:{
        pendingOrderAmount:{
            handler(val){
                if(!val){
                    this.closePopUp()
                }
            }
        }
    },
    methods:{
        closePopUp(){
            clearTimeout(this.timeOut)
            this.timeOut = null 
            let status = {}
            if(this.type === 'fail' || this.type === 'success'){
                status.id = this.typeData.id
            }else {
                status.kind = this.type
            }
            this.$store.dispatch('closePopUp' , status)
        }
    }
}
</script>
<style lang="stylus" scoped>
.popup_outer {
    width 300px
    height 80px
    // position fixed
    // top 60px
    // right calc(50% - 720px)
    position relative
    background-color #fff
    box-shadow: 0px 2px 20px 0px rgba(170, 170, 170, 0.25); 
    border-radius 10px
    overflow hidden
    // opacity 0
    // animation: showPopDown 0.5s ease-out 0  forwards;
    animation: showPopDown 0.5s linear forwards;
    .icon {
        width 77px
        img {
            width 44px
            height 44px
            margin 18px 0 0 20px
        }
    }
    .item {
        display flex
        position relative
        >div {
            height 80px
        }
        .close {
            position absolute
            width 18px
            height 18px
            right 13px
            top 13px
            background url('~@/assets/image/dialog_close.png') no-repeat;
            background-size contain
            cursor pointer
        }
        .info {
            .notZH{
                font-size 14px
            }
        }
        
    }
    .trade_success .info {
        p {
            font-size 20px
            line-height 20px
            font-weight 600
            padding 16px 0 9px 0
        }
        a {
            font-size 18px
            text-decoration: underline;
            color: #FFC200;
        }
        
    }
    .trade_fail {
         p {
            font-size 20px
            line-height 20px
            font-weight 600
            padding 16px 0 9px 0
            color #f00
        }
        a {
            font-size 18px
            text-decoration: underline;
            color: #FFC200;
        }
    }
    .trade_pending {
        .info{
            font-size 18px
            line-height 80px
            font-weight 600
            .num {
                color #FFC200
                display inline-block
                margin-right 12px
            }
        }
        .icon {
            img{
                transform rotateZ(360deg)
                transform-origin 20px 22px
                animation imgRotating 2s linear    infinite
            }
                    
        }
    }
    .trade_pending.construction {
        .icon{
            img{
                animation none
            }
        }
    }
    .close_line {
        position absolute
        height 4px
        width 100%
        border-radius 2px
        background-color #FFC200
        bottom 0px
        left 0px
        animation closeLine 10s linear 0.8s forwards 
    }
    
}
@keyframes closeLine {
    0% {
        width 100%
    }
    100% {
        width 0%
    }
}
@keyframes showPopDown {
    0% {
        top -30px
        opacity 0
    }
    100%{
        top 0px
        opacity 1
    }
}

@keyframes imgRotating {
    0%{
        transform:rotateZ(360deg);
    }
    100%{
        transform:rotateZ(0deg)
    }
}
</style>
