<?php

use App\Models\Location;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->actingAs($this->user);
});

function plantPayload(Location $location, array $overrides = []): array
{
    return array_merge([
        'name' => 'Snake Plant',
        'scientific_name' => 'Dracaena trifasciata',
        'location_id' => $location->id,
    ], $overrides);
}

function plantsIndexUrl(array $query = []): string
{
    if ($query === []) {
        return '/api/plants';
    }

    return '/api/plants?'.http_build_query($query);
}

test('plant routes reject guests', function (): void {
    auth()->logout();

    $location = Location::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Living room',
    ]);

    $plant = Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Fern',
        'scientific_name' => 'Filicopsida',
        'description' => null,
        'image_url' => null,
    ]);

    $this->getJson('/api/plants')->assertUnauthorized();
    $this->postJson('/api/plants', plantPayload($location))->assertUnauthorized();
    $this->getJson("/api/plants/{$plant->id}")->assertUnauthorized();
    $this->putJson("/api/plants/{$plant->id}", ['name' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/plants/{$plant->id}")->assertUnauthorized();
});

test('index returns only the authenticated users plants', function (): void {
    $myLocation = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Mine']);
    $theirLocation = Location::query()->create(['user_id' => $this->otherUser->id, 'name' => 'Theirs']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $myLocation->id,
        'name' => 'My visible plant',
        'scientific_name' => 'Visibilis',
        'description' => null,
        'image_url' => null,
    ]);

    Plant::query()->create([
        'user_id' => $this->otherUser->id,
        'location_id' => $theirLocation->id,
        'name' => 'Secret plant',
        'scientific_name' => 'Secretus',
        'description' => null,
        'image_url' => null,
    ]);

    $response = $this->getJson('/api/plants');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('My visible plant')->not->toContain('Secret plant');
});

test('index search treats percent literally', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Hall']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Has 100% label',
        'scientific_name' => 'S',
        'description' => 'D',
        'image_url' => null,
    ]);
    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'No percent here',
        'scientific_name' => 'T',
        'description' => 'E',
        'image_url' => null,
    ]);

    $this->getJson(plantsIndexUrl(['search' => '100%']))->assertOk()->assertJsonCount(1, 'data');
});

test('index search treats underscore literally', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Hall']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'x_y',
        'scientific_name' => 'S',
        'description' => 'D',
        'image_url' => null,
    ]);
    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'plain',
        'scientific_name' => 'T',
        'description' => 'E',
        'image_url' => null,
    ]);

    $this->getJson(plantsIndexUrl(['search' => '_']))->assertOk()->assertJsonCount(1, 'data');
});

test('index search matches description and scientific columns', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Hall']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'n1',
        'scientific_name' => 'SciMarkerAAA',
        'description' => 'DescMarkerBBB',
        'image_url' => null,
    ]);
    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'n2',
        'scientific_name' => 'SciOther',
        'description' => 'DescOther',
        'image_url' => null,
    ]);

    $this->getJson(plantsIndexUrl(['search' => 'DescMarkerBBB']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(plantsIndexUrl(['search' => 'SciMarkerAAA']))->assertOk()->assertJsonCount(1, 'data');
});

test('index clamps per page and falls back invalid sort and direction', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Yard']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'A',
        'scientific_name' => 'AA',
        'description' => null,
        'image_url' => null,
    ]);

    $tooSmall = $this->getJson('/api/plants?per_page=0')->assertOk();
    expect($tooSmall->json('per_page'))->toBe(1);

    $tooLarge = $this->getJson('/api/plants?per_page=999')->assertOk();
    expect($tooLarge->json('per_page'))->toBe(100);

    $badSort = $this->getJson('/api/plants?sort=hacked&direction=asc')->assertOk();
    expect($badSort->json('data'))->not->toBeEmpty();

    $badDirection = $this->getJson('/api/plants?sort=name&direction= sideways')->assertOk();
    expect($badDirection->json('data'))->not->toBeEmpty();
});

test('index orders by allowed columns asc and desc', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Patio']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Zebra',
        'scientific_name' => 'Zebra zebra',
        'description' => null,
        'image_url' => null,
    ]);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Apple',
        'scientific_name' => 'Malus',
        'description' => null,
        'image_url' => null,
    ]);

    $asc = $this->getJson('/api/plants?sort=name&direction=asc')->json('data');
    expect(collect($asc)->pluck('name')->first())->toBe('Apple');

    $desc = $this->getJson('/api/plants?sort=name&direction=DESC')->json('data');
    expect(collect($desc)->pluck('name')->first())->toBe('Zebra');
});

test('index orders by multiple columns', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Yard']);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Ivy',
        'scientific_name' => 'Hedera helix',
        'description' => null,
        'image_url' => null,
    ]);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Ivy',
        'scientific_name' => 'Hedera algeriensis',
        'description' => null,
        'image_url' => null,
    ]);

    Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Oak',
        'scientific_name' => 'Quercus',
        'description' => null,
        'image_url' => null,
    ]);

    $sorted = $this->getJson('/api/plants?sort=name,scientific_name&direction=asc')->json('data');

    expect(collect($sorted)->pluck('scientific_name')->take(3)->values()->all())
        ->toBe(['Hedera algeriensis', 'Hedera helix', 'Quercus']);

    $bySciDesc = $this->getJson('/api/plants?sort=name,scientific_name&direction=asc,desc')->json('data');

    expect(collect($bySciDesc)->where('name', 'Ivy')->pluck('scientific_name')->values()->all())
        ->toBe(['Hedera helix', 'Hedera algeriensis']);
});

