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
          <label> <?php _e('Donation Amount','alb-hpp-donations');?> (<?php _e('UAH','alb-hpp-donations');?>) </label>
          <div class="alb-amounts">
            <?php foreach ([200,500,1000,2500,5000] as $a): ?>
              <button type="button" class="alb-btn-amount" data-amount="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></button>
            <?php endforeach; ?>
          </div>
           <input class="alb-input" type="number" min="100" step="10" name="amount" placeholder="<?php _e('Your amount, e.g.','alb-hpp-donations');?> 2400" required>          
        </div>
        <div class="alb-row">
            <label><?php _e('First name','alb-hpp-donations');?></label>
            <input class="alb-input" name="firstName">
        </div>
        <div class="alb-row">  
            <label><?php _e('Last name','alb-hpp-donations');?></label>
            <input class="alb-input" name="lastName">
        </div>
        <div class="alb-row">
          <label><?php _e('Email (for receipt)','alb-hpp-donations');?></label>
          <input class="alb-input" type="email" name="email">
        </div>
        <div class="alb-row">
          <label><?php _e('Comment (optional)','alb-hpp-donations');?></label>
          <input class="alb-input" name="purpose" placeholder="<?php _e('Charitable donation','alb-hpp-donations');?>">
        </div>
        <div class="alb-row">
          <button class="alb-btn-submit" type="submit"><?php _e('Donate','alb-hpp-donations');?></button>
        </div>
        <div class="alb-msg" style="margin-top:10px"></div>
      </form>
    </div>
 </div>
    <?php
    return ob_get_clean();
}


add_shortcode('alb_donate', 'alb_donate_shortcode');
