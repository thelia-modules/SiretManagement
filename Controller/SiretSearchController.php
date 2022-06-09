<?php
namespace SiretManagement\Controller;


use Exception;
use SiretManagement\Service\SiretAPIManagement;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

class SiretSearchController extends BaseFrontController
{
    private $siretAPIManagement;

    public function __construct(SiretAPIManagement $siretAPIManagement)
    {
        $this->siretAPIManagement = $siretAPIManagement;
    }

    /**
     * @throws Exception
     */
    public function siretResponse(Request $request){
        $siret =$request->get('siret');

        $data=$this->siretAPIManagement->getData($siret);

        return $this->jsonResponse(json_encode($data));

    }
}