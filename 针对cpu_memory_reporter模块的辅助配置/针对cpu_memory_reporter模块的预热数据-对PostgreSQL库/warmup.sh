#!/bin/bash

PGHOST="使用真实IP"
PGPORT="5432"
PGUSER="zabbix"
PGDATABASE="zabbix"

export PGPASSWORD="使用真实密码"

echo "==> 开始执行数据库预热 (调用独立 SQL 脚本)..."

# 通过 -f 参数一次性传入执行，整个 SQL 文件处于同一个 PostgreSQL 连接会话中
psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -f /opt/zabbix_warmup.sql

echo "$(date "+%Y-%m-%d %H:%M:%S"), [完成] 预热脚本执行完毕！"
unset PGPASSWORD







#---------------------------
#最好把psql命令的绝对路径写出来，替换掉psql，防止环境变量没有，提示psql命令不存在。(which psql查看绝对路径)
#--------------------------