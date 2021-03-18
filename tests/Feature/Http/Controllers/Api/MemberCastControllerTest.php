<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\CastMember;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class MemberCastControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testIndex()
    {
        $castMember = factory(CastMember::class)->create([
            'type' => CastMember::TYPE_DIRECTOR,
        ]);
        $response = $this->get(route('cast_members.index'));

        $response
            ->assertStatus(200)
            ->assertJson([$castMember->toArray()]);
    }

    public function testShow()
    {
        $castMember = factory(CastMember::class)->create([
            'type' => CastMember::TYPE_DIRECTOR,
        ]);
        $response = $this->get(route('cast_members.show', ['cast_member' => $castMember->id]));

        $response
            ->assertStatus(200)
            ->assertJson($castMember->toArray());
    }

    public function testValidationData()
    {
        $response = $this->json('POST', route('cast_members.store'));
        $this->assertInvalidationRequired($response);

        $response = $this->json('POST', route('cast_members.store'), [
            'name' => str_repeat('a', 256),
            'type' => 3
        ]);

        $this->assertInvalidationMax($response);
        $this->assertInvalidationType($response);

        $castMember = factory(CastMember::class)->create();
        $response = $this->json('PUT', route('cast_members.update',  ['cast_member' => $castMember->id]), []);
        $this->assertInvalidationRequired($response);

        $response = $this->json(
            'PUT',
            route('cast_members.update',  ['cast_member' => $castMember->id]),
            [
                'name' => str_repeat('a', 256),
                'type' => 3
            ]
        );
        $this->assertInvalidationMax($response);
        $this->assertInvalidationType($response);
    }

    protected function assertInvalidationRequired(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonValidationErrors(['type'])
            ->assertJsonFragment([
                Lang::get('validation.required', ['attribute' => 'name']),
            ])
            ->assertJsonFragment([
                Lang::get('validation.required', ['attribute' => 'type']),
            ]);
    }

    protected function assertInvalidationMax(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonFragment([
                Lang::get('validation.max.string', ['attribute' => 'name', 'max' => 255])
            ]);
    }

    protected function assertInvalidationType(TestResponse $response)
    {
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type'])
            ->assertJsonFragment([
                Lang::get('validation.in', ['attribute' => 'type'])
            ]);
    }

    public function testStore()
    {
        $response = $this->json(
            'POST',
            route('cast_members.store'),
            [
                'name' => 'Actor X',
                'type' => CastMember::TYPE_ACTOR,
            ]
        );

        $id = $response->json('id');
        $castMember = CastMember::find($id);

        $response
            ->assertStatus(201)
            ->assertJson($castMember->toArray());
        $this->assertEquals('Actor X', $response->json('name'));
        $this->assertEquals(CastMember::TYPE_ACTOR, $response->json('type'));

        $response = $this->json(
            'POST',
            route('cast_members.store'),
            [
                'name' => 'Director X',
                'type' => CastMember::TYPE_DIRECTOR,
            ]
        );

        $id = $response->json('id');
        $castMember = CastMember::find($id);

        $response
            ->assertStatus(201)
            ->assertJson($castMember->toArray());
        $this->assertEquals('Director X', $response->json('name'));
        $this->assertEquals(CastMember::TYPE_DIRECTOR, $response->json('type'));
    }

    public function testUpdate()
    {
        $castMember = factory(CastMember::class)->create([
            'type' => CastMember::TYPE_ACTOR,
            'name' => 'Actor X',
        ]);

        $response = $this->json(
            'PUT',
            route('cast_members.update', ['cast_member' => $castMember->id]),
            [
                'type' => CastMember::TYPE_DIRECTOR,
                'name' => 'Director X',
            ]
        );

        $id = $response->json('id');
        $castMember = CastMember::find($id);

        $response
            ->assertStatus(200)
            ->assertJson($castMember->toArray())
            ->assertJsonFragment([
                'type' => CastMember::TYPE_DIRECTOR,
                'name' => 'Director X',
            ]);
    }

    public function testDestroy()
    {
        $castMember = factory(CastMember::class)->create([
            'type' => CastMember::TYPE_ACTOR,
        ]);
        $response = $this->json('DELETE', route('cast_members.destroy', ['cast_member' => $castMember->id]));
        $response->assertStatus(204);
        $this->assertNull(CastMember::find($castMember->id));
    }
}
