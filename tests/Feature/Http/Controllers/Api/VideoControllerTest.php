<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\VideoController;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Video;
use Exception;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Mockery;
use Tests\Exceptions\TestException;
use Tests\TestCase;

class VideoControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testIndex()
    {
        $video = factory(Video::class)->create();
        $response = $this->get(route('videos.index'));

        $response
            ->assertStatus(200)
            ->assertJson([$video->toArray()]);
    }

    public function testShow()
    {
        $video = factory(Video::class)->create();
        $response = $this->get(route('videos.show', ['video' => $video->id]));

        $response
            ->assertStatus(200)
            ->assertJson($video->toArray());
    }

    public function testValidationData()
    {
        $response = $this->json('POST', route('videos.store'));
        $this->assertInvalidationRequired($response);

        $requestBody = [
            'title' => str_repeat('a', 256),
            'description' => 'description',
            'year_launched' => 'd',
            'opened' => 10,
            'rating' => '6',
            'duration' => 's',
            'categories_id' => 123,
            'genres_id' => 'a',
        ];

        $response = $this->json('POST', route('videos.store'), $requestBody);

        $this->assertInvalidationMax($response);
        $this->assertInvalidationBoolean($response);
        $this->assertInvalidationInteger($response);
        $this->assertInvalidationYear($response);
        $this->assertInvalidationRating($response);
        $this->assertInvalidationRelationship($response);

        $video = factory(Video::class)->create();
        $response = $this->json('PUT', route('videos.update',  ['video' => $video->id]), []);
        $this->assertInvalidationRequired($response);

        $response = $this->json(
            'PUT',
            route('videos.update',  ['video' => $video->id]),
            $requestBody
        );
        $this->assertInvalidationMax($response);
        $this->assertInvalidationBoolean($response);
        $this->assertInvalidationInteger($response);
        $this->assertInvalidationYear($response);
        $this->assertInvalidationRating($response);
        $this->assertInvalidationRelationship($response);
    }

    protected function assertInvalidationRequired(TestResponse $response)
    {
        $requiredFields = [
            'title',
            'description',
            'year_launched',
            'rating',
            'duration',
            'categories_id',
            'genres_id',
        ];

        foreach ($requiredFields as $key => $field) {
            $response
                ->assertJsonValidationErrors([$field])
                ->assertJsonFragment([
                    Lang::get('validation.required', ['attribute' => str_replace('_', ' ', $field)])
                ]);
        }

        $response
            ->assertStatus(422);
    }

    protected function assertInvalidationMax(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonFragment([
                Lang::get('validation.max.string', ['attribute' => 'title', 'max' => 255])
            ]);
    }

    protected function assertInvalidationBoolean(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opened'])
            ->assertJsonFragment([
                Lang::get('validation.boolean', ['attribute' => 'opened'])
            ]);
    }

    protected function assertInvalidationInteger(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration'])
            ->assertJsonFragment([
                Lang::get('validation.integer', ['attribute' => 'duration'])
            ]);
    }

    protected function assertInvalidationRating(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating'])
            ->assertJsonFragment([
                Lang::get('validation.in', ['attribute' => 'rating'])
            ]);
    }

    protected function assertInvalidationYear(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year_launched'])
            ->assertJsonFragment([
                'The year launched does not match the format Y.'
            ]);
    }

    protected function assertInvalidationRelationship(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categories_id'])
            ->assertJsonValidationErrors(['genres_id'])
            ->assertJsonFragment([
                Lang::get('validation.array', ['attribute' => 'categories id'])
            ])
            ->assertJsonFragment([
                Lang::get('validation.array', ['attribute' => 'genres id'])
            ]);
    }

    public function testStore()
    {
        $category = factory(Category::class)->create();
        $genre = factory(Genre::class)->create();

        $requestBody = [
            'title' => 'TestTitle',
            'description' => 'TestDescription',
            'year_launched' => 2021,
            'opened' => 1,
            'rating' => '12',
            'duration' => 8,
            'categories_id' => [$category->id],
            'genres_id' => [$genre->id]
        ];

        $response = $this->json('POST', route('videos.store'), $requestBody);

        $id = $response->json('id');
        $video = Video::find($id);

        $response
            ->assertStatus(201)
            ->assertJson($video->toArray());
        $this->assertEquals('TestTitle', $response->json('title'));
        $this->assertEquals('TestDescription', $response->json('description'));
        $this->assertEquals(2021, $response->json('year_launched'));
        $this->assertTrue($response->json('opened'));
        $this->assertEquals('12', $response->json('rating'));
        $this->assertEquals(8, $response->json('duration'));
    }

    public function testRoolbackStore()
    {
        $controller = Mockery::mock(VideoController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controller->shouldReceive('validate')
            ->withAnyArgs()
            ->andReturn([
                'title' => 'TestTitle',
                'description' => 'TestDescription',
                'year_launched' => 2021,
                'opened' => 1,
                'rating' => '12',
                'duration' => 8,
                'categories_id' => ['1'],
                'genres_id' => ['1']
            ]);

        $controller->shouldReceive('rulesStore')
            ->withAnyArgs()
            ->andReturn([]);

        $controller->shouldReceive('handleRelations')
            ->once()
            ->andThrow(new TestException());

        $request = Mockery::mock(Request::class);

        try {
            $controller->store($request);
        } catch (TestException $exception) {
            $this->assertCount(0, Video::all());
        }
    }

    public function testRoolbackUpdate()
    {
        $video = factory(Video::class)->create([
            'title' => 'TestTitle',
            'description' => 'TestDescription',
            'year_launched' => 2021,
            'opened' => 1,
            'rating' => '12',
            'duration' => 8
        ]);

        $controller = Mockery::mock(VideoController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controller->shouldReceive('validate')
            ->withAnyArgs()
            ->andReturn($video->get()->toArray());

        $controller->shouldReceive('rulesStore')
            ->withAnyArgs()
            ->andReturn([]);

        $controller->shouldReceive('findOrFail')
            ->withAnyArgs()
            ->andReturn($video);

        $controller->shouldReceive('handleRelations')
            ->once()
            ->andThrow(new TestException());

        $request = Mockery::mock(Request::class);

        try {
            $controller->update($request, $video->id);
        } catch (TestException $exception) {
            $video = Video::find($video->id);
            $this->assertEquals($video->created_at, $video->updated_at);
            $this->assertCount(1, Video::all());
        }
    }

    public function testUpdate()
    {
        $category = factory(Category::class)->create();
        $genre = factory(Genre::class)->create();

        $video = factory(Video::class)->create([
            'title' => 'TestTitle',
            'description' => 'TestDescription',
            'year_launched' => '2021',
            'opened' => 1,
            'rating' => '12',
            'duration' => 8,
        ]);

        $response = $this->json(
            'PUT',
            route('videos.update', ['video' => $video->id]),
            [
                'title' => 'TestTitleUpdate',
                'description' => 'TestDescriptionUpdated',
                'year_launched' => 2020,
                'rating' => 'L',
                'duration' => 1,
                'categories_id' => [$category->id],
                'genres_id' => [$genre->id]
            ]
        );

        $id = $response->json('id');
        $video = Video::find($id);

        $response
            ->assertStatus(200)
            ->assertJson($video->toArray())
            ->assertJsonFragment([
                'title' => 'TestTitleUpdate',
                'description' => 'TestDescriptionUpdated',
                'year_launched' => 2020,
                'rating' => 'L',
                'duration' => 1,
            ]);
    }

    public function testDestroy()
    {
        $video = factory(Video::class)->create();
        $response = $this->json('DELETE', route('videos.destroy', ['video' => $video->id]));
        $response->assertStatus(204);
        $this->assertNull(Video::find($video->id));
    }
}
