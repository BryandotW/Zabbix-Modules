
DROP TABLE IF EXISTS tmp_target_items;

CREATE TEMP TABLE tmp_target_items AS
SELECT itemid, value_type 
FROM items 
WHERE status = 0 
  AND key_ IN (
    'system.cpu.num', 
    'vm.memory.size[total]',
    'wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]',
    'wmi.get[root\cimv2,"Select NumberOfLogicalProcessors from Win32_Processor"]',
    'wmi.get[root\cimv2,"Select NumberOfCores from Win32_Processor"]',
    'system.cpu.util',
    'cpu.util',
    'vm.memory.utilization',
    'vm.memory.util',
    'vm.memory.size[pused]',
    'system.cpu.load[all,avg1]',
    'system.cpu.load[all,avg5]',
    'system.cpu.load[all,avg15]'
  );

CREATE INDEX idx_tmp_items ON tmp_target_items(itemid);

-- 2. 打印匹配到的监控项数量
SELECT '匹配到的监控项总数' AS check_type, count(*) AS total_count FROM tmp_target_items;

-- 3. 强行加载短周期 <= 1.5 天 (129600s) 精细历史数据
SELECT 
  'history' AS table_name,
  count(*) AS loaded_rows,
  coalesce(sum(value), 0) AS checksum 
FROM history 
WHERE clock >= (extract(epoch from now())::bigint - 129600) 
  AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT 
  'history_uint' AS table_name,
  count(*) AS loaded_rows,
  coalesce(sum(value), 0) AS checksum 
FROM history_uint 
WHERE clock >= (extract(epoch from now())::bigint - 129600) 
  AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 4. 强行加载长周期 > 1.5 天 趋势数据 (分 6 个 30 天滑动窗口)
-- 窗口 1 (0 ~ 30天)
SELECT '窗口 1 (0-30天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 2592000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 1 (0-30天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 2592000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 窗口 2 (30 ~ 60天)
SELECT '窗口 2 (30-60天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 5184000) AND clock < (extract(epoch from now())::bigint - 2592000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 2 (30-60天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 5184000) AND clock < (extract(epoch from now())::bigint - 2592000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 窗口 3 (60 ~ 90天)
SELECT '窗口 3 (60-90天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 7776000) AND clock < (extract(epoch from now())::bigint - 5184000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 3 (60-90天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 7776000) AND clock < (extract(epoch from now())::bigint - 5184000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 窗口 4 (90 ~ 120天)
SELECT '窗口 4 (90-120天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 10368000) AND clock < (extract(epoch from now())::bigint - 7776000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 4 (90-120天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 10368000) AND clock < (extract(epoch from now())::bigint - 7776000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 窗口 5 (120 ~ 150天)
SELECT '窗口 5 (120-150天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 12960000) AND clock < (extract(epoch from now())::bigint - 10368000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 5 (120-150天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 12960000) AND clock < (extract(epoch from now())::bigint - 10368000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);

-- 窗口 6 (150 ~ 180天)
SELECT '窗口 6 (150-180天)' AS range, 'trends' AS tbl, count(*) AS rows, coalesce(sum(value_avg), 0) AS sum FROM trends WHERE clock >= (extract(epoch from now())::bigint - 15552000) AND clock < (extract(epoch from now())::bigint - 12960000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 0)
UNION ALL
SELECT '窗口 6 (150-180天)', 'trends_uint', count(*), coalesce(sum(value_avg), 0) FROM trends_uint WHERE clock >= (extract(epoch from now())::bigint - 15552000) AND clock < (extract(epoch from now())::bigint - 12960000) AND itemid IN (SELECT itemid FROM tmp_target_items WHERE value_type = 3);