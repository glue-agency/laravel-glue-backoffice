<?php

namespace GlueAgency\Backoffice;

use Illuminate\Support\Facades\Http;

/**
 * Sends a report as the Dashboard has always taken one: form-encoded, to
 * /actions/backoffice/report, with the install's token. It answers
 * `{"success":true}`, or a 400 with the reasons it refused, field by field.
 *
 * Through the Http facade rather than an injected factory: before Laravel 9
 * the facade fakes only its own instance, never the container's.
 */
class Client
{
    private const TIMEOUT = 30;

    /**
     * Whether the Dashboard accepted the report, and every message it sent back.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: bool, 1: array<int, string>}
     */
    public function report(string $url, string $token, array $payload): array
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->asForm()
            ->timeout(self::TIMEOUT)
            ->post(rtrim($url, '/') . '/actions/backoffice/report', $payload);

        $body = $response->json();

        if ($response->successful() && is_array($body) && ($body['success'] ?? false) === true) {
            return [true, []];
        }

        return [false, self::errors($response->status(), $body, $response->body())];
    }

    /**
     * Every message, prefixed with the field it is about; the response itself
     * when it holds none, as a proxy's error page would.
     *
     * @param  mixed  $body
     * @return array<int, string>
     */
    public static function errors(int $status, $body, string $raw): array
    {
        $messages = [];

        if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $fieldMessages) {
                foreach ((array) $fieldMessages as $message) {
                    $messages[] = is_string($field) ? $field . ': ' . $message : (string) $message;
                }
            }
        }

        if (! $messages && is_array($body) && isset($body['message']) && is_string($body['message'])) {
            $messages[] = $body['message'];
        }

        if (! $messages) {
            $messages[] = sprintf('Unexpected response (HTTP %d): %s', $status, mb_substr(trim($raw), 0, 200));
        }

        return $messages;
    }
}
