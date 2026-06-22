<?php
// +----------------------------------------------------------------------
// | 文件说明：交易对配置
// +----------------------------------------------------------------------
namespace app\grid\model;

class TradeSymbol extends Base
{
    protected $name = 'trade_symbols';

    /**
    * 获取启用的交易对列表
    * @return array
    */
    public static function getActiveTradeSymbols()
    {
        $lists = self::where('status', 1)
            ->order('sort asc,id asc')
            ->select()
            ->toArray();

        return is_array($lists) ? $lists : [];
    }
}
