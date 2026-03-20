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
namespace backend\models\Product_Edit;

use backend\models\EP\Directory;
use common\models\Products;
class Save_Import_Export
{
    protected $product;
    public function __construct(Products $product)
    {
        $this->product = $product;
    }
    public function save()
    {
        $directories = Directory::get_all();
        foreach ($directories as $directory) {
            /**
             * @var Directory $directory
             */
            if ($directory->directory_type == 'datasource' && $datasource = $directory->get_datasource()) {
                if (!$datasource->allow_product_view()) {
                    continue;
                }
                $datasource->product_save($directory, $this->product);
            }
        }
    }
}