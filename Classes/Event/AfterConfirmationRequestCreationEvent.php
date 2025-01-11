<?php

namespace WapplerSystems\FeRegistration\Event;

use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;

final readonly class AfterConfirmationRequestCreationEvent
{

    public function __construct(
        private ConfirmationRequest $validationRequest,
    )
    {

    }


    public function getConfirmationRequest(): ConfirmationRequest
    {
        return $this->validationRequest;
    }


}
