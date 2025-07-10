<?php


namespace Glorand\Model\Settings\Managers;

use Glorand\Model\Settings\Contracts\SettingsManagerContract;

class FieldSettingsManager extends AbstractSettingsManager
{
    /**
     * @param  array  $settings
     */
    public function apply(array $settings = []): SettingsManagerContract
    {
        $this->validate($settings);

        // write JSON to our chosen column
        $this->model->{$this->fieldName} = json_encode($settings);

        if ($this->model->isPersistSettings()) {
            $this->model->save();
        }

        return $this;
    }


}
