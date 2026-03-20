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
namespace common\components\google\modules;

abstract class Abstract_Google implements Google_Interface
{
    protected $provider;
    abstract public function get_params();
    abstract public function render_widget();
    public function set_provider(\common\components\google\Module_Provider $provider)
    {
        $this->provider = $provider;
    }
    public function loaded(array $params)
    {
        $elements = $this->config[$this->code];
        foreach ($elements as $key => $element) {
            if (isset($params[$key])) {
                if ($key == 'fields') {
                    for ($i = 0; $i < count($element); $i++) {
                        if ($elements[$key][$i]['type'] == 'checkbox') {
                            $elements[$key][$i]['value'] = 0;
                            if (!isset($params[$key][$i])) {
                                $params[$key][$i] = [$elements[$key][$i]['name'] => 0];
                            } else {
                                $params[$key][$i] = [$elements[$key][$i]['name'] => 1];
                            }
                        }
                        if (is_array($params[$key][$i])) {
                            foreach ($params[$key][$i] as $field => $value) {
                                if ($field == $elements[$key][$i]['name']) {
                                    if ($elements[$key][$i]['type'] == 'checkbox') {
                                        $elements[$key][$i]['value'] = $value;
                                    } else {
                                        $elements[$key][$i]['value'] = $value;
                                    }
                                }
                            }
                        }
                    }
                } elseif ($key == 'type') {
                    $elements[$key]['selected'] = $params[$key];
                } elseif ($key == 'pages') {
                    $elements[$key] = $params[$key];
                }
            }
        }
        $this->config[$this->code] = $elements;
        return $this;
    }
    public function render()
    {
        return \common\components\google\widgets\Module_Widget::widget(['module' => $this]);
    }
    public function overload_config($config, array $params = [])
    {
        $this->config = unserialize($config);
        if ($params) {
            $this->config = array_merge($params, $this->config);
        }
        return $this;
    }
    public function get_available_pages()
    {
        $_pages = [];
        if (isset($this->config[$this->code]['pages'])) {
            foreach ($this->config[$this->code]['pages'] as $key => $_page) {
                $_pages[$key] = strtolower($_page);
            }
        }
        return count($_pages) ? $_pages : ['all'];
    }
    public function get_priority()
    {
        return isset($this->config[$this->code]['priority']) ? $this->config[$this->code]['priority'] : 99;
    }
    public function parse_fields($fields)
    {
        $ret = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $ret[$field['name']] = $field['value'];
            }
        }
        return $ret;
    }
    public function is_tracking_added($order_id, $type = '')
    {
        $m = \common\models\Ecommerce_Tracking::find()->and_where(['orders_id' => $order_id, 'services' => $this->code, 'message_type' => !empty($type) ? $type : 'purchase']);
        return $m->exists();
    }
    /**
     *
     * @param array $data [orders_id => NNN , < 'via'=>ssss> ]
     */
    public function save_tracking($data)
    {
        $m = new \common\models\Ecommerce_Tracking();
        $m->load_default_values();
        try {
            $m->set_attributes(array_merge(['date_added' => date(\common\helpers\Date::DATABASE_DATETIME_FORMAT), 'services' => $this->code, 'message_type' => 'purchase', 'via' => 'js', 'extra_info' => ''], $data), false);
            $m->save(false);
        } catch (\Exception $ex) {
            \Yii::warning(' #### ' . print_r($ex->get_message(), 1), 'TLDEBUG');
        }
    }
}