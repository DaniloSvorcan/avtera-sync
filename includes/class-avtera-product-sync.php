<?php
defined( 'ABSPATH' ) || exit;

class Avtera_Product_Sync {

    private array $results = [
        'created' => 0,
        'updated' => 0,
        'errors'  => [],
    ];

    // Mapiranje Avtera kategorija → naše WooCommerce kategorije
    private array $category_map = [
        // Prenosni računalniki
        'Consumer osnovni (Laptop, Pavilion, X360) 13 in-/33 cm- zaslon'          => 'Prenosni računalniki',
        'Consumer osnovni (Laptop, Pavilion, X360) 14 in+/36 cm+ zaslon'          => 'Prenosni računalniki',
        'Consumer osnovni (Laptop, Pavilion, X360) 15 in+/38 cm+ zaslon'          => 'Prenosni računalniki',
        'Consumer osnovni (Laptop, Pavilion, X360) 17 in+/43 cm+ zaslon'          => 'Prenosni računalniki',
        'Consumer zmogljivi (Envy, Spectre, Omnibook) 14 in+/36 cm+ zaslon'       => 'Prenosni računalniki',
        'Consumer zmogljivi (Envy, Spectre, Omnibook) 15 in+/38 cm+ zaslon'       => 'Prenosni računalniki',
        'Prenosniki - osnovni 15 in+/38 cm+ zaslon'                                => 'Prenosni računalniki',
        'Prenosniki - osnovni 17 in+/43 cm+ zaslon'                                => 'Prenosni računalniki',
        'Prenosniki - poslovni 13 in+/33 cm+ zaslon'                               => 'Prenosni računalniki',
        'Prenosniki - poslovni 14 in+/36 cm+ zaslon'                               => 'Prenosni računalniki',
        'Prenosniki - poslovni 15 in+/38 cm+ zaslon'                               => 'Prenosni računalniki',
        'Prenosniki - gaming 15 in+/38 cm+ zaslon'                                 => 'Prenosni računalniki',
        'Prenosniki - gaming 17 in+/43 cm+ zaslon'                                 => 'Prenosni računalniki',
        'Prenosniki - delovne postaje 14 in+/36 cm+ zaslon'                        => 'Prenosni računalniki',
        'Prenosniki - delovne postaje 15 in+/38 cm+ zaslon'                        => 'Prenosni računalniki',
        'Prenosniki - delovne postaje 17 in+/43 cm+ zaslon'                        => 'Prenosni računalniki',
        'Prenosniki - zmogljivi 14 in+/36 cm+ zaslon'                              => 'Prenosni računalniki',
        'Prenosniki - zmogljivi 15 in+/38 cm+ zaslon'                              => 'Prenosni računalniki',
        'Prenosniki - zmogljivi Intel 14 in+/36 cm+ zaslon'                        => 'Prenosni računalniki',
        'Prenosniki - zmogljivi Intel 15 in+/38 cm+ zaslon'                        => 'Prenosni računalniki',
        'Prenosniki - zmogljivi 17 in+/43 cm+ zaslon'                              => 'Prenosni računalniki',
        'Prenosniki - zmogljivi 13 in+/33 cm+ zaslon'                              => 'Prenosni računalniki',
        // Namizni računalniki
        'Računalniki - poslovni Intel platforma'                                    => 'Namizni računalniki',
        'Računalniki - poslovni AMD platforma'                                      => 'Namizni računalniki',
        'Računalniki - zmogljivi Intel platforma'                                   => 'Namizni računalniki',
        // AIO računalniki
        'Računalniki - All in One'                                                  => 'AIO računalniki',
        // Računalniška periferija
        'Dodatki za računalnike Dodatki splošno'                                    => 'Računalniška periferija',
        'Dodatki za računalnike Dodatki-prenosni računalniki'                       => 'Računalniška periferija',
        // Priklopne postaje
        'Dodatki za računalnike Priklopne postaje'                                  => 'Priklopne postaje',
        // Strežniki
        'Strežniki'                                                                 => 'Strežniki',
        'Dodatki za strežnike'                                                      => 'Strežniki',
    ];

    public function run( array $products ): array {
        $this->results = [ 'created' => 0, 'updated' => 0, 'errors' => [] ];

        foreach ( $products as $data ) {
            try {
                $this->sync_product( $data );
            } catch ( Exception $e ) {
                $this->results['errors'][] = "[SKU {$data['sku']}] " . $e->getMessage();
            }
        }

        return $this->results;
    }

