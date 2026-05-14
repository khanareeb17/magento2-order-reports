<?php
/**
 * @author    AkStackPro
 * @copyright Copyright (c) 2026 AkStackPro (http://akstackpro.com/)
 * @package   AkStackPro_SalesOrdersCostReport
 */
declare(strict_types=1);

namespace AkStackPro\SalesOrdersCostReport\Block\Adminhtml\Sales\Sales;

use Magento\Reports\Block\Adminhtml\Grid\Column\Renderer\Currency;

/**
 * Sales Orders report grid extended to render a "Cost" column.
 *
 * The new column shows the aggregated product cost * qty ordered for the period and
 * is only visible when the user sets "Show Actual Values" to "Yes" in the report filter
 * (uses the same `visibility_filter` as the other actual-value columns).
 *
 * @SuppressWarnings(PHPMD.DepthOfInheritance)
 */
class Grid extends \Magento\Reports\Block\Adminhtml\Sales\Sales\Grid
{
    /**
     * @inheritDoc
     */
    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        $currencyCode = $this->getCurrentCurrencyCode();
        $rate = $this->getRate($currencyCode);

        $this->addColumn(
            'total_item_cost_amount',
            [
                'header' => __('Cost'),
                'type' => 'currency',
                'currency_code' => $currencyCode,
                'index' => 'total_item_cost_amount',
                'total' => 'sum',
                'sortable' => false,
                'renderer' => Currency::class,
                'visibility_filter' => ['show_actual_columns'],
                'rate' => $rate,
                'header_css_class' => 'col-cost',
                'column_css_class' => 'col-cost',
            ]
        );

        // Reorder only when both columns are actually present (i.e. "Show Actual Values" = Yes).
        // If either column is missing, calling sortColumnsByOrder() blows up on getNameInLayout().
        if ($this->getColumn('total_item_cost_amount') && $this->getColumn('total_revenue_amount')) {
            $this->addColumnsOrder('total_item_cost_amount', 'total_revenue_amount');
            $this->sortColumnsByOrder();
        }

        return $this;
    }
}
