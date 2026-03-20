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

abstract class Complex
{
    protected $named;
    public $table;
    public $attribute;
    public $value;
    public function get_map_name()
    {
        return !empty($this->named) ? $this->named : $this->table . '.' . $this->attribute;
    }
    public function set_map($table, $attribute)
    {
        $this->table = $table;
        $this->attribute = $attribute;
    }
    public function serialize_to(\Simple_Xml_Element $parent)
    {
    }
    public static function restore_from(\Simple_Xml_Element $node, $obj)
    {
        return strval($node);
    }
    public function to_import_model()
    {
        return $this->value;
    }
}