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

use yii;
trait Location_Search_Trait
{
    public function action_address_state(): void
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $zones = [];
        $zones_query_active = \common\models\Zones::find()->where(['zone_country_id' => $country])->andfilter_where(['like', 'zone_name', $term])->order_by('zone_name')->as_array()->all();
        foreach ($zones_query_active as $response) {
            $zones[] = $response['zone_name'];
        }
        echo json_encode($zones);
    }
    public function action_address_city(): void
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $state = tep_db_prepare_input(Yii::$app->request->get('state', ''));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $out_data = Yii::$app->request->get('out_data', []);
        $cities = [];
        $cities_query_active = \common\models\Cities::find()->alias('c')->filter_where(['c.city_country_id' => $country])->join('left join', \common\models\Zones::table_name() . ' z', 'z.zone_id=c.city_zone_id')->and_filter_where(['like', 'c.city_name', $term])->order_by('c.city_name')->select(['c.city_name', 'z.zone_name']);
        if ($state) {
            $zones_query_active = clone $cities_query_active;
            $zones_query_active->and_filter_where(['z.zone_name' => $state]);
            if ($zones_query_active->count() > 0) {
                $cities_query_active = $zones_query_active;
            }
        }
        if (count($out_data) > 0) {
            $cities_query_active->join('left join', \common\models\Countries::table_name() . ' cc', "cc.countries_id=c.city_country_id and cc.language_id='" . \Yii::$app->settings->get('languages_id') . "'");
            $cities_query_active->add_select(['c.city_id', 'cc.countries_id', 'cc.countries_name', 'z.zone_id']);
        }
        foreach ($cities_query_active->as_array()->all() as $response) {
            if (count($out_data) > 0) {
                $cities[] = ['value' => $response['city_name'], 'city_id' => $response['city_id'], 'city_name' => $response['city_name'], 'zone_id' => $response['zone_id'], 'zone_name' => $response['zone_name'], 'country_id' => $response['countries_id'], 'country_name' => $response['countries_name']];
            } else {
                $cities[] = ['id' => $response['city_name'], 'value' => $response['city_name'], 'state' => (string) $response['zone_name']];
            }
        }
        echo json_encode($cities);
    }
    public function action_address_postcode(): void
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $addresses = [];
        $search_address = \common\models\Postal_Codes::find()->alias('p')->where(['like', 'postcode', $term . '%', false])->and_filter_where(['country_id' => $country])->join('left join', \common\models\Cities::table_name() . ' c', 'c.city_id=p.city_id')->join('left join', \common\models\Zones::table_name() . ' z', 'z.zone_id=p.zone_id')->order_by(['p.postcode' => SORT_ASC])->select(['p.postcode', 'p.suburb', 'c.city_name', 'z.zone_name']);
        foreach ($search_address->as_array()->all() as $addr) {
            $addresses[] = ['id' => $addr['postcode'], 'value' => $addr['postcode'], 'suburb' => (string) $addr['suburb'], 'city' => (string) $addr['city_name'], 'state' => (string) $addr['zone_name']];
        }
        echo json_encode($addresses);
    }
}