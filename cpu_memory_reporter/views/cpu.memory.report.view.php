<?php

/**
 * CPU/内存性能监测 View 视图文件 (支持自定义列勾选 & 按需加载)
 */

$this->addJsFile('multiselect.js');

$html_page = (new CHtmlPage())
	->setTitle($data['page_title']);

// 1. 构建过滤表单
$filter_form = (new CForm('get'))
	->setName('zbx_filter')
	->addVar('action', 'cpu.memory.report.view');

// 2. 左侧表单控件：主机群组 & 主机
$group_multiselect = (new CMultiSelect([
	'name' => 'groupids[]',
	'object_name' => 'hostGroup',
	'data' => $data['filter']['groupids'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'host_groups',
			'srcfld1' => 'groupid',
			'dstfrm' => 'zbx_filter',
			'dstfld1' => 'groupids_'
		]
	]
]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

$host_multiselect = (new CMultiSelect([
	'name' => 'hostids[]',
	'object_name' => 'hosts',
	'data' => $data['filter']['hostids'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'hosts',
			'srcfld1' => 'hostid',
			'dstfrm' => 'zbx_filter',
			'dstfld1' => 'hostids_',
			'real_hosts' => '1'
		]
	]
]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

$left_form_list = (new CFormList())
	->addRow(_('主机群组'), $group_multiselect)
	->addRow(_('主机'), $host_multiselect);

// 3. 右侧表单控件：时间过滤、排序方式、排序顺序
$time_range_select = (new CSelect('time_range'))
	->setValue((string)$data['filter']['time_range']);

$time_options = [
	300      => _('最近5分钟'),
	900      => _('最近15分钟'),
	1800     => _('最近30分钟'),
	3600     => _('最近1小时'),
	10800    => _('最近3小时'),
	21600    => _('最近6小时'),
	43200    => _('最近12小时'),
	86400    => _('最近1天'),
	172800   => _('最近2天'),
	259200   => _('最近3天'),
	345600   => _('最近4天'),
	432000   => _('最近5天'),
	518400   => _('最近6天'),
	604800   => _('最近7天'),
	1296000  => _('最近15天'),
	2592000  => _('最近30天'),
	5184000  => _('最近60天'),
	7776000  => _('最近90天'),
	10368000 => _('最近120天'),
	12960000 => _('最近150天'),
	15552000 => _('最近180天')
];
foreach ($time_options as $value => $label) {
	$time_range_select->addOption(new CSelectOption((string)$value, $label));
}

// 可选列全集定义
$all_optional_columns = [
	'cpu_util_max' => _('CPU使用率峰值(%)'),
	'cpu_util_avg' => _('CPU使用率均值(%)'),
	'cpu_util_med' => _('CPU使用率中位数(%)'),
	'load1_max'    => _('1分钟CPU负载峰值'),
	'load1_avg'    => _('1分钟CPU负载均值'),
	'load1_med'    => _('1分钟CPU负载中位数'),
	'load5_max'    => _('5分钟CPU负载峰值'),
	'load5_avg'    => _('5分钟CPU负载均值'),
	'load5_med'    => _('5分钟CPU负载中位数'),
	'load15_max'   => _('15分钟CPU负载峰值'),
	'load15_avg'   => _('15分钟CPU负载均值'),
	'load15_med'   => _('15分钟CPU负载中位数'),
	'mem_util_max' => _('内存使用率峰值(%)'),
	'mem_util_avg' => _('内存使用率均值(%)'),
	'mem_util_med' => _('内存使用率中位数(%)')
];

$sort_field_select = (new CSelect('sort_field'))
	->setValue($data['filter']['sort_field'])
	->addOption(new CSelectOption('hostid', 'HostID'));

foreach ($all_optional_columns as $key => $label) {
	if (in_array($key, $data['filter']['selected_columns'])) {
		$sort_field_select->addOption(new CSelectOption($key, $label));
	}
}

$sort_order_select = (new CSelect('sort_order'))
	->setValue($data['filter']['sort_order'])
	->addOption(new CSelectOption('DESC', _('降序 (DESC)')))
	->addOption(new CSelectOption('ASC', _('升序 (ASC)')));

$right_form_list = (new CFormList())
	->addRow(_('时间过滤选项'), $time_range_select)
	->addRow(_('排序方式'), $sort_field_select)
	->addRow(_('排序顺序'), $sort_order_select);

// 4. Flex 居中双列网格
$grid_container = (new CDiv([
	(new CDiv($left_form_list))->setAttribute('style', 'flex: 1; min-width: 400px;'),
	(new CDiv($right_form_list))->setAttribute('style', 'flex: 1; min-width: 400px;')
]))->setAttribute('style', 'display: flex; justify-content: space-between; max-width: 1000px; margin: 0 auto; gap: 40px;');

$filter_form->addItem($grid_container);

// 5. 指标自定义勾选面板 (完全原生构建，彻底消除 ID 冲突和误挂载)
$checkbox_grid = (new CDiv())->setAttribute('style', 'display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 15px; margin-top: 10px;');

foreach ($all_optional_columns as $key => $label) {
	$isChecked = in_array($key, $data['filter']['selected_columns']);
	$cb_id = 'opt_col_' . $key;

	$input = (new CTag('input', true))
		->setAttribute('type', 'checkbox')
		->setAttribute('name', 'columns[]')
		->setAttribute('value', $key)
		->setId($cb_id)
		->addClass('column-checkbox');

	if ($isChecked) {
		$input->setAttribute('checked', 'checked');
	}

	$label_tag = (new CTag('label', true, ' ' . $label))
		->setAttribute('for', $cb_id)
		->setAttribute('style', 'cursor: pointer; user-select: none; margin-left: 4px;');

	$item_wrap = (new CDiv([$input, $label_tag]))
		->setAttribute('style', 'display: flex; align-items: center;');

	$checkbox_grid->addItem($item_wrap);
}

// 全选/取消全选按钮
$toggle_id = 'opt_col_toggle_all';
$toggle_input = (new CTag('input', true))
	->setAttribute('type', 'checkbox')
	->setId($toggle_id)
	->setAttribute('onclick', '
		var checkboxes = document.querySelectorAll(".column-checkbox");
		for(var i = 0; i < checkboxes.length; i++){
			checkboxes[i].checked = this.checked;
		}
	');

if (!empty($data['filter']['selected_columns']) && count($data['filter']['selected_columns']) === count($all_optional_columns)) {
	$toggle_input->setAttribute('checked', 'checked');
}

$toggle_label = (new CTag('label', true, ' ' . _('【全选 / 取消全选】')))
	->setAttribute('for', $toggle_id)
	->setAttribute('style', 'cursor: pointer; user-select: none; font-weight: bold; margin-left: 4px;');

$toggle_wrap = (new CDiv([$toggle_input, $toggle_label]))
	->setAttribute('style', 'display: flex; align-items: center; margin-bottom: 8px;');

$column_selector_list = (new CFormList())
	->addRow(_('自定义显示指标'), [
		$toggle_wrap,
		$checkbox_grid
	]);

$column_container = (new CDiv($column_selector_list))
	->setAttribute('style', 'max-width: 1000px; margin: 15px auto 0 auto; border-top: 1px dashed var(--border-color, #dfe4e8); padding-top: 10px;');

$filter_form->addItem($column_container);

// 6. 底部 Apply / Reset 按钮区域
$filter_buttons = (new CDiv([
	new CSubmit('filter_apply', _('Apply')),
	(new CRedirectButton(_('Reset'), (new CUrl('zabbix.php'))->setArgument('action', 'cpu.memory.report.view')))
		->addClass('btn-alt')
]))->addClass('filter-forms');

$filter_form->addItem($filter_buttons);

// 7. 使用标准的 filter-container
$filter_wrapper = (new CDiv($filter_form))
	->addClass('filter-container')
	->setAttribute('style', 'background-color: var(--bg-color, #fff); padding: 15px 0 10px 0; margin-bottom: 10px; border-bottom: 1px solid var(--border-color, #dfe4e8);');

$html_page->addItem($filter_wrapper);

// 8. 动态生成数据表格列表 Header (默认只有基础6列)
$header_cols = [
	'HostID',
	[_('主机名称'), new CTag('br'), '(Visible Name)'],
	[_('主机名'), new CTag('br'), '(Host name)'],
	_('所属群组'),
	_('CPU核数'),
	[_('内存总量'), new CTag('br'), '(GB)']
];

// 列表头映射字典
$column_header_map = [
	'cpu_util_max' => [_('CPU使用率'), new CTag('br'), _('峰值(%)')],
	'cpu_util_avg' => [_('CPU使用率'), new CTag('br'), _('均值(%)')],
	'cpu_util_med' => [_('CPU使用率'), new CTag('br'), _('中位数(%)')],
	'load1_max'    => ['1分钟', new CTag('br'), 'CPU负载', new CTag('br'), '峰值'],
	'load1_avg'    => ['1分钟', new CTag('br'), 'CPU负载', new CTag('br'), '均值'],
	'load1_med'    => ['1分钟', new CTag('br'), 'CPU负载', new CTag('br'), '中位数'],
	'load5_max'    => ['5分钟', new CTag('br'), 'CPU负载', new CTag('br'), '峰值'],
	'load5_avg'    => ['5分钟', new CTag('br'), 'CPU负载', new CTag('br'), '均值'],
	'load5_med'    => ['5分钟', new CTag('br'), 'CPU负载', new CTag('br'), '中位数'],
	'load15_max'   => ['15分钟', new CTag('br'), 'CPU负载', new CTag('br'), '峰值'],
	'load15_avg'   => ['15分钟', new CTag('br'), 'CPU负载', new CTag('br'), '均值'],
	'load15_med'   => ['15分钟', new CTag('br'), 'CPU负载', new CTag('br'), '中位数'],
	'mem_util_max' => [_('内存使用率'), new CTag('br'), _('峰值(%)')],
	'mem_util_avg' => [_('内存使用率'), new CTag('br'), _('均值(%)')],
	'mem_util_med' => [_('内存使用率'), new CTag('br'), _('中位数(%)')]
];

foreach ($data['filter']['selected_columns'] as $col_key) {
	if (isset($column_header_map[$col_key])) {
		$header_cols[] = $column_header_map[$col_key];
	}
}

$table = (new CTableInfo())->setHeader($header_cols);

$total_hosts = count($data['report_data']);

if ($total_hosts > 0) {
	// 填充表格行数据
	foreach ($data['report_data'] as $row) {
		$row_cells = [
			$row['hostid'],
			$row['name'],
			$row['host'],
			$row['groups'],
			$row['cores'],
			$row['mem_total_gb']
		];

		foreach ($data['filter']['selected_columns'] as $col_key) {
			if (isset($row[$col_key])) {
				$row_cells[] = $row[$col_key];
			}
		}

		$table->addRow($row_cells);
	}

	// 计算当前表格总列数，实现精准列跨越与右对齐
	$total_cols = count($header_cols);
	$footer_cell = (new CCol(sprintf(_('共找到 %1$s 个主机'), $total_hosts)))
		->setColSpan($total_cols)
		->addClass(ZBX_STYLE_RIGHT); // 强制右对齐

	$table->addRow($footer_cell);
}

$html_page->addItem($table);

// 渲染输出
$html_page->show();
