<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    // Arrange - no setup needed for route test

    // Act
    $response = $this->get(route('login'));

    // Assert
    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    // Arrange
    $user = User::factory()->create();
    $loginData = [
        'email' => $user->email,
        'password' => 'password',
    ];

    // Act
    $response = $this->post(route('login.store'), $loginData);

    // Assert
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    // Arrange
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $loginData = [
        'email' => $user->email,
        'password' => 'password',
    ];

    // Act
    $response = $this->post(route('login'), $loginData);

    // Assert
    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    // Arrange
    $user = User::factory()->create();
    $invalidLoginData = [
        'email' => $user->email,
        'password' => 'wrong-password',
    ];

    // Act
    $this->post(route('login.store'), $invalidLoginData);

    // Assert
    $this->assertGuest();
});

test('users can logout', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)->post(route('logout'));

    // Assert
    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    // Arrange
    $user = User::factory()->create();
    RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);
    $loginData = [
        'email' => $user->email,
        'password' => 'wrong-password',
    ];

    // Act
    $response = $this->post(route('login.store'), $loginData);

    // Assert
    $response->assertSessionHasErrors('email');
    $errors = session('errors');
    $this->assertStringContainsString('Too many login attempts', $errors->first('email'));
});
