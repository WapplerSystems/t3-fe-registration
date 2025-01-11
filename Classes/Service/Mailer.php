<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ValidationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\OptInRepository;

class Mailer
{


    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws FinisherException
     */
    public function sendOptInMail(ValidationRequest $optIn, ServerRequestInterface $request, array $settings): void
    {


        $validationPid = $settings['validationPid'] ?? null;
        if (empty($validationPid)) {
            throw new FinisherException('The option "validationPid" must be set.', 1527148282);
        }
        $receiverAddress = $optIn->getEmail();

        $addHtmlPart = $settings['optInEmail']['useHTML'] ?? false;

        $senderAddress = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
        $senderName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? '';


        $senderAddress = $settings['optInEmail']['senderAddress'] ?? $senderAddress;
        $senderName = $settings['optInEmail']['senderName'] ?? $senderName;

        if (empty($senderAddress)) {
            throw new Exception('The option "senderAddress" must be set.', 1735853778);
        }
        if (empty($senderName)) {
            throw new Exception('The option "senderName" must be set.', 1735853779);
        }


        $mail = $this
            ->initializeFluidEmail( $settings, $request)
            ->from(new Address($senderAddress, $senderName))
            ->to($receiverAddress)
            ->subject(LocalizationUtility::translate('subject.pleaseConfirmEmailAddress', 'fe_registration'))
            ->format($addHtmlPart ? FluidEmail::FORMAT_BOTH : FluidEmail::FORMAT_PLAIN)
            ->assign('title', LocalizationUtility::translate('subject.pleaseConfirmEmailAddress', 'fe_registration'))
            ->assign('optIn', $optIn)
            ->assign('validationPid', $validationPid);

        /*
        if (!empty($languageBackup)) {
            $translationService->setLanguage($languageBackup);
        }*/


        GeneralUtility::makeInstance(MailerInterface::class)->send($mail);

        $optIn->setLastSent(new \DateTime());

        $optInRepository = GeneralUtility::makeInstance(OptInRepository::class);
        $optInRepository->update($optIn);

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();

    }


    protected function initializeFluidEmail(array $options, ServerRequestInterface $request): FluidEmail
    {
        $templatePaths = $this->initializeTemplatePaths(
            $GLOBALS['TYPO3_CONF_VARS']['MAIL'],
            $options,
        );
        /** @var FluidEmail $fluidEmail */
        $fluidEmail = GeneralUtility::makeInstance(FluidEmail::class, $templatePaths);


        $fluidEmail
            ->setRequest($request)
            ->setTemplate('DoubleOptInEmail');

        if (is_array($options['variables'] ?? null)) {
            $fluidEmail->assignMultiple($options['variables']);
        }

        return $fluidEmail;
    }


    protected function initializeTemplatePaths(array $globalConfig, array $localConfig): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(array_replace(
            $globalConfig['templateRootPaths'] ?? [],
            $localConfig['templateRootPaths'] ?? [],
        ));
        $templatePaths->setLayoutRootPaths(array_replace(
            $globalConfig['layoutRootPaths'] ?? [],
            $localConfig['layoutRootPaths'] ?? [],
        ));
        $templatePaths->setPartialRootPaths(array_replace(
            $globalConfig['partialRootPaths'] ?? [],
            $localConfig['partialRootPaths'] ?? [],
        ));
        return $templatePaths;
    }

}
