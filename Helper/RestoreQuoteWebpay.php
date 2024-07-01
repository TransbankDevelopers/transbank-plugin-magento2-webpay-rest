<?php

namespace Transbank\Webpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

class RestoreQuoteWebpay extends AbstractHelper
{
    protected $quoteFactory;
    protected $quoteRepository;
    protected $checkoutSession;
    protected $customerFactory;
    protected $customerRepository;

    public function __construct(
        Context $context,
        QuoteFactory $quoteFactory,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
    }

    public function replaceQuoteAfterRedirection($quote, $isGuest, $storeId)
    {
        $quote->setIsActive(false)->save();

        $newQuote = $this->quoteFactory->create();
        $newQuote->merge($quote);
        $newQuote->setIsActive(true)->collectTotals();

        if (!$isGuest) {
            $customer = $this->customerRepository->getById($quote->getCustomerId());
            $newQuote->assignCustomer($customer)->save();
            $newQuote->setStoreId($storeId);
        }

        $this->quoteRepository->save($newQuote);
        $this->checkoutSession->clearStorage();
        $this->checkoutSession->replaceQuote($newQuote);

        return $newQuote;
    }

    protected function restoreShippingInformation($oldQuote, $newQuote)
    {
        /*
        * Restore shipping information for guest customer
        */
        $oldAddress = $oldQuote->getShippingAddress();
        $newAddress = $newQuote->getShippingAddress();

        $newAddress->setEmail($oldAddress->getEmail());
        $newAddress->setFirstname($oldAddress->getFirstname());
        $newAddress->setLastname($oldAddress->getLastname());
        $newAddress->setCompany($oldAddress->getCompany());
        $newAddress->setStreetFull($oldAddress->getStreetFull());
        $newAddress->setCountryId($oldAddress->getCountryId());
        $newAddress->setCity($oldAddress->getCity());
        $newAddress->setRegion($oldAddress->getRegion());
        $newAddress->setRegionId($oldAddress->getRegionId());
        $newAddress->setTelephone($oldAddress->getTelephone());
        $newAddress->setPostcode($oldAddress->getPostcode());

        $this->quoteRepository->save($newQuote);
    }

    public function setGuestData($oldQuote)
    {
        $isGuest = $oldQuote->getCustomerIsGuest();

        if (!$isGuest) {
            $customerId = $oldQuote->getCustomerId();
            $newQuote = $this->checkoutSession->getQuote();
            $customer = $this->customerRepository->getById($customerId);
            $newQuote->assignCustomer($customer)->save();
        }
    }
}
