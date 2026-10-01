<?php

namespace App\Services\Email\Contracts;

/**
 * Contract for outbound email transport providers (SES, etc.).
 */
interface EmailProviderInterface
{
    /**
     * Send an email through the provider.
     *
     * @param array{
     *     to: string|list<string>,
     *     to_name?: ?string,
     *     from_email?: ?string,
     *     from_name?: ?string,
     *     reply_to?: ?string,
     *     subject: string,
     *     html: string,
     *     plain?: ?string,
     *     metadata?: array<string, mixed>,
     *     tags?: array<string, string>,
     *     configuration_set?: ?string
     * } $payload
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     error: ?string,
     *     response: array<string, mixed>
     * }
     */
    public function send(array $payload): array;

    /**
     * Get sending quotas and rates from the provider.
     *
     * @return array{
     *     max_24_hour_send: float,
     *     sent_last_24_hours: float,
     *     max_send_rate: float
     * }
     */
    public function getQuota(): array;

    /**
     * Get the identifier name of this provider.
     */
    public function getName(): string;
}
