<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Service — Lógica de negocio para rifas.
 */
class RaffleCore_Raffle_Service {

    /**
     * Catálogo de fuentes disponibles, agrupadas por categoría.
     * Para agregar una fuente: añadir una línea al grupo correspondiente.
     * Solo se carga en Google Fonts la fuente elegida → 0 impacto en rendimiento.
     */
    public static function get_font_catalog() {
        return array(
            'Redondeadas / Divertidas' => array(
                'Fredoka', 'Baloo 2', 'Comfortaa', 'Quicksand',
                'Bubblegum Sans', 'Chewy', 'Luckiest Guy', 'Boogaloo',
            ),
            'Sans-Serif Modernas' => array(
                'Poppins', 'Montserrat', 'Inter', 'Rubik', 'Nunito',
                'Raleway', 'Josefin Sans', 'DM Sans', 'Space Grotesk',
                'Outfit', 'Sora', 'Lexend', 'Urbanist', 'Plus Jakarta Sans',
            ),
            'Display / Impacto' => array(
                'Bangers', 'Righteous', 'Bungee', 'Permanent Marker',
                'Lilita One', 'Passion One', 'Anton', 'Bebas Neue',
                'Russo One', 'Orbitron', 'Press Start 2P',
            ),
            'Elegantes / Serif' => array(
                'Playfair Display', 'Lora', 'Merriweather', 'Cormorant Garamond',
                'Libre Baskerville', 'Crimson Text',
            ),
            'Script / Manuscritas' => array(
                'Pacifico', 'Dancing Script', 'Lobster', 'Caveat',
                'Satisfy', 'Great Vibes',
            ),
        );
    }

    /**
     * Lista plana de nombres de fuentes permitidas.
     */
    public static function get_allowed_fonts() {
        $fonts = array( '' ); // vacío = predeterminada
        foreach ( self::get_font_catalog() as $group ) {
            foreach ( $group as $font ) {
                $fonts[] = $font;
            }
        }
        return $fonts;
    }

    /**
     * Valida y prepara datos de formulario para crear/editar una rifa.
     */
    public static function prepare_data( $post, $raffle_id = 0 ) {
        $ticket_digits = isset($post['ticket_digits']) ? absint($post['ticket_digits']) : 2;
        $max_tickets = ($ticket_digits === 2) ? 99 : (($ticket_digits === 3) ? 999 : (($ticket_digits === 4) ? 9999 : 99999));
        $data = array(
            'title'         => sanitize_text_field( wp_unslash( $post['title'] ?? '' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $post['description'] ?? '' ) ),
            'lottery'       => sanitize_text_field( wp_unslash( $post['lottery'] ?? '' ) ),
            'prize_value'   => floatval( $post['prize_value'] ?? 0 ),
            'prize_image'   => esc_url_raw( wp_unslash( $post['prize_image'] ?? '' ) ),
            'total_tickets' => ( $raffle_id && ! isset( $post['ticket_digits'] ) ) ? 0 : $max_tickets, // 0 means don't update in DB if not provided
            'ticket_digits' => $ticket_digits,
            'ticket_price'  => floatval( $post['ticket_price'] ?? 0 ),
            'draw_date'           => sanitize_text_field( wp_unslash( $post['draw_date'] ?? '' ) ),
            'status'              => sanitize_text_field( wp_unslash( $post['status'] ?? 'active' ) ),
            'type'                => sanitize_text_field( wp_unslash( $post['type'] ?? 'quantity' ) ),
            'max_number'          => absint( $post['max_number'] ?? 0 ),
            'countdown_threshold' => absint( $post['countdown_threshold'] ?? 0 ),
        );

        // Packages: "5:20000, 10:35000" → [{"qty":5,"price":20000}, ...]
        $packages_raw = sanitize_text_field( wp_unslash( $post['packages'] ?? '' ) );
        $packages     = array();
        if ( ! empty( $packages_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $packages_raw ) );
            foreach ( $parts as $part ) {
                if ( strpos( $part, ':' ) !== false ) {
                    list( $qty, $price ) = explode( ':', $part, 2 );
                    $qty   = absint( $qty );
                    $price = absint( $price );
                    if ( $qty > 0 && $price > 0 ) {
                        $packages[] = array( 'qty' => $qty, 'price' => $price );
                    }
                }
            }
        }
        $data['packages'] = wp_json_encode( $packages );

        // Lucky numbers: "012, 345, 6789" → ["012", "345", "6789"]
        $lucky_raw = sanitize_text_field( wp_unslash( $post['lucky_numbers'] ?? '' ) );
        $lucky     = array();
        if ( ! empty( $lucky_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $lucky_raw ) );
            foreach ( $parts as $num ) {
                if ( $num !== '' && ctype_digit( $num ) ) {
                    $lucky[] = str_pad( $num, $ticket_digits, '0', STR_PAD_LEFT );
                }
            }
        }
        $data['lucky_numbers'] = wp_json_encode( array_unique( $lucky ) );

        // Font family
        $font = sanitize_text_field( wp_unslash( $post['font_family'] ?? '' ) );
        if ( $font === 'custom' ) {
            $data['font_family'] = 'custom';
            $data['custom_font_url'] = esc_url_raw( wp_unslash( $post['custom_font_url'] ?? '' ) );
        } else {
            $data['font_family'] = in_array( $font, self::get_allowed_fonts(), true ) ? $font : '';
            $data['custom_font_url'] = '';
        }

        // Color palette
        $allowed_palettes = array( '', 'vibrant', 'ocean', 'sunset', 'neon', 'galaxy', 'matte-fusion' );
        $palette = sanitize_text_field( wp_unslash( $post['color_palette'] ?? '' ) );
        $data['color_palette'] = in_array( $palette, $allowed_palettes, true ) ? $palette : '';

        // Min custom qty
        $data['min_custom_qty'] = absint( $post['min_custom_qty'] ?? 0 );

        return $data;
    }

    /**
     * Calcula el progreso de venta.
     */
    public static function get_progress( $raffle ) {
        if ( $raffle->total_tickets <= 0 ) {
            return 0;
        }
        return min( 100, round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) );
    }

    /**
     * Obtiene paquetes válidos (que caben en los boletos restantes).
     */
    public static function get_available_packages( $raffle ) {
        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;
        return array_values( array_filter( $packages, function ( $pkg ) use ( $remaining ) {
            $qty = is_array( $pkg ) ? ( $pkg['qty'] ?? 0 ) : (int) $pkg;
            return $qty > 0 && $qty <= $remaining;
        } ) );
    }
}
