# EXT:fe_registration

Frontend-Registrierung mit Double-Opt-In für TYPO3 v14. Die Extension stellt
ein `feregistration_registration`-Content-Element bereit, das ein per
**EXT:form** definiertes Registrierungsformular rendert und nach
E-Mail-Bestätigung einen `fe_users`-Datensatz anlegt.

## Schnellinstallation

```bash
composer require wapplersystems/fe-registration
ddev exec vendor/bin/typo3 cache:flush
ddev exec vendor/bin/typo3 database:updateschema
```

Anschließend Site-Set `wapplersystems/fe-registration` in
`config/sites/<site>/config.yaml` als Dependency eintragen, damit alle
Finisher- und Validator-Defaults geladen werden:

```yaml
dependencies:
  - wapplersystems/fe-registration
```

## Aufbau eines Registrierungs-Formulars

1. Eine Form-YAML unter `EXT:meinpaket/Resources/Private/Forms/` anlegen –
   z. B. basierend auf `EXT:fe_registration/Configuration/Yaml/Forms/Standard.form.yaml`.
2. Pflichtfelder: mindestens ein `Email`-Feld und ein `EmailConfirmation`-Feld.
   Das `EmailConfirmation`-Feld trennt Pre-Confirmation (Daten erfassen,
   Bestätigungs-Mail senden) von Post-Confirmation (Daten aus Confirmation-
   Request laden, `fe_users` anlegen).
3. Finisher-Block mit ConfirmationRequest + ConfirmationEmail, plus optional
   CompleteRegistration / FeUser / NewsletterSubscription – siehe Beispiel
   unten.

### Mindest-Finisher-Block

```yaml
finishers:
  -
    identifier: ConfirmationRequest
    options:
      preConfirmation: true
      confirmationRequestPid: 304          # sysfolder für tx_feregistration_domain_model_confirmationrequest
  -
    identifier: ConfirmationEmail
    options:
      preConfirmation: true
      subject: 'Bitte bestätigen Sie Ihre E-Mail-Adresse'
      recipients:
        '{email}': '{firstName} {lastName}'
      senderAddress: 'no-reply@example.com'
      senderName: 'Beispiel GmbH'
      templateName: Confirmation
      templateRootPaths:
        890: 'EXT:fe_registration/Resources/Private/Templates/Email/'
      format: html
  -
    identifier: CompleteRegistration
    options:
      pid: 77                                # sysfolder für fe_users
  -
    identifier: FeUser
    options:
      pid: 77
```

> **Wichtig:** Die `preConfirmation: true`-Markierung sorgt dafür, dass
> `ConfirmationRequest` und `ConfirmationEmail` nur beim ersten Submit
> ausgeführt werden. Beim Klick auf den Bestätigungslink (Post-Confirmation)
> entfernt `RegistrationPatchFormFactory` markierte Finisher automatisch,
> damit kein zweiter Confirmation-Request entsteht.

## Content-Element konfigurieren

Im Backend ein Content-Element vom Typ **"Frontend User Registration"**
anlegen. Im Reiter *Plugin Options*:

| Feld                         | Beschreibung                                                              |
|------------------------------|---------------------------------------------------------------------------|
| `settings.form`              | YAML-Form, die gerendert werden soll                                      |
| `settings.forward`           | Seite, auf die nach erfolgreichem Abschluss weitergeleitet wird           |
| `settings.identifierFieldName` | Form-Feld, dessen Wert `fe_users.username` wird (Dropdown, aus YAML)   |
| `settings.emailFieldName`    | Form-Feld, das die Empfänger-Mailadresse liefert (Dropdown, aus YAML)     |
| `settings.confirmationRequestPid` | Sysfolder für Confirmation-Requests                                  |
| `settings.feUserStoragePid`  | Sysfolder für neu angelegte `fe_users`                                    |
| `settings.usergroups`        | Welche `fe_groups` der neue User automatisch zugeordnet bekommt           |
| `settings.feUserMustConfirmed` | Wenn aktiv, bleibt der `fe_users`-Datensatz `disable=1` bis ein Admin freigibt |

`identifierFieldName` und `emailFieldName` werden im Backend als **Dropdown**
mit den verfügbaren Form-Feldern der unter `settings.form` gewählten YAML
befüllt. Nach Auswahl einer neuen Form muss der Datensatz einmal gespeichert
werden, damit die Dropdowns aktualisiert erscheinen (TYPO3-Standardverhalten:
*"Refresh required"*-Dialog).

## URL-Beautification per Route Enhancer

Damit der Bestätigungslink in der Form
`/registrierung/confirm/<hash>` statt mit GET-Parameter erscheint, im
Site-Konfig folgenden `routeEnhancer` eintragen (Page-ID auf die Seite mit
dem Registrierungs-CE anpassen):

```yaml
routeEnhancers:
  FeRegistration:
    type: Extbase
    limitToPages:
      - 330                              # Page-ID des Registrierungs-CEs
    extension: fe_registration
    plugin: Registration
    routes:
      -
        routePath: '/confirm/{hash}'
        _controller: 'Registration::confirm'
        _arguments:
          hash: hash
    defaultController: 'Registration::new'
    aspects:
      hash:
        type: PersistedFieldValueMapper
        tableName: tx_feregistration_domain_model_confirmationrequest
        routeFieldName: confirmation_hash
```

## Mail-Transport

Der Versand läuft über den TYPO3-Mailer (`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`).
Für lokale Tests bietet sich Mailpit (SMTP `localhost:1025`) an. In Produktion
typischerweise SMTP-Auth, Sendmail oder
`WapplerSystems\MicrosoftGraphMailer\Mailer\GraphTransport`.

## Aufräumen abgelaufener Confirmation-Requests

```bash
ddev exec vendor/bin/typo3 fe-registration:cleanup-expired-confirmation-requests
```

Im Scheduler einplanen, damit nicht bestätigte Anfragen nicht ewig liegen
bleiben.

## Debugging

* `EXT:claude_diagnostics` installieren — `ts:setup` / `ts:constants` /
  `database:query` helfen beim Inspect der Plugin-Settings.
* TYPO3-Log unter `var/log/typo3_*.log` zeigt Finisher-Exceptions
  (`Failed to execute finisher` mit Stacktrace).
* Mailpit (`https://<projekt>.ddev.site:8026`) zeigt alle ausgehenden Mails.