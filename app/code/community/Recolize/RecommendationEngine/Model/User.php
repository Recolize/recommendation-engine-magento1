<?php
/**
 * Recolize GmbH
 *
 * @section LICENSE
 * This source file is subject to the GNU General Public License Version 3 (GPLv3).
 *
 * @category Recolize
 * @package Recolize_RecommendationEngine
 * @author Recolize GmbH <service@recolize.com>
 * @copyright 2015 Recolize GmbH (http://www.recolize.com)
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License Version 3 (GPLv3).
 */
class Recolize_RecommendationEngine_Model_User extends Mage_Core_Model_Abstract
{
    /**
     * Customer status for a new customer.
     *
     * @var string
     */
    const STATUS_NEW_CUSTOMER = 'new_customer';

    /**
     * Customer status for a returning customer.
     *
     * @var string
     */
    const STATUS_RETURNING_CUSTOMER = 'returning_customer';

    /**
     * Check if customer is logged in or not.
     *
     * @return boolean
     */
    public function isCustomerLoggedIn()
    {
        return $this->_getCustomerSession()->isLoggedIn();
    }

    /**
     * Get logged in customer id.
     *
     * @return integer
     */
    public function getCustomerId()
    {
        return $this->_getCustomerSession()->getId();
    }

    /**
     * Returns the default customer group.
     *
     * @return string the default customer group
     */
    public function getDefaultCustomerGroup()
    {
        $customerGroupCode = Mage::getModel('customer/group')->load(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)->getCustomerGroupCode();

        return $this->_replaceSpecialCharacters($customerGroupCode);
    }

    /**
     * Returns current customer group.
     *
     * @return string
     */
    public function getCustomerGroup()
    {
        $customerGroupId = $this->_getCustomerSession()->getCustomerGroupId();
        $customerGroupCode = Mage::getModel('customer/group')->load($customerGroupId)->getCustomerGroupCode();

        return $this->_replaceSpecialCharacters($customerGroupCode);
    }

    /**
     * Returns current customer status that is either taken from saved value in customer session or calculated via
     * last order.
     *
     * @return string
     */
    public function getCustomerStatus()
    {
        $customerStatus = self::STATUS_NEW_CUSTOMER;

        if ($this->isCustomerLoggedIn() === true) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('entity_id')
                ->addFieldToFilter('customer_id', $this->getCustomerId())
                ->setCurPage(1)
                ->setPageSize(1)
                ->getFirstItem();
            $lastOrderId = $order->getId();
        } else {
            $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        }

        if (empty($lastOrderId) === false) {
            $customerStatus = self::STATUS_RETURNING_CUSTOMER;
        }

        return $customerStatus;
    }

    /**
     * Return customer session model.
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Replaces special characters in a given string.
     *
     * @param string $text the text with possible special characters
     * @return string a cleaned text
     */
    protected function _replaceSpecialCharacters($text)
    {
        return str_replace('\'', '', $text);
    }
}