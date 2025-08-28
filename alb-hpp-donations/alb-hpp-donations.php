<?php
/**
 * Plugin Name: ALB HPP Donations
 * Description: Шорткод [alb_donate] для благодійних внесків через Hosted Payment Page Альянс Банку.
 * Version: 1.2.0
 * Author: SP for <a href="https://clusters.org.ua" target="_blank">UCA</a>
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

define('ALB_HPP_DIR', plugin_dir_path(__FILE__));

// Environment base URLs
//if (!defined('ALB_HPP_TEST_BASE')) define('ALB_HPP_TEST_BASE', 'https://api-ecom-release.develop.bankalliance.ua');
if (!defined('ALB_HPP_TEST_BASE')) define('ALB_HPP_TEST_BASE', 'https://api-ecom-prod.bankalliance.ua');
if (!defined('ALB_HPP_PROD_BASE')) define('ALB_HPP_PROD_BASE', 'https://api-ecom-prod.bankalliance.ua');
if (!defined('ALB_HPP_TEST_JWE')) define('ALB_HPP_TEST_JWE', 'https://api-ecom-release.develop.bankalliance.ua');

define('ALB_HPP_URL', plugin_dir_url(__FILE__));
define('ALB_HPP_OPT', 'alb_hpp_options');

// ===== Підключення класів =====
require_once ALB_HPP_DIR.'includes/class-alb-payments.php';
require_once ALB_HPP_DIR.'includes/class-alb-hpp-client.php';
require_once ALB_HPP_DIR.'includes/class-alb-admin.php';
require_once ALB_HPP_DIR.'includes/class-alb-keys.php';
require_once ALB_HPP_DIR.'includes/shortcode-donate.php';
require_once ALB_HPP_DIR.'includes/class-alb-authorize.php';

// ===== Автогенерація Success/Fail сторінок + створення таблиці платежів =====
register_activation_hook(__FILE__, function () {
    $opt = get_option(ALB_HPP_OPT, []);

    // хелпер створення сторінки (або повернення існуючої з метаданими)
    $ensure_page = function(string $title, string $slug, string $meta_key, string $content) {
        $existing = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => 1,
            'meta_key'       => $meta_key,
            'meta_value'     => '1',
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);
        if ($existing) return (int)$existing[0];

        // якщо користувач уже має сторінку з таким слагом
        $by_slug = get_page_by_path($slug, OBJECT, 'page');
        if ($by_slug && $by_slug->post_status === 'publish') {
            $pid = $by_slug->ID;
        } else {
            $pid = wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $content,
            ]);
        }
        if (!is_wp_error($pid) && $pid) {
            update_post_meta($pid, $meta_key, '1');
            return (int)$pid;
        }
        return 0;
    };

    $success_id = $ensure_page(
        'Платіж успішний',
        'donation-success',
        '_alb_hpp_success_page',
        "<h1>Дякуємо за підтримку!</h1>\n<p>Статус вашого платежу: <strong>успішно</strong>.</p>\n<p><a href='".esc_url(home_url('/'))."'>Повернутися на головну</a></p>"
    );
    $fail_id = $ensure_page(
        'Платіж не виконано',
        'donation-fail',
        '_alb_hpp_fail_page',
        "<h1>На жаль, платіж не виконано</h1>\n<p>Будь ласка, спробуйте ще раз або зв’яжіться з нами.</p>\n<p><a href='".esc_url(home_url('/'))."'>Повернутися на головну</a></p>"
    );

    if ($success_id) $opt['successUrl'] = get_permalink($success_id);
    if ($fail_id)    $opt['failUrl']    = get_permalink($fail_id);

    if (empty($opt['notificationUrl'])) {
        $opt['notificationUrl'] = home_url('/wp-json/alb/v1/notify');
    }
    $opt['baseUrl']     = $opt['baseUrl']     ?? ALB_HPP_PROD_BASE;
    $opt['apiVersion']  = $opt['apiVersion']  ?? 'v1';
    $opt['language']    = $opt['language']    ?? 'uk';
    if (empty($opt['paymentMethods'])) {
        $opt['paymentMethods'] = 'CARD,APPLE_PAY,GOOGLE_PAY';
    }

    // створити таблицю платежів
    if (class_exists('ALB_Payments')) {
        ALB_Payments::create_table();
    }

    update_option(ALB_HPP_OPT, $opt);
});


// Load plugin textdomain for translations
function alb_hpp_donations_load_textdomain() {
    load_plugin_textdomain('alb-hpp-donations', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'alb_hpp_donations_load_textdomain');


// Detect current site language ('uk' or 'en') using Polylang/WPML if present, else WP locale
function alb_hpp_detect_language(): string {
    // 1) Запит може явно передати ?lang=uk|en
    $req_lang = isset($_REQUEST['lang']) ? strtolower(sanitize_text_field((string)$_REQUEST['lang'])) : '';
    if (in_array($req_lang, ['uk','en'], true)) {
        return $req_lang;
    }
    // 2) Polylang
    if (function_exists('pll_current_language')) {
        $slug = strtolower((string) pll_current_language('slug'));
        if (in_array($slug, ['uk','en'], true)) return $slug;
    }
    // 3) WPML
    if (defined('ICL_LANGUAGE_CODE')) {
        $slug = strtolower((string) ICL_LANGUAGE_CODE);
        if (in_array($slug, ['uk','en'], true)) return $slug;
    }
    // 4) WP core
    $loc = get_locale(); // типово 'uk_UA' або 'en_US'
    if (is_string($loc) && strlen($loc) >= 2) {
        $two = strtolower(substr($loc, 0, 2));
        if (in_array($two, ['uk','en'], true)) return $two;
    }
    return 'uk';
}


// ===== REST маршрути =====
add_action('rest_api_init', function () {
    register_rest_route('alb/v1', '/create-order', [
        'methods'  => 'POST',
        'callback' => 'alb_hpp_rest_create_order',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('alb/v1', '/notify', [
        'methods'  => 'POST',
        'callback' => 'alb_hpp_rest_notify',
        'permission_callback' => '__return_true',
    ]);
});

// ===== REST: створення HPP-замовлення =====
function alb_hpp_rest_create_order(\WP_REST_Request $req) {
    $opt = get_option(ALB_HPP_OPT, []);

    $opt['baseUrl']    = $opt['baseUrl']    ?? ALB_HPP_PROD_BASE;
    $opt['apiVersion'] = $opt['apiVersion'] ?? 'v1';
    $opt['language'] = alb_hpp_detect_language();

    if (!empty($opt['paymentMethods']) && is_string($opt['paymentMethods'])) {
        $opt['paymentMethods'] = array_filter(array_map('trim', explode(',', $opt['paymentMethods'])));
    } elseif (empty($opt['paymentMethods'])) {
        $opt['paymentMethods'] = ['CARD','APPLE_PAY','GOOGLE_PAY'];
    }



    try {
        $client = new \ALB\AlbHppClient($opt);

        $amount = max(10, (int)$req->get_param('amount'));
        $coinAmount = $amount * 100;

        $purpose = trim((string)$req->get_param('purpose'));
        if (!$purpose) $purpose = 'Благодійний внесок';

        $email = trim((string)$req->get_param('email'));
        $first = trim((string)$req->get_param('firstName'));
        $last  = trim((string)$req->get_param('lastName'));

        $merchantRequestId = wp_generate_uuid4();

        $params = [
            'coinAmount'       => $coinAmount,
            'paymentMethods'   => $opt['paymentMethods'],
            'language'         => $opt['language'],
            'successUrl'       => $opt['successUrl'] ?? home_url('/'),
            'failUrl'          => $opt['failUrl']    ?? home_url('/'),
            'notificationUrl'  => $opt['notificationUrl'] ?? (home_url('/wp-json/alb/v1/notify')),
            'merchantRequestId'=> $merchantRequestId,
            'purpose'          => $purpose,
            'customerData'     => [
                'senderCustomerId' => substr(hash('sha256', $email ?: ($first.$last ?: 'anon')),0,32),
                'senderFirstName'  => $first ?: null,
                'senderLastName'   => $last  ?: null,
                'senderEmail'      => $email ?: null,
            ],
        ];

        $res = $client->create_hpp_order($params);

        // Запис у таблицю як CREATED (hppOrderId може бути у відповіді; якщо ні — прийде у callback)
        if (class_exists('ALB_Payments')) {
            ALB_Payments::insert_created([
                'merchantRequestId' => $merchantRequestId,
                'hppOrderId'        => $res['hppOrderId'] ?? null,
                'coinAmount'        => $coinAmount,
                'purpose'           => $purpose,
                'customerEmail'     => $email,
                'firstName'         => $first,
                'lastName'          => $last,
            ]);
        }

        if (!empty($res['redirectUrl'])) {
            return new \WP_REST_Response(['ok' => true, 'redirectUrl' => $res['redirectUrl']], 200);
        }
        return new \WP_REST_Response(['ok' => false, 'error' => 'No redirectUrl in response'], 500);

    } catch (\Throwable $e) {
        return new \WP_REST_Response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ===== REST: callback від банку =====
function alb_hpp_rest_notify(\WP_REST_Request $req) {
    $raw = $req->get_body();

    // Лог для відладки
	if (defined('ALB_HPP_DEBUG') && ALB_HPP_DEBUG) {
		$dir = WP_CONTENT_DIR.'/uploads/alb-logs';
		if (!is_dir($dir)) wp_mkdir_p($dir);
		@file_put_contents($dir.'/callback.log', date('c').' '.$raw.PHP_EOL, FILE_APPEND);
    }
	
    $payload = json_decode($raw, true) ?: [];
    if (!isset($payload['orderStatus'], $payload['hppOrderId'])) {
        return new \WP_REST_Response(['ok'=>false, 'msg'=>'Bad payload'], 400);
    }

    // Оновити/вставити запис у таблиці платежів
    if (class_exists('ALB_Payments')) {
        ALB_Payments::upsert_from_callback($payload);
    }

    return new \WP_REST_Response(['ok'=>true], 200);
}

// ===== Статичні ресурси форми =====
add_action('wp_enqueue_scripts', function () {
    wp_register_style('alb-donate', ALB_HPP_URL.'assets/donate.css', [], '1.0.0');
    wp_register_script('alb-donate', ALB_HPP_URL.'assets/donate.js', ['jquery'], '1.0.0', true);
    wp_localize_script('alb-donate', 'ALB_HPP', [
        'endpoint' => esc_url_raw(rest_url('alb/v1/create-order')),
    ]);
});




// ===== REST: ручна синхронізація статусів =====
add_action('rest_api_init', function(){
    register_rest_route('alb/v1', '/sync-order', [
        'methods'  => 'POST',
        'callback' => function(\WP_REST_Request $r){
            if (!current_user_can('manage_options')) return new \WP_REST_Response(['ok'=>false,'error'=>'forbidden'], 403);
            $j = $r->get_json_params();
            $row_id = (int)($j['id'] ?? 0);
            global $wpdb; $t = $wpdb->prefix.'alb_hpp_payments';
            $row = $row_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $row_id), ARRAY_A) : null;
            if (!$row) return ['ok'=>false,'error'=>'not_found'];
            $args = [];
            if (!empty($row['hpp_order_id'])) $args['hppOrderId'] = $row['hpp_order_id'];
            elseif (!empty($row['merchant_request_id'])) $args['merchantRequestId'] = $row['merchant_request_id'];
            else return ['ok'=>false,'error'=>'no_ids'];

            try {
                $client = new \ALB\AlbHppClient(get_option(ALB_HPP_OPT, []));
                $data = $client->get_order($args['hppOrderId'] ?? $args['merchantRequestId']);
            } catch (\Throwable $e) {
                // деякі API приймають лише один ключ; спробуємо універсальну operations
                try {
                    $data = \ALB\AlbHppClient::operations($args); // якщо додано статичний, інакше fallback нижче
                } catch (\Throwable $e2) {
                    return ['ok'=>false,'error'=>$e->getMessage()];
                }
            }
            // Мапінг і запис
            $mapped = alb_hpp_map_status_payload($data);
            alb_hpp_store_status_update((int)$row['id'], $mapped, $data);
            return ['ok'=>true,'status'=>$mapped['status']??null];
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('alb/v1', '/sync-pending', [
        'methods'  => 'POST',
        'callback' => function(\WP_REST_Request $r){
            if (!current_user_can('manage_options')) return new \WP_REST_Response(['ok'=>false,'error'=>'forbidden'], 403);
            $limit = (int)($r->get_param('limit') ?? 30);
            $cnt = alb_hpp_sync_pending($limit);
            return ['ok'=>true,'synced'=>$cnt];
        },
        'permission_callback' => '__return_true',
    ]);
});

/** Маппер полів відповіді банку → наші колонки */
if (!function_exists('alb_hpp_map_status_payload')) {
function alb_hpp_map_status_payload(array $data): array {
    $status = $data['status'] ?? ($data['operationStatus'] ?? ($data['orderStatus'] ?? null));
    $method = $data['paymentMethod'] ?? ($data['method'] ?? null);
    $amt    = $data['coinAmount'] ?? ($data['amount'] ?? null);
    $approvedAt = $data['approvedAt'] ?? ($data['finishedAt'] ?? null);
    $statusUrl  = $data['statusUrl'] ?? null;
    return array_filter([
        'status'      => $status,
        'amount_coin' => ($amt!==null ? (int)$amt : null),
        'method'      => $method,
        'operations'  => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
    ], function($v){ return $v!==null && $v!==''; });
}}

