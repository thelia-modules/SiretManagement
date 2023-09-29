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

/*      web : https://www.openstudio.fr */

/*      For the full copyright and license information, please view the LICENSE */
/*      file that was distributed with this source code. */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 29/09/2023.
 */

namespace SiretManagement\Event;

use Thelia\Core\Event\ActionEvent;

class CheckDataEvent extends ActionEvent
{
    protected $dataToCheck;
    protected $data;
    protected $error;

    public function __construct($siret)
    {
        $this->dataToCheck = $siret;
    }

    public function isValid() : bool
    {
        return $this->error === null && $this->data !== null;
    }

    /**
     * @return mixed
     */
    public function getDataToCheck()
    {
        return $this->dataToCheck;
    }

    /**
     * @param mixed $dataToCheck
     *
     * @return $this
     */
    public function setDataToCheck($dataToCheck)
    {
        $this->dataToCheck = $dataToCheck;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     *
     * @return $this
     */
    public function setError($error)
    {
        $this->error = $error;

        return $this;
    }
}
