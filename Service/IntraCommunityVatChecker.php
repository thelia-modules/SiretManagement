<?php
/*************************************************************************************/
/*      Copyright (c) OpenStudio                                                     */
/*      web : https://www.openstudio.fr                                              */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 29/09/2023
 */

namespace SiretManagement\Service;

use Thelia\Core\Translation\Translator;

class IntraCommunityVatChecker
{
    public function check($vatNumber)
    {
        // borrowed from https://www.oreilly.com/library/view/regular-expressions-cookbook/9781449327453/ch04s21.html
        $regexpElements = [
            "(AT)?U[0-9]{8}", # Austria
            "(BE)?0[0-9]{9}", # Belgium
            "(BG)?[0-9]{9,10}", # Bulgaria
            "(CY)?[0-9]{8}L", # Cyprus
            "(CZ)?[0-9]{8,10}", # Czech Republic
            "(DE)?[0-9]{9}", # Germany
            "(DK)?[0-9]{8}", # Denmark
            "(EE)?[0-9]{9}", # Estonia
            "(EL|GR)?[0-9]{9}", # Greece
            "(ES)?[0-9A-Z][0-9]{7}[0-9A-Z]", # Spain
            "(FI)?[0-9]{8}", # Finland
            "(FR)?[0-9A-Z]{2}[0-9]{9}", # France
            "(GB)?([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3})", # United Kingdom
            "(HU)?[0-9]{8}", # Hungary
            "(IE)?[0-9]S[0-9]{5}L", # Ireland
            "(IT)?[0-9]{11}", # Italy
            "(LT)?([0-9]{9}|[0-9]{12})", # Lithuania
            "(LU)?[0-9]{8}", # Luxembourg
            "(LV)?[0-9]{11}", # Latvia
            "(MT)?[0-9]{8}", # Malta
            "(NL)?[0-9]{9}B[0-9]{2}", # Netherlands
            "(PL)?[0-9]{10}", # Poland
            "(PT)?[0-9]{9}", # Portugal
            "(RO)?[0-9]{2,10}", # Romania
            "(SE)?[0-9]{12}", # Sweden
            "(SI)?[0-9]{8}", # Slovenia
            "(SK)?[0-9]{10}", # Slovakia
        ];

        $regexp = '/^(' . implode('|', $regexpElements) . ')$/';

        if (! preg_match($regexp, $vatNumber)) {
            throw new \InvalidArgumentException(
                Translator::getInstance()->trans("This Intra-Community VAT Number seems invalid.")
            );
        }

        return $vatNumber;
    }
}