/** Записати оновлення статусу */
if (!function_exists('alb_hpp_store_status_update')) {
function alb_hpp_store_status_update(int $id, array $mapped, array $raw): void {
    global $wpdb; $t = $wpdb->prefix.'alb_hpp_payments';
    $mapped['updated_at'] = current_time('mysql');
    if (!isset($mapped['operations'])) $mapped['operations'] = wp_json_encode($raw, JSON_UNESCAPED_UNICODE);
    $wpdb->update($t, $mapped, ['id'=>$id]);
}}

/** Вибрати та синхронізувати незакриті платежі */
if (!function_exists('alb_hpp_sync_pending')) {
function alb_hpp_sync_pending(int $limit = 1): int {
    global $wpdb; $t = $wpdb->prefix.'alb_hpp_payments';
    $open = ['CREATED','REDIRECTED','PENDING','IN_PROGRESS', NULL, ''];
    $in = "'".implode("','", array_map('esc_sql', array_filter($open, 'strlen')))."'";
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t}
         WHERE (status IN ({$in}) OR status IS NULL OR status='')
         ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    $ok=0;
    $client = new \ALB\AlbHppClient(get_option(ALB_HPP_OPT, []));
    foreach ($rows as $row) {
        try {
            $argsId = $row['hpp_order_id'] ?: $row['merchant_request_id'];
            if (!$argsId) continue;
            $data = $client->get_order($argsId);
            $mapped = alb_hpp_map_status_payload($data);
            alb_hpp_store_status_update((int)$row['id'], $mapped, $data);
            $ok++;
        } catch (\Throwable $e) {
            if (defined('ALB_HPP_DEBUG') && ALB_HPP_DEBUG) {
                $dir = WP_CONTENT_DIR.'/uploads/alb-logs'; if (!is_dir($dir)) @wp_mkdir_p($dir);
                @file_put_contents($dir.'/debug.log', date('c')." SYNC ERR id=".$row['id'].": ".$e->getMessage().PHP_EOL, FILE_APPEND);
            }
        }
    }
    return $ok;
}}




