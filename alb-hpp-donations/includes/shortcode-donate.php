<?php
if (!defined('ABSPATH')) exit;

function alb_donate_shortcode($atts = []) {
    wp_enqueue_style('alb-donate');
    wp_enqueue_script('alb-donate');

    ob_start(); ?>
 <div class="alb-donate-block">
    <div class="alb-donate">
      <form class="alb-donate-form">
        <div class="alb-row">
          <label>Сума (UAH)</label>
          <div class="alb-amounts">
            <?php foreach ([200,500,1000,2000,5000] as $a): ?>
              <button type="button" class="alb-btn-amount" data-amount="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></button>
            <?php endforeach; ?>
          </div>
          
           <input class="alb-input" type="number" min="10" step="10" name="amount" placeholder="Своя сума, напр. 300" required>
          
        </div>

        <div class="alb-row">
            <label>Ім’я</label>
            <input class="alb-input" name="firstName">
        </div>
        <div class="alb-row">  
            <label>Прізвище</label>
            <input class="alb-input" name="lastName">
        </div>

        <div class="alb-row">
          <label>Email (квитанція)</label>
          <input class="alb-input" type="email" name="email">
        </div>

        <div class="alb-row">
          <label>Коментар (необов’язково)</label>
          <input class="alb-input" name="purpose" placeholder="Благодійний внесок">
        </div>

        <div class="alb-row">
          <button class="alb-btn-submit" type="submit">Підтримати</button>
        </div>

        <div class="alb-msg" style="margin-top:10px"></div>
      </form>
    </div>
 </div>
    <?php
    return ob_get_clean();
}
add_shortcode('alb_donate', 'alb_donate_shortcode');
