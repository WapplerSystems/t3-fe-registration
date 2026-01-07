# TYPO3 Frontend Registration Extension (fe_registration)

Diese Extension bietet eine flexible und erweiterbare Lösung für die Benutzerregistrierung im Frontend von TYPO3, basierend auf dem TYPO3 Form Framework.

## Vorteile und Merkmale

### 1. Integration in das TYPO3 Form Framework
* **Flexibilität:** Nutzen Sie alle Vorteile des TYPO3 Form Frameworks, um Registrierungsformulare visuell im Form Editor zu erstellen.
* **Bekannte Bedienung:** Administratoren und Redakteure können Formulare wie gewohnt verwalten, ohne neue Tools erlernen zu müssen.
* **Validierung:** Nutzen Sie integrierte Validatoren oder die spezialisierten Validatoren dieser Extension (z.B. `UserAlreadyExistsValidator`), um Datenqualität sicherzustellen.

### 2. Double Opt-In (DOI) Prozess
* **Rechtssicherheit:** Ein vollständiger Double Opt-In Prozess ist integriert, um DSGVO-Anforderungen zu erfüllen.
* **Sichere Bestätigung:** Erzeugt eindeutige Bestätigungs-Hashes und speichert diese sicher in der Datenbank.
* **Anpassbare E-Mails:** Bestätigungs-E-Mails können über Fluid-Templates vollständig individualisiert werden.

### 3. Erweiterbarkeit durch PSR-14 Events
Die Extension ist nach modernen Standards entwickelt und bietet zahlreiche Events, um den Registrierungsprozess an eigene Bedürfnisse anzupassen:
* `AfterConfirmationRequestCreationEvent`: Nach der Erstellung einer Bestätigungsanfrage.
* `AfterConfirmationEvent`: Nach erfolgreicher Bestätigung durch den Benutzer.
* `AfterRegistrationCompletionEvent`: Nach Abschluss des gesamten Registrierungsprozesses (z.B. für die Synchronisation mit Drittsystemen).
* `SetPredefinedRegistrationFormValuesEvent`: Zum Vorbelegen von Formularwerten.

### 4. Nahtlose Frontend-Integration
* **Controller-gesteuerter Workflow:** Ein spezialisierter `RegistrationController` verwaltet den Ablauf von der Anmeldung bis zur erfolgreichen Bestätigung.
* **Passwort-Management:** Unterstützung für Passwort-Felder inklusive automatischer Generierung, falls kein Passwort-Feld im Formular vorhanden ist.
* **Benutzergruppen-Zuordnung:** Automatische Zuweisung von FE-Benutzern zu vordefinierten Benutzergruppen nach erfolgreicher Registrierung.

### 5. Entwicklerfreundlich
* **Moderne Architektur:** Nutzt Dependency Injection und ist für TYPO3 v13 optimiert.
* **Einfache Konfiguration:** Die Konfiguration erfolgt bequem über YAML (Form Framework) und TypoScript/FlexForms.
* **Spezialisierte Finisher:** Enthält maßgeschneiderte Finisher wie `ConfirmationRequestFinisher` und `CompleteRegistrationFinisher`.

## Installation

1. Installation via Composer:
   ```bash
   composer require wapplersystems/fe-registration
   ```
2. Includieren Sie das TypoScript der Extension in Ihr Template.
3. Konfigurieren Sie das Registrierungsformular im TYPO3-Backend.
