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

use Psr\Cache\CacheItemPoolInterface;
use SiretManagement\Form\Configuration;
use SiretManagement\Service\VatExistenceChecker;
use SiretManagement\SiretManagement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class ConfigurationBackController extends BaseAdminController
{
    use ThrottleTrait;

    public function __construct(
        private readonly VatExistenceChecker $vatExistenceChecker,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $throttleCache,
    ) {
    }

    /**
     */
    #[Route('/admin/module/siret/configuration/save', name: '_cofiguration_siret', methods: ['POST'])]
    public function saveAction(Request $request): mixed
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['siretmanagement'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::class);

        try {
            $data = $this->validateForm($form)->getData();

            static $names = [
                SiretManagement::API_KEY,
                SiretManagement::TVA_INTRA_REQUIRED,
                SiretManagement::SIRET_REQUIRED,
                SiretManagement::USE_TVA_INTRA,
                SiretManagement::USE_SIRET,
                SiretManagement::API_CHECK_DISABLED,
                SiretManagement::VAT_API_CHECK_ENABLED,
            ];

            foreach ($names as $name) {
                SiretManagement::setConfigValue($name, $data[$name]);
            }

            if ($request->request->get('save_mode') === 'stay') {
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

    #[Route('/admin/module/siret/configuration/test-vat-api', name: '_configuration_siret_test_vat_api', methods: ['GET'])]
    public function testVatApiAction(): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['siretmanagement'], AccessManager::VIEW)) {
            return $response;
        }

        if ($this->isThrottled('siretmanagement.test_vat_api.last_call')) {
            return $this->throttledJsonResponse();
        }

        return $this->jsonResponse(json_encode(
            $this->vatExistenceChecker->checkApiAvailability(),
            JSON_THROW_ON_ERROR
        ));
    }

    #[Route('/admin/module/siret/configuration/test-vat-number', name: '_configuration_siret_test_vat_number', methods: ['GET'])]
    public function testVatNumberAction(Request $request): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['siretmanagement'], AccessManager::VIEW)) {
            return $response;
        }

        if ($this->isThrottled('siretmanagement.test_vat_number.last_call')) {
            return $this->throttledJsonResponse();
        }

        return $this->jsonResponse(json_encode(
            $this->vatExistenceChecker->testVatNumber((string) $request->query->get('vat_number', '')),
            JSON_THROW_ON_ERROR
        ));
    }
}
