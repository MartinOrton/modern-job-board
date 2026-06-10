<?php

use PHPUnit\Framework\TestCase;

class XmlImporterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_terms'] = array();
        $GLOBALS['mjb_test_companies_by_title'] = array();
        $GLOBALS['mjb_test_inserted_posts'] = array();
        $GLOBALS['mjb_test_next_post_id'] = 2000;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_remote_get_responses'] = array();
    }

    public function test_parse_xml_string_reads_mjb_feed_fields()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:mjb="https://github.com/MartinOrton/modern-job-board/ns/feed/1.0">
  <channel>
    <item>
      <title>Backend Engineer</title>
      <link>https://source.test/jobs/backend-engineer/</link>
      <guid isPermaLink="true">https://source.test/jobs/backend-engineer/</guid>
      <description>Short summary</description>
      <content:encoded><![CDATA[<p>Long description</p>]]></content:encoded>
      <mjb:company>Acme Corp</mjb:company>
      <mjb:location>Remote</mjb:location>
      <mjb:jobType>Full-time</mjb:jobType>
      <mjb:featured>1</mjb:featured>
    </item>
  </channel>
</rss>
XML;

        $parsed = MJB_Xml_Importer::parse_xml_string($xml);

        $this->assertIsArray($parsed);
        $this->assertCount(1, $parsed);
        $this->assertSame('Backend Engineer', $parsed[0]['title']);
        $this->assertSame('<p>Long description</p>', $parsed[0]['content']);
        $this->assertSame('Acme Corp', $parsed[0]['company']);
        $this->assertSame('Remote', $parsed[0]['location']);
        $this->assertSame('Full-time', $parsed[0]['type']);
        $this->assertTrue($parsed[0]['featured']);
        $this->assertSame('https://source.test/jobs/backend-engineer/', $parsed[0]['external_id']);
    }

    public function test_import_jobs_skips_duplicate_external_ids()
    {
        $job = array(
            'title' => 'Designer',
            'description' => 'Design things',
            'company' => 'Studio',
            'location' => 'London',
            'type' => 'Contract',
            'external_id' => 'designer-guid-1',
        );

        $first = MJB_Xml_Importer::import_jobs(array($job));
        $second = MJB_Xml_Importer::import_jobs(array($job));

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, $second['skipped']);
    }

    public function test_import_from_url_fetches_and_imports_feed()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Support Agent</title>
      <guid>support-agent-1</guid>
      <description>Help customers</description>
    </item>
  </channel>
</rss>
XML;

        $GLOBALS['mjb_test_remote_get_responses']['https://feeds.test/jobs.xml'] = array(
            'response' => array('code' => 200),
            'body' => $xml,
        );

        $result = MJB_Xml_Importer::import_from_url('https://feeds.test/jobs.xml');

        $this->assertIsArray($result);
        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_parse_xml_string_returns_error_for_invalid_xml()
    {
        $result = MJB_Xml_Importer::parse_xml_string('<rss><channel><item><title>Broken');

        $this->assertInstanceOf(WP_Error::class, $result);
    }
}