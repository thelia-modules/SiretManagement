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

namespace SiretManagement\Form;

use Thelia\Form\BaseForm;

class CustomerForm extends BaseForm
{
    use CustomerFormManagementTrait;

    protected function getDispatcher()
    {
        return $this->dispatcher;
    }

    protected function buildForm(): void
    {
        $this->setupCustomerForm($this->formBuilder);
    }

    public static function getName(): string
    {
        return 'siretmanagement_customer_form';
    }
}
