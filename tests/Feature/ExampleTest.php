<?php

it('returns a successful response', function () {
    // Arrange

    // Act
    $response = $this->get('/');

    // Assert
    $response->assertStatus(200);
});
