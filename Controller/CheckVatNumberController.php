<?php

declare(strict_types=1);

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
use SiretManagement\Service\VatExistenceChecker;
use SiretManagement\SiretManagement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

/**
 * Front-facing, unauthenticated equivalent of ConfigurationBackController::testVatNumberAction,
 * used by the register/account-update forms to warn the customer before submission that
 * VIES did not find their VAT number, without waiting for a full form submit.
 */
#[AsController]
#[Route('/register/checkVatNumber', name: 'route.response.check.vat.number', methods: ['GET'])]
class CheckVatNumberController extends BaseFrontController
{
    use ThrottleTrait;

    public function __construct(
        private readonly VatExistenceChecker $vatExistenceChecker,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $throttleCache,
    ) {
    }

    private function getThrottleCache(): CacheItemPoolInterface
    {
        return $this->throttleCache;
    }

    /**
     * @throws \JsonException
     */
    public function __invoke(Request $request): Response
    {
        if (!(bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, null)) {
            return $this->jsonResponse(json_encode(
                ['success' => true, 'found' => true, 'message' => ''],
                JSON_THROW_ON_ERROR
            ));
        }

        if ($this->isThrottled('siretmanagement.front_check_vat_number.last_call')) {
            return $this->throttledJsonResponse();
        }

        $result = $this->vatExistenceChecker->testVatNumber((string) $request->query->get('vat_number', ''));

        // testVatNumber() is written for the BO diagnostic tool: its 'message' carries the
        // VIES-resolved company name and detailed error codes. Relaying that as-is to this
        // anonymous, unauthenticated endpoint would turn it into a public VIES lookup oracle.
        // The front script only ever reads success/found, so nothing else is exposed here.
        return $this->jsonResponse(json_encode(
            [
                'success' => $result['success'],
                'found' => $result['found'],
            ],
            JSON_THROW_ON_ERROR
        ));
    }
}
