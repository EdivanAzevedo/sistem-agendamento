<?php

declare(strict_types=1);

it('reports the application as alive', function () {
    $this->get('/up')->assertOk();
});
