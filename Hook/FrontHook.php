<?php

namespace SiretManagement\Hook;

use SiretManagement\Model\SiretCustomerQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class FrontHook extends BaseHook
{
    public function siretCheck(HookRenderEvent $event)
    {
        $siret = "";
        if(isset($event->getTemplateVars()["customer_id"])){
            $customer_id = $event->getTemplateVars()["customer_id"];
            $siretCustomer = SiretCustomerQuery::create()
                ->filterByCustomerId($customer_id)
                ->findOne();
            if ($siretCustomer !== null && $siretCustomer->getCodeSiret() !== null){
                $siret=$siretCustomer->getCodeSiret();
            }
        }
        $event->add($this->render(
            'siret.html',
            [
                'siret' => $siret,
            ]
        ));
    }
}