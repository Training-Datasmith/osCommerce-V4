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
namespace backend\components;

trait Sources_Search_Trait
{
    public function action_sources()
    {
        $this->layout = false;
        $term = \Yii::$app->request->get('term');
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $query1 = (new \yii\db\Query())->select('source as `text`, source AS label')->from(\common\models\Products::table_name())->where(['!=', 'source', ''])->and_filter_where(['LIKE', 'source', $term]);
        $query2 = (new \yii\db\Query())->select('source as `text`, source AS label')->from(\common\models\Suppliers_Products::table_name())->where(['!=', 'source', ''])->and_filter_where(['LIKE', 'source', $term]);
        $union_query = (new \yii\db\Query())->from(['dummy_name' => $query1->union($query2)])->order_by(['label' => SORT_ASC]);
        \Yii::$app->response->data = $union_query->all();
    }
}