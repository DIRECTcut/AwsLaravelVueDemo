<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    // Arrange - no setup needed for route test

    // Act
    $response = $this->get(route('register'));

    // Assert
    $response->assertStatus(200);
});

test('new users can register', function () {
    // Arrange
    $registrationData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    // Act
    $response = $this->post(route('register.store'), $registrationData);

    // Assert
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