    private function sync_product( array $data ): void {
        if ( empty( $data['sku'] ) ) {
            throw new Exception( 'Nedostaje SKU (izdelekID).' );
        }

        $product_id = wc_get_product_id_by_sku( $data['sku'] );
        $is_new     = ! $product_id;

        if ( $is_new ) {
            $product = new WC_Product_Simple();
        } else {
            $product = wc_get_product( $product_id );
        }

        $this->populate_product( $product, $data );
        $product->save();

        $product_id = $product->get_id();

        // Slike (uvoz posle save-a da imamo product_id)
        $this->handle_images( $product_id, $data, $is_new );

        // Extra meta polja
        update_post_meta( $product_id, '_avtera_id',  $data['id'] );
        update_post_meta( $product_id, '_mpn',        $data['mpn'] );
        update_post_meta( $product_id, '_ean',        $data['ean'] );
        update_post_meta( $product_id, '_warranty',   $data['warranty'] );
        update_post_meta( $product_id, '_avtera_url', $data['url'] );

        if ( $is_new ) {
            $this->results['created']++;
        } else {
            $this->results['updated']++;
        }
    }

    private function populate_product( WC_Product $product, array $data ): void {
        $product->set_name( $data['name'] );
        $product->set_sku( $data['sku'] );
        $product->set_description( $data['description'] );
        $product->set_regular_price( $data['price'] );
        $product->set_sale_price( $data['sale_price'] !== '' ? $data['sale_price'] : '' );

        // Zaliha
        $in_stock = ( false !== stripos( $data['stock_status'], 'zalogi' ) );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $data['stock_qty'] );
        $product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );

        // Težina
        if ( $data['weight'] ) {
            $product->set_weight( $data['weight'] );
        }

        // Kategorija
        if ( $data['category'] ) {
            $cat_id = $this->get_or_create_category( $data['category'] );
            if ( $cat_id ) {
                $product->set_category_ids( [ $cat_id ] );
            }
        }

        // Atributi
        $wc_attributes = [];

        if ( $data['brand'] ) {
            $wc_attributes[] = $this->make_attribute( 'Blagovna znamka', $data['brand'] );
        }

        if ( $data['warranty'] ) {
            $wc_attributes[] = $this->make_attribute( 'Garancija', $data['warranty'] );
        }

        foreach ( $data['attributes'] as $name => $value ) {
            $wc_attributes[] = $this->make_attribute( $name, $value );
        }

        if ( $wc_attributes ) {
            $product->set_attributes( $wc_attributes );
        }

        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
    }

    private function make_attribute( string $name, string $value ): WC_Product_Attribute {
        $attr = new WC_Product_Attribute();
        $attr->set_name( $name );
        $attr->set_options( [ $value ] );
        $attr->set_visible( true );
        $attr->set_variation( false );
        return $attr;
    }

    private function get_or_create_category( string $name ): ?int {
        // Primijeni mapping — koristi našu kategoriju, nikad Avterinu
        $mapped_name = $this->category_map[ $name ] ?? null;

        if ( $mapped_name ) {
            // Samo traži — NIKAD ne kreira kategoriju
            $term = get_term_by( 'name', $mapped_name, 'product_cat' );
            return $term ? $term->term_id : null;
        }

        // Nije u mappingu — ignoriši, ne kreira ništa
        return null;
    }

    private function handle_images( int $product_id, array $data, bool $is_new ): void {
        $product       = wc_get_product( $product_id );
        $existing_thumb = $product->get_image_id();

        // Glavna slika — uvoz samo ako nedostaje
        if ( ! $existing_thumb && ! empty( $data['image'] ) ) {
            $image_id = $this->sideload_image( $data['image'], $product_id, $data['name'] );
            if ( $image_id ) {
                $product->set_image_id( $image_id );
                $product->save();
            }
        }

        // Galerija — uvoz ako je trenutno prazna
        $existing_gallery = $product->get_gallery_image_ids();
        if ( empty( $existing_gallery ) && ! empty( $data['gallery'] ) ) {
            $gallery_ids = [];
            foreach ( array_slice( $data['gallery'], 0, 9 ) as $url ) {
                $img_id = $this->sideload_image( $url, $product_id, $data['name'] );
                if ( $img_id ) {
                    $gallery_ids[] = $img_id;
                }
            }
            if ( $gallery_ids ) {
                $product->set_gallery_image_ids( $gallery_ids );
                $product->save();
            }
        }
    }

    private function sideload_image( string $url, int $post_id, string $alt = '' ): ?int {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $result = media_sideload_image( $url, $post_id, $alt, 'id' );

        if ( is_wp_error( $result ) ) {
            return null;
        }

        return (int) $result;
    }
}
