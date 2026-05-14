<?php
/**
 * @author    AkStackPro
 * @copyright Copyright (c) 2026 AkStackPro (http://akstackpro.com/)
 * @package   AkStackPro_SalesOrdersCostReport
 */
declare(strict_types=1);

namespace AkStackPro\SalesOrdersCostReport\Model\ResourceModel\Report\Order;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\DateTime\Timezone\Validator;
use Magento\Reports\Model\FlagFactory;
use Psr\Log\LoggerInterface;

/**
 * Sales Orders aggregator (by created_at) extended with a per-period item cost column.
 *
 * The new "total_item_cost_amount" is SUM((qty_ordered - qty_canceled) * product_cost) over all
 * non-child order items in the period, converted to the global/base currency via base_to_global_rate.
 * Product cost is read live from the catalog `cost` attribute (catalog_product_entity_decimal,
 * admin/global scope).
 */
class Createdat extends \Magento\Sales\Model\ResourceModel\Report\Order\Createdat
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * Cached cost attribute id (per request lifecycle).
     *
     * @var int|null
     */
    private $costAttributeId;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        TimezoneInterface $localeDate,
        FlagFactory $reportsFlagFactory,
        Validator $timezoneValidator,
        DateTime $dateTime,
        EavConfig $eavConfig,
        $connectionName = null
    ) {
        parent::__construct(
            $context,
            $logger,
            $localeDate,
            $reportsFlagFactory,
            $timezoneValidator,
            $dateTime,
            $connectionName
        );
        $this->eavConfig = $eavConfig;
    }

    /**
     * Aggregate Orders data by custom field.
     *
     * Mirrors the parent implementation but adds a "total_item_cost_amount" column
     * (product cost * qty_ordered in base/global currency).
     *
     * @param string $aggregationField
     * @param string|int|\DateTime|array|null $from
     * @param string|int|\DateTime|array|null $to
     * @return $this
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _aggregateByField($aggregationField, $from, $to)
    {
        $connection = $this->getConnection();

        $connection->beginTransaction();
        try {
            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeSelect(
                    $this->getTable('sales_order'),
                    $aggregationField,
                    $aggregationField,
                    $from,
                    $to
                );
            } else {
                $subSelect = null;
            }
            $this->_clearTableByDateRange($this->getMainTable(), $from, $to, $subSelect);

            $periodExpr = $connection->getDatePartSql(
                $this->getStoreTZOffsetQuery(
                    ['o' => $this->getTable('sales_order')],
                    'o.' . $aggregationField,
                    $from,
                    $to
                )
            );

            $columns = [
                'period' => $periodExpr,
                'store_id' => 'o.store_id',
                'order_status' => 'o.status',
                'orders_count' => new \Zend_Db_Expr('COUNT(o.entity_id)'),
                'total_qty_ordered' => new \Zend_Db_Expr('SUM(oi.total_qty_ordered)'),
                'total_qty_invoiced' => new \Zend_Db_Expr('SUM(oi.total_qty_invoiced)'),
                'total_income_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $connection->getIfNullSql('o.base_grand_total', 0),
                        $connection->getIfNullSql('o.base_total_canceled', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_revenue_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s - %s - (%s - %s - %s)) * %s)',
                        $connection->getIfNullSql('o.base_total_invoiced', 0),
                        $connection->getIfNullSql('o.base_tax_invoiced', 0),
                        $connection->getIfNullSql('o.base_shipping_invoiced', 0),
                        $connection->getIfNullSql('o.base_total_refunded', 0),
                        $connection->getIfNullSql('o.base_tax_refunded', 0),
                        $connection->getIfNullSql('o.base_shipping_refunded', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_profit_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s - %s - %s - %s) * %s)',
                        $connection->getIfNullSql('o.base_total_paid', 0),
                        $connection->getIfNullSql('o.base_total_refunded', 0),
                        $connection->getIfNullSql('o.base_tax_invoiced', 0),
                        $connection->getIfNullSql('o.base_shipping_invoiced', 0),
                        $connection->getIfNullSql('o.base_total_invoiced_cost', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_invoiced_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $connection->getIfNullSql('o.base_total_invoiced', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_canceled_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $connection->getIfNullSql('o.base_total_canceled', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_paid_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $connection->getIfNullSql('o.base_total_paid', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_refunded_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $connection->getIfNullSql('o.base_total_refunded', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_tax_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $connection->getIfNullSql('o.base_tax_amount', 0),
                        $connection->getIfNullSql('o.base_tax_canceled', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_tax_amount_actual' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s -%s) * %s)',
                        $connection->getIfNullSql('o.base_tax_invoiced', 0),
                        $connection->getIfNullSql('o.base_tax_refunded', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_shipping_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $connection->getIfNullSql('o.base_shipping_amount', 0),
                        $connection->getIfNullSql('o.base_shipping_canceled', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_shipping_amount_actual' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $connection->getIfNullSql('o.base_shipping_invoiced', 0),
                        $connection->getIfNullSql('o.base_shipping_refunded', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_discount_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((ABS(%s) - %s) * %s)',
                        $connection->getIfNullSql('o.base_discount_amount', 0),
                        $connection->getIfNullSql('o.base_discount_canceled', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_discount_amount_actual' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM((%s - %s) * %s)',
                        $connection->getIfNullSql('o.base_discount_invoiced', 0),
                        $connection->getIfNullSql('o.base_discount_refunded', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
                'total_item_cost_amount' => new \Zend_Db_Expr(
                    sprintf(
                        'SUM(%s * %s)',
                        $connection->getIfNullSql('oi.total_item_cost', 0),
                        $connection->getIfNullSql('o.base_to_global_rate', 0)
                    )
                ),
            ];

            $select = $connection->select();
            $selectOrderItem = $connection->select();

            $qtyCanceledExpr = $connection->getIfNullSql('soi.qty_canceled', 0);
            $costValueExpr = $connection->getIfNullSql('cped.value', 0);

            $cols = [
                'order_id' => 'soi.order_id',
                'total_qty_ordered' => new \Zend_Db_Expr("SUM(soi.qty_ordered - {$qtyCanceledExpr})"),
                'total_qty_invoiced' => new \Zend_Db_Expr('SUM(soi.qty_invoiced)'),
                'total_item_cost' => new \Zend_Db_Expr(
                    "SUM((soi.qty_ordered - {$qtyCanceledExpr}) * {$costValueExpr})"
                ),
            ];

            $costAttributeId = $this->getCostAttributeId();
            $selectOrderItem->from(
                ['soi' => $this->getTable('sales_order_item')],
                $cols
            )->joinLeft(
                ['cped' => $this->getTable('catalog_product_entity_decimal')],
                $connection->quoteInto(
                    'cped.entity_id = soi.product_id AND cped.attribute_id = ? AND cped.store_id = 0',
                    $costAttributeId
                ),
                []
            )->where(
                'soi.parent_item_id IS NULL'
            )->group(
                'soi.order_id'
            );

            $select->from(
                ['o' => $this->getTable('sales_order')],
                $columns
            )->join(
                ['oi' => $selectOrderItem],
                'oi.order_id = o.entity_id',
                []
            )->where(
                'o.state NOT IN (?)',
                [\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, \Magento\Sales\Model\Order::STATE_NEW]
            );

            if ($subSelect !== null) {
                $select->having($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group([$periodExpr, 'o.store_id', 'o.status']);

            $connection->query($select->insertFromSelect($this->getMainTable(), array_keys($columns)));

            foreach ($columns as $k => $v) {
                $columns[$k] = new \Zend_Db_Expr('SUM(' . $k . ')');
            }
            $columns['period'] = 'period';
            $columns['store_id'] = new \Zend_Db_Expr(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
            $columns['order_status'] = 'order_status';

            $select->reset();
            $select->from($this->getMainTable(), $columns)->where('store_id <> 0');

            if ($subSelect !== null) {
                $select->where($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->group(['period', 'order_status']);
            $connection->query($select->insertFromSelect($this->getMainTable(), array_keys($columns)));
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Resolve the catalog `cost` attribute id (product entity).
     *
     * @return int
     */
    private function getCostAttributeId(): int
    {
        if ($this->costAttributeId === null) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, 'cost');
            $this->costAttributeId = $attribute && $attribute->getAttributeId()
                ? (int) $attribute->getAttributeId()
                : 0;
        }
        return $this->costAttributeId;
    }
}
