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
use SiretManagement\Service\SiretAPIManagement;
use SiretManagement\Service\VatExistenceChecker;
use SiretManagement\SiretManagement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

class SiretSearchController extends BaseFrontController
{
    use ThrottleTrait;

    public function __construct(
        protected SiretAPIManagement $siretAPIManagement,
        protected VatExistenceChecker $vatExistenceChecker,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $throttleCache,
    ) {
    }

    /**
     * @throws \Exception
     *
     * @Route("/register/searchSiret", name="_search_siret", methods="GET")
     */
    public function siretResponse(Request $request): Response
    {
        $siret = $request->get('siret');

        $data = $this->siretAPIManagement->getData(preg_replace("/\D/", '', $siret));

        return $this->jsonResponse(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Front-facing, unauthenticated equivalent of ConfigurationBackController::testVatNumberAction,
     * used by the register/account-update forms to warn the customer before submission that
     * VIES did not find their VAT number, without waiting for a full form submit.
     *
     * @throws \JsonException
     *
     * @Route("/register/checkVatNumber", name="_check_vat_number_front", methods="GET")
     */
    public function checkVatNumberAction(Request $request): Response
    {
        if (!(bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, false)) {
            return $this->jsonResponse(json_encode(
                ['success' => true, 'found' => true, 'message' => ''],
                JSON_THROW_ON_ERROR
            ));
        }

        if ($this->isThrottled('siretmanagement.front_check_vat_number.last_call')) {
            return $this->throttledJsonResponse();
        }

        return $this->jsonResponse(json_encode(
            $this->vatExistenceChecker->testVatNumber((string) $request->query->get('vat_number', '')),
            JSON_THROW_ON_ERROR
        ));
    }
}
