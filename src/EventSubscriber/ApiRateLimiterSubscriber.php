<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ApiRateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $limiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $limiter = $this->limiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Too many requests. Limit: 60 requests per minute.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()],
            ));
        }
    }
}
