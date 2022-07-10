<?php
  	namespace ClassLibrary;
  	
  	ini_set('memory_limit',-1); //要有足够大的内存来允许处理上传的文件
  	set_time_limit(0); //防止超时
  	
  	class CLUpload
  	{	
  		private static $filepath = "upload/h2o-media"; //上传目录
  		private static $tmpPath = "upload/temp"; //PHP文件临时目录
  		// private static $blobNum; //第几个文件块
  		// private static $totalBlobNum; //文件块总数
  		// private static $fileName; //文件名
  	
  		// public function __construct(){
  		//     self::$tmpPath = "/upload/temp";
  		//     // $this->blobNum = $blobNum;
  		//     // $this->totalBlobNum = $totalBlobNum;
  		//     // $this->fileName = $fileName;
  		//     self::$filepath = "/upload/h2o-media";
  		//     // $this->moveFile();
  		//     // $this->fileMerge();
  		// }

          /**
          * 处理客户端上传
          * @param string $file_save_dir 文件保存绝对路径或相对路径，如果是相对路径，则会自动拼接成绝对路径
          * @return array
          */
         public static function uploadDealClient()
         {
            //判断上传文件是否存在
            if (!isset($_FILES['file'])) {
                $response = json_return([
                    'message' => '上传文件不存在'
                ]);
                $response->send();
                exit;
            }
            if (!empty($file_save_dir) && strpos($file_save_dir, DOCUMENT_ROOT_PATH) === false) {
                $file_save_dir = DOCUMENT_ROOT_PATH . '/' . ltrim($file_save_dir, '/');
            }
            $file_size = input('post.file_size', $_FILES['file']['size'], 'trim');
            $file_name = input('post.file_name', $_FILES['file']['name'], 'trim,strval');
            $tmp_name = input('post.tmp_name', $_FILES['file']['tmp_name'], 'trim,strval');
            
            $blobNum     = input('post.chunk', 'no', 'trim');
            $totalBlobNum = input('post.chunks/d', null, 'trim');
            // $root_path = sprintf(DOCUMENT_ROOT_PATH . '/upload/%s/', date('Y/m/d'));
            self::moveFile($file_name, $tmp_name, $blobNum);
            self::fileMerge($file_name, $blobNum, $totalBlobNum);
            $data = [];
            if($blobNum == $totalBlobNum){
                if(file_exists(self::$filepath.'/'. $file_name)){
                  $data['code'] = 2;
                  $data['msg'] = 'success';
                  $data['file_path'] = $file_name;
                  $data['file_name'] = substr($file_name,0,strrpos($file_name, '.'));
                  $data['file_extension'] = substr(strrchr($file_name, '.'), 1);
                }
            }else{
                if(file_exists(self::$filepath.'/'. $file_name.'__'.$blobNum)){
                  $data['code'] = 1;
                  $data['msg'] = 'waiting';
                  $data['file_path'] = '';
                }
            }
            return $data;
         }
  	
  	  	private static function fileMerge($fileName='', $blobNum=0, $totalBlobNum=0){
  		    if($blobNum == $totalBlobNum) {
  		      // 此处文件不应该拼接，应该是追加入内
  		      // 实测修改后php占用很少的内存也可以实现大文件的上传和拼接操作
  		      for($i=1; $i<= $totalBlobNum; $i++){
  		        $blob = '';
  		        $blob = file_get_contents(self::$filepath.'/'. $fileName.'__'.$i);
  		        file_put_contents(self::$filepath.'/'. $fileName, $blob, FILE_APPEND );
  		        unset($blob);
  		      }
  		      self::deleteFileBlob($fileName, $totalBlobNum);
  		    }
  		}
  	   
  	  	//删除文件块
  	  	private static function deleteFileBlob($fileName, $totalBlobNum){
  		    for($i=1; $i<= $totalBlobNum; $i++){
  		      @unlink(self::$filepath.'/'. $fileName.'__'.$i);
  		    }
  	  	}
  	   
        // 首先上传到临时文件
  	 	private static function moveFile($fileName='', $tmp_name='', $blobNum=0){
            $root_path = sprintf(DOCUMENT_ROOT_PATH . self::$filepath);
            // $tmpPath = sprintf(self::$tmpPath);
            if (!is_dir($root_path)) {
                ClFile::dirCreate($root_path);
            }
  		    $file_name = $root_path .'/'. $fileName.'__'.$blobNum;
  		    move_uploaded_file($tmp_name, $file_name);
  	  	}

    /**
     * 无限新建文件夹，支持linux和windows目录
     * @param string $absolute_file_name 待创建的文件，例如：C:\workspace\PhpStorm\CC\WebSite\Application\Runtime\Logs/Home//DDD/14_08_07.log
     * @param bool $is_file 传入的是否是文件，如果是文件则不进行文件、文件夹的自动判断
     * @return string
     */
    public static function dirCreate($absolute_file_name, $is_file = false)
    {
        $file_name = trim(str_replace('\\', '/', $absolute_file_name), '/');
        $dir_array = explode('/', $file_name);
        if (ClSystem::isWin()) {
            $dir_str_pre = '';
        } else {
            $dir_str_pre = '/';
        }
        if (empty($dir_array[0])) {
            //去除为空的数据
            array_shift($dir_array);
        }
        //判断最后一个是文件还是文件夹
        if ($is_file) {
            $min_limit = 1;
        } else {
            $min_limit = empty(self::getSuffix($file_name)) ? 0 : 1;
        }
        if ($min_limit > 0) {
            array_pop($dir_array);
        }
        //赋值
        $temp_dir_array = $dir_array;
        while (is_array($temp_dir_array) && count($temp_dir_array) > $min_limit) {
            $dir_str = $dir_str_pre . implode('/', $temp_dir_array);
            if (is_dir($dir_str)) {
                break;
            }
            array_pop($temp_dir_array);
        }
        //去除相同目录
        $dir_array = $dir_str_pre . implode('/', $dir_array);
        $dir_array = str_replace($dir_str, '', $dir_array);
        $dir_array = explode('/', trim($dir_array, '/'));
        //第一个目录不判断
        while (is_array($dir_array) && !empty($dir_array)) {
            $dir_str .= '/' . $dir_array[0];
            if (!is_dir($dir_str)) {
                mkdir($dir_str, 0777);
                if (self::checkChmod($dir_str, 0777)) {
                    //修改权限，root用户创建可能是0755
                    chmod($dir_str, 0777);
                }
            }
            array_shift($dir_array);
        }
        return $dir_str;
    }

     /**
     * 获取文件后缀名
     * @param $file
     * @param bool $with_point 是否包含.
     * @return string
     */
    public static function getSuffix($file, $with_point = true)
    {
        $suffix = isset(pathinfo($file)['extension']) ? strtolower(pathinfo($file)['extension']) : '';
        if ($with_point && !empty($suffix)) {
            $suffix = '.' . $suffix;
        }
        return $suffix;
    }
  	  
}
