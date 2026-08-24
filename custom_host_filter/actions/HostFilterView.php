<?php

namespace Modules\CustomHostFilter\Actions;

use CController;
use CControllerResponseData;
use API;

class HostFilterView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'action'          => 'string',
			'groupids'        => 'array_db hosts_groups.groupid',
			'hostids'         => 'array_db hosts.hostid',
			'ip'              => 'string',
			'port'            => 'string',
			'status'          => 'in -1,0,1',
			'interface_type'  => 'in -1,1,2,3,4,0,99', // 99 代表“无接口”
			'interface_state' => 'in -1,1,2,0,3',    // 3 代表“无接口”
			'filter_set'      => 'string',
			'filter_rst'      => 'string'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_rst')) {
			$filter = [
				'groupids'        => [],
				'hostids'         => [],
				'ip'              => '',
				'port'            => '',
				'status'          => -1,
				'interface_type'  => -1,
				'interface_state' => -1
			];
		} else {
			$filter = [
				'groupids'        => $this->getInput('groupids', []),
				'hostids'         => $this->getInput('hostids', []),
				'ip'              => trim($this->getInput('ip', '')),
				'port'            => trim($this->getInput('port', '')),
				'status'          => (int)$this->getInput('status', -1),
				'interface_type'  => (int)$this->getInput('interface_type', -1),
				'interface_state' => (int)$this->getInput('interface_state', -1)
			];
		}

		$options = [
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectInterfaces' => ['interfaceid', 'type', 'main', 'available', 'ip', 'port', 'dns', 'useip'],
			'selectHostGroups' => ['groupid', 'name'],
			'preservekeys' => true
		];

		if (!empty($filter['groupids'])) {
			$options['groupids'] = $filter['groupids'];
		}

		if (!empty($filter['hostids'])) {
			$options['hostids'] = $filter['hostids'];
		}

		if ($filter['status'] !== -1) {
			$options['filter']['status'] = $filter['status'];
		}

		$raw_hosts = API::Host()->get($options);
		$filtered_hosts = [];

		if (is_array($raw_hosts)) {
			foreach ($raw_hosts as $hostid => $host) {
				$interfaces = $host['interfaces'] ?? [];
				$has_no_interface = empty($interfaces);

				// 1. 如果用户专门选择了“无接口”筛选（状态设为3 或 类型设为99）
				if ($filter['interface_state'] === 3 || $filter['interface_type'] === 99) {
					if (!$has_no_interface) {
						continue; // 有接口的主机排除
					}
					// 如果输入了 IP 或端口，因为无接口，无法匹配
					if ($filter['ip'] !== '' || $filter['port'] !== '') {
						continue;
					}
					$filtered_hosts[$hostid] = $host;
					continue;
				}

				// 2. 如果主机没有接口，但筛选条件要求了具体的接口类型/接口状态/IP/端口
				if ($has_no_interface) {
					if ($filter['interface_state'] !== -1 || $filter['interface_type'] !== -1 || $filter['ip'] !== '' || $filter['port'] !== '') {
						continue; // 条件不匹配，排除
					}
					$filtered_hosts[$hostid] = $host;
					continue;
				}

				// 3. 主机包含接口，进行具体的接口匹配
				$has_matching_interface = false;

				foreach ($interfaces as $interface) {
					if ($filter['ip'] !== '' && mb_strpos($interface['ip'], $filter['ip']) === false) {
						continue;
					}

					if ($filter['port'] !== '' && mb_strpos($interface['port'], $filter['port']) === false) {
						continue;
					}

					if ($filter['interface_type'] !== -1) {
						if ($filter['interface_type'] == 0) {
							if (in_array((int)$interface['type'], [1, 2, 3, 4])) {
								continue;
							}
						} elseif ((int)$interface['type'] !== $filter['interface_type']) {
							continue;
						}
					}

					if ($filter['interface_state'] !== -1) {
						if ((int)$interface['available'] !== $filter['interface_state']) {
							continue;
						}
					}

					$has_matching_interface = true;
					break;
				}

				if ($has_matching_interface) {
					$filtered_hosts[$hostid] = $host;
				}
			}
		}

		$multiselect_groups = [];
		if (!empty($filter['groupids'])) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids']
			]);
			if (is_array($groups)) {
				foreach ($groups as $group) {
					$multiselect_groups[] = ['id' => $group['groupid'], 'name' => $group['name']];
				}
			}
		}

		$multiselect_hosts = [];
		if (!empty($filter['hostids'])) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]);
			if (is_array($hosts)) {
				foreach ($hosts as $h) {
					$multiselect_hosts[] = ['id' => $h['hostid'], 'name' => $h['name']];
				}
			}
		}

		$data = [
			'filter'             => $filter,
			'multiselect_groups' => $multiselect_groups,
			'multiselect_hosts'  => $multiselect_hosts,
			'hosts'              => $filtered_hosts
		];

		$response = new CControllerResponseData($data);
		$response->setTitle('高级主机筛选');
		$this->setResponse($response);
	}
}
