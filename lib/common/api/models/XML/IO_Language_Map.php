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
        if (Io_Core::get()->is_local_project()) {
            // force lang mapping for local projects
            if (!empty($this->language)) {
                $arr = \common\helpers\Language::get_language_id($this->language);
                $new_id = $arr['languages_id'] ?? null;
                if ($new_id) {
                    $this->value = $new_id;
                    Io_Core::get()->get_attribute_mapper()->map_ids($this, $new_id, $this->internal_id);
                } else {
                    $this->value = null;
                }
            }
            return $this->value;
        }
        $parent_result = parent::to_import_model();
        if (!$parent_result && !empty($this->language) && !Io_Core::get()->is_local_project()) {
            $new_id = \common\classes\language::get_id($this->language);
            if ($new_id) {
                $this->internal_id = $new_id;
                $this->value = $new_id;
                $parent_result = $new_id;
                Io_Core::get()->get_attribute_mapper()->map_ids($this, $this->internal_id, $this->external_id);
            }
        }
        return $parent_result;
    }
}