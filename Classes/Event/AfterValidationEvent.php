<?php

namespace WapplerSystems\FeRegistration\Event;

use WapplerSystems\FeRegistration\Domain\Model\ValidationRequest;

final class AfterValidationEvent
{

    public function __construct(
        private readonly ValidationRequest $optIn,
    ) {

    }


    public function getOptIn():ValidationRequest {
        return $this->optIn;
    }




}
