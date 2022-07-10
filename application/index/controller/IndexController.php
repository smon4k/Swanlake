<?php
namespace app\index\controller;

use think\Request;
use think\Controller;

class IndexController extends Controller
{
    public function index()
    {
        return 'Hello Swanlake API';
        // return $this->fetch();
    }

    public function test()
    {
        // return 'Hello H2O API';
        return $this->fetch();
    }
}
