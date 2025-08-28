<?php
if (!defined('ABSPATH')) exit;

class ALB_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'settings']);
    }

    public static function menu() {
        // 1) Топ-левел меню (іконку можна змінити)
        add_menu_page(
            'ALB HPP Donations',          // page_title
            'ALB Donations',              // menu_title (те, що видно в меню)
            'manage_options',             // capability
            'alb-hpp',                    // menu slug (головна сторінка = Налаштування)
            [__CLASS__, 'render_page'],   // callback
            'dashicons-heart',            // іконка (або data:image/svg+xml;base64,...)
            56                             // позиція (за бажанням)
        );

        // 2) Сабменю: Налаштування (дублює головну, щоб було два пункти)
        add_submenu_page(
            'alb-hpp',                    // parent slug
            'Налаштування',               // page_title
            'Налаштування',               // menu_title
            'manage_options',             // capability
            'alb-hpp',                    // same slug -> показує render_page()
            [__CLASS__, 'render_page']    // callback
        );

        // 3) Сабменю: Історія платежів
        add_submenu_page(
            'alb-hpp',
            'Історія платежів',
            'Історія платежів',
            'manage_options',
            'alb-hpp-payments',
            [__CLASS__, 'render_payments']
        );
		
		// 4) Сабменю: Мануал
		add_submenu_page(
			'alb-hpp',
			'Документація', 
			'Документація',
			'manage_options',
			'alb-hpp-manual',
			function () {
				$manual_url = plugins_url('../docs/manual.html', __FILE__);
				echo '<div class="wrap"><h1>ALB Donations Plugin — Документація</h1>';
				echo '<iframe src="' . esc_url($manual_url) . '" style="width:100%;height:80vh;border:1px solid #ccd0d4;border-radius:8px;background:#fff"></iframe>';
				echo '</div>';
			}
	    );

}

    public static function settings() {
		register_setting('alb-hpp', ALB_HPP_OPT, [
		  'capability' => 'manage_options',
		  'sanitize_callback' => function ($input) {
			$saved = get_option(ALB_HPP_OPT, []);
			$input = is_array($input) ? $input : [];
			foreach (['baseUrl','serviceCode','merchantId','successUrl','failUrl','notificationUrl'] as $k) {
				if (isset($input[$k])) $input[$k] = trim((string)$input[$k]);
			}
			if (isset($input['paymentMethods'])) {
				$pm = array_filter(array_map('trim', explode(',', (string)$input['paymentMethods'])));
				$input['paymentMethods'] = $pm;
			}
			// Private JWK — лишаємо як JSON-рядок (НЕ перетворюємо у масив тут)
			if (isset($input['privateJwk'])) {
				$input['privateJwk'] = trim((string)$input['privateJwk']);
			}
			// API версія та мова — статичні
			$input['apiVersion'] = 'v1';
			$input['language']   = 'uk';
			// Режим декрипту: у Продакшн лише SimpleJWT
			$env  = $saved['environment'] ?? ($input['environment'] ?? 'prod');
			$mode = $input['mode'] ?? ($saved['mode'] ?? 'simplejwt');
			if ($env === 'prod') $mode = 'simplejwt';
			$input['mode'] = $mode;
			// ОХОРОНА службових ключів авторизації: якщо їх немає у формі, беремо зі збережених (щоб не «зникли»)
			foreach (['deviceId','refreshToken','alb_token_issued_at','alb_token_expires_at'] as $k) {
				if (!array_key_exists($k, $input) && array_key_exists($k, $saved)) {
					$input[$k] = $saved[$k];
				}
			}
			// Повертаємо МЕРДЖ: нові значення поверх старих
			return array_merge($saved, $input);
		  }
		]);


        add_settings_section('alb_hpp_main', 'Основні налаштування', '__return_false', 'alb-hpp');
        // Environment switch

        add_settings_field('environment', 'Режим роботи', function() {
            $opt = get_option(ALB_HPP_OPT, []);
            $env = $opt['environment'] ?? 'prod';
            ?>
            <label><input type="radio" name="<?php echo ALB_HPP_OPT; ?>[environment]" value="test" <?php checked($env, 'test'); ?> /> Тестовий</label><br>
            <label><input type="radio" name="<?php echo ALB_HPP_OPT; ?>[environment]" value="prod" <?php checked($env, 'prod'); ?> /> Продакшн</label>
            <p class="description">Визначає базовий API-хост та доступні методи авторизації.</p>
            <?php
        }, 'alb-hpp', 'alb_hpp_main');
          

        $fields = [
            'baseUrl'         => 'Base URL API *',
			'merchantId'      => 'Merchant ID (надає банк) *',
            'serviceCode'     => 'Service Code (надає банк) *',
            'privateJwk'      => 'Private JWK *',
            'deviceId'        => 'Device ID',
            'refreshToken'    => 'Refresh Token',
            'paymentMethods'  => 'Методи оплати *',
            'successUrl'      => 'Success URL',
            'failUrl'         => 'Fail URL',
            'notificationUrl' => 'Notification URL',
        ];


        
foreach ($fields as $key=>$label) {
    add_settings_field($key, $label, function() use ($key) {
        $opt = get_option(ALB_HPP_OPT, []);
        $val_raw = $opt[$key] ?? '';
        if ($key === 'privateJwk') {
            $val = esc_textarea(is_array($val_raw) ? wp_json_encode($val_raw) : $val_raw);
            echo '<textarea style="width:500px;height:140px" name="'.ALB_HPP_OPT.'['.$key.']" placeholder=\'{"kty":"EC","crv":"P-384","d":"...","x":"...","y":"...","alg":"ECDH-ES+A256KW","use":"enc"}\'>'.$val.'</textarea>';
            echo '<p class="description">Приватний ключ для розшифрування JWE. Не публічний.</p>';
        } elseif ($key === 'deviceId' || $key === 'refreshToken') {
            $val = esc_attr(is_array($val_raw) ? implode(',', $val_raw) : $val_raw);
            echo '<input type="text" style="width:460px" value="'.$val.'" readonly disabled />';
            echo '<p class="description">Інформаційне поле. Оновлюється після авторизації.</p>';			
        } else {
            $val = esc_attr(is_array($val_raw) ? implode(',', $val_raw) : $val_raw);
            echo '<input type="text" style="width:460px" name="'.ALB_HPP_OPT.'['.$key.']" value="'.$val.'" />';
			if ($key === 'paymentMethods') echo '<p class="description">(CSV: CARD,APPLE_PAY,GOOGLE_PAY)</p>';
			if ($key === 'notificationUrl') echo '<p class="description">(REST: /wp-json/alb/v1/notify)</p>';
			if ($key === 'successUrl' || $key === 'failUrl') echo '<p class="description">Сторінка повернення користувачу після оплати</p>';
			
			
        }
    }, 'alb-hpp', 'alb_hpp_main');
}

        // Режим декрипту (SimpleJWT / Remote). У Продакшн доступний лише SimpleJWT
        add_settings_field('mode','Режим декрипту', function(){
            $opt = get_option(ALB_HPP_OPT, []);
            $env = $opt['environment'] ?? 'prod';
            $mode = $opt['mode'] ?? 'simplejwt';
            if ($mode === 'local') $mode = 'simplejwt';
            echo '<label><input type="radio" name="'.ALB_HPP_OPT.'[mode]" value="simplejwt" '.checked($mode,'simplejwt',false).' /> Local decrypt (SimpleJWT)</label><br>';
            if ($env === 'test') {
                echo '<label><input type="radio" name="'.ALB_HPP_OPT.'[mode]" value="remote" '.checked($mode,'remote',false).' /> Remote decrypt (тільки тестове середовище)</label>';
            } else {
                echo '<span class="description">Remote decrypt недоступний у Продакшн</span>';
                echo '<input type="hidden" name="'.ALB_HPP_OPT.'[mode]" value="simplejwt" />';
            }
        }, 'alb-hpp', 'alb_hpp_main');
				
    }

    public static function render_page() {
	    if ( ! current_user_can('manage_options') ) {
            wp_die(__('Вам заборонено переглядати цю сторінку.'));
        }
        $opt = get_option(ALB_HPP_OPT, []);
        $env = $opt['environment'] ?? 'prod';
        $recommended = ($env === 'test') ? ALB_HPP_TEST_BASE : ALB_HPP_PROD_BASE;
        if (empty($opt['baseUrl'])) { $opt['baseUrl'] = $recommended; update_option(ALB_HPP_OPT, $opt); }
        $opt = get_option(ALB_HPP_OPT, []);
        if (empty($opt['notificationUrl'])) {
            $opt['notificationUrl'] = home_url('/wp-json/alb/v1/notify');
            update_option(ALB_HPP_OPT, $opt);
        }
		
        echo '<div class="wrap"><h1>ALB Donations — Налаштування</h1>';
        // Інфо-блок зі статусом і кнопкою переавторизації
        $mode = $opt['mode'] ?? 'simplejwt';
        if ($mode === 'local') $mode = 'simplejwt';
        $badge_color = ($mode === 'simplejwt') ? '#16a34a' : '#2563eb';
        $badge_text  = ($mode === 'simplejwt') ? 'Local decrypt' : 'Remote decrypt';
        if ($env === 'prod' && $mode === 'remote') { $badge_color = '#dc2626'; $badge_text = 'Remote decrypt (заборонено)'; }
        $exp   = (int)($opt['alb_token_expires_at'] ?? 0);
        $exp_hours  = $exp ? floor(($exp - time()) / HOUR_IN_SECONDS) : null;
		
        $exp_print = $exp ? esc_html( wp_date('Y-m-d H:i:s', $exp, wp_timezone()) ) : '—';		
		
        $env_color = ($env === 'test') ? '#F5B027' : '#16a34a';
        echo '<div class="alb-info" style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff">';
        echo '<p style="margin:0 0 8px 0"><strong>Середовище: </strong> 
		      <span style="display:inline-block;padding:2px 8px;border-radius:9999px;color:#fff;background:'.$env_color.'">
		      '.(($env==='test')?'Тестовий':'Продакшн').'</span> &nbsp; ';
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:9999px;color:#fff;background:'.$badge_color.'">'.$badge_text.'</span></p>';
        echo '<p style="margin:4px 0 0 0"><strong>Токен дійсний до:</strong> '.$exp_print;
        echo " <i style=\"color:#6b7280\">(Автооновлення кожні 12 годин, ";
		if ($exp) { $left_color = ($exp_hours<=2)?'#dc2626':(($exp_hours<=4)?'#d97706':'#6b7280'); echo ' <span style="color:'.$left_color.'"> залишилось ~'.(int)$exp_hours.' год.</span>'; }
        echo ')</i>';
		echo '</p>';
        echo '<p style="margin:10px 0 0 0"><button type="button" class="button button-primary" id="alb-reauthorize-now">Переавторизувати зараз</button> <span id="alb-reauth-msg" style="margin-left:8px;color:#6b7280"></span></p>';
        echo '</div>';
?>
<script>
 document.addEventListener('DOMContentLoaded', function(){
          const btn = document.getElementById('alb-reauthorize-now');
          if (!btn) return;
          const msg = document.getElementById('alb-reauth-msg');
          const url = '<?php echo esc_js( rest_url('alb/v1/reauthorize-now') ); ?>';
          const nonce = '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>';
          btn.addEventListener('click', async ()=>{
            btn.disabled = true; const old = btn.textContent; btn.textContent = 'Переавторизація...';
            msg.textContent = '';
            try {
              const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}});
              const j = await r.json();
              if (j && j.ok) { msg.style.color = '#16a34a'; msg.textContent = 'Готово'; setTimeout(()=>location.reload(), 800); }
              else { msg.style.color = '#dc2626'; msg.textContent = (j && j.error) ? j.error : 'Помилка'; }
            } catch(e) { msg.style.color = '#dc2626'; msg.textContent = e.message || 'Помилка мережі'; }
            finally { btn.disabled = false; btn.textContent = old; }
          });
 });
