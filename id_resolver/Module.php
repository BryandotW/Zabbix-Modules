<?php

namespace Modules\IdResolver;

use CMenuItem;

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
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(_('ID 详情解析')))->setAction('id.resolver.view')
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('IdResolver Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}