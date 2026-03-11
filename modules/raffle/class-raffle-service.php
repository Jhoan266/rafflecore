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
    public static function prepare_data( $post ) {
        $data = array(
            'title'         => sanitize_text_field( wp_unslash( $post['title'] ?? '' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $post['description'] ?? '' ) ),
            'prize_value'   => floatval( $post['prize_value'] ?? 0 ),
            'prize_image'   => esc_url_raw( wp_unslash( $post['prize_image'] ?? '' ) ),
            'total_tickets' => absint( $post['total_tickets'] ?? 0 ),
            'ticket_price'  => floatval( $post['ticket_price'] ?? 0 ),
            'draw_date'     => sanitize_text_field( wp_unslash( $post['draw_date'] ?? '' ) ),
            'status'        => sanitize_text_field( wp_unslash( $post['status'] ?? 'active' ) ),
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

        // Lucky numbers: "12, 345, 6789" → [12, 345, 6789]
        $lucky_raw = sanitize_text_field( wp_unslash( $post['lucky_numbers'] ?? '' ) );
        $lucky     = array();
        if ( ! empty( $lucky_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $lucky_raw ) );
            foreach ( $parts as $num ) {
                $n = absint( $num );
                if ( $n > 0 ) {
                    $lucky[] = $n;
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

        // Prize gallery: "url1, url2" → ["url1","url2"]
        $gallery_raw = wp_unslash( $post['prize_gallery'] ?? '' );
        $gallery     = array();
        if ( ! empty( $gallery_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $gallery_raw ) );
            foreach ( $parts as $url ) {
                $clean = esc_url_raw( $url );
                if ( $clean ) {
                    $gallery[] = $clean;
                }
            }
        }
        $data['prize_gallery'] = wp_json_encode( $gallery );

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
