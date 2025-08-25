(function($){
  $(document).on('click', '.alb-btn-amount', function(){
    $(this).closest('.alb-donate').find('input[name="amount"]').val($(this).data('amount'));
  });

  $(document).on('submit', '.alb-donate-form', async function(e){
    e.preventDefault();
    const $form = $(this), $msg = $form.find('.alb-msg').text('');

    const data = {
      amount:     parseInt($form.find('input[name="amount"]').val(), 10)||0,
      firstName:  $form.find('input[name="firstName"]').val()||'',
      lastName:   $form.find('input[name="lastName"]').val()||'',
      email:      $form.find('input[name="email"]').val()||'',
      purpose:    $form.find('input[name="purpose"]').val()||'',
    };

    try{
      const res = await fetch(ALB_HPP.endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (json.ok && json.redirectUrl) {
        window.location.href = json.redirectUrl;
      } else {
        $msg.text(json.error || 'Помилка створення платежу');
      }
    } catch(err){
      $msg.text(err.message || 'Помилка мережі');
    }
  });
})(jQuery);
