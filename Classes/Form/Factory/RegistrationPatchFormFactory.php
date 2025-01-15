<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Form\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;

#[Autoconfigure(public: true, shared: false)]
class RegistrationPatchFormFactory extends ArrayFormFactory
{

    protected static array $settings;

    protected static UriBuilder $uriBuilder;

    public function __construct(array $settings = null, ?UriBuilder $uriBuilder = null)
    {
        if ($settings !== null) {
            self::$settings = $settings;
        }
        if ($uriBuilder !== null) {
            self::$uriBuilder = $uriBuilder;
        }
    }

    public function build(
        array $configuration,
        ?string $prototypeName = null,
        ?ServerRequestInterface $request = null
    ): FormDefinition {

        foreach ($configuration['renderables'] as $pageKey => $page) {
            foreach ($page['renderables'] as $elementKey => $renderable) {
                $validators = $renderable['validators'] ?? [];
                foreach ($validators as $validatorKey => $validator) {
                    if ($validator['identifier'] === 'ConfirmationRequest') {
                        if (!isset($validator['options'])) {
                            $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options'] = [];
                        }
                        $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options']['pid'] = self::$settings['confirmationRequestPid'] ?? '';
                        $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options']['uriBuilder'] = self::$uriBuilder;
                        $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options']['request'] = $request;
                    }
                    if ($validator['identifier'] === 'FeUser') {
                        if (!isset($validator['options'])) {
                            $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options'] = [];
                        }
                        $configuration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][$validatorKey]['options']['pid'] = self::$settings['feUserStoragePid'] ?? '';
                    }
                }
            }
        }

        //DebugUtility::debug($configuration['renderables'], 'Patched configuration');


        return parent::build($configuration, $prototypeName, $request);
    }

}
