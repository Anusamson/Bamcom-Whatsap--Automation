<?php

namespace App\Services\Contact;

/**
 * Service to standardize and normalize phone numbers into international E.164 format.
 * Prevents duplicates caused by diverse input formats (spaces, brackets, local prefixes).
 */
class PhoneNormalizerService
{
    /**
     * Default country calling code (Nigeria = 234 for Bamcom Real Estate / AI CRM).
     */
    protected string $defaultCountryCode = '234';

    /**
     * Normalize a phone number to standard E.164 format (e.g. +2348012345678).
     */
    public function normalize(string $phone, ?string $defaultCountryCode = null): string
    {
        $countryCode = $defaultCountryCode ?? $this->defaultCountryCode;

        // 1. Remove all whitespace, hyphens, periods, brackets, slashes
        $cleaned = (string) preg_replace('/[\s\-\(\)\.\/]/', '', trim($phone));

        if ($cleaned === '') {
            return '';
        }

        // 2. Standardize international double zero '00' to '+'
        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+'.substr($cleaned, 2);
        }

        // 3. If already formatted with leading '+', strip any internal invalid characters
        if (str_starts_with($cleaned, '+')) {
            $digitsOnly = (string) preg_replace('/\D/', '', substr($cleaned, 1));

            return '+'.$digitsOnly;
        }

        // 4. Handle Nigerian local format starting with 0 (e.g. 08012345678 -> 11 digits)
        if (str_starts_with($cleaned, '0')) {
            $digits = substr($cleaned, 1);

            return '+'.$countryCode.$digits;
        }

        // 5. Handle numbers starting directly with country code without '+' (e.g. 2348012345678)
        if (str_starts_with($cleaned, $countryCode)) {
            return '+'.$cleaned;
        }

        // 6. Handle 10-digit Nigerian numbers missing the leading zero (e.g. 8012345678)
        if (strlen($cleaned) === 10 && in_array(substr($cleaned, 0, 2), ['70', '80', '81', '90', '91', '71', '82'])) {
            return '+'.$countryCode.$cleaned;
        }

        // 7. General international or standard numeric fallback
        $digits = (string) preg_replace('/\D/', '', $cleaned);

        return '+'.$digits;
    }

    /**
     * Determine whether the normalized phone string conforms to international E.164.
     */
    public function isValid(string $phone): bool
    {
        $normalized = $this->normalize($phone);

        // International E.164 standard: + followed by country code and 7 to 15 digits
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $normalized);
    }

    /**
     * Format a normalized E.164 phone number for human-friendly visual display.
     */
    public function format(string $phone): string
    {
        $normalized = $this->normalize($phone);

        // Format Nigerian numbers: +234 801 234 5678
        if (str_starts_with($normalized, '+234') && strlen($normalized) === 14) {
            return sprintf(
                '+234 %s %s %s',
                substr($normalized, 4, 3),
                substr($normalized, 7, 3),
                substr($normalized, 10, 4)
            );
        }

        return $normalized;
    }

    /**
     * Generate a direct WhatsApp click-to-chat URL.
     */
    public function toWhatsAppUrl(string $phone, ?string $message = null): string
    {
        $normalized = $this->normalize($phone);
        $rawNumber = ltrim($normalized, '+');

        $url = 'https://wa.me/'.$rawNumber;

        if (! empty($message)) {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}
