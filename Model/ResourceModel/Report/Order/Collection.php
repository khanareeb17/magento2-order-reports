<?php
/**
 * @author    AkStackPro
 * @copyright Copyright (c) 2026 AkStackPro (http://akstackpro.com/)
 * @package   AkStackPro_SalesOrdersCostReport
 */
declare(strict_types=1);

namespace AkStackPro\SalesOrdersCostReport\Model\ResourceModel\Report\Order;

/**
 * Sales Orders report collection extended to expose the aggregated item cost column.
 */
class Collection extends \Magento\Sales\Model\ResourceModel\Report\Order\Collection
{
    /**
     * @inheritDoc
     */
    protected function _getSelectedColumns()
    {
        $columns = parent::_getSelectedColumns();

        if (!$this->isTotals() && !isset($columns['total_item_cost_amount'])) {
            $columns['total_item_cost_amount'] = 'SUM(total_item_cost_amount)';
        }

        return $columns;
    }
}
