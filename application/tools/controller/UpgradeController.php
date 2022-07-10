<?php
namespace app\tools\controller;

use app\index\model\ExamGroupBatchModel;
use app\index\model\ExamGroupModel;
use app\index\model\PlatformModel;
use app\index\model\PlatformOrgModel;
use app\index\model\PlatformOrgUserModel;
use app\index\model\PlatformScaleOrgModel;
use app\index\model\PlatformScaleUserModel;
use app\index\model\PlatformUserModel;
use app\index\model\ScalesModel;
use ClassLibrary\ClExcel;
use ClassLibrary\ClFieldVerify;
use ClassLibrary\ClString;
use Mpdf\Tag\Em;

/**
 * 升级
 * Class UpgradeController
 * @package app\tools\controller
 */
class UpgradeController extends ToolsBaseController {

    /**
     * 20181010 PB项目虚拟分组等信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function up20181010() {
        //考试分组
        $group_id   = -1;
        $group_info = ExamGroupModel::instance()->where([
            ExamGroupModel::F_ID => $group_id
        ])->find();
        if (empty($group_info)) {
            //新增
            ExamGroupModel::instance()->insert([
                ExamGroupModel::F_ID      => $group_id,
                ExamGroupModel::F_NAME    => '优策虚拟考试分组',
                ExamGroupModel::F_IS_OPEN => ExamGroupModel::V_IS_OPEN_NO,
            ]);
        }
        //考试批次
        $batch_id   = -1;
        $batch_info = ExamGroupBatchModel::instance()->where([
            ExamGroupBatchModel::F_ID            => $batch_id,
            ExamGroupBatchModel::F_EXAM_GROUP_ID => $group_id,
        ])->find();
        if (empty($batch_info)) {
            ExamGroupBatchModel::instance()->insert([
                ExamGroupBatchModel::F_ID            => $batch_id,
                ExamGroupBatchModel::F_EXAM_GROUP_ID => $group_id,
                ExamGroupBatchModel::F_NAME          => '优策虚拟考试批次',
                ExamGroupBatchModel::F_IS_VALID      => ExamGroupBatchModel::V_IS_VALID_NO
            ]);
        }
    }

    /**
     * 上海筑桥
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function forZhuQiao20181204() {
        $for_scale_id      = get_param('parent_scale_id', ClFieldVerify::instance()->verifyIsRequire()->verifyNumber()->fetchVerifies(), '量表中心-学习适应性家长版-id');
        $scale_parent_name = '学习适应性量表-家长版';
        $scale_parent_id   = ScalesModel::instance()->where([
            ScalesModel::F_NAME => $scale_parent_name
        ])->value(ScalesModel::F_ID);
        if (empty($scale_parent_id)) {
            echo_info($scale_parent_name . ' 不存在');
            exit;
        }
        $scale_teacher_name = '学习适应性量表-教师版';
        $scale_teacher_id   = ScalesModel::instance()->where([
            ScalesModel::F_NAME => $scale_teacher_name
        ])->value(ScalesModel::F_ID);
        if (empty($scale_teacher_id)) {
            echo_info($scale_teacher_name . ' 不存在');
            exit;
        }
        $vip_id = PlatformModel::instance()->where([
            PlatformModel::F_NAME => ['like', '%VIP%']
        ])->value(PlatformModel::F_ID);
        if (empty($vip_id)) {
            echo_info('VIP 项目不存在');
            exit;
        }
        $csv         = '姓名,学号,年级,班级,
陈乐言,1,1,1,
春小天,2,1,1,
何灵杰,3,1,1,
季哲然,4,1,1,
李霁洋,5,1,1,
廖俊博,6,1,1,
是胤泽,7,1,1,
宋霈辰,8,1,1,
杨其华,9,1,1,
叶骁毅,10,1,1,
余暄英哲,11,1,1,
刘林轩,12,1,1,
郭小熙,13,1,1,
韩子钰,14,1,1,
刘家祯,15,1,1,
刘佩霖,16,1,1,
束云霏,17,1,1,
宋慕远,18,1,1,
伍子凡,19,1,1,
赵韵迪,20,1,1,
,,,,
陈一诺,1,1,2,
丁睿涵,2,1,2,
冯海洋,3,1,2,
高振庭,4,1,2,
郭一江,5,1,2,
罗一言,6,1,2,
逄佳霖,7,1,2,
苏子烨,8,1,2,
殷睿泽,9,1,2,
宗煜霖,10,1,2,
陈依晨,11,1,2,
冯子麟,12,1,2,
郭孟涵,13,1,2,
刘浅妤,14,1,2,
皮涵羽,15,1,2,
盛思齐,16,1,2,
阎家仪,17,1,2,
叶林溪,18,1,2,
于子晗,19,1,2,
张子轩,20,1,2,
,,,,
巢涵宁,1,1,3,
陈从心,2,1,3,
陈定垚,3,1,3,
李泽远,4,1,3,
蔺宸,5,1,3,
毛景澄,6,1,3,
沈择言,7,1,3,
王之辰,8,1,3,
姚书晨,9,1,3,
张忱之,10,1,3,
蔡欣辰,11,1,3,
曹艺涵,12,1,3,
马飞飞,13,1,3,
王乐妍,14,1,3,
王陆好,15,1,3,
谢喆熙,16,1,3,
谢芷涵,17,1,3,
姚思宸,18,1,3,
张安笛,19,1,3,
朱清扬,20,1,3,
,,,,
李铠泽,1,1,4,
李云龙,2,1,4,
林炫默,3,1,4,
孙海朔,4,1,4,
万千航,5,1,4,
王泓钧,6,1,4,
王若非,7,1,4,
张瀚中,8,1,4,
周昊辰,9,1,4,
周兰一钡,10,1,4,
周子烜,11,1,4,
翁康杰,12,1,4,
洪语希,13,1,4,
施子宸,14,1,4,
汤锦恬,15,1,4,
王奕惟,16,1,4,
杨毓舒,17,1,4,
姚宇柔,18,1,4,
郁炘宜,19,1,4,
张淡月,20,1,4,
朱家瑶,21,1,4,
,,,,
陈嘉荣,1,2,1,
龚子健,2,2,1,
康文森,3,2,1,
张元贞,4,2,1,
廖远喆,5,2,1,
宋英正,6,2,1,
张瀚,7,2,1,
张兴牧,8,2,1,
赵墨扬,9,2,1,
周思易,10,2,1,
杨鈺涵,11,2,1,
范梓言,12,2,1,
李依霏,13,2,1,
卢品尚,14,2,1,
逄芮桐,15,2,1,
沈芊岑,16,2,1,
童瑶,17,2,1,
魏璟涵,18,2,1,
严然,19,2,1,
郁舒涵,20,2,1,
,,,,
张丞远,1,2,2,
李澍晨,2,2,2,
,3,2,2,
龙万祯,4,2,2,
满昊达,5,2,2,
潘奕帆,6,2,2,
王音哲,7,2,2,
闻博阳,8,2,2,
徐威,9,2,2,
张雨航,10,2,2,
池扬子,11,2,2,
付婉宁,12,2,2,
路安妮,13,2,2,
綦妙,14,2,2,
孙韵涵,15,2,2,
辛梓琳,16,2,2,
陆可可,17,2,2,
阳馨仪,18,2,2,
张雁扬,19,2,2,
贺意涵,20,2,2,
赵子涵,21,2,2,
,,,,
陈诺,1,2,3,
伏广鹏,2,2,3,
李政霖,3,2,3,
柳博尧,4,2,3,
邱子川,5,2,3,
申博远,6,2,3,
石清远,7,2,3,
唐布尔,8,2,3,
王陆宽,9,2,3,
周明泽,10,2,3,
董雨菡,11,2,3,
李欣然,12,2,3,
刘宜君,13,2,3,
柳宜辰,14,2,3,
佘米佳,15,2,3,
覃显越,16,2,3,
吴彧葭,17,2,3,
奚嘉盈,18,2,3,
许家瑜,19,2,3,
郑媛元,20,2,3,
段昌博,21,2,3,
,,,,
裴尊玺,1,2,4,
程智炜,2,2,4,
邓皓元,3,2,4,
关彧,4,2,4,
潘奕同,5,2,4,
王铭一,6,2,4,
杨朗,7,2,4,
王敬翔,8,2,4,
虞尧盛,9,2,4,
周戡又,10,2,4,
朱俊泽,11,2,4,
马恩钰,12,2,4,
李嫣然,13,2,4,
卢麒朵,14,2,4,
苏嘉琪,15,2,4,
孙一一,16,2,4,
王语陌,17,2,4,
姜乐怡,18,2,4,
张予,19,2,4,
张悦,20,2,4,
臧子嫣,21,2,4,
';
        $items       = explode("\n", $csv);
        $csv_student = [];
        $students    = [];
        foreach ($items as $k => $each) {
            if ($k == 0) {
                continue;
            }
            if (empty($each)) {
                continue;
            }
            list($name, $num, $grade, $class) = explode(',', $each);
            if (empty($name)) {
                continue;
            }
            if (!isset($students[$grade])) {
                $students[$grade] = [];
            }
            if (!isset($students[$grade][$class])) {
                $students[$grade][$class] = [];
            }
            $account                    = 'zqs' . ClString::append(ClString::append($grade) . ClString::append($class) . ClString::append($num), 0, 9);
            $students[$grade][$class][] = [
                'name'    => $name,
                'num'     => $num,
                'account' => $account
            ];
            $csv_student[]              = [$grade, $class, $account, '后六位', $name];
        }
        $file    = ClExcel::exportToCsv(['年级', '班级', '账号', '密码', '姓名'], $csv_student);
        $to_file = DOCUMENT_ROOT_PATH . '/shzq_students.csv';
        rename($file, $to_file);
        echo_info($to_file);
        //新建分组
        $org_name = '上海筑桥-家长';
        $org_id   = PlatformOrgModel::instance()->where([
            PlatformOrgModel::F_PLATFORM_ID => $vip_id,
            PlatformOrgModel::F_NAME        => $org_name
        ])->value(PlatformOrgModel::F_ID);
        if (empty($org_id)) {
            PlatformOrgModel::instance()->insert([
                PlatformOrgModel::F_PLATFORM_ID => $vip_id,
                PlatformOrgModel::F_NAME        => $org_name,
                PlatformOrgModel::F_ACCOUNT     => 'shanghaizhuqiao',
                PlatformOrgModel::F_PASSWORD    => '123456'
            ]);
            $org_id = PlatformOrgModel::instance()->getLastInsID();
        }
        //分组量表关系
        $scale_org_info = PlatformScaleOrgModel::instance()->where([
            PlatformScaleOrgModel::F_PLATFORM_ID     => $vip_id,
            PlatformScaleOrgModel::F_SCALE_ID        => $scale_parent_id,
            PlatformScaleOrgModel::F_PLATFORM_ORG_ID => $org_id
        ])->find();
        if (empty($scale_org_info)) {
            //新增
            PlatformScaleOrgModel::instance()->insert([
                PlatformScaleOrgModel::F_PLATFORM_ID     => $vip_id,
                PlatformScaleOrgModel::F_SCALE_ID        => $scale_parent_id,
                PlatformScaleOrgModel::F_PLATFORM_ORG_ID => $org_id
            ]);
        }
        foreach ($students as $grade_id => $grade_students) {
            foreach ($grade_students as $class_id => $class_students) {
                foreach ($class_students as $each) {
                    $account = $each['account'];
                    $uid     = PlatformUserModel::getUidByPlatformAndAccount($vip_id, $account);
                    if (empty($uid)) {
                        //新增用户
                        PlatformUserModel::$fields_verifies = [];
                        PlatformUserModel::instance()->insert([
                            PlatformUserModel::F_PLATFORM_ID => $vip_id,
                            PlatformUserModel::F_NAME        => $each['name'],
                            PlatformUserModel::F_ACCOUNT     => $account,
                            PlatformUserModel::F_PASSWORD    => substr($account, -6)
                        ]);
                        $uid = PlatformUserModel::instance()->getLastInsID();
                    }
                    //群组关系
                    $org_info = PlatformOrgUserModel::instance()->where([
                        PlatformOrgUserModel::F_PLATFORM_ID     => $vip_id,
                        PlatformOrgUserModel::F_PLATFORM_ORG_ID => $org_id,
                        PlatformOrgUserModel::F_PLATFORM_UID    => $uid
                    ])->find();
                    if (empty($org_info)) {
                        //新增
                        PlatformOrgUserModel::instance()->insert([
                            PlatformOrgUserModel::F_PLATFORM_ID     => $vip_id,
                            PlatformOrgUserModel::F_PLATFORM_ORG_ID => $org_id,
                            PlatformOrgUserModel::F_PLATFORM_UID    => $uid
                        ]);
                    }
                    //量表用户关系
                    $scale_user_info = PlatformScaleUserModel::instance()->where([
                        PlatformScaleUserModel::F_PLATFORM_ID     => $vip_id,
                        PlatformScaleUserModel::F_PLATFORM_ORG_ID => $org_id,
                        PlatformScaleUserModel::F_SCALE_ID        => $scale_parent_id,
                        PlatformScaleUserModel::F_PLATFORM_UID    => $uid
                    ])->find();
                    if (empty($scale_user_info)) {
                        //新增
                        PlatformScaleUserModel::instance()->insert([
                            PlatformScaleUserModel::F_PLATFORM_ID     => $vip_id,
                            PlatformScaleUserModel::F_PLATFORM_ORG_ID => $org_id,
                            PlatformScaleUserModel::F_SCALE_ID        => $scale_parent_id,
                            PlatformScaleUserModel::F_PLATFORM_UID    => $uid
                        ]);
                    }
                }
            }
        }
        //处理老师
        $csv_teacher = [];
        foreach ($students as $grade_id => $grade_students) {
            foreach ($grade_students as $class_id => $class_students) {
                $org_name = sprintf('上海筑桥-教师-%s年级-%s班', $grade_id, $class_id);
                $org_id   = PlatformOrgModel::instance()->where([
                    PlatformOrgModel::F_PLATFORM_ID => $vip_id,
                    PlatformOrgModel::F_NAME        => $org_name
                ])->value(PlatformOrgModel::F_ID);
                if (empty($org_id)) {
                    PlatformOrgModel::instance()->insert([
                        PlatformOrgModel::F_PLATFORM_ID => $vip_id,
                        PlatformOrgModel::F_NAME        => $org_name,
                        PlatformOrgModel::F_ACCOUNT     => 'shanghaizhuqiao' . $grade_id . $class_id,
                        PlatformOrgModel::F_PASSWORD    => '123456'
                    ]);
                    $org_id = PlatformOrgModel::instance()->getLastInsID();
                }
                //分组量表关系
                $scale_org_info = PlatformScaleOrgModel::instance()->where([
                    PlatformScaleOrgModel::F_PLATFORM_ID     => $vip_id,
                    PlatformScaleOrgModel::F_SCALE_ID        => $scale_teacher_id,
                    PlatformScaleOrgModel::F_PLATFORM_ORG_ID => $org_id
                ])->find();
                if (empty($scale_org_info)) {
                    //新增
                    PlatformScaleOrgModel::instance()->insert([
                        PlatformScaleOrgModel::F_PLATFORM_ID     => $vip_id,
                        PlatformScaleOrgModel::F_SCALE_ID        => $scale_teacher_id,
                        PlatformScaleOrgModel::F_PLATFORM_ORG_ID => $org_id
                    ]);
                }
                $account       = 'zqt' . ClString::append(ClString::append($grade_id) . ClString::append($class_id), 0, 9);
                $csv_teacher[] = [$grade_id, $class_id, $account, '后六位', sprintf('%s年级%s班老师', $grade_id, $class_id)];
                $uid           = PlatformUserModel::getUidByPlatformAndAccount($vip_id, $account);
                if (empty($uid)) {
                    //新增用户
                    PlatformUserModel::$fields_verifies = [];
                    PlatformUserModel::instance()->insert([
                        PlatformUserModel::F_PLATFORM_ID => $vip_id,
                        PlatformUserModel::F_NAME        => sprintf('%s年级%s班老师', $grade_id, $class_id),
                        PlatformUserModel::F_ACCOUNT     => $account,
                        PlatformUserModel::F_PASSWORD    => substr($account, -6)
                    ]);
                    $uid = PlatformUserModel::instance()->getLastInsID();
                }
                //群组关系
                $org_info = PlatformOrgUserModel::instance()->where([
                    PlatformOrgUserModel::F_PLATFORM_ID     => $vip_id,
                    PlatformOrgUserModel::F_PLATFORM_ORG_ID => $org_id,
                    PlatformOrgUserModel::F_PLATFORM_UID    => $uid
                ])->find();
                if (empty($org_info)) {
                    //新增
                    PlatformOrgUserModel::instance()->insert([
                        PlatformOrgUserModel::F_PLATFORM_ID     => $vip_id,
                        PlatformOrgUserModel::F_PLATFORM_ORG_ID => $org_id,
                        PlatformOrgUserModel::F_PLATFORM_UID    => $uid
                    ]);
                }
                foreach ($class_students as $each) {
                    $account          = $each['account'];
                    $for_platform_uid = PlatformUserModel::getUidByPlatformAndAccount($vip_id, $account);
                    if (empty($for_platform_uid)) {
                        //新增用户
                        PlatformUserModel::$fields_verifies = [];
                        PlatformUserModel::instance()->insert([
                            PlatformUserModel::F_PLATFORM_ID => $vip_id,
                            PlatformUserModel::F_NAME        => $each['name'],
                            PlatformUserModel::F_ACCOUNT     => $account,
                            PlatformUserModel::F_PASSWORD    => substr($account, -6),
                            PlatformUserModel::F_MOBILE      => $account,
                        ]);
                        $for_platform_uid = PlatformUserModel::instance()->getLastInsID();
                    }
                    //量表用户关系
                    $scale_user_info = PlatformScaleUserModel::instance()->where([
                        PlatformScaleUserModel::F_PLATFORM_ID      => $vip_id,
                        PlatformScaleUserModel::F_PLATFORM_ORG_ID  => $org_id,
                        PlatformScaleUserModel::F_SCALE_ID         => $scale_teacher_id,
                        PlatformScaleUserModel::F_PLATFORM_UID     => $uid,
                        PlatformScaleUserModel::F_FOR_SCALE_ID     => $for_scale_id,
                        PlatformScaleUserModel::F_FOR_PLATFORM_UID => $for_platform_uid
                    ])->find();
                    if (empty($scale_user_info)) {
                        //新增
                        PlatformScaleUserModel::instance()->insert([
                            PlatformScaleUserModel::F_PLATFORM_ID       => $vip_id,
                            PlatformScaleUserModel::F_PLATFORM_ORG_ID   => $org_id,
                            PlatformScaleUserModel::F_SCALE_ID          => $scale_teacher_id,
                            PlatformScaleUserModel::F_PLATFORM_UID      => $uid,
                            PlatformScaleUserModel::F_SCALE_NAME_APPEND => $each['name'],
                            PlatformScaleUserModel::F_FOR_SCALE_ID      => $for_scale_id,
                            PlatformScaleUserModel::F_FOR_PLATFORM_UID  => $for_platform_uid
                        ]);
                    }
                }
            }
        }
        $file    = ClExcel::exportToCsv(['年级', '班级', '账号', '密码', '姓名'], $csv_teacher);
        $to_file = DOCUMENT_ROOT_PATH . '/shzq_techers.csv';
        rename($file, $to_file);
        echo_info($to_file);
    }

}