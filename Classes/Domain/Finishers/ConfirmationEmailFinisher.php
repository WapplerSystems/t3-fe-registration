<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;

class ConfirmationEmailFinisher extends EmailFinisher
{

    /**
     * Template used when the submitted address already has an account or a pending
     * request. Overridable via the `accountExistsTemplateName` option.
     */
    private const DEFAULT_ACCOUNT_EXISTS_TEMPLATE = 'AccountExists';

    /**
     * @var array
     */
    protected $defaultOptions = [
        'loginPageIdSiteSettingKey' => 'feRegistration.loginPageId',
    ];

    protected function executeInternal(): void
    {
        // ConfirmationRequestFinisher sets this when it recognised the address and
        // deliberately created nothing. Sending the normal double-opt-in mail would be
        // wrong (there is no hash to confirm) and telling the visitor on screen would
        // reintroduce the enumeration leak we just closed. So: same view, same
        // redirect, one mail either way — only its content differs.
        if ($this->isAccountExistsCase()) {
            $this->options['templateName'] = $this->resolveAccountExistsTemplateName();

            // Overwriting $this->options['subject'] would achieve nothing: parseOption()
            // runs every string option through the form translation chain, and a site
            // package that defines <form>.finisher.<id>.subject in its XLF wins over the
            // options array. The per-finisher translation overrides are consulted before
            // that chain, so that is where a runtime subject has to go.
            $accountExistsSubject = (string)($this->options['subject_accountExists'] ?? '');
            if ($accountExistsSubject !== '') {
                $languageCode = $this->finisherContext->getFormRuntime()
                    ->getCurrentSiteLanguage()?->getLocale()->getLanguageCode() ?? '';
                $this->options['translation']['overrides'][$languageCode]['subject'] = $accountExistsSubject;
                $this->options['subject'] = $accountExistsSubject;
            }

            // No confirmationHash exists in this branch — assigning it would render an
            // empty confirmation URL into the mail.
            unset($this->options['variables']['confirmationHash']);

            // Optional login link. Resolved here rather than in the controller because
            // the site is reliably available on the finisher's request — the same source
            // DeleteAccountFinisher uses for its redirect target.
            $loginPageUid = $this->resolveSiteSettingPageId((string)$this->parseOption('loginPageIdSiteSettingKey'));
            if ($loginPageUid > 0) {
                $this->options['variables']['loginPageUid'] = $loginPageUid;
            }

            // A failure here must not change what the visitor sees. An uncaught
            // FinisherException would abort the remaining finishers — including the
            // controller's RedirectToUri — so the form would re-render instead of
            // redirecting, and that difference is exactly the enumeration signal this
            // whole branch exists to remove. Log it and carry on.
            try {
                parent::executeInternal();
            } catch (\Throwable $e) {
                $this->logger?->error('Could not send the "account exists" email', [
                    'exception' => $e->getMessage(),
                    'templateName' => $this->options['templateName'],
                ]);
            }
            return;
        }

        if (is_array($this->options['variables'] ?? null)) {
            $this->options['variables']['confirmationHash'] = $this->finisherContext->getFormRuntime()->getFormState()->getFormValue('confirmationHash');
        } else {
            $this->options['variables'] = ['confirmationHash' => $this->finisherContext->getFormRuntime()->getFormState()->getFormValue('confirmationHash')];
        }

        parent::executeInternal();
    }

    /**
     * Where the "account exists" template lives, mirroring however the confirmation
     * template is addressed.
     *
     * The two callers disagree on the convention: RegistrationController passes
     * `Email/Confirmation`, while the form YAML passes `Confirmation` plus
     * `templateRootPaths` that already point into `Email/`. Swapping only the last
     * segment keeps both working — a fixed name resolves under one and throws under the
     * other.
     */
    private function resolveAccountExistsTemplateName(): string
    {
        $explicit = (string)($this->options['accountExistsTemplateName'] ?? '');
        if ($explicit !== '') {
            return $explicit;
        }

        $configured = (string)($this->options['templateName'] ?? '');
        $separator = strrpos($configured, '/');

        return $separator === false
            ? self::DEFAULT_ACCOUNT_EXISTS_TEMPLATE
            : substr($configured, 0, $separator + 1) . self::DEFAULT_ACCOUNT_EXISTS_TEMPLATE;
    }

    /**
     * Reads a page id from the site settings, 0 when unset or unavailable.
     */
    private function resolveSiteSettingPageId(string $settingKey): int
    {
        if ($settingKey === '') {
            return 0;
        }

        $site = $this->finisherContext->getRequest()->getAttribute('site');

        return $site instanceof Site ? (int)$site->getSettings()->get($settingKey, 0) : 0;
    }

    private function isAccountExistsCase(): bool
    {
        $renderingOptions = $this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions();

        return (bool)($renderingOptions[ConfirmationRequestFinisher::RENDERING_OPTION_ACCOUNT_EXISTS] ?? false);
    }

}
