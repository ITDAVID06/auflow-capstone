<?php

namespace Tests\Unit;

use App\Modules\FormBuilder\Requests\StoreFormRequest;
use PHPUnit\Framework\TestCase;

class StoreFormRequestTest extends TestCase
{
    public function test_does_not_override_request_clamp_method(): void
    {
        $method = (new \ReflectionClass(StoreFormRequest::class))->getMethod('clamp');

        $this->assertNotSame(StoreFormRequest::class, $method->getDeclaringClass()->getName());
    }
}
