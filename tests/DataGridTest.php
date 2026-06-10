<?php

use PHPUnit\Framework\TestCase;

class DataGridTest extends TestCase
{
    public function test_renders_div_grid_without_table_markup()
    {
        ob_start();
        $grid = MJB_Data_Grid::begin('mjb-data-grid mjb-data-grid--dashboard', 2);
        $grid->render_header(array('Name', 'Count'))
            ->open_body()
            ->open_row()
            ->render_cell('Alpha', 'Name')
            ->render_cell('3', 'Count')
            ->close_row()
            ->close_body()
            ->end();
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-data-grid', $html);
        $this->assertStringContainsString('role="table"', $html);
        $this->assertStringContainsString('data-label="Name"', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('<tr', $html);
        $this->assertStringNotContainsString('<td', $html);
        $this->assertStringNotContainsString('<th', $html);
    }

    public function test_renders_empty_state_row()
    {
        ob_start();
        $grid = MJB_Data_Grid::begin('mjb-data-grid', 3);
        $grid->open_body()
            ->render_empty_row('Nothing here')
            ->close_body()
            ->end();
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-data-grid__cell--empty', $html);
        $this->assertStringContainsString('Nothing here', $html);
    }
}