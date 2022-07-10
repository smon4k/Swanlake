<?php
/**
 * H2O-笔记功能
 * @copyright  Copyright (c) 2000-2020 QIN TEAM (http://www.qlh.com)
 * @version    GUN  General Public License 10.0.0
 * @license    Id:  Userinfo.php 2022-02-19 15:00:00
 * @author     Qinlh WeChat QinLinHui0706
 */
namespace app\admin\controller;
use think\Cookie;
use think\Request;
use lib\ClCrypt;
use ClassLibrary\ClFile;
use app\admin\model\Notes;

class NotesController extends BaseController
{
    /**
    * 获取笔记列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function getNotesList(Request $request) {
        $page = $request->request('page', 1, 'intval');
        $limits = $request->request('limit', 1, 'intval');
        $title = $request->request('title', '', 'trim');
        $address = $request->request('address', '', 'trim');
        $where = [];
        if($title && $title !== "") {
            $where['a.title'] = ['like',"%" . $title . "%"];
        }
        if($address && $address !== "") {
            $where['b.address'] = $address;
        }
        $data = Notes::getNotesList($page, $where, $limits);
        $count = $data['count'];
        $allpage = $data['allpage'];
        $lists = $data['lists'];
        return $this->as_json(['page'=>$page, 'allpage'=>$allpage, 'count'=>$count, 'data'=>$lists]);
    }

    /**
    * 删除笔记信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function delNotesRow(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $res = Notes::delNotesRow($id);
        if($res['code'] == 1) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

    /**
    * 修改笔记状态
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public function rackUpStart(Request $request) {
        $id = $request->request('id', 0, 'intval'); 
        $status = $request->request('status', 0, 'intval'); 
        $res = Notes::rackUpStart($id, $status);
        if($res) {
            return $this->as_json($res['msg']);
        } else {
            return $this->as_json(70001, $res['msg']);
        }
    }

}