// === Adding WP-Cron

// === Hourly Token Guard: перевіряє строк дії токена і оновлює, якщо залишилось ≤ 12 год ===
// Міграція: один раз прибрати стару подію, якщо вона була
add_action('init', function () {
    if (!get_option('alb_hpp_hourly_migrated')) {
        wp_clear_scheduled_hook('alb_hpp_reauth_cron'); // старе ім'я події (twicedaily)
        update_option('alb_hpp_hourly_migrated', 1, true);
    }
});

// Планування на активації
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('alb_hpp_token_guard_cron')) {
        // перший запуск через 2 хв, далі — щогодини
        wp_schedule_event(time() + 120, 'hourly', 'alb_hpp_token_guard_cron');
    }
});

// Очищення на деактивації
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('alb_hpp_token_guard_cron');
});

// Failsafe: якщо подія зникла — створюємо знову
add_action('init', function () {
    if (!wp_next_scheduled('alb_hpp_token_guard_cron')) {
        wp_schedule_event(time() + 120, 'hourly', 'alb_hpp_token_guard_cron');
    }
});

// Обробник події (щогодини)
add_action('alb_hpp_token_guard_cron', function () {
    // простий анти-дубль-замок (на випадок багатьох воркерів/одночасних хітів)
    if (get_transient('alb_hpp_reauth_lock')) {
        return;
    }
    set_transient('alb_hpp_reauth_lock', 1, 5 * MINUTE_IN_SECONDS);
    try {
        $opt = get_option(ALB_HPP_OPT, []);
        // 1) дістаємо момент закінчення дії токена (epoch)
        $exp = 0;
        if (!empty($opt['alb_token_expires_at'])) {
            $exp = (int) $opt['alb_token_expires_at'];
        } elseif (!empty($opt['tokenExpiration'])) {
            // fallback: парсимо сирий рядок, якщо ще не збережений epoch
            if (function_exists('parse_token_expiration')) {
                $exp = (int) parse_token_expiration($opt['tokenExpiration']);
            } else {
                try {
                    $exp = (new \DateTimeImmutable($opt['tokenExpiration']))->getTimestamp();
                } catch (\Throwable $e) {
                    $exp = 0;
                }
            }
        }
        $now = time();
        $rem = $exp ? ($exp - $now) : -1;
        // 2) якщо немає токена / протермінувався / залишилось ≤ 12 год — оновлюємо
        if ($exp <= 0 || $rem <= 12 * HOUR_IN_SECONDS) {
            try {
                // очікується, що цей метод сам оновить option (deviceId/refreshToken/issued_at/expires_at)
                ALB_Authorize::reauthorize_device($opt);
            } catch (\Throwable $e) {
                error_log('[ALB HPP] reauthorize_device failed: ' . $e->getMessage());
            }
        }
    } finally {
        // звільняємо замок одразу, щоб можна було запускати вручну з адмінки/CLI
        delete_transient('alb_hpp_reauth_lock');
    }
});



