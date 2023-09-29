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

class FrontHook extends BaseHook
{
    public function onSiretJs(HookRenderEvent $event): void
    {
        $event->add(
           $this->render('hook/siret-js.html')
        );
    }
    public function onSiretCheck(HookRenderEvent $event): void
    {
        $siretCustomer = SiretCustomerQuery::create()
            ->filterByCustomerId($this->getSession()->getCustomerUser()?->getId())
            ->findOne()
        ;

        $tvaIntra = $siret = '';

        if ($siretCustomer !== null && $siretCustomer->getCodeSiret() !== null) {
            $siret = $siretCustomer->getCodeSiret();
            $tvaIntra = $siretCustomer->getCodeTvaIntra();
        }

        $event->add($this->render(
            'hook/siret.html',
            [
                'use_tva_intra' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true),
                'use_siret' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_SIRET, true),
                'tva_intra_required' => (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, false),
                'siret_required' => (bool) SiretManagement::getConfigValue(SiretManagement::SIRET_REQUIRED, false),

                'mode' => $event->getArgument('mode'), // create or update

                'siret' => $siret,
                'tva_intra' => $tvaIntra,
            ]
        ));
    }
}
