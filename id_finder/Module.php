<?php

namespace Modules\IdFinder;

use CMenuItem;

// 继承兼容基类，完美兼容 Zabbix 6.x / 7.x
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
                    // 挂载到 Inventory (资产记录) 菜单下
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(_('ID 查询工具')))->setAction('id.finder.view')
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('IdFinder Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}