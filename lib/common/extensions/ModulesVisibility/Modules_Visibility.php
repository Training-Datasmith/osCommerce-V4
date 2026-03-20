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
namespace common\extensions\Modules_Visibility;

use yii\db\Query;
use yii\helpers\Array_Helper;
class Modules_Visibility extends \common\classes\modules\Module_Extensions
{
    protected static function get_visibility_id($area)
    {
        static $_fetched = false;
        if (!is_array($_fetched)) {
            $_fetched = [];
            $qry = new Query();
            foreach ($qry->select(['visibility_id', 'visibility_constant'])->from(TABLE_VISIBILITY)->all() as $_data) {
                $_key = strtoupper($_data['visibility_constant']);
                if (!isset($_fetched[$_key])) {
                    $_fetched[$_key] = (int) $_data['visibility_id'];
                }
            }
        }
        return isset($_fetched[strtoupper($area)]) ? $_fetched[strtoupper($area)] : false;
    }
    protected static function get_visibility_area_data($platform_id, $visibility_id)
    {
        static $area_data = [];
        $key = (int) $platform_id . '@' . (int) $visibility_id;
        if (!isset($area_data[$key])) {
            $area_data[$key] = [];
            $visibility_area_query = tep_db_query('SELECT * FROM ' . TABLE_VISIBILITY_AREA . ' ' . "where visibility_id='" . (int) $visibility_id . "' AND platform_id = '" . (int) $platform_id . "'");
            if (tep_db_num_rows($visibility_area_query) > 0) {
                while ($visibility_area = tep_db_fetch_array($visibility_area_query)) {
                    if (isset($area_data[$key][$visibility_area['visibility_code']])) {
                        continue;
                    }
                    $area_data[$key][$visibility_area['visibility_code']] = $visibility_area;
                }
            }
        }
        return $area_data[$key];
    }
    public static function visibility($platform_id = 0, $area = '', $module = null)
    {
        if ((int) $platform_id == 0) {
            return true;
        }
        if (is_null($module)) {
            return true;
        }
        $visibility_id = static::get_visibility_id($area);
        if (!empty($visibility_id)) {
            $data = static::get_visibility_area_data($platform_id, $visibility_id);
            return isset($data[$module->code]);
        }
        return false;
    }
    public static function display_text($platform_id = 0, $area = '', $totals = null, $module = null)
    {
        if (is_null($totals) || is_null($module)) {
            return '';
        }
        if ((int) $platform_id == 0) {
            return $totals;
        }
        $visibility_id = static::get_visibility_id($area);
        if (!empty($visibility_id)) {
            $data = static::get_visibility_area_data($platform_id, $visibility_id);
            if (isset($data[$module->code])) {
                $visibility_area = $data[$module->code];
                $totals['show_line'] = $visibility_area['show_line'];
                $total_extra_text = '';
                $total_extra_text2 = '';
                if ($visibility_area['visibility_vat'] == 1) {
                    $totals['text'] = $totals['text_inc_tax'];
                    $total_extra_text = ' (' . TEXT_INC_VAT . ')';
                    $total_extra_text2 = TEXT_INC_VAT;
                } elseif ($visibility_area['visibility_vat'] == -1) {
                    $totals['text'] = $totals['text_exc_tax'];
                    $total_extra_text = ' (' . TEXT_EXC_VAT . ')';
                    $total_extra_text2 = TEXT_EXC_VAT;
                }
                if (!empty($total_extra_text)) {
                    $sc_position = strrpos($totals['title'], ':');
                    if ($sc_position !== false) {
                        $totals['title'] = substr($totals['title'], 0, $sc_position) . $total_extra_text . substr($totals['title'], $sc_position);
                        $totals['title_main'] = substr($totals['title'], 0, $sc_position);
                    } else {
                        $totals['title'] .= $total_extra_text;
                        $totals['title_main'] = $totals['title'];
                    }
                }
                $totals['title_extra'] = $total_extra_text2;
            }
        }
        return $totals;
    }
    public static function get_visibility($platform_id = 0, $module = null)
    {
        if (is_null($module)) {
            return '';
        }
        if ((int) $platform_id == 0) {
            return '';
        }
        $response = '<br><br><table width="70%" id="module_ext_visibility" style="max-height:350px"><thead><tr><th>' . TEXT_VISIBILITY_ON_PAGES . '</th><th style="text-align: center">' . $module->get_inc_vat_title() . '</th><th style="text-align: center">' . $module->get_exc_vat_title() . '</th><th style="text-align: center">' . $module->get_default_title() . '</th><th style="text-align: center">' . SHOW_TOP_LINE . '</th></tr></thead><tbody>';
        $visibility_query = tep_db_query('SELECT * FROM ' . TABLE_VISIBILITY . ' where 1 order by visibility_constant');
        while ($visibility = tep_db_fetch_array($visibility_query)) {
            if (!\common\helpers\Extensions::is_visibility($visibility['visibility_constant'])) {
                continue;
            }
            $visibility_area_query = tep_db_query('SELECT * FROM ' . TABLE_VISIBILITY_AREA . " where visibility_id='" . $visibility['visibility_id'] . "' AND visibility_code='" . $module->code . "' AND platform_id = '" . (int) $platform_id . "'");
            $checked = 0;
            if (tep_db_num_rows($visibility_area_query) > 0) {
                $checked = 1;
            }
            $visibility_area = tep_db_fetch_array($visibility_area_query);
            $visibility_area['visibility_vat'] = $visibility_area['visibility_vat'] ?? null;
            $visibility_area['show_line'] = $visibility_area['show_line'] ?? null;
            $response .= '<tr class="hover-dark"><td>';
            $response .= '<label>';
            $response .= tep_draw_checkbox_field('visibility[' . $visibility['visibility_id'] . ']', 1, $checked, '', 'class="uniform" ');
            $response .= '&nbsp;' . constant($visibility['visibility_constant']) . '<br>';
            $response .= '</label>';
            $response .= '</td><td style="text-align: center">';
            $response .= $module->get_inc_vat($visibility['visibility_id'], $visibility_area['visibility_vat'] == 1);
            $response .= '</td><td style="text-align: center">';
            $response .= $module->get_exc_vat($visibility['visibility_id'], $visibility_area['visibility_vat'] == -1);
            $response .= '</td><td style="text-align: center">';
            $response .= $module->get_default($visibility['visibility_id'], $visibility_area['visibility_vat'] == 0);
            $response .= '</td><td style="text-align: center">';
            $response .= tep_draw_checkbox_field('show_line[' . $visibility['visibility_id'] . ']', '', $visibility_area['show_line'] == 1, '', 'class="uniform" ');
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        return $response;
    }
    public static function set_visibility($module)
    {
        $platform_id = (int) \Yii::$app->request->post('platform_id');
        if ((int) $platform_id == 0) {
            return false;
        }
        /** @var $reportLog \common\extensions\ReportUniversalLog\ReportUniversalLog */
        if (($report_log = \common\helpers\Extensions::is_allowed('ReportUniversalLog')) && $report_log::is_instance($module->code)) {
            $log_universal = $report_log::get_instance($module->code);
            $log_universal->merge_before_array(['restriction_visibility' => self::get_restriction_visibility_array($module->code, $platform_id)]);
        }
        tep_db_query('delete from ' . TABLE_VISIBILITY_AREA . " where visibility_code = '" . $module->code . "' AND platform_id = '" . (int) $platform_id . "'");
        $visibility = \Yii::$app->request->post('visibility');
        $visibility_vat = \Yii::$app->request->post('visibility_vat');
        $show_line = \Yii::$app->request->post('show_line');
        if (is_array($visibility)) {
            foreach ($visibility as $visibility_id => $checked) {
                $sl = empty($show_line[$visibility_id]) ? 0 : 1;
                tep_db_query('insert into ' . TABLE_VISIBILITY_AREA . " (visibility_id, visibility_code, platform_id, visibility_vat, show_line) values ('" . $visibility_id . "', '" . $module->code . "', '" . (int) $platform_id . "', '" . (int) Array_Helper::get_value($visibility_vat, $visibility_id) . "', '" . $sl . "')");
            }
        }
        if (isset($log_universal)) {
            $log_universal->merge_after_array(['restriction_visibility' => self::get_restriction_visibility_array($module->code, $platform_id)]);
        }
        return true;
    }
    private static function get_restriction_visibility_array($visibility_code = '', $platform_id = 0)
    {
        $return = [];
        foreach (\common\models\Visibility_Area::find()->where(['visibility_code' => trim($visibility_code), 'platform_id' => (int) $platform_id])->as_array(true)->all() as $v_record) {
            $return[$v_record['visibility_code']][$v_record['platform_id']][$v_record['visibility_id']] = ['visibility_vat' => $v_record['visibility_vat'], 'show_line' => $v_record['show_line']];
        }
        unset($v_record);
        return $return;
    }
}