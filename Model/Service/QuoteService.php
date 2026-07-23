<?php

namespace Transbank\Webpay\Model\Service;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Transbank\Webpay\Exceptions\QuoteNotFoundException;
use Transbank\Webpay\Helper\PluginLogger;

/**
 * Class QuoteService
 * Quote administration shared by the plugin's controllers, backed by Magento's official Quote repository.
 */
class QuoteService
{
    protected $quoteRepository;
    protected $quoteFactory;
    protected $cart;
    protected $checkoutSession;
    protected $log;

    /**
     * Constructor
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteFactory            $quoteFactory
     * @param Cart                    $cart
     * @param Session                 $checkoutSession
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        QuoteFactory $quoteFactory,
        Cart $cart,
        Session $checkoutSession
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteFactory = $quoteFactory;
        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->log = new PluginLogger();
    }

    /**
     * Get a Quote by id.
     *
     * @param int $quoteId The quote id.
     *
     * @throws QuoteNotFoundException When no quote matches the given id.
     *
     * @return Quote
     */
    public function getById(int $quoteId): Quote
    {
        try {
            return $this->quoteRepository->get($quoteId);
        } catch (NoSuchEntityException $e) {
            throw new QuoteNotFoundException($e);
        }
    }

    /**
     * Persist a Quote.
     *
     * @param Quote $quote The quote to persist.
     *
     * @return Quote
     */
    public function save(Quote $quote): Quote
    {
        return $this->quoteRepository->save($quote);
    }

    /**
     * Set a quote's payment method, recollect its totals and persist it, in a single save.
     *
     * @param Quote  $quote  The quote to update.
     * @param string $method The payment method code to import.
     *
     * @return Quote
     */
    public function setPaymentMethod(Quote $quote, string $method): Quote
    {
        $quote->getPayment()->importData(['method' => $method]);
        $quote->collectTotals();

        return $this->quoteRepository->save($quote);
    }

    /**
     * Activate a quote and persist it.
     *
     * @param Quote $quote The quote to activate.
     *
     * @return Quote
     */
    public function activate(Quote $quote): Quote
    {
        $quote->setIsActive(true);

        return $this->quoteRepository->save($quote);
    }

    /**
     * Deactivate a quote and persist it.
     *
     * @param Quote $quote The quote to deactivate.
     *
     * @return Quote
     */
    public function deactivate(Quote $quote): Quote
    {
        $quote->setIsActive(false);

        return $this->quoteRepository->save($quote);
    }

    /**
     * Reactivate the cart after its order was canceled: retire the quote tied to that order and merge
     * it into a new active quote, replacing it in both the checkout session and the cart.
     * No-op (logged) if the quote no longer exists.
     *
     * @param int $quoteId The quote id tied to the canceled order.
     *
     * @return void
     */
    public function reactivateAfterOrderCancelByQuoteId(int $quoteId): void
    {
        try {
            $quote = $this->getById($quoteId);
            $quote->setIsActive(false);
            $quote->setReservedOrderId(null);
            $this->quoteRepository->save($quote);

            $newQuote = $this->quoteFactory->create();
            $newQuote->merge($quote)
                ->setIsActive(true)
                ->setStoreId($quote->getStoreId())
                ->setCustomer($quote->getCustomer());
            $this->quoteRepository->save($newQuote);

            $this->checkoutSession->replaceQuote($newQuote);
            $this->cart->setQuote($newQuote);
            $this->cart->saveQuote();
        } catch (QuoteNotFoundException $e) {
            $this->log->logWarning("No se pudo reactivar el carro, el quote {$quoteId} no existe.");
        }
    }
}
