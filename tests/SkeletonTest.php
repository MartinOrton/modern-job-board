<?php

use PHPUnit\Framework\TestCase;

class SkeletonTest extends TestCase
{
    public function test_jobs_skeleton_html_matches_card_layout()
    {
        $html = MJB_Shortcodes::get_jobs_skeleton_html(3);

        $this->assertStringContainsString('mjb-skeleton-list', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertSame(3, substr_count($html, 'mjb-skeleton-card'));
        $this->assertStringContainsString('mjb-skeleton--title', $html);
        $this->assertStringContainsString('mjb-skeleton--company', $html);
        $this->assertStringNotContainsString('mjb-skeleton--excerpt', $html);
        $this->assertStringContainsString('mjb-skeleton--pill', $html);
        $this->assertStringContainsString('mjb-skeleton-pagination', $html);
    }

    public function test_jobs_skeleton_count_is_clamped()
    {
        $this->assertSame(1, substr_count(MJB_Shortcodes::get_jobs_skeleton_html(0), 'mjb-skeleton-card'));
        $this->assertSame(8, substr_count(MJB_Shortcodes::get_jobs_skeleton_html(99), 'mjb-skeleton-card'));
    }

    public function test_recruiter_overview_skeleton_has_stats_and_actions()
    {
        $html = MJB_Dashboard::get_tab_skeleton_html('overview');

        $this->assertStringContainsString('mjb-skeleton-dashboard', $html);
        $this->assertSame(6, substr_count($html, 'mjb-skeleton-stat'));
        $this->assertStringContainsString('mjb-skeleton-chart', $html);
        $this->assertSame(4, substr_count($html, 'mjb-skeleton-action'));
    }

    public function test_recruiter_jobs_skeleton_uses_list_rows()
    {
        $html = MJB_Dashboard::get_tab_skeleton_html('jobs');

        $this->assertGreaterThanOrEqual(5, substr_count($html, 'mjb-skeleton-row'));
        $this->assertStringContainsString('mjb-skeleton-toolbar', $html);
    }
}
