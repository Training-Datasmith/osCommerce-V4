<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\api\models\XML;

class Io_Platform_Map extends Complex
{
    protected $name = '@platform';
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        if (!empty($this->value)) {
            $parent->add_attribute('internalId', $this->value);
            $external_id = Io_Core::get()->get_attribute_mapper()->external_id($this);
            if (is_numeric($external_id)) {
                $parent->add_attribute('externalId', $external_id);
            }
        }
    }
}