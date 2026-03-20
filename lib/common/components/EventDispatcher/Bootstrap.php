<?php

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
declare (strict_types=1);
namespace common\components\Event_Dispatcher;

use common\components\Event_Dispatcher\Provider\Provider;
use common\components\Event_Dispatcher\Provider\Providers_Aggregate;
use yii\base\Bootstrap_Interface;
class Bootstrap implements Bootstrap_Interface
{
    public function bootstrap($app)
    {
        $container = \Yii::$container;
        try {
            $container->set_singleton('eventProvider', static function () {
                return new Provider();
            });
            $container->set_singleton('eventDispatcher', static function () use ($container) {
                $providers_aggregate = new Providers_Aggregate();
                $providers_aggregate->attach($container->get('eventProvider'));
                return new Event_Dispatcher($providers_aggregate);
            });
        } catch (\Exception $e) {
            // throw new \RuntimeException($e->getMessage(), 0, $e);
            \Yii::error($e->get_message());
        }
    }
}