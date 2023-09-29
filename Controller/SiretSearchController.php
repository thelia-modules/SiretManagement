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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

class SiretSearchController extends BaseFrontController
{
    public function __construct(protected SiretAPIManagement $siretAPIManagement)
    {
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
}
