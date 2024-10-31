<?php 
// Net Pay paypage v4.0

if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly
if (!defined('NETPAY_ACCESS')) return ; 

wp_enqueue_style( 'bootstrap', plugins_url('assets/bootstrap.min.css' , __FILE__ ) );
wp_enqueue_style( 'np-paypage', plugins_url('assets/paypage.css' , __FILE__ ) );
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">	
	<title>NetPay</title>
	<?php wp_head() ?>
</head>
<body onload="function $_GET(key) { var s = window.location.search; s = s.match(new RegExp(key + '=([^&=]+)')); return s ? unescape(s[1]) : false; } document.getElementById('expire').value = $_GET('expire'); document.getElementById('data').value = $_GET('data'); document.getElementById('auth').value = $_GET('auth'); if ($_GET('demo')) document.getElementById('paymentParams').setAttribute('action', 'https://demo.net2pay.ru/billingService/paypage/');">	
  <div class="container">
  <div class="row clearfix col-md-9">
    <div class="row clearfix" style="display: table; margin-top: 140px; margin-bottom: 40px;">
      <div class="col-md-4 col-md-offset-0 column" style="z-index: 1; display: table-cell; vertical-align: top; float: none; padding: 0px;">        
      </div>
      <div style="display: table-cell; float: none;" class="col-md-8 col-sm-9 column" id="content-inner">
        <div class="text-center"><img alt="" src="<?php echo plugins_url('assets/logo.png' , __FILE__ ) ?>" style="margin: 5px 0;"></div>
        <hr>
		
        <div class=" text-center">
          <p>Для оплаты заказа нажмите кнопку "Оплатить" и Вы будете перенаправлены на защищённую страницу компании Net Pay.</p><br>          
        </div>        

		<form id="paymentParams" name="doPayment" class="form-horizontal" action="https://my.net2pay.ru/billingService/paypage/" method="POST" onsubmit="return checkExp();">
          <fieldset style="background: none;padding-top:0;padding-bottom:0;">
            <div class="form-group">
              <div class="col-sm-4 text-center"><img alt="" src="<?php echo plugins_url('assets/ver-by-visa.png' , __FILE__ ) ?>"></div>
              <div class="col-sm-4">
				<input type="hidden" id="data" name="data" value="" />
				<input type="hidden" id="auth" name="auth" value="" />             
				<input type="hidden" id="expire" name="expire" value="" />  
                <button type="submit" class="btn btn-block btn-lg btn-success"><span class="glyphicon glyphicon-ok"></span> Оплатить</button>                  
              </div>
              <div class="col-sm-4 text-center"><img alt="" src="<?php echo plugins_url('assets/master-sec-code.png' , __FILE__ ) ?>" style="float:right;"></div>
            </div>
          </fieldset>
          <input type="hidden" id="cardNumberValue">
        </form>
		
        <div style="font-size: 11px;" class="text-muted text-center">
          <p>В системе Net Pay безопасность платежей и конфиденциальность введенной Вами информации обеспечивается использованием протокола SSL и другими средствами защиты информации.</p>
          <p>В случае возникновения вопросов, Вы можете обратиться в службу поддержки<br>
            по телефону +7 800 200 63 62 или по электронной почте support@net2pay.ru</p>
          <p align="center">CERTIFIED by PCI DSS <img src="<?php echo plugins_url('assets/pci.png' , __FILE__ ) ?>" style="display: inline;"></p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
