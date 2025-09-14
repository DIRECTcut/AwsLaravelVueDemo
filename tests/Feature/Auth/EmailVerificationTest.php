<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('email verification screen can be rendered', function () {
    // Arrange
    $user = User::factory()->unverified()->create();

    // Act
    $response = $this->actingAs($user)->get(route('verification.notice'));

    // Assert
    $response->assertStatus(200);
});

test('email can be verified', function () {
    // Arrange
    $user = User::factory()->unverified()->create();
    Event::fake();
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Act
    $response = $this->actingAs($user)->get($verificationUrl);

    // Assert
    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    // Arrange
    $user = User::factory()->unverified()->create();
    Event::fake();
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    // Act
    $this->actingAs($user)->get($verificationUrl);

    // Assert
    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    // Arrange
    $user = User::factory()->unverified()->create();
    Event::fake();
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)]
    );

    // Act
    $this->actingAs($user)->get($verificationUrl);

    // Assert
    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    // Arrange
    $user = User::factory()->create();
    Event::fake();

    // Act
    $response = $this->actingAs($user)->get(route('verification.notice'));

    // Assert
    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    // Arrange
    $user = User::factory()->create();
    Event::fake();
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Act
    $response = $this->actingAs($user)->get($verificationUrl);

    // Assert
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
