<?php

namespace WapplerSystems\FeRegistration\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterConfirmationEvent;
use WapplerSystems\FeRegistration\Service\Mailer;

class DoubleOptInController extends ActionController
{


    public function __construct(readonly ConfirmationRequestRepository $optInRepository,
                                EventDispatcherInterface               $eventDispatcher)
    {
    }

    /**
     *
     * @param string $hash
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function confirmAction($hash = ''): ResponseInterface
    {
        if ($hash !== '') {
            /** @var ConfirmationRequest $optIn */
            $optIn = $this->optInRepository->findOneByConfirmationHash($hash);

            if ($optIn) {

                if ($optIn->isConfirmed()) {
                    $this->view->assign('alreadyConfirmed', true);
                    return $this->htmlResponse();
                }

                $optIn->setIsValidated(TRUE);
                $optIn->setConfirmationDate(new \DateTime);
                $this->optInRepository->update($optIn);

                $this->eventDispatcher->dispatch(
                    new AfterConfirmationEvent($optIn)
                );

                if (isset($this->settings['forward']) && (int)$this->settings['forward'] > 0) {
                    $url = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->setTargetPageUid($this->settings['forward'])->build();
                    $this->redirectToUri($url);
                }

                if ((int)($this->settings['createFeUser'] ?? 0) === 1) {

                    $values = $optIn->getDecodedValues();

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
                        'fe_users'
                    );
                    $queryBuilder
                        ->insert('fe_users')
                        ->values([
                            'pid' => (int)$this->settings['feUserStoragePid'],
                            'usergroup' => $this->settings['usergroups'] ?? '',
                            'username' => $values['email'],
                            'email' => $values['email'],
                            'first_name' => $values['firstName'] ?? '',
                            'last_name' => $values['lastName'] ?? '',
                            'password' => $values['password'],
                            'crdate' => time(),
                            'tstamp' => time(),
                            'deleted' => 0,
                            'disable' => 0,
                        ])
                        ->executeStatement();

                    $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                    $persistenceManager->persistAll();
                }


                $this->view->assign('success', true);
                return $this->htmlResponse();
            }
        }

        $this->view->assign('notFound', true);

        return $this->htmlResponse();
    }


    public function resendOptInEmailAction(): ResponseInterface
    {
        $hash = $this->request->getQueryParams()['hash'] ?? '';

        $optInRecord = $this->optInRepository->findOneByConfirmationHash($hash);
        if ($optInRecord) {

            if ($optInRecord->getIsValidated()) {
                return new JsonResponse(['success' => false, 'alreadyConfirmed' => true]);
            }
            if ($optInRecord->getLastSent() && $optInRecord->getLastSent()->getTimestamp() + (int)$this->settings['optInEmail']['timeLock'] > time()) {
                return new JsonResponse(['success' => false, 'wait' => true, 'nextSend' => $optInRecord->getLastSent()->getTimestamp() + (int)$this->settings['optInEmail']['timeLock']]);
            }

            /** @var Mailer $mailer */
            $mailer = GeneralUtility::makeInstance(Mailer::class);
            try {
                $mailer->sendconfirmationMail($optInRecord, $this->request, $this->settings);
            } catch (Exception $e) {

                return new JsonResponse(['success' => false]);
            }


            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false]);
    }


}
