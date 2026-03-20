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
use yii\base\Component;
class Settings extends Component
{
    public $session_key = 'settings';
    private $data = [];
    public function has($variable)
    {
        $this->load();
        if (isset($this->data[$variable])) {
            return true;
        }
        return false;
    }
    public function get($variable)
    {
        $this->load();
        if (isset($this->data[$variable])) {
            return $this->data[$variable];
        } else {
            $def = $this->get_def($variable);
            // too much warnings about currency in the log
            // \Yii::warning("Settings variable '$variable' is not defined, default value returned.", 'main');
            return $def;
        }
    }
    public function get_def($variable)
    {
        switch ($variable) {
            case 'currency':
                return DEFAULT_CURRENCY;
            case 'affiliate_id':
                return 0;
            case 'customer_groups_id':
                return defined('DEFAULT_USER_GROUP') ? (int) DEFAULT_USER_GROUP : 0;
        }
        return false;
    }
    public function set($variable, $value)
    {
        $this->load();
        if (!is_array($this->data)) {
            $this->data = [];
        }
        $this->data[$variable] = $value;
        $this->save();
        return true;
    }
    public function get_all()
    {
        $this->load();
        return $this->data;
    }
    public function set_all(array $data)
    {
        $this->data = $data;
        $this->save();
        return true;
    }
    public function remove($variable)
    {
        $this->load();
        if (isset($this->data[$variable])) {
            unset($this->data[$variable]);
            $this->save();
            return true;
        }
        return false;
    }
    public function clear($except = [])
    {
        if (is_array($except) && count($except) > 0) {
            foreach (array_keys($this->data) as $key) {
                if (in_array($key, $except)) {
                    continue;
                }
                unset($this->data[$key]);
            }
        } else {
            $this->data = [];
        }
        $this->save();
        return true;
    }
    private function load()
    {
        if (Yii::$app instanceof \yii\console\Application) {
            $this->data = $_SESSION[$this->session_key] ?? [];
        } else if (Yii::$app->storage->pointer_shifted()) {
            $this->data = Yii::$app->storage->get_all();
        } else {
            $this->data = Yii::$app->session->get($this->session_key, []);
        }
    }
    private function save()
    {
        if (Yii::$app instanceof \yii\console\Application) {
            $_SESSION[$this->session_key] = $this->data;
        } else if (!Yii::$app->storage->pointer_shifted()) {
            Yii::$app->session->set($this->session_key, $this->data);
        }
    }
}