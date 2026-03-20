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

use backend\models\EP\Tools;
class Io_Country_Map extends Io_Map
{
    protected $named = '@country';
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        parent::serialize_to($parent);
        static $iso_codes = [];
        if ($this->value && !isset($iso_codes[$this->value])) {
            $iso_codes[$this->value] = false;
            $country_info = \common\helpers\Country::get_country_info_by_id($this->value);
            $iso_codes[$this->value] = $country_info['countries_iso_code_2'];
        }
        if (isset($iso_codes[$this->value])) {
            $parent->add_attribute('iso2', $iso_codes[$this->value]);
        }
    }
    public static function restore_from(\Simple_Xml_Element $node, $obj)
    {
        $parent_result = parent::restore_from($node, $obj);
        if (empty($parent_result->value) && isset($node['iso2']) && (string) $node['iso2'] != '') {
            $from_iso2 = (string) $node['iso2'];
            $tools = new Tools();
            $internal_id = $tools->get_country_id($from_iso2);
            if ($internal_id) {
                $parent_result->internal_id = $internal_id;
                $parent_result->value = $internal_id;
                Io_Core::get()->get_attribute_mapper()->map_ids($parent_result, $parent_result->internal_id, $parent_result->external_id);
            } else {
                \Osc_Link\Logger::printf("Country not found: ISO2={$from_iso2} externalID=%s", $node['internalId'] ?? null);
            }
        }
        return $parent_result;
    }
}