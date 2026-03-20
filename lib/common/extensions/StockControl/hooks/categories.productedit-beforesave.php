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
/**
 * @var $productModel common\models\Products
 * @var $TabAccess \backend\models\ProductEdit\TabAccess
 */
if (($ext = \common\helpers\Extensions::is_allowed('StockControl')) && $tab_access->tab_data_save('TEXT_MAIN_DETAILS') && $product_model->return_product_type() == 'product') {
    $ext::save_product($product_model);
}