// ===== WP‑Cron кожні 10 хв для синхронізації платежів =====
add_filter('cron_schedules', function($s){ $s['ten_minutes']=['interval'=>600,'display'=>'Every 10 minutes']; return $s; });

add_action('init', function () {
    if (!wp_next_scheduled('alb_hpp_sync_cron')) {
        // перший запуск через 60с, далі — кожні 10 хв
        wp_schedule_event(time() + 60, 'ten_minutes', 'alb_hpp_sync_cron');
    }
});


register_activation_hook(__FILE__, function(){
    if (!wp_next_scheduled('alb_hpp_sync_cron')) {
        wp_schedule_event(time()+300, 'ten_minutes', 'alb_hpp_sync_cron');
    }
});

register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook('alb_hpp_sync_cron');
});

add_action('alb_hpp_sync_cron', function(){ alb_hpp_sync_pending(2); });



// ===== REST: Переавторизувати зараз =====
function alb_hpp_rest_reauthorize_now( WP_REST_Request $r ){
    if ( ! current_user_can('manage_options') ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'forbidden'], 403);
    }
    $opt = get_option(ALB_HPP_OPT, []);
    try {
        ALB_Authorize::reauthorize_device($opt);
        $opt = get_option(ALB_HPP_OPT, []);
        $exp = (int)($opt['alb_token_expires_at'] ?? 0);
        return ['ok'=>true,'expiresAt'=>$exp];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}
