<?php

namespace Glorand\Model\Settings\Managers;

use Glorand\Model\Settings\Contracts\SettingsManagerContract;
use Glorand\Model\Settings\Exceptions\ModelSettingsException;
use Glorand\Model\Settings\Traits\HasSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Class AbstractSettingsManager
 * @package Glorand\Model\Settings\Managers
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
abstract class AbstractSettingsManager implements SettingsManagerContract
{
    /** @var Model */
    protected $model;

    /** @var string */
    protected $fieldName;

    /** @var array */
    protected $defaultSettings = [];

    /**
     * AbstractSettingsManager constructor.
     *
     * @param Model $model
     * @param string|null $fieldName  JSON column name; if null, uses model's default
     * @throws ModelSettingsException
     */
    public function __construct(Model $model, string $fieldName = null)
    {
        $this->model = $model;

        if (! in_array(HasSettings::class, class_uses_recursive($model))) {
            throw new ModelSettingsException('Wrong model, missing HasSettings trait.');
        }

        // Determine which JSON column to work with
        $this->fieldName = $fieldName ?: $model->getSettingsFieldName();
    }

    /**
     * Retrieve and decode the JSON from the active column.
     *
     * @return array
     */
    protected function getSettingsValue(): array
    {
        $raw = $this->model->getAttributeValue($this->fieldName) ?? '[]';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Retrieve default settings from the model's HasSettings trait.
     *
     * @return array
     */
    protected function getDefaultSettings(): array
    {
        return $this->model->getDefaultSettings();
    }

    /**
     * Check if array is associative and not sequential
     *
     * @param array $arr
     * @return bool
     */
    private static function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Flatten an associative array using dot notation
     *
     * @param array $array
     * @param string $prepend
     * @return array
     */
    public static function dotFlatten(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && static::isAssoc($value) && !empty($value)) {
                $results = array_merge($results, static::dotFlatten($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get merged array of defaults and stored settings
     *
     * @return array
     */
    public function all(): array
    {
        return array_replace_recursive(
            $this->getDefaultSettings(),
            $this->getSettingsValue()
        );
    }

    /**
     * Get flat merged array with dot-notation keys
     *
     * @return array
     */
    public function allFlattened(): array
    {
        $flattenedDefault = static::dotFlatten($this->getDefaultSettings());
        $flattenedStored  = static::dotFlatten($this->getSettingsValue());

        return array_merge($flattenedDefault, $flattenedStored);
    }

    /** @return bool */
    public function exist(): bool
    {
        return count($this->all()) > 0;
    }

    /** @return bool */
    public function empty(): bool
    {
        return count($this->all()) <= 0;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function has(string $path): bool
    {
        return Arr::has($this->all(), $path);
    }

    /**
     * @param string|null $path
     * @param mixed $default
     * @return mixed
     */
    public function get(?string $path = null, $default = null)
    {
        return $path
            ? Arr::get($this->all(), $path, $default)
            : $this->all();
    }

    /**
     * @param iterable|null $paths
     * @param mixed $default
     * @return array
     */
    public function getMultiple(?iterable $paths = null, $default = null): array
    {
        $settings = $this->all();
        $flattened = static::dotFlatten($settings);
        $rebuilt  = [];
        foreach ($flattened as $key => $value) {
            Arr::set($rebuilt, $key, $value);
        }

        if (is_null($paths)) {
            return $rebuilt;
        }

        $result = [];
        foreach ($paths as $path) {
            Arr::set($result, $path, Arr::get($rebuilt, $path, $default));
        }

        return $result;
    }

    /**
     * Set a single value then persist
     *
     * @param string $path
     * @param mixed $value
     * @return SettingsManagerContract
     */
    public function set(string $path, $value): SettingsManagerContract
    {
        $settings = $this->all();
        Arr::set($settings, $path, $value);

        return $this->apply($settings);
    }

    /**
     * Alias for set()
     */
    public function update(string $path, $value): SettingsManagerContract
    {
        return $this->set($path, $value);
    }

    /**
     * Delete a key or clear all
     *
     * @param string|null $path
     * @return SettingsManagerContract
     */
    public function delete(?string $path = null): SettingsManagerContract
    {
        if ($path === null) {
            $settings = [];
        } else {
            $settings = $this->all();
            Arr::forget($settings, $path);
        }

        return $this->apply($settings);
    }

    /**
     * Clear all settings
     *
     * @return SettingsManagerContract
     */
    public function clear(): SettingsManagerContract
    {
        return $this->delete();
    }

    /**
     * Set multiple values at once
     *
     * @param iterable $values
     * @return SettingsManagerContract
     */
    public function setMultiple(iterable $values): SettingsManagerContract
    {
        $settings = $this->all();
        foreach ($values as $path => $value) {
            Arr::set($settings, $path, $value);
        }

        return $this->apply($settings);
    }

    /**
     * Delete multiple paths
     *
     * @param iterable $paths
     * @return SettingsManagerContract
     */
    public function deleteMultiple(iterable $paths): SettingsManagerContract
    {
        $settings = $this->all();
        foreach ($paths as $path) {
            Arr::forget($settings, $path);
        }

        return $this->apply($settings);
    }

    /**
     * Validate settings against model rules
     *
     * @param array $settings
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(array $settings)
    {
        Validator::make(
            Arr::wrap($settings),
            Arr::wrap($this->model->getSettingsRules())
        )->validate();
    }

    /**
     * Apply() must be implemented by child classes
     *
     * @param array $settings
     * @return SettingsManagerContract
     */
    abstract public function apply(array $settings = []): SettingsManagerContract;
}
