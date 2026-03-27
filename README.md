# Volunteer Exchange Platform

## License

This plugin is licensed under the GNU General Public License v2 or later.
See [LICENSE.txt](LICENSE.txt).

This plugin bundles the "Share Tech Mono" font under the SIL Open Font License 1.1.
See [assets/fonts/vep-timer/OFL.txt](assets/fonts/vep-timer/OFL.txt).

Et omfattende WordPress plugin til at administrere frivillig udveksling mellem aktører/organisationer med events, deltagere og aftaler.

## Kompatibilitet

- **Kræver mindst WordPress**: 5.0
- **Testet op til WordPress**: 6.8
- **Kræver PHP**: 7.0

## Funktioner

### Database Tabeller

Pluginet opretter følgende custom database tabeller:

- **vep_events** - Events der kan aktiveres/deaktiveres
- **vep_participants** - Tilmeldte aktører/organisationer
- **vep_participant_types** - Typer af aktører
- **vep_we_offer_tags** - Tags for hvad aktører tilbyder
- **vep_participant_tags** - Relation mellem deltagere og tags
- **vep_agreements** - Aftaler mellem deltagere

### Admin Sider

#### Events
- Se, opret, rediger og deaktiver events
- Kun ét event kan være aktivt ad gangen
- Nyeste oprettede event bliver automatisk aktivt
- Vis aftaler under hvert event

#### Deltagere (Participants)
- Se, opret, rediger og slet deltagere
- Godkend nye tilmeldinger
- Vis aftaler under hver deltager
- Upload logo via WordPress media library

#### Deltagertyper (Participant Types)
- CRUD funktionalitet for aktørtyper

#### Vi Tilbyder Tags (We Offer Tags)
- CRUD funktionalitet for tags
- Navn og beskrivelse felter

### Shortcodes

#### Tilmelding: `[vep_registration]`
Viser tilmeldingsformular med:
- Organisationsnavn
- Aktørtype (dropdown)
- Kontaktpersonens navn
- Email og telefon
- Logo upload (benytter WordPress API'er)
- "Hvad vi tilbyder" tags (checkboxes)
- AJAX submit med nonce sikkerhed
- Tilmeldte er ikke aktive før godkendt i admin

#### Grid Visning: `[vep_participants_grid]`
Viser godkendte deltagere i grid layout:
- Responsive grid
- Logo, navn, type og tags
- Bootstrap modal med detaljeret information
- Kun godkendte deltagere vises

#### Aftale Formular: `[vep_agreement_form]`
Opret aftaler mellem to deltagere:
- To dropdowns med Choices.js for søgning
- Radio felter til at vælge hvem der er initiativtager
- Beskrivelsefelt
- AJAX submit med nonce sikkerhed

## Installation

1. Upload plugin mappen til `/wp-content/plugins/`
2. (Udvikling) Kør `composer install` i plugin mappen for at generere `vendor/autoload.php`
3. Aktiver pluginet via 'Plugins' menuen i WordPress
4. Databasetabeller oprettes automatisk ved aktivering
5. Gå til "Volunteer Exchange" i admin menuen for at komme i gang

## Brug

### Opsætning

1. **Opret Deltagertyper**: Gå til Volunteer Exchange > Participant Types
2. **Opret Tags**: Gå til Volunteer Exchange > We Offer Tags
3. **Opret Event**: Gå til Volunteer Exchange > Events

### Tilføj Shortcodes til Sider

```
[vep_registration]
[vep_participants_grid]
[vep_agreement_form]
```

### Godkend Tilmeldinger

1. Gå til Volunteer Exchange > Participants
2. Find deltagere med "Pending Approval" status
3. Klik "Approve" for at godkende

## Teknisk Information

### Arkitektur
- **OOP struktur** med namespaces
- **PSR-4 autoloading**
- **WP_List_Table** til admin oversigter
- **AJAX handlers** med nonce verifikation
- **Vanilla JavaScript** (ingen jQuery)

### Sikkerhed
- Nonce verifikation på alle forms
- Capability checks (manage_options)
- Input sanitization
- Output escaping
- Prepared statements for database queries

### Kodestandarder og hardening (2026)
- Direkte adgangsbeskyttelse i loadbare PHP-filer via `ABSPATH`-guard.
- Admin actions og quick actions bruger nonce-beskyttede URLs + server-side nonce verifikation.
- Request data i admin/AJAX/list tables håndteres med `wp_unslash()` + korrekt sanitization (`sanitize_key`, `sanitize_text_field`, `sanitize_textarea_field`, `absint`, `sanitize_email`).
- Output i notices, links og attributter escapes konsekvent (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Public methods er dokumenteret med PHPDoc i pluginets kerneområder (Admin, Ajax, Services, Database, Plugin, Shortcodes, Frontend).

### Frontend
- Bootstrap 5 til modals
- Choices.js til søgbare dropdowns
- Responsive design
- AJAX submissions

## Filstruktur

```
volunteer-exchange-platform/
├── volunteer-exchange-platform.php
├── src/
│   ├── Plugin/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── Dependencies.php
│   ├── Admin/
│   ├── Ajax/
│   ├── Database/
│   ├── Services/
│   ├── Shortcodes/
│   └── Frontend/
└── assets/
    ├── css/
    │   ├── admin.css
    │   └── public.css
    └── js/
        ├── admin.js
        └── frontend.js
```

## Requirements

- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+

## Changelog

### 1.0.0
- Initial release
- Events management
- Participants management med approval system
- Participant Types management
- We Offer Tags management
- Registration shortcode med logo upload
- Grid view shortcode med Bootstrap modal
- Agreement form shortcode med Choices.js
- Komplet AJAX funktionalitet
- Responsive design

## Support

For support, kontakt plugin udvikleren.

## License

GPL v2 or later
