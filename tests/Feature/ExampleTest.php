<?php

test('redirects guests to the admin login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/admin/login');
});
