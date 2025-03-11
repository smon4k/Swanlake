<template>
    <div class="app-wrapper">
        <OkxHeaderNav />
        <div class="appmain-container" style="padding-bottom: 0;">
            <AppMain style="padding: 0;"/>
        </div>
    </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
import OkxHeaderNav from './components/okxHeaderNav.vue'
import AppMain from './components/AppMain.vue'
import { mapGetters, mapState } from 'vuex'
export default {
    name:'Layout',
    components:{ AppMain, OkxHeaderNav},
    created(){
        this.$store.commit('copyDefaultState')
        this.checkLogin()
    },
    computed:{
        ...mapGetters([
            'pendingOrderAmount',
            'successOrderAmount',
            'failedOrderAmount'
        ]),
        ...mapState({
            SuccessHash:state=>state.base.tradeStatus.SuccessHash,
            FailedHash:state=>state.base.tradeStatus.FailedHash,
            userDenyId:state=>state.base.tradeStatus.userDenyId,
            domainHostAddress:state=>state.base.domainHostAddress,
            errMessage:state=>state.base.errMessage,
            apiUrl:state=>state.base.apiUrl,
        })
    },
    watch:{
        pendingOrderAmount:{
            handler(val){
                if(val){
                    this.$notify({
                        title: 'Trading...',
                        message: `${val} Processing`,
                        duration: 6000
                    });
                }
            }
        },
        SuccessHash:{
            handler(val){
                if(val){
                    this.$notify({
                        title: 'Success!',
                        dangerouslyUseHTMLString:true,  
                        message: `<a href="${this.domainHostAddress + '' + val}" target="_blank">View on Explorer</a>`,
                        type: 'success',
                        duration: 6000
                    });
                }
            }
        },
        FailedHash:{
            handler(val){
                if(val){
                    this.$notify.error({
                        title: 'Failed!',
                        dangerouslyUseHTMLString:true,  
                        message: this.errMessage || `<a href="${this.domainHostAddress + '' + val}" target="_blank">View on Explorer</a>`,
                        duration: 6000
                    });
                    if(this.errMessage){
                        this.$store.commit('setErrMessage' , '')
                    }
                }
            }
        },
        userDenyId:{
            handler(val){
                console.log('userDenyId' , val);
                if(val){
                    
                    this.$notify.error({
                        title: 'Failed!',
                        message: `Refuse`,
                        duration: 6000
                    });
                    
                }
            }
        }
    },
    methods:{
        checkLogin() {
            const token = localStorage.getItem('token');
            if (token) {
                get(this.apiUrl + '/api/userokx/checkToken', {}, json => {
                    console.log(json.data);
                    if (json.code == 10000) {
                        // localStorage.removeItem('token');
                        this.$store.commit('setUserOkxId', json.data);
                    } else {
                        this.$router.push('/okx/login');
                    }
                });
            } else {
                this.$router.push('/okx/login');
            }
        }
    }
}
</script>