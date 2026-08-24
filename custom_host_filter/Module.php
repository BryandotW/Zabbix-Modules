<?php

namespace Modules\CustomHostFilter;

use CMenuItem;

// 根据实际存在的类选择基类，完美兼容 Zabbix 6.x 和 Zabbix 7.x
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        try {
            if (class_exists('APP')) {
                $app = new \ReflectionClass('APP');
                
                if ($app->hasMethod('Component')) {
                    // 精准获取 menu.main 组件并插入到 Inventory 菜单下
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(_('高级主机筛选')))->setAction('host.filter.view')
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('CustomHostFilter Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