</script>
<?php

        echo '<form method="post" action="options.php">';
		
		settings_fields('alb-hpp');
        do_settings_sections('alb-hpp');
        submit_button();
        $payments_url = esc_url(admin_url('admin.php?page=alb-hpp-payments'));
        echo '<p>Перегляд платежів: <a href="'.$payments_url.'">Історія платежів</a></p>';
	
	    echo '<p><strong>Як користуватись:</strong> створіть сторінку «Підтримати донатом» та додайте шорткод <code>[alb_donate]</code>. ';
        echo 'Переконайтесь, що <code>Merchant ID</code>, <code>Service Code</code> та <code>Private JWK</code> заповнені.<br> ';
		echo 'При першому підключенні натисність "Переавторизувати зараз", перевірте що поля <code>Device ID</code> та <code>Refresh Token</code> автоматично заповнились.<br>';
        echo 'Налаштуйте сторінки <code>Success URL</code> та <code>Fail URL</code> для перенаправлення після успішної (або ні) оплати з сайту банку.<br>';
		echo 'Поле <code>Notification URL</code> повинно бути у вигляді: [SITEURL]/wp-json/alb/v1/notify (Заповнюється автоматично — не змінюйте без потреби).';
		echo '</p>';
		
        echo '</form></div>';
?>
<script>
 jQuery(function($){
  const TEST_URL = '<?=ALB_HPP_TEST_BASE?>';
  const PROD_URL = '<?=ALB_HPP_PROD_BASE?>';
  $('input[name="alb_hpp_options[environment]"]').on('change', function(){
    if ($(this).val() === 'test') {
      $('input[name="alb_hpp_options[baseUrl]"]').val(TEST_URL);
    } else {
      $('input[name="alb_hpp_options[baseUrl]"]').val(PROD_URL);
    }
  });
 });
