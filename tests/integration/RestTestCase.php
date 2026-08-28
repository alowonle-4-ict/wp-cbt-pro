<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration;

abstract class RestTestCase extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
    }

    protected function dispatch(string $method, string $route, array $params = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return rest_get_server()->dispatch($request);
    }
}
