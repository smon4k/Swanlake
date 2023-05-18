<?php
// +----------------------------------------------------------------------
// | 文件说明：理财产品 api
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15035574759·@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-09-05
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\admin\model\LlpFinance;
use think\Request;
use think\Db;
use think\Controller;
use ClassLibrary\ClFile;
use ClassLibrary\CLUpload;
use ClassLibrary\CLFfmpeg;
use lib\Filterstring;

class LlpfinanceController extends BaseController
{
    /**
     * 获取跟踪数据列表
     * @author qinlh
     * @since 2023-05-18
     */
    public function getLlpFinanceList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $where = [];
        $data = LlpFinance::getLlpFinanceList($where, $page, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        // p($lists);
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }
    
}
