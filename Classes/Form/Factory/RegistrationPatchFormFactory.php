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

        $hasPasswordField = $this->hasPasswordField($configuration);

        $newConfiguration = $configuration;
        $newConfiguration['renderingOptions']['hasPasswordField'] = $hasPasswordField;

        $preConfirmation = false;
        foreach ($configuration['finishers'] ?? [] as $finisherName => $page) {
            if ($finisherName === 'ConfirmationRequest') {
                $preConfirmation = true;
            }
        }

        foreach ($configuration['renderables'] ?? [] as $pageKey => $page) {
            if ($page['type'] === 'EmailConfirmation') {
                if ($preConfirmation) {
                    $newConfiguration['renderables'] = array_slice($newConfiguration['renderables'], 0 , $pageKey);
                } else {
                    $newConfiguration['renderables'] = array_slice($newConfiguration['renderables'], $pageKey + 1);
                }
            }
        }
        if (count($newConfiguration['renderables']) === 0) {
            // add pseudo page
            $newConfiguration['renderables'][] = [
                'type' => 'Page',
                'identifier' => 'pseudoPage',
                'renderables' => [
                    [
                        'type' => 'Text',
                        'identifier' => 'pseudoText',
                        'properties' => [
                            'text' => 'No fields available'
                        ]
                    ]
                ]
            ];
        }

        //DebugUtility::debug($newConfiguration, 'Patched configuration');
        //exit();

        foreach ($newConfiguration['renderables'] ?? [] as $pageKey => $page) {
            foreach ($page['renderables'] ?? [] as $elementKey => $renderable) {

                if (($renderable['properties']['mapOnDatabaseColumn'] ?? '') === 'username') {
                    $newConfiguration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][] = [
                        'identifier' => 'ConfirmationRequest',
                        'options' => [
                            'pid' => self::$settings['confirmationRequestPid'] ?? '',
                            'uriBuilder' => self::$uriBuilder,
                            'request' => $request
                        ]
                    ];
                    $newConfiguration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][] = [
                        'identifier' => 'FeUser',
                        'options' => [
                            'pid' => self::$settings['feUserStoragePid'] ?? '',
                        ]
                    ];
                }

            }
        }

        //DebugUtility::debug($configuration['renderables'], 'Patched configuration');


        return parent::build($newConfiguration, $prototypeName, $request);
    }


    private function hasPasswordField(array $configuration) : bool
    {
        $renderables = $this->getRenderables($configuration['renderables']);
        foreach ($renderables as $renderable) {
            if ($renderable['type'] === 'Password' || $renderable['type'] === 'AdvancedPassword') {
                return true;
            }
        }
        return false;
    }

    private function getRenderables(array $configuration): array
    {
        $renderables = [];
        foreach ($configuration as $elements) {
            $renderables[] = $elements;
            if (isset($elements['renderables'])) {
                $renderables = array_merge($renderables, $this->getRenderables($elements['renderables']));
            }
        }
        return $renderables;
    }

}
