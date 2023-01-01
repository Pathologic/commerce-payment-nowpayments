<?php
$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'nowpayments';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('nowpayments');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\Nowpayments($modx, $params);
        if (empty($params['title'])) {
            $params['title'] = $lang['nowpayments.caption'];
        }

        $commerce->registerPayment('nowpayments', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }

    case 'OnManagerBeforeOrderRender': {
        if (isset($params['groups']['payment_delivery']) && $isSelectedPayment) {
            $params['groups']['payment_delivery']['fields']['payment_link'] = [
                'title'   => $lang['nowpayments.link_caption'],
                'content' => function($data) use ($commerce) {
                    return $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
                },
                'sort' => 50,
            ];
        }

        break;
    }
    
    case 'OnBeforeCustomerNotifySending': {
        if (isset($params['partial_payment_status']) && $params['order']['status_id'] == $params['partial_payment_status']) {
            $params['data']['payment_link'] = $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
        }
    }
}
