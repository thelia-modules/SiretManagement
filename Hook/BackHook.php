<?php

namespace SiretManagement\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class BackHook extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event){
        $event->add($this->render("module_configuration.html"));
    }
}