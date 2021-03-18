<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\BasicCrudController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionClass;
use Tests\Stubs\Controllers\BasicCrudStubController;
use Tests\Stubs\Models\CategoryStub;
use Tests\TestCase;

class BasicCrudControllerTest extends TestCase
{
    private $controller;
    protected function setUp(): void
    {
        parent::setUp();
        CategoryStub::dropTable();
        CategoryStub::createTable();
        $this->controller = new BasicCrudStubController();
    }

    protected function tearDown(): void
    {
        CategoryStub::dropTable();
        parent::tearDown();
    }

    public function testIndex()
    {
        $category = CategoryStub::create(['name' => 'teste_name', 'description' => 'test_description']);
        $this->assertEquals([$category->toArray()], $this->controller->index()->toArray());
    }

    public function testInvalidationData()
    {
        $this->expectException(ValidationException::class);
        $request = Mockery::mock(Request::class);
        $request
            ->shouldReceive('all')
            ->once()
            ->andReturn(['name' => '']);

        $this->controller->store($request);
    }

    public function testStore()
    {
        $request = Mockery::mock(Request::class);
        $request
            ->shouldReceive('all')
            ->once()
            ->andReturn(['name' => 'teste_name', 'description' => 'test_description']);

        $obj = $this->controller->store($request);

        $this->assertEquals(
            CategoryStub::find(1)->toArray(),
            $obj->toArray()
        );
    }

    public function testFindOrFailFetchModel()
    {
        $category = CategoryStub::create(['name' => 'teste_name', 'description' => 'test_description']);

        $reflectionClass = new ReflectionClass(BasicCrudController::class);
        $relectionMethod = $reflectionClass->getMethod('findOrFail');
        $relectionMethod->setAccessible(true);

        $result = $relectionMethod->invokeArgs($this->controller, [$category->id]);
        $this->assertInstanceOf(CategoryStub::class, $result);
    }

    public function testIfFindOrFailThrowsExceptionWhenInvalid()
    {
        $this->expectException(ModelNotFoundException::class);
        $reflectionClass = new ReflectionClass(BasicCrudController::class);
        $relectionMethod = $reflectionClass->getMethod('findOrFail');
        $relectionMethod->setAccessible(true);

        $result = $relectionMethod->invokeArgs($this->controller, [0]);
    }
}
