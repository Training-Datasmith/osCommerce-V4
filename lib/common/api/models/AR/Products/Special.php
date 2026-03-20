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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
use yii\db\Expression;
class Special extends Ep_Map
{
    protected $parent_object;
    protected $child_collections = ['prices' => []];
    public static function table_name()
    {
        return 'specials';
    }
    public static function primary_key()
    {
        return ['specials_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $this->pending_removal = false;
        if (!empty($imported_object->specials_id) && intval($imported_object->specials_id) == intval($this->specials_id)) {
            return true;
        }
        if (!is_null($this->start_date) && $imported_object->start_date === $this->start_date && (!is_null($this->expires_date) && $imported_object->expires_date === $this->expires_date)) {
            return true;
        }
        return false;
    }
    public function import_array($data)
    {
        if ($this->is_new_record) {
            $data['status'] = isset($data['status']) ? $data['status'] ? 1 : 0 : 1;
            $data['start_date'] = isset($data['start_date']) ? strval($data['start_date']) : null;
            $data['expires_date'] = isset($data['expires_date']) ? strval($data['expires_date']) : null;
        }
        if (!is_array($this->child_collections['prices']) || count($this->child_collections['prices']) == 0) {
            $this->init_collection_by_lookup_key_prices(['*']);
        }
        if (array_key_exists('specials_new_products_price', $data)) {
            $def_price_key = \common\helpers\Currencies::system_currency_code() . '_0';
            if (isset($this->child_collections['prices'][$def_price_key]) && !isset($data['prices'][$def_price_key]['specials_new_products_price'])) {
                $data['prices'][$def_price_key]['specials_new_products_price'] = $data['specials_new_products_price'];
            }
        }
        return parent::import_array($data);
    }
    public function init_collection_by_lookup_key_prices($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (true) {
            if (!is_null($this->specials_id)) {
                $db_map_collect = [];
                foreach (Special_Prices::find_all(['specials_id' => $this->specials_id]) as $obj) {
                    $key_code = $obj->currencies_id . '_' . $obj->groups_id;
                    $db_map_collect[$key_code] = $obj;
                }
                foreach (Special_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        $db_key_code = $lookup_pk['currencies_id'] . '_' . $lookup_pk['groups_id'];
                        if (isset($db_map_collect[$db_key_code])) {
                            $this->child_collections['prices'][$key_code] = $db_map_collect[$db_key_code];
                        } else {
                            $lookup_pk['specials_id'] = $this->specials_id;
                            $this->child_collections['prices'][$key_code] = new Special_Prices($lookup_pk);
                        }
                    }
                }
                unset($db_map_collect);
            } else {
                foreach (Special_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    $this->child_collections['prices'][$key_code] = new Special_Prices($lookup_pk);
                }
            }
        } else {
            foreach (Special_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                $this->child_collections['prices'][$key_code] = null;
                if (is_null($this->specials_id)) {
                    $this->child_collections['prices'][$key_code] = new Special_Prices($lookup_pk);
                } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                    if (!isset($this->child_collections['prices'][$key_code])) {
                        $lookup_pk['specials_id'] = $this->specials_id;
                        $this->child_collections['prices'][$key_code] = Special_Prices::find_one($lookup_pk);
                        if (!is_object($this->child_collections['prices'][$key_code])) {
                            $this->child_collections['prices'][$key_code] = new Special_Prices($lookup_pk);
                        }
                    }
                }
            }
        }
        return $this->child_collections['prices'];
    }
    public function before_save($insert)
    {
        if ($insert && empty($this->specials_date_added)) {
            $this->specials_date_added = new \yii\db\Expression('NOW()');
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        if ($insert && defined('SALE_STRICT_DATE') && SALE_STRICT_DATE == 'True') {
            if (!is_null($this->start_date) || !is_null($this->expires_date)) {
                $this->refresh();
                // ------|==|-----------// --------------------- 1
                // -------|=====|-------// -----------|=|------- 2
                // ---|=====|-----------// ---|=|--------------- 3
                // =========|-----------// =====|--------------- 4
                // ---------|===========// -----------|========= 5
                // --|===========|------// --|==|-----|==|------ 6 => 3
                //+-----|+++++|---------//+-----|+++++|---------
                $check_collection = static::find()->where(['AND', ['products_id' => $this->products_id], ['!=', 'specials_id', $this->specials_id]])->and_where(['OR', ['status' => 1], ['>=', 'start_date', new Expression('NOW()')]])->all();
                foreach ($check_collection as $check_model) {
                    $db_special = ['s' => strtotime($check_model->start_date), 'e' => strtotime($check_model->expires_date)];
                    $new_special = ['s' => strtotime($this->start_date), 'e' => strtotime($this->expires_date)];
                    //echo '<pre>NEW '; var_dump(date(DATE_ATOM, $newSpecial['s']), date(DATE_ATOM, $newSpecial['e'])); echo '</pre>';
                    //echo '<pre>DB '; var_dump(date(DATE_ATOM, $dbSpecial['s']), date(DATE_ATOM, $dbSpecial['e'])); echo '</pre>';
                    if (intval($new_special['s']) > 0 && intval($new_special['e']) > 0) {
                        if (intval($db_special['s']) > 0 && intval($db_special['e']) > 0) {
                            //123
                            if ($db_special['s'] >= $new_special['s'] && $db_special['e'] <= $new_special['e']) {
                                //1
                                $check_model->start_date = null;
                                $check_model->expires_date = null;
                                if ($this->status) {
                                    $check_model->status = 0;
                                }
                            } else if ($db_special['s'] > $new_special['s'] && $db_special['e'] > $new_special['e']) {
                                //2
                                $check_model->start_date = date('Y-m-d H:i:s', $new_special['e'] + 1);
                            } elseif ($db_special['s'] < $new_special['s'] && $new_special['s'] < $db_special['e'] && $db_special['e'] < $new_special['e']) {
                                //3
                                $check_model->expires_date = date('Y-m-d H:i:s', $new_special['s'] - 1);
                            }
                        } elseif (intval($db_special['s']) > 0 && intval($db_special['e']) == 0) {
                            //5
                            if ($new_special['e'] > $db_special['s']) {
                                $check_model->start_date = date('Y-m-d H:i:s', $new_special['e'] + 1);
                            }
                        } elseif (intval($db_special['s']) == 0 && intval($db_special['e']) > 0) {
                            //4
                            if ($db_special['e'] > $new_special['s']) {
                                $check_model->expires_date = date('Y-m-d H:i:s', $new_special['s'] - 1);
                            }
                        } elseif (intval($db_special['s']) == 0 && intval($db_special['e']) == 0) {
                        }
                    } elseif (intval($new_special['s']) > 0 && intval($new_special['e']) == 0) {
                        // ------|==|-----------// --------------------- 1
                        // -------|=====|-------// --------------------- 2
                        // ---|=====|-----------// ---|=|--------------- 3
                        // =========|-----------// =====|--------------- 4
                        // ---------|===========// --------------------- 5
                        //+-----|+++++++++++++++//+-----|+++++++++++++++
                        if (intval($db_special['s']) > 0) {
                            //(1 2 3 5)
                            if (intval($db_special['s']) < intval($new_special['s'])) {
                                //3
                                if (intval($db_special['e']) > intval($new_special['e'])) {
                                    $check_model->expires_date = date('Y-m-d H:i:s', $new_special['s'] - 1);
                                }
                            } else {
                                //1 2 5
                                $check_model->start_date = null;
                                $check_model->expires_date = null;
                            }
                        } elseif (intval($db_special['e']) > 0) {
                            // (4)
                            if (intval($db_special['e']) > intval($new_special['s'])) {
                                //4
                                $check_model->expires_date = date('Y-m-d H:i:s', $new_special['s'] - 1);
                            }
                        } else {
                            // status
                        }
                    } elseif (intval($new_special['s']) == 0 && intval($new_special['e']) > 0) {
                        //expire
                        // ------|==|-----------// ------|==|----------- 1
                        // -------|=====|-------// -------|=====|------- 2
                        // ---|=====|-----------// -----|===|----------- 3
                        // =========|-----------// -----|===|----------- 4
                        // ---------|===========// ---------|=========== 5
                        //++++++|---------------//++++++|---------------
                        if (intval($db_special['s']) > 0 && intval($db_special['s']) < intval($new_special['e'])) {
                            //3
                            $check_model->start_date = date('Y-m-d H:i:s', $new_special['e'] + 1);
                        }
                        if (intval($db_special['e']) > 0 && intval($db_special['e']) < intval($new_special['e'])) {
                            //4
                            $check_model->expires_date = date('Y-m-d H:i:s', $new_special['e'] + 1);
                        }
                    }
                    $check_model->save();
                }
                $this->parent_object->initiate_after_save('Product::SpecialClean');
            }
            //            die;
            /*
            $this->refresh();
            if ( $this->start_date ) {}
            if ( $this->expires_date ) {}
            */
        }
        parent::after_save($insert, $changed_attributes);
    }
    /*public function afterSave($insert, $changedAttributes)
        {
            parent::afterSave($insert, $changedAttributes);
    
            foreach (SpecialPrices::getAllKeyCodes() as $pk){
                if ( !$insert && empty($pk['groups_id']) ) continue;
    
                $pk['specials_id'] = $this->specials_id;
                $priceModel = SpecialPrices::findOne($pk);
                if ( !$priceModel ) $priceModel = new SpecialPrices($pk);
                $priceModel->specials_new_products_price = empty($pk['groups_id'])?$this->specials_new_products_price:-2;
                $priceModel->save(false);
            }
        }*/
    public function before_delete()
    {
        if (!parent::before_delete()) {
            return false;
        }
        Special_Prices::delete_all(['specials_id' => $this->specials_id]);
        return true;
    }
}