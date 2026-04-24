<?php
defined( 'ABSPATH' ) || exit;

class Avtera_XML_Parser {

    public function fetch_and_parse( string $url ): array {
        $response = wp_remote_get( $url, [
            'timeout'   => 30,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'HTTP greška: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            throw new Exception( "Feed nije dostupan (HTTP $status)." );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            throw new Exception( 'Prazan odgovor sa feed-a.' );
        }

        return $this->parse( $body );
    }

    private function parse( string $xml_string ): array {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_string, 'SimpleXMLElement', LIBXML_NOCDATA );

        if ( false === $xml ) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = ! empty( $errors ) ? $errors[0]->message : 'nepoznata greška';
            throw new Exception( 'XML parse greška: ' . $msg );
        }

        $products = [];
        foreach ( $xml->izdelki->izdelek as $item ) {
            $products[] = $this->parse_product( $item );
        }

        return $products;
    }

    private function parse_product( SimpleXMLElement $item ): array {
        // Galerija slika (child elementi: dodatnaSlika1, dodatnaSlika2, ...)
        $gallery = [];
        if ( isset( $item->dodatneSlike ) ) {
            foreach ( $item->dodatneSlike->children() as $slika ) {
                $url = trim( (string) $slika );
                if ( $url ) {
                    $gallery[] = $url;
                }
            }
        }

        // Dinamički atributi
        $attributes = [];
        if ( isset( $item->dodatneLastnosti->lastnost ) ) {
            foreach ( $item->dodatneLastnosti->lastnost as $lastnost ) {
                $name  = trim( (string) $lastnost['ime'] );
                $value = trim( (string) $lastnost );
                if ( $name && $value ) {
                    $attributes[ $name ] = $value;
                }
            }
        }

        $sale_price = trim( (string) $item->cenaAkcijska );

        return [
            'id'          => trim( (string) $item->izdelekID ),
            'sku'         => trim( (string) $item->izdelekID ),
            'mpn'         => trim( (string) $item->MPN ),
            'ean'         => trim( (string) $item->EAN ),
            'name'        => trim( (string) $item->izdelekIme ),
            'description' => trim( (string) $item->opis ),
            'price'       => trim( (string) $item->PPC ),
            'sale_price'  => $sale_price,
            'tax_rate'    => trim( (string) $item->davcnaStopnja ),
            'category'    => trim( (string) $item->kategorija ),
            'brand'       => trim( (string) $item->blagovnaZnamka ),
            'image'       => trim( (string) $item->slikaVelika ),
            'gallery'     => $gallery,
            'stock_status'=> trim( (string) $item->dobava ),
            'stock_qty'   => (int) $item->zaloga,
            'weight'      => trim( (string) $item->brutoTeza ),
            'warranty'    => trim( (string) $item->WarrantyCustomer ),
            'url'         => trim( (string) $item->url ),
            'attributes'  => $attributes,
        ];
    }
}
