<?php

namespace Modules\CpuMemoryReporter;

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
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Monitoring'))  // 挂载到 Monitoring (监测) 菜单
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(_('CPU/内存性能监测')))->setAction('cpu.memory.report.view')
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('CpuMemoryReporter Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
