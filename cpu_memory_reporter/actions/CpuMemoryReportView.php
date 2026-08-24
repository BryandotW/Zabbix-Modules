<?php

class CpuMemoryReportView extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'groupids'     => 'array_id',
			'hostids'      => 'array_id',
			'time_range'   => 'in 300,900,1800,3600,10800,21600,43200,86400,172800,259200,345600,432000,518400,604800,1296000,2592000,5184000,7776000,10368000,12960000,15552000',
			'sort_field'   => 'in hostid,cpu_util_max,cpu_util_avg,cpu_util_med,load1_max,load1_avg,load1_med,load5_max,load5_avg,load5_med,load15_max,load15_avg,load15_med,mem_util_max,mem_util_avg,mem_util_med',
			'sort_order'   => 'in DESC,ASC',
			'columns'      => 'array',
			'filter_apply' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode([
				'error' => [
					'title' => _('Invalid input parameters')
				]
			])]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (class_exists('CRoleHelper')) {
			return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
		}
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction() {
		@ini_set('memory_limit', '4096M');
		@set_time_limit(600);

		$all_columns = [
			'cpu_util_max', 'cpu_util_avg', 'cpu_util_med',
			'load1_max', 'load1_avg', 'load1_med',
			'load5_max', 'load5_avg', 'load5_med',
			'load15_max', 'load15_avg', 'load15_med',
			'mem_util_max', 'mem_util_avg', 'mem_util_med'
		];

		$groupids   = $this->getInput('groupids', []);
		$hostids    = $this->getInput('hostids', []);
		$time_range = (int) $this->getInput('time_range', 3600);
		$sort_field = $this->getInput('sort_field', 'hostid');
		$sort_order = $this->getInput('sort_order', 'DESC');
		
		$selected_columns = $this->getInput('columns', []);
		$selected_columns = array_intersect($selected_columns, $all_columns);

		$need_cpu_util = (bool) array_intersect($selected_columns, ['cpu_util_max', 'cpu_util_avg', 'cpu_util_med']);
		$need_load1    = (bool) array_intersect($selected_columns, ['load1_max', 'load1_avg', 'load1_med']);
		$need_load5    = (bool) array_intersect($selected_columns, ['load5_max', 'load5_avg', 'load5_med']);
		$need_load15   = (bool) array_intersect($selected_columns, ['load15_max', 'load15_avg', 'load15_med']);
		$need_mem_util = (bool) array_intersect($selected_columns, ['mem_util_max', 'mem_util_avg', 'mem_util_med']);

		$report_data = [];

		if (!empty($groupids) || !empty($hostids)) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'host', 'name'],
				'selectHostGroups' => ['groupid', 'name'],
				'hostids' => $hostids ? $hostids : null,
				'groupids' => $groupids ? $groupids : null,
				'preservekeys' => true
			]);

			if (!empty($hosts)) {
				$host_ids = array_keys($hosts);
				// 兼容 Zabbix 7.4 最新模板和历史旧版 Windows 模板的 Key
				$search_keys = [
					'system.cpu.num', 
					'vm.memory.size[total]',
					'wmi.get[root/cimv2,"Select NumberOfLogicalProcessors from Win32_ComputerSystem"]',
					'wmi.get[root\cimv2,"Select NumberOfLogicalProcessors from Win32_Processor"]',
					'wmi.get[root\cimv2,"Select NumberOfCores from Win32_Processor"]'
				];

				if ($need_cpu_util) {
					$search_keys[] = 'system.cpu.util';
					$search_keys[] = 'cpu.util';
				}
				if ($need_mem_util) {
					$search_keys[] = 'vm.memory.utilization';
					$search_keys[] = 'vm.memory.util';
					$search_keys[] = 'vm.memory.size[pused]';
				}
				if ($need_load1)  $search_keys[] = 'system.cpu.load[all,avg1]';
				if ($need_load5)  $search_keys[] = 'system.cpu.load[all,avg5]';
				if ($need_load15) $search_keys[] = 'system.cpu.load[all,avg15]';

				$items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'lastvalue'],
					'hostids' => $host_ids,
					'filter' => [
						'key_' => $search_keys
					],
					'webitems' => true
				]);

				$host_items = [];
				$item_ids_float = [];
				$item_ids_uint = [];

				foreach ($items as $item) {
					$hid = $item['hostid'];
					$name = $item['name'];
					$key = $item['key_'];

					if ($need_cpu_util && !isset($host_items[$hid]['cpu_util'])) {
						if (strpos($key, 'system.cpu.util') === 0 || $key === 'cpu.util' || strpos($key, 'perf_counter[\Processor(_Total)\% Processor Time]') === 0 || $name === 'CPU utilization' || $name === 'CPU utilization percentage') {
							$host_items[$hid]['cpu_util'] = $item['itemid'];
						}
					}

					if ($need_mem_util && !isset($host_items[$hid]['mem_util'])) {
						if (strpos($key, 'vm.memory.utilization') === 0 || strpos($key, 'vm.memory.util') === 0 || $key === 'vm.memory.size[pused]' || $name === 'Memory utilization' || $name === 'Memory usage %') {
							$host_items[$hid]['mem_util'] = $item['itemid'];
						}
					}

					if ($need_load1 && !isset($host_items[$hid]['load1'])) {
						if (strpos($key, 'system.cpu.load[all,avg1]') === 0 || $key === 'loadavg1' || $name === 'Load average (1m avg)') {
							$host_items[$hid]['load1'] = $item['itemid'];
						}
					}

					if ($need_load5 && !isset($host_items[$hid]['load5'])) {
						if (strpos($key, 'system.cpu.load[all,avg5]') === 0 || $key === 'loadavg5' || $name === 'Load average (5m avg)') {
							$host_items[$hid]['load5'] = $item['itemid'];
						}
					}

					if ($need_load15 && !isset($host_items[$hid]['load15'])) {
						if (strpos($key, 'system.cpu.load[all,avg15]') === 0 || $key === 'loadavg15' || $name === 'Load average (15m avg)') {
							$host_items[$hid]['load15'] = $item['itemid'];
						}
					}

					if (!isset($host_items[$hid]['cores'])) {
						if (strpos($key, 'system.cpu.num') === 0 || $name === 'Number of cores' || $name === 'Number of CPUs' || (strpos($key, 'wmi.get') !== false && stripos($key, 'Processor') !== false) || (strpos($key, 'wmi.get') !== false && stripos($key, 'ComputerSystem') !== false)) {
							$host_items[$hid]['cores_val'] = ($item['lastvalue'] !== null && $item['lastvalue'] !== '') ? (int)$item['lastvalue'] : '-';
							$host_items[$hid]['cores'] = $item['itemid'];
						}
					}

					if (!isset($host_items[$hid]['mem_total'])) {
						if ($key === 'vm.memory.size[total]' || $name === 'Total memory' || $name === 'Memory total') {
							$host_items[$hid]['mem_total_val'] = ($item['lastvalue'] !== null && $item['lastvalue'] !== '') ? round((float)$item['lastvalue'] / 1073741824, 2) : '-';
							$host_items[$hid]['mem_total'] = $item['itemid'];
						}
					}

					if (in_array($item['itemid'], [
						$host_items[$hid]['cpu_util'] ?? 0,
						$host_items[$hid]['mem_util'] ?? 0,
						$host_items[$hid]['load1'] ?? 0,
						$host_items[$hid]['load5'] ?? 0,
						$host_items[$hid]['load15'] ?? 0
					])) {
						if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
							$item_ids_float[] = $item['itemid'];
						} elseif ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
							$item_ids_uint[] = $item['itemid'];
						}
					}
				}

				$time_now = time();
				$time_from = $time_now - $time_range;
				$history_data = [];
				$trends_data = [];
				
				$use_trends = ($time_range > 129600); // 超过 1.5 天走趋势

				if (!$use_trends) {
					// 历史数据量小，保持原样拉取
					if (!empty($item_ids_float)) {
						$h_float = API::History()->get([
							'output' => ['itemid', 'value'],
							'history' => ITEM_VALUE_TYPE_FLOAT,
							'itemids' => array_values(array_unique($item_ids_float)),
							'time_from' => $time_from
						]);
						foreach ($h_float as $h) {
							$history_data[$h['itemid']][] = (float) $h['value'];
						}
						unset($h_float);
					}

					if (!empty($item_ids_uint)) {
						$h_uint = API::History()->get([
							'output' => ['itemid', 'value'],
							'history' => ITEM_VALUE_TYPE_UINT64,
							'itemids' => array_values(array_unique($item_ids_uint)),
							'time_from' => $time_from
						]);
						foreach ($h_uint as $h) {
							$history_data[$h['itemid']][] = (float) $h['value'];
						}
						unset($h_uint);
					}
				} else {
					// 【核心改进】：长周期趋势数据引入时间滑动窗口，每次最多拉取30天，彻底打破4GB内存瓶颈
					$all_item_ids = array_values(array_unique(array_merge($item_ids_float, $item_ids_uint)));
					
					if (!empty($all_item_ids)) {
						$chunk_size = 2592000; // 30天时间戳步长
						$current_from = $time_from;

						while ($current_from < $time_now) {
							$current_till = min($current_from + $chunk_size, $time_now);
							
							$trends = API::Trend()->get([
								'output' => ['itemid', 'value_min', 'value_avg', 'value_max', 'num'],
								'itemids' => $all_item_ids,
								'time_from' => $current_from,
								'time_till' => $current_till
							]);

							if (is_array($trends)) {
								foreach ($trends as $t) {
									$trends_data[$t['itemid']][] = [
										'min' => (float)$t['value_min'],
										'avg' => (float)$t['value_avg'],
										'max' => (float)$t['value_max'],
										'num' => (int)$t['num']
									];
								}
							}
							
							unset($trends); // 立即释放当前的内存
							$current_from = $current_till;
						}
					}
				}

				$calculate_stats = function($itemid) use ($use_trends, &$history_data, &$trends_data) {
					if ($use_trends) {
						if (!$itemid || empty($trends_data[$itemid])) {
							return ['max' => '-', 'avg' => '-', 'med' => '-'];
						}
						
						$t_rows = $trends_data[$itemid];
						$max_val = -999999;
						$total_sum = 0;
						$total_num = 0;
						$avg_values_for_med = [];

						foreach ($t_rows as $row) {
							if ($row['max'] > $max_val) {
								$max_val = $row['max'];
							}
							$total_sum += $row['avg'] * $row['num'];
							$total_num += $row['num'];
							$avg_values_for_med[] = $row['avg'];
						}

						if ($total_num <= 0) {
							return ['max' => '-', 'avg' => '-', 'med' => '-'];
						}

						$avg_val = $total_sum / $total_num;

						sort($avg_values_for_med);
						$count = count($avg_values_for_med);
						$mid = floor($count / 2);
						$med_val = ($count % 2 == 0) ? ($avg_values_for_med[$mid - 1] + $avg_values_for_med[$mid]) / 2 : $avg_values_for_med[$mid];

						return [
							'max' => number_format($max_val, 2, '.', ''),
							'avg' => number_format($avg_val, 2, '.', '') . '*',
							'med' => number_format($med_val, 2, '.', '') . '*'
						];
					}

					if (!$itemid || empty($history_data[$itemid])) {
						return ['max' => '-', 'avg' => '-', 'med' => '-'];
					}
					
					$values = $history_data[$itemid];
					$max = max($values);
					$avg = array_sum($values) / count($values);

					sort($values);
					$count = count($values);
					$mid = floor($count / 2);
					$med = ($count % 2 == 0) ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];

					return [
						'max' => number_format($max, 2, '.', ''),
						'avg' => number_format($avg, 2, '.', ''),
						'med' => number_format($med, 2, '.', '')
					];
				};

				foreach ($hosts as $hostid => $host) {
					$group_names = [];
					if (isset($host['hostgroups'])) {
						foreach ($host['hostgroups'] as $g) {
							$group_names[] = $g['name'];
						}
					}

					$h_map = $host_items[$hostid] ?? [];

					$cpu_stats   = $need_cpu_util ? $calculate_stats($h_map['cpu_util'] ?? null) : ['max'=>'-','avg'=>'-','med'=>'-'];
					$load1_stats = $need_load1    ? $calculate_stats($h_map['load1'] ?? null)    : ['max'=>'-','avg'=>'-','med'=>'-'];
					$load5_stats = $need_load5    ? $calculate_stats($h_map['load5'] ?? null)    : ['max'=>'-','avg'=>'-','med'=>'-'];
					$load15_stats= $need_load15   ? $calculate_stats($h_map['load15'] ?? null)   : ['max'=>'-','avg'=>'-','med'=>'-'];
					$mem_stats   = $need_mem_util ? $calculate_stats($h_map['mem_util'] ?? null)  : ['max'=>'-','avg'=>'-','med'=>'-'];

					$cores = $h_map['cores_val'] ?? '-';
					$mem_total_gb = $h_map['mem_total_val'] ?? '-';

					$report_data[] = [
						'hostid'       => $hostid,
						'name'         => $host['name'],
						'host'         => $host['host'],
						'groups'       => implode(', ', $group_names),
						'cores'        => $cores,
						'mem_total_gb' => $mem_total_gb,
						'cpu_util_max' => $cpu_stats['max'],
						'cpu_util_avg' => $cpu_stats['avg'],
						'cpu_util_med' => $cpu_stats['med'],
						'load1_max'    => $load1_stats['max'],
						'load1_avg'    => $load1_stats['avg'],
						'load1_med'    => $load1_stats['med'],
						'load5_max'    => $load5_stats['max'],
						'load5_avg'    => $load5_stats['avg'],
						'load5_med'    => $load5_stats['med'],
						'load15_max'   => $load15_stats['max'],
						'load15_avg'   => $load15_stats['avg'],
						'load15_med'   => $load15_stats['med'],
						'mem_util_max' => $mem_stats['max'],
						'mem_util_avg' => $mem_stats['avg'],
						'mem_util_med' => $mem_stats['med']
					];
				}

				unset($history_data);
				unset($trends_data);

				if ($sort_field !== 'hostid' && !in_array($sort_field, $selected_columns)) {
					$sort_field = 'hostid';
				}

				usort($report_data, function($a, $b) use ($sort_field, $sort_order) {
					$val_a = isset($a[$sort_field]) ? $a[$sort_field] : '-';
					$val_b = isset($b[$sort_field]) ? $b[$sort_field] : '-';

					if (is_string($val_a)) {
						$val_a = rtrim($val_a, '*');
					}
					if (is_string($val_b)) {
						$val_b = rtrim($val_b, '*');
					}

					if ($val_a === '-' && $val_b === '-') return 0;
					if ($val_a === '-') return 1;
					if ($val_b === '-') return -1;

					if (is_numeric($val_a) && is_numeric($val_b)) {
						$cmp = ((float)$val_a < (float)$val_b) ? -1 : 1;
					} else {
						$cmp = strcmp((string)$val_a, (string)$val_b);
					}

					return ($sort_order === 'DESC') ? -$cmp : $cmp;
				});
			}
		}

		$multiselect_groups = [];
		if (!empty($groupids)) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids
			]);
			foreach ($groups as $group) {
				$multiselect_groups[] = [
					'id' => $group['groupid'],
					'name' => $group['name']
				];
			}
		}

		$multiselect_hosts = [];
		if (!empty($hostids)) {
			$hosts_ms = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $hostids
			]);
			foreach ($hosts_ms as $host) {
				$multiselect_hosts[] = [
					'id' => $host['hostid'],
					'name' => $host['name']
				];
			}
		}

		$data = [
			'page_title' => _('CPU/内存性能监测'),
			'filter' => [
				'groupids'         => $multiselect_groups,
				'hostids'          => $multiselect_hosts,
				'time_range'       => $time_range,
				'sort_field'       => $sort_field,
				'sort_order'       => $sort_order,
				'selected_columns' => $selected_columns
			],
			'report_data' => $report_data
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}