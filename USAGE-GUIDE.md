# Volunteer Exchange Platform - Hurtig Start Guide

## Kom i Gang på 5 Minutter

### Trin 1: Aktiver Pluginet
1. Gå til WordPress Admin → Plugins
2. Find "Volunteer Exchange Platform"
3. Klik "Activate"
4. Database tabeller oprettes automatisk

### Trin 2: Opret Grundlæggende Data

#### A. Opret Deltagertyper
1. Gå til: **Volunteer Exchange → Participant Types**
2. Klik "Add New"
3. Tilføj typer som:
   - Virksomhed
   - Forening
   - Organisation
   - Offentlig Institution
   - etc.

#### B. Opret "Vi Tilbyder" Tags
1. Gå til: **Volunteer Exchange → We Offer Tags**
2. Klik "Add New"
3. Tilføj tags som:
   - Lokaler
   - Materiel
   - Frivillige
   - Sponsorering
   - Kompetencer
   - Transport
   - etc.

#### C. Opret Dit Første Event
1. Gå til: **Volunteer Exchange → Events**
2. Klik "Add New"
3. Udfyld:
   - Event navn: "Frivillighedsmesse 2026"
   - Beskrivelse
   - Start dato og tid
   - Slut dato og tid
4. Klik "Create Event"
5. **Bemærk**: Dette event bliver automatisk aktivt

### Trin 3: Opret Sider med Shortcodes

#### Tilmeldingsside
1. Opret ny side: **Pages → Add New**
2. Titel: "Tilmeld Dig"
3. Indsæt shortcode (vælg én):
   - Simpel formular (standard): `[vep_registration]`
   - Multistep formular (4 trin): `[vep_registration form_type="multiform"]`
4. Publicer

#### Oversigtsside
1. Opret ny side: **Pages → Add New**
2. Titel: "Deltagere"
3. Indsæt shortcode (vælg én):
   - Kort/grid visning med filtre: `[vep_participants_grid]`
   - Enkel tabelvisning: `[vep_participants_table]`
   - Enkel tabel + "Se detaljer" knap: `[vep_participants_table show_button="yes"]`
4. Publicer

#### Aftaleside
1. Opret ny side: **Pages → Add New**
2. Titel: "Opret Aftale"
3. Indsæt shortcode: `[vep_agreement_form]`
4. Publicer

### Trin 4: Test Tilmelding

1. Gå til din "Tilmeld Dig" side (frontend)
2. Udfyld formularen
3. Upload et logo
4. Vælg "Vi tilbyder" tags
5. Klik "Register"
6. Se bekræftelsesbesked

### Trin 5: Godkend Tilmeldinger

1. Gå til: **Volunteer Exchange → Participants**
2. Find deltagere med ⏳ (pending) status
3. Klik "Approve" under deltagerens navn
4. Deltageren vises nu på frontend grid

### Trin 6: Opret Aftaler

1. Gå til din "Opret Aftale" side (frontend)
2. Vælg to forskellige deltagere
3. Vælg hvem der er initiativtager
4. Beskriv aftalen
5. Klik "Create Agreement"

### Trin 7: Se Aftaler

#### På Frontend (liste)
1. Opret ny side: **Pages → Add New**
2. Titel: "Aftaler"
3. Indsæt shortcode: `[vep_agreements_list]`
4. Publicer

#### Under Events:
1. Gå til: **Volunteer Exchange → Events**
2. Klik "View" på dit event
3. Se alle aftaler under eventet

#### Under Deltagere:
1. Gå til: **Volunteer Exchange → Participants**
2. Klik "View" på en deltager
3. Se deltakerens aftaler

## Almindelige Arbejdsgange

### Ny Event Sæson
1. **Events** → "Add New"
2. Opret nyt event
3. Det bliver automatisk aktivt
4. Tidligere event bliver inaktivt
5. Tilmeldinger går nu til det nye event

### Håndter Tilmeldinger
1. **Participants** → Se liste
2. Pending (⏳) = afventer godkendelse
3. Approved (✓) = godkendt og synlig
4. Klik "Approve" for at godkende
5. Klik "Edit" for at redigere
6. Klik "Delete" for at slette

### Rediger Deltager Data
1. **Participants** → Klik "Edit"
2. Rediger information
3. Upload nyt logo (valgfrit)
4. Vælg/fravælg tags
5. Ændre godkendelsesstatus
6. Klik "Update Participant"

### Se Statistik og Aktivitet
1. **Events** → Klik "View" på event
   - Se antal aftaler
   - Se hvilke deltagere har aftaler
2. **Participants** → Klik "View" på deltager
   - Se deltakerens aftaler
   - Se samarbejdspartnere

## Shortcode Parametre

### Basic Brug
```
[vep_registration]
[vep_registration form_type="multiform"]
[vep_participants_grid]
[vep_participants_table]
[vep_participants_table show_button="yes"]
[vep_agreement_form]
[vep_agreements_list]
```

### Hvad de enkelte shortcodes gør

- `[vep_registration]`
   - Viser standard tilmeldingsformular.

- `[vep_registration form_type="multiform"]`
   - Viser multistep tilmeldingsformular.

