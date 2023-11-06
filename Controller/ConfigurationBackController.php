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

namespace SiretManagement\Controller;

use SiretManagement\Form\Configuration;
use SiretManagement\SiretManagement;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class ConfigurationBackController extends BaseAdminController
{
    /**
     * @Route("/admin/module/siret/configuration/save", name="_cofiguration_siret", methods="POST")
     */
    public function saveAction(Request $request): mixed
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['siretmanagement'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::class);

        try {
            $data = $this->validateForm($form)->getData();

            static $names = [
                SiretManagement::PRIVATE_CONSUMER,
                SiretManagement::PUBLIC_CONSUMER,
                SiretManagement::TVA_INTRA_REQUIRED,
                SiretManagement::SIRET_REQUIRED,
                SiretManagement::USE_TVA_INTRA,
                SiretManagement::USE_SIRET,
                SiretManagement::API_CHECK_DISABLED,
            ];

            foreach ($names as $name) {
                SiretManagement::setConfigValue($name, $data[$name]);
            }

            if ($request->get('save_mode') === 'stay') {
                // If we have to stay on the same page, redisplay the configuration page/
                return $this->generateRedirect(URL::getInstance()?->absoluteUrl('/admin/module/SiretManagement'));
            }

            return $this->generateRedirect(URL::getInstance()?->absoluteUrl('/admin/modules'));
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()?->trans('Syntax error'),
                $e->getMessage(),
                $form,
                $e
            );
        }

        return $this->generateErrorRedirect($form);
    }
}
