<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\View\Helper;

use Dewdrop\Admin\Component\CrudAbstract;
use Dewdrop\Fields;
use Dewdrop\Fields\Helper\SelectSort;
use Dewdrop\Fields\Listing;

/**
 * Render a table using classes and markup consistent with Boostrap's
 * documentation.  Note that this helper will wrap your table in a
 * .table-responsive div so that it is still reasonably usable on a
 * small screen.
 */
class BootstrapTable extends Table
{
    /**
     * Table wrapper's id HTML attribute.
     *
     * @var string
     */
    protected $tableWrapperId;

    /**
     * Open the table for rendering.  Notice the .table-responsive wrapper.
     *
     * @return string
     */
    public function open()
    {
        $tableWrapperIdAttr = '';

        if (null !== $this->tableWrapperId) {
            $tableWrapperIdAttr = ' id="' . $this->view->escapeHtmlAttr($this->tableWrapperId) . '"';
        }

        return <<<HTML
            <div class="table-responsive" {$tableWrapperIdAttr}><table class="bootstrap-table table table-hover">
HTML;
    }

    /**
     * Close the table itself and its .table-responsive wrapper.
     *
     * @return string
     */
    public function close()
    {
        return '</table></div>';
    }

    /**
     * Return the table wrapper's id HTML attribute.
     *
     * @return string|null
     */
    public function getTableWrapperId()
    {
        return $this->tableWrapperId;
    }

    /**
     * Set the table wrapper's id HTML attribute
     * @param string $tableWrapperId
     * @return BootstrapTable
     */
    public function setTableWrapperId($tableWrapperId)
    {
        $this->tableWrapperId = (string) $tableWrapperId;

        return $this;
    }

    /**
     * Render a sorting link for a particular column.  If the column is
     * currently selected, which we can detect using a SelectSort helper, we use the
     * carets provided in Bootstrap to indicate in which direction it is sorted.
     *
     * @param string $content
     * @param string $queryStringId
     * @param string $direction
     * @param SelectSort $sorter
     * @return string
     */
    protected function renderSortLink($content, $queryStringId, $direction, SelectSort $sorter = null)
    {
        $caret        = '';
        $sortedFields = ($sorter) ? $sorter->getSortedFields() : [];

        if (isset($sortedFields[$queryStringId])) {
            $activeDirection = $sortedFields[$queryStringId];

            if ('ASC' === $activeDirection) {
                $caret = ' <span class="caret caret-up"></span>';
            } else {
                $caret = '<span class="caret"></span>';
            }
        }

        /** @noinspection HtmlUnknownTarget */
        return sprintf(
            '<a href="%s">%s%s</a>',
            $this->view->escapeHtmlAttr($this->assembleSortUrl($queryStringId, $direction)),
            $this->view->escapeHtml($content),
            $caret
        );
    }
}
