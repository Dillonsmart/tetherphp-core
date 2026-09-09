<?php

declare(strict_types=1);

use TetherPHP\Router;

/*
 * The route table the introspection commands are tested against.
 *
 * tests/bootstrap.php links this to <root>/routes, because `tether routes`,
 * `explain` and `context` all read project_root() . '/routes/web.php' — the
 * same file a real application defines its routes in.
 */
return function (Router $router): void {
    $router->get('/greet', TetherPHP\Tests\Fixtures\app\Actions\Greet::class);
    $router->get('/greet/{name}', TetherPHP\Tests\Fixtures\app\Actions\Greet::class);
    $router->post('/save', TetherPHP\Tests\Fixtures\app\Actions\Greet::class);
    $router->get('/broken', 'Actions\DoesNotExist');
    $router->get('/nope', TetherPHP\Tests\Fixtures\app\Actions\NotAnAction::class);
    $router->view('/static', 'errors.404');
};
