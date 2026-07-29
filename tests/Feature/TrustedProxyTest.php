<?php

it('generates secure redirects behind the production proxy', function () {
    $this->withHeaders([
        'Host' => 'zentrum-tvde.com',
        'X-Forwarded-Host' => 'zentrum-tvde.com',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ])->get('/admin')
        ->assertRedirect('https://zentrum-tvde.com/admin/login');
});
