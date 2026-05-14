<?php
/**
 * @author    AkStackPro
 * @copyright Copyright (c) 2026 AkStackPro (http://akstackpro.com/)
 * @package   AkStackPro_SalesOrdersCostReport
 */
declare(strict_types=1);

namespace AkStackPro\SalesOrdersCostReport\Model\ResourceModel\Report\Order\Updatedat;

/**
 * Sales Orders (updated_at) report collection - inherits cost-aware behaviour.
 */
class Collection extends \AkStackPro\SalesOrdersCostReport\Model\ResourceModel\Report\Order\Collection
{
    /**
     * Aggregated Data Table
     *
     * @var string
     */
    protected $_aggregationTable = 'sales_order_aggregated_updated';
}
