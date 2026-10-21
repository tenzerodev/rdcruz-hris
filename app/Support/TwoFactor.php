<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Time-based one-time passwords (RFC 6238), compatible with Google Authenticator,
 * Microsoft Authenticator, Authy and similar apps: 6 digits, 30-second steps, SHA-1.
 */
class TwoFactor
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code, allowing one step of clock drift either way. A code can be used only once
     * per account ($replayKey), so a code seen over someone's shoulder can't be reused.
     */
    public static function verify(string $secret, string $code, ?string $replayKey = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $now = intdiv(now()->getTimestamp(), 30);
        $last = $replayKey ? (int) Cache::get("2fa-step:{$replayKey}", 0) : 0;

        foreach ([0, -1, 1] as $drift) {
            $step = $now + $drift;
            if ($step > $last && hash_equals(self::code($secret, $step), $code)) {
                if ($replayKey) {
                    Cache::put("2fa-step:{$replayKey}", $step, 300);
                }

                return true;
            }
        }

        return false;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function qr(string $uri): string
    {
        $options = new QROptions(['outputType' => 'svg', 'outputBase64' => false, 'eccLevel' => QRCode::ECC_M, 'svgUseFillAttributes' => true]);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', (new QRCode($options))->render($uri));
    }

    /** @return list<string> */
    public static function recoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text));
        $bits = '';
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