- `[vep_participants_grid]`
   - Viser godkendte deltagere i grid.
   - Understøtter frontend-filtre for deltagertype og tags.

- `[vep_participants_table]`
   - Viser godkendte deltagere i en simpel tabel.
   - Kolonner: deltagernummer + deltagernavn.
   - Sorteres efter deltagernummer.

- `[vep_participants_table show_button="yes"]`
   - Som ovenfor, men med ekstra knap der linker til deltagerens frontend-side.

- `[vep_agreement_form]`
   - Formular til oprettelse af børsaftaler mellem to deltagere.

- `[vep_agreements_list]`
   - Viser aftaler for aktivt event i tabelform.
   - Indeholder tekstsøgning på tværs af alle kolonner.

Opdatering af deltager sker via unik URL med nøgle:
- `.../vep/updateparticipant/{random_key}` (EN)
- `.../vep/opdaterdeltager/{random_key}` (DA)

### Registrering: simpel vs. multistep
- `form_type` (valgfri)
   - `simple` (standard): én samlet formular
   - `multiform` / `multistep` / `multi`: 4-trins formular med Næste/Tilbage

Eksempler:
```
// Simpel (standard)
[vep_registration]

// Multistep
[vep_registration form_type="multiform"]
```

### Deltagertabel: vis detaljer-knap
- `show_button` (valgfri)
   - `no` (standard): kun deltagernummer + navn
   - `yes`: tilføjer knap med link til frontend deltager-side

Eksempler:
```
// Uden knap
[vep_participants_table]

// Med "Se detaljer" knap
[vep_participants_table show_button="yes"]
```

Alle shortcodes henter automatisk det aktive event.

## Tips & Tricks

### Oversættelser (da_DK)
Pluginet bruger WordPress' standard oversættelser via **Text Domain**: `volunteer-exchange-platform`.

Filerne ligger i `languages/`:
- `volunteer-exchange-platform-da_DK.po` (redigér denne)
- `volunteer-exchange-platform-da_DK.mo` (WordPress bruger denne)
- `volunteer-exchange-platform.pot` (skabelon med alle strenge)

Hvis du ændrer i `.po` filen, skal du regenerere `.mo` filen:
```
php tools/compile-mo.php languages/volunteer-exchange-platform-da_DK.po languages/volunteer-exchange-platform-da_DK.mo
```

### Design Tilpasning
CSS filer findes i: `assets/css/`
- `public.css` - Styling af frontend elementer
- `admin.css` - Styling af admin sider

### Logo Krav
- Format: JPG, PNG eller GIF
- Maks størrelse: 2MB
- Anbefalet: 300x300px eller højere

### Kun Ét Aktivt Event
- Systemet understøtter kun ét aktivt event ad gangen
- Når du opretter nyt event, bliver det automatisk aktivt
- Tidligere event bliver inaktivt
- Du kan manuelt deaktivere et event

### Godkendelsesflow
1. Bruger tilmelder sig via frontend
2. Status: "Pending Approval"
3. Ikke synlig i grid view
4. Admin godkender i backend
5. Status: "Approved"
6. Nu synlig i grid view og kan oprette aftaler

## Fejlfinding

### Tilmelding virker ikke
- Tjek at der er et aktivt event
- Tjek at der findes deltagertyper
- Tjek at der findes tags
- Tjek browser console for fejl

### Deltagere vises ikke i grid
- Tjek at deltagere er godkendt (Approved)
- Tjek at de tilhører det aktive event
- Refresh siden

### Choices.js virker ikke
- Tjek at internet forbindelse er aktiv (CDN)
- Tjek browser console for fejl
- Tjek at shortcode `[vep_agreement_form]` er korrekt

### Bootstrap modal virker ikke
- Tjek at internet forbindelse er aktiv (CDN)
- Tjek browser console for fejl
- Tjek at shortcode `[vep_participants_grid]` er korrekt

## Support & Udvikling

### Database Præfix
Alle tabeller bruger WordPress database præfix: `{$wpdb->prefix}vep_*`

### Nonces
- Frontend: `vep_frontend_nonce`
- Admin: `vep_admin_nonce`
- Forms: Specifik nonce per form

### Kodekvalitet & Sikkerhed (drift)
Når der laves ændringer i pluginet, følg denne korte checkliste:

1. **Permissions**: Admin actions skal validere `current_user_can('manage_options')`.
2. **Nonce**: Forms, quick actions og AJAX skal verificere nonce.
3. **Input**: Request-data håndteres med `wp_unslash()` + sanitization (`sanitize_key`, `sanitize_text_field`, `sanitize_textarea_field`, `absint`, `sanitize_email`).
4. **Output**: Brug korrekt escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
5. **Direkte adgang**: Loadbare PHP-filer skal have `ABSPATH`-guard.
6. **Validering**: Kør `php -l` på ændrede filer før deploy.

### Hooks & Filters
Pluginet bruger standard WordPress hooks.
Custom hooks kan tilføjes i fremtidige versioner.

## Næste Skridt

1. Tilpas CSS til dit tema
2. Test alle funktioner
3. Træn brugere i godkendelsesflow
4. Opret backup rutiner
5. Overvej ekstra tags baseret på behov

God fornøjelse med Volunteer Exchange Platform! 🎉
