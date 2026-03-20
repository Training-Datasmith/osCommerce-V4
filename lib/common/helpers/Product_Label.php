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

class Product_Label
{
    protected static function get_logo_data()
    {
        $platform = \common\models\Platforms::find()->alias('p')->left_join(['p2t' => 'platforms_to_themes'], 'p.platform_id=p2t.platform_id AND p2t.is_default=1')->select(['p.logo', 'p2t.theme_id'])->where(['p.platform_id' => \common\classes\platform::default_id()])->as_array()->one();
        if ($platform['logo'] && is_file(DIR_FS_CATALOG . $platform['logo'])) {
            $image = $platform['logo'];
        }
        $theme = \common\models\Themes::find_one($platform['theme_id']);
        if ($theme && $theme->theme_name) {
            $image = \frontend\design\Info::theme_setting('logo', 'hide', $theme->theme_name);
        }
        if (is_file(DIR_FS_CATALOG . $image)) {
            return '@' . base64_encode(file_get_contents(DIR_FS_CATALOG . $image));
        }
        return '';
    }
    protected static function get_store_name()
    {
        /**
         * @var $platformConfig \common\classes\platform_config
         */
        $platform_config = \Yii::$app->get('platform')->get_config(\common\classes\platform::default_id());
        return $platform_config->const_value('STORE_NAME');
    }
    public static function label($text, $count = 1)
    {
        $label_size = [89, 36];
        $pdf = new \TCPDF('L', 'mm', $label_size);
        $pdf->set_viewer_preferences(['PrintScaling' => 'None']);
        $pdf->set_margins(0, 0, 0);
        $pdf->set_auto_page_break(false, 0);
        $pdf->set_font('arial', '', 36);
        $pdf->set_print_header(false);
        $pdf->set_print_footer(false);
        for ($i = 1; $i <= max(1, $count); $i++) {
            $pdf->add_page();
            $pdf->set_font('arial', '', 36);
            //$pdf->Cell($labelSize[0]-0.1, $labelSize[1]-0.1,'',1);
            $branding_logo = static::get_logo_data();
            $branding_text = static::get_store_name();
            if ($branding_logo) {
                $pdf->write_html_cell(12, 12, 2, 3, '<img src="' . $branding_logo . '" width="12mm"/>');
            }
            $pdf->write_html_cell($label_size[0] - 10, 10, 10, 4, '<div style="font-size: 12pt; text-align: center">' . $branding_text . '</div>');
            //$pdf->Rect(1,11,$labelSize[0]-2,14);
            $pdf->set_font('arial', 'B', 36);
            $pdf->multi_cell($label_size[0] - 2, 16, $text, 0, 'C', false, 1, 1, 10, true, 0, false, true, 16, 'M', true);
            $barcode_size = [38, 7.5];
            $pdf->write1d_barcode($text, 'C128', $label_size[0] / 2 - $barcode_size[0] / 2, 25, $barcode_size[0], $barcode_size[1]);
        }
        return $pdf->Output('', 'S');
        //$pdf->Output(preg_replace('/[^\da-z-_]+/i','_',$text).'.pdf');
    }
}