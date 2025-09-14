<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('reset password link screen can be rendered', function () {
    // Arrange - no setup needed for route test

    // Act
    $response = $this->get(route('password.request'));

    // Assert
    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    // Arrange
    Notification::fake();
    $user = User::factory()->create();
    $emailData = ['email' => $user->email];

    // Act
    $this->post(route('password.email'), $emailData);

    // Assert
    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    // Arrange
    Notification::fake();
    $user = User::factory()->create();
    $emailData = ['email' => $user->email];

    // Act
    $this->post(route('password.email'), $emailData);

    // Assert
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));
        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    // Arrange
    Notification::fake();
    $user = User::factory()->create();
    $emailData = ['email' => $user->email];

    // Act
    $this->post(route('password.email'), $emailData);

    // Assert
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $resetData = [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
        $response = $this->post(route('password.store'), $resetData);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    // Arrange
    $user = User::factory()->create();
    $invalidResetData = [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ];

    // Act
    $response = $this->post(route('password.store'), $invalidResetData);

    // Assert
    $response->assertSessionHasErrors('email');
});
