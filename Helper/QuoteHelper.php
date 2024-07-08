<?php

namespace Transbank\Webpay\Helper;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteFactory;

class QuoteHelper {
    private $cart;
    private $checkoutSession;
    private $quoteFactory;
    public function __construct(Cart $cart, Session $session, QuoteFactory $quoteFactory) {
        $this->cart = $cart;
        $this->checkoutSession = $session;
        $this->quoteFactory = $quoteFactory;
    }

    public function processQuoteForCancelOrder(int $quoteId)
    {
        $quote = $this->quoteFactory->create()->load($quoteId);
        if ($quote->getId()) {
            $quote->setIsActive(false);
            $quote->setReservedOrderId(null);
            $quote->save();

            $newQuote = $this->quoteFactory->create();
            $newQuote->merge($quote)
                ->setIsActive(true)
                ->setStoreId($quote->getStoreId())
                ->setCustomer($quote->getCustomer())
                ->save();

            $this->checkoutSession->replaceQuote($newQuote);
            $this->cart->setQuote($newQuote);
            $this->cart->saveQuote();
        }
    }
}
