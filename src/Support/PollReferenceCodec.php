<?php

declare(strict_types=1);

namespace Capell\WidgetLivePoll\Support;

use Capell\LayoutBuilder\Support\WidgetSnapshots\WidgetSnapshotRequestDomain;
use Capell\WidgetLivePoll\Models\Poll;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use RuntimeException;

final readonly class PollReferenceCodec
{
    private const string PURPOSE = 'capell-live-poll:v1';

    public function __construct(private WidgetSnapshotRequestDomain $requestDomain) {}

    public function encode(Poll $poll): string
    {
        $payload = json_encode(['p' => self::PURPOSE, 'id' => $poll->id, 's' => $poll->site_id, 'l' => $poll->language_id], JSON_THROW_ON_ERROR);

        return sodium_bin2base64(Crypt::encryptString($payload), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function resolve(string $reference): ?Poll
    {
        if ($reference === '' || strlen($reference) > 4096) {
            return null;
        }

        try {
            $encrypted = sodium_base642bin($reference, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $payload = json_decode(Crypt::decryptString($encrypted), true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['p'] ?? null) !== self::PURPOSE || ! is_int($payload['id'] ?? null) || ! is_int($payload['s'] ?? null) || ! is_int($payload['l'] ?? null)) {
                return null;
            }

            if ($this->requestDomain->resolve($payload['s'], $payload['l']) === null) {
                return null;
            }

            $poll = Poll::query()->find($payload['id']);

            return $poll instanceof Poll && $poll->site_id === $payload['s'] && $poll->language_id === $payload['l'] && $poll->is_active ? $poll : null;
        } catch (DecryptException|JsonException|RuntimeException $exception) {
            return null;
        }
    }
}
