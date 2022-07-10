<template>
    <div class="app-wrapper">
        <!-- <sidebar /> -->
        <headerNav />
        <div class="appmain-container">
            <AppMain/>
        </div>
    </div>
</template>
<script>
import sidebar from './components/sidebar.vue'
import headerNav from './components/headerNav.vue'
import AppMain from './components/AppMain.vue'
import { mapGetters, mapState } from 'vuex'
export default {
    name:'Layout',
    components:{sidebar , headerNav, AppMain},
    created(){
        this.$store.commit('copyDefaultState')
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
    }
}
</script>