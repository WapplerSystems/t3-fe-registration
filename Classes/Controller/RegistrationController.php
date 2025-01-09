<?php

namespace WapplerSystems\FeRegistration\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use WapplerSystems\FeRegistration\Domain\Model\OptIn;
use WapplerSystems\FeRegistration\Domain\Repository\OptInRepository;
use WapplerSystems\FeRegistration\Event\AfterOptInValidationEvent;
use WapplerSystems\FeRegistration\Service\Mailer;

class RegistrationController extends ActionController
{


    public function __construct(readonly
                                OptInRepository $optInRepository,
                                EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * action validation
     *
     * @param string $hash
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function doAction($hash = ''): ResponseInterface
    {



        if ($hash !== '') {
            /** @var OptIn $optIn */
            $optIn = $this->optInRepository->findOneByValidationHash($hash);

            if ($optIn) {

                if ($optIn->getIsValidated()) {
                    $this->view->assign('alreadyConfirmed', true);
                    return $this->htmlResponse();
                }

                $optIn->setIsValidated(TRUE);
                $optIn->setValidationDate(new \DateTime);
                $this->optInRepository->update($optIn);

                $this->eventDispatcher->dispatch(
                    new AfterOptInValidationEvent($optIn)
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

        $optInRecord = $this->optInRepository->findOneByValidationHash($hash);
        if ($optInRecord) {

            if ($optInRecord->getIsValidated()) {
                return new JsonResponse(['success' => false, 'alreadyConfirmed' => true]);
            }
            if ($optInRecord->getLastSent() && $optInRecord->getLastSent()->getTimestamp() + (int)$this->settings['optInEmail']['timeLock'] > time()) {
                return new JsonResponse(['success' => false, 'wait' => true, 'nextSend' => $optInRecord->getLastSent()->getTimestamp() + (int)$this->settings['optInEmail']['timeLock']]);
            }

            /** @var Mailer $mailer */
            $mailer = GeneralUtility::makeInstance(Mailer::class);
            $mailer->sendOptInMail($optInRecord, $this->request, $this->settings);


            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false]);
    }


}
