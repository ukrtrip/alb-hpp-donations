<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin page to generate EC P-384 JWK keys (no Composer, uses OpenSSL + bundled SimpleJWT)
 * - Generates EC P-384 (secp384r1) private key
 * - Builds JWK (private with "d", public without "d"), WITHOUT kid
 * - Saves files into wp-content/uploads/alb-hpp-keys/
 * - Allows downloading public_jwk.json
 * - Allows saving private_jwk.json content into plugin option (field "privateJwk")
 */
class ALB_Keys_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu() {
        add_submenu_page(
            'alb-hpp',                           // parent
            'Ключі (JWK)',                       // page_title
            'Ключі (JWK)',                       // menu_title
            'manage_options',                    // capability
            'alb-hpp-keys',                      // slug
            [__CLASS__, 'render_page']           // callback
        );
    }

    protected static function get_dir() {
        $upload = wp_get_upload_dir();
        return trailingslashit($upload['basedir']) . 'alb-hpp-keys';
    }

    protected static function get_url() {
        $upload = wp_get_upload_dir();
        return trailingslashit($upload['baseurl']) . 'alb-hpp-keys';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');

        $message = '';
        // Handle actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['alb_action']) && $_POST['alb_action']==='gen' && check_admin_referer('alb_keys_gen')) {
                $err = self::handle_generate();
                if (is_wp_error($err)) $message = '<div class="notice notice-error"><p><strong>Помилка:</strong> '.esc_html($err->get_error_message()).'</p></div>';
                else $message = '<div class="notice notice-success"><p><strong>Готово.</strong> Ключі згенеровано/оновлено.</p></div>';
            } elseif (isset($_POST['alb_action']) && $_POST['alb_action']==='save_private' && check_admin_referer('alb_keys_save_private')) {
                $err = self::handle_save_private_to_options();
                if (is_wp_error($err)) $message = '<div class="notice notice-error"><p><strong>Помилка:</strong> '.esc_html($err->get_error_message()).'</p></div>';
                else $message = '<div class="notice notice-success"><p><strong>OK.</strong> Приватний JWK збережено в налаштування плагіну.</p></div>';
            }
        }

        $dir = self::get_dir();
        $pub_file  = $dir . '/public_jwk.json';
        $priv_file = $dir . '/private_jwk.json';
        $pub_exists  = file_exists($pub_file);
        $priv_exists = file_exists($priv_file);
        $pub_url = self::get_url() . '/public_jwk.json';

        echo '<div class="wrap"><h1>ALB HPP — Ключі (JWK)</h1>';
        echo '<p>Генерація EC P-384 ключів у форматі JWK для банку. Публічний JWK можна завантажити та передати банку, приватний — зберегти у налаштування плагіну (поле <code>Private JWK</code>).</p>';

        if ($message) echo $message;

        echo '<form method="post" id="albFormGen" style="margin-bottom:16px">';
        wp_nonce_field('alb_keys_gen');
        submit_button('Згенерувати ключі (перезапише)', 'primary', 'submit', false);
        echo '<input type="hidden" name="alb_action" value="gen"/></form>';

        echo '<hr/>';

        echo '<h2>Публічний JWK</h2>';
        if ($pub_exists) {
            $pub = file_get_contents($pub_file);
            echo '<p><a class="button button-secondary" href="'.esc_url($pub_url).'" download>Завантажити public_jwk.json</a></p>';
            echo '<textarea readonly style="width:100%;height:200px">'.esc_textarea($pub).'</textarea>';
        } else {
            echo '<p>Ще не згенеровано.</p>';
        }

        echo '<h2>Приватний JWK</h2>';
        if ($priv_exists) {
            $priv = file_get_contents($priv_file);
            echo '<form method="post" id="albFormSavePriv">';
            wp_nonce_field('alb_keys_save_private');
            echo '<p><em>Нижче вміст <code>private_jwk.json</code> (не відправляти банку):</em></p>';
            echo '<textarea readonly style="width:100%;height:200px">'.esc_textarea($priv).'</textarea>';
            echo '<p>';
            submit_button('Зберегти приватний JWK у налаштування плагіну', 'secondary', 'submit', false);
            echo '<input type="hidden" name="alb_action" value="save_private"/>';
            echo '</p></form>';
        } else {
            echo '<p>Ще не згенеровано.</p>';
        }

        echo '</div>';
?>		
<script>
 document.addEventListener('DOMContentLoaded', function(){
    const gen=document.getElementById('albFormGen');
    if(gen) gen.addEventListener('submit',function(e){
        if(!confirm('⚠️ Це ПЕРЕЗАПИШЕ існуючі ключі (private.pem, private_jwk.json, public_jwk.json). Продовжити?')) {
            e.preventDefault();
        }
    });
    const savep=document.getElementById('albFormSavePriv');
    if(savep) savep.addEventListener('submit',function(e){
        if(!confirm('⚠️ Це ЗАПИШЕ приватний JWK у налаштування та ОЧИСТИТЬ Merchant ID і Service Code. Продовжити?')) {
            e.preventDefault();
        }
    });
 });
