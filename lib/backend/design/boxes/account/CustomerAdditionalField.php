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
namespace backend\design\boxes\account;

use yii\base\Widget;
class Customer_Additional_Field extends Widget
{
    public $id;
    public $params;
    public $settings;
    public $visibility;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (!\common\helpers\Acl::check_extension_allowed('CustomerAdditionalFields')) {
            return '';
        }
        global $languages_id;
        $fields = \common\extensions\Customer_Additional_Fields\models\Additional_Fields::find()->alias('f')->select('f.*, fd.title, gd.title as group_title')->left_join('additional_fields_description fd', 'fd.additional_fields_id = f.additional_fields_id')->left_join('additional_fields_group_description gd', 'gd.additional_fields_group_id = f.additional_fields_group_id')->where('fd.language_id = ' . $languages_id . ' and gd.language_id = ' . $languages_id)->order_by(['additional_fields_group_id' => SORT_ASC, 'sort_order' => SORT_ASC])->as_array()->all();
        $fields_by_group = [];
        foreach ($fields as $field) {
            $fields_by_group[$field['group_title']][$field['additional_fields_id']] = $field['title'];
        }
        return $this->render('../../views/account/customer-additional-field.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'fieldsByGroup' => $fields_by_group]);
    }
}