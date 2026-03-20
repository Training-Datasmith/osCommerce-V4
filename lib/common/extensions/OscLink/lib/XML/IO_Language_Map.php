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

class Io_Language_Map extends Io_Map
{
    protected $named = '@language';
    public $language;
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        if (!empty($this->value)) {
            $parent->add_attribute('language', \common\classes\language::get_code($this->value));
            $parent->add_attribute('internalId', $this->value);
            $external_id = Io_Core::get()->get_attribute_mapper()->external_id($this);
            if (is_numeric($external_id)) {
                $parent->add_attribute('externalId', $external_id);
            }
        }
    }
    public function to_import_model()
    {
        // don't import this language
        $this->value = null;
        $this->internal_id = null;
        $result = parent::to_import_model();
        // 0 if skip
        if (is_null($result)) {
            if (!empty($this->language)) {
                // force lang mapping
                $new_id = \common\helpers\Language::get_language_id($this->language);
                if ($new_id) {
                    $this->value = $new_id;
                    $this->internal_id = $new_id['languages_id'];
                    Io_Core::get()->get_attribute_mapper()->map_ids($this, $this->internal_id, $this->external_id);
                }
            }
        }
        return $this->value;
    }
    public function after_import_model($value)
    {
        // do nothing
    }
}