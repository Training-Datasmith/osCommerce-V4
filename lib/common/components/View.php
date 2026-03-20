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

use yii\helpers\Html;
/**
 * configured in lib\frontend\config\main.php
 */
class View extends \yii\web\View
{
    /**
     * Renders the content to be inserted at the end of the body section.
     * The content is rendered using the registered JS code blocks and files.
     * @param bool $ajaxMode whether the view is rendering in AJAX mode.
     * If true, the JS scripts registered at [[POS_READY]] and [[POS_LOAD]] positions
     * will be rendered at the end of the view like normal scripts.
     * @return string the rendered content
     */
    protected function render_body_end_html($ajax_mode)
    {
        $lines = [];
        $files = '';
        $js_files = '';
        if (!empty($this->js_files[self::POS_END])) {
            $js_files_end = $this->js_files[self::POS_END];
            $conditional_files = preg_grep('#^<!--\[if#i', $this->js_files[self::POS_END]);
            if (count($conditional_files) > 0) {
                foreach (array_keys($conditional_files) as $conditional_key) {
                    unset($js_files_end[$conditional_key]);
                }
                $js_files = implode('', $conditional_files) . "\n";
            }
            if (count($js_files_end) > 0) {
                $files = "['" . implode("', '", array_keys($js_files_end)) . "'], ";
            }
        }
        if ($ajax_mode) {
            if (!empty($this->js[self::POS_END])) {
                $lines[] = implode("\n", $this->js[self::POS_END]);
            }
            if (!empty($this->js[self::POS_READY])) {
                $lines[] = implode("\n", $this->js[self::POS_READY]);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $lines[] = implode("\n", $this->js[self::POS_LOAD]);
            }
        } else {
            if (!empty($this->js[self::POS_END])) {
                $lines[] = implode("\n", $this->js[self::POS_END]);
            }
            if (!empty($this->js[self::POS_READY])) {
                $lines[] = implode("\n", $this->js[self::POS_READY]);
            }
            if (!empty($this->js[self::POS_LOAD])) {
                $lines[] = implode("\n", $this->js[self::POS_LOAD]);
            }
        }
        if (!$files && empty($lines)) {
            return $js_files;
        }
        return $js_files . Html::script('tl(' . $files . "function(){\n" . implode("\n", $lines) . "\n})");
    }
}