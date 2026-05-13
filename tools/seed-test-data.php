<?php
/**
 * Seed test data: event, 50 Danish participants, ~100 agreements.
 *
 * Event window: 2026-05-14 00:00:00 → 2026-05-14 01:30:00 (90 minutes)
 * Average ~4 agreements per participant (range 1–10).
 *
 * Usage (from plugin root or WordPress root):
 *   php tools/seed-test-data.php
 */

require 'D:/Projects/WordpressLocalInstall/wp-load.php';

global $wpdb;

$event_start = '2026-05-14 00:00:00';
$event_end   = '2026-05-14 01:30:00';
$start_ts    = strtotime( $event_start );
$end_ts      = strtotime( $event_end );
$duration    = $end_ts - $start_ts; // 5400 seconds

// ── 1. Participant type ────────────────────────────────────────────────────────
$type_id = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}vep_participant_types LIMIT 1" );
if ( ! $type_id ) {
    $wpdb->insert(
        $wpdb->prefix . 'vep_participant_types',
        array(
            'name'        => 'Organisation',
            'description' => 'Frivillig organisation',
            'icon'        => '',
            'color'       => '#3b82f6',
        ),
        array( '%s', '%s', '%s', '%s' )
    );
    $type_id = $wpdb->insert_id;
    echo "Created participant type ID {$type_id}\n";
} else {
    echo "Using existing participant type ID {$type_id}\n";
}

// ── 2. Event ───────────────────────────────────────────────────────────────────
$wpdb->insert(
    $wpdb->prefix . 'vep_events',
    array(
        'name'        => 'Frivilligmesse 2026 – Testdata',
        'description' => 'Automatisk genereret testbegivenhed med 50 deltagere og ~100 aftaler.',
        'start_date'  => $event_start,
        'end_date'    => $event_end,
        'is_active'   => 1,
    ),
    array( '%s', '%s', '%s', '%s', '%d' )
);
$event_id = $wpdb->insert_id;

if ( ! $event_id ) {
    die( "FEJL: Kunne ikke oprette begivenhed.\n" );
}
echo "Oprettet begivenhed ID {$event_id}\n";

// ── 3. Organisationsnavne og kontaktpersoner ───────────────────────────────────
$organisations = array(
    'Røde Kors Lokalforening',
    'Mødrehjælpen',
    'Kirkens Korshær',
    'Blå Kors Danmark',
    'SOS Børnebyerne',
    'Plan International Danmark',
    'CARE Danmark',
    'WWF Verdensnaturfonden',
    'Greenpeace Danmark',
    'Amnesty International DK',
    'Dansk Flygtningehjælp',
    'Red Barnet',
    'Børns Vilkår',
    'Danmarks Naturfredningsforening',
    'FN-forbundet Danmark',
    'Folkekirkens Nødhjælp',
    'Dansk Handicap Forbund',
    'Ældre Sagen',
    'Kræftens Bekæmpelse',
    'Hjerteforeningen',
    'Diabetesforeningen',
    'Scleroseforeningen',
    'Gigtforeningen',
    'Astma-Allergi Danmark',
    'LEV Landsforeningen',
    'SIND Landsforeningen',
    'Autismeforeningen',
    'Bedre Psykiatri',
    'ADHD-foreningen',
    'Headspace Danmark',
    'Ventilen',
    'Café Klare',
    'Fontænehuset Odense',
    'Danner',
    'Kvinden i Centrum',
    'BOF Fonden',
    'Settlementet',
    'Kofoeds Skole',
    'Frivilligcenter Aarhus',
    'Frivilligcenter København',
    'Frivilligcenter Odense',
    'DUF – Dansk Ungdoms Fællesråd',
    'Frivilligt Forum',
    'Center for Frivilligt Arbejde',
    'Håndværk uden Grænser',
    'Engineers Without Borders DK',
    'Foreningen Nydansker',
    'Integrationshuset',
    'Mellemfolkeligt Samvirke',
    'GlobalDanmark',
);

$first_names = array(
    'Anders', 'Birthe', 'Carl', 'Dorte', 'Erik', 'Freja', 'Gunnar', 'Hanne',
    'Ib', 'Jette', 'Klaus', 'Lene', 'Morten', 'Nina', 'Ole', 'Pernille',
    'Rasmus', 'Søren', 'Tina', 'Ulla', 'Viggo', 'Winnie', 'Anne', 'Bo',
    'Charlotte', 'Dennis', 'Eva', 'Frederik', 'Gitte', 'Hans', 'Ida', 'Johan',
    'Karen', 'Lars', 'Marie', 'Niels', 'Pia', 'Torben', 'Susanne', 'Flemming',
);

$last_names = array(
    'Nielsen', 'Jensen', 'Hansen', 'Pedersen', 'Andersen', 'Christensen',
    'Larsen', 'Sørensen', 'Rasmussen', 'Jørgensen', 'Petersen', 'Madsen',
    'Kristensen', 'Olsen', 'Thomsen', 'Christiansen', 'Poulsen', 'Johansen',
    'Berg', 'Holm', 'Kjær', 'Møller', 'Dahl', 'Lund', 'Henriksen',
);

// ── 4. Indsæt 50 deltagere ────────────────────────────────────────────────────
$participant_ids = array();

