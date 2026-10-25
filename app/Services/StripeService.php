<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\WebhookSignature;
use UnexpectedValueException;

/**
 * The only class that talks to Stripe: creates Checkout sessions and verifies webhook
 * signatures. Kept thin on purpose, so the payment rules live in actions and tests can swap
 * this class without network access.
 */
class StripeService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $webhookSecret,
        private readonly int $webhookTolerance = 300,
    ) {}

    /**
     * Online payment is offered only with a secret key.
     */
    public function isConfigured(): bool
    {
        return filled($this->secret);
    }

    /**
     * @param  array<string, mixed>  $params  Stripe's checkout.sessions.create parameters
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(array $params): array
    {
        $session = $this->client()->checkout->sessions->create($params);

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    /**
     * Checks the Stripe-Signature header against the raw body and returns the event as an
     * array. Throws when the signature is missing, wrong or too old (replayed).
     *
     * @return array<string, mixed>
     *
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function verifyWebhook(string $payload, ?string $signature): array
    {
        if (blank($this->webhookSecret)) {
            throw new UnexpectedValueException('No Stripe webhook secret is configured.');
        }

        WebhookSignature::verifyHeader($payload, (string) $signature, (string) $this->webhookSecret, $this->webhookTolerance);

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            throw new UnexpectedValueException('Not a Stripe event.');
        }

        /** @var array<string, mixed> $event */
        return $event;
    }

    private function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Stripe is not configured (STRIPE_SECRET).');
        }

        return $this->client ??= new StripeClient((string) $this->secret);
    }
}
