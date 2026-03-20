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
namespace common\components\Event_Dispatcher\Provider;

use common\components\Event_Dispatcher\Listener_Provider_Interface;
class Provider implements Listener_Provider_Interface
{
    private $listeners = [];
    public function get_listeners_for_event($event)
    {
        $class_name = \get_class($event);
        if (isset($this->listeners[$class_name])) {
            yield from $this->listeners[$class_name];
        }
        foreach (class_parents($event) as $parent) {
            if (isset($this->listeners[$parent])) {
                yield from $this->listeners[$parent];
            }
        }
        foreach (class_implements($event) as $interface) {
            if (isset($this->listeners[$interface])) {
                yield from $this->listeners[$interface];
            }
        }
    }
    public function attach(callable $listener)
    {
        $this->listeners[$this->get_parameter_type($listener)][] = $listener;
    }
    public function detach(string $interface)
    {
        unset($this->listeners[$interface]);
    }
    private function get_parameter_type(callable $callable): string
    {
        try {
            switch (true) {
                case $this->is_class_callable($callable):
                    $reflect = new \ReflectionClass($callable[0]);
                    $params = $reflect->get_method($callable[1])->get_parameters();
                    break;
                case $this->is_function_callable($callable):
                case $this->is_closure_callable($callable):
                    $reflect = new \ReflectionFunction($callable);
                    $params = $reflect->get_parameters();
                    break;
                case $this->is_object_callable($callable):
                    $reflect = new \Reflection_Object($callable[0]);
                    $params = $reflect->get_method($callable[1])->get_parameters();
                    break;
                case $this->is_invokable($callable):
                    $params = (new \ReflectionMethod($callable, '__invoke'))->get_parameters();
                    break;
                default:
                    throw new \InvalidArgumentException('Not a recognized type of callable');
            }
            $reflection_type = $params[0]->get_type();
            if ($reflection_type === null) {
                throw new \InvalidArgumentException('Listeners must be declare an object type they can accept.');
            }
            if (method_exists($reflection_type, 'getName')) {
                $type = $reflection_type->get_name();
            } else {
                $type = (string) $reflection_type;
            }
        } catch (\Reflection_Exception $e) {
            throw new \RuntimeException('Type error registering listener.', 0, $e);
        }
        return $type;
    }
    private function is_function_callable(callable $callable): bool
    {
        // function_exists() not suitable because many functions includes later
        return is_string($callable);
    }
    private function is_closure_callable(callable $callable): bool
    {
        return $callable instanceof \Closure;
    }
    private function is_invokable(callable $callable): bool
    {
        return is_object($callable);
    }
    private function is_object_callable(callable $callable): bool
    {
        return is_array($callable) && is_object($callable[0]);
    }
    /**
     * For StaticMethods
     * @param $callable
     * @return bool
     */
    private function is_class_callable($callable): bool
    {
        return is_array($callable) && is_string($callable[0]) && class_exists($callable[0]);
    }
}