<?php
defined( 'ABSPATH' ) || exit;

class Avtera_Admin {

    const OPTION_FEED_URL    = 'avtera_feed_url';
    const OPTION_LAST_SYNC   = 'avtera_last_sync';
    const OPTION_LAST_RESULT = 'avtera_last_result';
    const CRON_HOOK          = 'avtera_sync_cron';

    public function __construct() {
        add_action( 'admin_menu',                             [ $this, 'add_menu' ] );
        add_action( 'admin_init',                             [ $this, 'register_settings' ] );
        add_action( self::CRON_HOOK,                          [ $this, 'run_cron_sync' ] );
        add_action( 'admin_post_avtera_manual_sync',          [ $this, 'handle_manual_sync' ] );
        add_action( 'update_option_avtera_cron_schedule',     [ $this, 'update_cron_schedule' ], 10, 2 );
    }

    public function add_menu(): void {
        add_submenu_page(
            'woocommerce',
            'Avtera Sync',
            'Avtera Sync',
            'manage_woocommerce',
            'avtera-sync',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'avtera_settings', self::OPTION_FEED_URL, [
            'sanitize_callback' => 'esc_url_raw',
        ] );
        register_setting( 'avtera_settings', 'avtera_cron_schedule', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
    }

    public function render_page(): void {
        $feed_url    = get_option( self::OPTION_FEED_URL, '' );
        $last_sync   = get_option( self::OPTION_LAST_SYNC );
        $last_result = get_option( self::OPTION_LAST_RESULT );
        $schedule    = get_option( 'avtera_cron_schedule', 'disabled' );
        $next_cron   = wp_next_scheduled( self::CRON_HOOK );
        ?>
        <div class="wrap">
            <h1>Avtera — Sinhronizacija Proizvoda</h1>

            <?php if ( isset( $_GET['synced'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Sinhronizacija uspešno završena.</p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['sync_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>Greška: <?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['sync_error'] ) ) ) ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $last_result ) : ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Poslednja sinhronizacija:</strong>
                        <?php echo $last_sync ? esc_html( date_i18n( 'd.m.Y H:i', $last_sync ) ) : '—'; ?> &nbsp;|&nbsp;
                        Kreirano: <strong><?php echo (int) $last_result['created']; ?></strong> &nbsp;|&nbsp;
                        Ažurirano: <strong><?php echo (int) $last_result['updated']; ?></strong> &nbsp;|&nbsp;
                        Greške: <strong><?php echo count( $last_result['errors'] ); ?></strong>
                    </p>
                    <?php if ( ! empty( $last_result['errors'] ) ) : ?>
                        <details>
                            <summary>Prikaži greške</summary>
                            <ul style="margin-top:8px;">
                                <?php foreach ( $last_result['errors'] as $err ) : ?>
                                    <li style="color:#cc1818;"><?php echo esc_html( $err ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <hr>

            <h2>Postavke feed-a</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'avtera_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="avtera_feed_url">URL XML feed-a</label></th>
                        <td>
                            <input
                                type="url"
                                id="avtera_feed_url"
                                name="<?php echo self::OPTION_FEED_URL; ?>"
                                value="<?php echo esc_attr( $feed_url ); ?>"
                                class="large-text"
                            />
                            <p class="description">Kompletan URL Avtera XML feed-a.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avtera_cron_schedule">Auto-sinhronizacija</label></th>
                        <td>
                            <select id="avtera_cron_schedule" name="avtera_cron_schedule">
                                <option value="disabled"   <?php selected( $schedule, 'disabled' ); ?>>Isključena</option>
                                <option value="hourly"     <?php selected( $schedule, 'hourly' ); ?>>Svaki sat</option>
                                <option value="twicedaily" <?php selected( $schedule, 'twicedaily' ); ?>>Dva puta dnevno</option>
                                <option value="daily"      <?php selected( $schedule, 'daily' ); ?>>Jednom dnevno</option>
                            </select>
                            <?php if ( $next_cron ) : ?>
                                <p class="description">
                                    Sledeće automatsko pokretanje: <strong><?php echo esc_html( date_i18n( 'd.m.Y H:i', $next_cron ) ); ?></strong>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Sačuvaj postavke' ); ?>
            </form>

            <hr>

            <h2>Manuelna sinhronizacija</h2>
            <p>Pokreće odmah uvoz/ažuriranje svih proizvoda sa feed-a.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="avtera_manual_sync" />
                <?php wp_nonce_field( 'avtera_manual_sync' ); ?>
                <?php submit_button( 'Pokreni sinhronizaciju sada', 'primary large', 'submit', false ); ?>
            </form>

            <hr>

            <h2>Mapiranje polja</h2>
            <table class="widefat striped" style="max-width:600px;">
                <thead>
                    <tr><th>Avtera XML polje</th><th>WooCommerce</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>izdelekID</code></td><td>SKU (primarni sync ključ)</td></tr>
                    <tr><td><code>izdelekIme</code></td><td>Naziv proizvoda</td></tr>
                    <tr><td><code>opis</code></td><td>Opis</td></tr>
                    <tr><td><code>PPC</code></td><td>Redovna cena</td></tr>
                    <tr><td><code>cenaAkcijska</code></td><td>Akcijska cena</td></tr>
                    <tr><td><code>zaloga</code></td><td>Količina na zalogi</td></tr>
                    <tr><td><code>dobava</code></td><td>Status zaloge (in/outofstock)</td></tr>
                    <tr><td><code>kategorija</code></td><td>Kategorija proizvoda</td></tr>
                    <tr><td><code>blagovnaZnamka</code></td><td>Atribut: Blagovna znamka</td></tr>
                    <tr><td><code>WarrantyCustomer</code></td><td>Atribut: Garancija</td></tr>
                    <tr><td><code>slikaVelika</code></td><td>Glavna slika</td></tr>
                    <tr><td><code>dodatneSlike</code></td><td>Galerija slika</td></tr>
                    <tr><td><code>brutoTeza</code></td><td>Težina</td></tr>
                    <tr><td><code>MPN</code></td><td>Meta: _mpn</td></tr>
                    <tr><td><code>EAN</code></td><td>Meta: _ean</td></tr>
                    <tr><td><code>url</code></td><td>Meta: _avtera_url</td></tr>
                    <tr><td><code>dodatneLastnosti</code></td><td>Atributi proizvoda (dinamički)</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_manual_sync(): void {
        check_admin_referer( 'avtera_manual_sync' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Nemate dozvolu za ovu akciju.' );
        }

        try {
            $this->do_sync();
            wp_safe_redirect( admin_url( 'admin.php?page=avtera-sync&synced=1' ) );
        } catch ( Exception $e ) {
            wp_safe_redirect( admin_url( 'admin.php?page=avtera-sync&sync_error=' . rawurlencode( $e->getMessage() ) ) );
        }

        exit;
    }

    public function run_cron_sync(): void {
        try {
            $this->do_sync();
        } catch ( Exception $e ) {
            error_log( '[Avtera Sync] Cron greška: ' . $e->getMessage() );
        }
    }

    public function update_cron_schedule( $old_value, $new_value ): void {
        // Ukloni postojeci cron
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        // Zakaži novi ako nije "disabled"
        if ( 'disabled' !== $new_value ) {
            wp_schedule_event( time(), $new_value, self::CRON_HOOK );
        }
    }

    private function do_sync(): array {
        $feed_url = get_option( self::OPTION_FEED_URL, '' );

        if ( empty( $feed_url ) ) {
            throw new Exception( 'URL feed-a nije postavljen. Idi na Postavke i unesi URL.' );
        }

        $parser   = new Avtera_XML_Parser();
        $products = $parser->fetch_and_parse( $feed_url );

        if ( empty( $products ) ) {
            throw new Exception( 'Feed je prazan — nema proizvoda za uvoz.' );
        }

        $sync    = new Avtera_Product_Sync();
        $results = $sync->run( $products );

        update_option( self::OPTION_LAST_SYNC,   time() );
        update_option( self::OPTION_LAST_RESULT, $results );

        return $results;
    }
}
