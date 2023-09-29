<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SiretManagement\Hook;

use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\SiretManagement;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class BackHook extends BaseHook
{
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('module_configuration.html'));
    }

    public function onCustomerEdit(HookRenderEvent $event): void
    {
        $tva = $siret = '';

        if (null !== $siretInfo = SiretCustomerQuery::create()->findOneByCustomerId(
            $event->getArgument('customer_id')
            )
        ) {
            $tva = $siretInfo->getCodeTvaIntra();
            $siret = $siretInfo->getCodeSiret();
        }

        $event->add(
            $this->render(
            'customer-edit.html',
                [
                    'use_tva_intra' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true),
                    'use_siret' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_SIRET, true),
                    'tva_intra' => $tva,
                    'siret' => $siret
                ]
            )
        );
    }
}
