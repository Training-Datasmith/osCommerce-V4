<?php

declare(strict_types=1);
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

namespace common\classes;

use Yii;

class navigation
{
    public $path;
    public $snapshot;

    public function __construct()
    {
        $this->reset();
    }

    public function reset()
    {
        $this->path = [];
        $this->snapshot = [];
    }

    public function add_current_page()
    {
        global $PHP_SELF, $request_type, $cPath;

        $set = 'true';
        for ($i = 0, $n = sizeof($this->path); $i < $n; $i++) {
            if (($this->path[$i]['page'] == basename($PHP_SELF))) {
                if (isset($cPath)) {
                    if (!isset($this->path[$i]['get']['cPath'])) {
                        continue;
                    } else {
                        if ($this->path[$i]['get']['cPath'] == $cPath) {
                            array_splice($this->path, ($i + 1));
                            $set = 'false';
                            break;
                        } else {
                            $old_cPath = explode('_', $this->path[$i]['get']['cPath']);
                            $new_cPath = explode('_', $cPath);

                            for ($j = 0, $n2 = sizeof($old_cPath); $j < $n2; $j++) {
                                if ($old_cPath[$j] != $new_cPath[$j]) {
                                    array_splice($this->path, ($i));
                                    $set = 'true';
                                    break 2;
                                }
                            }
                        }
                    }
                } else {
                    array_splice($this->path, ($i));
                    $set = 'true';
                    break;
                }
            }
        }

        if ($set == 'true') {
            if (defined('DIR_WS_ADMIN')) {
                if (basename($PHP_SELF) == str_replace('/', '', DIR_WS_ADMIN)) {
                    $PHP_SELF = DIR_WS_ADMIN . FILENAME_DEFAULT;
                }
            }
            $this->path[] = ['page' => basename($PHP_SELF),
                                  'mode' => $request_type,
                                  'get' => $_GET,
                                  'post' => []/*$_POST*/];
        }
        if (count($this->path) > 10) {
            array_shift($this->path);
        }
    }

    public function remove_current_page()
    {
        global $PHP_SELF;

        $last_entry_position = sizeof($this->path) - 1;
        if ($this->path[$last_entry_position]['page'] == basename($PHP_SELF)) {
            unset($this->path[$last_entry_position]);
        }
    }

    public function set_snapshot($page = '', $ajax = false)
    {
        if (Yii::$app->request->isAjax && !$ajax) {
            return '';
        }
        global $PHP_SELF, $request_type;

        if (is_array($page)) {
            $this->snapshot = ['page' => $page['page'] ?? '',
                                    'mode' => $page['mode'] ?? '',
                                    'get' => $page['get'] ?? [],
                                    'post' => $page['post'] ?? [],
                ];
        } else {
            if (class_exists('Yii')) {
                $this->snapshot = ['page' => Yii::$app->request->getPathInfo(),
                  'mode' => $request_type,
                  'get' => Yii::$app->request->getQueryParams(),
                  'post' => Yii::$app->request->getBodyParams()];
            } else {
                $this->snapshot = ['page' => basename($PHP_SELF),
                                        'mode' => $request_type,
                                        'get' => $_GET,
                                        'post' => []/*$_POST*/];
            }
        }
    }

    public function clear_snapshot()
    {
        $this->snapshot = [];
    }

    public function set_path_as_snapshot($history = 0)
    {
        $pos = (sizeof($this->path) - 1 - $history);
        $this->snapshot = ['page' => $this->path[$pos]['page'],
                                'mode' => $this->path[$pos]['mode'],
                                'get' => $this->path[$pos]['get'],
                                'post' => $this->path[$pos]['post']];
    }

    public function debug()
    {
        for ($i = 0, $n = sizeof($this->path); $i < $n; $i++) {
            echo $this->path[$i]['page'] . '?';
            if (is_array($this->path[$i]['get'])) {
                foreach ($this->path[$i]['get'] as $key => $value) {
                    echo $key . '=' . $value . '&';
                }
            }
            if (sizeof($this->path[$i]['post']) > 0) {
                echo '<br>';
                if (is_array($this->path[$i]['post'])) {
                    foreach ($this->path[$i]['post'] as $key => $value) {
                        echo '&nbsp;&nbsp;<b>' . $key . '=' . $value . '</b><br>';
                    }
                }
            }
            echo '<br>';
        }

        if (sizeof($this->snapshot) > 0) {
            echo '<br><br>';

            echo $this->snapshot['mode'] . ' ' . $this->snapshot['page'] . '?' . \common\helpers\Output::array_to_string($this->snapshot['get'], [tep_session_name()]) . '<br>';
        }
    }

    public function unserialize($broken)
    {
        if (is_array($broken)) {
            foreach ($broken as $key => $value) {
                if (gettype($this->$key) != 'user function') {
                    $this->$key = $value;
                }
            }
        }
    }
}
