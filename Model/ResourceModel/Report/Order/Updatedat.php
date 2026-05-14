<?php
/**
 * @author    AkStackPro
 * @copyright Copyright (c) 2026 AkStackPro (http://akstackpro.com/)
 * @package   AkStackPro_SalesOrdersCostReport
 */
declare(strict_types=1);

namespace AkStackPro\SalesOrdersCostReport\Model\ResourceModel\Report\Order;

/**
 * Sales Orders aggregator (by updated_at) that inherits the cost-aware aggregation from Createdat.
 */
class Updatedat extends Createdat
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('sales_order_aggregated_updated', 'id');
    }

    /**
     * Aggregate Orders data by order updated at.
     *
     * @param string|int|\DateTime|array|null $from
     * @param string|int|\DateTime|array|null $to
     * @return $this
     */
    public function aggregate($from = null, $to = null)
    {
        return $this->_aggregateByField('updated_at', $from, $to);
    }
}
