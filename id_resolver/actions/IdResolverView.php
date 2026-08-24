<?php

namespace Modules\IdResolver\Actions;

use CController;
use CControllerResponseData;
use API;

class IdResolverView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'action'     => 'string',
			'query_ids'  => 'string',
			'id_type'    => 'string',
			'filter_set' => 'string',
			'filter_rst' => 'string'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_rst')) {
			$query_ids = '';
			$id_type = 'auto';
		} else {
			$query_ids = $this->getInput('query_ids', '');
			$id_type = $this->getInput('id_type', 'auto');
		}

		preg_match_all('/\d+/', $query_ids, $matches);
		$parsed_ids = array_unique(array_filter($matches[0] ?? []));

		$results = [
			'hosts'    => [],
			'groups'   => [],
			'items'    => [],
			'triggers' => []
		];

		if (!empty($parsed_ids)) {
			// 1. 查询主机（获取关联群组）
			if ($id_type === 'auto' || $id_type === 'host') {
				$hosts = API::Host()->get([
					'output' => ['hostid', 'host', 'name', 'status', 'description'],
					'selectInterfaces' => ['ip', 'port', 'main', 'type'],
					'selectHostGroups' => ['groupid', 'name'],
					'hostids' => $parsed_ids
				]);
				if (is_array($hosts)) {
					$results['hosts'] = $hosts;
				}
			}

			// 2. 查询主机群组
			if ($id_type === 'auto' || $id_type === 'group') {
				$groups = API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'selectHosts' => ['name'],
					'groupids' => $parsed_ids
				]);
				if (is_array($groups)) {
					$results['groups'] = $groups;
				}
			}

			// 3. 查询监控项 (Item)
			if ($id_type === 'auto' || $id_type === 'item') {
				$items = API::Item()->get([
					'output' => ['itemid', 'name', 'key_', 'value_type', 'delay', 'status', 'units'],
					'selectHosts' => ['hostid', 'name'],
					'itemids' => $parsed_ids
				]);
				if (is_array($items)) {
					$results['items'] = $items;
				}
			}

			// 4. 查询触发器（传入 expandExpression 解析展开表达式）
			if ($id_type === 'auto' || $id_type === 'trigger') {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description', 'expression', 'priority', 'status', 'value'],
					'selectHosts' => ['hostid', 'name'],
					'expandExpression' => true,
					'triggerids' => $parsed_ids
				]);
				if (is_array($triggers)) {
					$results['triggers'] = $triggers;
				}
			}
		}

		$data = [
			'query_ids'  => $query_ids,
			'id_type'    => $id_type,
			'parsed_ids' => $parsed_ids,
			'results'    => $results
		];

		$response = new CControllerResponseData($data);
		$response->setTitle('ID 详情解析工具');
		$this->setResponse($response);
	}
}
