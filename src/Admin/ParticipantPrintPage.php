<?php
/**
 * Participant print page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the browser-print preview page for one or more selected participants.
 *
 * Listens for admin_post_vep_print_participant, verifies the request,
 * fetches participant data and outputs a standalone HTML print page
 * that calls window.print() on load.
 *
 * @package VolunteerExchangePlatform\Admin
 */
class ParticipantPrintPage {

    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Allowed field keys.
     *
     * @var string[]
     */
    private static $allowed_fields = array(
        'logo',
        'participant_number',
        'organization_name',
        'description',
        'type',
        'expected_count',
        'expected_names',
        'contact_person',
        'email',
        'phone',
        'we_offer',
        'status',
        'missing_fields',
    );

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?ParticipantService $participant_service = null ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_action( 'admin_post_vep_print_participant', array( $this, 'render_print' ) );
    }

    /**
     * Handle the print request.
     *
     * Verifies nonce and capability, fetches participant data and
     * outputs a standalone HTML print page.
     *
     * @return void
     */
    public function render_print() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'volunteer-exchange-platform' ) );
        }

        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_print_participant' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'volunteer-exchange-platform' ) );
        }

        $participant_ids_raw = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '';
        $participant_ids = array();
        if ( $participant_ids_raw ) {
            foreach ( explode( ',', $participant_ids_raw ) as $id_raw ) {
                $participant_id = absint( $id_raw );
                if ( $participant_id > 0 ) {
                    $participant_ids[] = $participant_id;
                }
            }
        }

        $single_participant_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( empty( $participant_ids ) && $single_participant_id ) {
            $participant_ids[] = $single_participant_id;
        }

        if ( empty( $participant_ids ) ) {
            wp_die( esc_html__( 'Invalid participant selection.', 'volunteer-exchange-platform' ) );
        }

            // Parse and whitelist field keys.
        $fields_raw = isset( $_GET['fields'] ) ? sanitize_text_field( wp_unslash( $_GET['fields'] ) ) : '';
        $fields     = array();
        if ( $fields_raw ) {
            foreach ( explode( ',', $fields_raw ) as $f ) {
                $f = sanitize_key( $f );
                if ( in_array( $f, self::$allowed_fields, true ) ) {
                    $fields[] = $f;
                }
            }
        }

        $layout_raw  = isset( $_GET['layout'] ) ? sanitize_key( wp_unslash( $_GET['layout'] ) ) : 'liste';
        $layout      = in_array( $layout_raw, array( 'grid', 'box', 'liste' ), true ) ? $layout_raw : 'liste';

        $orient_raw  = isset( $_GET['orientation'] ) ? sanitize_key( wp_unslash( $_GET['orientation'] ) ) : 'portrait';
        $orientation = in_array( $orient_raw, array( 'portrait', 'landscape' ), true ) ? $orient_raw : 'portrait';

        $paper_raw = isset( $_GET['paper'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['paper'] ) ) ) : 'A4';
        $paper     = in_array( $paper_raw, array( 'A3', 'A4', 'A5' ), true ) ? $paper_raw : 'A4';

        $grid_cols = isset( $_GET['grid_cols'] ) ? absint( $_GET['grid_cols'] ) : 3;
        if ( $grid_cols < 1 || $grid_cols > 6 ) {
            $grid_cols = 3;
        }

        $show_checkbox = isset( $_GET['show_checkbox'] ) && '1' === (string) $_GET['show_checkbox'];
        $allow_cut     = isset( $_GET['allow_cut'] ) && '1' === (string) $_GET['allow_cut'];

        $participants = $this->participant_service->get_by_ids_with_details( $participant_ids );
        if ( empty( $participants ) ) {
            wp_die( esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) );
        }

        $entries = array();
        foreach ( $participants as $participant ) {
            $participant_tags = $this->participant_service->get_tags_for_participant( (int) $participant->id );
            $entries[] = array(
                'participant' => $participant,
                'tags'        => $participant_tags,
                'missing'     => $this->participant_service->get_update_reminder_missing_fields( $participant ),
            );
        }

        $this->output_print_page( $entries, $fields, $layout, $orientation, $paper, $grid_cols, $show_checkbox, $allow_cut );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get display label for a field key.
     *
     * @param string $field_key Field key.
     * @return string
     */
    private function get_field_label( $field_key ) {
        $labels = array(
            'logo'               => __( 'Logo', 'volunteer-exchange-platform' ),
            'participant_number' => __( 'Deltagernummer', 'volunteer-exchange-platform' ),
            'organization_name'  => __( 'Organisationsnavn', 'volunteer-exchange-platform' ),
            'description'        => __( 'Beskrivelse', 'volunteer-exchange-platform' ),
            'type'               => __( 'Type', 'volunteer-exchange-platform' ),
            'expected_count'     => __( 'Forventede antal deltagere', 'volunteer-exchange-platform' ),
            'expected_names'     => __( 'Navne på deltager(e)', 'volunteer-exchange-platform' ),
            'contact_person'     => __( 'Kontaktperson', 'volunteer-exchange-platform' ),
            'email'              => __( 'E-mail', 'volunteer-exchange-platform' ),
            'phone'              => __( 'Telefon', 'volunteer-exchange-platform' ),
            'we_offer'           => __( 'Vi tilbyder', 'volunteer-exchange-platform' ),
            'status'             => __( 'Status', 'volunteer-exchange-platform' ),
            'missing_fields'     => __( 'Mangler at udfylde', 'volunteer-exchange-platform' ),
        );

        return isset( $labels[ $field_key ] ) ? $labels[ $field_key ] : $field_key;
    }

    /**
     * Get rendered HTML value for a field key.
     *
     * @param string $field_key  Field key.
     * @param object $participant Participant object.
     * @param array  $tags        Tag objects with ->name property.
     * @param array  $missing     Missing field descriptors.
     * @param bool   $rich        Whether to allow inline HTML (img, spans etc.).
     * @return string
     */
    private function get_field_value( $field_key, $participant, $tags, $missing, $rich = true ) {
        switch ( $field_key ) {
            case 'logo':
                if ( ! empty( $participant->logo_url ) ) {
                    if ( $rich ) {
                        return '<img src="' . esc_url( $participant->logo_url ) . '" alt="' . esc_attr( $participant->organization_name ) . '" style="max-width:100%;max-height:80px;object-fit:contain;">';
                    }
                    return esc_url( $participant->logo_url );
                }
                if ( ! empty( $participant->no_logo ) ) {
                    return esc_html__( '(intet logo)', 'volunteer-exchange-platform' );
                }
                return '&mdash;';

            case 'participant_number':
                return $participant->participant_number
                    ? esc_html( $participant->participant_number )
                    : '&mdash;';

            case 'organization_name':
                return esc_html( $participant->organization_name ?? '' );

            case 'description':
                $desc = trim( (string) ( $participant->description ?? '' ) );
                if ( $desc ) {
                    return $rich ? nl2br( esc_html( $desc ) ) : esc_html( $desc );
                }
                return '&mdash;';

            case 'type':
                return esc_html( $participant->type_name ?? '&mdash;' );

            case 'expected_count':
                $count = $participant->expected_participants_count ?? null;
                if ( null !== $count && '' !== (string) $count ) {
                    return esc_html( $count );
                }
                return esc_html__( 'Unknown', 'volunteer-exchange-platform' );

            case 'expected_names':
                $names_value = $participant->expected_participants_names ?? '';

                // Backward-compatible fallbacks for older/alternate payload shapes.
                if ( '' === (string) $names_value && isset( $participant->expected_participant_names ) ) {
                    $names_value = $participant->expected_participant_names;
                }
                if ( '' === (string) $names_value && isset( $participant->participant_names ) ) {
                    $names_value = $participant->participant_names;
                }
                if ( '' === (string) $names_value && isset( $participant->expected_names ) ) {
                    $names_value = $participant->expected_names;
                }

                if ( is_array( $names_value ) ) {
                    $names_value = implode( ', ', array_map( 'strval', $names_value ) );
                }

                $names = trim( (string) $names_value );
                if ( $names ) {
                    return $rich ? nl2br( esc_html( $names ) ) : esc_html( $names );
                }
                if ( ! empty( $participant->no_expected_names ) ) {
                    return esc_html__( '(ikke oplyst)', 'volunteer-exchange-platform' );
                }
                return '&mdash;';

            case 'contact_person':
                return esc_html( $participant->contact_person_name ?? '' );

            case 'email':
                return esc_html( $participant->contact_email ?? '' );

            case 'phone':
                return esc_html( $participant->contact_phone ?? '' );

            case 'we_offer':
                if ( empty( $tags ) ) {
                    return '&mdash;';
                }
                $names = array_map( function ( $t ) {
                    return esc_html( $t->name );
                }, $tags );
                return implode( ', ', $names );

            case 'status':
                if ( ! empty( $participant->is_approved ) ) {
                    return $rich
                        ? '<span style="color:#00a32a;font-weight:600;">' . esc_html__( 'Godkendt', 'volunteer-exchange-platform' ) . '</span>'
                        : esc_html__( 'Godkendt', 'volunteer-exchange-platform' );
                }
                return $rich
                    ? '<span style="color:#d63638;">' . esc_html__( 'Afventer godkendelse', 'volunteer-exchange-platform' ) . '</span>'
                    : esc_html__( 'Afventer godkendelse', 'volunteer-exchange-platform' );

            case 'missing_fields':
                if ( empty( $missing ) ) {
                    return $rich
                        ? '<span style="color:#00a32a;">' . esc_html__( 'Intet mangler', 'volunteer-exchange-platform' ) . '</span>'
                        : esc_html__( 'Intet mangler', 'volunteer-exchange-platform' );
                }
                $items = array_map( function ( $m ) {
                    return esc_html( $m['name'] );
                }, $missing );
                return implode( $rich ? '<br>' : ', ', $items );

            default:
                return '&mdash;';
        }
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Output the standalone HTML print page.
     *
     * @param array  $entries      Participant entries with participant/tags/missing keys.
     * @param array  $fields       Selected field keys in display order.
     * @param string $layout       'grid' | 'box' | 'liste'.
     * @param string $orientation  'portrait' | 'landscape'.
     * @param string $paper        'A3' | 'A4' | 'A5'.
     * @param int    $grid_cols    Number of columns for grid layout.
     * @param bool   $show_checkbox Whether to prepend a printable checkbox.
     * @param bool   $allow_cut     Whether to allow boxes to be cut across page breaks.
     * @return void
     */
    private function output_print_page( array $entries, $fields, $layout, $orientation, $paper, $grid_cols, $show_checkbox, $allow_cut = false ) {
        $title    = esc_html__( 'Udskrift af deltagere', 'volunteer-exchange-platform' );
        $page_css = esc_attr( $paper . ' ' . $orientation );
        $cols     = (int) $grid_cols;

        header( 'Content-Type: text/html; charset=UTF-8' );
        ?><!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title; ?></title>
<style>
@page {
    size: <?php echo $page_css; ?>;
    margin: 12mm 15mm;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11pt;
    color: #222;
    background: #fff;
    padding: 0;
}

/* ---------- header ---------- */
.vep-ph {
    border-bottom: 2px solid #333;
    margin-bottom: 14pt;
    padding-bottom: 6pt;
}
.vep-ph h1 {
    font-size: 16pt;
    font-weight: 700;
    margin-bottom: 2pt;
}
.vep-ph .event {
    font-size: 9pt;
    color: #666;
}

.vep-participant-card {
    border: 1pt solid #ccc;
    border-radius: 4pt;
    padding: 10pt;
    background: #fff;
    page-break-inside: avoid;
}

.vep-participant-card + .vep-participant-card {
    margin-top: 10pt;
}

.vep-participant-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8pt;
    margin-bottom: 8pt;
    padding-bottom: 6pt;
    border-bottom: 0.5pt solid #ddd;
}

.vep-participant-card-title {
    font-size: 12pt;
    font-weight: 700;
}

.vep-grid-card-title {
    display: flex;
    align-items: flex-start;
    gap: 6pt;
}

.vep-grid-cb {
    width: 12pt;
    height: 12pt;
    flex-shrink: 0;
    align-self: flex-start;
}

.vep-box-card-title {
    display: flex;
    align-items: flex-start;
    gap: 6pt;
}

.vep-box-cb {
    width: 12pt;
    height: 12pt;
    flex-shrink: 0;
    align-self: flex-start;
}

.vep-participant-card-meta {
    font-size: 9pt;
    color: #666;
}

/* ---------- printable checkbox ---------- */
.vep-cb {
    display: inline-block;
    width: 14pt;
    height: 14pt;
    border: 1.5pt solid #333;
    vertical-align: middle;
    flex-shrink: 0;
}

/* ---------- BOX layout ---------- */
.vep-box-row {
    display: flex;
    align-items: flex-start;
    padding: 5pt 0;
    border-bottom: 0.5pt solid #eee;
    gap: 8pt;
}
.vep-box-row:last-child { border-bottom: none; }
.vep-box-label {
    font-weight: bold;
    min-width: 110pt;
    flex-shrink: 0;
    color: #555;
    font-size: 9.5pt;
    padding-top: 1pt;
}
.vep-box-value { flex: 1; word-wrap: break-word; overflow-wrap: break-word; }

/* ---------- GRID layout ---------- */
.vep-grid {
    display: grid;
    grid-template-columns: repeat(<?php echo $cols; ?>, minmax(0, 1fr));
    grid-auto-rows: 1fr;
    align-items: stretch;
    gap: 8pt;
    width: 100%;
    max-width: 100%;
    padding-right: 1pt;
}

.vep-grid .vep-participant-card {
    height: 100%;
    min-width: 0;
}

.vep-grid .vep-participant-card + .vep-participant-card {
    margin-top: 0;
}

.vep-grid .vep-participant-card-header {
    flex-wrap: wrap;
    align-items: flex-start;
}

.vep-grid .vep-participant-card-title {
    min-width: 0;
    flex: 1 1 0;
}

.vep-grid .vep-participant-card-title span,
.vep-grid .vep-participant-card-meta,
.vep-grid .vep-box-label,
.vep-grid .vep-box-value {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.vep-grid .vep-box-row,
.vep-grid .vep-box-value {
    min-width: 0;
}

.vep-grid .vep-box-row {
    display: flex;
    align-items: flex-start;
    padding: 4pt 0;
    gap: 8pt;
}

.vep-grid .vep-box-label {
    display: inline-block;
    min-width: 0;
    padding-top: 0;
    margin-bottom: 0;
    white-space: nowrap;
}

.vep-grid .vep-box-value {
    display: inline-block;
}

.vep-grid .vep-box-row.vep-wrap-detected {
    display: block;
    gap: 0;
}

.vep-grid .vep-box-row.vep-wrap-detected .vep-box-label {
    display: block;
    margin-bottom: 2pt;
}

.vep-grid .vep-box-row.vep-wrap-detected .vep-box-value {
    display: block;
}

.vep-grid-cell {
    border: 1pt solid #ccc;
    border-radius: 4pt;
    padding: 7pt;
    background: #fafafa;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.vep-grid-cell-label {
    font-size: 8pt;
    color: #777;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    margin-bottom: 4pt;
}
.vep-grid-cell-value { font-size: 10pt; }

/* ---------- LISTE layout ---------- */
.vep-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10pt;
}
.vep-table th {
    background: #f0f0f0;
    border: 1pt solid #bbb;
    padding: 5pt 7pt;
    text-align: left;
    font-size: 9pt;
    font-weight: bold;
    white-space: nowrap;
}
.vep-table td {
    border: 1pt solid #bbb;
    padding: 5pt 7pt;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.vep-cb-col { width: 22pt; text-align: center; }

@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body[data-allow-cut="1"] .vep-participant-card {
        page-break-inside: auto;
    }
}
</style>
</head>
<body data-paper="<?php echo esc_attr( $paper ); ?>" data-orientation="<?php echo esc_attr( $orientation ); ?>" data-allow-cut="<?php echo $allow_cut ? '1' : '0'; ?>">
<div class="vep-ph">
    <h1><?php echo $title; ?></h1>
    <?php if ( ! empty( $entries[0]['participant']->event_name ) ) : ?>
    <div class="event"><?php echo esc_html( $entries[0]['participant']->event_name ); ?></div>
    <?php endif; ?>
</div>

<?php if ( 'box' === $layout ) : ?>
    <?php $this->render_box_layout( $entries, $fields, $show_checkbox ); ?>
<?php elseif ( 'grid' === $layout ) : ?>
    <?php $this->render_grid_layout( $entries, $fields, $show_checkbox, $grid_cols ); ?>
<?php else : ?>
    <?php $this->render_liste_layout( $entries, $fields, $show_checkbox ); ?>
<?php endif; ?>

<script>
function vepIsElementMultiline(el) {
    var style = window.getComputedStyle(el);
    var lineHeight = parseFloat(style.lineHeight);
    if (!lineHeight || Number.isNaN(lineHeight)) {
        var fontSize = parseFloat(style.fontSize) || 10;
        lineHeight = fontSize * 1.2;
    }

    return el.scrollHeight > (lineHeight * 1.35);
}

function vepApplyGridWrapRules() {
    var rows = document.querySelectorAll('.vep-grid .vep-box-row');
    rows.forEach(function (row) {
        row.classList.remove('vep-wrap-detected');

        var value = row.querySelector('.vep-box-value');
        if (!value) {
            return;
        }

        var wraps = vepIsElementMultiline(value) || (value.scrollWidth > (value.clientWidth + 1));
        if (wraps) {
            row.classList.add('vep-wrap-detected');
        }
    });
}

window.onload = function () {
     // Get paper format and orientation
     var paperFormat = document.body.getAttribute('data-paper') || 'A4';
     var orientation = document.body.getAttribute('data-orientation') || 'portrait';
     
     // Calculate body width based on paper format and orientation (minus 15mm margins on each side)
     var paperWidths = {
         'portrait': {
             'A3': '267mm',  // 297mm - 30mm
             'A4': '180mm',  // 210mm - 30mm
             'A5': '118mm'   // 148mm - 30mm
         },
         'landscape': {
             'A3': '390mm',  // 420mm - 30mm
             'A4': '267mm',  // 297mm - 30mm
             'A5': '180mm'   // 210mm - 30mm
         }
     };
     
     var maxWidth = (paperWidths[orientation] && paperWidths[orientation][paperFormat]) 
         ? paperWidths[orientation][paperFormat]
         : paperWidths['portrait']['A4'];
     
     // Set body to calculated width to match print output
     document.body.style.maxWidth = maxWidth;
     document.body.style.margin = '0 auto';
     
     // Wait for layout to reflow before detecting wraps
     vepApplyGridWrapRules();
    window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
            vepApplyGridWrapRules();
            window.print();
        });
    });

    window.onafterprint = function () { window.close(); };
};

