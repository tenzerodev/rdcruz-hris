<?php

namespace App\Services;

use App\Models\DocumentVerification;
use App\Models\Employee;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Issues verification codes for printed documents. Printing the same content again reuses its code;
 * when the content changes (e.g. a corrected DTR), a new code is issued and the old one shows as
 * superseded on the verification page.
 */
class DocumentVerifier
{
    // No 0/O/1/I/L to avoid misreading a printed code.
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** @param  array<string, string|int|float|null>  $summary  label => value, shown publicly */
    public function issue(string $type, string $subjectKey, string $title, array $summary, ?Employee $employee = null): DocumentVerification
    {
        $summary = array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, array_filter($summary, fn ($v) => $v !== null && $v !== ''));
        $fingerprint = hash('sha256', json_encode([$type, $title, $summary], JSON_UNESCAPED_UNICODE));

        $existing = DocumentVerification::where('subject_key', $subjectKey)->where('fingerprint', $fingerprint)->whereNull('revoked_at')->first();
        if ($existing) {
            return $existing;
        }

        return DocumentVerification::create([
            'code' => $this->newCode(),
            'type' => $type,
            'subject_key' => $subjectKey,
            'employee_id' => $employee?->id,
            'title' => Str::limit($title, 250, ''),
            'summary' => $summary,
            'fingerprint' => $fingerprint,
            'issued_by' => auth()->id(),
        ]);
    }

    /** Inline SVG QR code pointing at the verification page. */
    public function qr(DocumentVerification $doc): string
    {
        $options = new QROptions(['outputType' => 'svg', 'outputBase64' => false, 'eccLevel' => QRCode::ECC_M, 'addQuietzone' => false, 'svgUseFillAttributes' => true]);

        $svg = (new QRCode($options))->render($doc->url());

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    private function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (DocumentVerification::where('code', $code)->exists());

        return $code;
    }
}
