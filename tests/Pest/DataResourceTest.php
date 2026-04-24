<?php

use Momentum\Lock\Tests\Stubs\UserData;
use function Pest\Laravel\actingAs;
use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertEquals;

test('data resource can append permissions', function () {
    $user = user();

    actingAs($user);

    $data = UserData::from($user);

    $assertionMap = [
        'viewAny' => true,
        'view' => false,
        'create' => true,
        'update' => true,
        'delete' => false,
    ];

    assertArrayHasKey('permissions', $data->toArray());

    foreach ($assertionMap as $ability => $value) {
        assertEquals($value, $data->toArray()['permissions'][$ability]);
    }
});

test('data resource preserves permissions when collecting paginated models', function () {
    $authenticatedUser = user();
    \Momentum\Lock\Tests\Stubs\User::create(['username' => 'another-user']);

    actingAs($authenticatedUser);

    $data = UserData::collect(
        \Momentum\Lock\Tests\Stubs\User::query()->orderBy('id')->paginate(2)
    );

    $items = $data->items();

    assertArrayHasKey('permissions', $items[0]->toArray());
    assertArrayHasKey('permissions', $items[1]->toArray());

    assertEquals(true, $items[0]->toArray()['permissions']['update']);
    assertEquals(false, $items[0]->toArray()['permissions']['delete']);
    assertEquals(false, $items[1]->toArray()['permissions']['update']);
    assertEquals(true, $items[1]->toArray()['permissions']['delete']);
});
