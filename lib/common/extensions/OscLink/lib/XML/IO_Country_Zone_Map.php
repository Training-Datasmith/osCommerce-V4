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
namespace Osc_Link\XML;

class Io_Country_Zone_Map extends Io_Map
{
    protected $name = '@country_zone';
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        parent::serialize_to($parent);
        static $zone_info = [];
        if ($this->value && !isset($zone_info[$this->value])) {
            $zone_info[$this->value] = false;
            $zone_query = tep_db_query('select zone_code as code, zone_name as name ' . 'from ' . TABLE_ZONES . ' ' . "where zone_id = '" . (int) $this->value . "'");
            if (tep_db_num_rows($zone_query) > 0) {
                $zone_info[$this->value] = tep_db_fetch_array($zone_query);
            }
        }
        if ($zone_info[$this->value]) {
            if ($zone_info[$this->value]['code']) {
                $parent->add_attribute('code', $zone_info[$this->value]['code']);
            }
            if ($zone_info[$this->value]['name']) {
                $parent->add_attribute('name', $zone_info[$this->value]['name']);
            }
        }
    }
}