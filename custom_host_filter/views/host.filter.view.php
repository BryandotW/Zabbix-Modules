<?php

/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('multiselect.js');

$html_page = (new \CHtmlPage())->setTitle(_('高级主机筛选'));

// 明确把 action 参数绑定在 Target URL 上
$form_url = (new \CUrl('zabbix.php'))->setArgument('action', 'host.filter.view');

$filter_form = (new \CForm('get', $form_url->getUrl()))
	->setName('zbx_filter');

$filter_form->addItem(new \CVar('action', 'host.filter.view'));

// ==== 左侧表单列 ====
$left_grid = new \CFormGrid();

// 1. 主机群组
$left_grid->addItem([
	new \CLabel(_('主机群组'), 'groupids_'),
	new \CFormField(
		(new \CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['multiselect_groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $filter_form->getName(),
					'dstfld1' => 'groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

// 2. 主机名称
$left_grid->addItem([
	new \CLabel(_('主机名称'), 'hostids_'),
	new \CFormField(
		(new \CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $data['multiselect_hosts'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => $filter_form->getName(),
					'dstfld1' => 'hostids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

// 3. IP地址
$left_grid->addItem([
	new \CLabel(_('IP地址'), 'ip'),
	new \CFormField(
		(new \CTextBox('ip', $data['filter']['ip']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

// 4. 端口
$left_grid->addItem([
	new \CLabel(_('端口'), 'port'),
	new \CFormField(
		(new \CTextBox('port', $data['filter']['port']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

// ==== 右侧表单列 ====
$right_grid = new \CFormGrid();

// 5. 状态
$status_radio = (new \CRadioButtonList('status', (int)$data['filter']['status']))
	->addValue(_('任何'), -1)
	->addValue(_('已启用'), HOST_STATUS_MONITORED)
	->addValue(_('停用'), HOST_STATUS_NOT_MONITORED)
	->setModern(true);

$right_grid->addItem([
	new \CLabel(_('状态'), 'status'),
	new \CFormField($status_radio)
]);

// 6. 接口类型（增加了“无接口”选项）
$type_radio = (new \CRadioButtonList('interface_type', (int)$data['filter']['interface_type']))
	->addValue(_('任何'), -1)
	->addValue('Agent类型', INTERFACE_TYPE_AGENT)
	->addValue('SNMP类型', INTERFACE_TYPE_SNMP)
	->addValue('IPMI类型', INTERFACE_TYPE_IPMI)
	->addValue('JMX类型', INTERFACE_TYPE_JMX)
	->addValue('未知', 0)
	->addValue('无接口', 99)
	->setModern(true);

$right_grid->addItem([
	new \CLabel(_('接口类型'), 'interface_type'),
	new \CFormField($type_radio)
]);

// 7. 接口状态（增加了“无接口”选项）
$state_radio = (new \CRadioButtonList('interface_state', (int)$data['filter']['interface_state']))
	->addValue(_('任何'), -1)
	->addValue('可用', INTERFACE_AVAILABLE_TRUE)
	->addValue('不可用', INTERFACE_AVAILABLE_FALSE)
	->addValue('未知', INTERFACE_AVAILABLE_UNKNOWN)
	->addValue('无接口', 3)
	->setModern(true);

$right_grid->addItem([
	new \CLabel(_('接口状态'), 'interface_state'),
	new \CFormField($state_radio)
]);

// ==== 居中对齐的双列布局容器 ====
$filter_container = (new \CDiv([
	(new \CDiv($left_grid))->addStyle('width: 480px;'),
	(new \CDiv($right_grid))->addStyle('width: 480px;')
]))
->addStyle('display: flex; gap: 40px; justify-content: center;');

// ==== 底部按钮组 ====
$buttons_div = (new \CDiv([
	new \CSubmit('filter_set', _('Apply')),
	(new \CSubmit('filter_rst', _('Reset')))->addClass(ZBX_STYLE_BTN_ALT)
]))
->addStyle('text-align: center; padding-top: 15px; border-top: 1px solid #dfe4e7; margin-top: 15px;');

// 组装整个筛选卡片
$filter_card = (new \CDiv([
	$filter_container,
	$buttons_div
]))
->addClass(ZBX_STYLE_FILTER_CONTAINER)
->addStyle('padding: 15px; background-color: #fff; border: 1px solid #dfe4e7; margin-bottom: 15px; border-radius: 2px;');

$filter_form->addItem($filter_card);

$html_page->addItem($filter_form);

// ==== 结果表格展示 ====
$table = (new \CTableInfo())->setHeader([
	_('主机名称'),
	_('主机群组'),
	_('接口 (IP:Port)'),
	_('接口类型'),
	_('接口状态'),
	_('状态')
]);

$type_map = [
	INTERFACE_TYPE_AGENT => 'Agent',
	INTERFACE_TYPE_SNMP  => 'SNMP',
	INTERFACE_TYPE_IPMI  => 'IPMI',
	INTERFACE_TYPE_JMX   => 'JMX'
];

$host_count = !empty($data['hosts']) ? count($data['hosts']) : 0;

if (!empty($data['hosts'])) {
	foreach ($data['hosts'] as $host) {
		$group_names = [];
		if (!empty($host['hostgroups'])) {
			foreach ($host['hostgroups'] as $g) {
				$group_names[] = $g['name'];
			}
		}

		$interfaces_div = new \CDiv();
		$types_div = new \CDiv();
		$states_div = new \CDiv();

		if (!empty($host['interfaces'])) {
			foreach ($host['interfaces'] as $iface) {
				// IP:Port
				$interfaces_div->addItem(new \CDiv($iface['ip'].':'.$iface['port']));
				
				// 接口类型
				$t_code = (int)$iface['type'];
				$t_name = isset($type_map[$t_code]) ? $type_map[$t_code] : '未知';
				$types_div->addItem(new \CDiv($t_name));

				// 接口状态
				$a_code = (int)$iface['available'];
				if ($a_code == INTERFACE_AVAILABLE_TRUE) {
					$states_div->addItem(new \CDiv((new \CSpan('可用'))->addClass(ZBX_STYLE_GREEN)));
				} elseif ($a_code == INTERFACE_AVAILABLE_FALSE) {
					$states_div->addItem(new \CDiv((new \CSpan('不可用'))->addClass(ZBX_STYLE_RED)));
				} else {
					$states_div->addItem(new \CDiv((new \CSpan('未知'))->addClass(ZBX_STYLE_GREY)));
				}
			}
		} else {
			// 如果该主机没有任何接口，显示灰色的“无接口”标记
			$interfaces_div->addItem(new \CDiv((new \CSpan('无接口'))->addClass(ZBX_STYLE_GREY)));
			$types_div->addItem(new \CDiv((new \CSpan('无接口'))->addClass(ZBX_STYLE_GREY)));
			$states_div->addItem(new \CDiv((new \CSpan('无接口'))->addClass(ZBX_STYLE_GREY)));
		}

		$status_span = ($host['status'] == HOST_STATUS_MONITORED)
			? (new \CSpan(_('已启用')))->addClass(ZBX_STYLE_GREEN)
			: (new \CSpan(_('停用')))->addClass(ZBX_STYLE_RED);

		$table->addRow([
			$host['name'],
			implode(', ', $group_names),
			$interfaces_div,
			$types_div,
			$states_div,
			$status_span
		]);
	}
}

$html_page->addItem($table);

// 右下角显示找到的主机总数
$table_footer = (new \CDiv([
	'共找到 ',
	(new \CSpan($host_count))->addStyle('font-weight: bold; margin: 0 4px;'),
	' 个主机'
]))
->addClass(ZBX_STYLE_TABLE_PAGING)
->addStyle('text-align: right; padding: 8px 12px;');

$html_page->addItem($table_footer);

// 触发 jQuery multiselect 初始化
$js_script = "
	jQuery(function($) {
		$('#groupids_').multiSelect();
		$('#hostids_').multiSelect();
	});
";
$html_page->addItem(new \CScriptTag($js_script));

$html_page->show();
