<?php
namespace app\api\controller;

use think\Request;
use think\Controller;
use RequestService\RequestService;

class TestController extends BaseController
{
    public function index()
    {
        return $this->fetch('index');
    }

    public function test()
    {
        $url = "https://api.huobi.pro/v1/common/symbols";
        $data = $this->postPage($url);
        p($data);
    }

    public function postPage($url)
    {
        $response = "";
        $rd=rand(1, 4);
        $proxy='http://127.0.0.1:7890';
        if ($url != "") {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_PROXY, $proxy);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $response = "";
            }
            curl_close($ch);
        }
        return $response;
    }

    public function getScapesTrendingData(Request $request)
    {
        $returnArr = [];
        $url = "https://scape.store/api/scapes?type=trending&networkID=56";
        $data = file_get_contents($url);
        $returnArr = json_decode($data, true);
        return $this->as_json($returnArr);
    }

    public function getScapesData(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $returnArr = [];
        if ($page <= 5) {
            $url = "https://scape.store/api/scapes?type=marketplace_default&networkID=56&page=".$page;
            $data = file_get_contents($url);
            $returnArr = json_decode($data, true);
        }
        // $limit = $request->request('limit', 20, 'intval');
        // p($returnArr);
        return $this->as_json($returnArr);
    }

    public function getScapesMyTrendingData(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $owner = $request->request('owner', '', 'trim');
        $returnArr = [];
        $url = "https://scape.store/api/scapes?type=inventory&networkID=56&owner=".$owner;
        $data = file_get_contents($url);
        $returnArr = json_decode($data, true);
        // $limit = $request->request('limit', 20, 'intval');
        // p($returnArr);
        return $this->as_json($returnArr);
    }

    public function getScapesMyTrendingSaleData(Request $request)
    {
        $page = $request->request('page', 1, 'intval');
        $owner = $request->request('owner', '', 'trim');
        $returnArr = [];
        $url = "https://scape.store/api/scapes?type=sale&networkID=56&owner=".$owner;
        $data = file_get_contents($url);
        $returnArr = json_decode($data, true);
        // $limit = $request->request('limit', 20, 'intval');
        // p($returnArr);
        return $this->as_json($returnArr);
    }
    
    /**
     * ??????????????????
     * @author qinlh
     * @since 2022-03-14
     */
    public function walletAddressSplicing()
    {
        $file_url = "./upload/bsc/bscAddress003.csv";
        $result = self::ReadCsvToArray($file_url);
        $str = "";
        foreach ($result as $key => $val) {
            if ($key > 1) {
                $str .= "'" . $val['A'] . "',";
            }
        }
        $newStr = rtrim($str, ',');
        // p($newStr);
        $myfile = fopen("./upload/bsc/bscAddress003.txt", "w") or die("Unable to open file!");
        fwrite($myfile, $newStr);
        fclose($myfile);
    }

    /**
         * ??????csv?????????????????????
         * @Author sunbobin
         * @param $url
         * @param $sheet_name
         * @return mixed
         */
    private function ReadCsvToArray($file)
    {
        setlocale(LC_ALL, 'zh_CN');//linux???????????????
            $data = null;//????????????????????????
            if (!is_file($file) &&! file_exists($file)) {
                die('????????????');
            }

        $cvs_file = fopen($file, 'r'); //????????????csv????????????
            $i = 0;//??????cvs??????
            while ($file_data = fgetcsv($cvs_file)) {
                $i++;
                // if( $i==1 ) {
                //     continue;//????????????
                // }
                if ($file_data[0] != '') {
                    // $data[$i] = array_map('str_getcsv', file($file_data));
                    $data[$i] = self::LetterStringTitleArray($file_data);
                    // $data[$i] = eval('return ' . mb_convert_encoding(ImportTesting::LetterStringTitleArray($file_data), "GB2312", "UTF-8"));
                }
            }
        fclose($cvs_file);
        return $data;
    }

    /**
     * ???????????????????????????????????????????????????????????????????????????????????????
     * @param  [post] [description]
     * @return [type] [description]
     * @author [qinlh] [WeChat QinLinHui0706]
     */
    public static function LetterStringTitleArray($arr)
    {
        if (count((array)$arr) > 0) {
            $letterArr = [];
            for ($i = 'A', $k = 0; $i <= 'Z'; $i++, $k++) {
                if ($k == count((array)$arr)) {
                    break;
                }
                $letterArr[] = $i;
            }
            $newArray = [];
            foreach ($arr as $key => $val) {
                $newArray[$letterArr[$key]] = $val;
            }
            return $newArray;
        }
    }
}
