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
namespace backend\design;

class Migration
{
    protected $theme_name;
    protected $migration;
    public function __construct($theme_name)
    {
        $this->theme_name = $theme_name;
    }
    public function create($steps_i_ds)
    {
        if (!isset($this->theme_name) || !isset($steps_i_ds) || !is_array($steps_i_ds)) {
            return 'error';
        }
        $steps = \common\models\Themes_Steps::find()->where(['IN', 'steps_id', $steps_i_ds])->and_where(['theme_name' => $this->theme_name])->as_array()->all();
        $migration = [];
        foreach ($steps as $step) {
            $step['data'] = json_decode($step['data'], true);
            $migration[] = $step;
        }
        return $migration;
    }
    public function apply($migration)
    {
    }
    protected function css_save($step)
    {
    }
    protected function box_save($step)
    {
    }
    protected function box_add($step)
    {
    }
    protected function blocks_move($step)
    {
    }
    protected function box_delete($step)
    {
    }
    protected function import_block($step)
    {
    }
    protected function style_save($step)
    {
    }
    protected function settings($step)
    {
    }
    protected function extend_remove($step)
    {
    }
    protected function extend_add($step)
    {
    }
    protected function javascript_save($step)
    {
    }
    protected function add_page($step)
    {
    }
    protected function add_page_settings($step)
    {
    }
    protected function styles_change($step)
    {
    }
    protected function remove_class($step)
    {
    }
}