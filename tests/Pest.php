<?php

uses(Tests\TestCase::class)->in(__DIR__);

function invokeProtectedMethod(object $class, string $methodName, array $parameters = [])
{
    $method = new ReflectionMethod($class, $methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($class, $parameters);
}
