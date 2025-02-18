<?php

namespace WapplerSystems\FeRegistration\Event;


final readonly class AfterRegistrationCompletionEvent
{

    public function __construct(
        private array $feUser,
        private array $formValues,
        private array $settings,
    )
    {

    }

    public function getFeUser(): array
    {
        return $this->feUser;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getFormValues(): array
    {
        return $this->formValues;
    }

}
