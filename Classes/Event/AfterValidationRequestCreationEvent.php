<?php

namespace WapplerSystems\FeRegistration\Event;

use WapplerSystems\FeRegistration\Domain\Model\ValidationRequest;

final class AfterValidationRequestCreationEvent
{

    public function __construct(
        private readonly ValidationRequest $validationRequest,
    ) {

    }


    public function getValidationRequest():ValidationRequest {
        return $this->validationRequest;
    }




}
