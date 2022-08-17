#!/bin/bash - 
#===============================================================================
#
#          FILE: deploy.dev.sh
# 
#         USAGE: ./deploy.dev.sh 
# 
#   DESCRIPTION: 
# 
#       OPTIONS: ---
#  REQUIREMENTS: ---
#          BUGS: ---
#         NOTES: ---
#        AUTHOR: Zengpu Zhang (zengpu), zengpu.zhang@ronghai.com
#  ORGANIZATION: XWG
#       CREATED: 11/10/2017 12:44:58 AM EST
#      REVISION:  ---
#===============================================================================

set -o nounset                              # Treat unset variables as an error


docker build -t admin_web .
docker tag admin_web registry.cn-hangzhou.aliyuncs.com/sshangce/project_abs_admin
docker push registry.cn-hangzhou.aliyuncs.com/sshangce/project_abs_admin
docker service rm admin_web

docker pull registry.cn-hangzhou.aliyuncs.com/sshangce/project_abs_admin
docker service create --name admin_web \
-e ENVIRONMENT=development \
-e DEVELOP_MYSQL_SERVER=192.168.199.231 \
-e DEVELOP_MYSQL_PORT=3306 \
-e DEVELOP_MYSQL_USER=root \
-e DEVELOP_MYSQL_PASS=123456 \
-e DEVELOP_MYSQL_DB=ssc_bdf_new \
-e DEVELOP_REDIS_SERVER=192.168.199.231 \
--publish 82:80 registry.cn-hangzhou.aliyuncs.com/sshangce/project_abs_admin

docker service logs -f admin_web

