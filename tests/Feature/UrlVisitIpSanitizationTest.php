<?php

use RayzenAI\UrlManager\Models\UrlVisit;

function callSanitizeIp(?string $ip): ?string
{
    $method = new ReflectionMethod(UrlVisit::class, 'sanitizeIpAddress');
    $method->setAccessible(true);

    return $method->invoke(null, $ip);
}

it('takes the leftmost IP from an X-Forwarded-For chain', function () {
    expect(callSanitizeIp('2400:1a00:4ba5:fc2f:15d7:661d:37a8:c550,13.202.187.206'))
        ->toBe('2400:1a00:4ba5:fc2f:15d7:661d:37a8:c550');

    expect(callSanitizeIp('203.0.113.5, 13.202.187.206, 10.0.0.1'))
        ->toBe('203.0.113.5');
});

it('returns null for empty or invalid IPs', function () {
    expect(callSanitizeIp(null))->toBeNull();
    expect(callSanitizeIp(''))->toBeNull();
    expect(callSanitizeIp('not-an-ip'))->toBeNull();
    expect(callSanitizeIp('999.999.999.999'))->toBeNull();
});

it('passes through a valid single IPv4 or IPv6 address', function () {
    expect(callSanitizeIp('203.0.113.5'))->toBe('203.0.113.5');
    expect(callSanitizeIp('2400:1a00:4ba5:fc2f:15d7:661d:37a8:c550'))
        ->toBe('2400:1a00:4ba5:fc2f:15d7:661d:37a8:c550');
});

it('keeps the result within the 45-char column limit', function () {
    $longest = '2400:1a00:4ba5:fc2f:15d7:661d:37a8:c550';
    expect(strlen(callSanitizeIp($longest)))->toBeLessThanOrEqual(45);
});
