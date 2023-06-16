//<?php
/**
 * Payment NOWPayments
 *
 * NOWPayments payments processing
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender,OnBeforeCustomerNotifySending
 * @internal    @properties &title=Title;text; &api_key=API Key;text; &secret_key=Secret Key;text; &debug=Debug;list;No==0||Yes==1;0 &test=Test mode;list;No==0||Yes==1;0 &partial_payment_status=Partial payment status ID;text; &fee_paid_by_user=Fee is paid by user;list;No==0||Yes==1;0
 * @internal    @modx_category Commerce
 * @internal    @installset base
 */

return require MODX_BASE_PATH . 'assets/plugins/nowpayments/plugin.nowpayments.php';
