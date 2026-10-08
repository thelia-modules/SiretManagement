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

use SiretManagement\Service\SiretAPIManagement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\HttpFoundation\Request;

#[AsController]
#[Route('/register/searchSiret', name: 'route.response.search.siret', methods: ['GET'])]
class SiretSearchController extends AbstractController
{
    public function __construct(protected SiretAPIManagement $siretAPIManagement)
    {
    }

    public function __invoke(Request $request): Response
    {
        $siret = preg_replace("/\D/", '', (string) $request->query->get('siret', ''));

        // A number of the wrong length, one SIRENE does not know, a SIRENE outage or a module without its API key
        // all answer "nothing found": never a 500, and never the reason, which is about the shop's configuration.
        try {
            $data = [$this->siretAPIManagement->getData($siret)];
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
