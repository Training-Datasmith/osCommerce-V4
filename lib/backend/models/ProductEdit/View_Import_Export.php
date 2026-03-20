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
class View_Import_Export
{
    /**
     * @var \objectInfo
     */
    protected $product_info_ref;
    protected $list = [];
    public function __construct($product_info)
    {
        $this->product_info_ref = $product_info;
        //$this->wrap($this->productInfoRef);
        $directories = Directory::get_all();
        foreach ($directories as $directory) {
            /**
             * @var Directory $directory
             */
            if ($directory->directory_type == 'datasource' && $datasource = $directory->get_datasource()) {
                if (!$datasource->allow_product_view()) {
                    continue;
                }
                $view = $datasource->product_view(['directory' => $directory, 'productInfo' => $product_info]);
                if ($view) {
                    $this->list[] = ['datasource' => $datasource, 'directory_name' => $directory->directory, 'title' => $datasource->get_name(), 'content' => $view];
                }
            }
        }
    }
    public function has_tabs()
    {
        return count($this->list);
    }
    public function tab_list()
    {
        return $this->list;
    }
}