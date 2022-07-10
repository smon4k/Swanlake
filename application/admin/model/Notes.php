<?php
// +----------------------------------------------------------------------
// | 文件说明：H2O-笔记-Model
// +----------------------------------------------------------------------
// | Copyright (c) 2017-2021 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2022-06-24
// +----------------------------------------------------------------------
namespace app\admin\model;

use lib\ClCrypt;
use think\Cache;
use app\api\model\Reward;

class Notes extends Base
{   
    /**
    * 获取NFT列表
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function getNotesList($page, $where, $limits=0) {
        if($limits == 0) {
            $limits = config('paginate.list_rows');// 获取总条数
        }
        // p($where);
        $count = self::name("notes")
                    ->alias("a")
                    ->join('m_user b', 'a.user_id=b.id')
                    ->where($where)
                    ->count();//计算总页面
        // p($count);
        $allpage = intval(ceil($count / $limits));
        $lists = self::name("notes")
                    ->alias("a")
                    ->join('m_user b', 'a.user_id=b.id')
                    ->where($where)
                    ->page($page, $limits)
                    ->field('a.*,b.address,b.nickname,avatar')
                    ->order("id desc")
                    ->select();
        foreach ($lists as $key => $val) {
            $lists[$key]['imgs'] = json_decode($val['imgs'], true);
            $lists[$key]['amount'] = Reward::getNotesFollowNum($val['id']);
        }
        // p($lists);
        return ['count'=>$count,'allpage'=>$allpage,'lists'=>$lists];
    }

    /**
     * 获取nft最新状态
     * @author qinlh
     * @since 2022-02-19
     */
    public static function getMyNotesInfo($nft_id) {
        if($nft_id) {
            $data = self::name('my_market')->where("nft_id", $nft_id)->order('id desc')->limit(1)->find();
            if($data) {
                return $data->toArray();
            }
            return [];
        }
    }

    /**
     * 获取用户我的市场状态
     * @author qinlh
     * @since 2022-02-18
     */
    public static function getUserNftBuyFind($address='', $nft_id=0, $type = 0)
    {
        if ($nft_id > 0 && $address !== '' && $type > 0) {
            $res = self::name('my_market')->where(['address'=>$address, 'nft_id' => $nft_id, 'type' => $type])->find();
            if ($res) {
                return $res->toArray();
            }
        }
        return false;
    }

    /**
    * 删除球队信息
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function delNotesRow($id) {
        if($id) {
            //首先判断是否有对应广告数据
            // $IsTeamList = self::name("team_game")->where("id", $id)->find();
            // if($IsTeamList && count($IsTeamList) > 0) {
            //     return ['code'=>0, 'msg'=>'该广告位下面对应有广告数据，不能直接删除'];
            // } else {
                self::startTrans();
                try{
                    $res = self::where("id", $id)->setField('is_del', 0);
                    if(true == $res) {
                        self::commit();
                        return ['code'=>1, 'msg'=>'删除成功'];
                    } else {
                        self::rollback();
                        return ['code'=>0, 'msg'=>'删除失败'];
                    }
                } catch (\Exception $e) {
                    // 回滚事务
                    self::rollback();
                    return ['code'=>0, 'msg'=>'删除失败'];
                }
            // }
        }
    }

    /**
    * 更改状态
    * @param  [post] [description]
    * @return [type] [description]
    * @author [qinlh] [WeChat QinLinHui0706]
    */
    public static function rackUpStart($id, $state) {
        return self::where("id", $id)->setField('state', $state);
    }
}
