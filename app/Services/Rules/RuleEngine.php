<?php

namespace App\Services\Rules;

use App\Models\BusinessRule;
use App\Models\BusinessRuleRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Governed resolver/writer for configurable business rules.
 *
 * Reads apply the rule's schema, effective window, safe default and failure
 * behaviour. Writes are schema-validated, versioned and appended to an
 * immutable revision log. Rules flagged non-editable are hard controls: they
 * always resolve from code and can never be changed through the admin UI.
 */
class RuleEngine
{
    protected RuleRegistry $registry;

    /** @var array<string, BusinessRule|null> */
    protected array $rows = [];

    protected bool $loaded = false;

    /** @var array<string, bool> */
    protected array $warned = [];

    public function __construct(?RuleRegistry $registry = null)
    {
        $this->registry = $registry ?? app(RuleRegistry::class);
    }

    public function registry(): RuleRegistry
    {
        return $this->registry;
    }

    public function definition(string $key): ?array
    {
        return $this->registry->definition($key);
    }

    public function flush(): void
    {
        $this->rows = [];
        $this->loaded = false;
    }

    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->rows = BusinessRule::query()->get()->keyBy('key')->all();
        $this->loaded = true;
    }

    protected function row(string $key): ?BusinessRule
    {
        $this->load();

        return $this->rows[$key] ?? null;
    }

    /**
     * The row that currently governs a key: active and inside its effective
     * window. Returns null when the rule is absent, disabled or out of window.
     */
    public function resolve(string $key): ?BusinessRule
    {
        $row = $this->row($key);

        if (! $row || ! $row->is_active) {
            return null;
        }

        $now = Carbon::now();

        if ($row->effective_from && $row->effective_from->greaterThan($now)) {
            return null;
        }

        if ($row->effective_to && $row->effective_to->lessThan($now)) {
            return null;
        }

        return $row;
    }

    public function string(string $key, $default = null): ?string
    {
        $def = $this->definition($key);

        if (! $def) {
            $row = $this->row($key);

            if ($row && $row->is_active) {
                return $row->value === null ? $default : (string) $row->value;
            }

            return $default === null ? null : (string) $default;
        }

        if (($def['editable'] ?? true) === false) {
            return (string) ($def['default'] ?? '');
        }

        $row = $this->resolve($key);

        if (! $row) {
            return (string) ($def['default'] ?? '');
        }

        if ($this->validates($def, $row->value)) {
            return $row->value === null ? (string) ($def['default'] ?? '') : (string) $row->value;
        }

        return (string) $this->onInvalid($key, $def, $row->value);
    }

    public function number(string $key, $default = 0): float
    {
        return (float) $this->string($key, $default);
    }

    public function boolean(string $key, $default = false): bool
    {
        $value = $this->string($key, $default ? '1' : '0');

        return in_array($value, ['1', 'true', 'yes', 'on'], true) || (bool) $value;
    }

    public function select(string $key, array $allowed, $default = null): ?string
    {
        $value = $this->string($key, $default);

        return in_array((string) $value, array_map('strval', $allowed), true) ? (string) $value : $default;
    }

    /**
     * Lightweight schema check used on the (hot) read path. Handles the
     * constrained tokens the registry uses without spinning up the full
     * validator, and never throws so an invalid stored value can fall back to
     * the rule's failure behaviour.
     */
    protected function validates(array $def, $value): bool
    {
        $rules = $def['rules'] ?? null;

        if (! $rules) {
            return true;
        }

        $tokens = is_array($rules) ? $rules : explode('|', $rules);

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '' || $token === 'nullable' || $token === 'string') {
                continue;
            }

            if ($token === 'required') {
                if ($value === null || $value === '') {
                    return false;
                }
            } elseif ($token === 'boolean') {
                if (! in_array($value, [true, false, 1, 0, '1', '0'], true)) {
                    return false;
                }
            } elseif ($token === 'integer') {
                if (! preg_match('/^-?\d+$/', (string) $value)) {
                    return false;
                }
            } elseif ($token === 'numeric') {
                if (! is_numeric($value)) {
                    return false;
                }
            } elseif (str_starts_with($token, 'min:')) {
                if (! is_numeric($value) || (float) $value < (float) substr($token, 4)) {
                    return false;
                }
            } elseif (str_starts_with($token, 'max:')) {
                if (! is_numeric($value) || (float) $value > (float) substr($token, 4)) {
                    return false;
                }
            } elseif (str_starts_with($token, 'in:')) {
                if (! in_array((string) $value, explode(',', substr($token, 3)), true)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function onInvalid(string $key, array $def, $value)
    {
        if (($def['on_invalid'] ?? 'default') === 'block') {
            throw new RuleBlockedException("Business rule [{$key}] has an invalid configured value and is set to block.");
        }

        if (empty($this->warned[$key])) {
            $this->warned[$key] = true;
            Log::warning('Business rule resolved to an invalid value; using the safe default.', [
                'key' => $key,
                'value' => $value,
                'default' => $def['default'] ?? null,
            ]);
        }

        return $def['default'] ?? null;
    }

    /**
     * Validated, versioned value change. Operators cannot write non-editable
     * (hard control) rules or values that violate the schema.
     *
     * @throws ValidationException
     */
    public function setValue(string $key, $value, ?int $actorId = null, ?string $reason = null, ?Carbon $effectiveFrom = null, ?Carbon $effectiveTo = null): BusinessRule
    {
        $def = $this->definition($key);

        if (! $def) {
            throw ValidationException::withMessages(['rules.'.$key => "Rule [{$key}] is not a registered business rule."]);
        }

        if (($def['editable'] ?? true) === false) {
            throw ValidationException::withMessages(['rules.'.$key => "Rule [{$key}] is a locked platform control and cannot be edited."]);
        }

        $normalized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        if (! $this->validates($def, $normalized)) {
            throw ValidationException::withMessages(['rules.'.$key => "Value for [{$key}] does not satisfy its schema ({$def['rules']})."]);
        }

        $rule = BusinessRule::firstOrNew(['key' => $key]);

        $rule->fill([
            'name' => $rule->name ?: ($def['name'] ?? $key),
            'value_type' => $def['type'] ?? 'text',
            'validation_rules' => $def['rules'] ?? null,
            'default_value' => $def['default'] ?? null,
            'on_invalid' => $def['on_invalid'] ?? 'default',
            'category' => $rule->category ?: ($def['category'] ?? null),
            'owner' => $rule->owner ?: ($def['owner'] ?? null),
            'is_managed' => true,
        ]);

        $rule->value = $normalized;
        $rule->is_active = $rule->exists ? $rule->is_active : true;
        $rule->version = ($rule->exists ? (int) $rule->version : 0) + 1;

        if ($effectiveFrom) {
            $rule->effective_from = $effectiveFrom;
        }
        if ($effectiveTo) {
            $rule->effective_to = $effectiveTo;
        }

        $rule->updated_by = $actorId;
        $rule->save();

        $this->writeRevision($rule, $actorId, $reason);
        $this->flush();

        return $rule;
    }

    public function setActive(string $key, bool $active, ?int $actorId = null, ?string $reason = null): BusinessRule
    {
        $rule = BusinessRule::firstOrNew(['key' => $key]);

        if (! $rule->exists && ! $this->definition($key)) {
            throw ValidationException::withMessages(['rules.'.$key => "Rule [{$key}] is not a registered business rule."]);
        }

        $rule->is_active = $active;
        $rule->version = ($rule->exists ? (int) $rule->version : 0) + 1;
        $rule->updated_by = $actorId;
        $rule->save();

        $this->writeRevision($rule, $actorId, $reason);
        $this->flush();

        return $rule;
    }

    /**
     * Restore a prior version's value as a new revision (never rewrites history).
     */
    public function revert(string $key, int $version, ?int $actorId = null, ?string $reason = null): BusinessRule
    {
        $revision = BusinessRuleRevision::where('key', $key)->where('version', $version)->first();

        if (! $revision) {
            throw ValidationException::withMessages(['rules.'.$key => "Version {$version} of [{$key}] does not exist."]);
        }

        return $this->setValue($key, $revision->value, $actorId, $reason ?? "Reverted to version {$version}.");
    }

    protected function writeRevision(BusinessRule $rule, ?int $actorId, ?string $reason): void
    {
        $rule->revisions()->create([
            'key' => $rule->key,
            'version' => $rule->version,
            'value' => $rule->value,
            'is_active' => $rule->is_active,
            'effective_from' => $rule->effective_from,
            'effective_to' => $rule->effective_to,
            'changed_by' => $actorId,
            'reason' => $reason,
            'snapshot' => $rule->only([
                'key', 'name', 'value', 'value_type', 'is_active', 'version',
                'effective_from', 'effective_to', 'category', 'owner',
            ]),
        ]);
    }
}
