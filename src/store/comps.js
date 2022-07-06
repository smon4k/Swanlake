export default {
    state:{
        mainTheme:localStorage.getItem('theme') ? localStorage.getItem('theme') : 'dark',
        positionFinish:false,
        screenWidth: document.body.clientWidth,
        isMobel: document.body.clientWidth < 600 ? true : false,
    },
    mutations:{
        setMainTheme(state , val){
            state.mainTheme = val
        },
        setPositionFinish(state , val){
            state.positionFinish = val
        },
        setScreenWidth(state , val){
            state.screenWidth = val;
            if(val < 600) {
                state.isMobel = true;
            } else {
                state.isMobel = false;
            }
        },
    }
}