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
namespace common\classes\modules;

abstract class Module_Total extends Module
{
    protected static $adjusting;
    protected $processing_order = [];
    public function set_processing_order($processing_order)
    {
        $this->processing_order = $processing_order;
    }
    public function process()
    {
    }
    public function visibility($platform_id = 0, $area = '')
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesVisibility', 'allowed')) {
            return $ext::visibility($platform_id, $area, $this);
        }
        return true;
    }
    public function display_text($platform_id = 0, $area = '', $totals = '')
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesVisibility', 'allowed')) {
            $totals = $ext::display_text($platform_id, $area, $totals, $this);
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesZeroPrice', 'allowed')) {
            $totals = $ext::display_text($platform_id, $area, $totals, $this);
        }
        return $totals;
    }
    public function get_default_title()
    {
        return TEXT_DEFAULT;
    }
    public function get_default($visibility_id = 0, $checked = false)
    {
        return tep_draw_radio_field('visibility_vat[' . $visibility_id . ']', 0, $checked);
    }
    public function get_inc_vat_title()
    {
        return TEXT_INC_VAT;
    }
    public function get_inc_vat($visibility_id = 0, $checked = false)
    {
        return tep_draw_radio_field('visibility_vat[' . $visibility_id . ']', 1, $checked);
    }
    public function get_exc_vat_title()
    {
        return TEXT_EXC_VAT;
    }
    public function get_exc_vat($visibility_id = 0, $checked = false)
    {
        return tep_draw_radio_field('visibility_vat[' . $visibility_id . ']', -1, $checked);
    }
    public function get_visibility($platform_id)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesVisibility', 'allowed')) {
            return $ext::get_visibility($platform_id, $this);
        }
        $response = '<br><br><table width="50%" class="dis_module"><thead><tr><th>' . TEXT_VISIBILITY_ON_PAGES . '</th><th style="text-align: center">' . $this->get_inc_vat_title() . '</th><th style="text-align: center">' . $this->get_exc_vat_title() . '</th><th style="text-align: center">' . $this->get_default_title() . '</th><th style="text-align: center">' . SHOW_TOP_LINE . '</th></tr></thead><tbody>';
        $visibility_query = tep_db_query('SELECT * FROM ' . TABLE_VISIBILITY . ' where 1 order by visibility_constant');
        while ($visibility = tep_db_fetch_array($visibility_query)) {
            if (!\common\helpers\Extensions::is_visibility($visibility['visibility_constant'])) {
                continue;
            }
            $response .= '<tr><td><input type="checkbox" disabled>';
            $response .= '&nbsp;' . constant($visibility['visibility_constant']) . '<br>';
            $response .= '</td><td style="text-align: center"><input type="radio" disabled>';
            $response .= '</td><td style="text-align: center"><input type="radio" disabled>';
            $response .= '</td><td style="text-align: center"><input type="radio" disabled>';
            $response .= '</td><td style="text-align: center"><input type="checkbox" disabled>';
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        return $response;
    }
    public function set_visibility()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesVisibility', 'allowed')) {
            return $ext::set_visibility($this);
        }
        return true;
    }
    public function get_zero_price($platform_id)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesZeroPrice', 'allowed')) {
            return \common\helpers\Modules::get_info_link_for_extension('ModulesZeroPrice') . $ext::get_zero_price($platform_id, $this);
        }
    }
    public function set_zero_price()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ModulesZeroPrice', 'allowed')) {
            return $ext::set_zero_price($this);
        }
        return true;
    }
}