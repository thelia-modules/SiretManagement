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

use SiretManagement\Form\CustomerForm;
use SiretManagement\Model\SiretCustomer;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\SiretManagement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;

class CustomerController extends BaseAdminController
{
    /**
     * @Route("/admin/module/siret/customer/{customerId}", name="_customer_siret_data", methods="POST")
     */
    public function saveAction(int $customerId, Translator $translator): Response
    {
        if (null === $siretInfo = SiretCustomerQuery::create()->findOneByCustomerId($customerId)) {
            $siretInfo = (new SiretCustomer())->setCustomerId($customerId);
        }

        $form = $this->createForm(CustomerForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $siretInfo
                ->setCodeSiret($data[SiretManagement::SIRET] ?? '')
                ->setCodeTvaIntra($data[SiretManagement::TVA_INTRA] ?? '')
                ->save();

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $ex) {
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $translator->trans('Failed to update customer information', [], SiretManagement::DOMAIN_NAME),
            $error_msg,
            $form
        );

        return $this->generateErrorRedirect($form);
    }
}
