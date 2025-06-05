<?php

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * @issue https://github.com/mobilestock/backend/issues/1001
 */
function invokeProtectedMethod(object $class, string $methodName, array $parameters = []): mixed
{
    $method = new ReflectionMethod($class, $methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($class, $parameters);
}