foreach ( $organisations as $i => $org ) {
    $fname = $first_names[ array_rand( $first_names ) ];
    $lname = $last_names[ array_rand( $last_names ) ];

    // Lav en simpel e-mail-slug ud fra organisationsnavnet
    $slug  = strtolower( $org );
    $slug  = str_replace( array( 'æ', 'ø', 'å', 'Æ', 'Ø', 'Å' ), array( 'ae', 'oe', 'aa', 'ae', 'oe', 'aa' ), $slug );
    $slug  = preg_replace( '/[^a-z0-9]+/', '', $slug );
    $email = $slug . '@eksempel.dk';

    $wpdb->insert(
        $wpdb->prefix . 'vep_participants',
        array(
            'event_id'                    => $event_id,
            'participant_number'          => $i + 1,
            'organization_name'           => $org,
            'description'                 => '',
            'participant_type_id'         => $type_id,
            'contact_person_name'         => "{$fname} {$lname}",
            'contact_email'               => $email,
            'contact_phone'               => '+45 ' . rand( 20000000, 99999999 ),
            'is_approved'                 => 1,
            'expected_participants_count' => rand( 2, 8 ),
        ),
        array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d' )
    );

    $participant_ids[] = $wpdb->insert_id;
}

echo 'Oprettet ' . count( $participant_ids ) . " deltagere\n";

// ── 5. Tildel målantal aftaler (gns. ~4, spredning 1–10) ─────────────────────
// Bland deltagerne så fordelingen er tilfældig (ikke knyttet til rækkefølgen)
shuffle( $participant_ids );
$targets = array();

foreach ( $participant_ids as $idx => $pid ) {
    if ( $idx < 4 ) {
        // 4 super-aktive: 9–10 aftaler
        $targets[ $pid ] = rand( 9, 10 );
    } elseif ( $idx < 12 ) {
        // 8 aktive: 6–8 aftaler
        $targets[ $pid ] = rand( 6, 8 );
    } elseif ( $idx < 30 ) {
        // 18 middel: 3–5 aftaler
        $targets[ $pid ] = rand( 3, 5 );
    } elseif ( $idx < 45 ) {
        // 15 lav-middel: 2–3 aftaler
        $targets[ $pid ] = rand( 2, 3 );
    } else {
        // 5 minimale: 1 aftale
        $targets[ $pid ] = 1;
    }
}

// ── 6. Generer aftalepar (grådig: par altid de to med flest resterende) ───────
$remaining = $targets;
$agreements = array();
$paired_set = array(); // Forhindrer duplikerede par

while ( true ) {
    arsort( $remaining );
    $top = array_keys( $remaining );

    if ( count( $top ) < 2 )       break;
    if ( $remaining[ $top[0] ] <= 0 ) break;

    $a = $top[0];
    $b = null;

    foreach ( array_slice( $top, 1 ) as $candidate ) {
        if ( $remaining[ $candidate ] <= 0 ) break;
        if ( $candidate === $a )             continue;

        // Undgå dubletter – prøv et andet par hvis dette allerede eksisterer
        $key = min( $a, $candidate ) . '_' . max( $a, $candidate );
        if ( isset( $paired_set[ $key ] ) )  continue;

        $b = $candidate;
        break;
    }

    if ( $b === null ) {
        // Ingen tilgængelig partner uden duplikat – stop denne deltager
        $remaining[ $top[0] ] = 0;
        continue;
    }

    $key = min( $a, $b ) . '_' . max( $a, $b );
    $paired_set[ $key ] = true;
    $agreements[]        = array( $a, $b );
    $remaining[ $a ]--;
    $remaining[ $b ]--;
}

$total_agreements = count( $agreements );
echo "Genereret {$total_agreements} aftaler\n";

// ── 7. Indsæt aftaler med realistiske tidsstempler ───────────────────────────
// Fordelingen topper omkring minut 35-45 (triangel-distribution, peak = 0.42)
$peak = 0.42;

foreach ( $agreements as $pair ) {
    $u = mt_rand() / mt_getrandmax();
    $v = mt_rand() / mt_getrandmax();

    // Triangel-fordeling
    if ( $u < $peak ) {
        $t = $peak * sqrt( $u / $peak );
    } else {
        $t = 1.0 - ( 1.0 - $peak ) * sqrt( ( 1.0 - $u ) / ( 1.0 - $peak ) );
    }

    // Lille jitter (±3 %)
    $t = max( 0.005, min( 0.995, $t + ( $v - 0.5 ) * 0.06 ) );

    $offset    = (int) round( $t * $duration );
    $created   = date( 'Y-m-d H:i:s', $start_ts + $offset );
    $initiator = ( mt_rand( 0, 1 ) === 0 ) ? $pair[0] : $pair[1];

    $wpdb->insert(
        $wpdb->prefix . 'vep_agreements',
        array(
            'event_id'        => $event_id,
            'participant1_id' => $pair[0],
            'participant2_id' => $pair[1],
            'initiator_id'    => $initiator,
            'description'     => '',
            'status'          => 'active',
            'created_at'      => $created,
            'updated_at'      => $created,
        ),
        array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );
}

echo "Indsat {$total_agreements} aftaler i databasen\n";

// ── 8. Opsummering ────────────────────────────────────────────────────────────
$actual_count = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}vep_agreements WHERE event_id = %d",
        $event_id
    )
);

$avg = round( ( $actual_count * 2 ) / count( $participant_ids ), 2 );

echo "\n=== Færdig ===\n";
echo "Begivenhed ID : {$event_id}\n";
echo "Deltagere     : " . count( $participant_ids ) . "\n";
echo "Aftaler       : {$actual_count}\n";
echo "Gns. pr. deltager : {$avg}\n";
