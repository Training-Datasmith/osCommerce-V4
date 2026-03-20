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
return ['Header' => 'site/products_options', 'Data' => ['common\models\ProductsOptions' => [
    'xmlCollection' => 'ProductsOptions>ProductsOption',
    'orderBy' => ['products_options_id' => 'ASC'],
    /*'softGroup' => [
          'column' => 'products_options_id',
      ],*/
    'properties' => ['products_options_id' => ['class' => 'IOPK'], 'language_id' => ['class' => 'IOLanguageMap'], 'products_options_image' => ['class' => 'IOAttachment', 'location' => '@images']],
    'withRelated' => ['values' => ['xmlCollection' => 'OptionValues>OptionValue', 'properties' => ['products_options_values_id' => ['class' => 'IOPK'], 'language_id' => ['class' => 'IOLanguageMap'], 'products_options_values_image' => ['class' => 'IOAttachment', 'location' => '@images']], 'beforeImportSave' => function ($model, $data) {
        \common\helpers\Assert::instance_of($model, \common\models\Products_Options_Values::class);
        if (empty($model->products_options_values_id) && !$data->skipped) {
            $new_id = $model::next_id();
            $data->data['products_options_values_id']->internal_id = $new_id;
            $data->data['products_options_values_id']->value = $new_id;
            $model->products_options_values_id = $new_id;
        }
    }, 'afterImport' => function ($model, $data) {
        \common\helpers\Assert::instance_of($model, \common\models\Products_Options_Values::class);
        // forcing map id, because it isn't autoinc and was not saved automatically into RelatedSerialize->importModel()
        $data->data['products_options_values_id']->after_import_model($model->products_options_values_id);
        //                            $po2pov_model = new \common\models\ProductsOptions2ProductsOptionsValues();
        //                            $po2pov_model->products_options_id = $products_options_id;
        //                            $po2pov_model->products_options_values_id = $model->products_options_values_id;
        //                            try {
        //                                $po2pov_model->save(false);
        //                            } catch (\Exception $e) {
        //                                \OscLink\Logger::get()->log('Error saving ProductsOptions2ProductsOptionsValues: '.$e->getMessage() );
        //                            }
    }]],
    'beforeDelete' => function ($model, $id) {
        $values_query = \common\models\Products_Options2products_Options_Values::find()->select('products_options_values_id')->where(['products_options_id' => $id]);
        \common\models\Products_Options_Values::delete_all(['products_options_values_id' => $values_query]);
        \common\models\Products_Options2products_Options_Values::delete_all(['products_options_id' => $id]);
        \common\models\Products_Options::delete_all(['products_options_id' => $id]);
        return 'deleted';
    },
    'beforeImportSave' => function ($model, $data) {
        \common\helpers\Assert::instance_of($model, \common\models\Products_Options::class);
        if (empty($model->products_options_id && !$data->skipped)) {
            $new_id = $model::next_id();
            $data->data['products_options_id']->internal_id = $new_id;
            $data->data['products_options_id']->value = $new_id;
            $model->products_options_id = $new_id;
        }
    },
    'afterImport' => function ($model, $data) {
        \common\helpers\Assert::instance_of($model, \common\models\Products_Options::class);
        // forcing map id, because it isn't autoinc and was not saved automatically into RelatedSerialize->importModel()
        $data->data['products_options_id']->after_import_model($model->products_options_id);
        \common\models\Products_Options::delete_all(['language_id' => 0]);
        \common\models\Products_Options_Values::delete_all(['language_id' => 0]);
        if (isset($data->data['products_options_id']) && is_object($data->data['products_options_id']) && !$data->skipped) {
            $products_options_id = $data->data['products_options_id']->internal_id;
            if (isset($data->data['values']) && is_object($data->data['values']) && is_array($data->data['values']->data)) {
                foreach ($data->data['values']->data as $value) {
                    if (isset($value->data['products_options_values_id']) && is_object($value->data['products_options_values_id']) && !$value->skipped) {
                        $products_options_values_id = $value->data['products_options_values_id']->internal_id;
                        if ($products_options_id > 0 && $products_options_values_id > 0) {
                            $po2pov_model = \common\models\Products_Options2products_Options_Values::find_one(['products_options_id' => $products_options_id, 'products_options_values_id' => $products_options_values_id]);
                            if (!$po2pov_model) {
                                $po2pov_model = new \common\models\Products_Options2products_Options_Values();
                                $po2pov_model->products_options_id = $products_options_id;
                                $po2pov_model->products_options_values_id = $products_options_values_id;
                                try {
                                    $po2pov_model->save(false);
                                } catch (\Exception $e) {
                                    \Osc_Link\Logger::print('Error saving ProductsOptions2ProductsOptionsValues: ' . $e->get_message());
                                }
                            }
                        }
                    }
                }
            }
        }
    },
]], 'covered_tables' => ['products_options', 'products_options_values', 'products_options_values_to_products_options']];