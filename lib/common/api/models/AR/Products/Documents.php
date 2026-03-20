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
use common\api\models\AR\Products\Documents\Title;
class Documents extends Ep_Map
{
    protected $hide_fields = ['products_documents_id', 'products_id'];
    protected $child_collections = ['titles' => []];
    public static function table_name()
    {
        return TABLE_PRODUCTS_DOCUMENTS;
    }
    public static function primary_key()
    {
        return ['products_documents_id'];
    }
    public function init_collection_by_lookup_key_titles($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        foreach (Title::get_all_key_codes() as $key_code => $lookup_pk) {
            $this->child_collections['titles'][$key_code] = null;
            if (is_null($this->products_documents_id)) {
                $this->child_collections['titles'][$key_code] = new Title($lookup_pk);
            } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                if (!isset($this->child_collections['titles'][$key_code])) {
                    $lookup_pk['products_documents_id'] = $this->products_documents_id;
                    $this->child_collections['titles'][$key_code] = Title::find_one($lookup_pk);
                    if (!is_object($this->child_collections['titles'][$key_code])) {
                        $this->child_collections['titles'][$key_code] = new Title($lookup_pk);
                    }
                }
            }
        }
        return $this->child_collections['titles'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->document_types_id) && !is_null($this->document_types_id) && $imported_object->document_types_id == $this->document_types_id && !is_null($imported_object->filename) && !is_null($this->filename) && $imported_object->filename == $this->filename) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function import_array($data)
    {
        if (isset($data['document_types_name'])) {
            $data['document_types_id'] = \backend\models\EP\Tools::get_instance()->get_document_types_by_name($data['document_types_name']);
        }
        if ($this->has_attribute('is_link') && array_key_exists('is_link', $data) && $data['is_link']) {
        } elseif (!empty($data['document_url'])) {
            $document_filename = basename($data['filename']);
            $target_filename = rtrim(DIR_FS_CATALOG, '/') . '/' . 'documents/' . $document_filename;
            $file_time_match = false;
            if (array_key_exists('document_modify_time', $data) && $data['document_modify_time']) {
                $file_time_match = $data['document_modify_time'];
            }
            if (!is_file($target_filename) || $file_time_match !== false && $file_time_match > filemtime($target_filename)) {
                @copy($data['document_url'], $target_filename);
                @chmod($target_filename, 0666);
                if ($file_time_match !== false) {
                    @touch($target_filename, $file_time_match);
                }
            }
            $data['filename'] = $document_filename;
        }
        return parent::import_array($data);
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (count($fields) == 0 || in_array('document_types_name', $fields)) {
            $data['document_types_name'] = \backend\models\EP\Tools::get_instance()->get_document_types_name($this->document_types_id, \common\classes\language::default_id());
        }
        if ($this->has_attribute('is_link') && $this->is_link) {
            $data['document_url'] = $this->filename;
        } else {
            $target_filename = rtrim(DIR_FS_CATALOG, '/') . '/' . 'documents/' . strval($this->filename);
            if (is_file($target_filename)) {
                $data['document_modify_time'] = filemtime($target_filename);
            }
            $data['document_url'] = \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . 'documents/' . $this->filename;
        }
        return $data;
    }
    public function before_delete()
    {
        $target_filename = rtrim(DIR_FS_CATALOG, '/') . '/' . 'documents/' . strval($this->filename);
        if (false && $this->products_documents_id && !empty($this->filename) && is_file($target_filename)) {
            $check_remove_file = true;
            if ($this->has_attribute('is_link') && $this->is_link) {
                $check_remove_file = false;
            }
            if ($check_remove_file) {
                $check_use_in_other = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS_DOCUMENTS . ' ' . "WHERE products_documents_id!='" . intval($this->products_documents_id) . "' " . " AND filename='" . tep_db_input($this->filename) . "'"));
                if ($check_use_in_other['c'] == 0) {
                    @unlink($target_filename);
                }
            }
        }
        return parent::before_delete();
    }
}