<?php

declare(strict_types=1);

namespace Vented\Plenum\Strategies;

use Illuminate\Contracts\Session\Session;
use Throwable;
use Vented\Plenum\Contracts\RoutingStrategy;

final class SessionOnlyStrategy implements RoutingStrategy
{
    public function __construct(private readonly Session $session) {}

    public function resolve(): ?string
    {
        try {
            $id = $this->session->getId();
        } catch (Throwable) {
            return null;
        }

        return $id === '' ? null : $id;
    }

    public function name(): string
    {
        return 'session';
    }
}
