<?php

namespace WapplerSystems\FeRegistration\Event;

use WapplerSystems\FeRegistration\Domain\Model\OptIn;

final class AfterOptInCreationEvent
{

    public function __construct(
        private readonly OptIn $optIn,
    ) {

    }


    public function getOptIn():OptIn {
        return $this->optIn;
    }




}
