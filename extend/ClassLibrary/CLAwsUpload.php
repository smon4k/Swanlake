<?php
  	namespace ClassLibrary;
    
    use think\Loader;
    use Aws\S3\S3Client;
    use Aws\Exception\AwsException;
    use Aws\Exception\MultipartUploadException;
    use Aws\S3\MultipartUploader;
  	
  	class CLAwsUpload
  	{
         /**
         * Aws S3 在存储桶中放置对象
         * @author qinlh
         * @since 2022-02-25
         */
        public static function AwsS3PutObject($file_url='', $file_name='', $file_size='', $save_file='')
        {
            //判断上传文件是否存在
            // if (!isset($_FILES['file'])) {
            //     $response = json_return([
            //         'message' => '上传文件不存在'
            //     ]);
            //     $response->send();
            //     exit;
            // }
            Loader::import('aws.aws', EXTEND_PATH, '-autoloader.php');
            try {
                $s3Client = new S3Client([
                    'profile' => 'default',
                    'region' => 'us-east-1',
                    'version' => '2006-03-01'
                ]);
                if (!$save_file || $save_file == '') {
                    if(config('env') === 'dev') {
                        $root_path = sprintf('h2oMediaDev/picture/%s/', date('Y/m/d'));
                    } else {
                        $root_path = sprintf('h2oMedia/picture/%s/', date('Y/m/d'));
                    }
                    $save_file = $root_path . date('His') . '_' . (ClString::toCrc32($file_size . $file_name)) . ClFile::getSuffix($file_name);
                }
                $result = $s3Client->putObject([
                    'Bucket' =>'h2o-finance-images',
                    'Key' => $save_file,
                    'SourceFile' => $file_url,
                ]);
                $fileUrl = $result->get('ObjectURL');
                if ($fileUrl) {
                    return [
                        'result' => true,
                        'msg'    => '上传成功',
                        'file'   => $fileUrl
                    ];
                }
            } catch (S3Exception $e) {
                return [
                    'result' => false,
                    'msg'    => $e->getMessage()
                ];
            }
        }
        
        /**
         * Aws S3 在删除桶中放置对象
         * @author qinlh
         * @since 2022-02-25
         */
        public static function AwsS3DeleateObject($file_url='')
        {
            if (!$file_url || $file_url == '') {
                return false;
            }
            Loader::import('aws.aws', EXTEND_PATH, '-autoloader.php');
            try {
                $s3Client = new S3Client([
                    'region' => 'us-east-1',
                    'version' => '2006-03-01'
                ]);
                $file_url_array = parse_url($file_url);
                $key_path = substr($file_url_array['path'], 1);
                // p($key_path);
                $result = $s3Client->deleteObject([
                    'Bucket' => 'h2o-finance-images',
                    'Key' => $key_path,
                ]);
                return true;
            } catch (S3Exception $e) {
                return false;
            }
        }

        /**
         * Aws S3 分段上传
         * @author qinlh
         * @since 2022-02-25
         */
        public static function AwsS3MultipartUpload($file_url='', $file_name='', $file_size='', $save_file='')
        {
            //设置超时
            set_time_limit(0);
            Loader::import('aws.aws', EXTEND_PATH, '-autoloader.php');
            try {
                $s3Client = new S3Client([
                    'profile' => 'default',
                    'region' => 'us-east-1',
                    'version' => '2006-03-01'
                ]);
                if (!$save_file || $save_file == '') {
                    // if(getEnvs() === 'dev') {
                        $root_path = sprintf('h2oMediaDev/picture/%s/', date('Y/m/d'));
                    // } else {
                    //     $root_path = sprintf('h2oMedia/picture/%s/', date('Y/m/d'));
                    // }
                    $save_file = $root_path . date('His') . '_' . (ClString::toCrc32($file_size . $file_name)) . ClFile::getSuffix($file_name);
                }
                $source = fopen($file_url, 'rb');
                $uploader = new MultipartUploader($s3Client, $source, [
                    'bucket' => 'h2o-finance-images',
                    'key' => $save_file,
                    'before_initiate' => function (\Aws\Command $command) {
                        // $command is a CreateMultipartUpload operation
                        $command['CacheControl'] = 'max-age=3600';
                    },
                    'before_upload' => function (\Aws\Command $command) {
                        // $command is an UploadPart operation
                        $command['RequestPayer'] = 'requester';
                    },
                    'before_complete' => function (\Aws\Command $command) {
                        // $command is a CompleteMultipartUpload operation
                        $command['RequestPayer'] = 'requester';
                    },
                    'concurrency' => 1
                ]);
                // p($uploader);
                // $fileUrl = $result->get('ObjectURL');
                try {
                    $result = $uploader->upload();
                    //上传成功--返回上传后的地址
                    $data = [
                        'type' => '1',
                        'data' => urldecode($result['ObjectURL'])
                    ];
                } catch (Aws\Exception\MultipartUploadException $e) {
                    //上传失败--返回错误信息
                    $uploader =  new Aws\S3\MultipartUploader($s3, $source, [
                        'state' => $e->getState(),
                    ]);
                    $data = [
                        'type' => '0',
                        'data' =>  $e->getMessage()
                    ];
                }
                return $data;
            } catch (S3Exception $e) {
                return [
                    'result' => false,
                    'msg'    => $e->getMessage()
                ];
            }
        }
    }