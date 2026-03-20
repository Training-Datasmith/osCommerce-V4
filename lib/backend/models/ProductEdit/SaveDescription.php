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

use common\helpers\Seo;
use common\models\Products;
use yii;
class Save_Description
{
    protected $product;
    protected $department_id = 0;
    public function __construct(Products $product, $selected_department_id)
    {
        $this->product = $product;
        $this->department_id = (int) $selected_department_id;
    }
    public function save()
    {
        $products_id = $this->product->products_id;
        $selected_department_id = $this->department_id;
        $languages = \common\helpers\Language::get_languages();
        $platforms = \common\models\Platforms::get_platforms_by_type('non-virtual')->all();
        if ($platforms) {
            $posted_description = Yii::$app->request->post('pDescription', []);
            foreach ($platforms as $platform) {
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    $language_id = $languages[$i]['id'];
                    if (isset($posted_description[$platform->platform_id][$language_id])) {
                        $p_description = \common\models\Products_Description::find()->where(['products_id' => $products_id, 'language_id' => $language_id, 'platform_id' => $platform->platform_id, 'department_id' => $selected_department_id])->one();
                        if (!is_object($p_description)) {
                            $p_description = \common\models\Products_Description::create($products_id, $language_id, $platform->platform_id, $selected_department_id);
                        }
                        if ($selected_department_id > 0) {
                            $p_description_original = \common\models\Products_Description::find()->where(['products_id' => $products_id, 'language_id' => $language_id, 'platform_id' => $platform->platform_id, 'department_id' => 0])->one();
                            if (is_object($p_description_original)) {
                                if ($p_description_original->products_name == ($posted_description[$platform->platform_id][$language_id]['products_name'] ?? '')) {
                                    $posted_description[$platform->platform_id][$language_id]['products_name'] = '';
                                }
                                if ($p_description_original->products_internal_name == ($posted_description[$platform->platform_id][$language_id]['products_internal_name'] ?? '')) {
                                    $posted_description[$platform->platform_id][$language_id]['products_internal_name'] = '';
                                }
                                if ($p_description_original->products_description_short == ($posted_description[$platform->platform_id][$language_id]['products_description_short'] ?? '')) {
                                    $posted_description[$platform->platform_id][$language_id]['products_description_short'] = '';
                                }
                                if ($p_description_original->products_description == ($posted_description[$platform->platform_id][$language_id]['products_description'] ?? '')) {
                                    $posted_description[$platform->platform_id][$language_id]['products_description'] = '';
                                }
                                //$posted_description[$platform->platform_id][$language_id]['products_seo_page_name'] = $pDescriptionOriginal->products_seo_page_name;
                            }
                        }
                        if (isset($posted_description[$platform->platform_id][$language_id]['products_h2_tag']) && is_array($posted_description[$platform->platform_id][$language_id]['products_h2_tag'])) {
                            $posted_description[$platform->platform_id][$language_id]['products_h2_tag'] = implode("\n", $posted_description[$platform->platform_id][$language_id]['products_h2_tag']);
                        }
                        if (isset($posted_description[$platform->platform_id][$language_id]['products_h3_tag']) && is_array($posted_description[$platform->platform_id][$language_id]['products_h3_tag'])) {
                            $posted_description[$platform->platform_id][$language_id]['products_h3_tag'] = implode("\n", $posted_description[$platform->platform_id][$language_id]['products_h3_tag']);
                        }
                        $p_description->overwrite_head_title_tag = (int) ($posted_description[$platform->platform_id][$language_id]['overwrite_head_title_tag'] ?? null);
                        $p_description->overwrite_head_desc_tag = (int) ($posted_description[$platform->platform_id][$language_id]['overwrite_head_desc_tag'] ?? null);
                        $p_description->set_attributes($posted_description[$platform->platform_id][$language_id], false);
                        if ($selected_department_id == 0) {
                            $p_description->products_seo_page_name = Seo::make_product_slug($p_description, $this->product);
                            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                                $ext::track_product_links($products_id, $language_id, $platform->platform_id, $p_description->get_attributes(), $p_description->get_old_attributes());
                            }
                        }
                        $p_description->save(false);
                    } else {
                        // not posted - add empty record if not exist
                        $p_description = \common\models\Products_Description::find()->where(['products_id' => $products_id, 'language_id' => $language_id, 'platform_id' => $platform->platform_id, 'department_id' => $selected_department_id])->one();
                        if (!is_object($p_description)) {
                            $p_description = \common\models\Products_Description::create($products_id, $language_id, $platform->platform_id, $selected_department_id);
                            $p_description->load_default_values();
                            $p_description->save(false);
                        }
                    }
                }
            }
        }
    }
}