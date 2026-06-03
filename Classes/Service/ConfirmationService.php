<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class ConfirmationService
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher,
                                readonly DatabaseService               $databaseService,
                                readonly PersistenceManager            $persistenceManager,
                                readonly LoggerInterface               $logger,
                                readonly Context                       $context)
    {
    }


    public function requestToFeUser(ConfirmationRequest $confirmationRequest, array $settings): ?array
    {
        $feUser = $this->databaseService->findFeUserByConfirmationRequest($confirmationRequest);
        if ($feUser !== false) {
            return $feUser;
        }

        if ((int)($settings['createFeUser'] ?? 0) === 1) {
            $values = $confirmationRequest->getDecodedValues();
            $values['registrationRequest'] = $confirmationRequest->getUid();
            $values['disable'] = ((int)($settings['feUserMustConfirmed'] ?? 0)) === 1 ? 1 : 0;
            return $this->databaseService->createFeUser($values, $settings);
        }
        return null;
    }


    public function findFeUserByConfirmationRequest(ConfirmationRequest $confirmationRequest): ?array
    {
        $feUser = $this->databaseService->findFeUserByConfirmationRequest($confirmationRequest);
        if ($feUser !== false) {
            return $feUser;
        }
        return null;
    }

    public function findByHash(string $hash): ?ConfirmationRequest
    {
        return $this->confirmationRequestRepository->findOneByConfirmationHash($hash);
    }

    public function setRegistrationCompleted(ConfirmationRequest $confirmationRequest): void
    {
        $confirmationRequest->setCompletionDate(new \DateTime());
        // The JSON-encoded original form payload (address, phone, hashed
        // password, …) is no longer needed once the user has been materialized
        // in fe_users. Drop it now to minimize data retention.
        $confirmationRequest->setDecodedValues([]);
        $this->confirmationRequestRepository->update($confirmationRequest);
        $this->persistenceManager->persistAll();
        $this->logger->info('Registration completed', ['email' => $confirmationRequest->getEmail(), 'uid' => $confirmationRequest->getUid()]);
    }

    public function setRequestConfirmed(ConfirmationRequest $confirmationRequest): void
    {
        /** @var \DateTimeImmutable $currentDateTime */
        $currentDateTime = $this->context->getPropertyFromAspect('date', 'full');
        $confirmationRequest->setConfirmationDate(\DateTime::createFromImmutable($currentDateTime));
        $this->confirmationRequestRepository->update($confirmationRequest);
        $this->persistenceManager->persistAll();
        $this->logger->info('Email confirmed', ['email' => $confirmationRequest->getEmail(), 'uid' => $confirmationRequest->getUid()]);
    }

    public function findUnconfirmedByEmail(string $email): ?ConfirmationRequest
    {
        return $this->confirmationRequestRepository->findUnconfirmedByEmail($email);


    }


}
