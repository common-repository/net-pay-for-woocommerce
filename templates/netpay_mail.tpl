<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
    <title>Новый Hotpatner</title>
    <style type="text/css">
        .ReadMsgBody {
            width: 100%;
            background-color: #f5f5f5;
        }
        .ExternalClass {
            width: 100%;
            line-height: 100%;
        }
        body {
            width: 100%;
            background-color: #e3e3e3;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        * {
            font-family: Verdana, sans-serif;
        }
        table {
            border-collapse: collapse;
        }
        #outlook a {
            padding:0;
        }
        body{
            width:100% !important;
            -webkit-text-size-adjust:100%;
            -ms-text-size-adjust:100%;
            margin:0;
            padding:0;
        }
        .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
            line-height: 100%;
        }
        #backgroundTable {
            margin:0;
            padding:0;
            width:100% !important;
            line-height: 100% !important;
        }
        img {
            outline:none;
            text-decoration:none;
            -ms-interpolation-mode: bicubic;
        }
        a img {
            border:none;
        }
        .image_fix {
            display:block;
        }
        p {
            margin: 1em 0;
        }
        table td {
            border-collapse: collapse;
        }
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        a {
            color: #404041;
            font-weight: normal;
            text-decoration: none;
        }
        a:hover {
            text-decoration: none !important;
        }
    </style>
</head>
<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" yahoo="fix" bgcolor="#fff">
<!-- PREHEADER -->
<!-- (Optional) This text will appear in the inbox preview, but not the email body. -->

<div style="font-family: Verdana,Arial,sans-serif !important; background: #e3e3e3; margin: 0 auto;">
    <br>
    <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#fff" align="center"
           style="border-radius: 10px 10px 0 0; margin: 0 auto; padding: 0; max-width: 700px; min-width:700px; ">
        <tbody>
        <tr>
            <td>
                <!-- HEADER -->
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#fff" align="center"
                       style="margin:0 auto; padding:0; max-width: 500px;">
                    <tbody>
                    <tr>
                        <td height="100" align="center"><img width="225" height="60"
                                                             src="{$assests}/email/logonetpay.png"
                                                             alt="Net Pay"></td>
                    </tr>
                    <tr>
                        <td id="colored-caption" align="center"
                            style="background: #279B27 url({$assests}/email/green-bar.png) no-repeat center center; height: 48px; padding: 0 10px;">
                            <span class="caption" style="color: #fff; font: bold 20px/17px Verdana,Arial,sans-serif;">Ссылка для оплаты</span>
                        </td>
                    </tr>
                    <!-- HELLOW -->
                    <tr>
                        <td valign="top" bgcolor="#fff" style="padding: 20px;"></td>
                    </tr>
                    </tbody>
                </table>
                <!-- LETTER -->
                <table width="100%" cellspacing="0" cellpadding="0" border="0"
                       style="padding: 0; margin: 0; background: #fff url({$assests}/email/envelope.jpg) no-repeat center top;">
                    <tbody>
                    <tr>
                        <td valign="top"
                            style="background: url({$assests}/email/grad.png) no-repeat center bottom; padding: 30px 20px;">
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center"
                                   style="max-width: 460px;word-wrap: break-word; word-break: break-all;">
                                <tbody>
                                <tr>
                                    <td valign="top" style="max-width:460px;">
                                        <!-- 1 COLUMT TEXT --> <span id="text-content"
                                                                     style="color: #000; font: 12px/18px Verdana,Arial,sans-serif; /*display: inline-block;*/ text-align: justify;">
                                            Идентификатор заказа: <b>{$order_id}</b>.<br>
                                            Сумма к оплате: <b>{$amount}</b> руб.<br><br>
                                            Для оплаты заказа, пожалуйста, нажмите кнопку «Перейти к оплате счета».</span><br><br>
                                        <p align="center"><a href="{$link}" style="text-decoration:none;">
                                                <span style="border-radius: 5px; background: #5cb85c; margin:5px; padding: 10px; height: 30px;font: bold 20px/20px Verdana,Arial,sans-serif; color:white;">Перейти к оплате счета</span>
                                            </a></p></span> <span
                                                style="color: #000; font: 12px/22px Verdana,Arial,sans-serif;"> <br> </span>
												
												<br> 
<span style="color: #000; font: 12px/18px Verdana,Arial,sans-serif; display: inline-block; text-align: justify;"><b style="font-size:-1" >Или скопируйте ссылку в адресную строку браузера:</b><br><code style="color: #000; border:1px solid #ddd;padding: 5px;display: inline-block;">{$link}</code>
                                        <br>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <table width="100%" cellspacing="0" cellpadding="0" border="0"
                       style="padding: 0; margin: 0; background: #fff url({$assests}/email/envelope.jpg) no-repeat center bottom; word-wrap: break-word; word-break: break-all;">
                    <tbody>
                    <tr id="footer-buyer">
                        <td align="center"
                            style="padding: 20px 0px;word-wrap: break-word; word-break: break-all;max-width:460px;">
                            <span id="footer-buyer-span"
                                  style="color: #000; font: 12px/18px Verdana,Arial,sans-serif; display: inline-block; max-width:460px; word-wrap: break-word; word-break: break-all;">  <br><br> Компания <a
                                        href="http://net2pay.ru/" style="color: #0054c9; text-decoration: underline;"
                                        target="_blank">Net Pay</a> является <br> сервисом онлайн-платежей.<br><br> </span><br>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 60px 0;"><span
                                    style="color: #000; font: 12px/18px Verdana,Arial,sans-serif;"> <span
                                        id="footer_line1">Мы круглосуточно</span> <br> <span id="footer_line2">ответим на все вопросы:</span> <br> <span
                                        id="phone">Телефон:</span> <a target="_blank"
                                                                      style="color: #000; text-decoration: none;"
                                                                      href="callto:8(800)2006362">8 (800) 200 63 62</a><br> E-mail: <a
                                        target="_blank" style="color: #0054c9; text-decoration: underline;"
                                        href="mailto:support@net2pay.ru">support@net2pay.ru</a> </span></td>
                    </tr>
                    </tbody>
                </table>
                <!--Footer -->
                <table width="100%" cellspacing="0" cellpadding="0" border="0"
                       style="background: #484f59 url({$assests}/email/footer.png) repeat scroll center bottom; margin: 10px 0 0; padding: 0;">
                    <tbody>
                    <tr>
                        <td style="padding: 15px 20px;">
                            <!--[if gte mso 9]>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td width="50%">
                            <![endif]--> <span style="float: left;"> <span
                                        style="color: #fff; font: 12px/14px Verdana,Arial,sans-serif; display: inline-block;"> <strong>© 2017 Net Pay. <br>All rights reserved.</strong> </span> </span>
                            <!--[if gte mso 9]>
                            </td>
                            <td width="50%" align="right">
                            <![endif]--> 
                            <!--[if gte mso 9]>
                            </td>
                            </tr>
                            </table>
                            <![endif]--> </td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        </tbody>
    </table>
    <br>
</div>
</body>
</html>