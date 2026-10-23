<?php

declare(strict_types=1);

it('renders the welcome page', function (): void {
    $this->get('/')->assertOk();
});

it('reports healthy on the health check route', function (): void {
    $this->get('/up')->assertOk();
});
