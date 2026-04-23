# TYPO3 Frontend Registration Extension (fe_registration)

Flexible und erweiterbare Lösung für die Frontend-Benutzerregistrierung in TYPO3 14, basierend auf dem TYPO3 Form Framework mit DSGVO-konformem Double-Opt-In-Verfahren.

## Features

### Form Framework Integration
- Registrierungsformulare visuell im Form Editor erstellen und verwalten
- Integrierte und spezialisierte Validatoren (Duplikatprüfung E-Mail/Benutzername)
- Formular-Splitting: Felder vor und nach der E-Mail-Bestätigung getrennt konfigurierbar
- Vorausfüllung von Formularfeldern über URL-Parameter

### Double Opt-In (DOI)
- DSGVO-konformer Bestätigungsprozess mit UUID-basierten Hashes
- Konfigurierbare Ablaufzeit für Bestätigungslinks (Standard: 7 Tage)
- Resend-Endpoint mit Time-Lock gegen Missbrauch
- Automatische Bereinigung abgelaufener Requests via Scheduler-Command

### E-Mail-System
- Bestätigungsmail mit individuell anpassbarem Fluid-Template
- Optionale Willkommensmail nach erfolgreicher Registrierung
- Admin-Benachrichtigungen bei abgeschlossenen Registrierungen
- Konfigurierbare Absender, Betreff und HTML/Plain-Text-Format

### Benutzerverwaltung
- Automatische Erstellung von `fe_users`-Einträgen
- Zuweisung zu konfigurierbaren Benutzergruppen
- Passwort-Management mit automatischem Hashing
- Optionale Deaktivierung bis Admin-Freigabe (`feUserMustConfirmed`)

### Erweiterbarkeit (PSR-14 Events)
- `AfterConfirmationRequestCreationEvent` -- nach Erstellung einer Bestätigungsanfrage
- `BeforeConfirmationEvent` / `AfterConfirmationEvent` -- vor/nach E-Mail-Bestätigung
- `AfterRegistrationCompletionEvent` -- nach Abschluss der Registrierung (z.B. Sync mit Drittsystemen)
- `FeUserDatabaseDataEvent` -- vor dem Speichern der Benutzerdaten (Daten modifizieren)
- `SetPredefinedRegistrationFormValuesEvent` -- Formularwerte vorbelegen

## Registrierungsablauf

```
1. Benutzer füllt Registrierungsformular aus
2. ConfirmationRequest wird gespeichert, Bestätigungsmail versendet
3. Benutzer klickt Bestätigungslink in E-Mail
4. Optionales Vervollständigungsformular (Adresse, etc.)
5. fe_users-Eintrag wird erstellt, Admin wird benachrichtigt
6. Erfolgsseite
```

## Installation

```bash
composer require wapplersystems/fe-registration
```

## Konfiguration

1. TypoScript der Extension in das Template einbinden
2. Inhaltselement "Frontend User Registration" auf einer Seite platzieren
3. Im FlexForm konfigurieren:
   - **Formular** auswählen (Form Framework Persistenz-Identifier)
   - **Identifier-Feld** festlegen (z.B. `email`)
   - **E-Mail-Feld** festlegen
   - **Storage-PIDs** für Bestätigungsanfragen und fe_users setzen
   - **Benutzergruppen** zuweisen
   - **Absender** für E-Mails konfigurieren

### Registrierungsformular erstellen

Das Formular wird im TYPO3 Form Editor erstellt. Es muss ein `EmailConfirmation`-Element enthalten, das den Formular-Split definiert:

- Felder **vor** dem `EmailConfirmation`-Element werden im ersten Schritt abgefragt
- Felder **danach** erscheinen erst nach der E-Mail-Bestätigung

Formularfelder werden automatisch auf gleichnamige `fe_users`-Spalten gemappt (camelCase wird in snake_case konvertiert).

### Cleanup abgelaufener Requests

Abgelaufene Bestätigungsanfragen können per Scheduler-Command bereinigt werden:

```bash
# Entfernt abgelaufene und unbestätigte Requests (Fallback: älter als 30 Tage)
vendor/bin/typo3 feregistration:cleanup

# Mit benutzerdefiniertem Fallback-Zeitraum
vendor/bin/typo3 feregistration:cleanup --days=14
```

## Anforderungen

- TYPO3 14.0+
- TYPO3 Form Framework (`typo3/cms-form`)
- Extension `wapplersystems/form_extended`

## Lizenz

GPL-2.0+

## Autor

Sven Wappler -- [WapplerSystems](https://wappler.systems)