</script>
<?php
    }

    protected static function handle_generate() {
        // Ensure uploads dir exists
        $dir = self::get_dir();
        if (!wp_mkdir_p($dir)) return new WP_Error('mkdir_failed', 'Не вдалося створити директорію: '.$dir);

        // .htaccess to deny all except public_jwk.json
        $ht = $dir . '/.htaccess';
        $ht_body = <<<HT
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>

# Дозволити лише публічний ключ
<Files "public_jwk.json">
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
    <IfModule !mod_authz_core.c>
        Allow from all
        Satisfy any
    </IfModule>
</Files>
HT;
        if (!file_exists($ht)) @file_put_contents($ht, $ht_body);

        // 1) Generate EC P-384 private key (PEM)
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'secp384r1',
        ]);
        if (!$res) return new WP_Error('openssl_failed', 'OpenSSL: не вдалося згенерувати ключ');

        $ok = openssl_pkey_export($res, $privPem);
        if (!$ok) return new WP_Error('openssl_export_failed', 'OpenSSL: не вдалося експортувати приватний ключ PEM');

        // 2) Build JWKs via SimpleJWT library from PEM (bundled)
        require_once ALB_HPP_DIR.'lib/simplejwt/init.php';
        try {
            $ecKey = new \SimpleJWT\Keys\ECKey($privPem, 'pem');
            // Private JWK (contains "d")
            $privJwkArr = $ecKey->getKeyData();
            // ensure expected curve
            $privJwkArr['kty'] = 'EC';
            $privJwkArr['crv'] = 'P-384';
			$privJwkArr['alg'] = 'ECDH-ES+A256KW';
            $privJwkArr['use'] = 'enc';
            // Remove kid if any
            unset($privJwkArr['kid']);
            // Optional: Do not include alg/use in key object; bank said kid not used. Keeping clean.

            // Public JWK (no "d")
            $pubJwkArr = $privJwkArr;
            unset($pubJwkArr['d']);

            // Save files
            $pub_json  = wp_json_encode($pubJwkArr, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
            $priv_json = wp_json_encode($privJwkArr, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
            if (!$pub_json || !$priv_json) return new WP_Error('json_failed', 'Не вдалося серіалізувати JWK у JSON');

            if (@file_put_contents($dir.'/private.pem', $privPem) === false) return new WP_Error('write_failed', 'Не вдалося записати private.pem');
            if (@file_put_contents($dir.'/public_jwk.json', $pub_json) === false) return new WP_Error('write_failed', 'Не вдалося записати public_jwk.json');
            if (@file_put_contents($dir.'/private_jwk.json', $priv_json) === false) return new WP_Error('write_failed', 'Не вдалося записати private_jwk.json');

            @chmod($dir.'/private.pem', 0600);
            @chmod($dir.'/private_jwk.json', 0600);
            @chmod($dir.'/public_jwk.json', 0644);

            

            return true;
        } catch (\Throwable $e) {
            return new WP_Error('gen_exception', 'Помилка SimpleJWT: '.$e->getMessage());
        }
    }

    protected static function handle_save_private_to_options() {
        $dir = self::get_dir();
        $priv_file = $dir . '/private_jwk.json';
        if (!file_exists($priv_file)) return new WP_Error('no_private', 'Файл private_jwk.json не знайдено. Спершу згенеруйте ключі.');
        $priv = file_get_contents($priv_file);
        if ($priv===false || $priv==='') return new WP_Error('empty_private', 'Не вдалося прочитати private_jwk.json');

        // Validate JSON minimally
        $data = json_decode($priv, true);
        if (!is_array($data) || !isset($data['kty'],$data['crv'],$data['d'],$data['x'],$data['y'])) {
            return new WP_Error('invalid_json', 'Некоректний приватний JWK (очікуємо kty, crv, d, x, y)');
        }
        if ($data['kty']!=='EC' || $data['crv']!=='P-384') {
            return new WP_Error('invalid_curve', 'Очікується EC P-384');
        }

        // Save into plugin settings: field 'privateJwk'
        $opt = get_option(ALB_HPP_OPT, []);
        $opt['privateJwk'] = wp_json_encode($data, JSON_UNESCAPED_SLASHES);
        // На вимогу: очистити Merchant ID та Service Code під нові дані від банку
        $opt['merchantId'] = '';
        $opt['serviceCode'] = '';
        // Нормалізуємо paymentMethods та зберігаємо як CSV-рядок (щоб не було "Array" у полі)
        $pm = $opt['paymentMethods'] ?? ['CARD','APPLE_PAY','GOOGLE_PAY'];
        if (is_string($pm)) {
            if (preg_match('/^\s*Array\s*$/i', $pm)) {
                $pm_list = ['CARD','APPLE_PAY','GOOGLE_PAY'];
            } else {
                $pm_list = array_filter(array_map('trim', explode(',', $pm)));
            }
        } elseif (is_array($pm)) {
            // сплющити на 1 рівень про всяк випадок
            $flat = [];
            foreach ($pm as $v) {
                if (is_array($v)) { foreach ($v as $vv) $flat[] = trim((string)$vv); }
                else $flat[] = trim((string)$v);
            }
            $pm_list = array_filter($flat);
        } else {
            $pm_list = ['CARD','APPLE_PAY','GOOGLE_PAY'];
        }
        $opt['paymentMethods'] = implode(',', $pm_list); // записуємо саме CSV-рядок
        update_option(ALB_HPP_OPT, $opt);

        return true;
    }
}
ALB_Keys_Admin::init();
