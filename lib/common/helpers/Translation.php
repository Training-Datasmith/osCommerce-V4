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
namespace common\helpers;

use frontend\design\Info;
class Translation
{
    public static $translations = [];
    public static $translations_keys = [];
    public static $translations_values = [];
    public static function init($entity = '', $language_id = '', $skip_empty_keys = true)
    {
        global $languages_id, $language;
        if (!$language_id) {
            $language_id = $languages_id;
        }
        // {{ double define
        static $loaded_by_key = [];
        $key = strval($entity) . '^' . (int) $language_id . '^' . ($skip_empty_keys ? '1' : '0');
        if (isset($loaded_by_key[$key])) {
            return;
        }
        $loaded_by_key[$key] = 1;
        // }} double define
        $translations = \Yii::$app->get_cache()->get_or_set('translation_' . str_replace('/', '.', $entity) . '_' . (int) $language_id, function () use ($entity, $language_id) {
            return \common\models\Translation::find()->select(['translation_key', 'translation_value'])->where(['translation_entity' => $entity, 'language_id' => (int) $language_id])->as_array()->all();
        }, 0, new \yii\caching\Tag_Dependency(['tags' => ['translation', self::get_tag_name_for_entity($entity)]]));
        /*
        $translations = [];
        $translation_query = tep_db_query("select translation_key, translation_value from " . TABLE_TRANSLATION . " where translation_entity = '" . tep_db_input($entity) . "' and language_id = '" . (int)$language_id . "'");
        while ($translation = tep_db_fetch_array($translation_query)) {
            $translations[] = $translation;
        }
        */
        foreach ($translations as $translation) {
            if ($skip_empty_keys && empty($translation['translation_value'])) {
                continue;
            }
            self::define_keys($translation, $entity);
        }
        $lang = \common\helpers\Language::get_language_id(DEFAULT_LANGUAGE);
        if (isset($lang['languages_id']) && $lang['languages_id'] != $language_id) {
            $translation_query = tep_db_query('select translation_key, translation_value from ' . TABLE_TRANSLATION . " where translation_entity = '" . tep_db_input($entity) . "' and language_id = '" . (int) $lang['languages_id'] . "'");
            while ($translation = tep_db_fetch_array($translation_query)) {
                self::define_keys($translation, $entity);
            }
        }
    }
    public static function define_keys($translation, $entity)
    {
        if (defined($translation['translation_key'])) {
            return false;
        }
        $translation['translation_value'] = \common\classes\Tl_Url::replace_url($translation['translation_value']);
        $translation['translation_value'] = self::check_included_constants($translation['translation_value']);
        static $define_flag;
        if (is_null($define_flag)) {
            $define_flag = !\common\helpers\Acl::is_frontend_translation() && !(Info::is_admin() && method_exists(\Yii::$app->request, 'get') && \Yii::$app->request->get('texts'));
        }
        if ($define_flag) {
            define($translation['translation_key'], $translation['translation_value']);
            return true;
        }
        define($translation['translation_key'], '##' . $translation['translation_key'] . '##');
        if (isset(self::$translations[$translation['translation_key']]) && self::$translations[$translation['translation_key']]) {
            return true;
        }
        self::$translations[$translation['translation_key']] = ['value' => $translation['translation_value'], 'entity' => $entity];
        self::$translations_keys[] = '##' . $translation['translation_key'] . '##';
        self::$translations_values[] = '<span class="translation-key" data-translation-key="' . $translation['translation_key'] . '" data-translation-entity="' . $entity . '">' . $translation['translation_value'] . '</span>';
        return true;
    }
    public static function check_included_constants($value)
    {
        $value = preg_replace_callback('/##(.*?)##/', function ($found) {
            return defined($found[1]) ? CONSTANT($found[1]) : '';
        }, $value);
        return $value;
    }
    /**
     *
     * @global int $languages_id
     * @param string $translation_key
     * @param string $translation_entity
     * @param int $language_id optional
     * @return translation or false
     */
    public static function get_translation_value($translation_key, $translation_entity = '', $language_id = '')
    {
        global $languages_id;
        if (!$language_id) {
            $language_id = $languages_id;
        }
        $ret = false;
        $translation_query = tep_db_query('select translation_value from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        if ($translation = tep_db_fetch_array($translation_query)) {
            $ret = $translation['translation_value'];
        }
        return $ret;
    }
    public static function get_value($translation_key, $translation_entity = 'configuration', $default = '##key##')
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (defined($translation_key)) {
            return constant($translation_key);
        }
        $res = self::get_translation_value($translation_key, $translation_entity, $languages_id);
        $def_language_id = \common\helpers\Language::get_default_language_id();
        if (!$res && $languages_id != $def_language_id) {
            $res = self::get_translation_value($translation_key, $translation_entity, $def_language_id);
        }
        // return
        if ($res) {
            return $res;
        }
        if (is_null($default)) {
            return false;
        }
        if ($default == '##key##') {
            return $translation_key;
        }
        return $default;
    }
    public static function set_translation_value($translation_key, $translation_entity, $language_id, $translation_value)
    {
        $translation_query = tep_db_query('select * from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        if (tep_db_num_rows($translation_query) > 0) {
            $sql_data_array = ['translation_value' => $translation_value];
            tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $language_id . "' and translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "'");
        } else {
            $hash = md5($translation_key . '-' . $translation_entity);
            $sql_data_array = ['language_id' => (int) $language_id, 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'translation_value' => $translation_value, 'hash' => $hash];
            tep_db_perform(TABLE_TRANSLATION, $sql_data_array);
        }
    }
    public static function replace_translation_value_by_key($translation_key, $translation_entity, $language_id, $translation_value)
    {
        $translation_query = tep_db_query('select * from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        if (tep_db_num_rows($translation_query) == 0) {
            $hash = md5($translation_key . '-' . $translation_entity);
            $sql_data_array = ['language_id' => (int) $language_id, 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'translation_value' => $translation_value, 'hash' => $hash];
            tep_db_perform(TABLE_TRANSLATION, $sql_data_array);
        }
        $sql_data_array = ['translation_value' => $translation_value];
        tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $language_id . "' and translation_key = '" . tep_db_input($translation_key) . "'");
    }
    public static function replace_translation_value_by_old_value($translation_key, $translation_entity, $language_id, $translation_value)
    {
        $translation_query = tep_db_query('select * from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        if (tep_db_num_rows($translation_query) > 0) {
            $translation = tep_db_fetch_array($translation_query);
            $old_translation_value = $translation['translation_value'];
            if (!empty($old_translation_value)) {
                $sql_data_array = ['translation_value' => $translation_value];
                tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $language_id . "' and translation_value = '" . tep_db_input($old_translation_value) . "'");
            } else {
                $sql_data_array = ['translation_value' => $translation_value];
                tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $language_id . "' and translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "'");
            }
        } else {
            $hash = md5($translation_key . '-' . $translation_entity);
            $sql_data_array = ['language_id' => (int) $language_id, 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'translation_value' => $translation_value, 'hash' => $hash];
            tep_db_perform(TABLE_TRANSLATION, $sql_data_array);
        }
    }
    public static function load_js($translation_entity, $language_id = 0)
    {
        global $languages_id, $lng;
        $language_id = !$language_id ? $languages_id : $language_id;
        $translation_query = tep_db_query('select t1.translation_key, if(length(t1.translation_value)>0, t1.translation_value, t2.translation_value) as translation_value from ' . TABLE_TRANSLATION . ' t1 left join ' . TABLE_TRANSLATION . ' t2 on (t2.language_id = (select l.languages_id from ' . TABLE_LANGUAGES . " l where l.code = '" . DEFAULT_LANGUAGE . "') and t1.translation_key = t2.translation_key and t1.translation_entity = t2.translation_entity) where t1.translation_entity = '" . tep_db_input($translation_entity) . "' and t1.language_id = '" . (int) $language_id . "'");
        $translations = [];
        if (tep_db_num_rows($translation_query)) {
            while ($translation = tep_db_fetch_array($translation_query)) {
                if (!isset($translations[$translation['translation_key']])) {
                    $translations[$translation['translation_key']] = $translation['translation_value'];
                }
            }
        }
        return $translations;
    }
    public static function is_translated($translation_key, $translation_entity = '', $language_id = '')
    {
        global $languages_id;
        if (!$language_id) {
            $language_id = $languages_id;
        }
        $translation_query = tep_db_query('select translated from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        $translation = tep_db_fetch_array($translation_query);
        return $translation['translated'] ?? null;
    }
    public static function set_translated($translation_key, $translation_entity, $language_id, $status = 0)
    {
        tep_db_query('update ' . TABLE_TRANSLATION . ' set translated = ' . (int) $status . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
    }
    public static function is_checked($translation_key, $translation_entity = '', $language_id = '')
    {
        global $languages_id;
        if (!$language_id) {
            $language_id = $languages_id;
        }
        $translation_query = tep_db_query('select checked from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
        $translation = tep_db_fetch_array($translation_query);
        return $translation['checked'] ?? null;
    }
    public static function set_checked($translation_key, $translation_entity, $language_id, $status = 0)
    {
        tep_db_query('update ' . TABLE_TRANSLATION . ' set checked = ' . (int) $status . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "' and language_id = '" . (int) $language_id . "'");
    }
    public static function translations_for_js($keys, $json = true)
    {
        if (!$keys || !is_array($keys)) {
            return [];
        }
        $js_keys = [];
        foreach ($keys as $key) {
            if (defined($key)) {
                $js_keys[$key] = constant($key);
            } else {
                $js_keys[$key] = $key;
            }
        }
        if ($json) {
            return json_encode($js_keys);
        } else {
            return $js_keys;
        }
    }
    public static function frontend_translation($content)
    {
        if (!\common\helpers\Acl::is_frontend_translation() && !(Info::is_admin() && \Yii::$app->request->get('texts'))) {
            return $content;
        }
        $content = preg_replace_callback('|([a-zA-Z\-]+)=\"[\s]{0,}(##([A-Z0-9_]+)##)[\s]{0,}\"|', function ($matches) {
            return str_replace($matches[2], self::$translations[$matches[3]]['value'], $matches[0]) . ' data-translation' . ' data-translation-key-' . $matches[1] . '="' . $matches[3] . '"' . ' data-translation-entity-' . $matches[1] . '="' . self::$translations[$matches[3]]['entity'] . '"';
        }, $content);
        $content = preg_replace_callback('|([a-zA-Z\-]+)=\'[\s]{0,}(##([A-Z0-9_]+)##)[\s]{0,}\'|', function ($matches) {
            return str_replace($matches[2], self::$translations[$matches[3]]['value'], $matches[0]) . ' data-translation' . " data-translation-key-' . {$matches[1]} . '='" . $matches[3] . "'" . " data-translation-entity-' . {$matches[1]} . '='" . self::$translations[$matches[3]]['entity'] . "'";
        }, $content);
        $content = preg_replace_callback('|<option([^>]+)>(.*(##([A-Z0-9_]+)##).*?)</option>[\s\n]{0,}|', function ($matches) {
            return '<option class="translation-key-option" ' . $matches[1] . ' data-translation-key="' . $matches[4] . '"' . ' data-translation-entity="' . self::$translations[$matches[4]]['entity'] . '">' . str_replace($matches[3], self::$translations[$matches[4]]['value'], $matches[2]) . '</option>';
        }, $content);
        $content = str_replace(self::$translations_keys, self::$translations_values, $content);
        \frontend\design\Info::add_js_data(\frontend\design\Edit_Data::js_data());
        $entry_data_place_holder = 'var entryData = JSON.parse(\'' . addslashes(json_encode(\frontend\design\Info::$js_global_data)) . '\');';
        if (Info::is_admin() && \Yii::$app->request->get('texts')) {
            $entry_data_place_holder .= '
    window.parent.postMessage(entryData, \'*\');
            ';
        }
        $content = str_replace('var entryDataPlaceHolder;', $entry_data_place_holder, $content);
        return $content;
    }
    public static function reset_cache()
    {
        \yii\caching\Tag_Dependency::invalidate(\Yii::$app->get_cache(), 'translation');
    }
    public static function reset_cache_enity($entity)
    {
        \yii\caching\Tag_Dependency::invalidate(\Yii::$app->get_cache(), self::get_tag_name_for_entity($entity));
    }
    private static function get_tag_name_for_entity($entity)
    {
        return 'translate_' . str_replace('/', '.', $entity);
    }
    public static function force_const($const_names, $entity)
    {
        if (!is_array($const_names)) {
            $const = [$const_names];
        }
        foreach ($const_names as $const_name) {
            defined($const_name) or define($const_name, self::get_value($const_name, $entity));
        }
    }
}