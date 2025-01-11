<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Model\FormElements\FileUpload;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3\CMS\Form\ViewHelpers\RenderRenderableViewHelper;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterConfirmationRequestCreationEvent;

class DoubleOptInFinisher extends EmailFinisher
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'recipientName' => '',
        'senderName' => '',
        'attachUploads' => true,
        'payloadElements' => [],
        'validationPid' => null,
        'useFluidEmail' => true,
        'addHtmlPart' => true,
        'templateName' => 'Default',
    ];


    public function __construct(readonly ConfirmationRequestRepository $optInRepository,
                                readonly EventDispatcherInterface      $eventDispatcher)
    {
    }


    /**
     * Executes this finisher
     * @throws FinisherException
     * @see AbstractFinisher::execute()
     *
     */
    protected function executeInternal()
    {

        /* passing options from default options to options for using in EmailFinisher */
        if (empty($this->options['subject'])) {
            $this->options['subject'] = LocalizationUtility::translate('subject.pleaseConfirmEmailAddress', 'fe_registration');
        }

        $formRuntime = $this->finisherContext->getFormRuntime();
        $elements = $formRuntime->getFormDefinition()->getRenderablesRecursively();
        $payloadElementsConfiguration = $this->parseOption('payloadElements');

        $senderAddress = $this->parseOption('senderAddress');
        $senderAddress = is_string($senderAddress) ? $senderAddress : '';


        $featureSiteEmail = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('form_extended', 'featureSiteEmail');
        if ($featureSiteEmail) {

            $flexformService = GeneralUtility::makeInstance(FlexFormService::class);
            $settings = $flexformService->convertFlexFormContentToArray($this->finisherContext->getRequest()->getAttribute('currentContentObject')?->data['pi_flexform'] ?? '');
            $settings = $settings['settings'] ?? [];

            /** @var Site $site */
            $site = $this->finisherContext->getRequest()->getAttribute('site');
            $senders = $site->getAttribute('senders');

            if (isset($settings['sender'])) {
                $senderName = '';
                foreach ($senders as $sender) {
                    if ($sender['email'] === ($settings['sender'] ?? '')) {
                        $senderName = $sender['name'];
                    }
                }
                $senderAddress = $settings['sender'] ?? '';
            }
        }


        $recipients = $this->getRecipients('recipients');

        if (empty($senderAddress)) {
            throw new FinisherException('A valid sender field and a sender address is required.', 1527145483);
        }
        if (empty($recipients)) {
            throw new FinisherException('The option "recipients" must be set for the EmailFinisher.', 1327060200);
        }


        /* Opt in data set  */
        $optIn = new ConfirmationRequest();
        $optIn->setEmail($recipients[0]->getAddress());

        if (is_array($payloadElementsConfiguration)) {
            $payload = $this->prepareData($payloadElementsConfiguration);
            $optIn->setDecodedValues($payload);
        }

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configuration = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $storagePid = $configuration['plugin.']['tx_formextended_doubleoptin.']['settings.']['optInStoragePid'] ?? -1;

        $optIn->setPid($storagePid);

        $this->optInRepository->add($optIn);

        $this->eventDispatcher->dispatch(
            new AfterConfirmationRequestCreationEvent($optIn)
        );

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();
        /* Opt in data set  */


        $languageBackup = null;
        // Flexform overrides write strings instead of integers so
        // we need to cast the string '0' to false.
        if (
            isset($this->options['addHtmlPart'])
            && $this->options['addHtmlPart'] === '0'
        ) {
            $this->options['addHtmlPart'] = false;
        }

        $subject = $this->parseOption('subject');

        $senderName = $this->parseOption('senderName');
        $senderName = is_string($senderName) ? $senderName : '';
        $replyToRecipients = $this->getRecipients('replyToRecipients');
        $carbonCopyRecipients = $this->getRecipients('carbonCopyRecipients');
        $blindCarbonCopyRecipients = $this->getRecipients('blindCarbonCopyRecipients');
        $addHtmlPart = $this->parseOption('addHtmlPart') ? true : false;
        $attachUploads = $this->parseOption('attachUploads');
        $title = (string)$this->parseOption('title') ?: $subject;

        if (empty($subject)) {
            throw new FinisherException('The option "subject" must be set for the EmailFinisher.', 1327060320);
        }
        if (empty($senderAddress)) {
            throw new FinisherException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210);
        }

        $formRuntime = $this->finisherContext->getFormRuntime();

        $translationService = GeneralUtility::makeInstance(TranslationService::class);
        if (is_string($this->options['translation']['language'] ?? null) && $this->options['translation']['language'] !== '') {
            $languageBackup = $translationService->getLanguage();
            $translationService->setLanguage($this->options['translation']['language']);
        }

        $mail = $this
            ->initializeFluidEmail($formRuntime)
            ->from(new Address($senderAddress, $senderName))
            ->to(...$recipients)
            ->subject($subject)
            ->format($addHtmlPart ? FluidEmail::FORMAT_BOTH : FluidEmail::FORMAT_PLAIN)
            ->assign('title', $title)
            ->assign('optIn', $optIn)
            ->assign('validationPid', $validationPid);

        if (!empty($replyToRecipients)) {
            $mail->replyTo(...$replyToRecipients);
        }

        if (!empty($carbonCopyRecipients)) {
            $mail->cc(...$carbonCopyRecipients);
        }

        if (!empty($blindCarbonCopyRecipients)) {
            $mail->bcc(...$blindCarbonCopyRecipients);
        }

        if (!empty($languageBackup)) {
            $translationService->setLanguage($languageBackup);
        }

        if ($attachUploads) {
            foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
                if (!$element instanceof FileUpload) {
                    continue;
                }
                $file = $formRuntime[$element->getIdentifier()];
                if ($file) {
                    if (is_array($file)) {
                        foreach ($file as $singleFile) {
                            if ($singleFile instanceof FileReference) {
                                $singleFile = $singleFile->getOriginalResource();
                            }
                            $mail->attach($singleFile->getContents(), $singleFile->getName(), $singleFile->getMimeType());
                        }
                        continue;
                    }
                    if ($file instanceof FileReference) {
                        $file = $file->getOriginalResource();
                    }
                    $mail->attach($file->getContents(), $file->getName(), $file->getMimeType());
                }
            }
        }

        GeneralUtility::makeInstance(MailerInterface::class)->send($mail);
    }


    /**
     * Prepare data for saving to database
     *
     * @param array $elementsConfiguration
     * @return mixed
     */
    protected function prepareData(array $elementsConfiguration)
    {
        $data = [];
        $formRuntime = $this->finisherContext->getFormRuntime();
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');

        foreach ($this->getFormValues() as $elementIdentifier => $elementValue) {

            if (!in_array($elementIdentifier, $elementsConfiguration, true)) {
                continue;
            }

            $element = $formRuntime->getFormDefinition()->getElementByIdentifier($elementIdentifier);
            if ($element !== null && ($element->getType() === 'Password' || $element->getType() === 'AdvancedPassword')) {
                $data[$elementIdentifier] = $hashInstance->getHashedPassword($elementValue);
            } else {
                $data[$elementIdentifier] = $elementValue;
            }

        }
        return $data;
    }

    /**
     * Returns the values of the submitted form
     *
     * @return array
     */
    protected function getFormValues(): array
    {
        return $this->finisherContext->getFormValues();
    }


    /**
     * Returns a form element object for a given identifier.
     *
     * @param string $elementIdentifier
     * @return FormElementInterface|null
     */
    protected function getElementByIdentifier(string $elementIdentifier)
    {
        return $this
            ->finisherContext
            ->getFormRuntime()
            ->getFormDefinition()
            ->getElementByIdentifier($elementIdentifier);
    }


    protected function initializeFluidEmail(FormRuntime $formRuntime): FluidEmail
    {
        $templateConfiguration = $GLOBALS['TYPO3_CONF_VARS']['MAIL'];

        if (is_array($this->options['templateRootPaths'] ?? null)) {
            $templateConfiguration['templateRootPaths'] = array_replace_recursive(
                $templateConfiguration['templateRootPaths'],
                $this->options['templateRootPaths']
            );
            ksort($templateConfiguration['templateRootPaths']);
        }

        if (is_array($this->options['partialRootPaths'] ?? null)) {
            $templateConfiguration['partialRootPaths'] = array_replace_recursive(
                $templateConfiguration['partialRootPaths'],
                $this->options['partialRootPaths']
            );
            ksort($templateConfiguration['partialRootPaths']);
        }

        if (is_array($this->options['layoutRootPaths'] ?? null)) {
            $templateConfiguration['layoutRootPaths'] = array_replace_recursive(
                $templateConfiguration['layoutRootPaths'],
                $this->options['layoutRootPaths']
            );
            ksort($templateConfiguration['layoutRootPaths']);
        }

        $fluidEmail = GeneralUtility::makeInstance(
            FluidEmail::class,
            GeneralUtility::makeInstance(TemplatePaths::class, $templateConfiguration)
        );

        if (!isset($this->options['templateName']) || $this->options['templateName'] === '') {
            throw new FinisherException('The option "templateName" must be set to use FluidEmail.', 1599834020);
        }

        // Migrate old template name to default FluidEmail name
        if ($this->options['templateName'] === '{@format}.html') {
            $this->options['templateName'] = 'Default';
        }

        $fluidEmail
            ->setRequest($this->finisherContext->getRequest())
            ->setTemplate($this->options['templateName'])
            ->assignMultiple([
                'finisherVariableProvider' => $this->finisherContext->getFinisherVariableProvider(),
                'form' => $formRuntime,
            ]);

        if (is_array($this->options['variables'] ?? null)) {
            $fluidEmail->assignMultiple($this->options['variables']);
        }

        $fluidEmail
            ->getViewHelperVariableContainer()
            ->addOrUpdate(RenderRenderableViewHelper::class, 'formRuntime', $formRuntime);

        return $fluidEmail;
    }

}
