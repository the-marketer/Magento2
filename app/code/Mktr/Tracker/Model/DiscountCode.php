<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 **/

namespace Mktr\Tracker\Model;

use Magento\SalesRule\Helper\Coupon;
use Magento\SalesRule\Model\Coupon\CodegeneratorInterface;
use Magento\SalesRule\Model\Rule;

class DiscountCode extends \Magento\Framework\DataObject implements CodegeneratorInterface
{
    const discountRules = [
        0 => "fixedValue",
        1 => "percentage",
        2 => "freeShipping"
    ];

    private static $NewCode = [];
    private static $generator = null;
    private static $cGroups;
    private static $sIds;
    private static $init = null;
    private static $in = [
        'Rule' => null,
        'Help' => null
    ];
    const PREFIX = 'MKTR-';
    const NAME = "MKTR-%s-%s";
    const DESCRIPTION = "Discount Code Generated through TheMarketer API";

    private static $ruleType;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Model\DiscountCode');
        }
        return self::$init;
    }

    /** TODO: Magento 2 */
    public static function getHelp()
    {
        if (self::$in["Help"] == null) {
            self::$in["Help"] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Mktr\Tracker\Helper\Data');
        }
        return self::$in["Help"];
    }

    public static function getRule()
    {
        if (self::$in['Rule'] == null) {
            self::$in['Rule'] = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\SalesRule\Model\Rule');
        }
        return self::$in['Rule'];
    }

    private static function getGenerator()
    {
        if (self::$generator === null)
        {
            $mktrGenerator = self::init();
            $mktrGenerator->setFormat(Coupon::COUPON_FORMAT_ALPHANUMERIC);
            $mktrGenerator->setLength(10);
            $mktrGenerator->setPrefix(self::PREFIX);
            $mktrGenerator->setType(Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED);

            self::$generator = $mktrGenerator;
        }
        return self::$generator;
    }

    public static function getNewCode($p)
    {
        self::$ruleType = self::discountRules[$p['type']];

        $name = vsprintf(self::NAME,[
                self::$ruleType,
                $p['value']
            ]).(isset($p['expiration_date']) ? '-' . $p['expiration_date'] : '');

        self::$NewCode[$name] = self::getRule()
            ->getCollection()
            ->addFieldToFilter('name', array('eq' => $name))
            ->getFirstItem();

        if (self::$NewCode[$name] === null || !self::$NewCode[$name]->getRuleId()) {
            self::$NewCode[$name] = self::getRule();

            if (self::$sIds === null) {
                $nIds = array();
                foreach (self::getHelp()->getStoreRepo->getList() as $website) {
                    if ($website->getCode() !== 'admin') {
                        $nIds[] = $website->getWebsiteId();
                    }
                }
                self::$sIds = $nIds;
            }
            if (self::$cGroups === null) {
                $nGroups = array();
                foreach (self::getHelp()->getCustomerGroup->getCollection()->toOptionHash() as $groupId => $n) {
                    $nGroups[] = $groupId;
                }
                self::$cGroups = $nGroups;
            }

            self::$NewCode[$name]->setCustomerGroupIds(self::$cGroups);
            self::$NewCode[$name]->setWebsiteIds(self::$sIds);
        }

        self::$NewCode[$name]->setName($name)
            ->setDescription(self::DESCRIPTION)
            ->setStopRulesProcessing(0)
            ->setFromDate(date('Y-m-d',strtotime( date('Y-m-d'). ' -1 day')))
            ->setIsActive(1)
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(1)
            ->setSortOrder(0)
            ->setDiscountAmount($p['value'])
            ->setDiscountQty(0)
            ->setDiscountStep(0)
            ->setApplyToShipping(0)
            ->setIsRss(0)
            ->setUseAutoGeneration(true);

        switch (self::$ruleType) {
            case 'percentage':
                self::$NewCode[$name]->setSimpleAction(Rule::BY_PERCENT_ACTION);
                break;
            case 'freeShipping':
                self::$NewCode[$name]->setSimpleAction(Rule::BY_PERCENT_ACTION);
                self::$NewCode[$name]->setSimpleFreeShipping(1);
                break;
            case 'fixedValue':
                self::$NewCode[$name]->setSimpleAction(Rule::CART_FIXED_ACTION);
                break;
        }

        self::$NewCode[$name]->setToDate($p['expiration_date'] ?? '');
        self::$NewCode[$name]->setCouponCodeGenerator(self::getGenerator());
        self::$NewCode[$name]->setCouponType(Rule::COUPON_TYPE_AUTO);
        self::$NewCode[$name]->setUseAutoGeneration(Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED);
        self::$NewCode[$name]->save();

        self::$NewCode[$name]->acquireCoupon(true);
        self::$NewCode[$name]->setCouponType(Rule::COUPON_TYPE_SPECIFIC);
        self::$NewCode[$name]->save();
        return self::$NewCode[$name];
    }

    /**
     * The minimum length of the default
     */
    const DEFAULT_LENGTH_MIN = 16;

    /**
     * The maximal length of the default
     */
    const DEFAULT_LENGTH_MAX = 32;

    /**
     * Collection of the default symbols
     */
    const SYMBOLS_COLLECTION = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Delimiter default
     */
    const DEFAULT_DELIMITER = '-';

    private $code = null;

    /**
     * Retrieve generated code
     *
     * @return string
     */
    public function generateCode()
    {
        $alphabet = $this->getAlphabet() ? $this->getAlphabet() : static::SYMBOLS_COLLECTION;
        $length = $this->getActualLength();

        $this->code = $this->getPrefix().'';
        for ($i = 0, $indexMax = strlen($alphabet) - 1; $i < $length; ++$i) {
            $this->code .= substr($alphabet, random_int(0, $indexMax), 1);
        }


        return $this->code;
    }
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Getting actual code length
     *
     * @return int
     */
    protected function getActualLength()
    {
        $lengthMin = $this->getLengthMin() ? $this->getLengthMin() : static::DEFAULT_LENGTH_MIN;
        $lengthMax = $this->getLengthMax() ? $this->getLengthMax() : static::DEFAULT_LENGTH_MAX;

        return $this->getLength() ? $this->getLength() : random_int($lengthMin, $lengthMax);
    }

    /**
     * Retrieve delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->hasData('delimiter') ? $this->getData('delimiter') : static::DEFAULT_DELIMITER;
    }
}