add_action('rest_api_init', function(){
    register_rest_route('alb/v1', '/reauthorize-now', [
        'methods'  => 'POST',
        'callback' => 'alb_hpp_rest_reauthorize_now',
        'permission_callback' => '__return_true',
    ]);
});


// popup повідомлення, після редіректу
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $n = get_transient('alb_hpp_notice');
    if (!$n) return;
    delete_transient('alb_hpp_notice');
    echo '<div class="notice notice-' . esc_attr($n['type']) . ' is-dismissible"><p>' . esc_html($n['text']) . '</p></div>';
});

// Обробник POST: Видалення всіх платежів
add_action('admin_post_alb_hpp_delete_all_payments', function () {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'alb-hpp'));
    }
    check_admin_referer('alb_hpp_delete_all_payments');
    global $wpdb;
    $table = $wpdb->prefix . 'alb_hpp_payments';
    // Порахуємо скільки рядків буде видалено (для повідомлення)
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    // Видаляємо всі записи (DELETE сумісніший за TRUNCATE на хостингах)
    $wpdb->query("DELETE FROM {$table}");
    // За бажанням: скинути автоінкремент (не обов’язково)
    $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    set_transient('alb_hpp_notice', [
        'type' => 'success',
        'text' => sprintf('Видалено %d платежів.', $count),
    ], 60);
    // Повернемося на сторінку "Історія платежів"
    $back = wp_get_referer();
    if (!$back) {
        // замініть slug, якщо інший
        $back = admin_url('admin.php?page=alb-hpp-payments');
    }
    wp_safe_redirect($back);
    exit;
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=alb-hpp-manual')) . '">Документація</a>';
    // за бажанням: лінк на налаштування
     $links[] = '<a href="' . esc_url(admin_url('admin.php?page=alb-hpp')) . '">Налаштування</a>';
    return $links;
});


// Блокувати доступ до адмін-частини плагіна для не-адмінів
add_action('admin_init', function () {
    if (is_admin() && ! current_user_can('manage_options')) {
        wp_die(__('Вам заборонено переглядати цю сторінку.'));
    }
});

