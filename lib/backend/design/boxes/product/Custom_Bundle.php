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
namespace backend\design\boxes\product;

use common\helpers\Translation;
use yii\base\Widget;
class Custom_Bundle extends Widget
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
        global $languages_id;
        $xsell_type_variants = [0 => Translation::get_translation_value('FIELDSET_ASSIGNED_XSELL_PRODUCTS', 'admin/categories')];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UpSell')) {
            $tmp = $ext::get_xsell_type_list();
        } else {
            $tmp = null;
        }
        if (is_array($tmp)) {
            $xsell_type_variants += $tmp;
        }
        $platform_list = \common\classes\platform::get_list();
        return $this->render('../../views/custom-bundle.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'xsellTypeVariants' => $xsell_type_variants, 'platformList' => $platform_list]);
    }
}