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
use WapplerSystems\FeRegistration\Domain\Model\ValidationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ValidationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterValidationRequestCreationEvent;

class CreateValidationRequestFinisher extends EmailFinisher
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
    ];


    public function __construct(readonly ValidationRequestRepository $optInRepository,
                                readonly EventDispatcherInterface    $eventDispatcher)
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


        /* Opt in data set  */
        $optIn = new ValidationRequest();
        $optIn->setEmail($recipients[0]->getAddress());

        if (is_array($payloadElementsConfiguration)) {
            $payload = $this->prepareData($payloadElementsConfiguration);
            $optIn->setDecodedValues($payload);
        }

        $optIn->setPid($storagePid);

        $this->optInRepository->add($optIn);

        $this->eventDispatcher->dispatch(
            new AfterValidationRequestCreationEvent($optIn)
        );

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();
        /* Opt in data set  */


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
