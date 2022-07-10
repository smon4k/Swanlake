<?php
namespace app\admin\controller;
use think\Request;
use think\Controller;

class IndexController extends BaseController {

    public function index()
    {
        // $this->redirect("/admin/user/login");
        return $this->fetch("user/login");
    }
}
