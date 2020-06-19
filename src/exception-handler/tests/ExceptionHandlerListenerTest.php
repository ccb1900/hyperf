<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\ExceptionHandler;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\ExceptionHandler\Annotation\ExceptionHandler;
use Hyperf\ExceptionHandler\Listener\ExceptionHandlerListener;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ExceptionHandlerListenerTest extends TestCase
{
    protected function tearDown()
    {
        Mockery::close();
        AnnotationCollector::clear();
    }

    public function testConfig()
    {
        $config = new Config([
            'exceptions' => [
                'handler' => $handler = [
                    'http' => [
                        'Foo', 'Bar',
                    ],
                    'ws' => [
                        'Foo', 'Tar', 'Bar',
                    ],
                ],
            ],
        ]);
        // AnnotationCollector::collectClass('Bar1', ExceptionHandler::class, new ExceptionHandler(['server'=>'http', 'priority' => 0]));
        $listener = new ExceptionHandlerListener($config);
        $listener->process(new \stdClass());
        $this->assertEquals($handler, $config->get('exceptions.handler', []));
    }

    public function testAnnotation()
    {
        $config = new Config([
            'exceptions' => [
                'handler' => [
                    'http' => [
                        'Foo', 'Bar',
                    ],
                ],
            ],
        ]);
        AnnotationCollector::collectClass('Bar1', ExceptionHandler::class, new ExceptionHandler(['server' => 'http', 'priority' => 1]));
        $listener = new ExceptionHandlerListener($config);
        $listener->process(new \stdClass());
        $this->assertEquals([
            'http' => [
                'Bar1', 'Foo', 'Bar',
            ],
        ], $config->get('exceptions.handler', []));
    }

    protected function mockConfig()
    {
        return Mockery::mock(ConfigInterface::class);
    }
}