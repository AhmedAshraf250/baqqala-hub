<?php

test('returns a successful response', function () {
    $response = $this->get(route('frontend.home'));

    $response->assertOk();
});
