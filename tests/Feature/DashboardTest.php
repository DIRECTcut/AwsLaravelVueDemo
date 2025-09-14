<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    // Arrange - no setup needed for guest test

    // Act
    $response = $this->get(route('dashboard'));

    // Assert
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    // Arrange
    $user = User::factory()->create();
    $this->actingAs($user);

    // Act
    $response = $this->get(route('dashboard'));

    // Assert
    $response->assertStatus(200);
});
