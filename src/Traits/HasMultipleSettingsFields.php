<?php
namespace App\Traits;

use Glorand\Model\Settings\Contracts\SettingsManagerContract;
use Glorand\Model\Settings\Managers\FieldSettingsManager;
use Glorand\Model\Settings\Exceptions\ModelSettingsException;

trait HasMultipleSettingsFields
{
    /**
     * List here all JSON columns you want to manage.
     * e.g. ['settings', 'address', 'preferences']
     */
    public array $settingsFieldNames = ['settings'];

    /**
     * Whether to auto-persist settings to the model after each change.
     * Inherited from the original package.
     */
    protected bool $persistSettings = true;

    /**
     * Get the manager for a given settings‐column.
     *
     * @param string|null $field  Column name; defaults to the first in $settingsFieldNames
     * @return SettingsManagerContract
     * @throws ModelSettingsException
     */
    public function settings(string $field = null): SettingsManagerContract
    {
        // default to first configured name
        $field = $field ?: $this->getDefaultSettingsFieldName();

        if (!in_array($field, $this->settingsFieldNames, true)) {
            throw new ModelSettingsException("Field [{$field}] is not registered.");
        }

        // pass the model *and* the column name to the manager
        return new FieldSettingsManager($this, $field);
    }


    /**
     * Return the default column name (the first one configured).
     */
    protected function getDefaultSettingsFieldName(): string
    {
        return $this->settingsFieldNames[0];
    }

    /**
     * Magic‐call so that $model->address()->get('street') works
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, $this->settingsFieldNames, true)) {
            return $this->settings($method);
        }

        return parent::__call($method, $parameters);
    }
}
