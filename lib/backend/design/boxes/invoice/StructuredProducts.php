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
namespace backend\design\boxes\invoice;

use yii\base\Widget;
class Structured_Products extends Widget
{
    public $id;
    public $params;
    public $settings;
    public $visibility;
    public $base_columns = [];
    public $extended_columns = [];
    public function init()
    {
        parent::init();
        \common\helpers\Translation::init('invoice');
        \common\helpers\Translation::init('admin/main');
        $this->base_columns = ['column_qty' => ENTRY_INVOICE_QTY, 'column_name' => TEXT_NAME, 'column_model' => TEXT_MODEL, 'column_tax' => TABLE_HEADING_TAX, 'column_price_inc_tax' => TABLE_HEADING_PRICE_INCLUDING_TAX, 'column_total_exc_tax' => TABLE_HEADING_TOTAL_EXCLUDING_TAX, 'column_total_inc_tax' => TABLE_HEADING_TOTAL_INCLUDING_TAX];
        $this->extended_columns = ['column_ean' => 'strtoupper', 'column_upc' => 'strtoupper', 'column_asin' => 'strtoupper', 'column_isbn' => 'strtoupper', 'column_model_barcode' => TEXT_MODEL . ' ' . TEXT_BARCODE, 'column_ean_barcode' => 'EAN ' . TEXT_BARCODE, 'column_upc_barcode' => 'UPC ' . TEXT_BARCODE, 'column_asin_barcode' => 'ASIN ' . TEXT_BARCODE, 'column_isbn_barcode' => 'ISBN ' . TEXT_BARCODE];
    }
    public function run()
    {
        return $this->render('../../views/invoice/structured-products.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'attribute' => $this]);
    }
    public function get_key($subject)
    {
        return preg_replace('/column_/', '', $subject);
    }
    public function get_label($subject)
    {
        if (isset($this->extended_columns[$subject])) {
            if (is_callable($this->extended_columns[$subject])) {
                return call_user_func($this->extended_columns[$subject], $this->get_key($subject));
            } else {
                return $this->extended_columns[$subject];
            }
        }
        if (isset($this->base_columns[$subject]) && !empty($this->base_columns[$subject])) {
            return $this->base_columns[$subject];
        }
        $subject = $this->get_key($subject);
        return defined('TEXT_' . strtoupper($subject)) ? constant('TEXT_' . strtoupper($subject)) : \yii\helpers\Inflector::humanize($subject, '_');
    }
    public function get_more_coulmns()
    {
        $columns = array_keys($this->base_columns);
        $list = array_diff(array_keys($this->extended_columns), $columns);
        $attr = $this;
        return array_combine($list, array_map(function ($item) use ($attr) {
            return $this->get_label($item);
        }, $list));
    }
    public function get_disabled_columns()
    {
        if ($this->settings[0]['sort_order']) {
            $columns = explode(';', $this->settings[0]['sort_order']);
        } else {
            $columns = array_keys($this->base_columns);
        }
        $list = array_intersect($columns, array_keys($this->extended_columns));
        return array_fill_keys($list, ['disabled' => true]);
    }
}