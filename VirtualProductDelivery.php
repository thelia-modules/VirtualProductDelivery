<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VirtualProductDelivery;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Install\Database;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Country;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModuleWithState;
use Thelia\Module\Exception\DeliveryException;

class VirtualProductDelivery extends AbstractDeliveryModuleWithState
{
    public const MESSAGE_DOMAIN = 'virtualproductdelivery';

    /** @var Translator */
    protected $translator;

    /**
     * The module is valid if the cart contains only virtual products.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return bool true if there is only virtual products in cart elsewhere false
     */
    public function isValidDelivery(Country $country, ?State $state = null): bool
    {
        $request = $this->getRequest();

        if (null === $request || !$request->hasSession()) {
            return false;
        }

        return $request->getSession()->getSessionCart($this->getDispatcher())->isVirtual();
    }

    public function getPostage(Country $country, ?State $state = null): OrderPostage|float
    {
        if (!$this->isValidDelivery($country, $state)) {
            throw new DeliveryException(
                $this->trans('This module cannot be used on the current cart.')
            );
        }

        return 0.0;
    }

    /**
     * This module manages virtual product delivery.
     *
     * @return bool
     */
    public function handleVirtualProductDelivery(): bool
    {
        return true;
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__.'/Config/update');

        $database = new Database($con);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // create new message
        if (null === MessageQuery::create()->findOneByName('mail_virtualproduct')) {
            $message = new Message();
            $message
                ->setName('mail_virtualproduct')
                ->setHtmlTemplateFileName('virtual-product-download.html')
                ->setHtmlLayoutFileName('')
                ->setTextTemplateFileName('virtual-product-download.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            $languages = LangQuery::create()->find();

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setSubject(
                    $this->trans('Order {{ order_ref }} validated. Download your files.', [], $locale)
                );
                $message->setTitle(
                    $this->trans('Virtual product download message', [], $locale)
                );
            }

            $message->save();
        }
    }

    protected function trans($id, $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, self::MESSAGE_DOMAIN, $locale);
    }
}
