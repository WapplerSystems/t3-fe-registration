<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class ConfirmationService
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher,
                                readonly DatabaseService               $databaseService)
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


    public function completeRegistration(ConfirmationRequest $confirmationRequest): void
    {

        $confirmationRequest->setConfirmationDate(new \DateTime());
        $this->confirmationRequestRepository->update($confirmationRequest);

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();
    }


}
