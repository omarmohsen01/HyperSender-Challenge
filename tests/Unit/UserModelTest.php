<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can be created', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('Password123!'),
    ];

    $user = User::create($userData);

    expect($user->name)->toBe('John Doe');
    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

test('user can be updated', function () {
    $user = User::factory()->create();

    $updateData = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ];

    $user->update($updateData);

    $this->assertDatabaseHas('users', array_merge(['id' => $user->id], $updateData));
});

test('user can be deleted', function () {
    $user = User::factory()->create();

    $user->delete();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('user has required attributes', function () {
    $user = User::factory()->create();

    expect($user->name)->not->toBeNull();
    expect($user->email)->not->toBeNull();
    expect($user->password)->not->toBeNull();
    expect($user->created_at)->not->toBeNull();
});

test('user password is hashed', function () {
    $user = User::factory()->create();

    expect(Hash::needsRehash($user->password))->toBeTrue();
    expect($user->password)->not->toBe('plaintext');
});

test('user email is unique', function () {
    User::factory()->create(['email' => 'test@example.com']);

    expect(fn() => User::create([
        'name' => 'Another User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('user can authenticate', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123')
    ]);

    expect(\Illuminate\Support\Facades\Auth::attempt([
        'email' => $user->email,
        'password' => 'password123'
    ]))->toBeTrue();
});

test('user factory creates valid user', function () {
    $user = User::factory()->create();

    expect($user->name)->not->toBeNull();
    expect($user->email)->not->toBeNull();
    expect($user->password)->not->toBeNull();
    expect($user->name)->toBeString();
    expect($user->email)->toBeString();
    expect($user->password)->toBeString();
});

test('user can be created with factory', function () {
    $user = User::factory()->create([
        'name' => 'Custom Name',
        'email' => 'custom@example.com'
    ]);

    expect($user->name)->toBe('Custom Name');
    expect($user->email)->toBe('custom@example.com');
});
