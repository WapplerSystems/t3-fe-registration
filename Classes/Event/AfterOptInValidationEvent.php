<?php

namespace WapplerSystems\FeRegistration\Event;

use WapplerSystems\FeRegistration\Domain\Model\OptIn;

final class AfterOptInValidationEvent
{

    public function __construct(
        private readonly OptIn $optIn,
    ) {

    }


    public function getOptIn():OptIn {
        return $this->optIn;
    }




}
