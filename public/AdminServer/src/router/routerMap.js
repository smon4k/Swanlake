const routerMap = [
    {
        name: '系统总览',
        path: '/index/system'
    },
    {
        name: '项目中心',
        path: '/index/project',
        children: [{
            name: '项目详情',
            path: '/index/project/detail',
            children: [{
                name: '学校数据'
            }]
        }]
    }
]
module.exports = routerMap