<?php
// Routes

$app->get('/[{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->group('/users/{id:[0-9]+}', function () {
    // $this is $app
    $this->map(['GET', 'DELETE', 'PATCH', 'PUT'], '', function ($request, $response, $args) {
        // Find, delete, patch or replace user identified by $args['id']
    })->setName('user');
    $this->get('/reset-password', function ($request, $response, $args) {
        // Route for /users/{id:[0-9]+}/reset-password
        // Reset the password for user identified by $args['id']
        // $this is $container
    })->setName('user-password-reset');
});