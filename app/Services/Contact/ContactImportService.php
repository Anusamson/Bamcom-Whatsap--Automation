<?php

namespace App\Services\Contact;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactImportService
{
    /**
     * Common column header mappings (case-insensitive & trimmed).
     *
     * @var array<string, list<string>>
     */
    protected array $headerAliases = [
        'first_name' => ['first_name', 'firstname', 'first', 'fname', 'given_name', 'name', 'full_name'],
        'last_name' => ['last_name', 'lastname', 'last', 'lname', 'surname', 'family_name'],
        'phone' => ['phone', 'phone_number', 'phonenumber', 'mobile', 'mobile_number', 'telephone', 'tel', 'whatsapp', 'whatsapp_number', 'contact_number', 'cell'],
        'email' => ['email', 'email_address', 'e-mail', 'mail'],
        'location' => ['location', 'city', 'state', 'address', 'residence', 'area', 'town'],
        'occupation' => ['occupation', 'job_title', 'job', 'profession', 'title', 'company', 'company_name', 'work'],
        'lead_source' => ['lead_source', 'source', 'channel', 'acquisition_source', 'origin'],
        'status' => ['status', 'stage', 'lifecycle_status', 'contact_status'],
        'assigned_agent' => ['assigned_agent', 'agent', 'assigned_to', 'representative', 'rep', 'agent_email', 'assigned_agent_email', 'assigned_user', 'sales_agent', 'agent_name', 'assigned_staff'],
        'preferred_language' => ['preferred_language', 'language', 'lang'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comments', 'description'],
    ];

    public function __construct(
        protected PhoneNormalizerService $phoneNormalizer
    ) {}

    /**
     * Import contacts from an uploaded CSV file.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importFromCsv(UploadedFile|string $file, array $options = [], ?User $importer = null): array
    {
        $content = is_string($file) ? $file : file_get_contents($file->getRealPath());

        if (empty($content)) {
            throw ValidationException::withMessages(['file' => 'The provided CSV file is empty.']);
        }

        return $this->import($content, $options, $importer, 'CSV File');
    }

    /**
     * Import contacts from a public/shared Google Sheet URL.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importFromGoogleSheet(string $sheetUrl, array $options = [], ?User $importer = null): array
    {
        $csvContent = $this->fetchGoogleSheetContent($sheetUrl);

        return $this->import($csvContent, $options, $importer, 'Google Sheet');
    }

    /**
     * Preview rows from an uploaded CSV file.
     *
     * @return array<string, mixed>
     */
    public function previewFromCsv(UploadedFile|string $file, int $maxRows = 5): array
    {
        $content = is_string($file) ? $file : file_get_contents($file->getRealPath());

        if (empty($content)) {
            throw ValidationException::withMessages(['file' => 'The provided CSV file is empty.']);
        }

        return $this->preview($content, $maxRows);
    }

    /**
     * Preview rows from a Google Sheet URL.
     *
     * @return array<string, mixed>
     */
    public function previewFromGoogleSheet(string $sheetUrl, int $maxRows = 5): array
    {
        $csvContent = $this->fetchGoogleSheetContent($sheetUrl);

        return $this->preview($csvContent, $maxRows);
    }

    /**
     * Fetch CSV content from a Google Sheet URL.
     *
     * @throws ValidationException
     */
    public function fetchGoogleSheetContent(string $sheetUrl): string
    {
        $trimmed = trim($sheetUrl);

        if (empty($trimmed) || ! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['sheet_url' => 'Please provide a valid Google Sheet URL.']);
        }

        $exportUrl = $this->extractGoogleSheetExportUrl($trimmed);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])
                ->get($exportUrl);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'sheet_url' => 'Unable to access Google Sheet (HTTP '.$response->status().'). Please verify the link.',
                ]);
            }

            $body = $response->body();

            // Check if response redirected to a Google login HTML page
            if (str_contains($body, '<!DOCTYPE html') || str_contains($body, '<html') || str_contains($body, 'accounts.google.com')) {
                throw ValidationException::withMessages([
                    'sheet_url' => 'Google Sheet is private or requires sign-in. Please change sharing to "Anyone with the link can view".',
                ]);
            }

            if (empty(trim($body))) {
                throw ValidationException::withMessages([
                    'sheet_url' => 'The Google Sheet appears to be empty.',
                ]);
            }

            return $body;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'sheet_url' => 'Failed to connect to Google Sheets: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Extract direct CSV export URL from various Google Sheets URL formats.
     */
    public function extractGoogleSheetExportUrl(string $url): string
    {
        // 1. Direct CSV export or published output URL
        if (str_contains($url, 'output=csv') || str_contains($url, 'format=csv')) {
            return $url;
        }

        // 2. Published web URLs (e.g. .../pubhtml -> .../pub?output=csv)
        if (preg_match('#/spreadsheets/d/e/([a-zA-Z0-9-_]+)/pub#', $url, $m)) {
            $gid = $this->extractGid($url) ?? '0';

            return "https://docs.google.com/spreadsheets/d/e/{$m[1]}/pub?output=csv&gid={$gid}";
        }

        // 3. Standard Google Sheets URL: https://docs.google.com/spreadsheets/d/{ID}/edit...
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $matches)) {
            $spreadsheetId = $matches[1];
            $gid = $this->extractGid($url) ?? '0';

            return "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";
        }

        // 4. Fallback: Return raw URL if already an external CSV endpoint
        return $url;
    }

    /**
     * Extract sheet tab GID from URL parameters or fragment.
     */
    protected function extractGid(string $url): ?string
    {
        if (preg_match('/[?&#]gid=([0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Preview parsed CSV content.
     *
     * @return array{total_rows: int, headers: list<string>, mapped_fields: array<string, string>, sample_rows: list<array<string, mixed>>}
     */
    public function preview(string $csvContent, int $maxRows = 5): array
    {
        $parsed = $this->parseCsv($csvContent);
        $totalRows = count($parsed['rows']);

        $sampleRows = [];
        $slice = array_slice($parsed['rows'], 0, $maxRows);

        foreach ($slice as $row) {
            $phone = $row['phone'] ?? '';
            $normalized = ! empty($phone) ? $this->phoneNormalizer->normalize($phone) : '';
            $isValidPhone = ! empty($normalized) && $this->phoneNormalizer->isValid($normalized);

            $existing = null;
            if ($isValidPhone) {
                $existing = Contact::where('phone', $normalized)->first();
            } elseif (! empty($row['email'])) {
                $existing = Contact::where('email', $row['email'])->first();
            }

            $sampleRows[] = [
                'raw' => $row,
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'phone' => $phone,
                'normalized_phone' => $normalized,
                'email' => $row['email'] ?? '',
                'location' => $row['location'] ?? '',
                'occupation' => $row['occupation'] ?? '',
                'lead_source' => $row['lead_source'] ?? '',
                'status' => $row['status'] ?? '',
                'is_valid_phone' => $isValidPhone,
                'has_duplicate' => (bool) $existing,
                'duplicate_name' => $existing?->full_name,
            ];
        }

        return [
            'success' => true,
            'total_rows' => $totalRows,
            'headers' => $parsed['headers'],
            'mapped_fields' => $parsed['field_mapping'],
            'sample_rows' => $sampleRows,
        ];
    }

    /**
     * Core import execution routine.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function import(string $csvContent, array $options = [], ?User $importer = null, string $sourceName = 'CSV'): array
    {
        $parsed = $this->parseCsv($csvContent);
        $rows = $parsed['rows'];
        $totalRows = count($rows);

        if ($totalRows === 0) {
            throw ValidationException::withMessages([
                'file' => 'No readable contact records found in the provided data.',
            ]);
        }

        $duplicateHandling = $options['duplicate_handling'] ?? 'skip'; // 'skip' | 'update'
        $defaultSource = $this->resolveLeadSource($options['default_lead_source'] ?? null);
        $defaultStatus = $this->resolveContactStatus($options['default_status'] ?? null);
        $defaultAssignedUserId = ! empty($options['default_assigned_user_id']) ? (int) $options['default_assigned_user_id'] : null;

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Accounting for 1-based index and header line

            try {
                $rawPhone = trim((string) ($row['phone'] ?? ''));
                $email = ! empty($row['email']) ? trim((string) $row['email']) : null;

                if (empty($rawPhone) && empty($email)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Row missing required phone number or email address.',
                        'data' => $row,
                    ];

                    continue;
                }

                $normalizedPhone = ! empty($rawPhone) ? $this->phoneNormalizer->normalize($rawPhone) : '';

                if (! empty($rawPhone) && ! $this->phoneNormalizer->isValid($normalizedPhone)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => "Invalid phone format [{$rawPhone}]. Standard international or 11-digit local format expected.",
                        'data' => $row,
                    ];

                    continue;
                }

                // Check for duplicate contact
                $existing = null;
                if (! empty($normalizedPhone)) {
                    $existing = Contact::where('phone', $normalizedPhone)->first();
                }
                if (! $existing && ! empty($email)) {
                    $existing = Contact::where('email', $email)->first();
                }

                // Handle Duplicates
                if ($existing) {
                    if ($duplicateHandling === 'update') {
                        $this->updateExistingContact($existing, $row, $importer, $sourceName);
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }

                    continue;
                }

                // Resolve Names
                $firstName = trim((string) ($row['first_name'] ?? ''));
                $lastName = trim((string) ($row['last_name'] ?? ''));

                if (empty($firstName)) {
                    $firstName = 'Contact';
                }

                // Resolve Lead Source & Status
                $leadSource = ! empty($row['lead_source'])
                    ? $this->resolveLeadSource($row['lead_source'], $defaultSource)
                    : $defaultSource;

                $status = ! empty($row['status'])
                    ? $this->resolveContactStatus($row['status'], $defaultStatus)
                    : $defaultStatus;

                // Resolve Assigned Agent
                $assignedUserId = $defaultAssignedUserId;
                if (! empty($row['assigned_agent'])) {
                    $lookupId = $this->lookupUserId($row['assigned_agent']);
                    if ($lookupId) {
                        $assignedUserId = $lookupId;
                    }
                }

                // Create new contact in transaction
                DB::transaction(function () use (
                    $firstName,
                    $lastName,
                    $normalizedPhone,
                    $email,
                    $row,
                    $leadSource,
                    $status,
                    $assignedUserId,
                    $importer,
                    $sourceName
                ): void {
                    $contact = Contact::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName ?: null,
                        'phone' => $normalizedPhone,
                        'email' => $email,
                        'location' => ! empty($row['location']) ? trim((string) $row['location']) : null,
                        'occupation' => ! empty($row['occupation']) ? trim((string) $row['occupation']) : null,
                        'preferred_language' => ! empty($row['preferred_language']) ? strtolower(trim((string) $row['preferred_language'])) : 'en',
                        'lead_source' => $leadSource,
                        'status' => $status,
                        'assigned_user_id' => $assignedUserId,
                        'last_contact_at' => now(),
                    ]);

                    // Add note if present
                    if (! empty($row['notes'])) {
                        Note::create([
                            'contact_id' => $contact->id,
                            'user_id' => $importer?->id,
                            'content' => trim((string) $row['notes']),
                        ]);
                    }

                    // Log activity
                    Activity::create([
                        'contact_id' => $contact->id,
                        'user_id' => $importer?->id,
                        'activity_type' => 'contact_imported',
                        'description' => "Contact imported from {$sourceName}",
                        'properties' => [
                            'source' => $sourceName,
                            'action' => 'created',
                        ],
                    ]);
                });

                $importedCount++;
            } catch (Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                    'data' => $row,
                ];
            }
        }

        return [
            'success' => true,
            'source' => $sourceName,
            'total_rows' => $totalRows,
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Update existing contact from row data.
     *
     * @param  array<string, mixed>  $row
     */
    protected function updateExistingContact(Contact $contact, array $row, ?User $importer, string $sourceName): void
    {
        $updates = [];

        if (! empty($row['first_name'])) {
            $updates['first_name'] = trim((string) $row['first_name']);
        }
        if (! empty($row['last_name'])) {
            $updates['last_name'] = trim((string) $row['last_name']);
        }
        if (! empty($row['email']) && empty($contact->email)) {
            $updates['email'] = trim((string) $row['email']);
        }
        if (! empty($row['location'])) {
            $updates['location'] = trim((string) $row['location']);
        }
        if (! empty($row['occupation'])) {
            $updates['occupation'] = trim((string) $row['occupation']);
        }
        if (! empty($row['lead_source'])) {
            $updates['lead_source'] = $this->resolveLeadSource($row['lead_source'], $contact->lead_source);
        }
        if (! empty($row['status'])) {
            $updates['status'] = $this->resolveContactStatus($row['status'], $contact->status);
        }

        $updates['last_contact_at'] = now();

        DB::transaction(function () use ($contact, $updates, $row, $importer, $sourceName): void {
            $contact->update($updates);

            if (! empty($row['notes'])) {
                Note::create([
                    'contact_id' => $contact->id,
                    'user_id' => $importer?->id,
                    'content' => '[Import Note] '.trim((string) $row['notes']),
                ]);
            }

            Activity::create([
                'contact_id' => $contact->id,
                'user_id' => $importer?->id,
                'activity_type' => 'contact_updated_from_import',
                'description' => "Contact details updated from {$sourceName}",
                'properties' => [
                    'source' => $sourceName,
                    'action' => 'updated',
                ],
            ]);
        });
    }

    /**
     * Parse raw CSV content into normalized structured records.
     *
     * @return array{headers: list<string>, field_mapping: array<string, string>, rows: list<array<string, mixed>>}
     */
    public function parseCsv(string $csvContent): array
    {
        // Strip UTF-8 BOM if present
        $cleanContent = preg_replace('/^\xEF\xBB\xBF/', '', trim($csvContent));

        // Detect delimiter
        $delimiter = $this->detectDelimiter($cleanContent);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $cleanContent);
        rewind($stream);

        $headers = [];
        $fieldMap = [];
        $rows = [];

        $isHeader = true;

        while (($data = fgetcsv($stream, 0, $delimiter)) !== false) {
            // Skip empty rows
            if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                continue;
            }

            if ($isHeader) {
                $headers = array_map(fn ($h) => trim((string) $h), $data);
                $fieldMap = $this->buildFieldMapping($headers);
                $isHeader = false;

                continue;
            }

            $mappedRow = [];
            foreach ($data as $colIdx => $val) {
                $targetField = $fieldMap[$colIdx] ?? null;
                if ($targetField) {
                    $mappedRow[$targetField] = trim((string) $val);
                }
            }

            // Split name into first and last name if only full name was mapped
            if (isset($mappedRow['first_name']) && ! isset($mappedRow['last_name'])) {
                $fullName = $mappedRow['first_name'];
                if (str_contains($fullName, ' ')) {
                    $parts = explode(' ', $fullName, 2);
                    $mappedRow['first_name'] = $parts[0];
                    $mappedRow['last_name'] = $parts[1] ?? '';
                }
            }

            if (! empty($mappedRow)) {
                $rows[] = $mappedRow;
            }
        }

        fclose($stream);

        return [
            'headers' => $headers,
            'field_mapping' => $fieldMap,
            'rows' => $rows,
        ];
    }

    /**
     * Map column indexes to canonical model fields based on alias dictionary.
     *
     * @param  list<string>  $headers
     * @return array<int, string>
     */
    protected function buildFieldMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', trim($header)));

            foreach ($this->headerAliases as $canonicalField => $aliases) {
                if (in_array($normalizedHeader, $aliases, true)) {
                    $mapping[$index] = $canonicalField;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Detect CSV delimiter (comma, semicolon, tab).
     */
    protected function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n");
        if (! $firstLine) {
            return ',';
        }

        $delimiters = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($delimiters);

        return array_key_first($delimiters) ?: ',';
    }

    /**
     * Resolve LeadSource enum instance from string input or default.
     */
    public function resolveLeadSource(mixed $input, LeadSource $default = LeadSource::Direct): LeadSource
    {
        if ($input instanceof LeadSource) {
            return $input;
        }

        if (empty($input)) {
            return $default;
        }

        $str = strtolower(trim((string) $input));

        // Exact match
        $found = LeadSource::tryFrom($str);
        if ($found) {
            return $found;
        }

        // Fuzzy / label match
        return match ($str) {
            'whatsapp', 'wa', 'whats_app' => LeadSource::WhatsApp,
            'website', 'web', 'site', 'portal' => LeadSource::Website,
            'referral', 'ref', 'friend', 'client_referral' => LeadSource::Referral,
            'social_media', 'social', 'facebook', 'instagram', 'ig', 'fb', 'linkedin', 'twitter' => LeadSource::SocialMedia,
            'google_ads', 'google', 'adwords', 'ads', 'ppc' => LeadSource::GoogleAds,
            'event', 'seminar', 'expo', 'exhibition', 'fair' => LeadSource::Event,
            'walk_in', 'walkin', 'office' => LeadSource::WalkIn,
            default => $default,
        };
    }

    /**
     * Resolve ContactStatus enum instance from string input or default.
     */
    public function resolveContactStatus(mixed $input, ContactStatus $default = ContactStatus::Lead): ContactStatus
    {
        if ($input instanceof ContactStatus) {
            return $input;
        }

        if (empty($input)) {
            return $default;
        }

        $str = strtolower(trim((string) $input));

        $found = ContactStatus::tryFrom($str);
        if ($found) {
            return $found;
        }

        return match ($str) {
            'lead', 'new', 'uncontacted' => ContactStatus::Lead,
            'prospect', 'qualified', 'interested' => ContactStatus::Prospect,
            'customer', 'client', 'buyer', 'investor' => ContactStatus::Customer,
            'dormant', 'cold' => ContactStatus::Dormant,
            'inactive', 'lost', 'unresponsive' => ContactStatus::Inactive,
            default => $default,
        };
    }

    /**
     * Lookup active user ID by email or name.
     */
    protected function lookupUserId(string $identifier): ?int
    {
        $trimmed = trim($identifier);
        if (empty($trimmed)) {
            return null;
        }

        return User::where(function ($q) use ($trimmed): void {
            $q->where('email', $trimmed)
                ->orWhere('name', 'like', "%{$trimmed}%");
        })->value('id');
    }

    /**
     * Generate standard downloadable sample CSV template with example records.
     */
    public function generateSampleCsvTemplate(): string
    {
        $headers = [
            'first_name',
            'last_name',
            'phone',
            'email',
            'location',
            'occupation',
            'lead_source',
            'status',
            'assigned_agent',
            'notes',
        ];

        $rows = [
            [
                'Chinedu',
                'Okafor',
                '08031234567',
                'chinedu.okafor@primecapital.ng',
                'Maitama, Abuja',
                'Senior Investment Director',
                'website',
                'lead',
                'agent@bamcom.ng',
                'Inquiring about 4-bedroom detached duplex in Guzape Heights',
            ],
            [
                'Amina',
                'Yusuf',
                '+2348129876543',
                'amina.yusuf@nordicenergy.com',
                'Wuse II, Abuja',
                'Managing Partner',
                'referral',
                'prospect',
                '',
                'Seeking commercial investment plots in Idu Industrial zone',
            ],
            [
                'Emeka',
                'Nnamdi',
                '09055551234',
                'emeka.nnamdi@fintechflow.io',
                'Victoria Island, Lagos',
                'Chief Technology Officer',
                'whatsapp',
                'customer',
                '',
                'Follow-up on deed of assignment for Peace Court Estate',
            ],
        ];

        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }
}
