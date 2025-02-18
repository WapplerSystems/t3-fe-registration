<?php

namespace WapplerSystems\FeRegistration\Event;


use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

final readonly class SetPredefinedRegistrationFormValuesEvent
{

    protected ?array $values;

    public function __construct(
        private FormRuntime $formRuntime
    )
    {
    }

    public function getFormRuntime(): FormRuntime
    {
        return $this->formRuntime;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    public function getValues(): ?array
    {
        return $this->values;
    }

}
