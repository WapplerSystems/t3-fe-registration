<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Form\Factory;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\EmailAddressValidator;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use TYPO3\CMS\Extbase\Validation\Validator\StringLengthValidator;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\CMS\Form\Domain\Configuration\ConfigurationService;
use TYPO3\CMS\Form\Domain\Configuration\Exception\PrototypeNotFoundException;
use TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException;
use TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException;
use TYPO3\CMS\Form\Domain\Factory\AbstractFormFactory;
use TYPO3\CMS\Form\Domain\Model\Exception\FinisherPresetNotFoundException;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\GridRow;
use TYPO3\CMS\Form\Domain\Model\FormElements\Section;
use TYPO3\CMS\Form\Domain\Renderer\FluidFormRenderer;
use WapplerSystems\FeRegistration\Validation\Validator\FeUsernameAlreadyExistsValidator;
use WapplerSystems\FeRegistration\Validation\Validator\ConfirmationRequestAlreadyExistsValidator;

class RegistrationFormFactory extends AbstractFormFactory
{
    /**
     * @param array $configuration
     * @param string|null $prototypeName
     * @param ServerRequestInterface|null $request
     * @return FormDefinition
     * @throws FinisherPresetNotFoundException
     * @throws PrototypeNotFoundException
     * @throws TypeDefinitionNotFoundException
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     * @throws TypeDefinitionNotValidException
     */
    public function build(array $configuration, ?string $prototypeName = null, ?ServerRequestInterface $request = null): FormDefinition
    {
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($GLOBALS['TSFE']->id);
        $context = GeneralUtility::makeInstance(Context::class);
        $currentLanguage = $site->getDefaultLanguage();
        $languages = $site->getLanguages();
        foreach ($languages as $language) {
            if ($language->getLanguageId() === (int)$context->getPropertyFromAspect('language', 'id')) {
                $currentLanguage = $language;
                break;
            }
        }

        $resolver = GeneralUtility::makeInstance(ValidatorResolver::class);


        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
        $prototypeConfiguration = $configurationService->getPrototypeConfiguration('standard');

        /** @var FormDefinition $formDefinition */
        $formDefinition = GeneralUtility::makeInstance(
            FormDefinition::class,
            'registrationForm',
            $prototypeConfiguration
        );
        $formDefinition->setRendererClassName(FluidFormRenderer::class);
        $formDefinition->setRenderingOption('controllerAction', 'registration');
        $formDefinition->setRenderingOption('submitButtonLabel', 'Submit registration');

        $doubleOptInFormFinisher = $formDefinition->createFinisher('DoubleOptIn');

        $doubleOptInFormFinisher->setOption('validationPid', $configuration['validationPid']);
        $doubleOptInFormFinisher->setOption('subject', 'Please confirm your email address');
        $doubleOptInFormFinisher->setOption('recipientAddress', '{email}');
        $doubleOptInFormFinisher->setOption('recipients', ['{email}' => '{firstName} {lastName}']);
        $doubleOptInFormFinisher->setOption('senderAddress', $configuration['senderAddress']);
        $doubleOptInFormFinisher->setOption('senderName', $configuration['senderName']);
        $doubleOptInFormFinisher->setOption('useFluidEmail', true);
        $doubleOptInFormFinisher->setOption('payloadElements', [
            'email',
            'firstName',
            'lastName',
            'company',
            'street',
            'postalcode',
            'city',
        ]);

        $doubleOptInFormFinisher->setOption('translation', [
            'language' => $currentLanguage->getHreflang(),
        ]);

        $confirmationFinisher = $formDefinition->createFinisher('Confirmation');
        $confirmationFinisher->setOptions([
            'message' => LocalizationUtility::translate(
                'LLL:EXT:form_extended/Resources/Private/Language/Frontend.xlf:msg.pleaseConfirmEmailAddress'
            ),
            'templateName' => 'Confirmation',
            'templateRootPaths' => [
                10 => 'EXT:form_extended/Resources/Private/Templates/Form/Finisher/Confirmation/',
            ],
        ]);

        $page = $formDefinition->createPage('page1');

        /** @var GridRow $row */
        $row = $page->createElement('row1', 'GridRow');

        /** @var Section $fieldset */
        $fieldset = $row->createElement('fieldsetPerson', 'Fieldset');
        $fieldset->setLabel('Personal data');
        $fieldset->setOptions([
            'properties' => [
                'gridColumnClassAutoConfiguration' => [
                    'viewPorts' => [
                        'xs' => [
                            'numbersOfColumnsToUse' => 12,
                        ],
                        'sm' => [
                            'numbersOfColumnsToUse' => 12,
                        ],
                        'md' => [
                            'numbersOfColumnsToUse' => 6,
                        ],
                    ],
                ],
            ],
        ]);


        /** @var GenericFormElement $element */
        $element = $fieldset->createElement('firstName', 'Text');
        $element->setLabel('firstName');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 100]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('lastName', 'Text');
        $element->setLabel('lastName');
        $element->setProperty('required', true);
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 100]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('email', 'Text');
        $element->setLabel('email');
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(FeUsernameAlreadyExistsValidator::class));
        $element->addValidator($resolver->createValidator(ConfirmationRequestAlreadyExistsValidator::class, ['pid' => $configuration['optInStoragePid']]));
        $element->addValidator($resolver->createValidator(EmailAddressValidator::class));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('telephone', 'Text');
        $element->setLabel('telephone');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 30]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 30]));

        /** @var GenericFormElement $element */
        /*
        $element = $fieldset->createElement('password', 'AdvancedPassword');
        $element->setLabel('password');
        $element->setProperty('confirmationLabel', 'confirm password');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 255]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['minimum' => 8, 'maximum' => 255]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));
        $element->setProperty(
            'passwordDescription',
            'At least 8 characters. Numbers, letters and special characters are recommended.'
        );*/

        /** @var Section $fieldset */
        $fieldset = $row->createElement('fieldsetCompany', 'Fieldset');
        $fieldset->setLabel('Company');
        $fieldset->setOptions([
            'properties' => [
                'gridColumnClassAutoConfiguration' => [
                    'viewPorts' => [
                        'xs' => [
                            'numbersOfColumnsToUse' => 12,
                        ],
                        'sm' => [
                            'numbersOfColumnsToUse' => 12,
                        ],
                        'md' => [
                            'numbersOfColumnsToUse' => 6,
                        ],
                    ],
                ],
            ],
        ]);

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('company', 'Text');
        $element->setLabel('company');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 100]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('street', 'Text');
        $element->setLabel('street');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 100]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('postalcode', 'Text');
        $element->setLabel('postalcode');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 8]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 8]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));

        /**
         * @var GenericFormElement $element
         */
        $element = $fieldset->createElement('city', 'Text');
        $element->setLabel('city');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => 100]);
        $element->addValidator($resolver->createValidator(StringLengthValidator::class, ['maximum' => 100]));
        $element->addValidator($resolver->createValidator(NotEmptyValidator::class));


        return $formDefinition;
    }
}
