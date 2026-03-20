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
namespace common\components;

use common\models\Information;
use yii\base\Model;
use yii\caching\Tag_Dependency;
use yii\helpers\Array_Helper;
class Information_Page extends Model
{
    public static function get_frontend_data($page_id, $language_id = null, $platform_id = null)
    {
        if (empty($language_id)) {
            $language_id = \Yii::$app->settings->get('languages_id');
        }
        if (empty($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $platform_ids = [];
        $platform_ids[] = (int) $platform_id;
        if ((int) $platform_id != \common\classes\platform::default_id()) {
            $platform_ids[] = (int) \common\classes\platform::default_id();
        }
        $cache_key = 'information_' . (int) $page_id . '_' . (int) $language_id . '@' . implode('_', $platform_ids);
        $info_data = \Yii::$app->get_cache()->get_or_set($cache_key, function () use ($page_id, $language_id, $platform_ids) {
            $info_data = Information::find()->where(['information_id' => (int) $page_id, 'languages_id' => $language_id])->and_where(['affiliate_id' => 0])->and_where(['IN', 'platform_id', $platform_ids])->as_array()->all();
            return Array_Helper::index($info_data, 'platform_id');
        }, 0, new Tag_Dependency(['tags' => ['information', 'information_page_' . (int) $page_id]]));
        $result_data = false;
        if (!isset($info_data[$platform_id])) {
            if (isset($info_data[\common\classes\platform::default_id()])) {
                $result_data = $info_data[\common\classes\platform::default_id()];
            }
        } else {
            $result_data = $info_data[$platform_id];
            foreach (Information::merge_description_column_list() as $column) {
                if (strlen($result_data[$column]) == 0) {
                    $result_data[$column] = $info_data[\common\classes\platform::default_id()][$column];
                }
            }
        }
        return $result_data;
    }
    public static function get_frontend_data_visible($page_id, $language_id = null, $platform_id = null)
    {
        $data = static::get_frontend_data($page_id, $language_id, $platform_id);
        if (is_array($data) && $data['visible']) {
            return $data;
        }
        return false;
    }
    public static function find_page_id_by_seo($seo_path, $language_id = null, $platform_id = null)
    {
        if (empty($language_id)) {
            $language_id = \Yii::$app->settings->get('languages_id');
        }
        if (empty($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $data = Information::find()->where(['seo_page_name' => $seo_path, 'platform_id' => (int) $platform_id])->select(['information_id', 'languages_id', 'visible'])->as_array()->order_by([new \yii\db\Expression("IF(languages_id='" . (int) $language_id . "', 0, 1)")])->limit(1)->one();
        if (is_array($data)) {
            if ($data['visible']) {
                return $data;
            }
        } else {
            $data = Information::find()->alias('i')->where(['i.seo_page_name' => $seo_path, 'i.platform_id' => \common\classes\platform::default_id()])->join('left join', Information::table_name() . ' pi', "pi.information_id=i.information_id and pi.languages_id=i.languages_id and pi.platform_id='" . (int) $platform_id . "' and pi.affiliate_id=i.affiliate_id")->select(['i.information_id', 'i.languages_id', 'def_seo' => 'i.seo_page_name', 'p_seo' => 'pi.seo_page_name', 'visible' => new \yii\db\Expression('IFNULL(pi.visible,i.visible)')])->as_array()->order_by([new \yii\db\Expression("IF(i.languages_id='" . (int) $language_id . "', 0, 1)")])->limit(1)->one();
            if (is_array($data) && $data['visible']) {
                if (empty($data['p_seo'])) {
                    return $data;
                }
            }
        }
        return false;
    }
}