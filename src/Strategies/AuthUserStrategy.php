<?php

declare(strict_types=1);

namespace Vented\Plenum\Strategies;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Throwable;
use Vented\Plenum\Contracts\RoutingStrategy;

final class AuthUserStrategy implements RoutingStrategy
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ?string $guard = null,
    ) {}

    public function resolve(): int|string|null
    {
        try {
            $id = $this->auth->guard($this->guard)->id();
        } catch (Throwable) {
            return null;
        }

        return self::normalize($id);
    }

    public function name(): string
    {
        return 'auth-user';
    }

    private static function normalize(mixed $id): int|string|null
    {
        return match (true) {
            is_int($id) => $id,
            is_string($id) && $id !== '' => $id,
            default => null,
        };
    }
}