window.addEventListener('beforeprint', function () {
    vepApplyGridWrapRules();
});

window.addEventListener('resize', function () {
    vepApplyGridWrapRules();
});
</script>
</body>
</html>
        <?php
    }

    /**
     * Render the box layout for all selected participants.
     *
     * @param array $entries Participant entries.
     * @param array $fields Selected field keys.
     * @param bool  $show_checkbox Show printable checkbox.
     * @return void
     */
    private function render_box_layout( array $entries, array $fields, $show_checkbox ) {
        foreach ( $entries as $entry ) {
            $participant = $entry['participant'];
            $tags        = $entry['tags'];
            $missing     = $entry['missing'];
            ?>
            <div class="vep-participant-card">
                <div class="vep-participant-card-header">
                    <div class="vep-participant-card-title vep-box-card-title">
                        <?php if ( $show_checkbox ) : ?>
                        <span class="vep-cb vep-box-cb" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span><?php echo esc_html( $participant->organization_name ?? '' ); ?></span>
                    </div>
                    <?php if ( ! empty( $participant->participant_number ) ) : ?>
                    <div class="vep-participant-card-meta">#<?php echo esc_html( $participant->participant_number ); ?></div>
                    <?php endif; ?>
                </div>
                <?php $this->render_field_rows( $participant, $tags, $missing, $fields, false ); ?>
            </div>
            <?php
        }
    }

    /**
     * Render the grid layout for all selected participants.
     *
     * @param array $entries Participant entries.
     * @param array $fields Selected field keys.
     * @param bool  $show_checkbox Show printable checkbox.
     * @param int   $grid_cols Columns per row.
     * @return void
     */
    private function render_grid_layout( array $entries, array $fields, $show_checkbox, $grid_cols ) {
        ?>
        <div class="vep-grid">
            <?php foreach ( $entries as $entry ) : ?>
            <div class="vep-participant-card">
                <div class="vep-participant-card-header">
                    <div class="vep-participant-card-title vep-grid-card-title">
                        <?php if ( $show_checkbox ) : ?>
                        <span class="vep-cb vep-grid-cb" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span><?php echo esc_html( $entry['participant']->organization_name ?? '' ); ?></span>
                    </div>
                    <?php if ( ! empty( $entry['participant']->participant_number ) ) : ?>
                    <div class="vep-participant-card-meta">#<?php echo esc_html( $entry['participant']->participant_number ); ?></div>
                    <?php endif; ?>
                </div>
                <?php $this->render_field_rows( $entry['participant'], $entry['tags'], $entry['missing'], $fields, false ); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render the liste (table) layout for all participants.
     *
     * @param array $entries Participant entries.
     * @param array $fields Selected field keys.
     * @param bool  $show_checkbox Show printable checkbox.
     * @return void
     */
    private function render_liste_layout( array $entries, array $fields, $show_checkbox ) {
        ?>
        <table class="vep-table">
            <thead>
                <tr>
                    <?php if ( $show_checkbox ) : ?><th class="vep-cb-col"></th><?php endif; ?>
                    <?php foreach ( $fields as $key ) : ?>
                    <th><?php echo esc_html( $this->get_field_label( $key ) ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $entries as $entry ) : ?>
                <tr>
                    <?php if ( $show_checkbox ) : ?><td class="vep-cb-col"><span class="vep-cb" aria-hidden="true"></span></td><?php endif; ?>
                    <?php foreach ( $fields as $key ) : ?>
                    <td><?php echo $this->get_field_value( $key, $entry['participant'], $entry['tags'], $entry['missing'], false ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render repeated field rows for box/grid layouts.
     *
     * @param object $participant Participant object.
     * @param array  $tags Tag objects.
     * @param array  $missing Missing field descriptors.
     * @param array  $fields Selected field keys.
     * @param bool   $show_checkbox Show printable checkbox.
     * @return void
     */
    private function render_field_rows( $participant, $tags, $missing, array $fields, $show_checkbox ) {
        if ( $show_checkbox ) {
            echo '<div class="vep-box-row"><div class="vep-box-label">&nbsp;</div><div class="vep-box-value"><span class="vep-cb" aria-hidden="true"></span></div></div>';
        }

        foreach ( $fields as $key ) {
            echo '<div class="vep-box-row">';
            echo '<div class="vep-box-label">' . esc_html( $this->get_field_label( $key ) ) . ':</div>';
            echo '<div class="vep-box-value">' . $this->get_field_value( $key, $participant, $tags, $missing, true ) . '</div>';
            echo '</div>';
        }
    }
}
