<?php

/**
 * @var CView $this
 * @var array $data
 */

$html_page = (new \CHtmlPage())->setTitle(_('ID 详情解析工具'));

$form_url = (new \CUrl('zabbix.php'))->setArgument('action', 'id.resolver.view');
$filter_form = (new \CForm('get', $form_url->getUrl()))->setName('zbx_filter');
$filter_form->addItem(new \CVar('action', 'id.resolver.view'));

// ==== 表单区域 ====
$grid = new \CFormGrid();

$type_select = (new \CSelect('id_type'))
	->setValue($data['id_type'])
	->addOption(new \CSelectOption('auto', _('自动识别 (推荐)')))
	->addOption(new \CSelectOption('host', _('仅查询主机 ID')))
	->addOption(new \CSelectOption('group', _('仅查询群组 ID')))
	->addOption(new \CSelectOption('item', _('仅查询监控项 ID')))
	->addOption(new \CSelectOption('trigger', _('仅查询触发器 ID')));

$grid->addItem([
	new \CLabel(_('ID 类型选择'), 'id_type'),
	new \CFormField($type_select)
]);

$grid->addItem([
	new \CLabel(_('输入 ID 号'), 'query_ids'),
	new \CFormField(
		(new \CTextArea('query_ids', $data['query_ids']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setRows(4)
			->setAttribute('placeholder', "输入一个或多个 ID，支持用逗号、空格或换行分隔，如:\n10084, 23211\n43102")
	)
]);

$buttons_div = (new \CDiv([
	new \CSubmit('filter_set', _('解析 ID 详情')),
	(new \CSubmit('filter_rst', _('清空')))->addClass(ZBX_STYLE_BTN_ALT)
]))->addStyle('text-align: center; padding-top: 10px;');

$filter_card = (new \CDiv([$grid, $buttons_div]))
	->addClass(ZBX_STYLE_FILTER_CONTAINER)
	->addStyle('padding: 15px; background-color: #fff; border: 1px solid #dfe4e7; margin-bottom: 20px;');

$filter_form->addItem($filter_card);
$html_page->addItem($filter_form);

$create_sub_header = function(string $text) {
	return (new \CTag('h4', true, $text))
		->addStyle('margin: 15px 0 10px 0; font-weight: bold; font-size: 14px; color: #1f2c33;');
};

// ==== 结果呈现区域 ====
$results = $data['results'];
$has_data = !empty($results['hosts']) || !empty($results['groups']) || !empty($results['items']) || !empty($results['triggers']);

if ($has_data) {
	// 1. 主机匹配结果
	if (!empty($results['hosts'])) {
		$html_page->addItem($create_sub_header(_('匹配的主机详情 (Hosts)')));
		$table_hosts = (new \CTableInfo())->setHeader(['Host ID', '主机名称 (Visible Name)', '名称(Host Name)', '主 IP 接口', '所属群组', '状态']);
		foreach ($results['hosts'] as $h) {
			$ip_list = [];
			$interfaces = $h['interfaces'] ?? [];
			if (is_array($interfaces)) {
				foreach ($interfaces as $if) {
					if (isset($if['main']) && $if['main'] == 1) {
						$ip_list[] = $if['ip'] . ':' . $if['port'];
					}
				}
			}

			$groups_data = $h['hostgroups'] ?? $h['groups'] ?? [];
			$groups = (is_array($groups_data) && !empty($groups_data))
				? implode(', ', array_column($groups_data, 'name')) 
				: '-';

			$status_span = (isset($h['status']) && $h['status'] == 0)
				? (new \CSpan('启用'))->addClass(ZBX_STYLE_GREEN) 
				: (new \CSpan('禁用'))->addClass(ZBX_STYLE_RED);

			$table_hosts->addRow([
				(new \CSpan($h['hostid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace;'),
				$h['name'] ?? '-',
				$h['host'] ?? '-',
				!empty($ip_list) ? implode(', ', $ip_list) : '-',
				$groups,
				$status_span
			]);
		}
		$html_page->addItem($table_hosts);
	}

	// 2. 主机群组匹配结果
	if (!empty($results['groups'])) {
		$html_page->addItem($create_sub_header(_('匹配的主机群组详情 (Groups)')));
		$table_groups = (new \CTableInfo())->setHeader(['Group ID', '群组名称', '包含主机数']);
		foreach ($results['groups'] as $g) {
			$hosts_count = is_array($g['hosts'] ?? null) ? count($g['hosts']) : 0;
			$table_groups->addRow([
				(new \CSpan($g['groupid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace;'),
				$g['name'] ?? '-',
				$hosts_count
			]);
		}
		$html_page->addItem($table_groups);
	}

	// 3. 监控项匹配结果
	if (!empty($results['items'])) {
		$html_page->addItem($create_sub_header(_('匹配的监控项详情 (Items)')));
		$table_items = (new \CTableInfo())->setHeader(['Item ID', '监控项名称', 'Key', '所属主机', '更新间隔', '状态', '操作']);
		foreach ($results['items'] as $i) {
			$host_name = !empty($i['hosts']) && is_array($i['hosts']) ? $i['hosts'][0]['name'] : '-';
			$status_span = (isset($i['status']) && $i['status'] == 0) 
				? (new \CSpan('启用'))->addClass(ZBX_STYLE_GREEN) 
				: (new \CSpan('禁用'))->addClass(ZBX_STYLE_RED);

			$graph_url = (new \CUrl('history.php'))
				->setArgument('action', 'showgraph')
				->setArgument('itemids', [$i['itemid']]);

			// 采用 Zabbix 原生最新的 ZBX_STYLE_LINK_ALT 样式（蓝色文字/虚线下划线）
			$graph_btn = (new \CLink('图形', $graph_url->getUrl()))
				->addClass(ZBX_STYLE_LINK_ALT)
				->setAttribute('target', '_blank');

			$table_items->addRow([
				(new \CSpan($i['itemid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace;'),
				$i['name'] ?? '-',
				(new \CSpan($i['key_'] ?? ''))->addStyle('font-family: monospace;'),
				$host_name,
				$i['delay'] ?? '-',
				$status_span,
				$graph_btn
			]);
		}
		$html_page->addItem($table_items);
	}

	// 4. 触发器匹配结果
	if (!empty($results['triggers'])) {
		$html_page->addItem($create_sub_header(_('匹配的触发器详情 (Triggers)')));
		$table_triggers = (new \CTableInfo())->setHeader(['Trigger ID', '触发器描述', '表达式 (Expression)', '所属主机', '严重性', '状态']);
		
		$priorities = [
			0 => ['未定义', ZBX_STYLE_GREY],
			1 => ['信息', ZBX_STYLE_BLUE],
			2 => ['警告', ZBX_STYLE_YELLOW],
			3 => ['一般严重', 'style-orange'],
			4 => ['严重', ZBX_STYLE_RED],
			5 => ['灾难', 'style-disaster']
		];

		foreach ($results['triggers'] as $t) {
			$host_name = !empty($t['hosts']) && is_array($t['hosts']) ? $t['hosts'][0]['name'] : '-';
			$p_info = $priorities[$t['priority'] ?? 0] ?? ['未知', ZBX_STYLE_GREY];
			$p_span = (new \CSpan($p_info[0]))->addClass($p_info[1]);
			$status_span = (isset($t['status']) && $t['status'] == 0) 
				? (new \CSpan('启用'))->addClass(ZBX_STYLE_GREEN) 
				: (new \CSpan('禁用'))->addClass(ZBX_STYLE_RED);

			$table_triggers->addRow([
				(new \CSpan($t['triggerid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace;'),
				$t['description'] ?? '-',
				(new \CSpan($t['expression'] ?? ''))->addStyle('font-family: monospace; font-size: 11px; word-break: break-all;'),
				$host_name,
				$p_span,
				$status_span
			]);
		}
		$html_page->addItem($table_triggers);
	}
} elseif (!empty($data['parsed_ids'])) {
	$html_page->addItem((new \CDiv('未在系统中匹配到任何对应的资源信息'))
		->addStyle('padding: 20px; text-align: center; color: #999; background: #fff; border: 1px solid #dfe4e7; margin-top: 15px;'));
}

$html_page->show();
