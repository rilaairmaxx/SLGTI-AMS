<?php
class Validator
{
    /** @var array Original data being validated */
    private array $data;

    /** @var array Collected error messages [ field => message ] */
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    
    /** Field must be present and non-empty */
    public function required(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val === '') {
            $this->addError($field, "{$label} is required.");
        }
        return $this;
    }

    /** Minimum string length */
    public function minLength(string $field, int $min, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
        return $this;
    }

    /** Maximum string length */
    public function maxLength(string $field, int $max, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if (mb_strlen($val) > $max) {
            $this->addError($field, "{$label} must not exceed {$max} characters.");
        }
        return $this;
    }

    /** Valid email address */
    public function email(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
        return $this;
    }

    /** Must be numeric (integer or float) */
    public function numeric(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '' && !is_numeric($val)) {
            $this->addError($field, "{$label} must be a number.");
        }
        return $this;
    }

    /** Must be a valid integer */
    public function integer(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '' && filter_var($val, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "{$label} must be a whole number.");
        }
        return $this;
    }

    /** Value must be in a given list */
    public function in(string $field, array $allowed, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = $this->data[$field] ?? '';
        if ($val !== '' && !in_array($val, $allowed, true)) {
            $list = implode(', ', $allowed);
            $this->addError($field, "{$label} must be one of: {$list}.");
        }
        return $this;
    }

    /** Value must match a regex pattern */
    public function regex(string $field, string $pattern, string $message = '')
    {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && !preg_match($pattern, $val)) {
            $this->addError($field, $message ?: "{$this->humanise($field)} format is invalid.");
        }
        return $this;
    }

    /** Two fields must match (e.g. password confirmation) */
    public function matches(string $field, string $otherField, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $a     = $this->data[$field]      ?? '';
        $b     = $this->data[$otherField] ?? '';
        if ($a !== $b) {
            $this->addError($field, "{$label} does not match.");
        }
        return $this;
    }

    /** Must be a valid date (Y-m-d) */
    public function date(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $val);
            if (!$d || $d->format('Y-m-d') !== $val) {
                $this->addError($field, "{$label} must be a valid date (YYYY-MM-DD).");
            }
        }
        return $this;
    }

    /** Date must not be in the future */
    public function notFutureDate(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $val);
            if ($d && $d > new DateTime('today')) {
                $this->addError($field, "{$label} cannot be a future date.");
            }
        }
        return $this;
    }

    /** Date must not be in the past */
    public function notPastDate(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $val);
            if ($d && $d < new DateTime('today')) {
                $this->addError($field, "{$label} cannot be a past date.");
            }
        }
        return $this;
    }

    /** Must be a valid Sri Lanka phone number */
    public function phone(string $field, string $label = '')
    {
        $label   = $label ?: $this->humanise($field);
        $val     = preg_replace('/\s+/', '', $this->data[$field] ?? '');
        $pattern = '/^(?:0|\+94)?(?:7[0-9]{8}|[0-9]{9})$/';
        if ($val !== '' && !preg_match($pattern, $val)) {
            $this->addError($field, "{$label} must be a valid phone number.");
        }
        return $this;
    }

    /** Must be a valid Sri Lanka NIC (old 9-digit or new 12-digit) */
    public function nic(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = strtoupper(trim($this->data[$field] ?? ''));
        if ($val !== '' && !preg_match('/^([0-9]{9}[VvXx]|[0-9]{12})$/', $val)) {
            $this->addError($field, "{$label} must be a valid NIC number.");
        }
        return $this;
    }

    /** Must be a valid student number format (e.g. SLGTI-2024-001) */
    public function studentNumber(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');
        // Allow any alphanumeric + dash/underscore 4-20 chars
        if ($val !== '' && !preg_match('/^[A-Za-z0-9\-_]{4,20}$/', $val)) {
            $this->addError($field, "{$label} must be 4–20 alphanumeric characters (dashes/underscores allowed).");
        }
        return $this;
    }

    /** Password strength: at least 1 uppercase, 1 digit, 1 special char */
    public function strongPassword(string $field, string $label = '')
    {
        $label = $label ?: $this->humanise($field);
        $val   = $this->data[$field] ?? '';
        if ($val !== '') {
            if (strlen($val) < 8) {
                $this->addError($field, "{$label} must be at least 8 characters.");
            } elseif (!preg_match('/[A-Z]/', $val)) {
                $this->addError($field, "{$label} must contain at least one uppercase letter.");
            } elseif (!preg_match('/[0-9]/', $val)) {
                $this->addError($field, "{$label} must contain at least one number.");
            } elseif (!preg_match('/[^A-Za-z0-9]/', $val)) {
                $this->addError($field, "{$label} must contain at least one special character.");
            }
        }
        return $this;
    }

    /** Must be one of: active, inactive, graduated, suspended */
    public function studentStatus(string $field, string $label = '')
    {
        return $this->in($field, ['active', 'inactive', 'graduated', 'suspended'], $label);
    }

    /** Must be one of: admin, lecturer */
    public function userRole(string $field, string $label = '')
    {
        return $this->in($field, ['admin', 'lecturer'], $label);
    }

    /** Must be one of: Present, Absent, Late, Excused */
    public function attendanceStatus(string $field, string $label = '')
    {
        return $this->in($field, ['Present', 'Absent', 'Late', 'Excused'], $label);
    }

    // ─────────────────────────────────────────────────────
    //  UNIQUENESS CHECK (requires DB connection)
    // ─────────────────────────────────────────────────────

    /**
     * Check if a value is unique in the database.
     * Optionally exclude a row by ID (for edit scenarios).
     *
     * @param mysqli  $conn
     * @param string  $field        POST field name
     * @param string  $table        DB table name
     * @param string  $column       DB column name
     * @param int     $excludeId    Row ID to exclude (0 = no exclusion)
     * @param string  $label
     */
    public function unique(
        mysqli $conn,
        string $field,
        string $table,
        string $column,
        int    $excludeId = 0,
        string $label     = ''
    ) {
        $label = $label ?: $this->humanise($field);
        $val   = trim($this->data[$field] ?? '');

        if ($val === '') return $this;

        if ($excludeId > 0) {
            $stmt = $conn->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $val, $excludeId);
        } else {
            $stmt = $conn->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
            $stmt->bind_param("s", $val);
        }

        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $this->addError($field, "{$label} is already taken. Please choose another.");
        }
        $stmt->close();

        return $this;
    }

   
    /** Returns true if any validation rule failed */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** Returns true if all validation rules passed */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** Return all errors as an associative array [ field => message ] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Return first error message for a specific field, or null */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /** Return the first error message across all fields, or null */
    public function firstErrorAny(): ?string
    {
        return !empty($this->errors) ? array_values($this->errors)[0] : null;
    }

    /** Return all errors as a flat list of strings */
    public function errorList(): array
    {
        return array_values($this->errors);
    }

    /** Return errors as a Bootstrap-compatible HTML alert string */
    public function toHtml(string $type = 'danger'): string
    {
        if ($this->passes()) return '';
        $items = implode('', array_map(
            fn($e) => "<li>{$e}</li>",
            $this->errorList()
        ));
        return <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">{$items}</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        HTML;
    }


    /** Sanitise a string for safe DB/display use */
    public static function sanitiseString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /** Sanitise and return an integer, default 0 */
    public static function sanitiseInt($value, int $default = 0): int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        return $v !== false ? (int)$v : $default;
    }

    /** Sanitise email */
    public static function sanitiseEmail(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /** Format a date string to Y-m-d, or return empty string on failure */
    public static function sanitiseDate(string $value): string
    {
        $d = DateTime::createFromFormat('Y-m-d', trim($value));
        return ($d && $d->format('Y-m-d') === trim($value)) ? $d->format('Y-m-d') : '';
    }

    /** Strip all HTML tags */
    public static function stripTags(string $value): string
    {
        return strip_tags(trim($value));
    }


    private function addError(string $field, string $message): void
    {
        // Only store the first error per field
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    private function humanise(string $field): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }
}
