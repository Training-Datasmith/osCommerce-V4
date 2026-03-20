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

class Io_Order_Status extends Io_Map
{
    protected $named = '@order_status';
    public $name;
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        parent::serialize_to($parent);
        static $statuses = [];
        if ($this->value && !isset($statuses[$this->value])) {
            $statuses[$this->value] = \common\helpers\Order::get_order_status_name($this->value, \common\helpers\Language::get_default_language_id());
        }
        if (isset($statuses[$this->value])) {
            $parent->add_attribute('name', $statuses[$this->value]);
        }
    }
    public function to_import_model()
    {
        $parent_result = parent::to_import_model();
        //        if ( !$parentResult && !empty($this->name) && !IOCore::get()->isLocalProject() ) {
        //            // unknown import status id
        //            $newStatusId = IOCore::get()->getLookupTool()->lookupOrderStatus($this->name, true);
        //            if ( $newStatusId ) {
        //                $this->internalId = $newStatusId;
        //                $this->value = $newStatusId;
        //                $parentResult = $newStatusId;
        //                IOCore::get()->getAttributeMapper()->mapIds($this, $this->internalId, $this->externalId);
        //            }
        //        }
        return $parent_result;
    }
}