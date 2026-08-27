<?php

declare(strict_types=1);

namespace WPCBTPro\REST;

/**
 * Central REST wiring point (§18). Concrete controllers (Attempts, Answers,
 * Camera, Programming, DSA — arriving in later phases) register themselves
 * via the 'wpcbtpro_rest_controllers' filter instead of each calling
 * register_rest_route() independently, so the namespace and versioning stay
 * consistent in one place.
 */
final class RestServiceProvider
{
    public const NAMESPACE_V1 = 'wp-cbt-pro/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerControllers']);
    }

    public function registerControllers(): void
    {
        /** @var RestController[] $controllers */
        $controllers = apply_filters('wpcbtpro_rest_controllers', []);

        foreach ($controllers as $controller) {
            if ($controller instanceof RestController) {
                $controller->registerRoutes();
            }
        }
    }
}
