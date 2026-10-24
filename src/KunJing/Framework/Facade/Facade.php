<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：门面基类（净室实现，能力对标 ThinkPHP Facade）
 * 子类实现 getFacadeAccessor() 返回容器绑定键，静态调用转发到真实实例。
 */

namespace KunJing\Framework\Facade;

use KunJing\Framework\Container\Container;

abstract class Facade
{
    protected static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    protected static function getContainer(): Container
    {
        if (self::$container === null) {
            self::$container = Container::getInstance();
        }
        return self::$container;
    }

    /**
     * 子类返回容器绑定键（类名或自定义字符串）。
     */
    abstract protected static function getFacadeAccessor(): string;

    public static function __callStatic(string $method, array $args)
    {
        $accessor = static::getFacadeAccessor();
        $instance = static::resolveFacadeInstance($accessor);

        if (!is_object($instance)) {
            throw new \RuntimeException("门面 [{$accessor}] 无法解析为实例。");
        }

        return $instance->$method(...$args);
    }

    /**
     * @return mixed
     */
    protected static function resolveFacadeInstance(string $accessor)
    {
        $container = static::getContainer();
        if ($container->bound($accessor) || class_exists($accessor)) {
            return $container->make($accessor);
        }
        return null;
    }
}
