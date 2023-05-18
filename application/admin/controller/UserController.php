<?php
namespace app\admin\controller;
use app\admin\model\User;
use think\Controller;
use think\Request;

class UserController extends Controller
{
    public function index()
    {
        return $this->fetch();
    }

    public function login()
    {
        return $this->fetch();
    }
    
    public function profile(Request $request)
    {
        $type = $request->param('type','personalInformation');
        $this->assign('type',$type);
        return $this->fetch();
    }

    /**
    * 获取用户列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getUserList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $nickname = $request->request('nickname', '', 'trim');
        $address = $request->request('address', '', 'trim');
        $currency = $request->request('currency', 'usdt', 'trim');
        $where = [];
        $order = 'total_balance desc';
        if(!$currency || $currency == '') {
            return $this->as_json('70001', 'Missing parameters');
        }
        if($nickname && $nickname !== "") {
            $where['a.nickname'] = ['like',"%" . $nickname . "%"];
        }
        if($address && $address !== "") {
            $where['a.address'] = $address;
        }
        if($currency && $currency !== "") {
            $where['b.currency'] = $currency;
        }
        $data = User::getUserList($page, $where, $limits, $order);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }
}
