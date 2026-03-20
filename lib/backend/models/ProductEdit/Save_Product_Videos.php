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

use backend\design\Uploads;
use common\models\Products;
use yii;
class Save_Product_Videos
{
    protected $product;
    protected $uploads_directory = '';
    public function __construct(Products $product, $path_to_uploads)
    {
        $this->product = $product;
        $this->uploads_directory = $path_to_uploads;
    }
    public function save()
    {
        $languages = \common\helpers\Language::get_languages();
        $products_id = $this->product->products_id;
        $path = $this->uploads_directory;
        $images_directory = DIR_WS_IMAGES . 'products' . DIRECTORY_SEPARATOR . $products_id . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR;
        $video = Yii::$app->request->post('video');
        $video_type = Yii::$app->request->post('video_type');
        $video_id = Yii::$app->request->post('video_id');
        //tep_db_query("delete from " . TABLE_PRODUCTS_VIDEOS . " where products_id  = '" . (int) $productsId . "'");
        $products_videos = \common\models\Products_Videos::find()->where(['products_id' => $products_id])->all();
        foreach ($products_videos as $products_video) {
            if (empty($video_id[$products_video->language_id]) || !is_array($video_id) || !in_array($products_video->video_id, $video_id[$products_video->language_id])) {
                $products_video->delete();
                $images_path = \common\classes\Images::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR . $products_id . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR;
                if ($products_video->video && is_file($images_path . $products_video->video)) {
                    @unlink($images_path . $products_video->video);
                }
            }
        }
        if (!is_array($video)) {
            return;
        }
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $language_id = $languages[$i]['id'];
            if (!isset($video[$language_id]) || !is_array($video[$language_id])) {
                continue;
            }
            foreach ($video[$language_id] as $key => $item) {
                if (!$item) {
                    continue;
                }
                if ($video_id[$language_id][$key]) {
                    continue;
                }
                if ($video_type[$language_id][$key] == '1') {
                    $item = Uploads::move($item, $images_directory, false);
                }
                if ($video_id[$language_id][$key]) {
                    $products_videos = \common\models\Products_Videos::find_one(['video_id' => $video_id[$language_id][$key]]);
                } else {
                    $products_videos = new \common\models\Products_Videos();
                }
                $products_videos->products_id = $products_id;
                $products_videos->video = $item;
                $products_videos->language_id = $language_id;
                $products_videos->type = $video_type[$language_id][$key];
                $products_videos->save();
            }
        }
    }
}