<?php

/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('multiselect.js');

$html_page = (new \CHtmlPage())->setTitle(_('ID 查询工具'));

$form_url = (new \CUrl('zabbix.php'))->setArgument('action', 'id.finder.view');
$filter_form = (new \CForm('get', $form_url->getUrl()))->setName('zbx_filter');
$filter_form->addItem(new \CVar('action', 'id.finder.view'));

// ==== 表单区域 (两列排布) ====
$left_grid = new \CFormGrid();

// 1. 主机群组选择
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

// 2. 主机选择
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

$right_grid = new \CFormGrid();

// 3. 监控项选择
$right_grid->addItem([
	new \CLabel(_('监控项 (Item)'), 'itemids_'),
	new \CFormField(
		(new \CMultiSelect([
			'name' => 'itemids[]',
			'object_name' => 'items',
			'data' => $data['multiselect_items'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'dstfrm' => $filter_form->getName(),
					'dstfld1' => 'itemids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

// 4. 触发器选择
$right_grid->addItem([
	new \CLabel(_('触发器 (Trigger)'), 'triggerids_'),
	new \CFormField(
		(new \CMultiSelect([
			'name' => 'triggerids[]',
			'object_name' => 'triggers',
			'data' => $data['multiselect_triggers'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => $filter_form->getName(),
					'dstfld1' => 'triggerids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
]);

$filter_container = (new \CDiv([
	(new \CDiv($left_grid))->addStyle('width: 480px;'),
	(new \CDiv($right_grid))->addStyle('width: 480px;')
]))->addStyle('display: flex; gap: 40px; justify-content: center;');

$buttons_div = (new \CDiv([
	new \CSubmit('filter_set', _('查询 ID')),
	(new \CSubmit('filter_rst', _('清空')))->addClass(ZBX_STYLE_BTN_ALT)
]))->addStyle('text-align: center; padding-top: 15px; border-top: 1px solid #dfe4e7; margin-top: 15px;');

$filter_card = (new \CDiv([$filter_container, $buttons_div]))
	->addClass(ZBX_STYLE_FILTER_CONTAINER)
	->addStyle('padding: 15px; background-color: #fff; border: 1px solid #dfe4e7; margin-bottom: 20px; border-radius: 2px;');

$filter_form->addItem($filter_card);
$html_page->addItem($filter_form);

// ==== ID 结果展示表格 ====
$table = (new \CTableInfo())->setHeader([
	_('类别'),
	_('ID 号 (ID)'),
	_('名称 / 描述'),
	_('附加参数 (Key / 所属主机)')
]);

// 1. 主机群组 ID
foreach ($data['result_groups'] as $g) {
	$table->addRow([
		(new \CSpan('主机群组'))->addClass(ZBX_STYLE_GREY),
		(new \CSpan($g['groupid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace; font-size: 14px;'),
		$g['name'],
		'-'
	]);
}

// 2. 主机 ID
foreach ($data['result_hosts'] as $h) {
	$table->addRow([
		(new \CSpan('主机'))->addClass(ZBX_STYLE_BLUE),
		(new \CSpan($h['hostid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace; font-size: 14px;'),
		$h['name'],
		'Host Name: '.$h['host']
	]);
}

// 3. 监控项 ID
foreach ($data['result_items'] as $i) {
	$host_name = !empty($i['hosts']) ? $i['hosts'][0]['name'] : '-';
	$table->addRow([
		(new \CSpan('监控项'))->addClass(ZBX_STYLE_GREEN),
		(new \CSpan($i['itemid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace; font-size: 14px;'),
		$i['name'],
		'所属主机: '.$host_name.' | Key: '.$i['key_']
	]);
}

// 4. 触发器 ID
foreach ($data['result_triggers'] as $t) {
	$host_name = !empty($t['hosts']) ? $t['hosts'][0]['name'] : '-';
	$table->addRow([
		(new \CSpan('触发器'))->addClass(ZBX_STYLE_RED),
		(new \CSpan($t['triggerid']))->addStyle('font-weight: bold; color: #d64e00; font-family: monospace; font-size: 14px;'),
		$t['description'],
		'所属主机: '.$host_name
	]);
}

$html_page->addItem($table);

// 激活前端 Multiselect JS 控件
$js_script = "
	jQuery(function($) {
		$('#groupids_').multiSelect();
		$('#hostids_').multiSelect();
		$('#itemids_').multiSelect();
		$('#triggerids_').multiSelect();
	});
";
$html_page->addItem(new \CScriptTag($js_script));

$html_page->show();