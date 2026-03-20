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

use Yii;
/**
 * Search Bulder class.
 */
class Search_Builder
{
    private $_addtional_search = null;
    private $_user_keywords_contain_error = false;
    private $_search_keywords = [];
    private $_parsed_keywords = [];
    private $_msearch_keywords = [];
    private $_relevance_keywords = [];
    private $_search_in_description = false;
    private $_search_internal = false;
    private $_type_search = 'simple';
    public $replace_words = [];
    public $relevance_words = [];
    public $relevance_order = false;
    //faster
    public function __construct($type_seach = 'simple')
    {
        /**
         * @var $ext \common\extensions\PlainProductsDescription\PlainProductsDescription
         */
        if ($ext = \common\helpers\Extensions::is_allowed_and('PlainProductsDescription', 'optionSearchByElements')) {
            $this->_addtional_search = $ext::option_search_by_elements();
        }
        $this->_type_search = $type_seach;
    }
    public function set_search_in_desc($value = false)
    {
        $this->_search_in_description = (bool) $value;
    }
    public function use_search_in_desc()
    {
        return $this->_search_in_description;
    }
    public function set_search_internal($value = false)
    {
        $this->_search_internal = (bool) $value;
    }
    public function use_search_internal()
    {
        return $this->_search_internal;
    }
    public $categories_array = [];
    public $gapis_array = [];
    public $products_array = [];
    public $manufactures_array = [];
    public $information_array = [];
    public $search_in_property = false;
    public $search_in_attributes = false;
    public function prepare_request(string $keywords)
    {
        if (tep_not_null($keywords)) {
            if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'soundex') {
                if (!\common\helpers\Output::parse_search_string($keywords, $this->_search_keywords, false) || !\common\helpers\Output::parse_search_string($keywords, $this->_msearch_keywords, MSEARCH_ENABLE)) {
                    $this->_user_keywords_contain_error = true;
                }
            } else if (!\common\helpers\Output::parse_search_string($keywords, $this->_search_keywords)) {
                $this->_user_keywords_contain_error = true;
            }
        }
        if ($this->_user_keywords_contain_error) {
            $this->_search_keywords = [$keywords];
            $this->_msearch_keywords = [$keywords];
        }
        $this->prepare_keywords_request();
    }
    public function parse_keywords(string $keywords)
    {
        $this->_parsed_keywords = [];
        $keywords = trim(strtolower($keywords));
        $keywords = preg_replace(['/[\(\)\'`]/', '/"/'], [' ', ' " '], $keywords);
        /* @var $ext \common\extensions\SearchPlus\SearchPlus */
        if ($sp = \common\helpers\Acl::check_extension_allowed('SearchPlus')) {
            $keywords = $sp::replace_keywords($keywords);
        }
        foreach (\common\helpers\Hooks::get_list('products-search/alter-keywords') as $filename) {
            include $filename;
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed')) {
            if (tep_not_null($keywords)) {
                $pieces = preg_split('/[\s]+/', $keywords, -1, PREG_SPLIT_NO_EMPTY);
                $started = false;
                $phrase = '';
                foreach ($pieces as $kw) {
                    if ($kw == '"') {
                        if ($started) {
                            $started = false;
                            $this->_parsed_keywords[] = trim($phrase);
                            $phrase = '';
                        } else {
                            $started = true;
                        }
                    } elseif (!$started) {
                        $this->_parsed_keywords[] = $kw;
                    } else {
                        $phrase .= ' ' . $kw;
                    }
                }
                if ($phrase != '') {
                    // not closed "
                    $this->_parsed_keywords[] = trim($phrase);
                }
            }
        } else {
            $this->_parsed_keywords[] = trim($keywords);
            $this->prepare_request($keywords);
        }
    }
    /**
     * for admin only - search by plain table, no platform restriction
     * @param \common\models\queries\ProductsQuery $q
     * @param type $params
     * @return type
     */
    public function add_products_restriction(\common\models\queries\Products_Query &$q, $params = [])
    {
        /** @var \common\extensions\GoogleAnalyticsTools\GoogleAnalyticsTools $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAnalyticsTools')) {
            $ext::attach_join($q);
        }
        /** @var \common\extensions\PlainProductsDescription\PlainProductsDescription $ext */
        $ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed');
        if ($ext && $ext::is_enabled()) {
            $kws = $this->get_parsed_keywords();
            if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'fulltext') {
                $kws = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($kws, true);
            } else {
                $kws = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($kws);
            }
            if (!is_array($kws) || empty($kws)) {
                // all keywords too short or common
                return;
            }
            if (\frontend\design\Info::is_totally_admin() && version_compare($ext::get_version(), '1.0.3', '>=')) {
                $_search_field = '{{%plain_products_name_search}}.search_details_be';
                $_search_field_sound_ex = '{{%plain_products_name_search}}.search_soundex_be';
            } else {
                $_search_field = '{{%plain_products_name_search}}.search_details';
                $_search_field_sound_ex = '{{%plain_products_name_search}}.search_soundex';
            }
            $q->join_with('anyListingName', false);
            $params = $kws;
            if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'fulltext') {
                if (is_array($params)) {
                    $q->and_where('match( ' . $_search_field . ' ) against(:kw)', [':kw' => implode(' ', $params)]);
                }
            } else {
                //always like by "search" field
                $params = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($params);
                if (is_array($params)) {
                    $f = ['like', '' . $_search_field . '', $params];
                    //highest/extra relevance by name (all keywords in the name)
                    if ($this->relevance_order) {
                        $relevance_f = ['like', '{{%plain_products_name_search}}.products_name', $params];
                        $tmp = $tmp_f = [];
                        foreach ($params as $param) {
                            $tmp_f[] = \Yii::$app->db->create_command($relevance_f[1] . ' ' . $relevance_f[0] . ' :kw', [':kw' => '%' . $param . '%'])->raw_sql;
                            $tmp[] = \Yii::$app->db->create_command('-100/if(LOCATE( :kw , ' . $f[1] . ')>0, LOCATE( :kw , ' . $f[1] . '), -100)', [':kw' => $param])->raw_sql;
                        }
                    }
                    if (defined('MSEARCH_ENABLE') && (strtolower(MSEARCH_ENABLE) == 'true' || strtolower(MSEARCH_ENABLE) == 'soundex')) {
                        //+ or like by soundex field
                        $fs = ['like', $_search_field_sound_ex, $params];
                        $tmps = \common\extensions\Plain_Products_Description\Plain_Products_Description::get_soundex(implode(' ', $params), false);
                        if (is_array($tmps)) {
                            $tmps = array_map(function ($el) {
                                return ',' . $el . ',';
                            }, $tmps);
                            $f = ['or', $f, ['like', $_search_field_sound_ex, $tmps]];
                            if ($this->relevance_order) {
                                foreach ($tmps as $param) {
                                    $tmp[] = \Yii::$app->db->create_command('-10/if(LOCATE( :kw , ' . $fs[1] . ')>0, LOCATE( :kw , ' . $fs[1] . '), -10)', [':kw' => $param])->raw_sql;
                                }
                            }
                        }
                    }
                    if ($this->relevance_order) {
                        $q->add_order_by(new \yii\db\Expression('(' . implode(' and ', $tmp_f) . ') desc, (' . implode(' + ', $tmp) . ')'));
                    }
                    $q->and_where($f);
                }
            }
        } else {
            $filters_where = $this->get_products_array(false);
            $q->and_where($filters_where);
        }
    }
    public function prepare_keywords_request()
    {
        if (sizeof($this->_search_keywords) > 0) {
            for ($i = 0, $n = sizeof($this->_search_keywords); $i < $n; $i++) {
                switch ($this->_search_keywords[$i]) {
                    //case '(':
                    //case ')':
                    case 'and':
                    case 'or':
                        $this->products_array['regulator'] = $this->_search_keywords[$i];
                        if ($this->_type_search != 'simple') {
                            $this->categories_array['regulator'] = $this->information_array['regulator'] = $this->manufactures_array['regulator'] = $this->gapis_array['regulator'] = $this->_search_keywords[$i];
                        }
                        break;
                    default:
                        $keyword = $this->_search_keywords[$i];
                        $this->replace_words[] = $this->_search_keywords[$i];
                        $this->relevance_words[] = $this->_search_keywords[$i];
                        $p_array = ['or', ['like', 'if(length(pd1.products_name), pd1.products_name, pd.products_name)', $keyword], ['like', 'm.manufacturers_name', $keyword], ['like', 'if(length(pd1.products_head_keywords_tag), pd1.products_head_keywords_tag, pd.products_head_keywords_tag)', $keyword]];
                        if ($this->use_search_in_desc()) {
                            $p_array[] = ['like', 'if(length(pd1.products_description), pd1.products_description, pd.products_description)', $keyword];
                        }
                        if ($this->use_search_internal()) {
                            $p_array[] = ['like', 'if(length(pd1.products_internal_name), pd1.products_internal_name, pd.products_internal_name)', $keyword];
                        }
                        if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'soundex' && isset($this->_msearch_keywords[$i])) {
                            $mkeyword = $this->_msearch_keywords[$i];
                            if (!empty($mkeyword)) {
                                $p_array[] = ['like', 'if(length(pd1.products_name_soundex), pd1.products_name_soundex, pd.products_name_soundex)', $mkeyword];
                                if ($this->use_search_in_desc()) {
                                    $p_array[] = ['like', 'if(length(pd1.products_description_soundex), pd1.products_description_soundex, pd.products_description_soundex)', $mkeyword];
                                }
                            }
                        }
                        $this->_check_product_additional_fileds($p_array, $keyword);
                        /**
                         * @var $ext \common\extensions\GoogleAnalyticsTools\GoogleAnalyticsTools
                         */
                        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAnalyticsTools')) {
                            $__condition = $ext::search_builder_condition($keyword);
                            if ($__condition) {
                                $p_array[] = $__condition;
                            }
                        }
                        if (PRODUCTS_PROPERTIES == 'True' && $this->search_in_property) {
                            global $languages_id;
                            $p_array[] = ['and', ['pvk.language_id' => (int) $languages_id], ['like', 'pvk.values_text', $keyword]];
                        }
                        if ($this->search_in_attributes) {
                            global $languages_id;
                            $p_array[] = ['and', ['pok.language_id' => (int) $languages_id], ['povk.language_id' => (int) $languages_id], ['like', 'povk.products_options_values_name', $keyword]];
                        }
                        $this->products_array[] = $p_array;
                        if ($this->_type_search != 'simple') {
                            if ($ext = \common\helpers\Extensions::is_allowed('GoogleAnalyticsTools')) {
                                $this->gapis_array[] = ['like', 'gs.gapi_keyword', $keyword];
                            }
                            $this->categories_array[] = ['or', ['like', 'if(length(cd1.categories_name), cd1.categories_name, cd.categories_name)', $keyword], ['like', 'if(length(cd1.categories_description), cd1.categories_description, cd.categories_description)', $keyword]];
                            $this->manufactures_array[] = ['like', 'manufacturers_name', $keyword];
                            $this->information_array[] = ['or', ['like', 'if(length(i1.info_title), i1.info_title, i.info_title)', $keyword], ['like', 'if(length(i1.description), i1.description, i.description)', $keyword], ['like', 'if(length(i1.page_title), i1.page_title, i.page_title)', $keyword]];
                        }
                        break;
                }
            }
        }
    }
    public function get_parsed_keywords()
    {
        return $this->_parsed_keywords;
    }
    public function get_search_keywords()
    {
        return $this->_search_keywords;
    }
    public function get_ms_search_keywords()
    {
        return $this->_msearch_keywords;
    }
    protected function _check_product_additional_fileds(&$p_array, $keyword)
    {
        if (is_array($this->_addtional_search) && count($this->_addtional_search)) {
            foreach ($this->_addtional_search as $item) {
                switch ($item) {
                    case 'SKU':
                        $p_array[] = ['like', 'p.products_model', $keyword];
                        break;
                    case 'ASIN':
                        $p_array[] = ['like', 'p.products_asin', $keyword];
                        break;
                    case 'EAN':
                        $p_array[] = ['like', 'p.products_ean', $keyword];
                        break;
                    case 'UPC':
                        $p_array[] = ['like', 'p.products_upc', $keyword];
                        break;
                    case 'ISBN':
                        $p_array[] = ['like', 'p.products_isbn', $keyword];
                        break;
                }
                if (\common\helpers\Extensions::is_allowed('Inventory')) {
                    $_ids = $this->get_inventory_ids($keyword, $item);
                    if (is_array($_ids) && count($_ids)) {
                        $p_array[] = ['in', 'p.products_id', $_ids];
                    }
                }
            }
        }
    }
    protected function get_inventory_ids($keyword, $search_in)
    {
        static $_cache = [];
        if (!isset($_cache[$keyword . '_' . $search_in])) {
            $i_query = \common\models\Inventory::find()->select('prid')->distinct();
            switch ($search_in) {
                case 'SKU':
                    $i_query->or_where(['like', 'products_model', $keyword]);
                    break;
                case 'ASIN':
                    $i_query->or_where(['like', 'products_asin', $keyword]);
                    break;
                case 'EAN':
                    $i_query->or_where(['like', 'products_ean', $keyword]);
                    break;
                case 'UPC':
                    $i_query->or_where(['like', 'products_upc', $keyword]);
                    break;
                case 'ISBN':
                    $i_query->or_where(['like', 'products_isbn', $keyword]);
                    break;
                default:
                    return [];
                    break;
            }
            $ids = \yii\helpers\Array_Helper::get_column($i_query->as_array()->all(), 'prid');
            $_cache[$keyword . '_' . $search_in] = $ids;
        }
        return $_cache[$keyword . '_' . $search_in];
    }
    public function get_categories_array($to_string = true)
    {
        if ($to_string) {
            return $this->_to_string($this->categories_array);
        } else {
            return $this->_get_array_to_model($this->categories_array);
        }
    }
    private function _get_array_to_model(array $array)
    {
        if (isset($array['regulator'])) {
            $regulator = $array['regulator'];
            unset($array['regulator']);
        } else {
            $regulator = 'and';
        }
        array_unshift($array, $regulator);
        return $array;
    }
    public function get_products_array($to_string = true)
    {
        if ($to_string) {
            return $this->_to_string($this->products_array);
        } else {
            return $this->_get_array_to_model($this->products_array);
        }
    }
    public function get_informations_array($to_string = true)
    {
        if ($to_string) {
            return $this->_to_string($this->information_array);
        } else {
            return $this->_get_array_to_model($this->information_array);
        }
    }
    public function get_manufacturers_array($to_string = true)
    {
        if ($to_string) {
            return $this->_to_string($this->manufactures_array);
        } else {
            return $this->_get_array_to_model($this->manufactures_array);
        }
    }
    public function get_google_keywords_array($to_string = true)
    {
        if ($to_string) {
            return $this->_to_string($this->gapis_array);
        } else {
            return $this->_get_array_to_model($this->gapis_array);
        }
    }
    private function _to_string(array $arrays)
    {
        if (empty($arrays)) {
            return '';
        }
        if (isset($arrays['regulator'])) {
            $reg = $arrays['regulator'];
            unset($arrays['regulator']);
        } else {
            $reg = ' and ';
        }
        $q_builder = Yii::$app->get_db()->get_query_builder();
        $params = [];
        $result = [];
        if (!empty($arrays)) {
            foreach ($arrays as $array) {
                $result[] = $q_builder->build_condition($array, $params);
                // . (count($array) == count($array, COUNT_RECURSIVE) ? '' : ') ');
            }
        } else {
            // no search words, only and/or
            $result[] = 0;
        }
        $sub_query = '(' . implode(') ' . $reg . ' (', $result) . ')';
        $sub_query = Yii::$app->get_db()->create_command($sub_query, $params)->raw_sql;
        return ' and (' . $sub_query . ') ';
    }
    private function _prepare_sql_params(&$values)
    {
        foreach ($values as $key => &$value) {
            $value = "'" . tep_db_input(tep_db_prepare_input($value)) . "'";
        }
    }
}