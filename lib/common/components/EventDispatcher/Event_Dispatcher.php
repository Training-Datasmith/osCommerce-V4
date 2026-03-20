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

/**
 * Class EventDispatcher
 * @see Personal Catalog Extension
 * use
 * // also provided invoke, static classes and objects
 * \Yii::$container->get('eventProvider')->attach(static function (OrderCreated $event) {
 *     sendConfirmationEmail($event->getOrder);
 * });
 *
 * \Yii::$container->get('eventDispatcher')->dispatch(new OrderCreated($this));
 */
class Event_Dispatcher
{
    /** @var ListenerProviderInterface */
    private $listener_provider;
    public function __construct(Listener_Provider_Interface $listener_provider)
    {
        $this->listener_provider = $listener_provider;
    }
    public function dispatch($event)
    {
        foreach ($this->listener_provider->get_listeners_for_event($event) as $listener) {
            if ($event instanceof Stoppable_Event_Interface && $event->is_propagation_stopped()) {
                return $event;
            }
            $listener($event);
        }
        return $event;
    }
}