<?php
namespace app\admin\controller;
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
}
