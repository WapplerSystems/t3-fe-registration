<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterConfirmationEvent;

class ConfirmationService
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher,
                                readonly DatabaseService               $databaseService)
    {
    }


    public function requestToFeUser(ConfirmationRequest $confirmationRequest, array $settings): int
    {
        if ((int)($settings['createFeUser'] ?? 0) === 1) {

            $feUser = $this->databaseService->findFeUserByConfirmationRequest($confirmationRequest);

            if ($feUser !== false) {
                return $feUser['uid'];
            }

            $values = $confirmationRequest->getDecodedValues();
            $values['registrationRequest'] = $confirmationRequest->getUid();
            return $this->databaseService->createFeUser($values, $settings);
        }
        return 0;
    }


    public function completeRegistration(ConfirmationRequest $confirmationRequest): void
    {

        $confirmationRequest->setConfirmationDate(new \DateTime());
        $this->confirmationRequestRepository->update($confirmationRequest);

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();

        $this->eventDispatcher->dispatch(
            new AfterConfirmationEvent($confirmationRequest)
        );

    }


}