test('store creates a plant for the authenticated user', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Kitchen']);

    $payload = plantPayload($location, [
        'description' => 'Bright indirect light',
        'image_url' => 'https://example.com/plant.jpg',
    ]);

    $response = $this->postJson('/api/plants', $payload);

    $response->assertCreated()
        ->assertJsonPath('name', 'Snake Plant')
        ->assertJsonPath('user_id', $this->user->id)
        ->assertJsonPath('location_id', $location->id);

    $this->assertDatabaseHas('plants', [
        'id' => $response->json('id'),
        'user_id' => $this->user->id,
        'name' => 'Snake Plant',
    ]);
});

test('store accepts optional null description and image url omitted', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Bedroom']);

    $this->postJson('/api/plants', plantPayload($location, ['description' => null]))
        ->assertCreated()
        ->assertJsonPath('description', null);

    $this->postJson('/api/plants', plantPayload($location, ['name' => 'Second']))
        ->assertCreated();
});

test('store validates required fields types max length and url', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Office']);

    $this->postJson('/api/plants', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'scientific_name', 'location_id']);

    $this->postJson('/api/plants', [
        'name' => '',
        'scientific_name' => '',
        'location_id' => $location->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'scientific_name']);

    $this->postJson('/api/plants', [
        'name' => str_repeat('x', 256),
        'scientific_name' => 'Ok',
        'location_id' => $location->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

    $this->postJson('/api/plants', plantPayload($location, ['location_id' => 999_999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['location_id']);

    $this->postJson('/api/plants', plantPayload($location, ['image_url' => 'not-a-url']))
        ->assertUnprocessable()->assertJsonValidationErrors(['image_url']);
});

test('store allows location owned by another user if that location exists', function (): void {
    $foreignLocation = Location::query()->create(['user_id' => $this->otherUser->id, 'name' => 'Their shelf']);

    $this->postJson('/api/plants', plantPayload($foreignLocation))
        ->assertCreated()
        ->assertJsonPath('location_id', $foreignLocation->id);
});

test('show allows owner and forbids other users', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Desk']);

    $mine = Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Mine',
        'scientific_name' => 'Meus',
        'description' => null,
        'image_url' => null,
    ]);

    $theirLocation = Location::query()->create(['user_id' => $this->otherUser->id, 'name' => 'Their room']);
    $theirs = Plant::query()->create([
        'user_id' => $this->otherUser->id,
        'location_id' => $theirLocation->id,
        'name' => 'Theirs',
        'scientific_name' => 'Suus',
        'description' => null,
        'image_url' => null,
    ]);

    $this->getJson("/api/plants/{$mine->id}")->assertOk()->assertJsonPath('name', 'Mine');

    $this->actingAs($this->user)
        ->getJson("/api/plants/{$theirs->id}")
        ->assertForbidden();
});

test('update applies partial changes for owner and forbids strangers', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Cabinet']);
    $locationB = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Shelf']);

    $plant = Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Old',
        'scientific_name' => 'Vetus',
        'description' => 'Was here',
        'image_url' => null,
    ]);

    $theirLocation = Location::query()->create(['user_id' => $this->otherUser->id, 'name' => 'Foreign']);
    $theirPlant = Plant::query()->create([
        'user_id' => $this->otherUser->id,
        'location_id' => $theirLocation->id,
        'name' => 'Not mine',
        'scientific_name' => 'Alienus',
        'description' => null,
        'image_url' => null,
    ]);

    $this->putJson("/api/plants/{$plant->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('name', 'New')
        ->assertJsonPath('scientific_name', 'Vetus');

    $this->putJson("/api/plants/{$plant->id}", [
        'location_id' => $locationB->id,
        'description' => null,
        'image_url' => 'https://example.org/p.png',
    ])->assertOk()
        ->assertJsonPath('location_id', $locationB->id)
        ->assertJsonPath('description', null);

    $this->actingAs($this->user)
        ->putJson("/api/plants/{$theirPlant->id}", ['name' => 'Hack'])
        ->assertForbidden();

    $this->putJson("/api/plants/{$plant->id}", ['name' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['name']);

    $this->putJson("/api/plants/{$plant->id}", ['location_id' => 999_999])
        ->assertUnprocessable()->assertJsonValidationErrors(['location_id']);
});

test('update accepts empty payload as no-op', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Corner']);

    $plant = Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Stable',
        'scientific_name' => 'Stabilis',
        'description' => 'Keep',
        'image_url' => null,
    ]);

    $this->putJson("/api/plants/{$plant->id}", [])->assertOk()->assertJsonPath('name', 'Stable');

    $plant->refresh();
    expect($plant->description)->toBe('Keep');
});

test('destroy deletes for owner returns no content and forbids others', function (): void {
    $location = Location::query()->create(['user_id' => $this->user->id, 'name' => 'Window']);

    $mine = Plant::query()->create([
        'user_id' => $this->user->id,
        'location_id' => $location->id,
        'name' => 'Gone soon',
        'scientific_name' => 'Brevis',
        'description' => null,
        'image_url' => null,
    ]);

    $theirLocation = Location::query()->create(['user_id' => $this->otherUser->id, 'name' => 'Alt']);
    $theirs = Plant::query()->create([
        'user_id' => $this->otherUser->id,
        'location_id' => $theirLocation->id,
        'name' => 'Protected',
        'scientific_name' => 'Tutus',
        'description' => null,
        'image_url' => null,
    ]);

    $this->deleteJson("/api/plants/{$mine->id}")->assertNoContent();

    expect(Plant::query()->withTrashed()->find($mine->id)?->trashed())->toBeTrue();

    $this->actingAs($this->user)
        ->deleteJson("/api/plants/{$theirs->id}")
        ->assertForbidden();
});
