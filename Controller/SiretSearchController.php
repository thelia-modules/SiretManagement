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
use Thelia\Core\HttpFoundation\Request;

#[AsController]
class SiretSearchController extends AbstractController
{
    public function __construct(protected SiretAPIManagement $siretAPIManagement)
    {
    }

    public function __invoke(Request $request): Response
    {
        $siret = $request->get('siret');

        $data = [$this->siretAPIManagement->getData(preg_replace("/\D/", '', $siret))];

        return new JsonResponse(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