</script>
<?php

    }

    public static function render_payments() {
        if (!class_exists('ALB_Payments')) {
            echo '<div class="wrap"><h1>Історія платежів</h1><p>Клас ALB_Payments не знайдено.</p></div>';
            return;
        }
		$opt = get_option(ALB_HPP_OPT, []);
		
        $page = max(1, (int)($_GET['paged'] ?? 1));
        $per  = 10;
        $list = ALB_Payments::list($page, $per);
        $rows = $list['rows'];
        $total= $list['total'];
        $pages= max(1, (int)ceil($total/$per));

        echo '<div class="wrap"><h1>Історія платежів</h1>';
        echo '<p><button class="button alb-sync-bulk">Оновити всі відкриті</button></p>'; echo '<table class="widefat striped">';
        echo '<thead><tr>
                <th>ID</th><th>Дата</th><th>Сума, грн</th>
                <th>Статус</th><th>HPP ID</th><th>MRID</th>
                <th>Email</th><th>Ім’я</th><th>Прізвище</th><th>Призначення</th><th>Дії</th>
              </tr></thead><tbody>';
        if ($rows) {
            foreach ($rows as $r) {
                $uah = $r['amount_coin'] !== null ? number_format($r['amount_coin']/100, 2, '.', ' ') : '';
                echo '<tr>';
                echo '<td>'.(int)$r['id'].'</td>';
                echo '<td>'.esc_html($r['created_at']).'</td>';
                echo '<td>'.esc_html($uah).'</td>';
                echo '<td>'.esc_html($r['status']).'</td>';
                echo '<td><a href="https://status-pay.alb.ua/?hpp_id='.esc_html($r['hpp_order_id']).'" target="_blank">'.esc_html($r['hpp_order_id']).'</a></td>';
                echo '<td>'.esc_html($r['merchant_request_id']).'</td>';
                echo '<td>'.esc_html($r['customer_email']).'</td>';
                echo '<td>'.esc_html($r['customer_first_name']).'</td>';
                echo '<td>'.esc_html($r['customer_last_name']).'</td>';
                echo '<td>'.esc_html($r['purpose']).'</td>'; echo '<td><button class="button button-small alb-sync-one" data-id="'.(int)$r['id'].'">Оновити</button></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10">Записів немає</td></tr>';
        }
        echo '</tbody></table>';

        // пагінація
        if ($pages > 1) {
            echo '<p style="margin-top:10px">';
            for ($i=1; $i<=$pages; $i++) {
                $url = esc_url(add_query_arg(['page'=>'alb-hpp-payments','paged'=>$i], admin_url('admin.php')));
                echo $i==$page ? '<strong> '.$i.' </strong>' : ' <a href="'.$url.'">'.$i.'</a> ';
            }
            echo '</p>';
        }
		
        if (current_user_can('manage_options') && $rows && $opt['environment'] === 'test') {
			echo'<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'" 
                 onsubmit="return confirm(\'Дійсно видалити історію по всіх платежах? Цю дію не можна скасувати!\');" style="margin: 10px 0;">';
		    echo '<input type="hidden" name="action" value="alb_hpp_delete_all_payments">';
            wp_nonce_field('alb_hpp_delete_all_payments');
            submit_button('Очистити історію платежів', 'delete', 'submit', false);
            echo '</form>';
		}
		
		
		
        echo '</div>';
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const base = '<?php echo esc_js( rest_url('alb/v1') ); ?>';
  const nonce = '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>';
  async function post(url, body){
    const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, body: JSON.stringify(body||{})});
    return await r.json();
  }
  document.querySelectorAll('.alb-sync-one').forEach(btn=>{
     btn.addEventListener('click', async ()=>{
        btn.disabled = true; const old = btn.textContent; btn.textContent='...';
        const id = btn.getAttribute('data-id');
        const res = await post(base + '/sync-order', {id: Number(id)});
        btn.disabled = false; btn.textContent = old;
        if(res && res.ok){ location.reload(); } else { alert(res.error||'Помилка синхронізації'); }
     });
  });
  const bulk = document.querySelector('.alb-sync-bulk');
  if (bulk){
    bulk.addEventListener('click', async ()=>{
      bulk.disabled=true; const old=bulk.textContent; bulk.textContent='Оновлення...';
      const res = await post(base + '/sync-pending', {limit: 50});
      bulk.disabled=false; bulk.textContent=old;
      if(res && res.ok){ location.reload(); } else { alert(res.error||'Помилка синхронізації'); }
    });
  }
});
</script>
<?php

    }

	
}
ALB_Admin::init();
