<?php
/**
 * Div-based data grid renderer (replaces HTML tables).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Data_Grid
{
    /**
     * @var string
     */
    private $classes = 'mjb-data-grid';

    /**
     * @var int
     */
    private $column_count = 0;

    /**
     * @var bool
     */
    private $body_open = false;

    /**
     * Open a data grid container.
     *
     * @param string $classes
     * @param int    $column_count
     * @return self
     */
    public static function begin($classes = 'mjb-data-grid', $column_count = 0)
    {
        $grid = new self();
        $grid->classes = $classes;
        $grid->column_count = max(0, intval($column_count));

        $columns = $grid->column_count;

        if ($columns > 0) {
            printf(
                '<div class="%s" role="table" data-cols="%s">',
                esc_attr($classes),
                esc_attr((string) $columns)
            );
        } else {
            printf('<div class="%s" role="table">', esc_attr($classes));
        }

        return $grid;
    }

    /**
     * Render a header row from label strings.
     *
     * @param array<int, string> $labels
     * @return self
     */
    public function render_header($labels)
    {
        if ($this->column_count === 0) {
            $this->column_count = count($labels);
        }

        echo '<div class="mjb-data-grid__head" role="rowgroup">';
        echo '<div class="mjb-data-grid__row mjb-data-grid__row--head" role="row">';

        foreach ($labels as $label) {
            echo '<div class="mjb-data-grid__cell" role="columnheader">' . esc_html($label) . '</div>';
        }

        echo '</div></div>';

        return $this;
    }

    /**
     * Open the grid body.
     *
     * @return self
     */
    public function open_body()
    {
        echo '<div class="mjb-data-grid__body" role="rowgroup">';
        $this->body_open = true;

        return $this;
    }

    /**
     * Open a body row.
     *
     * @return self
     */
    public function open_row()
    {
        echo '<div class="mjb-data-grid__row" role="row">';

        return $this;
    }

    /**
     * Render a body cell. Caller must escape HTML content.
     *
     * @param string $content
     * @param string $label Optional mobile label (data-label attribute).
     * @return self
     */
    public function render_cell($content, $label = '')
    {
        if ($label !== '') {
            printf('<div class="mjb-data-grid__cell" role="cell" data-label="%s">', esc_attr($label));
        } else {
            echo '<div class="mjb-data-grid__cell" role="cell">';
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cell HTML is escaped by callers.
        echo $content;
        echo '</div>';

        return $this;
    }

    /**
     * Close the current row.
     *
     * @return self
     */
    public function close_row()
    {
        echo '</div>';

        return $this;
    }

    /**
     * Close the grid body.
     *
     * @return self
     */
    public function close_body()
    {
        if ($this->body_open) {
            echo '</div>';
            $this->body_open = false;
        }

        return $this;
    }

    /**
     * Close the grid container.
     *
     * @return void
     */
    public function end()
    {
        $this->close_body();
        echo '</div>';
    }

    /**
     * Render a full-width empty-state row.
     *
     * @param string $message
     * @return self
     */
    public function render_empty_row($message)
    {
        $this->open_row();
        echo '<div class="mjb-data-grid__cell mjb-data-grid__cell--empty" role="cell">' . esc_html($message) . '</div>';
        $this->close_row();

        return $this;
    }
}