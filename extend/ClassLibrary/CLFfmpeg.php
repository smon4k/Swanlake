<?php
  	namespace ClassLibrary;
  	
  	class CLFfmpeg
  	{

        private static $video_path = "upload"; //上传目录

        //主要颜色
        private static $primaryColour = ['FFFFFF'];
        //边框颜色
        private static $OutlineColour = '000000';
        //字体
        private static $fontName = ["Muyao\-Softbrush","YRDZST"];
        
        /**
         * 拼接视频
         *  视频地址为 url
         * @param $url
         * @param string $object_name
         * @return array
         * @author: xiao
         * @Date: 2021/4/15  14:04
         */
        public static function videoConcatUrl($url,$object_name = '')
        {
            if (!is_array($url) || empty($url)){
                return array(
                    'code' => -1,
                    'msg' => 'url 非数组或为空'
                );
            }
            $str = '';
            foreach ($url as $key => $val){
                $str .= "file '".$val."'  \r\n";
            }
            //写入文件
            $temp_txt_name = 'temp_video_file_'.rand(1000,9999).'.txt';
            $fp = @fopen(self::$video_path.$temp_txt_name,"w+");
            @fputs($fp,$str);
            @fclose();
            if (!file_exists(self::$video_path.$temp_txt_name)){
                return array(
                    'code' => -1,
                    'msg' => 'url文件写入失败'
                );
            }
            //执行拼接命令
            # -safe 0 -protocol_whitelist "file,http,https,rtp,udp,tcp,tls"
            $ffmpeg = "/usr/local/bin/ffmpeg";
            $ffmpeg_exec = $ffmpeg .' -f concat -safe 0  -i '.self::$video_pathh.$temp_txt_name.' -c copy '.self::$video_pathh.$object_name.'';
            exec($ffmpeg_exec,$output,$code);
            if ($code != 0){
                return array(
                    'code' => -1,
                    'msg' => '视频拼接失败'
                );
            }
            //删除临时文件
            if (file_exists(self::$video_pathh.$temp_txt_name)){
                @unlink(self::$video_pathh.$temp_txt_name);
            }
            $this->data['data'] = [
                'video_url'  =>  self::$video_pathh.$object_name
            ];
            return $this->data;
        }

        /**
         * 字幕添加
         * @param $video_url
         * @param $video_srt_url
         * @param $object_name
         * @param string $ratio 分辨率
         * @return array
         * @author: xiao
         * @Date: 2021/4/15  14:41
         */
        public static function videoSubtitles($video_url,$video_srt_url,$object_name,$ratio='720x1280')
        {
            if (empty($video_url) || empty($video_srt_url) || empty($object_name)){
                return array(
                    'code' => -1,
                    'msg' => '参数为空'
                );
            }
            //下载字幕文件到本地
            $file = file_get_contents($video_srt_url);
            $srt_name = date('YmdHis').'_'.rand(1000,9999).'_'.'srt'.'.srt';
            $srt_path = self::$video_path.$srt_name;
            file_put_contents($srt_path,$file);

            /**
             * 设置字幕样式
             */
            //字体
            $font_name = self::$fontName[rand(0,(count(self::$fontName)-1))];
            $out_line_color = self::$OutlineColour;
            //主要颜色
            $primary_color = self::$primaryColour[rand(0,count(self::$primaryColour)-1)];
            $primary_color = '&H'.$primary_color;
            $out_line_color = '&H'.$out_line_color;
            $margin_v = rand(70,85);
            $font_size = rand(12,13);

            //根据分辨率判断视频是否为横屏
            if ($ratio == '1280x720' || $ratio == '1920x1080'){
                \Log::error('come in ratio');
                $font_size = rand(18,21);
                $margin_v = rand(5,15);
            }
            $ffmpeg = "/usr/local/bin/ffmpeg";
            $force_style='Fontname='.$font_name.'\,Fontsize='.$font_size.'\,MarginV='.$margin_v.'\,PrimaryColour='.$primary_color.'\,OutlineColour='.$out_line_color.'\,Alignment=2';
            $ffmpeg_exec = $ffmpeg."  -i ".$video_url." -threads 5 -preset ultrafast  -vf subtitles=".$srt_path.":force_style='".$force_style."'    -y ".self::$video_path.$object_name." ";
            exec($ffmpeg_exec,$output,$code);
            if ($code != 0){
                return array(
                    'code' => -1,
                    'msg' => '字幕插入失败'
                );
            }
            return array(
                'video_url' => self::$video_path.$object_name
            );

            if (file_exists($srt_path)){
                @unlink($srt_path);
            }
            return $this->data;
        }

        /**
         * 压缩视频
         * @param $video_url
         * @param string $object_name
         * @param string $code_rate
         * @param string $ratio 码率
         * @return array
         * @author: xiao
         * @Date: 2021/4/27  14:49
         */
        public static function videoCompress($video_url,$object_name = '',$code_rate = '500k',$ratio='1280x720' )
        {
            if (empty($video_url)){
                $this->data['code'] = -1;
                $this->data['msg'] = '参数为空';
                return $this->data;
            }
            $ffmpeg = "/usr/local/bin/ffmpeg";
    //        $ffmpeg_exec = '/usr/local/ffmpeg/bin/ffmpeg -i '.$video_url.' -threads 2 -preset ultrafast -b:v '.$code_rate.' -s '.$width.'x'.$height.' '.self::$video_path.$object_name.' ';
            $ffmpeg_exec = $ffmpeg.' -i '.$video_url.' -threads 2 -preset ultrafast -b:v '.$code_rate.' '.self::$video_path.$object_name.' ';
            exec($ffmpeg_exec,$o,$code);

            if ($code != 0){
                return array(
                    'code' => -1,
                    'msg' => '视频压缩失败'
                );
            }
            return array(
                'video_url' => self::$video_path.$object_name
            );
        }

        /**
         * 设置关键帧
         * @param $video_url
         * @param $object_name
         * @return array
         * @author: xiao
         * @Date: 2021/4/27  17:05
         */
        public static function videoSetKey($video_url, $object_name)
        {
            $ffmpeg = "/usr/local/bin/ffmpeg";
            $ffmpeg_exec = $ffmpeg.' -i '.$video_url.' -threads 2 -preset ultrafast -g 1 -keyint_min 2  '.self::$video_path.$object_name.' ';
            exec($ffmpeg_exec,$o,$code);

            if ($code != 0){
                return array(
                    'code' => -1,
                    'msg' => '关键帧设置失败'
                );
            }
            return array(
                'video_url' => self::$video_path.$object_name
            );
            return $this->data;
        }

        /**
         * 获取视频信息
         * @param $video_url
         * @return array|string[]
         * @author: xiao
         * @Date: 2021/6/10  15:54
         */
        public static function getVideoInfo($video_url)
        {
            $arr = [];
            $ffmpeg = "/usr/local/bin/ffprobe";
            $ffmpeg_exec = $ffmpeg.' -show_format -show_streams -v quiet '.$video_url;
    //        $video_info = shell_exec($ffmpeg_exec);
            exec($ffmpeg_exec,$info,$code);
            // p($info);
            if (empty($info)){
                return array(
                    'code' => -1,
                    'msg' => '获取视频信息失败'
                );
            }
            foreach ($info as $key => $val) {
                $arrs = explode('=',$val);
                // p($arrs);
                if(isset($arrs[1])) {
                    //分辨率
                    if (isset($arrs[0]) && $arrs[0] === 'ratio'){
                        $arr['ratio'] = isset($arrs[1])?trim($arrs[1]):'1280x720';
                    }
                    //长款
                    if (isset($arrs[0]) && $arrs[0] === 'width'){
                        $arr['width'] = isset($arrs[1]) ? $arrs[1] : 0;
                    }
                    if (isset($arrs[0]) && $arrs[0] === 'height'){
                        $arr['height'] = isset($arrs[1]) ? $arrs[1] : 0;
                    }
                    //大小
                    if (isset($arrs[0]) && $arrs[0] === 'size'){
                        $arr['size'] = isset($arrs[1]) ? $arrs[1] : 0;
                    }
                }
            }

            return $arr;
        }

        /**
         * 截取视频第一帧
         *
         * @param  $file   视频文件
         * @param  $time    第几帧
         * @param  $size    截图尺寸
         */
        public static function getVideoCover($file, $time=1, $size='348*470', $cover='') {
            $time = $time ? $time : '1';      //默认截取第一秒第一帧
            $size = $size ? $size : '348*470';
            // $fileName = getImgName();

            //临时视频路径，生成截图后删除
            $ffmpeg = "/usr/local/bin/ffmpeg";
            // $bool = move_uploaded_file($file, $tempfiles);
            $str = $ffmpeg." -i ".$file." -y -f mjpeg -ss ".$time." -t 0.001 -s $size $cover";
            exec($str,$out,$status);
            return $cover;
        }
    }