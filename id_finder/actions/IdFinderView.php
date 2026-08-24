<?php

namespace Modules\IdFinder\Actions;

use CController;
use CControllerResponseData;
use API;

class IdFinderView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'action'      => 'string',
			'groupids'    => 'array_db hosts_groups.groupid',
			'hostids'     => 'array_db hosts.hostid',
			'itemids'     => 'array_db items.itemid',
			'triggerids'  => 'array_db triggers.triggerid',
			'filter_set'  => 'string',
			'filter_rst'  => 'string'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_rst')) {
			$filter = [
				'groupids'   => [],
				'hostids'    => [],
				'itemids'    => [],
				'triggerids' => []
			];
		} else {
			$filter = [
				'groupids'   => $this->getInput('groupids', []),
				'hostids'    => $this->getInput('hostids', []),
				'itemids'    => $this->getInput('itemids', []),
				'triggerids' => $this->getInput('triggerids', [])
			];
		}

		// 1. 查询选中的主机群组详情
		$result_groups = [];
		if (!empty($filter['groupids'])) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids']
			]);
			if (is_array($groups)) {
				$result_groups = $groups;
			}
		}

		// 2. 查询选中的主机详情
		$result_hosts = [];
		if (!empty($filter['hostids'])) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name', 'host'],
				'hostids' => $filter['hostids']
			]);
			if (is_array($hosts)) {
				$result_hosts = $hosts;
			}
		}

		// 3. 查询选中的监控项 (Item) 详情
		$result_items = [];
		if (!empty($filter['itemids'])) {
			$items = API::Item()->get([
				'output' => ['itemid', 'name', 'key_'],
				'selectHosts' => ['name'],
				'itemids' => $filter['itemids']
			]);
			if (is_array($items)) {
				$result_items = $items;
			}
		}

		// 4. 查询选中的触发器 (Trigger) 详情
		$result_triggers = [];
		if (!empty($filter['triggerids'])) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'priority'],
				'selectHosts' => ['name'],
				'triggerids' => $filter['triggerids']
			]);
			if (is_array($triggers)) {
				$result_triggers = $triggers;
			}
		}

		// 组装 Multiselect 回显数据
		$multiselect_groups = array_map(fn($g) => ['id' => $g['groupid'], 'name' => $g['name']], $result_groups);
		$multiselect_hosts = array_map(fn($h) => ['id' => $h['hostid'], 'name' => $h['name']], $result_hosts);
		$multiselect_items = array_map(fn($i) => ['id' => $i['itemid'], 'name' => $i['name']], $result_items);
		$multiselect_triggers = array_map(fn($t) => ['id' => $t['triggerid'], 'name' => $t['description']], $result_triggers);

		$data = [
			'filter'               => $filter,
			'multiselect_groups'   => $multiselect_groups,
			'multiselect_hosts'    => $multiselect_hosts,
			'multiselect_items'    => $multiselect_items,
			'multiselect_triggers' => $multiselect_triggers,
			'result_groups'        => $result_groups,
			'result_hosts'         => $result_hosts,
			'result_items'         => $result_items,
			'result_triggers'      => $result_triggers
		];

		$response = new CControllerResponseData($data);
		$response->setTitle('ID 查询工具');
		$this->setResponse($response);
	}
}
