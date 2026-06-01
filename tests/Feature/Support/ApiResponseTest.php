<?php

use App\Support\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

it('wraps paginated responses with data meta links and legacy key', function () {
    $response = ApiResponse::paginated('items', new LengthAwarePaginator([['id' => 1]], 1, 10));
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['data', 'items', 'meta', 'links'])
        ->and($payload['meta']['total'])->toBe(1);
});
